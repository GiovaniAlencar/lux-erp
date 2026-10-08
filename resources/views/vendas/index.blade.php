@extends('default.layout', ['title' => 'Vendas'])

{{-- Tailwind escopado + Lucide (convive com Bootstrap do layout via important + sem preflight) --}}
@section('css')
<script src="https://cdn.tailwindcss.com"></script>
<script>
    tailwind.config = {
        darkMode: 'class',
        important: '#lux-vendas-index',
        corePlugins: { preflight: false },
        theme: {
            extend: {
                fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
            },
        },
    };
</script>
<script src="https://unpkg.com/lucide@0.460.0/dist/umd/lucide.min.js"></script>
<style>
    /* Select2 — tema escuro */
    #lux-vendas-index[data-vendas-ui="dark"] .select2-container--bootstrap4 .select2-selection {
        background-color: #1f2937 !important;
        border-color: #374151 !important;
        color: #e5e7eb !important;
        min-height: 38px;
        border-radius: 0.75rem !important;
    }
    #lux-vendas-index[data-vendas-ui="dark"] .select2-container--bootstrap4 .select2-selection__rendered {
        color: #e5e7eb !important;
        line-height: 36px !important;
    }
    #lux-vendas-index[data-vendas-ui="dark"] .select2-dropdown {
        background: #1f2937;
        border-color: #374151;
    }
    #lux-vendas-index[data-vendas-ui="dark"] .select2-results__option { color: #e5e7eb; }
    #lux-vendas-index[data-vendas-ui="dark"] .select2-results__option--highlighted { background: #4f46e5 !important; }
    /* Select2 — tema claro */
    #lux-vendas-index[data-vendas-ui="light"] .select2-container--bootstrap4 .select2-selection {
        background-color: #ffffff !important;
        border-color: #d1d5db !important;
        color: #111827 !important;
        min-height: 38px;
        border-radius: 0.75rem !important;
    }
    #lux-vendas-index[data-vendas-ui="light"] .select2-container--bootstrap4 .select2-selection__rendered {
        color: #111827 !important;
        line-height: 36px !important;
    }
    #lux-vendas-index[data-vendas-ui="light"] .select2-dropdown {
        background: #ffffff;
        border-color: #d1d5db;
    }
    #lux-vendas-index[data-vendas-ui="light"] .select2-results__option { color: #1f2937; }
    #lux-vendas-index[data-vendas-ui="light"] .select2-results__option--highlighted { background: #eef2ff !important; color: #1e1b4b !important; }

    #lux-vendas-index tbody tr.lux-vendas-row {
        transition: background-color 0.2s ease, box-shadow 0.2s ease;
    }
    #lux-vendas-index[data-vendas-ui="dark"] tbody tr.lux-vendas-row:hover {
        background-color: rgba(30, 41, 59, 0.65);
        box-shadow: inset 0 1px 0 0 rgba(255, 255, 255, 0.055);
    }
    #lux-vendas-index[data-vendas-ui="light"] tbody tr.lux-vendas-row:hover {
        background-color: #f1f5f9;
        box-shadow: inset 0 1px 0 0 rgba(15, 23, 42, 0.06);
    }
    /* Badges de workflow clicáveis */
    #lux-vendas-index .wf-pedido,
    #lux-vendas-index .wf-pagamento {
        transition: filter 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
    }
    #lux-vendas-index .wf-pedido:hover,
    #lux-vendas-index .wf-pagamento:hover {
        filter: brightness(1.08);
        transform: scale(1.02);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.35);
    }
    /* Botões segmentados (ações) */
    #lux-vendas-index .lux-acoes-grupo a,
    #lux-vendas-index .lux-acoes-grupo button {
        transition: background-color 0.2s ease, color 0.2s ease, transform 0.12s ease;
    }
    #lux-vendas-index .lux-acoes-grupo a:active,
    #lux-vendas-index .lux-acoes-grupo button:active {
        transform: scale(0.96);
    }
    #lux-vendas-index .btn-print-ficha.lux-btn-ficha-off {
        opacity: 0.38;
        pointer-events: none;
        cursor: not-allowed;
    }
    /* Cópia rápida (Excel): feedback visual sem mudar cor do texto na tela */
    #lux-vendas-index .lux-copy-nome,
    #lux-vendas-index .lux-copy-valor {
        cursor: pointer;
        border-bottom: 1px dashed currentColor;
        text-decoration: none;
        opacity: 0.95;
    }
    #lux-vendas-index .lux-copy-nome:hover,
    #lux-vendas-index .lux-copy-valor:hover {
        opacity: 1;
    }
    #lux-vendas-index .lux-copy-nome:focus,
    #lux-vendas-index .lux-copy-valor:focus {
        outline: 2px solid rgb(99 102 241 / 0.5);
        outline-offset: 2px;
        border-radius: 2px;
    }
    /* Filtros mais baixos — libera altura para ~7 linhas na tabela */
    #lux-vendas-index .lux-vendas-filter-compact .form-label,
    #lux-vendas-index .lux-vendas-filter-compact label.form-label {
        margin-bottom: 0.15rem !important;
        font-size: 0.8rem;
    }
    #lux-vendas-index .lux-vendas-filter-compact .form-group,
    #lux-vendas-index .lux-vendas-filter-compact .mb-3 {
        margin-bottom: 0.2rem !important;
    }
    #lux-vendas-index .lux-vendas-filter-compact .form-control,
    #lux-vendas-index .lux-vendas-filter-compact .form-select {
        padding-top: 0.2rem;
        padding-bottom: 0.2rem;
        font-size: 0.8125rem;
        min-height: calc(1.45em + 0.35rem + 2px);
    }
    #lux-vendas-index .lux-vendas-filter-compact .select2-container--bootstrap4 .select2-selection {
        min-height: 30px !important;
    }
    #lux-vendas-index .lux-vendas-filter-compact .select2-container--bootstrap4 .select2-selection__rendered {
        line-height: 28px !important;
    }
    #lux-vendas-index .lux-vendas-filter-compact .filter-actions {
        margin-top: 0.1rem;
    }
    /* Local (filial) alinhado aos outros selects da linha */
    #lux-vendas-index .lux-vendas-filter-compact #locais.form-select {
        padding-top: 0.2rem;
        padding-bottom: 0.2rem;
        font-size: 0.8125rem;
        min-height: calc(1.45em + 0.35rem + 2px);
    }
    #lux-vendas-index .lux-filter-caixa-wrap .lux-filter-caixa-label {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }
    #lux-vendas-index .lux-filter-caixa-wrap .lux-filter-caixa-select.is-active {
        border-color: rgb(34 197 94 / 0.55);
        box-shadow: 0 0 0 1px rgb(34 197 94 / 0.2);
    }
    #lux-vendas-index[data-vendas-ui="dark"] .lux-filter-caixa-wrap .lux-filter-caixa-select.is-active {
        border-color: rgb(74 222 128 / 0.5);
        box-shadow: 0 0 0 1px rgb(74 222 128 / 0.15);
        background-color: rgb(34 197 94 / 0.08);
    }
    #lux-vendas-index .lux-acoes-grupo svg {
        width: 1rem;
        height: 1rem;
    }
    /*
     | Altura da página de vendas: preenche do conteúdo até o rodapé fixo (~60px topbar + ~46px footer).
     | O flex reparte o espaço: filtros fixos em cima, tabela cresce no restante.
     */
    #lux-vendas-index.lux-vendas-page {
        min-height: calc(100dvh - 6.5rem);
        padding-bottom: 0.35rem;
    }
    /* style.css .tbl-400 fixa 400px — aqui a tabela deve crescer com o flex */
    #lux-vendas-index .lux-vendas-tabela-scroll {
        height: auto !important;
        max-height: none !important;
    }
</style>
@endsection

