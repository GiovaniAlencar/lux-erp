@extends('default.layout', ['title' => 'Venda #' . $item->id])

@section('content')
@php
    $stPed = $item->status_pedido ?? 'aguardando_confirmacao';
    $stPag = $item->status_pagamento ?? 'pendente';
    $estNfe = $item->estado_emissao ?? 'novo';
    $lblStatusPedido = [
        'aguardando_confirmacao' => 'Aguardando confirmação',
        'em_elaboracao' => 'Aguardando confirmação',
        'confirmado' => 'Confirmado',
        'em_separacao' => 'Em separação',
        'separado' => 'Separado',
        'alteracao_pendente' => 'Alteração pendente',
        'em_rota_entrega' => 'Em rota de entrega',
        'ocorrencia_entrega' => 'Ocorrência na entrega',
        'entregue' => 'Entregue',
        'cancelada' => 'Cancelada',
    ];
    $tomPedido = [
        'aguardando_confirmacao' => 'warning', 'em_elaboracao' => 'warning', 'confirmado' => 'info',
        'em_separacao' => 'info', 'separado' => 'success', 'alteracao_pendente' => 'danger',
        'em_rota_entrega' => 'info', 'ocorrencia_entrega' => 'danger', 'entregue' => 'success', 'cancelada' => 'muted',
    ];
    $lblStatusPagamento = ['pendente' => 'Pagamento pendente', 'pago' => 'Pago', 'parcial' => 'Pago em parte', 'estornado' => 'Estornado'];
    $tomPagamento = ['pendente' => 'warning', 'pago' => 'success', 'parcial' => 'info', 'estornado' => 'muted'];
    $tomNfe = ['novo' => 'muted', 'aprovado' => 'success', 'rejeitado' => 'danger', 'cancelado' => 'muted'];
    $icoPedido = [
        'aguardando_confirmacao' => 'hourglass-split', 'em_elaboracao' => 'hourglass-split', 'confirmado' => 'check2-circle',
        'em_separacao' => 'box-seam', 'separado' => 'box2-fill', 'alteracao_pendente' => 'exclamation-triangle',
        'em_rota_entrega' => 'truck', 'ocorrencia_entrega' => 'exclamation-octagon', 'entregue' => 'house-check', 'cancelada' => 'x-circle',
    ];

    // valores: valor_total é a soma dos itens; o total cobrado considera desconto, acréscimo e frete
    $attrs = $item->getAttributes();
    $vFrete = (float) ($attrs['frete'] ?? 0);
    $vDesc = (float) ($item->desconto ?? 0);
    $vAcr = (float) ($item->acrescimo ?? 0);
    $somaItens = (float) $item->itens->sum(fn ($p) => (float) $p->quantidade * (float) $p->valor);
    $totalVenda = round((float) $item->valor_total - $vDesc + $vAcr + $vFrete, 2);
    $qtdUnidades = (float) $item->itens->sum(fn ($p) => (float) $p->quantidade);

    $qtdFmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',');
    $dataBr = function ($d, $hora = false) {
        if (empty($d)) return null;
        try { return \Carbon\Carbon::parse($d)->format($hora ? 'd/m/Y \à\s H:i' : 'd/m/Y'); } catch (\Throwable $e) { return (string) $d; }
    };

    // comprovantes anexados (SQL 27)
    $comprovantes = \Illuminate\Support\Facades\Schema::hasTable('venda_comprovantes')
        ? \App\Models\VendaComprovante::where('empresa_id', $item->empresa_id)->where('venda_id', $item->id)->get(['id', 'valor'])
        : collect();
    $comprovado = round((float) $comprovantes->sum('valor'), 2);
    $faltaComprovar = max(0, round($totalVenda - $comprovado, 2));

    $cli = $item->cliente;
    $fone = $cli ? ($cli->celular ?: $cli->telefone) : null;
    $foneWa = $fone ? preg_replace('/\D/', '', $fone) : null;
    if ($foneWa && strlen($foneWa) <= 11) $foneWa = '55' . $foneWa;
    $endereco = $cli ? trim(implode(', ', array_filter([$cli->rua, $cli->numero, $cli->bairro]))) : '';

    $rotuloAcaoAuditoria = [
        'venda_criada' => 'Venda criada',
        'venda_alterada' => 'Venda alterada',
        'workflow_confirmar_pedido' => 'Pedido confirmado',
        'workflow_marcar_separado' => 'Marcado como separado',
        'workflow_confirmar_alteracao' => 'Alteração conferida',
        'workflow_marcar_pago' => 'Pagamento registrado',
        'workflow_marcar_entregue' => 'Marcado como entregue',
        'fluxo_impressao_em_separacao' => 'Foi para separação (impressão)',
        'ficha_separacao_impressa' => 'Ficha de separação impressa',
        'pedido_pdf_baixado' => 'PDF do pedido baixado',
        'entrega_confirmada_motoboy' => 'Entrega confirmada (motoboy)',
        'entrega_ocorrencia_motoboy' => 'Ocorrência na entrega (motoboy)',
        'caixa_fechada' => 'Caixa fechado (ADM)',
        'nf_externa_emitida' => 'NF-e fiscal emitida',
        'nf_externa_pendente' => 'NF-e fiscal pendente',
    ];
    $tomAuditoria = function (string $acao) {
        if (str_contains($acao, 'caixa') || str_contains($acao, 'ocorrencia')) return 'warning';
        if ($acao === 'venda_criada' || str_contains($acao, 'entregue') || str_contains($acao, 'pago') || str_contains($acao, 'confirmada')) return 'success';
        if ($acao === 'venda_alterada') return 'info';
        return 'gold';
    };
    $podeFichaShow = in_array($stPed, ['confirmado', 'em_separacao', 'separado', 'alteracao_pendente', 'em_rota_entrega', 'entregue'], true);
    $divFiscal = $item->divisaoFiscal();
    $sitNf = $item->situacaoNfExterna($divFiscal);
