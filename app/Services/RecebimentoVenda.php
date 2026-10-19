<?php

namespace App\Services;

use App\Models\CategoriaConta;
use App\Models\ContaReceber;
use App\Models\Venda;
use App\Models\VendaComprovante;
use Illuminate\Support\Facades\Schema;

/**
 * Mantém as "contas a receber" de uma venda de acordo com a realidade:
 *
 * 1) Se as parcelas lançadas na venda somam MENOS que o total (ex.: venda de 3.000 com só 1.000
 *    lançado, o resto "na entrega"), cria uma conta a receber com o RESTANTE, para nada ficar esquecido.
 * 2) Os comprovantes anexados na venda dão baixa nessas contas, da mais antiga para a mais nova:
 *    parcial quando cobre parte, recebida quando cobre tudo. Excluir o comprovante desfaz a baixa.
 *
 * É idempotente: pode chamar quantas vezes quiser (ao salvar a venda e a cada comprovante).
 */
class RecebimentoVenda
{
    /** marcador da conta "restante" criada automaticamente */
    public const MARCA_RESTANTE = '[restante automático]';

    public static function totalVenda(Venda $v): float
    {
        return round((float) $v->valor_total - (float) $v->desconto + (float) $v->acrescimo + (float) ($v->getAttributes()['frete'] ?? 0), 2);
    }

    /** @param bool $forcarBaixas true depois de excluir um comprovante (refaz as baixas mesmo sem comprovantes) */
    public static function sincronizar(Venda $v, bool $forcarBaixas = false): void
    {
        $v = Venda::find($v->id) ?? $v;
        $contas = ContaReceber::where('empresa_id', $v->empresa_id)->where('venda_id', $v->id)->orderBy('data_vencimento')->orderBy('id')->get();

        // venda à vista sem parcelas a receber: nada a controlar aqui
        if ($contas->isEmpty() && $v->forma_pagamento === 'a_vista') {
            return;
        }

        // 1) restante: total da venda − parcelas lançadas (sem contar o próprio restante)
        $restante = $contas->first(fn ($c) => str_contains((string) $c->observacao, self::MARCA_RESTANTE));
        $parcelas = $contas->reject(fn ($c) => $restante && $c->id === $restante->id);
        $falta = round(self::totalVenda($v) - (float) $parcelas->sum('valor_integral'), 2);

        if ($falta > 0.009 && $parcelas->isNotEmpty()) {
            $venc = $v->data_entrega ?: ($parcelas->max('data_vencimento') ?: date('Y-m-d'));
            if (!$restante) {
                $restante = new ContaReceber([
                    'venda_id' => $v->id,
                    'cliente_id' => $v->cliente_id,
                    'empresa_id' => $v->empresa_id,
                    'categoria_id' => optional(CategoriaConta::where('empresa_id', $v->empresa_id)->where('tipo', 'receber')->first())->id,
                    'referencia' => 'Restante da venda #' . $v->id . ' (a receber)',
                    'valor_recebido' => 0,
                    'status' => 0,
                    'juros' => 0,
                    'multa' => 0,
                    'tipo_pagamento' => $v->tipo_pagamento,
                    'observacao' => self::MARCA_RESTANTE . ' parcelas lançadas não cobrem o total da venda',
                    'filial_id' => $v->filial_id ?? null,
                ]);
            }
            $restante->valor_integral = $falta;
            $restante->data_vencimento = $restante->data_vencimento ?: $venc;
            $restante->data_recebimento = $restante->data_recebimento ?: $restante->data_vencimento;
            $restante->save();
        } elseif ($restante) {
            $restante->delete();
        }

        self::aplicarComprovantes($v, $forcarBaixas);
    }

    /** Distribui o valor dos comprovantes nas contas a receber da venda (mais antiga primeiro). */
    public static function aplicarComprovantes(Venda $v, bool $forcar = false): void
    {
        if (!Schema::hasTable('venda_comprovantes')) {
            return;
        }
        $comps = VendaComprovante::where('empresa_id', $v->empresa_id)->where('venda_id', $v->id)->whereNotNull('valor')->get();
        // venda sem comprovante: não mexe (pode ter sido recebida direto em Contas a receber)
        if ($comps->isEmpty() && !$forcar) {
            return;
        }
        $disponivel = round((float) $comps->sum('valor'), 2);
        $temData = Schema::hasColumn('venda_comprovantes', 'data_pagamento');
        $ultimaData = $temData ? optional($comps->max('data_pagamento'))->format('Y-m-d') : null;
        $ultimaData = $ultimaData ?: optional($comps->max('created_at'))->format('Y-m-d') ?: date('Y-m-d');

        $contas = ContaReceber::where('empresa_id', $v->empresa_id)->where('venda_id', $v->id)
            ->orderByRaw('CASE WHEN observacao LIKE ? THEN 1 ELSE 0 END', ['%' . self::MARCA_RESTANTE . '%'])
            ->orderBy('data_vencimento')->orderBy('id')->get();

        foreach ($contas as $c) {
            $integral = round((float) $c->valor_integral, 2);
            $aplica = round(min($integral, max(0, $disponivel)), 2);
            $disponivel = round($disponivel - $aplica, 2);
            $quitada = $aplica > 0 && $aplica + 0.009 >= $integral;
            $c->valor_recebido = $aplica;
            $c->status = $quitada ? 1 : 0;
            if ($aplica > 0) {
                $c->data_recebimento = $ultimaData;
            }
            if ($c->isDirty()) {
                $c->save();
            }
        }
    }
}