@section('content')
@php
    $vendasUiDark = isset($theme) && ($theme->tema ?? '') === 'dark-theme';

    /*
     | Badges de workflow — cores alinhadas ao tema global (html.dark-theme vs claro).
     | Mesmas chaves no JSON para o JS (renderPedidoCell / renderPagamentoCell).
     */
    $luxBadgeBase = 'inline-flex items-center gap-0.5 rounded-full border px-2 py-0.5 text-xs font-medium shadow-sm transition duration-200 ease-out leading-tight';
    if ($vendasUiDark) {
        $luxBadgeBase .= ' ring-1 ring-inset ring-white/5';
        $luxBadgeNeutral = $luxBadgeBase . ' border-white/20 bg-slate-950/55 text-gray-100';
        $luxBadgeNeutralMuted = $luxBadgeBase . ' border-white/15 bg-slate-950/40 text-gray-400';
        $badgeCancelada = $luxBadgeBase . ' border-gray-600/50 bg-slate-950/30 text-gray-500 line-through decoration-gray-500/70';
    } else {
        $luxBadgeBase .= ' ring-1 ring-inset ring-gray-200/70';
        $luxBadgeNeutral = $luxBadgeBase . ' border-gray-300 bg-gray-100 text-gray-800';
        $luxBadgeNeutralMuted = $luxBadgeBase . ' border-gray-200 bg-gray-50 text-gray-600';
        $badgeCancelada = $luxBadgeBase . ' border-gray-300 bg-gray-50 text-gray-500 line-through decoration-gray-400';
    }
    $badgeTwPedido = [
        'aguardando_confirmacao' => $luxBadgeNeutral,
        'em_elaboracao' => $luxBadgeNeutral,
        'confirmado' => $luxBadgeNeutral,
        'em_separacao' => $luxBadgeNeutral,
        'separado' => $luxBadgeNeutral,
        'alteracao_pendente' => $luxBadgeNeutral,
        'em_rota_entrega' => $luxBadgeNeutral,
        'ocorrencia_entrega' => $luxBadgeNeutral,
        'entregue' => $luxBadgeNeutral,
        'cancelada' => $badgeCancelada,
    ];
    $badgeTwPagamento = [
        'pendente' => $luxBadgeNeutral,
        'pago' => $luxBadgeNeutral,
        'parcial' => $luxBadgeNeutral,
        'estornado' => $luxBadgeNeutralMuted,
    ];
    $lucidePedido = [
        'aguardando_confirmacao' => 'clock',
        'em_elaboracao' => 'clock',
        'confirmado' => 'circle-check',
        'em_separacao' => 'package',
        'separado' => 'package-check',
        'alteracao_pendente' => 'triangle-alert',
        'em_rota_entrega' => 'truck',
        'ocorrencia_entrega' => 'triangle-alert',
        'entregue' => 'house',
        'cancelada' => 'ban',
    ];
    $lucidePagamento = [
        'pendente' => 'wallet',
        'pago' => 'banknote',
        'parcial' => 'percent',
        'estornado' => 'rotate-ccw',
    ];
@endphp