@endphp

<style>
    .vd { color: var(--lx-text); }
    .vd-crumb { font-size: .8rem; color: var(--lx-muted); margin-bottom: .35rem; }
    .vd-crumb a { color: var(--lx-muted); text-decoration: none; }
    .vd-crumb a:hover { color: var(--lx-text); }
    .vd-head { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 1rem; margin-bottom: 1.25rem; }
    .vd-title { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; }
    .vd-title h1 { font-size: 1.6rem; font-weight: 700; margin: 0; letter-spacing: -.02em; }
    .vd-sub { margin-top: .35rem; color: var(--lx-muted); font-size: .85rem; display: flex; flex-wrap: wrap; gap: .25rem .9rem; }
    .vd-sub i { margin-right: .25rem; }
    .vd-pills { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .75rem; }
    .vd-pill { display: inline-flex; align-items: center; gap: .35rem; padding: .28rem .7rem; border-radius: 999px; font-size: .78rem; font-weight: 600; border: 1px solid transparent; }
    .vd-pill.t-success { background: var(--lx-success-soft); color: var(--lx-success); }
    .vd-pill.t-warning { background: var(--lx-warning-soft); color: var(--lx-warning); }
    .vd-pill.t-danger { background: var(--lx-danger-soft); color: var(--lx-danger); }
    .vd-pill.t-info { background: var(--lx-info-soft); color: var(--lx-info); }
    .vd-pill.t-muted { background: var(--lx-surface-3); color: var(--lx-muted); }
    .vd-pill.t-gold { background: var(--lx-gold-soft); color: var(--lx-gold-strong); }
    button.vd-pill { cursor: pointer; border-color: var(--lx-warning); }
    .vd-acts { display: flex; flex-wrap: wrap; gap: .5rem; }
    .vd-acts .btn { display: inline-flex; align-items: center; gap: .4rem; white-space: nowrap; }
    .vd-acts .btn.disabled { opacity: .45; }
    .vd-badge { display: inline-flex; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 9px; background: var(--lx-success); color: #fff; font-size: 11px; font-weight: 700; align-items: center; justify-content: center; }
    html.dark-theme .vd-badge { color: #0b0b0c; }

    .vd-grid { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 1.25rem; align-items: start; }
    .vd-main, .vd-side { display: flex; flex-direction: column; gap: 1.25rem; min-width: 0; }
    .vd-side { position: sticky; top: 84px; }
    @media (max-width: 1199.98px) { .vd-grid { grid-template-columns: 1fr; } .vd-side { position: static; } }

    .vd-card { background: var(--lx-surface); border: 1px solid var(--lx-border); border-radius: var(--lx-radius); box-shadow: var(--lx-shadow); }
    .vd-card-h { display: flex; align-items: center; justify-content: space-between; gap: .5rem; padding: .85rem 1.1rem; border-bottom: 1px solid var(--lx-border); }
    .vd-card-h h2 { font-size: .95rem; font-weight: 600; margin: 0; display: flex; align-items: center; gap: .5rem; }
    .vd-card-h h2 i { color: var(--lx-gold); }
    .vd-card-h .vd-h-extra { font-size: .8rem; color: var(--lx-muted); }
    .vd-card-b { padding: 1rem 1.1rem; }
    .vd-two { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
    @media (max-width: 767.98px) { .vd-two { grid-template-columns: 1fr; } }

    .vd-dl { display: grid; grid-template-columns: 1fr 1fr; gap: .85rem 1rem; margin: 0; }
    .vd-dl > div { min-width: 0; }
    .vd-dl dt { font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--lx-muted); margin-bottom: .15rem; }
    .vd-dl dd { margin: 0; font-size: .9rem; color: var(--lx-text); overflow-wrap: anywhere; }
    .vd-dl .full { grid-column: 1 / -1; }
    .vd-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .8rem; }
    .vd-cli-nome { font-size: 1.05rem; font-weight: 600; margin: 0 0 .15rem; }
    .vd-cli-doc { color: var(--lx-muted); font-size: .85rem; }
    .vd-cli-links { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .75rem; }
    .vd-cli-links a { display: inline-flex; align-items: center; gap: .35rem; font-size: .8rem; padding: .3rem .6rem; border-radius: 8px; border: 1px solid var(--lx-border); color: var(--lx-text-2); text-decoration: none; }
    .vd-cli-links a:hover { border-color: var(--lx-border-strong); color: var(--lx-text); }
    .vd-aviso { display: flex; gap: .5rem; align-items: flex-start; padding: .6rem .75rem; border-radius: 8px; background: var(--lx-warning-soft); color: var(--lx-warning); font-weight: 600; font-size: .85rem; }

    .vd-table { width: 100%; font-size: .875rem; border-collapse: collapse; }
    .vd-table th { font-size: .68rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: var(--lx-muted); padding: .55rem 1.1rem; background: var(--lx-surface-2); border-bottom: 1px solid var(--lx-border); white-space: nowrap; }
    .vd-table td { padding: .65rem 1.1rem; border-bottom: 1px solid var(--lx-border); color: var(--lx-text-2); vertical-align: middle; }
    .vd-table tbody tr:last-child td { border-bottom: 0; }
    .vd-table tfoot td { background: var(--lx-surface-2); font-weight: 600; color: var(--lx-text); border-top: 1px solid var(--lx-border); border-bottom: 0; }
    .vd .vd-table td, .vd .vd-prod { white-space: normal !important; }
    .vd .vd-table .num { white-space: nowrap !important; }
    .vd-table .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .vd-prod { font-weight: 500; color: var(--lx-text); }
    .vd-prod small { display: block; color: var(--lx-muted); font-weight: 400; font-size: .74rem; }
    .vd-f { display: inline-block; margin-left: .3rem; padding: 0 .35rem; border-radius: 4px; font-size: .65rem; font-weight: 700; background: var(--lx-info-soft); color: var(--lx-info); vertical-align: 1px; }

    .vd-pay-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    @media (max-width: 767.98px) { .vd-pay-grid { grid-template-columns: 1fr; } }
    .vd-comp { margin-top: .9rem; border: 1px dashed var(--lx-border-strong); border-radius: 10px; padding: .75rem .85rem; display: flex; align-items: center; justify-content: space-between; gap: .75rem; flex-wrap: wrap; }
    .vd-comp-txt { font-size: .85rem; }
    .vd-comp-txt b { font-variant-numeric: tabular-nums; }
    .vd-comp-txt .falta { color: var(--lx-warning); font-weight: 600; }
    .vd-comp-txt .ok { color: var(--lx-success); font-weight: 600; }
    .vd-bar { height: 6px; border-radius: 3px; background: var(--lx-surface-3); overflow: hidden; margin-top: .4rem; width: 220px; max-width: 100%; }
    .vd-bar span { display: block; height: 100%; background: var(--lx-success); }

    .vd-sum { margin: 0; font-size: .9rem; }
    .vd-sum div { display: flex; justify-content: space-between; gap: 1rem; padding: .35rem 0; }
    .vd-sum dt { font-weight: 400; color: var(--lx-text-2); }
    .vd-sum dd { margin: 0; font-variant-numeric: tabular-nums; }
    .vd-sum .neg { color: var(--lx-danger); }
    .vd-sum .sep { border-top: 1px solid var(--lx-border); margin-top: .35rem; padding-top: .7rem; }
    .vd-sum .total dt { font-weight: 600; color: var(--lx-text); font-size: 1rem; }
    .vd-sum .total dd { font-weight: 700; font-size: 1.45rem; color: var(--lx-text); }

    .vd-contas div { display: flex; justify-content: space-between; align-items: center; gap: .75rem; padding: .45rem 0; }
    .vd-contas .nome { font-size: .85rem; color: var(--lx-text-2); display: flex; align-items: center; gap: .4rem; }
    .vd-contas .val { font-weight: 700; font-size: 1.05rem; font-variant-numeric: tabular-nums; display: flex; align-items: center; gap: .5rem; }
    .vd-tag-f, .vd-tag-2 { display: inline-flex; align-items: center; justify-content: center; width: 20px; height: 18px; border-radius: 4px; font-size: .65rem; font-weight: 700; color: #fff; }
    .vd-tag-f { background: var(--lx-info); }
    .vd-tag-2 { background: var(--lx-muted); }
    html.dark-theme .vd-tag-f, html.dark-theme .vd-tag-2 { color: #0b0b0c; }
    .vd-details summary { cursor: pointer; font-size: .8rem; font-weight: 600; color: var(--lx-gold-strong); margin-top: .5rem; }
    .vd-details table { width: 100%; font-size: .78rem; margin-top: .5rem; }
    .vd-details td, .vd-details th { padding: .25rem 0; border-top: 1px solid var(--lx-border); }
    .vd-details th { color: var(--lx-muted); font-weight: 500; border-top: 0; }
    .vd-nf-box { border-radius: 8px; padding: .55rem .75rem; font-size: .85rem; font-weight: 500; }
    .vd-nf-box.t-success { background: var(--lx-success-soft); color: var(--lx-success); }
    .vd-nf-box.t-warning { background: var(--lx-warning-soft); color: var(--lx-warning); }
    .vd-nf-box.t-danger { background: var(--lx-danger-soft); color: var(--lx-danger); }
    .vd-nf-box small { display: block; opacity: .8; font-weight: 400; margin-top: .1rem; }
    .vd-label { font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--lx-muted); margin-bottom: .4rem; }

    .vd-tl { list-style: none; margin: 0; padding: .4rem 1.1rem 1rem; }
    .vd-tl li { position: relative; padding: .6rem 0 .6rem 1.6rem; }
    .vd-tl li::before { content: ''; position: absolute; left: 5px; top: 0; bottom: 0; width: 2px; background: var(--lx-border); }
    .vd-tl li:first-child::before { top: 1rem; }
    .vd-tl li:last-child::before { bottom: calc(100% - 1rem); }
    .vd-tl .dot { position: absolute; left: 0; top: .95rem; width: 12px; height: 12px; border-radius: 50%; background: var(--lx-gold); box-shadow: 0 0 0 3px var(--lx-surface); }
    .vd-tl .dot.t-success { background: var(--lx-success); }
    .vd-tl .dot.t-warning { background: var(--lx-warning); }
    .vd-tl .dot.t-info { background: var(--lx-info); }
    .vd-tl-h { display: flex; flex-wrap: wrap; align-items: baseline; gap: .25rem .6rem; }
    .vd-tl-h b { font-size: .88rem; }
    .vd-tl-h span { font-size: .76rem; color: var(--lx-muted); }
    .vd-tl-d { font-size: .82rem; color: var(--lx-text-2); white-space: pre-line; margin-top: .15rem; overflow-wrap: anywhere; }
    .vd-tl-more { display: none; }
    .vd-tl.aberto .vd-tl-more { display: list-item; }
    .vd-total-mob { display: none; margin-top: .6rem; font-size: .9rem; color: var(--lx-muted); }
    .vd-total-mob b { color: var(--lx-text); font-size: 1.2rem; margin-left: .35rem; }
    .vd-prod .vd-unit-mob { display: none; }
    @media (max-width: 1199.98px) { .vd-total-mob { display: block; } }
    @media (max-width: 575.98px) {
        .vd-table th, .vd-table td { padding: .55rem .7rem; }
        .vd-table .c-unit { display: none; }
        .vd-prod .vd-unit-mob { display: block; }
    }
    .vd-vazio { text-align: center; color: var(--lx-muted); font-size: .85rem; padding: 1.5rem 1rem; }
</style>

<div class="page-content vd">
    <div class="vd-crumb"><a href="{{ route('vendas.index') }}"><i class="bi bi-arrow-left"></i> Vendas</a></div>

    {{-- Cabeçalho --}}
    <div class="vd-head">
        <div>
            <div class="vd-title">
                <h1>Venda #{{ $item->id }}</h1>
                @if($item->fechada_caixa)
                    <span class="vd-pill t-muted"><i class="bi bi-lock-fill"></i> Fechada no caixa</span>
                    @if(!empty($usuarioAdm))
                        <button type="button" id="lux-venda-reabrir-caixa" data-venda="{{ $item->id }}" class="vd-pill t-warning" title="Permitir edição e alterações novamente">
                            <i class="bi bi-unlock"></i> Reabrir caixa
                        </button>
                    @endif
                @endif
            </div>
            <div class="vd-sub">
                <span><i class="bi bi-calendar3"></i>{{ $dataBr($item->data_registro ?: $item->created_at, true) }}</span>
                @if($item->usuario)<span><i class="bi bi-person"></i>{{ $item->usuario->nome }}</span>@endif
                @if($item->pedido_nuvemshop_id && $item->pedidoNuvemShop)
                    <span><i class="bi bi-bag"></i><a href="{{ route('nuvemshop-pedidos.show', [$item->pedidoNuvemShop->pedido_id]) }}">Pedido Nuvemshop</a></span>
                @endif
            </div>
            <div class="vd-pills">
                <span class="vd-pill t-{{ $tomPedido[$stPed] ?? 'muted' }}"><i class="bi bi-{{ $icoPedido[$stPed] ?? 'circle' }}"></i>{{ $lblStatusPedido[$stPed] ?? $stPed }}</span>
                <span class="vd-pill t-{{ $tomPagamento[$stPag] ?? 'muted' }}"><i class="bi bi-wallet2"></i>{{ $lblStatusPagamento[$stPag] ?? $stPag }}</span>
                <span class="vd-pill t-{{ $tomNfe[$estNfe] ?? 'muted' }}"><i class="bi bi-file-earmark-text"></i>NF-e: {{ ucfirst($estNfe) }}</span>
            </div>
            <div class="vd-total-mob">Total da venda <b>R$ {{ __moeda($totalVenda) }}</b></div>
        </div>
        <div class="vd-acts">
            @if(!$item->fechada_caixa)
            <a href="{{ route('vendas.edit', $item->id) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> Editar</a>
            @endif
            <button type="button" data-lux-comprovantes="{{ $item->id }}" class="btn btn-outline-secondary">
                <i class="bi bi-paperclip"></i> Comprovantes
                <span data-lux-comp-count="{{ $item->id }}" class="vd-badge {{ $comprovantes->count() ? '' : 'd-none' }}">{{ $comprovantes->count() ?: '' }}</span>
            </button>
            <a href="{{ route('vendas.print', ['id' => $item->id, 'download' => 1]) }}" class="btn btn-outline-secondary"><i class="bi bi-download"></i> PDF</a>
            <a href="{{ $podeFichaShow ? route('vendas.print-ficha', $item->id) : '#' }}" target="{{ $podeFichaShow ? '_blank' : '_self' }}" rel="noopener"
               class="btn btn-outline-secondary {{ $podeFichaShow ? '' : 'disabled' }}" title="{{ $podeFichaShow ? 'Imprimir ficha de separação' : 'Confirme o pedido para imprimir a ficha' }}"
               @if(!$podeFichaShow) aria-disabled="true" tabindex="-1" @endif>
                <i class="bi bi-printer"></i> Ficha
            </a>
        </div>
    </div>

    <div class="vd-grid">
        <div class="vd-main">

            {{-- Cliente + Entrega --}}
            <div class="vd-two">
                <section class="vd-card">
                    <div class="vd-card-h"><h2><i class="bi bi-person-vcard"></i> Cliente</h2></div>
                    <div class="vd-card-b">
                        @if($cli)
                            <p class="vd-cli-nome">{{ $cli->razao_social }}</p>
                            <div class="vd-cli-doc">
                                {{ $cli->cpf_cnpj ?: 'Sem CPF/CNPJ' }}
                                @if($cli->cidade) · {{ $cli->cidade->nome }}/{{ $cli->cidade->uf }} @endif
                            </div>
                            @if($endereco)<div class="vd-cli-doc mt-1">{{ $endereco }}</div>@endif
                            <div class="vd-cli-links">
                                @if($foneWa && strlen($foneWa) >= 12)
                                <a href="https://wa.me/{{ $foneWa }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> {{ $fone }}</a>
                                @elseif($fone)
                                <a href="tel:{{ preg_replace('/\D/', '', $fone) }}"><i class="bi bi-telephone"></i> {{ $fone }}</a>
                                @endif
                                @if($cli->email)<a href="mailto:{{ $cli->email }}"><i class="bi bi-envelope"></i> {{ $cli->email }}</a>@endif
                                <a href="{{ route('clientes.edit', $cli->id) }}"><i class="bi bi-box-arrow-up-right"></i> Cadastro</a>
                            </div>
                        @else
                            <p class="text-muted mb-0">Cliente não encontrado.</p>
                        @endif
                    </div>
                </section>

                <section class="vd-card">
                    <div class="vd-card-h"><h2><i class="bi bi-truck"></i> Entrega e nota</h2></div>
                    <div class="vd-card-b">
                        <dl class="vd-dl">
                            <div><dt>Data de entrega</dt><dd>{{ $dataBr($item->data_entrega) ?? '—' }}</dd></div>
                            <div><dt>NF-e</dt><dd>{{ ucfirst($estNfe) }}{{ $item->numero_nfe ? ' · nº ' . $item->numero_nfe : '' }}</dd></div>
                            @if(!empty($item->chave))
                            <div class="full"><dt>Chave da NF-e</dt><dd class="vd-mono">{{ $item->chave }}</dd></div>
                            @endif
                            @if(!empty($item->observacao))
                            <div class="full"><dt>Observação</dt><dd>{{ $item->observacao }}</dd></div>
                            @endif
                        </dl>
                        @if(!empty($item->aviso_entrega))
                        <div class="vd-aviso mt-3"><i class="bi bi-exclamation-triangle-fill"></i><span>{{ $item->aviso_entrega }}</span></div>
                        @endif
                    </div>
                </section>
            </div>

            {{-- Itens --}}
            <section class="vd-card">
                <div class="vd-card-h">
                    <h2><i class="bi bi-bag"></i> Itens</h2>
                    <span class="vd-h-extra">{{ $item->itens->count() }} {{ $item->itens->count() === 1 ? 'produto' : 'produtos' }} · {{ $qtdFmt($qtdUnidades) }} un.</span>
                </div>
                <div class="table-responsive">
                    <table class="vd-table">
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th class="num">Qtd</th>
                                <th class="num c-unit">Unitário</th>
                                <th class="num">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($item->itens as $p)
                            <tr>
                                <td>
                                    <div class="vd-prod">
                                        {{ optional($p->produto)->nome ?? ('Produto #' . $p->produto_id) }}
                                        @if((float) $p->qtd_fiscal > 0)
                                            <span class="vd-f" title="Unidades com nota (conta fiscal)">F{{ (float) $p->qtd_fiscal < (float) $p->quantidade ? ' ' . $qtdFmt($p->qtd_fiscal) : '' }}</span>
                                        @endif
                                        <small>#{{ $p->produto_id }}</small>
                                        <small class="vd-unit-mob">{{ $qtdFmt($p->quantidade) }} × {{ __moeda($p->valor) }}</small>
                                    </div>
                                </td>
                                <td class="num">{{ $qtdFmt($p->quantidade) }}</td>
                                <td class="num c-unit">{{ __moeda($p->valor) }}</td>
                                <td class="num"><b>{{ __moeda((float) $p->quantidade * (float) $p->valor) }}</b></td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td>Soma dos itens</td>
                                <td class="num">{{ $qtdFmt($qtdUnidades) }}</td>
                                <td class="c-unit"></td>
                                <td class="num">{{ __moeda($somaItens) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>

            {{-- Pagamento --}}
            <section class="vd-card" id="pagamento">
                <div class="vd-card-h"><h2><i class="bi bi-credit-card"></i> Pagamento</h2></div>
                <div class="vd-card-b">
                    <div class="vd-pay-grid">
                        <dl class="vd-dl">
                            <div><dt>Forma</dt><dd>{{ ['a_vista' => 'À vista', '30_dias' => '30 dias', 'personalizado' => 'Personalizado'][$item->forma_pagamento] ?? ($item->forma_pagamento ?: '—') }}</dd></div>
                            <div><dt>Tipo</dt><dd>{{ \App\Models\Venda::getTipo($item->tipo_pagamento) ?: '—' }}</dd></div>
                        </dl>
                        <div>
                            <div class="vd-label">Contas a receber</div>
                            <table class="vd-table" style="border:1px solid var(--lx-border);border-radius:8px;overflow:hidden">
                                <tbody>
                                    @if(count($item->duplicatas) > 0)
                                        @foreach ($item->duplicatas->sortBy(fn ($d) => [str_contains((string) $d->observacao, \App\Services\RecebimentoVenda::MARCA_RESTANTE) ? 1 : 0, $d->data_vencimento, $d->id])->values() as $i => $dup)
                                        @php
                                            $ehRestante = str_contains((string) $dup->observacao, \App\Services\RecebimentoVenda::MARCA_RESTANTE);
                                            $rec = (float) $dup->valor_recebido;
                                            $stDup = $dup->status ? ['success', 'Recebida'] : ($rec > 0 ? ['info', 'Parcial · falta ' . __moeda(max(0, $dup->valor_integral - $rec))] : ['warning', 'A receber']);
                                        @endphp
                                        <tr>
                                            <td style="padding:.45rem .75rem">
                                                {{ $ehRestante ? 'Restante' : ($i + 1) . 'ª' }} · {{ $dataBr($dup->data_vencimento) ?? '—' }}
                                                <div><span class="vd-pill t-{{ $stDup[0] }}" style="padding:.05rem .5rem;font-size:.7rem">{{ $stDup[1] }}</span></div>
                                            </td>
                                            <td class="num" style="padding:.45rem .75rem"><b>{{ __moeda($dup->valor_integral) }}</b></td>
                                        </tr>
                                        @endforeach
                                    @else
                                        <tr>
                                            <td style="padding:.45rem .75rem">Única · {{ $dataBr($item->created_at) }}</td>
                                            <td class="num" style="padding:.45rem .75rem"><b>{{ __moeda($totalVenda) }}</b></td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="vd-comp">
                        <div class="vd-comp-txt">
                            @if($comprovantes->count())
                                Comprovado <b>R$ {{ __moeda($comprovado) }}</b> de <b>R$ {{ __moeda($totalVenda) }}</b> ·
                                @if($faltaComprovar > 0.009)
                                    <span class="falta">falta R$ {{ __moeda($faltaComprovar) }}</span>
                                @else
                                    <span class="ok">completo</span>
                                @endif
                                <div class="vd-bar"><span style="width: {{ $totalVenda > 0 ? min(100, round($comprovado / $totalVenda * 100)) : 0 }}%"></span></div>
                            @else
                                <span class="text-muted">Nenhum comprovante anexado.</span>
                            @endif
                        </div>
                        <button type="button" data-lux-comprovantes="{{ $item->id }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-paperclip"></i> {{ $comprovantes->count() ? 'Ver / anexar' : 'Anexar comprovante' }}
                        </button>
                    </div>
                </div>
            </section>

            {{-- Histórico --}}
            @php $hist = ($auditorias ?? collect())->values(); @endphp
            <section class="vd-card">
                <div class="vd-card-h">
                    <h2><i class="bi bi-clock-history"></i> Histórico</h2>
                    <span class="vd-h-extra">quem fez o quê</span>
                </div>
                @if($hist->count())
                <ol class="vd-tl" id="vd-tl">
                    @foreach($hist as $n => $a)
                    @php $acaoAud = (string) ($a->acao ?? ''); @endphp
                    <li class="{{ $n >= 8 ? 'vd-tl-more' : '' }}">
                        <span class="dot t-{{ $tomAuditoria($acaoAud) }}"></span>
                        <div class="vd-tl-h">
                            <b>{{ $rotuloAcaoAuditoria[$acaoAud] ?? $acaoAud }}</b>
                            <span>{{ \Carbon\Carbon::parse($a->created_at)->format('d/m/Y H:i') }} · {{ $a->usuario ? $a->usuario->nome : 'sistema' }}</span>
                        </div>
                        @if($a->descricao)<div class="vd-tl-d">{{ $a->descricao }}</div>@endif
                    </li>
                    @endforeach
                </ol>
                @if($hist->count() > 8)
                <div class="px-3 pb-3"><button type="button" class="btn btn-sm btn-outline-secondary w-100" onclick="document.getElementById('vd-tl').classList.add('aberto'); this.remove();">Ver mais {{ $hist->count() - 8 }}</button></div>
                @endif
                @else
                <div class="vd-vazio">Nenhuma atividade registrada ainda.</div>
                @endif
            </section>
        </div>

        {{-- Lateral --}}
        <aside class="vd-side">
            <section class="vd-card">
                <div class="vd-card-h"><h2><i class="bi bi-calculator"></i> Resumo</h2></div>
                <div class="vd-card-b">
                    <dl class="vd-sum">
                        <div><dt>Itens</dt><dd>{{ __moeda($item->valor_total) }}</dd></div>
                        @if($vDesc > 0)<div><dt>Desconto</dt><dd class="neg">− {{ __moeda($vDesc) }}</dd></div>@endif
                        @if($vAcr > 0)<div><dt>Acréscimo</dt><dd>+ {{ __moeda($vAcr) }}</dd></div>@endif
                        <div><dt>Frete</dt><dd>{{ $vFrete > 0 ? '+ ' . __moeda($vFrete) : 'grátis / sem frete' }}</dd></div>
                        <div class="sep total"><dt>Total</dt><dd>R$ {{ __moeda($totalVenda) }}</dd></div>
                    </dl>
                </div>
            </section>

            @if($divFiscal['tem_fiscal'] || $sitNf)
            <section class="vd-card" id="nf-externa">
                <div class="vd-card-h"><h2><i class="bi bi-signpost-split"></i> Cobrar em duas contas</h2></div>
                <div class="vd-card-b">
                    <div class="vd-contas">
                        <div>
                            <span class="nome"><span class="vd-tag-f">F</span>{{ config('lux.conta_fiscal') }}</span>
                            <span class="val">@include('vendas.partials.pix_btn', ['pago' => ($stPag === 'pago'), 'conta' => 'fiscal', 'valor' => $divFiscal['fiscal'], 'txid' => 'LUX' . $item->id . 'F']){{ __moeda($divFiscal['fiscal']) }}</span>
                        </div>
                        <div>
                            <span class="nome"><span class="vd-tag-2">2</span>{{ config('lux.conta_nao_fiscal') }}</span>
                            <span class="val">@include('vendas.partials.pix_btn', ['pago' => ($stPag === 'pago'), 'conta' => 'nao_fiscal', 'valor' => $divFiscal['nao_fiscal'], 'txid' => 'LUX' . $item->id . 'N']){{ __moeda($divFiscal['nao_fiscal']) }}</span>
                        </div>
                    </div>

                    <details class="vd-details">
                        <summary>Itens para emitir a NF-e ({{ $item->itensFiscais()->count() }})</summary>
                        <table>
                            <thead><tr><th class="text-start">Produto</th><th class="text-end">Qtd</th><th class="text-end">Unit.</th><th class="text-end">Subtotal</th></tr></thead>
                            <tbody>
                                @foreach($item->itensFiscais() as $fi)
                                <tr>
                                    <td class="pe-2">{{ $fi->produto->nome ?? ('#' . $fi->produto_id) }}</td>
                                    <td class="text-end">{{ $qtdFmt($fi->qtd_fiscal) }}</td>
                                    <td class="text-end">{{ __moeda($fi->valor) }}</td>
                                    <td class="text-end">{{ __moeda(round((float) $fi->valor * (float) $fi->qtd_fiscal, 2)) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if($vDesc > 0 || $vAcr > 0)
                        <p class="mt-2 mb-0 small text-muted">Frete, desconto e acréscimo ficam na {{ config('lux.conta_nao_fiscal') }}. Itens fiscais: {{ __moeda($divFiscal['itens_fiscal']) }} → cobrar {{ __moeda($divFiscal['fiscal']) }}.</p>
                        @endif
                    </details>

                    <hr class="my-3">
                    <div class="vd-label">NF-e da parte fiscal</div>
                    @php
                        $nfErp = \App\Models\NotaFiscal::where('venda_id', $item->id)->orderByDesc('id')->first();
                        $nfErpTom = ['rascunho' => 'muted', 'rejeitada' => 'danger', 'autorizada' => 'success', 'cancelada' => 'muted'];
                    @endphp
                    @if($divFiscal['tem_fiscal'])
                    <div class="mb-3 d-flex flex-wrap align-items-center gap-2">
                        @if($nfErp)
                            <a href="{{ route('notas-fiscais.conferir', $nfErp->id) }}" class="btn btn-sm btn-primary"><i class="bi bi-file-earmark-text"></i> {{ $nfErp->numero ? 'NF-e ' . $nfErp->numero : 'Rascunho da NF-e' }}</a>
                            <span class="vd-pill t-{{ $nfErpTom[$nfErp->status] ?? 'muted' }}">{{ \App\Models\NotaFiscal::STATUS[$nfErp->status] ?? $nfErp->status }}</span>
                            @if($nfErp->status === 'autorizada')
                            <a href="{{ route('notas-fiscais.danfe', $nfErp->id) }}" target="_blank" class="small">DANFE</a>
                            @endif
                            @if($nfErp->status === 'cancelada')
                            <a href="{{ route('notas-fiscais.criar-da-venda', $item->id) }}" class="small">Emitir nova</a>
                            @endif
                        @else
                            <a href="{{ route('notas-fiscais.criar-da-venda', $item->id) }}" class="btn btn-sm btn-primary"><i class="bi bi-file-earmark-plus"></i> Emitir NF-e (conferir antes)</a>
                        @endif
                    </div>
                    @endif

                    <p class="small text-muted mb-2">Controle manual (nota emitida em outro sistema):</p>
                    @if($sitNf === 'emitida')
                        <div class="vd-nf-box t-success">
                            <i class="bi bi-check-circle"></i> Emitida{{ $item->nf_externa_numero ? ' · nº ' . $item->nf_externa_numero : '' }}
                            <small>{{ $item->nf_externa_em ? __data_pt($item->nf_externa_em, 1) : '' }}{{ optional($item->nfExternaUsuario)->nome ? ' · ' . $item->nfExternaUsuario->nome : '' }}</small>
                        </div>
                    @elseif($sitNf === 'divergente')
                        <div class="vd-nf-box t-danger">
                            <i class="bi bi-exclamation-triangle"></i>
                            Marcada como emitida{{ $item->nf_externa_numero ? ' (nº ' . $item->nf_externa_numero . ')' : '' }} com
                            <strong>R$ {{ __moeda($item->nf_externa_valor) }}</strong>, mas o valor fiscal agora é
                            <strong>R$ {{ __moeda($divFiscal['fiscal']) }}</strong>. Confira a nota.
                        </div>
                    @else
                        <div class="vd-nf-box t-warning"><i class="bi bi-clock"></i> Pendente</div>
                    @endif

                    <div class="mt-3 d-flex flex-column gap-2">
                        @if($sitNf !== 'emitida' && $divFiscal['tem_fiscal'])
                        <input type="text" id="lux-nf-numero" maxlength="30" placeholder="Nº da NF-e (opcional)" value="{{ $item->nf_externa_numero }}" class="form-control">
                        <button type="button" class="lux-nf-acao btn btn-success" data-acao="emitida"><i class="bi bi-file-earmark-check"></i> Marcar NF como emitida</button>
                        @endif
                        @if($sitNf === 'emitida' || $sitNf === 'divergente')
                        <button type="button" class="lux-nf-acao btn btn-outline-secondary" data-acao="pendente"><i class="bi bi-arrow-counterclockwise"></i> Voltar para pendente</button>
                        @endif
                    </div>
                </div>
            </section>
            @endif

            @if($item->fechada_caixa)
            <div class="vd-card vd-card-b small text-muted">
                @if(!empty($usuarioAdm))
                    Venda travada no caixa. Use <b>Reabrir caixa</b> no topo para permitir alterações.
                @else
                    Esta venda está fechada no caixa e não pode ser editada.
                @endif
            </div>
            @endif
        </aside>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var btnReabrir = document.getElementById('lux-venda-reabrir-caixa');
        var csrfToken = '{{ csrf_token() }}';

        document.querySelectorAll('.lux-nf-acao').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var numEl = document.getElementById('lux-nf-numero');
                var body = new URLSearchParams();
                body.append('acao', btn.getAttribute('data-acao'));
                if (numEl) body.append('numero', numEl.value || '');
                btn.disabled = true;
                fetch("{{ route('vendas.nf-externa', $item->id) }}", {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: body
                }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (x) {
                    if (!x.ok) {
                        btn.disabled = false;
                        swal("Erro", (x.j && x.j.message) || "Não foi possível salvar.", "error");
                        return;
                    }
                    window.location.hash = 'nf-externa';
                    window.location.reload();
                }).catch(function () {
                    btn.disabled = false;
                    swal("Erro", "Falha de conexão.", "error");
                });
            });
        });
        if (btnReabrir) {
            btnReabrir.addEventListener('click', function () {
                var vid = btnReabrir.getAttribute('data-venda');
                swal({
                    title: "Reabrir no caixa?",
                    text: "A venda poderá ser editada e ter status alterado novamente.",
                    icon: "warning",
                    buttons: true,
                    dangerMode: true
                }).then(function (ok) {
                    if (!ok) return;
                    fetch("{{ url('vendas/reabrir-caixa') }}/" + encodeURIComponent(vid), {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
                    }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                    .then(function (x) {
                        if (!x.ok) {
                            swal("Erro", (x.j && x.j.message) || "Não foi possível reabrir.", "error");
                            return;
                        }
                        swal({ title: "Caixa reaberto", icon: "success", timer: 1200, buttons: false }).then(function () {
                            window.location.reload();
                        });
                    });
                });
            });
        }
        // depois de anexar/excluir comprovante, atualiza o resumo de pagamento desta tela
        var modal = document.getElementById('modal-lux-comprovantes');
        if (modal) modal.addEventListener('hidden.bs.modal', function () {
            if (modal.dataset.mudou === '1') window.location.reload();
        });
    });
</script>
@include('vendas.partials.comprovantes_modal')
@endsection
