<?php

namespace App\Services;

use App\Models\CompraPedido;
use App\Models\FinConta;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fechamento do mês (o relatório que era feito à mão):
 *
 *   vendas do mês → lucro bruto (vendas − custo dos produtos vendidos)
 *   − despesas (contas a pagar marcadas como despesa + lançamentos avulsos de despesa), por categoria
 *   = líquido → (− reinvestimento) → divisão entre sócios (% do mês) − retiradas de cada um
 *
 *   posição da loja no fim do mês:
 *   saldo das contas + custo do estoque + contas a receber + pedidos de compra a caminho (já pagos)
 *   + pendências com fornecedor − contas a pagar − parte dos sócios a pagar ± valores extras.
 */
class FechamentoMes
{
    public static function ativo(): bool
    {
        static $ok = null;
        return $ok ??= Schema::hasTable('fechamentos_mes');
    }

    /** Comprovantes de cartão do mês: valor abatido da venda − líquido que entrou na conta da maquininha. */
    public static function taxasCartao(int $empresaId, string $ini, string $fim): array
    {
        if (!Schema::hasTable('venda_comprovantes') || !Schema::hasColumn('venda_comprovantes', 'fin_movimento_id')) {
            return [];
        }
        $temData = Schema::hasColumn('venda_comprovantes', 'data_pagamento');
        $data = $temData ? 'COALESCE(c.data_pagamento, DATE(c.created_at))' : 'DATE(c.created_at)';
        return DB::table('venda_comprovantes as c')
            ->join('fin_movimentos as m', 'm.id', '=', 'c.fin_movimento_id')
            ->join('fin_contas as k', 'k.id', '=', 'm.conta_id')
            ->where('c.empresa_id', $empresaId)->where('k.tipo', 'cartao')
            ->whereRaw("$data BETWEEN ? AND ?", [$ini, $fim])
            ->whereRaw('c.valor > m.valor')
            ->orderByRaw($data)
            ->get([DB::raw("$data as data"), 'c.venda_id', 'c.valor', 'm.valor as liquido'])
            ->map(fn ($r) => [
                'data' => Carbon::parse($r->data)->format('d/m/Y'),
                'descricao' => 'Venda #' . $r->venda_id . ' · R$ ' . number_format((float) $r->valor, 2, ',', '.') . ' → líquido R$ ' . number_format((float) $r->liquido, 2, ',', '.'),
                'valor' => round((float) $r->valor - (float) $r->liquido, 2),
            ])->all();
    }