<div id="lux-vendas-index" data-vendas-ui="{{ $vendasUiDark ? 'dark' : 'light' }}" class="lux-vendas-page font-sans antialiased col-12 w-100 px-0 min-w-0 flex flex-col min-h-0 @if($vendasUiDark) dark text-gray-100 bg-slate-900 @else text-gray-900 bg-gray-100 @endif">
<div class="min-w-0 w-full max-w-none rounded-2xl shadow-xl flex flex-col flex-1 min-h-0 @if($vendasUiDark) border border-slate-700/70 bg-slate-950/35 shadow-black/30 ring-1 ring-white/5 @else border border-gray-200 bg-white shadow-gray-200/40 ring-1 ring-gray-900/5 @endif">

    {{-- Filtros: duas linhas Bootstrap (soma 12 colunas) — Local/Vendedor alinhados ao grid --}}
    <div class="shrink-0 px-3 py-2 rounded-t-2xl border-b @if($vendasUiDark) border-slate-700/70 bg-slate-950/40 @else border-gray-200 bg-gray-50 @endif">
        <h2 class="text-sm font-semibold tracking-tight @if($vendasUiDark) text-gray-100 @else text-gray-900 @endif mb-2">Buscar vendas</h2>
        @if(isset($config))
                <input type="hidden" id="pass" value="{{ $config->senha_remover }}">
        @endif
        {!! Form::open()->fill(request()->all())->get() !!}
        <div class="filter-bar !mb-0 lux-vendas-filter-compact">
            <div class="row g-2 align-items-end lux-vendas-filter-row">
                <div class="col-xl-4 col-lg-4 col-md-6">
                    {!! Form::select('cliente_id', 'Cliente') !!}
                </div>
                <div class="col-xl-2 col-lg-2 col-md-6">
                    {!! Form::select('pesquisa_data', 'Pesquisa por data',
 ['created_at' => 'Data Registro', 'data_entrega' => 'Data Entrega'])
                        ->attrs(['class' => 'select2']) !!}
                </div>
                <div class="col-xl-2 col-lg-2 col-md-6">
                    {!! Form::date('start_date', 'Data inicial') !!}
                </div>
                <div class="col-xl-2 col-lg-2 col-md-6">
                    {!! Form::date('end_date', 'Data final') !!}
                </div>
                @if(empresaComFilial())
                    {!! __view_locais_select_filtro('Local', isset($filial_id) ? $filial_id : '', 'col-xl-2 col-lg-2 col-md-6') !!}
                @else
                    <div class="col-xl-2 col-lg-2 col-md-6">
                        <label class="form-label small mb-0 @if($vendasUiDark) text-gray-400 @else text-gray-600 @endif">Vendedor</label>
                        <select name="filter_usuario_id" id="filter_usuario_id" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            @foreach($usuariosFiltroVenda as $uv)
                                <option value="{{ $uv->id }}" {{ (string) request('filter_usuario_id') === (string) $uv->id ? 'selected' : '' }}>{{ $uv->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>
            <div class="row g-2 align-items-end lux-vendas-filter-row mt-1">
                @if(empresaComFilial())
                    <div class="col-xl-2 col-lg-2 col-md-6">
                        <label class="form-label small mb-0 @if($vendasUiDark) text-gray-400 @else text-gray-600 @endif">Vendedor</label>
                        <select name="filter_usuario_id" id="filter_usuario_id" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            @foreach($usuariosFiltroVenda as $uv)
                                <option value="{{ $uv->id }}" {{ (string) request('filter_usuario_id') === (string) $uv->id ? 'selected' : '' }}>{{ $uv->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-xl-2 col-lg-2 col-md-6">
                    <label class="form-label small mb-0 @if($vendasUiDark) text-gray-400 @else text-gray-600 @endif">Status venda</label>
                    <select name="filter_status_pedido[]" id="filter_status_pedido" class="form-select form-select-sm" multiple="multiple">
                        @foreach($labelStatusPedidoVenda as $k => $lbl)
                            <option value="{{ $k }}" {{ in_array($k, $filter_status_pedido_selecionados ?? [], true) ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-lg-2 col-md-6">
                    <label class="form-label small mb-0 @if($vendasUiDark) text-gray-400 @else text-gray-600 @endif">Pagamento</label>
                    <select name="filter_status_pagamento" id="filter_status_pagamento" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        @foreach($labelStatusPagamentoVenda as $k => $lbl)
                            <option value="{{ $k }}" {{ request('filter_status_pagamento') == $k ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-lg-2 col-md-6">
                    <label class="form-label small mb-0 @if($vendasUiDark) text-gray-400 @else text-gray-600 @endif">NF fiscal</label>
                    <select name="nf_externa" class="form-select form-select-sm" title="Vendas com itens fiscais: NF-e emitida no outro sistema?">
                        <option value="">Todas</option>
                        <option value="pendente" {{ request('nf_externa') === 'pendente' ? 'selected' : '' }}>NF pendente</option>
                        <option value="emitida" {{ request('nf_externa') === 'emitida' ? 'selected' : '' }}>NF emitida</option>
                    </select>
                </div>
                @if(!empty($usuarioAdm))
                <div class="col-xl-2 col-lg-2 col-md-6 lux-filter-caixa-wrap">
                    <label class="form-label small mb-0 lux-filter-caixa-label @if($vendasUiDark) text-gray-400 @else text-gray-600 @endif" for="filter_somente_abertos">
                        <i data-lucide="lock-keyhole" class="size-3.5 shrink-0 opacity-80" aria-hidden="true"></i>
                        Caixa
                    </label>
                    <select name="filter_somente_abertos" id="filter_somente_abertos"
                        class="form-select form-select-sm lux-filter-caixa-select {{ !empty($filter_somente_abertos) ? 'is-active' : '' }}"
                        title="Filtrar vendas abertas ou fechadas no caixa (somente ADM)">
                        <option value="">Todos</option>
                        <option value="1" {{ !empty($filter_somente_abertos) ? 'selected' : '' }}>Somente abertos</option>
                    </select>
                </div>
                @endif
                <div class="col-12 col-lg-auto ms-lg-auto d-flex flex-wrap align-items-end gap-2 filter-actions">
                    <button class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-xs font-medium text-white shadow-sm transition-all duration-200 ease-out hover:bg-indigo-500 active:scale-[0.98]" type="submit" title="Aplicar os filtros e atualizar a lista" data-bs-toggle="tooltip" data-bs-placement="top">
                        <i data-lucide="search" class="size-3.5"></i>
                        <span class="hidden sm:inline">Filtrar</span>
                    </button>
                    <a id="clear-filter" href="{{ route('vendas.index') }}" class="inline-flex items-center justify-center gap-1.5 rounded-lg border px-3 py-2 text-xs font-medium shadow-sm transition-all duration-200 ease-out hover:shadow-md active:scale-[0.98] @if($vendasUiDark) border-gray-600/90 bg-gray-800/90 text-gray-200 hover:border-rose-400/45 hover:bg-rose-500/12 hover:text-rose-100 @else border-gray-300 bg-white text-gray-700 hover:border-rose-300 hover:bg-rose-50 hover:text-rose-800 @endif" title="Remove filtros e recarrega a lista completa" data-bs-toggle="tooltip" data-bs-placement="top">
                        <i data-lucide="filter-x" class="size-4"></i>
                        Limpar filtros
                    </a>
                </div>
            </div>
        </div>
        {!! Form::close() !!}
    </div>

    {{-- Lista (flex-1: tabela ocupa o espaço até o rodapé) — padding horizontal alinhado ao bloco de filtros --}}
    <div class="flex flex-col flex-1 min-h-0 px-3 py-2 rounded-b-2xl @if(!$vendasUiDark) bg-white @endif">
        <div class="shrink-0 flex flex-col gap-1 lg:flex-row lg:items-center lg:justify-between mb-2">
            <h2 class="text-sm font-semibold tracking-tight @if($vendasUiDark) text-gray-100 @else text-gray-900 @endif mb-0">Lista de vendas</h2>
            @if(!empty($periodoPadraoAplicado))
            <span class="text-[11px] @if($vendasUiDark) text-gray-400 @else text-gray-500 @endif">Mostrando os pedidos de hoje · seus pedidos primeiro, depois pendentes. Para ver mais, ajuste a data inicial no filtro.</span>
            @endif
        </div>


        @if(($qtdAlteracaoPendente ?? 0) > 0)
            <div class="shrink-0 mb-1 flex gap-1.5 rounded-lg border px-2 py-1 text-[11px] shadow-sm ring-1 @if($vendasUiDark) border-rose-500/35 bg-rose-500/10 text-rose-100 shadow-black/20 ring-rose-500/20 @else border-rose-200 bg-rose-50 text-rose-900 shadow-rose-900/5 ring-rose-100 @endif" role="alert">
                <i data-lucide="triangle-alert" class="size-4 shrink-0 mt-0.5 @if($vendasUiDark) text-rose-300 @else text-rose-600 @endif"></i>
                <div>{{ $qtdAlteracaoPendente }} pedido(s) com <strong>alteração pendente</strong> — confira os itens na separação ou na rota antes de enviar.</div>
            </div>
        @endif

        <div class="shrink-0 flex flex-col gap-1.5 lg:flex-row lg:flex-wrap lg:items-center mb-1">
            <a href="{{ route('vendas.create')}}" class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-medium text-white shadow-sm transition-all duration-200 ease-out hover:bg-emerald-500 active:scale-[0.98] w-full sm:w-auto" title="Abrir tela de nova venda" data-bs-toggle="tooltip" data-bs-placement="top">
                <i data-lucide="plus" class="size-3.5"></i>
                Nova venda
            </a>
            <div class="flex flex-wrap gap-1.5 w-full lg:w-auto">
                <button type="button" class="inline-flex items-center justify-center gap-1.5 rounded-lg border px-3 py-2 text-xs font-medium shadow-sm transition-all duration-200 ease-out hover:shadow-md active:scale-[0.98] @if($vendasUiDark) border-gray-600/90 bg-gray-800/90 text-gray-200 hover:border-gray-500 hover:bg-gray-700 @else border-gray-300 bg-white text-gray-700 hover:border-gray-400 hover:bg-gray-50 @endif" id="btn-select-all" title="Alterna seleção de todas as vendas desta página" data-bs-toggle="tooltip" data-bs-placement="top">
                    <i data-lucide="check-square" class="size-3.5"></i>
                    <span id="btn-select-all-label">Selecionar todos</span>
                </button>
                <button type="button" class="inline-flex items-center justify-center gap-1.5 rounded-lg border px-3 py-2 text-xs font-medium shadow-sm transition-all duration-200 ease-out hover:shadow-md active:scale-[0.98] @if($vendasUiDark) border-amber-400/35 bg-amber-500/10 text-amber-100 hover:bg-amber-500/18 @else border-amber-200 bg-amber-50 text-amber-900 hover:bg-amber-100 @endif" id="btn-print-selected" title="Imprime a ficha de separação de cada venda confirmada selecionada" data-bs-toggle="tooltip" data-bs-placement="top">
                    <i data-lucide="printer" class="size-3.5"></i>
                    Ficha selecionados
                </button>
                <button type="button" class="inline-flex items-center justify-center gap-1.5 rounded-lg border px-3 py-2 text-xs font-medium shadow-sm transition-all duration-200 ease-out hover:shadow-md active:scale-[0.98] @if($vendasUiDark) border-sky-400/35 bg-sky-500/10 text-sky-100 hover:bg-sky-500/18 @else border-sky-200 bg-sky-50 text-sky-900 hover:bg-sky-100 @endif" id="btn-download-selected" title="Baixa o PDF do pedido (sem ficha) de cada venda selecionada" data-bs-toggle="tooltip" data-bs-placement="top">
                    <i data-lucide="download" class="size-3.5"></i>
                    Baixar pedido (PDF)
                </button>
                <button type="button" class="inline-flex items-center justify-center gap-1.5 rounded-lg border px-3 py-2 text-xs font-medium shadow-sm transition-all duration-200 ease-out hover:shadow-md active:scale-[0.98] @if($vendasUiDark) border-indigo-400/35 bg-indigo-500/10 text-indigo-100 hover:bg-indigo-500/18 @else border-indigo-200 bg-indigo-50 text-indigo-900 hover:bg-indigo-100 @endif" id="btn-gerar-rota" title="Cria rota de entrega com os pedidos selecionados" data-bs-toggle="tooltip" data-bs-placement="top">
                    <i data-lucide="map" class="size-3.5"></i>
                    Gerar rota
                </button>
                <button type="button" class="inline-flex items-center justify-center gap-1.5 rounded-lg border px-3 py-2 text-xs font-medium shadow-sm transition-all duration-200 ease-out hover:shadow-md active:scale-[0.98] @if($vendasUiDark) border-emerald-400/35 bg-emerald-500/10 text-emerald-100 hover:bg-emerald-500/18 @else border-emerald-200 bg-emerald-50 text-emerald-900 hover:bg-emerald-100 @endif" id="btn-marcar-entregue" title="Marca como entregue apenas vendas em rota de entrega" data-bs-toggle="tooltip" data-bs-placement="top">
                    <i data-lucide="circle-check" class="size-3.5"></i>
                    Marcar entregue
                </button>
            </div>
        </div>

        <div class="flex flex-col flex-1 min-h-0 rounded-2xl border shadow-xl overflow-hidden ring-1 @if($vendasUiDark) border-slate-700/70 bg-slate-950/25 shadow-black/30 ring-white/5 @else border-gray-200 bg-gray-50/80 shadow-gray-200/50 ring-gray-900/5 @endif">
            <div class="lux-vendas-tabela-scroll flex-1 min-h-0 overflow-x-auto overflow-y-auto">
                <table class="w-full text-left text-sm @if($vendasUiDark) text-gray-200 @else text-gray-800 @endif">
                    <thead class="sticky top-0 z-10 backdrop-blur-md border-b shadow-sm @if($vendasUiDark) bg-slate-950/95 border-slate-700/80 @else bg-gray-100/95 border-gray-200 @endif">
                        <tr class="text-[11px] font-semibold uppercase tracking-wide @if($vendasUiDark) text-gray-400 @else text-gray-600 @endif">
                            <th class="w-10 px-2 py-2">
                                <input type="checkbox" id="check-all" class="rounded text-indigo-600 focus:ring-indigo-500 @if($vendasUiDark) border-gray-600 bg-gray-800 @else border-gray-300 bg-white @endif" title="Selecionar todas">
                            </th>
                            <th class="px-2 py-2 min-w-[12rem]">Pedido</th>
                            <th class="px-2 py-2 min-w-[10rem]">Valores</th>
                            <th class="px-2 py-2 min-w-[10rem]">Status</th>
                            <th class="px-2 py-2 text-center w-14">Caixa</th>
                            <th class="px-2 py-2 text-center min-w-[8.5rem]">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="@if($vendasUiDark) divide-y divide-slate-700/70 @else divide-y divide-gray-200 @endif">
                        @forelse ($data as $item)
                            @php
                                $totalLinha = $item->valor_total - $item->desconto + $item->acrescimo + $item->frete;
                                $stPed = $item->status_pedido ?? 'aguardando_confirmacao';
                                $stPag = $item->status_pagamento ?? 'pendente';
                                $pedidoClicavel = !$item->fechada_caixa && in_array($stPed, ['aguardando_confirmacao', 'em_elaboracao', 'em_separacao', 'alteracao_pendente'], true);
                                $acaoPedido = ['aguardando_confirmacao' => 'confirmar_pedido', 'em_elaboracao' => 'confirmar_pedido', 'em_separacao' => 'marcar_separado', 'alteracao_pendente' => 'confirmar_alteracao'][$stPed] ?? '';
                                $pagClicavel = !$item->fechada_caixa && $stPag === 'pendente';
                                $clsPed = $badgeTwPedido[$stPed] ?? $luxBadgeNeutral;
                                $clsPag = $badgeTwPagamento[$stPag] ?? $luxBadgeNeutral;
                                $icoPed = $lucidePedido[$stPed] ?? 'circle';
                                $icoPag = $lucidePagamento[$stPag] ?? 'circle';
                                $showDelete = !$item->fechada_caixa && ($item->estado_emissao == 'novo' || $item->estado_emissao == 'rejeitado');
                                $podeFicha = in_array($stPed, ['confirmado', 'em_separacao', 'separado', 'alteracao_pendente', 'em_rota_entrega', 'entregue'], true);
                                $rotaItem = $rotaPorVenda[$item->id] ?? null;
                                $rotaTooltip = '';
                                if ($stPed === 'em_rota_entrega' && $rotaItem) {
                                    $motoboyRota = optional($rotaItem->rota)->motoboy_nome ?: 'não informado';
                                    $rotaTooltip = 'Rota #' . $rotaItem->rota_entrega_id . ' · Motoboy: ' . $motoboyRota;
                                }
                            @endphp
                            <tr data-venda-row="{{ $item->id }}"
                                data-status-pedido="{{ $item->status_pedido ?? 'aguardando_confirmacao' }}"
                                data-status-pagamento="{{ $item->status_pagamento ?? 'pendente' }}"
                                data-estado-emissao="{{ $item->estado_emissao }}"
                                data-fechada-caixa="{{ $item->fechada_caixa ? '1' : '0' }}"
                                class="lux-vendas-row @if(!$vendasUiDark) bg-white @endif @if($stPed === 'alteracao_pendente') border-l-4 border-rose-500 @if($vendasUiDark) bg-rose-500/10 @else bg-rose-50 @endif @endif">
                                <td class="px-2 py-2.5 align-middle">
                                    <input type="checkbox" value="{{ $item->id }}" class="checkbox-venda rounded text-indigo-600 focus:ring-indigo-500 @if($vendasUiDark) border-gray-600 bg-gray-800 @else border-gray-300 bg-white @endif" title="Selecionar venda #{{ $item->id }}">
                                </td>
                                <td class="px-2 py-2.5 align-middle">
                                    <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5 leading-snug">
                                        <span class="font-semibold text-sm tracking-tight @if($vendasUiDark) text-gray-100 @else text-gray-900 @endif">#{{ $item->id }}</span>
                                        @if(($item->origem ?? 'erp') === 'site')
                                            <span class="text-[10px] font-semibold uppercase tracking-wide px-1.5 py-0.5 rounded @if($vendasUiDark) bg-amber-500/20 text-amber-300 @else bg-amber-100 text-amber-800 @endif">Site</span>
                                        @else
                                            <span class="text-[10px] font-semibold uppercase tracking-wide px-1.5 py-0.5 rounded @if($vendasUiDark) bg-slate-500/20 text-slate-300 @else bg-slate-100 text-slate-600 @endif">ERP</span>
                                        @endif
                                        <span class="text-[10px] font-medium tabular-nums @if($vendasUiDark) text-gray-500 @else text-gray-500 @endif">v{{ (int) ($item->versao_pedido ?? 1) }}</span>
                                        <span class="text-xs tabular-nums @if($vendasUiDark) text-gray-500 @else text-gray-500 @endif">{{ __data_pt($item->created_at, false) }}</span>
                                    </div>
                                    <div class="text-sm leading-snug mt-1 @if($vendasUiDark) text-gray-300 @else text-gray-700 @endif">
                                        <span class="lux-copy-nome font-normal @if($vendasUiDark) text-gray-300 @else text-gray-600 @endif"
                                            role="button"
                                            tabindex="0"
                                            data-copy="{{ e($item->cliente->razao_social) }}"
                                            title="Clique para copiar o nome (texto simples, ideal para Excel)">{{ $item->cliente->razao_social }}</span>
                                    </div>
                                    <div class="text-[11px] leading-snug mt-0.5 @if($vendasUiDark) text-gray-500 @else text-gray-500 @endif" title="Vendedor">
                                        <i data-lucide="user" class="size-3 inline -mt-0.5"></i> {{ optional($item->usuario)->nome ?? '—' }}
                                    </div>
                                </td>
                                <td class="px-2 py-2.5 align-middle">
                                    @php
                                        $valorCopiaExcel = number_format((float) $totalLinha, 2, ',', '.');
                                    @endphp
                                    <div class="lux-copy-valor text-sm font-semibold tabular-nums leading-snug @if($vendasUiDark) text-emerald-400 @else text-emerald-600 @endif"
                                        role="button"
                                        tabindex="0"
                                        data-copy="{{ $valorCopiaExcel }}"
                                        title="Clique para copiar o valor (ex.: {{ $valorCopiaExcel }}) — colar no Excel como texto">R$ {{ __moeda($totalLinha) }}</div>
                                    <div class="text-xs leading-snug mt-0.5 @if($vendasUiDark) text-gray-500 @else text-gray-600 @endif">
                                        Desc. {{ __moeda($item->desconto) }}
                                        <span class="@if($vendasUiDark) text-gray-600 @else text-gray-400 @endif">·</span> Frete {{ __moeda($item->frete) }}
                                        <span class="@if($vendasUiDark) text-gray-600 @else text-gray-400 @endif">·</span> Acrésc. {{ __moeda($item->acrescimo) }}
                                    </div>
                                    @php
                                        $divFiscal = $item->divisaoFiscal();
                                        $sitNf = $item->situacaoNfExterna($divFiscal);
                                        $nfBadge = [
                                            'pendente' => ['NF pendente', 'bg-amber-500 text-white'],
                                            'emitida' => ['NF emitida' . ($item->nf_externa_numero ? ' nº ' . $item->nf_externa_numero : ''), 'bg-emerald-600 text-white'],
                                            'divergente' => ['NF ≠ valor', 'bg-rose-600 text-white'],
                                        ][$sitNf] ?? null;
                                    @endphp
                                    @if($divFiscal['tem_fiscal'] || $nfBadge)
                                    <div class="text-xs leading-snug mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 tabular-nums" title="Cobrar em duas contas: {{ config('lux.conta_fiscal') }} / {{ config('lux.conta_nao_fiscal') }}">
                                        <span class="font-semibold @if($vendasUiDark) text-indigo-300 @else text-indigo-700 @endif"><span class="inline-block rounded px-1 text-[10px] font-bold bg-indigo-600 text-white">F</span> R$ {{ __moeda($divFiscal['fiscal']) }} @include('vendas.partials.pix_btn', ['pago' => ($stPag === 'pago'), 'conta' => 'fiscal', 'valor' => $divFiscal['fiscal'], 'txid' => 'LUX' . $item->id . 'F'])</span>
                                        <span class="font-semibold @if($vendasUiDark) text-gray-300 @else text-gray-700 @endif"><span class="inline-block rounded px-1 text-[10px] font-bold bg-gray-500 text-white">2</span> R$ {{ __moeda($divFiscal['nao_fiscal']) }} @include('vendas.partials.pix_btn', ['pago' => ($stPag === 'pago'), 'conta' => 'nao_fiscal', 'valor' => $divFiscal['nao_fiscal'], 'txid' => 'LUX' . $item->id . 'N'])</span>
                                        @if($nfBadge)
                                        <a href="{{ route('vendas.show', $item->id) }}#nf-externa" class="inline-block rounded px-1.5 py-0.5 text-[10px] font-semibold no-underline {{ $nfBadge[1] }}" title="Controle da NF-e fiscal (abrir pedido)">{{ $nfBadge[0] }}</a>
                                        @endif
                                    </div>
                                    @elseif($divFiscal['total'] > 0)
                                    <div class="text-xs leading-snug mt-1">@include('vendas.partials.pix_btn', ['pago' => ($stPag === 'pago'), 'conta' => 'nao_fiscal', 'valor' => $divFiscal['total'], 'txid' => 'LUX' . $item->id . 'N'])</div>
                                    @endif
                                </td>
                                <td class="px-2 py-2.5 align-middle">
                                    <div class="td-wf-status flex flex-wrap items-center gap-x-1.5 gap-y-1">
                                        <div class="td-wf-pedido shrink-0 flex flex-col items-start gap-0.5">
                                        @if($pedidoClicavel)
                                            <span role="button" class="wf-pedido {{ $clsPed }} cursor-pointer" data-venda="{{ $item->id }}" data-acao="{{ $acaoPedido }}" title="Clique para avançar o status do pedido">
                                                <i data-lucide="{{ $icoPed }}" class="size-3.5 shrink-0" aria-hidden="true"></i>{{ $labelStatusPedidoVenda[$stPed] ?? $stPed }}
                                            </span>
                                        @else
                                            <span class="{{ $clsPed }}" @if($rotaTooltip) data-bs-toggle="tooltip" data-bs-placement="top" title="{{ $rotaTooltip }}" @endif>
                                                <i data-lucide="{{ $icoPed }}" class="size-3.5 shrink-0" aria-hidden="true"></i>{{ $labelStatusPedidoVenda[$stPed] ?? $stPed }}
                                            </span>
                                            @if($stPed === 'em_rota_entrega' && $rotaItem)
                                            <div class="w-full text-[10px] leading-tight mt-0.5 @if($vendasUiDark) text-sky-300/90 @else text-sky-700 @endif" title="{{ $rotaTooltip }}">
                                                Rota #{{ $rotaItem->rota_entrega_id }} · {{ optional($rotaItem->rota)->motoboy_nome ?: 'Motoboy não informado' }}
                                            </div>
                                            @endif
                                        @endif
                                        </div>
                                        <div class="td-wf-pagamento shrink-0">
                                        @if($pagClicavel)
                                            <span role="button" class="wf-pagamento {{ $clsPag }} cursor-pointer" data-venda="{{ $item->id }}" title="Clique para registrar pagamento recebido">
                                                <i data-lucide="{{ $icoPag }}" class="size-3.5 shrink-0" aria-hidden="true"></i>{{ $labelStatusPagamentoVenda[$stPag] ?? $stPag }}
                                            </span>
                                        @else
                                            <span class="{{ $clsPag }}">
                                                <i data-lucide="{{ $icoPag }}" class="size-3.5 shrink-0" aria-hidden="true"></i>{{ $labelStatusPagamentoVenda[$stPag] ?? $stPag }}
                                            </span>
                                        @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-2 py-2.5 text-center align-middle td-caixa">
                                    @if(!empty($usuarioAdm) && !$item->fechada_caixa)
                                        <button type="button" class="inline-flex items-center justify-center rounded-lg border p-2 shadow-sm transition-all duration-200 ease-out hover:shadow-md active:scale-95 btn-fechar-caixa @if($vendasUiDark) border-gray-600/90 bg-gray-800/90 text-gray-300 hover:bg-gray-700 hover:text-white @else border-gray-300 bg-white text-gray-600 hover:bg-gray-100 hover:text-gray-900 @endif" data-venda="{{ $item->id }}" title="Travar venda no caixa (somente ADM): sem edição ou exclusão" data-bs-toggle="tooltip" data-bs-placement="left">
                                            <i data-lucide="lock" class="size-4"></i>
                                        </button>
                                    @elseif($item->fechada_caixa)
                                        @if(!empty($usuarioAdm))
                                            <button type="button" class="inline-flex items-center justify-center rounded-lg border p-2 shadow-sm transition-all duration-200 ease-out hover:shadow-md active:scale-95 btn-reabrir-caixa @if($vendasUiDark) border-amber-400/45 bg-amber-500/15 text-amber-100 hover:bg-amber-500/25 @else border-amber-300 bg-amber-50 text-amber-900 hover:bg-amber-100 @endif" data-venda="{{ $item->id }}" title="Reabrir no caixa (ADM): permitir edição, exclusão e alteração de status" data-bs-toggle="tooltip" data-bs-placement="left">
                                                <i data-lucide="lock-open" class="size-4"></i>
                                            </button>
                                        @else
                                            <span class="inline-flex text-gray-500" title="Fechada no caixa{{ $item->fechada_em ? ' em ' . __data_pt($item->fechada_em, 1) : '' }}">
                                                <i data-lucide="lock-keyhole" class="size-4"></i>
                                            </span>
                                        @endif
                                    @else
                                        <span class="text-gray-600">—</span>
                                    @endif
                                </td>
                                <td class="px-2 py-2.5 text-center align-middle">
                                    <form action="{{ route('vendas.destroy', $item->id) }}" method="post" id="form-{{ $item->id }}" class="inline-block">
                                        @method('delete')
                                        @csrf
                                        <div class="lux-acoes-grupo inline-flex rounded-xl border overflow-visible shadow-md ring-1 @if($vendasUiDark) border-gray-600/90 shadow-black/20 ring-white/5 @else border-gray-200 shadow-gray-200/40 ring-gray-900/5 @endif" role="group">
                                            <a href="{{ route('vendas.show', $item->id) }}" class="inline-flex items-center justify-center p-2 rounded-l-xl @if($vendasUiDark) text-gray-300 bg-gray-800/95 hover:bg-indigo-500/28 hover:text-indigo-100 @else text-gray-600 bg-gray-50 hover:bg-indigo-50 hover:text-indigo-700 @endif" title="Ver detalhes, histórico e totais" aria-label="Visualizar" data-bs-toggle="tooltip" data-bs-placement="top">
                                                <i data-lucide="eye" class="size-4"></i>
                                            </a>
                                            @if(!$item->fechada_caixa)
                                                <a href="{{ route('vendas.edit', $item->id) }}" class="inline-flex items-center justify-center p-2 border-l venda-acao-editar @if($vendasUiDark) text-gray-300 bg-gray-800/95 border-gray-600/80 hover:bg-gray-700 hover:text-white @else text-gray-600 bg-white border-gray-200 hover:bg-gray-100 hover:text-gray-900 @endif" title="Alterar itens, cliente ou valores" aria-label="Editar" data-bs-toggle="tooltip" data-bs-placement="top">
                                                    <i data-lucide="pencil" class="size-4"></i>
                                                </a>
                                            @endif
                                            <a href="{{ route('vendas.print', ['id' => $item->id, 'download' => 1]) }}"
                                                class="inline-flex items-center justify-center p-2 border-l btn-download-pedido @if($vendasUiDark) text-sky-100/95 bg-gray-800/95 border-gray-600/80 hover:bg-sky-500/22 @else text-sky-800 bg-white border-gray-200 hover:bg-sky-50 @endif"
                                                title="Baixar PDF do pedido (sem ficha de separação)" aria-label="Baixar pedido PDF" data-bs-toggle="tooltip" data-bs-placement="top">
                                                <i data-lucide="download" class="size-4"></i>
                                            </a>
                                            <a href="{{ $podeFicha ? route('vendas.print-ficha', $item->id) : '#' }}"
                                                target="{{ $podeFicha ? '_blank' : '_self' }}"
                                                rel="noopener noreferrer"
                                                data-ficha-enabled="{{ $podeFicha ? '1' : '0' }}"
                                                data-venda-id="{{ $item->id }}"
                                                class="btn-print-ficha inline-flex items-center justify-center p-2 border-l @if($vendasUiDark) text-amber-100/95 bg-gray-800/95 border-gray-600/80 hover:bg-amber-500/22 @else text-amber-800 bg-white border-gray-200 hover:bg-amber-50 @endif {{ !$podeFicha ? 'lux-btn-ficha-off' : '' }}"
                                                title="{{ $podeFicha ? 'Imprimir ficha de separação' : 'Confirme o pedido para imprimir a ficha' }}" aria-label="Imprimir ficha" data-bs-toggle="tooltip" data-bs-placement="top">
                                                <i data-lucide="printer" class="size-4"></i>
                                            </a>
                                            @if($showDelete)
                                                <button type="submit" class="inline-flex items-center justify-center p-2 border-l btn-delete venda-acao-excluir rounded-r-xl @if($vendasUiDark) text-rose-300 bg-gray-800/95 border-gray-600/80 hover:bg-rose-500/22 @else text-rose-600 bg-white border-gray-200 hover:bg-rose-50 @endif" title="Excluir venda (apenas NF-e novo ou rejeitado)" aria-label="Excluir" data-bs-toggle="tooltip" data-bs-placement="top">
                                                    <i data-lucide="trash-2" class="size-4"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                                               <td colspan="6" class="px-4 py-14 text-center text-sm @if($vendasUiDark) text-gray-500 @else text-gray-600 @endif">Nada encontrado com os filtros atuais.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @isset($data->appends)
            <div class="shrink-0 mt-1.5 text-xs @if($vendasUiDark) [&_a]:text-indigo-400 [&_a:hover]:text-indigo-300 [&_.page-link]:bg-gray-800 [&_.page-link]:border-gray-700 @else [&_a]:text-indigo-600 [&_a:hover]:text-indigo-800 [&_.page-link]:bg-white [&_.page-link]:border-gray-300 [&_.page-link]:text-gray-700 @endif">
                {!! $data->appends(request()->all())->links() !!}
            </div>
        @endisset
    </div>
</div>
</div>

{{-- Modais e e-mail: inalterados --}}
<div class="modal fade" id="modal-cancelar" aria-modal="true" role="dialog" style="overflow:scroll;" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cancelar NFe <strong class="text-danger numero_nfe"></strong></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="col-md-12">
                    {!! Form::text('motivo-cancela', 'Justificativa') !!}
                </div>
            </div>
            <div class="modal-footer">
                <button id="btn-cancelar-send" type="button" class="btn btn-danger px-5">Cancelar</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="modal-corrigir" aria-modal="true" role="dialog" style="overflow:scroll;" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Corrigir NFe <strong class="text-warning numero_nfe"></strong></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="col-md-12">
                    {!! Form::text('motivo-corrige', 'Descrição da correção') !!}
                </div>
            </div>
            <div class="modal-footer">
                <button id="btn-corrige-send" type="button" class="btn btn-warning px-5">Corrigir</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="modal-inutilizar" aria-modal="true" role="dialog" style="overflow:scroll;" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">INUTILIZAÇÃO DE NÚMERO(s) DE NFe</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-3">
                        {!! Form::tel('numero_inicial', 'Nº inicial') !!}
                    </div>
                    <div class="col-md-3">
                        {!! Form::tel('numero_final', 'Nº final') !!}
                    </div>
                    <div class="col-md-12 mt-3">
                        {!! Form::text('motivo-inutiliza', 'Justificativa') !!}
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button id="btn-inutiliza-send" type="button" class="btn btn-primary px-5">Inutilizar</button>
            </div>
        </div>
    </div>
</div>

@include('modals._email', ['not_submit' => true])

@endsection

@section('js')
<script type="text/javascript" src="/js/nf.js"></script>
<script type="text/javascript" src="/js/vendas.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const dateInputs = document.querySelectorAll('#lux-vendas-index input[type="date"]');
  dateInputs.forEach(input => {
    input.addEventListener('click', function(e) {
      if (e.offsetX > this.clientWidth - 30) return;
      this.showPicker?.();
      this.focus();
    });
  });
});

/** Select2 nos filtros */
$(function () {
 if (!$.fn.select2) return;
    var $st = $('#filter_status_pedido');
    if ($st.length) {
        $st.select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: 'Todos os status',
            allowClear: true,
            closeOnSelect: false,
            language: { noResults: function () { return 'Nenhum resultado'; } }
        });
    }
    var $vu = $('#filter_usuario_id');
    if ($vu.length) {
        $vu.select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: 'Todos os vendedores',
            allowClear: true
        });
    }
    var $pg = $('#filter_status_pagamento');
    if ($pg.length) {
        $pg.select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: 'Todos (pagamento)',
            allowClear: true
        });
    }
    $('#filter_somente_abertos').on('change', function () {
        $(this).toggleClass('is-active', $(this).val() === '1');
    });
});

