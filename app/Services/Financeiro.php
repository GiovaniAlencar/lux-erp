<?php

namespace App\Services;

use App\Models\CategoriaConta;
use App\Models\ContaPagar;
use App\Models\FinConta;
use App\Models\FinMovimento;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Regras do financeiro: baixa (total ou parcial) de contas a pagar, lançamentos avulsos,
 * transferências entre contas e estorno. Tudo passa pelo extrato (fin_movimentos).
 */
class Financeiro
{
    public const COMPROVANTE_MIMES = 'jpg,jpeg,png,webp,pdf';
    public const COMPROVANTE_MAX_KB = 8192;

    /** Guarda o comprovante fora da pasta pública (só sai pelo controller, com login). */
    public static function salvarComprovante(?UploadedFile $arq, $empresaId): ?string
    {
        if (!$arq || !$arq->isValid()) {
            return null;
        }
        $ext = strtolower($arq->getClientOriginalExtension() ?: $arq->extension());
        if (!in_array($ext, explode(',', self::COMPROVANTE_MIMES), true)) {
            return null;
        }
        return $arq->storeAs('comprovantes/' . (int) $empresaId . '/' . date('Y-m'), Str::uuid() . '.' . $ext, 'local');
    }

    /**
     * Lê um valor em reais digitado do jeito que a pessoa escreve:
     * "150" → 150 · "150,5" → 150,50 · "1.500" → 1500 · "1.500,00" → 1500 · "R$ 2.480,90" → 2480,90.
     */
    public static function valorBr($v): float
    {
        $s = preg_replace('/[^\d,.\-]/', '', (string) $v);
        if ($s === '' || $s === '-') {
            return 0.0;
        }
        if (str_contains($s, ',')) {
            $s = str_replace(',', '.', str_replace('.', '', $s));
        } elseif (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $s)) {
            $s = str_replace('.', '', $s); // só separador de milhar
        }
        return round((float) $s, 2);
    }

    /** Valor que ainda falta pagar no título. */
    public static function restante(ContaPagar $c): float
    {
        return max(0, round((float) $c->valor_integral - (float) $c->valor_pago, 2));
    }

    /**
     * Dá baixa (total ou parcial) num contas a pagar: cria a saída na conta escolhida,
     * soma no valor pago e marca como pago quando quitar.
     */
    public static function pagar(ContaPagar $titulo, float $valor, string $data, FinConta $conta, array $extra = []): FinMovimento
    {
        if ($valor <= 0) {
            throw new \InvalidArgumentException('Informe um valor maior que zero.');
        }
        return DB::transaction(function () use ($titulo, $valor, $data, $conta, $extra) {
            $t = ContaPagar::whereKey($titulo->id)->lockForUpdate()->firstOrFail();
            $falta = self::restante($t);
            if ($valor > $falta + 0.009) {
                throw new \InvalidArgumentException('o valor (R$ ' . __moeda($valor) . ') é maior que o que falta pagar (R$ ' . __moeda($falta) . '). Se houve juros ou multa, ajuste o valor da conta antes.');
            }
            $mov = FinMovimento::create([
                'empresa_id' => $t->empresa_id,
                'conta_id' => $conta->id,
                'tipo' => 'saida',
                'valor' => round($valor, 2),
                'data' => $data,
                'descricao' => mb_substr('Pagamento: ' . $t->referencia, 0, 255),
                'origem' => 'pagamento',
                'categoria_id' => $t->categoria_id,
                'forma_pagamento' => $extra['forma_pagamento'] ?? null,
                'conta_pagar_id' => $t->id,
                'comprovante' => $extra['comprovante'] ?? null,
                'usuario_id' => get_id_user(),
            ]);
            self::recalcularTitulo($t, $extra['forma_pagamento'] ?? null);
            return $mov;
        });
    }

    /** Recalcula valor pago / situação do título a partir das baixas do extrato. */
    public static function recalcularTitulo(ContaPagar $t, ?string $forma = null): void
    {
        $baixas = FinMovimento::where('conta_pagar_id', $t->id)->where('tipo', 'saida');
        $pago = round((float) (clone $baixas)->sum('valor'), 2);
        $ultima = (clone $baixas)->max('data');
        // títulos pagos antes do extrato existir (sem baixas) não são mexidos
        if ($pago <= 0 && $t->status && !FinMovimento::where('conta_pagar_id', $t->id)->exists()) {
            return;
        }
        $t->valor_pago = $pago;
        $t->status = $pago + 0.004 >= (float) $t->valor_integral;
        if ($ultima) {
            $t->data_pagamento = $ultima;
        }
        if ($forma) {
            $t->tipo_pagamento = mb_substr($forma, 0, 20);
        }
        $t->save();
    }

    /** Desfaz um lançamento do extrato (e devolve o valor ao título, se for pagamento). */
    public static function estornar(FinMovimento $m): void
    {
        DB::transaction(function () use ($m) {
            $tituloId = $m->conta_pagar_id;
            // se veio de um comprovante de venda, o comprovante continua anexado, mas sem lançamento
            if (\Illuminate\Support\Facades\Schema::hasColumn('venda_comprovantes', 'fin_movimento_id')) {
                DB::table('venda_comprovantes')->where('fin_movimento_id', $m->id)->update(['fin_movimento_id' => null]);
            }
            if ($m->transferencia) {
                FinMovimento::where('transferencia', $m->transferencia)->where('empresa_id', $m->empresa_id)->delete();
            } else {
                $m->delete();
            }
            if ($tituloId && ($t = ContaPagar::whereKey($tituloId)->lockForUpdate()->first())) {
                $t->valor_pago = round((float) FinMovimento::where('conta_pagar_id', $t->id)->where('tipo', 'saida')->sum('valor'), 2);
                $t->status = $t->valor_pago > 0 && $t->valor_pago + 0.004 >= (float) $t->valor_integral;
                $t->save();
            }
        });
    }

    /** Transferência entre contas: saída numa, entrada na outra, ligadas pelo mesmo código. */
    public static function transferir(FinConta $de, FinConta $para, float $valor, string $data, string $obs = ''): void
    {
        $cod = (string) Str::uuid();
        $desc = mb_substr('Transferência ' . $de->nome . ' → ' . $para->nome . ($obs !== '' ? ' · ' . $obs : ''), 0, 255);
        DB::transaction(function () use ($de, $para, $valor, $data, $cod, $desc) {
            foreach ([[$de, 'saida'], [$para, 'entrada']] as [$c, $tipo]) {
                FinMovimento::create([
                    'empresa_id' => $c->empresa_id, 'conta_id' => $c->id, 'tipo' => $tipo, 'valor' => round($valor, 2),
                    'data' => $data, 'descricao' => $desc, 'origem' => 'transferencia', 'transferencia' => $cod,
                    'usuario_id' => get_id_user(),
                ]);
            }
        });
    }

    /** Categoria de contas a pagar pelo nome (cria se não existir). */
    public static function categoriaPagar($empresaId, string $nome): int
    {
        $c = CategoriaConta::firstOrCreate(['empresa_id' => $empresaId, 'tipo' => 'pagar', 'nome' => mb_substr($nome, 0, 50)]);
        return (int) $c->id;
    }
}
