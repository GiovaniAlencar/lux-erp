@extends('default.layout', ['title' => 'Rota #' . $rota->id])
@section('css')
<style>
    .rota-page {
        --rota-surface: #f8fafc;
        --rota-surface-2: #ffffff;
        --rota-accent-bg: linear-gradient(135deg, #eff6ff 0%, #e0f2fe 100%);
        --rota-border: #cbd5e1;
        --rota-border-soft: #e2e8f0;
        --rota-text: #111827;
        --rota-text-muted: #64748b;
        --rota-label: #6b7280;
        --rota-readonly-bg: #f1f5f9;
        --rota-link-bg: #f8fafc;
        --rota-prio-bg: #fffbeb;
        --rota-prio-header: #fef3c7;
        --rota-save-bar-bg: #ffffff;
        --rota-btn-ghost-border: #94a3b8;
        --rota-btn-ghost-text: #475569;
        --rota-btn-ghost-hover: #f1f5f9;
    }

    html.dark-theme .rota-page {
        --rota-surface: #1a1d21;
        --rota-surface-2: #171717;
        --rota-accent-bg: linear-gradient(135deg, #1e293b 0%, #172033 100%);
        --rota-border: rgba(148, 163, 184, 0.22);
        --rota-border-soft: rgba(255, 255, 255, 0.08);
        --rota-text: #e5e7eb;
        --rota-text-muted: #9ca3af;
        --rota-label: #a1a1aa;
        --rota-readonly-bg: rgba(255, 255, 255, 0.05);
        --rota-link-bg: rgba(255, 255, 255, 0.04);
        --rota-prio-bg: rgba(245, 158, 11, 0.08);
        --rota-prio-header: rgba(245, 158, 11, 0.14);
        --rota-save-bar-bg: #171717;
        --rota-btn-ghost-border: rgba(255, 255, 255, 0.22);
        --rota-btn-ghost-text: #d1d5db;
        --rota-btn-ghost-hover: rgba(255, 255, 255, 0.08);
    }

    .rota-page .rota-resumo-box {
        background: var(--rota-accent-bg);
        border: 1px solid var(--rota-border);
        border-radius: 12px;
        padding: 1rem 1.25rem;
        color: var(--rota-text);
    }
    .rota-page .rota-resumo-box h5 { color: var(--rota-text); }
    .rota-page .rota-meta { color: var(--rota-text-muted) !important; }
    .rota-page .rota-meta strong { color: var(--rota-text); }
    .rota-page .rota-hint { color: var(--rota-text-muted); }
    .rota-page .rota-hint strong { color: var(--rota-text); }

    .rota-actions {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
        align-items: center;
        justify-content: flex-end;
    }
    .rota-btn {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        padding: .45rem .9rem;
        font-size: .8125rem;
        font-weight: 600;
        line-height: 1.2;
        border-radius: 8px;
        border: 1px solid transparent;
        text-decoration: none;
        transition: background .15s ease, border-color .15s ease, color .15s ease, opacity .15s ease;
        white-space: nowrap;
    }
    .rota-btn:hover { text-decoration: none; }
    .rota-btn-ghost {
        background: var(--rota-surface-2);
        border-color: var(--rota-btn-ghost-border);
        color: var(--rota-btn-ghost-text);
    }
    .rota-btn-ghost:hover {
        background: var(--rota-btn-ghost-hover);
        color: var(--rota-text);
        border-color: var(--rota-btn-ghost-border);
    }
    .rota-btn-txt {
        background: #ea580c;
        border-color: #c2410c;
        color: #fff;
    }
    .rota-btn-txt:hover {
        background: #c2410c;
        border-color: #9a3412;
        color: #fff;
    }
    .rota-btn-save {
        background: #15803d;
        border-color: #166534;
        color: #fff;
    }
    .rota-btn-save:hover {
        background: #166534;
        border-color: #14532d;
        color: #fff;
    }
    .rota-btn-finish {
        background: transparent;
        border-color: #22c55e;
        color: #15803d;
    }
    .rota-btn-finish:hover {
        background: rgba(34, 197, 94, 0.12);
        color: #15803d;
        border-color: #22c55e;
    }
    html.dark-theme .rota-btn-finish {
        border-color: #4ade80;
        color: #86efac;
    }
    html.dark-theme .rota-btn-finish:hover {
        background: rgba(74, 222, 128, 0.12);
        color: #bbf7d0;
    }
    .rota-btn-danger {
        background: transparent;
        border-color: #f87171;
        color: #dc2626;
    }
    .rota-btn-danger:hover {
        background: rgba(248, 113, 113, 0.12);
        color: #b91c1c;
        border-color: #f87171;
    }
    html.dark-theme .rota-btn-danger {
        border-color: #f87171;
        color: #fca5a5;
    }
    html.dark-theme .rota-btn-danger:hover {
        background: rgba(248, 113, 113, 0.15);
        color: #fecaca;
    }
    .rota-btn:disabled,
    .rota-btn.disabled {
        opacity: .45;
        cursor: not-allowed;
        pointer-events: none;
    }

    .rota-entrega-card {
        border-radius: 12px;
        border: 1px solid var(--rota-border-soft);
        overflow: hidden;
        background: var(--rota-surface-2);
    }
    .rota-entrega-card.prioridade {
        border-color: #f59e0b;
        box-shadow: inset 4px 0 0 #f59e0b;
        background: var(--rota-prio-bg);
    }
    .rota-entrega-card .card-header {
        background: var(--rota-surface);
        border-bottom: 1px solid var(--rota-border-soft);
        padding: .75rem 1rem;
    }
    .rota-entrega-card.prioridade .card-header {
        background: var(--rota-prio-header);
    }
    .rota-field-label {
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: var(--rota-label);
        margin-bottom: .25rem;
    }
    .rota-readonly-field {
        background: var(--rota-readonly-bg) !important;
        color: var(--rota-text-muted);
        border-color: var(--rota-border-soft);
    }
    .badge-entregue { background: #dcfce7; color: #166534; }
    .badge-ocorrencia { background: #fee2e2; color: #991b1b; }
    .badge-pendente { background: #f3f4f6; color: #4b5563; }
    html.dark-theme .badge-entregue { background: rgba(34, 197, 94, 0.18); color: #86efac; }
    html.dark-theme .badge-ocorrencia { background: rgba(239, 68, 68, 0.18); color: #fca5a5; }
    html.dark-theme .badge-pendente { background: rgba(255, 255, 255, 0.08); color: #d1d5db; }
    .rota-badge-pagamento {
        background: var(--rota-readonly-bg);
        color: var(--rota-text-muted);
        border: 1px solid var(--rota-border-soft);
        font-weight: 500;
    }
    .rota-link-box {
        background: var(--rota-link-bg);
        border: 1px dashed var(--rota-border);
        border-radius: 8px;
        padding: .5rem .75rem;
        font-size: 12px;
        word-break: break-all;
        color: var(--rota-text-muted);
    }
    .rota-save-bar {
        position: sticky;
        bottom: 0;
        z-index: 20;
        background: var(--rota-save-bar-bg);
        border: 1px solid var(--rota-border-soft);
        border-radius: 12px;
        padding: .85rem 1rem;
        box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.08);
    }
    html.dark-theme .rota-save-bar {
        box-shadow: 0 -4px 24px rgba(0, 0, 0, 0.35);
    }
    .rota-page .rota-status-badge.bg-warning { color: #1f2937 !important; }
    html.dark-theme .rota-page .rota-status-badge.bg-warning { color: #1f2937 !important; }
    .rota-pag-pago { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
    .rota-pag-entrega { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
    .rota-pag-parcial { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
    html.dark-theme .rota-pag-pago { background: rgba(34,197,94,.18); color: #86efac; border-color: rgba(74,222,128,.35); }
    html.dark-theme .rota-pag-entrega { background: rgba(245,158,11,.15); color: #fcd34d; border-color: rgba(251,191,36,.35); }
    html.dark-theme .rota-pag-parcial { background: rgba(59,130,246,.15); color: #93c5fd; border-color: rgba(96,165,250,.35); }
    .rota-valor-pedido-box {
        background: var(--rota-readonly-bg);
        border: 1px solid var(--rota-border-soft);
        border-radius: 8px;
        padding: .5rem .75rem;
    }
    .rota-valor-pedido-box .valor { font-size: 1.15rem; font-weight: 700; color: var(--rota-text); }
    .rota-resumo-pagamento {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .75rem;
        margin-top: .75rem;
        padding: .65rem .85rem;
        background: var(--rota-readonly-bg);
        border: 1px solid var(--rota-border-soft);
        border-radius: 10px;
    }
    .rota-resumo-pagamento .valor-rota {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--rota-text);
    }
    .rota-badge-motoboy-pago { background: #dcfce7; color: #166534; }
    .rota-badge-motoboy-pendente { background: #fef3c7; color: #92400e; }
    html.dark-theme .rota-badge-motoboy-pago { background: rgba(34,197,94,.18); color: #86efac; }
    html.dark-theme .rota-badge-motoboy-pendente { background: rgba(245,158,11,.15); color: #fcd34d; }
</style>
@endsection
@section('content')
@php
    $editavel = in_array($rota->status, ['rascunho', 'em_rota'], true);
    $oldItens = old('itens', []);
@endphp
<div class="page-content rota-page" id="rota-page"
    data-index-url="{{ route('rotas-entrega.index') }}"
    data-pedidos-url="{{ route('rotas-entrega.pedidos-disponiveis', $rota->id) }}"
    data-adicionar-url="{{ route('rotas-entrega.itens.store', $rota->id) }}">
    <div id="rota-flash-messages">
        @if(session('flash_sucesso'))
        <div class="alert alert-success py-2">{{ session('flash_sucesso') }}</div>
        @endif
        @if(session('flash_erro'))
        <div class="alert alert-danger py-2">{{ session('flash_erro') }}</div>
        @endif
        @if(session('flash_warning'))
        <div class="alert alert-warning py-2">{{ session('flash_warning') }}</div>
        @endif
    </div>

    <div class="rota-resumo-box mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <h5 class="mb-1">Rota #{{ $rota->id }}</h5>
                <div class="rota-meta small">
                    {{ __data_pt($rota->created_at, 1) }}
                    · <span class="badge rota-status-badge bg-{{ $rota->status === 'em_rota' ? 'primary' : ($rota->status === 'finalizada' ? 'success' : 'warning') }}" id="rota-status-badge">{{ $rota->labelStatus() }}</span>
                    · <span id="rota-qtd-entregas">{{ $rota->itens->count() }}</span> entrega(s)
                    · Total frete: <strong id="rota-total-frete-header">R$ {{ __moeda($rota->totalFrete()) }}</strong>
                </div>
                <div class="rota-resumo-pagamento">
                    <div>
                        <div class="rota-field-label mb-1">Pagamento ao motoboy</div>
                        <div class="valor-rota" id="rota-valor-motoboy-pagamento">R$ {{ __moeda($rota->totalFrete()) }}</div>
                        <div class="small rota-hint">Soma dos fretes da rota</div>
                    </div>
                    <div>
                        <span class="badge rounded-pill px-3 py-2 {{ $rota->motoboy_pago ? 'rota-badge-motoboy-pago' : 'rota-badge-motoboy-pendente' }}">
                            {{ $rota->labelPagamentoMotoboy() }}
                        </span>
                        @if($rota->motoboy_pago && $rota->motoboy_pago_em)
                        <div class="small rota-hint mt-1">
                            {{ __data_pt($rota->motoboy_pago_em, 1) }}
                            @if($rota->motoboyPagoPor) · {{ $rota->motoboyPagoPor->nome }} @endif
                        </div>
                        @endif
                    </div>
                    @if($rota->podeRegistrarPagamentoMotoboy())
                    <div class="ms-auto">
                        @if(!$rota->motoboy_pago)
                        <form action="{{ route('rotas-entrega.pagar-motoboy', $rota->id) }}" method="post" class="d-inline m-0" onsubmit="return confirm('Confirmar que pagou R$ {{ __moeda($rota->totalFrete()) }} ao motoboy desta rota?');">
                            @csrf
                            <button type="submit" class="rota-btn rota-btn-save">
                                <i class="bi bi-cash-coin"></i> Marcar como pago
                            </button>
                        </form>
                        @else
                        <form action="{{ route('rotas-entrega.desfazer-pagamento-motoboy', $rota->id) }}" method="post" class="d-inline m-0" onsubmit="return confirm('Desfazer o registro de pagamento ao motoboy?');">
                            @csrf
                            <button type="submit" class="rota-btn rota-btn-ghost">
                                Desfazer pagamento
                            </button>
                        </form>
                        @endif
                    </div>
                    @endif
                </div>
            </div>
            <div class="rota-actions">
                <a href="{{ route('rotas-entrega.index') }}" class="rota-btn rota-btn-ghost">
                    <i class="bi bi-arrow-left"></i> Voltar
                </a>
                <span id="rota-btn-download-wrap">
                @if($rota->status !== 'rascunho')
                <a href="{{ route('rotas-entrega.download', $rota->id) }}" class="rota-btn rota-btn-txt" id="rota-btn-download">
                    <i class="bi bi-download"></i> Baixar TXT
                </a>
                @else
                <button type="button" class="rota-btn rota-btn-txt" disabled id="rota-btn-download" title="Salve a rota antes de baixar o TXT">
                    <i class="bi bi-download"></i> Baixar TXT
                </button>
                @endif
                </span>
                @if($editavel)
                <button type="submit" form="form-rota" class="rota-btn rota-btn-save">
                    <i class="bi bi-check-lg"></i> Salvar rota
                </button>
                @endif
                @if($rota->status !== 'finalizada')
                <form action="{{ route('rotas-entrega.finalizar', $rota->id) }}" method="post" class="d-inline m-0" onsubmit="return confirm('Finalizar esta rota?');">
                    @csrf
                    <button type="submit" class="rota-btn rota-btn-finish">Finalizar</button>
                </form>
                @endif
                @if($rota->podeExcluir())
                <form action="{{ route('rotas-entrega.destroy', $rota->id) }}" method="post" class="d-inline m-0" onsubmit="return confirm('Excluir esta rota? Os pedidos em rota voltarão para Separado.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rota-btn rota-btn-danger">Excluir rota</button>
                </form>
                @endif
            </div>
        </div>

        <div class="row g-3 mt-2 align-items-end">
            <div class="col-md-8 col-lg-8">
                <div class="small rota-hint">
                    Revise os dados e clique em <strong>Salvar rota</strong> para colocar os pedidos em <em>em rota de entrega</em>.
                    Use <strong>Agregar pedido deste cliente</strong> para enviar dois pedidos juntos.
                </div>
            </div>
        </div>
    </div>

    <form method="post" action="{{ route('rotas-entrega.salvar', $rota->id) }}" id="form-rota">
        @csrf

        <div class="rota-resumo-box mb-4 py-3">
            <div class="row g-3 align-items-end">
                <div class="col-md-6 col-lg-4">
                    <label class="rota-field-label" for="inp-motoboy">Motoboy responsável</label>
                    <input type="text" id="inp-motoboy" name="motoboy_nome" class="form-control" placeholder="Nome do motoboy"
                        value="{{ old('motoboy_nome', $rota->motoboy_nome) }}"
                        {{ $editavel ? '' : 'readonly' }}>
                </div>
            </div>
        </div>

        @if($editavel)
        <div class="d-flex justify-content-end mb-3">
            <button type="button" class="rota-btn rota-btn-ghost" id="btn-abrir-adicionar-pedido" data-bs-toggle="modal" data-bs-target="#modal-adicionar-pedido">
                <i class="bi bi-plus-lg"></i> Adicionar pedido
            </button>
        </div>
        @endif

        <div class="row g-4" id="lista-entregas">
            @foreach($rota->itens as $item)
            @include('rotas_entrega._item_card', [
                'oi' => $oldItens[$item->id] ?? [],
            ])
            @endforeach
        </div>

        @if($editavel)
        <div class="rota-save-bar mt-4 rounded">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span class="small rota-hint">Alterações só são gravadas ao clicar em Salvar rota.</span>
                <button type="submit" class="rota-btn rota-btn-save" id="btn-salvar-rota">
                    <i class="bi bi-check-lg"></i> Salvar rota
                </button>
            </div>
        </div>
        @endif
    </form>
</div>

@if($editavel)
<div class="modal fade" id="modal-adicionar-pedido" tabindex="-1" aria-labelledby="modal-adicionar-pedido-label" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-adicionar-pedido-label">Adicionar pedido à rota</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">Busque por número do pedido ou nome do cliente. Só aparecem pedidos disponíveis (não cancelados, não entregues, não fechados no caixa e que não estejam em outra rota).</p>
                <div class="input-group mb-3">
                    <input type="text" class="form-control" id="inp-busca-pedido" placeholder="Ex.: 1234 ou Maria Silva" autocomplete="off">
                    <button type="button" class="btn btn-primary" id="btn-buscar-pedido">
                        <i class="bi bi-search"></i> Buscar
                    </button>
                </div>
                <div id="busca-pedido-status" class="small text-muted mb-2 d-none"></div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0" id="tabela-pedidos-disponiveis">
                        <thead>
                            <tr>
                                <th style="width:5rem">#</th>
                                <th>Cliente</th>
                                <th>Bairro</th>
                                <th>Status</th>
                                <th class="text-end">Frete</th>
                                <th style="width:6rem"></th>
                            </tr>
                        </thead>
                        <tbody id="tbody-pedidos-disponiveis">
                            <tr class="text-muted"><td colspan="6" class="text-center py-4">Digite acima e clique em Buscar</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@section('js')
<script>
(function() {
    const csrf = '{{ csrf_token() }}';
    const page = document.getElementById('rota-page');
    const formRota = document.getElementById('form-rota');
    const flashEl = document.getElementById('rota-flash-messages');

    function moedaFmt(n) {
        if (typeof convertFloatToMoeda === 'function') return convertFloatToMoeda(n);
        return Number(n).toFixed(2).replace('.', ',');
    }

    function showFlash(msg, type) {
        if (!flashEl) return;
        flashEl.innerHTML = '<div class="alert alert-' + type + ' py-2 alert-dismissible fade show">' + msg +
            '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        if (typeof toastr !== 'undefined') {
            if (type === 'success') toastr.success(msg);
            else if (type === 'danger') toastr.error(msg);
            else toastr.warning(msg);
        }
    }

    function atualizarResumoRota(rota) {
        if (!rota) return;
        const qtd = document.getElementById('rota-qtd-entregas');
        const freteHdr = document.getElementById('rota-total-frete-header');
        const freteMot = document.getElementById('rota-valor-motoboy-pagamento');
        const badge = document.getElementById('rota-status-badge');
        const dlWrap = document.getElementById('rota-btn-download-wrap');

        if (qtd) qtd.textContent = rota.qtd_entregas;
        if (freteHdr) freteHdr.textContent = 'R$ ' + rota.total_frete_fmt;
        if (freteMot) freteMot.textContent = 'R$ ' + rota.total_frete_fmt;
        if (badge && rota.status_label) {
            badge.textContent = rota.status_label;
            badge.className = 'badge rota-status-badge bg-' + (rota.status === 'em_rota' ? 'primary' : (rota.status === 'finalizada' ? 'success' : 'warning'));
        }
        if (dlWrap && rota.status !== 'rascunho' && rota.download_url) {
            dlWrap.innerHTML = '<a href="' + rota.download_url + '" class="rota-btn rota-btn-txt" id="rota-btn-download"><i class="bi bi-download"></i> Baixar TXT</a>';
        }
    }

    function toggleValorRestante(card) {
        const sel = card.querySelector('.rota-situacao-pagamento');
        const campo = card.querySelector('.campo-valor-restante');
        if (!sel || !campo) return;
        campo.classList.toggle('d-none', sel.value !== 'pago_parcial');
    }

    function initCopyButtons(root) {
        (root || document).querySelectorAll('.btn-copy-link').forEach(function(btn) {
            if (btn.dataset.copyBound) return;
            btn.dataset.copyBound = '1';
            btn.addEventListener('click', function() {
                const link = btn.getAttribute('data-link');
                if (!link) return;
                navigator.clipboard.writeText(link).then(function() {
                    if (typeof toastr !== 'undefined') toastr.success('Link copiado!');
                });
            });
        });
    }

    function initNovosCards(root) {
        (root || document).querySelectorAll('.rota-item-row').forEach(function(card) {
            toggleValorRestante(card);
        });
        initCopyButtons(root);
        if (typeof $ !== 'undefined' && $.fn.mask) {
            $(root || document).find('.moeda').mask('#.##0,00', { reverse: true });
        }
    }

    function reordenarListaEntregas(ordem) {
        const lista = document.getElementById('lista-entregas');
        if (!lista || !ordem || !ordem.length) return;
        const map = {};
        lista.querySelectorAll('[data-item-wrapper]').forEach(function(el) {
            map[el.getAttribute('data-item-wrapper')] = el;
        });
        ordem.forEach(function(id) {
            if (map[id]) lista.appendChild(map[id]);
        });
    }

    initCopyButtons(document);

    if (formRota) {
        formRota.addEventListener('submit', async function(ev) {
            ev.preventDefault();
            const btn = document.getElementById('btn-salvar-rota');
            const btns = formRota.querySelectorAll('[type="submit"]');
            btns.forEach(function(b) { b.disabled = true; });
            if (btn) btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Salvando…';

            try {
                const res = await fetch(formRota.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new FormData(formRota)
                });
                const j = await res.json().catch(function() { return {}; });
                if (!res.ok || !j.ok) {
                    let msg = j.message || 'Erro ao salvar a rota.';
                    if (j.errors) {
                        const first = Object.values(j.errors)[0];
                        if (Array.isArray(first) && first[0]) msg = first[0];
                    }
                    showFlash(msg, 'danger');
                    return;
                }
                showFlash(j.message, 'success');
                atualizarResumoRota(j.rota);
            } catch (e) {
                showFlash('Falha ao salvar a rota.', 'danger');
            } finally {
                btns.forEach(function(b) { b.disabled = false; });
                if (btn) btn.innerHTML = '<i class="bi bi-check-lg"></i> Salvar rota';
            }
        });
    }

    document.getElementById('lista-entregas').addEventListener('click', async function(ev) {
        const btn = ev.target.closest('.btn-remover-item');
        if (!btn) return;
        const vendaId = btn.getAttribute('data-venda-id');
        const url = btn.getAttribute('data-url');
        if (!url || !confirm('Remover o pedido #' + vendaId + ' desta rota?')) return;

        btn.disabled = true;
        const cardWrap = btn.closest('[data-item-wrapper]');

        try {
            const res = await fetch(url, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const j = await res.json().catch(function() { return {}; });
            if (!res.ok || !j.ok) {
                showFlash(j.message || 'Erro ao remover pedido.', 'danger');
                btn.disabled = false;
                return;
            }
            if (j.rota_vazia && j.redirect) {
                showFlash(j.message, 'success');
                setTimeout(function() { window.location.href = j.redirect; }, 800);
                return;
            }
            if (cardWrap) {
                cardWrap.style.transition = 'opacity .25s ease';
                cardWrap.style.opacity = '0';
                setTimeout(function() { cardWrap.remove(); }, 250);
            }
            showFlash(j.message, 'success');
            atualizarResumoRota(j.rota);
        } catch (e) {
            showFlash('Falha ao remover pedido.', 'danger');
            btn.disabled = false;
        }
    });

    document.getElementById('lista-entregas').addEventListener('change', function(ev) {
        if (ev.target.classList.contains('rota-situacao-pagamento')) {
            const card = ev.target.closest('.rota-item-row');
            if (card) toggleValorRestante(card);
        }
        if (ev.target.classList.contains('rota-prioridade')) {
            const card = ev.target.closest('.rota-item-row');
            if (card) card.classList.toggle('prioridade', ev.target.checked);
        }
    });

    document.querySelectorAll('.btn-copy-link').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const link = btn.getAttribute('data-link');
            if (!link) return;
            navigator.clipboard.writeText(link).then(function() {
                if (typeof toastr !== 'undefined') toastr.success('Link copiado!');
            });
        });
    });

    const modalAdicionar = document.getElementById('modal-adicionar-pedido');
    const inpBusca = document.getElementById('inp-busca-pedido');
    const btnBuscar = document.getElementById('btn-buscar-pedido');
    const tbodyPedidos = document.getElementById('tbody-pedidos-disponiveis');
    const buscaStatus = document.getElementById('busca-pedido-status');
    const pedidosUrl = page ? page.getAttribute('data-pedidos-url') : '';
    const adicionarUrl = page ? page.getAttribute('data-adicionar-url') : '';
    let filtroClienteId = null;

    async function buscarPedidosDisponiveis(clienteId) {
        if (!pedidosUrl || !tbodyPedidos) return;
        if (clienteId !== undefined) {
            filtroClienteId = clienteId || null;
        }
        const q = (inpBusca && inpBusca.value) ? inpBusca.value.trim() : '';
        if (buscaStatus) {
            buscaStatus.classList.remove('d-none');
            buscaStatus.textContent = filtroClienteId
                ? 'Buscando outros pedidos deste cliente…'
                : 'Buscando…';
        }
        if (btnBuscar) btnBuscar.disabled = true;

        try {
            const params = new URLSearchParams();
            if (q) params.set('q', q);
            if (filtroClienteId) params.set('cliente_id', filtroClienteId);
            const qs = params.toString();
            const url = pedidosUrl + (qs ? ('?' + qs) : '');
            const res = await fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const j = await res.json().catch(function() { return {}; });
            if (!res.ok || !j.ok) {
                tbodyPedidos.innerHTML = '<tr class="text-danger"><td colspan="6" class="text-center py-3">' + (j.message || 'Erro na busca.') + '</td></tr>';
                return;
            }
            if (!j.pedidos || !j.pedidos.length) {
                tbodyPedidos.innerHTML = '<tr class="text-muted"><td colspan="6" class="text-center py-3">Nenhum pedido encontrado.</td></tr>';
                if (buscaStatus) buscaStatus.textContent = 'Nenhum resultado.';
                return;
            }
            tbodyPedidos.innerHTML = j.pedidos.map(function(p) {
                const aviso = p.alteracao_pendente ? ' <span class="badge bg-warning text-dark">Alteração pendente</span>' : '';
                return '<tr data-venda-id="' + p.id + '">' +
                    '<td><strong>#' + p.id + '</strong></td>' +
                    '<td>' + escapeHtml(p.cliente) + '</td>' +
                    '<td>' + escapeHtml(p.bairro || '—') + '</td>' +
                    '<td>' + escapeHtml(p.status) + aviso + '</td>' +
                    '<td class="text-end">R$ ' + p.frete + '</td>' +
                    '<td class="text-end"><button type="button" class="btn btn-sm btn-success btn-adicionar-pedido" data-id="' + p.id + '"><i class="bi bi-plus"></i> Incluir</button></td>' +
                    '</tr>';
            }).join('');
            if (buscaStatus) {
                buscaStatus.textContent = j.pedidos.length + ' pedido(s) encontrado(s)' +
                    (filtroClienteId ? ' deste cliente' : '') + '.';
            }
        } catch (e) {
            tbodyPedidos.innerHTML = '<tr class="text-danger"><td colspan="6" class="text-center py-3">Falha na busca.</td></tr>';
        } finally {
            if (btnBuscar) btnBuscar.disabled = false;
        }
    }

    document.getElementById('lista-entregas').addEventListener('click', function(ev) {
        const btn = ev.target.closest('.btn-agregar-cliente');
        if (!btn || !modalAdicionar) return;
        const clienteId = btn.getAttribute('data-cliente-id');
        const clienteNome = btn.getAttribute('data-cliente-nome') || '';
        if (inpBusca) inpBusca.value = '';
        const modalTitle = document.getElementById('modal-adicionar-pedido-label');
        if (modalTitle) modalTitle.textContent = 'Agregar pedido — ' + clienteNome;
        const modal = bootstrap.Modal.getOrCreateInstance(modalAdicionar);
        modal.show();
        setTimeout(function() { buscarPedidosDisponiveis(clienteId); }, 150);
    });

    function escapeHtml(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    async function adicionarPedidoNaRota(vendaId, confirmarAlteracoes) {
        if (!adicionarUrl) return;
        const body = new FormData();
        body.append('_token', csrf);
        body.append('ids[]', vendaId);
        if (confirmarAlteracoes) body.append('confirmar_alteracoes', '1');

        const res = await fetch(adicionarUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body
        });
        return res.json().catch(function() { return {}; }).then(function(j) {
            return { res: res, j: j };
        });
    }

    if (btnBuscar) {
        btnBuscar.addEventListener('click', buscarPedidosDisponiveis);
    }
    if (inpBusca) {
        inpBusca.addEventListener('keydown', function(ev) {
            if (ev.key === 'Enter') {
                ev.preventDefault();
                buscarPedidosDisponiveis();
            }
        });
    }
    if (modalAdicionar) {
        modalAdicionar.addEventListener('shown.bs.modal', function() {
            if (inpBusca) inpBusca.focus();
        });
        modalAdicionar.addEventListener('hidden.bs.modal', function() {
            filtroClienteId = null;
            if (inpBusca) inpBusca.value = '';
            const modalTitle = document.getElementById('modal-adicionar-pedido-label');
            if (modalTitle) modalTitle.textContent = 'Adicionar pedido à rota';
            if (tbodyPedidos) {
                tbodyPedidos.innerHTML = '<tr class="text-muted"><td colspan="6" class="text-center py-4">Digite acima e clique em Buscar</td></tr>';
            }
            if (buscaStatus) buscaStatus.classList.add('d-none');
        });
    }
    if (tbodyPedidos) {
        tbodyPedidos.addEventListener('click', async function(ev) {
            const btn = ev.target.closest('.btn-adicionar-pedido');
            if (!btn) return;
            const vendaId = btn.getAttribute('data-id');
            if (!vendaId) return;

            btn.disabled = true;
            let confirmar = false;

            for (;;) {
                const { res, j } = await adicionarPedidoNaRota(vendaId, confirmar);
                if (j.needs_confirmation && !confirmar) {
                    if (!confirm(j.message || 'Este pedido tem alteração pendente. Incluir mesmo assim?')) {
                        btn.disabled = false;
                        return;
                    }
                    confirmar = true;
                    continue;
                }
                if (!res.ok || !j.ok) {
                    showFlash(j.message || 'Erro ao adicionar pedido.', 'danger');
                    btn.disabled = false;
                    return;
                }

                const lista = document.getElementById('lista-entregas');
                if (lista && j.html) {
                    const temp = document.createElement('div');
                    temp.innerHTML = j.html;
                    initNovosCards(temp);
                    while (temp.firstChild) {
                        lista.appendChild(temp.firstChild);
                    }
                }
                if (j.ordem) reordenarListaEntregas(j.ordem);
                showFlash(j.message, 'success');
                atualizarResumoRota(j.rota);

                const row = btn.closest('tr');
                if (row) row.remove();
                if (!tbodyPedidos.querySelector('tr[data-venda-id]')) {
                    tbodyPedidos.innerHTML = '<tr class="text-muted"><td colspan="6" class="text-center py-3">Nenhum outro pedido nesta busca.</td></tr>';
                }
                return;
            }
        });
    }
})();
</script>
@endsection