    /** @param array $e entradas informadas (socios, reinvestimento, extras) */
    public static function calcular(int $empresaId, string $mes, array $e = []): array
    {
        $ini = Carbon::createFromFormat('Y-m-d', $mes . '-01')->startOfDay();
        $fim = $ini->copy()->endOfMonth();
        $iniS = $ini->format('Y-m-d');
        $fimS = $fim->format('Y-m-d');

        // ---------- vendas e custo ----------
        $dataVenda = 'COALESCE(v.data_registro, v.created_at)';
        $vendas = DB::table('vendas as v')->where('v.empresa_id', $empresaId)
            ->whereRaw("$dataVenda BETWEEN ? AND ?", [$iniS . ' 00:00:00', $fimS . ' 23:59:59'])
            ->where(fn ($q) => $q->whereNull('v.estado_emissao')->orWhere('v.estado_emissao', '!=', 'cancelado'))
            ->when(Schema::hasColumn('vendas', 'status_pedido'), fn ($q) => $q->where(fn ($w) => $w->whereNull('v.status_pedido')->orWhere('v.status_pedido', '!=', 'cancelada')));
        $idsVendas = (clone $vendas)->pluck('v.id');
        $fatPedidos = (float) (clone $vendas)->selectRaw('COALESCE(SUM(v.valor_total - COALESCE(v.desconto,0) + COALESCE(v.acrescimo,0)), 0) s')->value('s');
        $cmvPedidos = $idsVendas->isEmpty() ? 0.0 : (float) DB::table('item_vendas as i')->join('produtos as p', 'p.id', '=', 'i.produto_id')
            ->whereIn('i.venda_id', $idsVendas)
            ->selectRaw('COALESCE(SUM(i.quantidade * COALESCE(NULLIF(i.valor_custo, 0), p.valor_compra, 0)), 0) s')->value('s');

        // vendas do PDV (frente de caixa)
        $pdv = DB::table('venda_caixas as v')->where('v.empresa_id', $empresaId)
            ->whereRaw("$dataVenda BETWEEN ? AND ?", [$iniS . ' 00:00:00', $fimS . ' 23:59:59'])
            ->where(fn ($q) => $q->whereNull('v.estado_emissao')->orWhere('v.estado_emissao', '!=', 'cancelado'))
            ->where(fn ($q) => $q->whereNull('v.rascunho')->orWhere('v.rascunho', 0));
        $idsPdv = (clone $pdv)->pluck('v.id');
        $fatPdv = (float) (clone $pdv)->selectRaw('COALESCE(SUM(v.valor_total), 0) s')->value('s');
        $cmvPdv = $idsPdv->isEmpty() ? 0.0 : (float) DB::table('item_venda_caixas as i')->join('produtos as p', 'p.id', '=', 'i.produto_id')
            ->whereIn('i.venda_caixa_id', $idsPdv)
            ->selectRaw('COALESCE(SUM(i.quantidade * COALESCE(NULLIF(i.valor_custo, 0), p.valor_compra, 0)), 0) s')->value('s');

        $faturamento = round($fatPedidos + $fatPdv, 2);
        $cmv = round($cmvPedidos + $cmvPdv, 2);
        $lucroBruto = round($faturamento - $cmv, 2);

        // ---------- despesas por categoria ----------
        $temDespesaFlag = Schema::hasColumn('conta_pagars', 'despesa');
        $cp = DB::table('conta_pagars as c')->leftJoin('categoria_contas as k', 'k.id', '=', 'c.categoria_id')
            ->where('c.empresa_id', $empresaId)->whereBetween('c.data_vencimento', [$iniS, $fimS])
            ->when($temDespesaFlag, fn ($q) => $q->where('c.despesa', 1), fn ($q) => $q->where('k.despesa', 1))
            ->get(['c.id', 'c.referencia', 'c.valor_integral', 'c.data_vencimento', 'c.status', 'k.nome as categoria']);
        $avulsas = Schema::hasTable('fin_movimentos') ? DB::table('fin_movimentos as m')->join('categoria_contas as k', 'k.id', '=', 'm.categoria_id')
            ->where('m.empresa_id', $empresaId)->where('m.tipo', 'saida')->whereNull('m.conta_pagar_id')
            ->where('m.origem', '!=', 'transferencia')->where('k.despesa', 1)
            ->whereBetween('m.data', [$iniS, $fimS])
            ->get(['m.id', 'm.descricao as referencia', 'm.valor as valor_integral', 'm.data as data_vencimento', 'k.nome as categoria']) : collect();

        $grupos = [];
        foreach ($cp->concat($avulsas) as $d) {
            $cat = $d->categoria ?: 'Sem categoria';
            $grupos[$cat] ??= ['categoria' => $cat, 'total' => 0, 'itens' => []];
            $grupos[$cat]['total'] = round($grupos[$cat]['total'] + (float) $d->valor_integral, 2);
            $grupos[$cat]['itens'][] = [
                'data' => $d->data_vencimento ? Carbon::parse($d->data_vencimento)->format('d/m/Y') : null,
                'descricao' => (string) $d->referencia,
                'valor' => round((float) $d->valor_integral, 2),
                'pago' => isset($d->status) ? (bool) $d->status : true,
            ];
        }
        // taxa da maquininha: o cliente paga o acréscimo (entra no faturamento), a intermediadora repassa o líquido.
        // A diferença é custo de verdade, calculada sozinha a partir dos comprovantes de cartão do mês.
        foreach (self::taxasCartao($empresaId, $iniS, $fimS) as $t) {
            $cat = 'Taxas da maquininha (automático)';
            $grupos[$cat] ??= ['categoria' => $cat, 'total' => 0, 'itens' => []];
            $grupos[$cat]['total'] = round($grupos[$cat]['total'] + $t['valor'], 2);
            $grupos[$cat]['itens'][] = $t + ['pago' => true];
        }
        uasort($grupos, fn ($a, $b) => $b['total'] <=> $a['total']);
        $despesas = round(array_sum(array_column($grupos, 'total')), 2);
        $liquido = round($lucroBruto - $despesas, 2);

        // ---------- divisão entre sócios ----------
        $reinvest = round(max(0, (float) ($e['reinvestimento'] ?? 0)), 2);
        $base = round($liquido - $reinvest, 2);
        $socios = [];
        $totalSocios = 0;
        foreach ((array) ($e['socios'] ?? []) as $s) {
            $pct = (float) ($s['pct'] ?? 0);
            $parte = round($base * $pct / 100, 2);
            $retiradas = array_values(array_filter((array) ($s['retiradas'] ?? []), fn ($r) => (float) ($r['valor'] ?? 0) != 0));
            $somaRet = round(array_sum(array_map(fn ($r) => (float) $r['valor'], $retiradas)), 2);
            $saldo = round($parte - $somaRet, 2);
            $totalSocios += $saldo;
            $socios[] = ['nome' => (string) ($s['nome'] ?? 'Sócio'), 'pct' => $pct, 'parte' => $parte, 'retiradas' => $retiradas, 'retiradas_total' => $somaRet, 'saldo' => $saldo];
        }
        $somaPct = round(array_sum(array_column($socios, 'pct')), 2);

        // ---------- posição no fim do mês ----------
        $contas = FinConta::daEmpresa($empresaId, false);
        $saldoEm = function (FinConta $c, string $ate) {
            $inicial = ($c->data_saldo_inicial && $c->data_saldo_inicial > $ate) ? 0 : (float) $c->saldo_inicial;
            $mov = (float) $c->movimentos()->where('data', '<=', $ate)
                ->selectRaw("COALESCE(SUM(CASE WHEN tipo = 'entrada' THEN valor ELSE -valor END), 0) s")->value('s');
            return round($inicial + $mov, 2);
        };
        $vespera = $ini->copy()->subDay()->format('Y-m-d');
        $saldoAbertura = round($contas->sum(fn ($c) => $saldoEm($c, $vespera)), 2);
        $porConta = $contas->map(fn ($c) => ['nome' => $c->nome, 'saldo' => $saldoEm($c, $fimS)])->filter(fn ($c) => abs($c['saldo']) > 0.009)->values()->all();
        $saldoFinal = round(array_sum(array_column($porConta, 'saldo')), 2);

        $estoque = round((float) DB::table('estoques as e')->join('produtos as p', 'p.id', '=', 'e.produto_id')
            ->where('p.empresa_id', $empresaId)->where('e.quantidade', '>', 0)
            ->selectRaw('COALESCE(SUM(e.quantidade * COALESCE(p.valor_compra, 0)), 0) s')->value('s'), 2);

        $receber = DB::table('conta_recebers as r')->leftJoin('clientes as c', 'c.id', '=', 'r.cliente_id')
            ->where('r.empresa_id', $empresaId)->where('r.status', 0)
            ->selectRaw("COALESCE(NULLIF(c.nome_fantasia, ''), c.razao_social, r.referencia) as nome, SUM(r.valor_integral - COALESCE(r.valor_recebido, 0)) as valor")
            ->groupBy('nome')->havingRaw('SUM(r.valor_integral - COALESCE(r.valor_recebido, 0)) > 0.009')->orderByDesc('valor')->get()
            ->map(fn ($r) => ['nome' => (string) $r->nome, 'valor' => round((float) $r->valor, 2)])->all();
        $aReceber = round(array_sum(array_column($receber, 'valor')), 2);

        $pagar = DB::table('conta_pagars as c')->where('c.empresa_id', $empresaId)->where('c.status', 0)
            ->selectRaw('c.referencia as nome, (c.valor_integral - COALESCE(c.valor_pago, 0)) as valor, c.data_vencimento')
            ->orderBy('c.data_vencimento')->get()
            ->filter(fn ($r) => (float) $r->valor > 0.009)
            ->map(fn ($r) => ['nome' => (string) $r->nome, 'valor' => round((float) $r->valor, 2), 'vence' => $r->data_vencimento ? Carbon::parse($r->data_vencimento)->format('d/m') : null])->values()->all();
        $aPagarContas = round(array_sum(array_column($pagar, 'valor')), 2);

        $pedidos = [];
        $pendForn = [];
        if (PedidoCompra::ativo()) {
            foreach (CompraPedido::with(['itens', 'custos', 'pendencias', 'fornecedor'])->where('empresa_id', $empresaId)->whereIn('status', ['rascunho', 'pedido'])->get() as $p) {
                $r = PedidoCompra::resumo($p);
                if ($r['gasto_brl'] > 0.009 || $r['falta_pagar_brl'] > 0.009) {
                    $pedidos[] = ['nome' => '#' . $p->id . ' ' . $p->nomeFornecedor(), 'pago' => $r['gasto_brl'], 'falta_pagar' => $r['falta_pagar_brl']];
                }
            }
            foreach (\App\Models\CompraPedidoPendencia::with('pedido.fornecedor')->where('status', 'aberta')->whereHas('pedido', fn ($w) => $w->where('empresa_id', $empresaId))->get() as $pd) {
                $pendForn[] = ['nome' => optional($pd->pedido)->nomeFornecedor() . ' · ' . $pd->descricao, 'valor' => round((float) $pd->valor_brl, 2)];
            }
        }
        $aCaminho = round(array_sum(array_column($pedidos, 'pago')), 2);
        $pendTotal = round(array_sum(array_column($pendForn, 'valor')), 2);

        // valores extras informados à mão (somam ou subtraem do total)
        $extras = array_values(array_filter((array) ($e['extras'] ?? []), fn ($x) => (float) ($x['valor'] ?? 0) != 0 && trim((string) ($x['descricao'] ?? '')) !== ''));
        $extrasReceber = round(array_sum(array_map(fn ($x) => ($x['tipo'] ?? '') === 'receber' ? (float) $x['valor'] : 0, $extras)), 2);
        $extrasPagar = round(array_sum(array_map(fn ($x) => ($x['tipo'] ?? '') === 'pagar' ? (float) $x['valor'] : 0, $extras)), 2);

        $aPagarTotal = round($aPagarContas + max(0, $totalSocios) + $extrasPagar, 2);
        $totalLoja = round($saldoFinal + $estoque + $aReceber + $extrasReceber + $aCaminho + $pendTotal - $aPagarTotal, 2);

        return [
            'mes' => $mes,
            'periodo' => [$ini->format('d/m/Y'), $fim->format('d/m/Y')],
            'saldo_abertura' => $saldoAbertura,
            'faturamento' => $faturamento,
            'faturamento_pdv' => round($fatPdv, 2),
            'cmv' => $cmv,
            'lucro_bruto' => $lucroBruto,
            'margem' => $faturamento > 0 ? round($lucroBruto / $faturamento * 100, 1) : 0,
            'despesas_grupos' => array_values($grupos),
            'despesas' => $despesas,
            'liquido' => $liquido,
            'reinvestimento' => $reinvest,
            'base_socios' => $base,
            'socios' => $socios,
            'socios_pct' => $somaPct,
            'socios_a_pagar' => round(max(0, $totalSocios), 2),
            'saldo_final' => $saldoFinal,
            'contas' => $porConta,
            'estoque' => $estoque,
            'receber' => $receber,
            'a_receber' => $aReceber,
            'pagar' => $pagar,
            'a_pagar_contas' => $aPagarContas,
            'a_pagar' => $aPagarTotal,
            'pedidos' => $pedidos,
            'a_caminho' => $aCaminho,
            'pend_fornecedor' => $pendForn,
            'pend_total' => $pendTotal,
            'extras' => $extras,
            'extras_receber' => $extrasReceber,
            'extras_pagar' => $extrasPagar,
            'total_loja' => $totalLoja,
            'calculado_em' => now()->format('d/m/Y H:i'),
        ];
    }