/** Ícones Lucide (inclui células atualizadas por workflow) */
function luxVendasLucideRefresh() {
    if (typeof lucide !== 'undefined' && lucide.createIcons) {
        lucide.createIcons();
    }
}

/** Tooltips Bootstrap 5, se disponíveis (fallback: atributo title nativo) */
function luxVendasInitTooltips() {
    if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) return;
    document.querySelectorAll('#lux-vendas-index [data-bs-toggle="tooltip"]').forEach(function (el) {
        if (bootstrap.Tooltip.getInstance(el)) return;
        new bootstrap.Tooltip(el, { container: 'body' });
    });
}

document.addEventListener('DOMContentLoaded', function () {
    luxVendasLucideRefresh();
    luxVendasInitTooltips();
});

/** Nome / valor: copiar texto simples (Excel cola sem formatação de tela) */
document.addEventListener('DOMContentLoaded', function () {
    var root = document.getElementById('lux-vendas-index');
    if (!root) return;
    function copyPlain(text) {
        if (!text) return Promise.reject();
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }
        return new Promise(function (resolve, reject) {
            try {
                var ta = document.createElement('textarea');
                ta.value = text;
                ta.setAttribute('readonly', '');
                ta.style.position = 'fixed';
                ta.style.left = '-9999px';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                resolve();
            } catch (e) {
                reject(e);
            }
        });
    }
    root.addEventListener('click', function (ev) {
        var el = ev.target.closest('.lux-copy-nome, .lux-copy-valor');
        if (!el || !root.contains(el)) return;
        ev.preventDefault();
        var t = el.getAttribute('data-copy');
        if (t === null || t === '') return;
        copyPlain(t).catch(function () {});
    });
    root.addEventListener('keydown', function (ev) {
        if (ev.key !== 'Enter') return;
        var el = ev.target.closest('.lux-copy-nome, .lux-copy-valor');
        if (!el || !root.contains(el)) return;
        ev.preventDefault();
        var t = el.getAttribute('data-copy');
        if (t === null || t === '') return;
        copyPlain(t).catch(function () {});
    });
});

