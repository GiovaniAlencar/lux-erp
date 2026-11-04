<?php

namespace App\Services;

use App\Models\ContaPagar;
use App\Models\Entregador;
use App\Models\EntregadorAcerto;
use App\Models\RotaEntrega;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Acerto do motoboy (SQL 39) — sem lançar nada à mão:
 *   - rota finalizada entra no acerto do motoboy dela: semanal = sábado a sexta (vence no sábado seguinte),
 *     diário = o próprio dia (motoboy avulso, recebe no dia);
 *   - o acerto mantém duas contas a pagar: repasse dos fretes (NÃO é despesa: o cliente pagou o frete)
 *     e extras do motoboy (2ª caixa, entrega que deu errado… — É despesa, entra no fechamento);
 *   - pagou as contas (aqui ou em Contas a pagar)? o acerto fica pago e as rotas marcadas como pagas ao motoboy.
 */
class AcertoMotoboy
{
    public static function ativo(): bool
    {
        static $ok = null;
        return $ok ??= Schema::hasTable('entregador_acertos') && Schema::hasColumn('rotas_entrega', 'acerto_id');
    }

    /** Período do acerto para a data da rota. */
    public static function periodo(string $tipo, Carbon $dia): array
    {
        $dia = $dia->copy()->startOfDay();
        if ($tipo === 'diario') {
            return [$dia, $dia->copy(), $dia->copy()];
        }
        // semana de sábado (6) a sexta
        $inicio = $dia->copy()->subDays(($dia->dayOfWeek - Carbon::SATURDAY + 7) % 7);
        $fim = $inicio->copy()->addDays(6);
        return [$inicio, $fim, $fim->copy()->addDay()];
    }

    /** Coloca (ou mantém) a rota no acerto certo e recalcula. Chame ao finalizar a rota. */
    public static function sincronizarRota(RotaEntrega $rota): ?EntregadorAcerto
    {
        if (!self::ativo()) {
            return null;
        }
        return DB::transaction(function () use ($rota) {
            $rota = RotaEntrega::whereKey($rota->id)->lockForUpdate()->first();
            if (!$rota) {
                return null;
            }
            $atual = $rota->acerto_id ? EntregadorAcerto::find($rota->acerto_id) : null;
            if ($atual && $atual->status === 'pago') {
                return $atual; // acerto fechado: não mexe
            }
            $motoboy = $rota->entregador_id ? Entregador::find($rota->entregador_id) : null;
            if ($rota->status !== 'finalizada' || !$motoboy || $rota->motoboy_pago) {
                if ($atual) {
                    $rota->acerto_id = null;
                    $rota->save();
                    self::recalcular($atual);
                }
                return null;
            }
            [$ini, $fim, $venc] = self::periodo($motoboy->acerto, Carbon::parse($rota->created_at));
            $certo = ($atual && (int) $atual->entregador_id === (int) $motoboy->id && $atual->periodo_inicio->eq($ini) && $atual->tipo === $motoboy->acerto) ? $atual : null;
            if (!$certo) {
                $certo = EntregadorAcerto::where('empresa_id', $rota->empresa_id)->where('entregador_id', $motoboy->id)
                    ->where('tipo', $motoboy->acerto)->whereDate('periodo_inicio', $ini)->where('status', 'aberto')->lockForUpdate()->first()
                    ?? EntregadorAcerto::create([
                        'empresa_id' => $rota->empresa_id, 'entregador_id' => $motoboy->id, 'tipo' => $motoboy->acerto,
                        'periodo_inicio' => $ini->format('Y-m-d'), 'periodo_fim' => $fim->format('Y-m-d'), 'vencimento' => $venc->format('Y-m-d'),
                        'status' => 'aberto',
                    ]);
            }
            if ((int) $rota->acerto_id !== (int) $certo->id) {
                $rota->acerto_id = $certo->id;
                $rota->save();
                if ($atual) {
                    self::recalcular($atual);
                }
            }
            return self::recalcular($certo);
        });
    }