    /** Texto no formato do relatório que era montado à mão (para copiar no WhatsApp). */
    public static function texto(array $r): string
    {
        $m = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
        $linha = fn ($a, $b) => str_pad(mb_strtoupper(mb_substr($a, 0, 38)) . ' ', 40, '.') . ' ' . $b;
        $nomeMes = mb_strtoupper(Carbon::createFromFormat('Y-m-d', $r['mes'] . '-01')->locale('pt_BR')->translatedFormat('F Y'));
        $t = [];
        $t[] = $nomeMes;
        $t[] = '';
        $t[] = 'Abertura de caixa';
        $t[] = 'SALDO: ' . $m($r['saldo_abertura']);
        $t[] = str_repeat('=', 40);
        $t[] = 'Venda: ' . $m($r['faturamento']);
        $t[] = 'Custo dos produtos: ' . $m($r['cmv']);
        $t[] = 'Lucro bruto: ' . $m($r['lucro_bruto']) . ' (' . number_format($r['margem'], 1, ',', '') . '%)';
        $t[] = str_repeat('=', 40);
        $t[] = 'DESPESAS';
        foreach ($r['despesas_grupos'] as $g) {
            $t[] = '';
            $t[] = mb_strtoupper($g['categoria']);
            foreach ($g['itens'] as $i) {
                $t[] = $linha(($i['data'] ? substr($i['data'], 0, 5) . ' ' : '') . $i['descricao'], $m($i['valor']));
            }
            $t[] = $linha('TOTAL', $m($g['total']));
        }
        $t[] = '';
        $t[] = 'RESUMO GERAL';
        foreach ($r['despesas_grupos'] as $g) {
            $t[] = $linha($g['categoria'], $m($g['total']));
        }
        $t[] = $linha('TOTAL GERAL', $m($r['despesas']));
        $t[] = str_repeat('+', 40);
        $t[] = 'Lucro bruto: ' . $m($r['lucro_bruto']);
        $t[] = 'Líquido: ' . $m($r['lucro_bruto']) . ' - ' . $m($r['despesas']) . ' = ' . $m($r['liquido']);
        if ($r['reinvestimento'] > 0) {
            $t[] = 'Reinvestimento: ' . $m($r['reinvestimento']) . ' → para dividir ' . $m($r['base_socios']);
        }
        foreach ($r['socios'] as $s) {
            $ret = $s['retiradas'] ? ' - ' . implode(' - ', array_map(fn ($x) => number_format((float) $x['valor'], 2, ',', '.'), $s['retiradas'])) : '';
            $t[] = mb_strtoupper($s['nome']) . ' (' . rtrim(rtrim(number_format($s['pct'], 2, ',', ''), '0'), ',') . '%) = ' . number_format($s['parte'], 2, ',', '.') . $ret . ' = ' . $m($s['saldo']);
        }
        $t[] = str_repeat('+', 40);
        $t[] = 'SALDO = ' . $m($r['saldo_final']);
        $t[] = 'CUSTO ESTOQUE = ' . $m($r['estoque']);
        $t[] = str_repeat('=', 30);
        $t[] = 'CONTAS A RECEBER';
        foreach ($r['receber'] as $x) {
            $t[] = $linha($x['nome'], $m($x['valor']));
        }
        foreach ($r['extras'] as $x) {
            if (($x['tipo'] ?? '') === 'receber') {
                $t[] = $linha($x['descricao'], $m($x['valor']));
            }
        }
        $t[] = $linha('TOTAL', $m($r['a_receber'] + $r['extras_receber']));
        $t[] = str_repeat('=', 30);
        $t[] = 'A PAGAR';
        foreach ($r['socios'] as $s) {
            if ($s['saldo'] > 0) {
                $t[] = $linha($s['nome'], $m($s['saldo']));
            }
        }
        foreach ($r['pagar'] as $x) {
            $t[] = $linha($x['nome'], $m($x['valor']));
        }
        foreach ($r['extras'] as $x) {
            if (($x['tipo'] ?? '') === 'pagar') {
                $t[] = $linha($x['descricao'], $m($x['valor']));
            }
        }
        $t[] = $linha('TOTAL', $m($r['a_pagar']));
        if ($r['pedidos']) {
            $t[] = str_repeat('=', 30);
            $t[] = 'PEDIDOS PENDENTES (já pagos, a caminho)';
            foreach ($r['pedidos'] as $x) {
                $t[] = $linha($x['nome'], $m($x['pago']));
            }
            $t[] = $linha('TOTAL', $m($r['a_caminho']));
        }
        if ($r['pend_fornecedor']) {
            $t[] = str_repeat('=', 30);
            $t[] = 'PENDÊNCIA FORNECEDOR';
            foreach ($r['pend_fornecedor'] as $x) {
                $t[] = $linha($x['nome'], $m($x['valor']));
            }
            $t[] = $linha('TOTAL', $m($r['pend_total']));
        }
        $t[] = '';
        $t[] = '+++++ ' . $m($r['total_loja']) . ' +++++';
        return implode("\n", $t);
    }
}