document.addEventListener('DOMContentLoaded', function(){
    const checkAll = document.getElementById('check-all');
    const selectAllBtn = document.getElementById('btn-select-all');
    const selectAllLabel = document.getElementById('btn-select-all-label');
    const printBtn = document.getElementById('btn-print-selected');
    const downloadBtn = document.getElementById('btn-download-selected');
    const rotaBtn = document.getElementById('btn-gerar-rota');
    const entregueBtn = document.getElementById('btn-marcar-entregue');
    const csrf = '{{ csrf_token() }}';
    window.LUX_VENDAS_USUARIO_ADM = @json(!empty($usuarioAdm));

    const WF = {
        labelsPed: @json($labelStatusPedidoVenda),
        labelsPag: @json($labelStatusPagamentoVenda),
        clsPed: @json($badgeTwPedido),
        clsPag: @json($badgeTwPagamento),
    };
    const WF_FALLBACK_BADGE = @json($luxBadgeNeutral);

    const IcoPed = {
        aguardando_confirmacao: 'clock',
        em_elaboracao: 'clock',
        confirmado: 'circle-check',
        em_separacao: 'package',
        separado: 'package-check',
        alteracao_pendente: 'triangle-alert',
        em_rota_entrega: 'truck',
        ocorrencia_entrega: 'triangle-alert',
        entregue: 'house',
        cancelada: 'ban'
    };
    const IcoPag = {
        pendente: 'wallet',
        pago: 'banknote',
        parcial: 'percent',
        estornado: 'rotate-ccw'
    };

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function pedidoAcaoPorStatus(st) {
        const m = { aguardando_confirmacao: 'confirmar_pedido', em_elaboracao: 'confirmar_pedido', em_separacao: 'marcar_separado', alteracao_pendente: 'confirmar_alteracao' };
        return m[st] || '';
    }

    const FICHA_PRINT_ALLOWED = ['confirmado', 'em_separacao', 'separado', 'alteracao_pendente', 'em_rota_entrega', 'entregue'];

    function podeImprimirFicha(status) {
        return FICHA_PRINT_ALLOWED.indexOf(status) !== -1;
    }

    function syncPrintFichaBtn(tr) {
        const btn = tr.querySelector('.btn-print-ficha');
        if (!btn) return;
        const st = tr.dataset.statusPedido;
        const ok = podeImprimirFicha(st);
        const vid = tr.getAttribute('data-venda-row');
        btn.dataset.fichaEnabled = ok ? '1' : '0';
        btn.href = ok ? (printFichaBase + '/' + encodeURIComponent(vid)) : '#';
        btn.target = ok ? '_blank' : '_self';
        btn.classList.toggle('lux-btn-ficha-off', !ok);
        btn.title = ok ? 'Imprimir ficha de separação' : 'Confirme o pedido para imprimir a ficha';
    }

    function iconPedHtml(st) {
        const name = IcoPed[st] || 'circle';
        return '<i data-lucide="' + name + '" class="size-3.5 shrink-0" aria-hidden="true"></i>';
    }
    function iconPagHtml(st) {
        const name = IcoPag[st] || 'circle';
        return '<i data-lucide="' + name + '" class="size-3.5 shrink-0" aria-hidden="true"></i>';
    }

    function renderPedidoCell(vendaId, statusPedido) {
        const tr = document.querySelector('tr[data-venda-row="' + vendaId + '"]');
        if (!tr) return;
        tr.dataset.statusPedido = statusPedido;
        const fech = tr.dataset.fechadaCaixa === '1';
        const td = tr.querySelector('.td-wf-pedido');
        if (!td) return;
        const label = WF.labelsPed[statusPedido] || statusPedido;
        const cls = WF.clsPed[statusPedido] || WF_FALLBACK_BADGE;
        const clicavel = !fech && ['aguardando_confirmacao', 'em_elaboracao', 'em_separacao', 'alteracao_pendente'].indexOf(statusPedido) !== -1;
        const acao = pedidoAcaoPorStatus(statusPedido);
        const ico = iconPedHtml(statusPedido);
        if (clicavel) {
            td.innerHTML = '<span role="button" class="wf-pedido ' + cls + ' cursor-pointer" data-venda="' + vendaId + '" data-acao="' + acao + '" title="Clique para avançar o status do pedido">' + ico + escapeHtml(label) + '</span>';
        } else {
            td.innerHTML = '<span class="' + cls + '">' + ico + escapeHtml(label) + '</span>';
        }
        syncPrintFichaBtn(tr);
        syncLinhaVendaAlerta(tr);
        luxVendasLucideRefresh();
    }

    function renderPagamentoCell(vendaId, statusPag) {
        const tr = document.querySelector('tr[data-venda-row="' + vendaId + '"]');
        if (!tr) return;
        tr.dataset.statusPagamento = statusPag;
        const fech = tr.dataset.fechadaCaixa === '1';
        const td = tr.querySelector('.td-wf-pagamento');
        if (!td) return;
        const label = WF.labelsPag[statusPag] || statusPag;
        const cls = WF.clsPag[statusPag] || WF_FALLBACK_BADGE;
        const clicavel = !fech && statusPag === 'pendente';
        const ico = iconPagHtml(statusPag);
        if (clicavel) {
            td.innerHTML = '<span role="button" class="wf-pagamento ' + cls + ' cursor-pointer" data-venda="' + vendaId + '" title="Clique para registrar pagamento recebido">' + ico + escapeHtml(label) + '</span>';
        } else {
            td.innerHTML = '<span class="' + cls + '">' + ico + escapeHtml(label) + '</span>';
        }
        luxVendasLucideRefresh();
    }

    function escAttr(s) {
        if (s == null || s === '') return '';
        return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    function aplicarLinhaFechadaNoCaixa(vendaId, fechadaEmLabel) {
        const tr = document.querySelector('tr[data-venda-row="' + vendaId + '"]');
        if (!tr) return;
        tr.dataset.fechadaCaixa = '1';
        const stPed = tr.dataset.statusPedido;
        const stPag = tr.dataset.statusPagamento;
        renderPedidoCell(vendaId, stPed);
        renderPagamentoCell(vendaId, stPag);
        const tdCaixa = tr.querySelector('.td-caixa');
        if (tdCaixa) {
            var t = 'Fechada no caixa';
            if (fechadaEmLabel) t += ' em ' + fechadaEmLabel;
            if (window.LUX_VENDAS_USUARIO_ADM) {
                var root = document.getElementById('lux-vendas-index');
                var isDark = root && root.getAttribute('data-vendas-ui') === 'dark';
                var bcls = 'inline-flex items-center justify-center rounded-lg border p-2 shadow-sm transition-all duration-200 ease-out hover:shadow-md active:scale-95 btn-reabrir-caixa ';
                bcls += isDark ? 'border-amber-400/45 bg-amber-500/15 text-amber-100 hover:bg-amber-500/25' : 'border-amber-300 bg-amber-50 text-amber-900 hover:bg-amber-100';
                tdCaixa.innerHTML = '<button type="button" class="' + bcls + '" data-venda="' + vendaId + '" title="Reabrir no caixa (ADM): permitir edição" data-bs-toggle="tooltip" data-bs-placement="left"><i data-lucide="lock-open" class="size-4"></i></button>';
            } else {
                tdCaixa.innerHTML = '<span class="inline-flex text-gray-500" title="' + escAttr(t) + '"><i data-lucide="lock-keyhole" class="size-4"></i></span>';
            }
            luxVendasLucideRefresh();
        }
        tr.querySelectorAll('.venda-acao-editar').forEach(function(el) { el.remove(); });
        tr.querySelectorAll('.venda-acao-excluir').forEach(function(el) { el.remove(); });
    }

    function toastOk(msg) {
        swal({ title: msg || 'OK', icon: 'success', timer: 1400, buttons: false });
    }

    function getSelectedIds(){
        return Array.from(document.querySelectorAll('.checkbox-venda:checked')).map(i => i.value);
    }
    function updateSelectAllBtnText() {
        if (!selectAllBtn) return;
        const all = document.querySelectorAll('.checkbox-venda');
        const n = all.length;
        const checked = document.querySelectorAll('.checkbox-venda:checked').length;
        const allOn = n > 0 && checked === n;
        if (selectAllLabel) selectAllLabel.textContent = allOn ? 'Desmarcar todos' : 'Selecionar todos';
    }
    function setAllChecked(val){
        document.querySelectorAll('.checkbox-venda').forEach(cb => cb.checked = val);
        if (checkAll) checkAll.checked = val;
        updateSelectAllBtnText();
    }
    function syncLinhaVendaAlerta(tr) {
        if (!tr) return;
        var st = tr.dataset.statusPedido;
        tr.classList.remove('border-l-4', 'border-rose-500', 'bg-rose-500/10', 'bg-rose-50');
        if (st === 'alteracao_pendente') {
            tr.classList.add('border-l-4', 'border-rose-500');
            var root = document.getElementById('lux-vendas-index');
            var isDark = root && root.getAttribute('data-vendas-ui') === 'dark';
            tr.classList.add(isDark ? 'bg-rose-500/10' : 'bg-rose-50');
        }
    }
    if (checkAll){
        checkAll.addEventListener('change', function() {
            setAllChecked(checkAll.checked);
        });
    }
    if (selectAllBtn){
        selectAllBtn.addEventListener('click', function() {
            var all = document.querySelectorAll('.checkbox-venda');
            var n = all.length;
            var checked = document.querySelectorAll('.checkbox-venda:checked').length;
            var allSelected = n > 0 && checked === n;
            setAllChecked(!allSelected);
        });
    }
    updateSelectAllBtnText();
    document.body.addEventListener('change', function(ev) {
        if (ev.target && ev.target.classList && ev.target.classList.contains('checkbox-venda')) updateSelectAllBtnText();
    });
    var printStatusBase = "{{ url('vendas/print-status') }}";
    var printPdfBase = "{{ url('vendas/print') }}";
    var printFichaBase = "{{ url('vendas/print-ficha') }}";

    document.body.addEventListener('click', function(ev) {
        var ficha = ev.target.closest('.btn-print-ficha');
        if (ficha && ficha.dataset.fichaEnabled !== '1') {
            ev.preventDefault();
            swal("Atenção", "Confirme o pedido antes de imprimir a ficha de separação.", "warning");
        }
    });

    async function processSelectedFichas() {
        const ids = getSelectedIds();
        if (ids.length === 0) {
            swal("Atenção", "Selecione pelo menos uma venda!", "warning");
            return;
        }
        for (var i = 0; i < ids.length; i++) {
            var vid = ids[i];
            var tr = document.querySelector('tr[data-venda-row="' + vid + '"]');
            if (!tr || !podeImprimirFicha(tr.dataset.statusPedido)) {
                continue;
            }
            try {
                var res = await fetch(printStatusBase + '/' + encodeURIComponent(vid), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({})
                });
                var j = await res.json().catch(function() { return {}; });
                if (res.ok && j.status_pedido) {
                    renderPedidoCell(parseInt(vid, 10), j.status_pedido);
                }
            } catch (err) {}
            window.open(printFichaBase + '/' + encodeURIComponent(vid), '_blank');
            await new Promise(function(r) { setTimeout(r, 350); });
        }
        toastOk('Ficha de separação aberta; status atualizado na lista.');
    }

    async function processSelectedPedidoPdf() {
        const ids = getSelectedIds();
        if (ids.length === 0) {
            swal("Atenção", "Selecione pelo menos uma venda!", "warning");
            return;
        }
        for (var i = 0; i < ids.length; i++) {
            var vid = ids[i];
            var url = printPdfBase + '/' + encodeURIComponent(vid) + '?download=1';
            var link = document.createElement('a');
            link.href = url;
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            link.remove();
            await new Promise(function(r) { setTimeout(r, 350); });
        }
        toastOk('Download do pedido iniciado.');
    }

    if (printBtn) {
        printBtn.addEventListener('click', function() { processSelectedFichas(); });
    }
    if (downloadBtn) {
        downloadBtn.addEventListener('click', function() { processSelectedPedidoPdf(); });
    }

    document.querySelectorAll('tr[data-venda-row]').forEach(function(tr) {
        syncPrintFichaBtn(tr);
    });

    async function gerarRota(confirmarAlteracoes) {
        const ids = getSelectedIds();
        if (ids.length === 0) {
            swal("Atenção", "Selecione pelo menos uma venda!", "warning");
            return;
        }
        const res = await fetch("{{ route('rotas-entrega.store') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ ids: ids, confirmar_alteracoes: !!confirmarAlteracoes })
        });
        if (res.status === 422) {
            const j = await res.json();
            if (j.needs_confirmation) {
                swal({
                    title: "Alterações pendentes",
                    text: j.message || "Confirme que as alterações físicas foram conferidas antes de gerar a rota.",
                    icon: "warning",
                    buttons: true,
                    dangerMode: true
                }).then(function(ok) {
                    if (ok) gerarRota(true);
                });
                return;
            }
        }
        if (res.status === 423) {
            const j = await res.json().catch(function() { return {}; });
            swal("Atenção", j.message || "Venda fechada no caixa.", "warning");
            return;
        }
        if (res.status === 409) {
            const j = await res.json().catch(function() { return {}; });
            swal("Pedido em outra rota", j.message || "Um ou mais pedidos já estão em rota ativa.", "warning");
            return;
        }
        if (!res.ok) {
            const j = await res.json().catch(function() { return {}; });
            swal("Erro", j.message || "Não foi possível criar a rota.", "error");
            return;
        }
        const j = await res.json();
        if (j.redirect) {
            window.location.href = j.redirect;
        }
    }
    if (rotaBtn) {
        rotaBtn.addEventListener('click', function() { gerarRota(false); });
    }

    if (entregueBtn) {
        entregueBtn.addEventListener('click', function() {
            const ids = getSelectedIds();
            if (ids.length === 0) {
                swal("Atenção", "Selecione pelo menos uma venda!", "warning");
                return;
            }
            swal({
                title: "Marcar como entregue?",
                text: "Somente vendas com status \"Em rota de entrega\" serão marcadas como entregues.",
                icon: "info",
                buttons: true
            }).then(function(ok) {
                if (!ok) return;
                fetch("{{ route('vendas.workflow-marcar-entregue') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ ids: ids })
                }).then(function(r) { return r.json().then(function(j) { return { ok: r.ok, j: j }; }); })
                .then(function(x) {
                    if (!x.ok) {
                        swal("Erro", (x.j && x.j.message) || "Falha ao atualizar.", "error");
                        return;
                    }
                    ids.forEach(function(id) {
                        var tr = document.querySelector('tr[data-venda-row="' + id + '"]');
                        if (tr && tr.dataset.statusPedido === 'em_rota_entrega') {
                            renderPedidoCell(id, 'entregue');
                        }
                    });
                    toastOk('Entregas atualizadas.');
                });
            });
        });
    }

    document.body.addEventListener('click', function(e) {
        var ped = e.target.closest('.wf-pedido');
        if (ped) {
            e.preventDefault();
            var id = ped.getAttribute('data-venda');
            var acao = ped.getAttribute('data-acao');
            var msgs = {
                confirmar_pedido: 'Confirmar este pedido?',
                marcar_separado: 'Confirmar que a separação foi concluída?',
                confirmar_alteracao: 'Confirmar que a alteração já foi efetuada fisicamente no pedido?'
            };
            swal({
                title: "Confirmação",
                text: msgs[acao] || "Confirmar?",
                icon: "info",
                buttons: true
            }).then(function(ok) {
                if (!ok) return;
                fetch("{{ route('vendas.workflow-status') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ venda_id: parseInt(id, 10), acao: acao })
                }).then(function(r) { return r.json().then(function(j) { return { ok: r.ok, status: r.status, j: j }; }); })
                .then(function(x) {
                    if (x.status === 423) {
                        swal("Atenção", (x.j && x.j.message) || "Venda fechada no caixa.", "warning");
                        return;
                    }
                    if (!x.ok) {
                        swal("Erro", (x.j && x.j.message) || "Não foi possível atualizar.", "error");
                        return;
                    }
                    if (x.j && x.j.status_pedido) {
                        renderPedidoCell(parseInt(id, 10), x.j.status_pedido);
                    }
                    if (acao === 'confirmar_pedido') {
                        var trConf = document.querySelector('tr[data-venda-row="' + id + '"]');
                        if (trConf) syncPrintFichaBtn(trConf);
                    }
                    toastOk('Status atualizado.');
                });
            });
            return;
        }
        var pag = e.target.closest('.wf-pagamento');
        if (pag) {
            e.preventDefault();
            var vid = pag.getAttribute('data-venda');
            swal({
                title: "Pagamento",
                text: "Registrar que o pagamento já foi recebido?",
                icon: "info",
                buttons: true
            }).then(function(ok) {
                if (!ok) return;
                fetch("{{ route('vendas.workflow-status') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ venda_id: parseInt(vid, 10), acao: 'marcar_pago' })
                }).then(function(r) { return r.json().then(function(j) { return { ok: r.ok, status: r.status, j: j }; }); })
                .then(function(x) {
                    if (x.status === 423) {
                        swal("Atenção", (x.j && x.j.message) || "Venda fechada no caixa.", "warning");
                        return;
                    }
                    if (!x.ok) {
                        swal("Erro", (x.j && x.j.message) || "Não foi possível atualizar.", "error");
                        return;
                    }
                    if (x.j && x.j.status_pagamento) {
                        renderPagamentoCell(parseInt(vid, 10), x.j.status_pagamento);
                    }
                    toastOk('Pagamento registrado.');
                });
            });
            return;
        }
        var fech = e.target.closest('.btn-fechar-caixa');
        if (fech) {
            e.preventDefault();
            var fid = fech.getAttribute('data-venda');
            swal({
                title: "Fechar no caixa?",
                text: "Esta venda ficará travada: ninguém poderá editar, excluir ou alterar status.",
                icon: "warning",
                buttons: true,
                dangerMode: true
            }).then(function(ok) {
                if (!ok) return;
                fetch("{{ url('vendas/fechar-caixa') }}/" + fid, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json'
                    }
                }).then(function(r) { return r.json().then(function(j) { return { ok: r.ok, j: j }; }); })
                .then(function(x) {
                    if (!x.ok) {
                        swal("Erro", (x.j && x.j.message) || "Não foi possível fechar.", "error");
                        return;
                    }
                    aplicarLinhaFechadaNoCaixa(parseInt(fid, 10), x.j && x.j.fechada_em_label ? x.j.fechada_em_label : null);
                    toastOk('Venda fechada no caixa.');
                });
            });
            return;
        }
        var reab = e.target.closest('.btn-reabrir-caixa');
        if (reab) {
            e.preventDefault();
            var rid = reab.getAttribute('data-venda');
            swal({
                title: "Reabrir no caixa?",
                text: "A venda poderá ser editada, excluída (se permitido) e ter status alterado novamente.",
                icon: "warning",
                buttons: true,
                dangerMode: true
            }).then(function(ok) {
                if (!ok) return;
                fetch("{{ url('vendas/reabrir-caixa') }}/" + rid, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json'
                    }
                }).then(function(r) { return r.json().then(function(j) { return { ok: r.ok, j: j }; }); })
                .then(function(x) {
                    if (!x.ok) {
                        swal("Erro", (x.j && x.j.message) || "Não foi possível reabrir.", "error");
                        return;
                    }
                    toastOk('Caixa reaberto. Atualizando…');
                    window.location.reload();
                });
            });
        }
    });
});
</script>
@endsection