    /** Soma fretes (entregas concluídas) e extras das rotas do acerto e acerta as duas contas a pagar. */
    public static function recalcular(EntregadorAcerto $a): EntregadorAcerto
    {
        return DB::transaction(function () use ($a) {
            $a = EntregadorAcerto::whereKey($a->id)->lockForUpdate()->first();
            $rotas = RotaEntrega::with('itens')->where('acerto_id', $a->id)->get();
            if ($a->status === 'pago') {
                return $a;
            }
            $fretes = round((float) $rotas->sum(fn ($r) => $r->itens->where('status_entrega', 'entregue')->sum('frete')), 2);
            $extras = round((float) $rotas->sum(fn ($r) => $r->itens->sum(fn ($i) => (float) $i->motoboy_extra)), 2);
            $a->valor_fretes = $fretes;
            $a->valor_extras = $extras;
            $nome = optional($a->motoboy)->nome ?: 'Motoboy';
            $quando = $a->tipo === 'diario' ? $a->periodo_inicio->format('d/m/Y') : 'semana ' . $a->periodo_inicio->format('d/m') . ' a ' . $a->periodo_fim->format('d/m');
            $a->conta_fretes_id = self::conta($a, $a->conta_fretes_id, $fretes, 'Entregas', false, 'Repasse de fretes · ' . $nome . ' · ' . $quando);
            $a->conta_extras_id = self::conta($a, $a->conta_extras_id, $extras, 'Extras do motoboy', true, 'Extras do motoboy · ' . $nome . ' · ' . $quando);
            $a->save();
            if ($rotas->isEmpty() && !$a->conta_fretes_id && !$a->conta_extras_id) {
                $a->delete(); // acerto vazio (a rota saiu dele)
                return $a;
            }
            return self::verificarPago($a);
        });
    }

    /** Cria/atualiza/remove a conta a pagar de uma parte do acerto. Retorna o id (ou null). */
    private static function conta(EntregadorAcerto $a, ?int $id, float $valor, string $categoria, bool $despesa, string $ref): ?int
    {
        $c = $id ? ContaPagar::where('empresa_id', $a->empresa_id)->find($id) : null;
        if ($c && ((int) $c->status === 1 || (float) $c->valor_pago > 0)) {
            return $c->id; // já teve pagamento: não muda mais o valor
        }
        if ($valor <= 0) {
            if ($c) {
                $c->delete();
            }
            return null;
        }
        $dados = [
            'empresa_id' => $a->empresa_id,
            'categoria_id' => Financeiro::categoriaPagar($a->empresa_id, $categoria),
            'fornecedor_id' => 0,
            'referencia' => mb_substr($ref, 0, 255),
            'valor_integral' => $valor,
            'data_vencimento' => $a->vencimento->format('Y-m-d'),
            'data_pagamento' => $a->vencimento->format('Y-m-d'),
            'tipo_pagamento' => null,
            'filial_id' => null,
        ];
        if (Schema::hasColumn('conta_pagars', 'despesa')) {
            $dados['despesa'] = $despesa ? 1 : 0;
        }
        if ($c) {
            $c->fill($dados)->save();
            return $c->id;
        }
        return ContaPagar::create($dados + ['valor_pago' => 0, 'status' => 0])->id;
    }

    /** As contas do acerto foram pagas (aqui ou em Contas a pagar)? fecha o acerto e marca as rotas. */
    public static function verificarPago(EntregadorAcerto $a): EntregadorAcerto
    {
        if ($a->status === 'pago') {
            return $a;
        }
        $ids = array_filter([$a->conta_fretes_id, $a->conta_extras_id]);
        if (!$ids) {
            return $a;
        }
        $abertas = ContaPagar::whereIn('id', $ids)->where('status', '!=', 1)->count();
        if ($abertas === 0) {
            $a->status = 'pago';
            $a->pago_em = now();
            $a->save();
            RotaEntrega::where('acerto_id', $a->id)->update(['motoboy_pago' => 1, 'motoboy_pago_em' => now(), 'motoboy_pago_usuario_id' => get_id_user() ?: null]);
        }
        return $a;
    }

    /** Paga o acerto inteiro saindo de uma conta (uma saída por conta a pagar, cada uma com a sua categoria). */
    public static function pagar(EntregadorAcerto $a, \App\Models\FinConta $conta, string $data, ?string $forma = null): EntregadorAcerto
    {
        return DB::transaction(function () use ($a, $conta, $data, $forma) {
            $a = self::recalcular($a);
            foreach (array_filter([$a->conta_fretes_id, $a->conta_extras_id]) as $id) {
                $t = ContaPagar::find($id);
                if ($t && (int) $t->status !== 1 && ($resta = Financeiro::restante($t)) > 0) {
                    Financeiro::pagar($t, $resta, $data, $conta, ['forma_pagamento' => $forma]);
                }
            }
            return self::verificarPago($a->fresh());
        });
    }
}
