@extends('default.layout', ['title' => 'Pedido de compra #' . $pedido->id])
@section('content')
@php
    $usd = $pedido->moeda === 'USD';
    $sim = $pedido->simbolo();
    $aberto = in_array($pedido->status, ['rascunho', 'pedido'], true);
    $corStatus = ['rascunho' => 'secondary', 'pedido' => 'primary', 'recebido' => 'success', 'cancelado' => 'dark'];
    $n4 = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, ',', '.'), '0'), ',');
    $qt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
    $pendAbertas = $pedido->pendencias->where('status', 'aberta');
@endphp
<div class="page-content">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h5 class="mb-1">Pedido de compra #{{ $pedido->id }} · {{ $pedido->nomeFornecedor() }}
                <span class="badge bg-{{ $corStatus[$pedido->status] ?? 'secondary' }} align-middle">{{ \App\Models\CompraPedido::STATUS[$pedido->status] ?? $pedido->status }}</span></h5>
            <div class="small text-muted">
                {{ $usd ? 'Em dólar' : 'Em real' }} · pedido em {{ optional($pedido->data_pedido)->format('d/m/Y') }}
                @if($pedido->previsao_pagamento) · pagar até {{ $pedido->previsao_pagamento->format('d/m/Y') }} @endif
                @if($pedido->recebido_em) · recebido em {{ $pedido->recebido_em->format('d/m/Y') }}{{ $pedido->entrada_estoque === false ? ' (estoque pelo XML)' : '' }} @endif
            </div>
            @if($pedido->observacao)<div class="small mt-1">{{ $pedido->observacao }}</div>@endif
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('compra-pedidos.index') }}" class="btn btn-light"><i class="bx bx-arrow-back"></i> Pedidos</a>
            <div class="btn-group">
                <button type="button" class="btn btn-outline-success btn-copiar" data-texto="{{ $pedido->textoFornecedor(true) }}" title="Copia a lista para colar no WhatsApp/e-mail do fornecedor"><i class="bx bx-copy"></i> Copiar pedido</button>
                <button type="button" class="btn btn-outline-success btn-copiar" data-texto="{{ $pedido->textoFornecedor(false) }}" title="Só produtos e quantidades">sem preços</button>
            </div>
            @if($aberto)
                <a href="{{ route('compra-pedidos.edit', $pedido->id) }}" class="btn btn-light"><i class="bx bx-edit"></i> Editar itens</a>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modal-pagamento"><i class="bx bx-dollar-circle"></i> Lançar pagamento</button>
                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modal-frete"><i class="bx bx-package"></i> Frete / transportadora</button>
                <a href="{{ route('compra-pedidos.entrada.form', $pedido->id) }}" class="btn btn-primary"><i class="bx bx-log-in-circle"></i> Custos e preços / dar entrada</a>
            @elseif($pedido->status === 'recebido')
                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modal-frete"><i class="bx bx-package"></i> Custo extra</button>
            @endif
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
            <div class="small text-muted text-uppercase">Pedido</div>
            <div class="fs-4 fw-bold">{{ $sim }} {{ __moeda($resumo['total_moeda']) }}</div>
            <div class="small text-muted">{{ $pedido->itens->count() }} item(ns) · {{ $qt($pedido->itens->sum('quantidade')) }} un.</div>
            @if(($resumo['agente_moeda'] ?? 0) > 0)<div class="small text-muted">fornecedor {{ $sim }} {{ __moeda($resumo['total_fornecedor']) }} + agente {{ $qt($pedido->pct_agente) }}% ({{ $sim }} {{ __moeda($resumo['agente_moeda']) }})</div>@endif
        </div></div></div>
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
            <div class="small text-muted text-uppercase">Pago ao fornecedor</div>
            <div class="fs-4 fw-bold {{ $resumo['falta_pagar_moeda'] > 0 ? '' : 'text-success' }}">{{ $sim }} {{ __moeda($resumo['pago_moeda']) }}</div>
            <div class="small {{ $resumo['falta_pagar_moeda'] > 0 ? 'text-danger' : 'text-muted' }}">{{ $resumo['falta_pagar_moeda'] > 0 ? 'falta ' . $sim . ' ' . __moeda($resumo['falta_pagar_moeda']) . ($usd && $resumo['taxa'] ? ' (~R$ ' . __moeda($resumo['falta_pagar_brl']) . ')' : '') : 'pago por completo' }}</div>
        </div></div></div>
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
            <div class="small text-muted text-uppercase">Gasto em reais</div>
            <div class="fs-4 fw-bold">R$ {{ __moeda($resumo['gasto_brl']) }}</div>
            <div class="small text-muted">pagamentos R$ {{ __moeda($resumo['pago_brl']) }} + fretes R$ {{ __moeda($resumo['fretes_brl']) }}</div>
        </div></div></div>
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
            <div class="small text-muted text-uppercase">{{ $usd ? 'Dólar saiu a' : 'Custo total previsto' }}</div>
            @if($usd)
                <div class="fs-4 fw-bold">{{ $resumo['taxa'] ? 'R$ ' . number_format($resumo['taxa'], 4, ',', '') : '—' }}</div>
                <div class="small text-muted">{{ $resumo['pago_moeda'] > 0 ? 'cotação + % pagos de verdade' : 'pela cotação prevista' }} · total ~R$ {{ __moeda($resumo['estimado_brl']) }}</div>
            @else
                <div class="fs-4 fw-bold">R$ {{ __moeda($resumo['estimado_brl']) }}</div>
                <div class="small text-muted">itens + fretes</div>
            @endif
        </div></div></div>
    </div>

    <div class="card mb-3"><div class="card-body">
        <h6>Itens e custo final</h6>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Produto</th><th class="text-end">Qtd</th><th class="text-end">Preço ({{ $sim }})</th><th class="text-end">Subtotal</th><th class="text-end" title="Preço × dólar efetivo + fretes rateados">Custo unit. final (R$)</th><th class="text-end">Custo atual</th>@if($pedido->status === 'recebido')<th class="text-end">Recebido</th>@endif</tr></thead>
                <tbody>
                @foreach($pedido->itens as $i)
                    @php $cf = $pedido->status === 'recebido' && $i->custo_final !== null ? $i->custo_final : ($custos[$i->id] ?? 0); @endphp
                    <tr>
                        <td>{{ $i->nome() }} @if(!$i->produto_id)<span class="badge bg-warning text-dark">sem cadastro</span>@endif
                            @if($i->produto_id && $i->descricao)<div class="small text-muted">{{ $i->descricao }}</div>@endif</td>
                        <td class="text-end">{{ $qt($i->quantidade) }}</td>
                        <td class="text-end">{{ $n4($i->preco_moeda) }}</td>
                        <td class="text-end">{{ __moeda($i->subtotal()) }}</td>
                        <td class="text-end fw-semibold">{{ $cf ? 'R$ ' . __moeda($cf) : '—' }}</td>
                        <td class="text-end text-muted">{{ $i->produto ? 'R$ ' . __moeda($i->produto->valor_compra) : '—' }}</td>
                        @if($pedido->status === 'recebido')<td class="text-end {{ (float) $i->qtd_recebida + 0.001 < $i->quantidade ? 'text-danger fw-semibold' : '' }}">{{ $qt($i->qtd_recebida) }}</td>@endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($aberto)<div class="small text-muted mt-2">O custo final ainda pode mudar: ele usa o dólar dos pagamentos lançados e os fretes. Na entrada no estoque, o custo médio do produto é recalculado com ele (o preço de venda não muda).</div>@endif
    </div></div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card h-100"><div class="card-body">
                <h6>Pagamentos e fretes</h6>
                @if($pedido->custos->isEmpty())
                    <p class="text-muted small mb-0">Nada lançado ainda. Use <b>Lançar pagamento</b> quando pagar o fornecedor e <b>Frete / transportadora</b> para os outros custos.</p>
                @else
                <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Data</th><th>O quê</th><th class="text-end">Valor</th><th>Conta</th><th></th></tr></thead>
                    <tbody>
                    @foreach($pedido->custos as $c)
                        <tr>
                            <td class="text-nowrap small">{{ $c->data->format('d/m/Y') }}</td>
                            <td>{{ \App\Models\CompraPedidoCusto::TIPOS[$c->tipo] ?? $c->tipo }}
                                <div class="small text-muted">
                                    @if($c->tipo === 'pagamento' && $usd)US$ {{ __moeda($c->valor_moeda) }}{{ $c->cotacao ? ' × ' . $n4($c->cotacao) : '' }}{{ $c->pct_frete ? ' + ' . $qt($c->pct_frete) . '%' : '' }}@endif
                                    {{ $c->descricao }}
                                </div></td>
                            <td class="text-end fw-semibold text-nowrap">R$ {{ __moeda($c->valor_brl) }}</td>
                            <td class="small">{{ optional($c->conta)->nome ?: '—' }}</td>
                            <td class="text-end">
                                <form method="post" action="{{ route('compra-pedidos.custo.excluir', [$pedido->id, $c->id]) }}" onsubmit="return confirm('Excluir este lançamento? Se saiu de uma conta, a saída é estornada do extrato.');">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-link text-danger p-0" title="Excluir"><i class="bx bx-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                </div>
                @endif
            </div></div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100"><div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Pendências com o fornecedor</h6>
                    @if($pedido->status !== 'cancelado')<button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modal-pendencia"><i class="bx bx-plus"></i> Pendência</button>@endif
                </div>
                @if($pedido->pendencias->isEmpty())
                    <p class="text-muted small mt-2 mb-0">Nenhuma. O que faltar na entrada entra aqui sozinho.</p>
                @endif
                @foreach($pedido->pendencias as $pd)
                    <div class="border rounded p-2 mt-2 {{ $pd->status === 'aberta' ? 'border-warning' : 'opacity-75' }}">
                        <div class="d-flex justify-content-between gap-2">
                            <div><span class="badge bg-{{ $pd->status === 'aberta' ? 'warning text-dark' : 'success' }}">{{ \App\Models\CompraPedidoPendencia::TIPOS[$pd->tipo] ?? $pd->tipo }}</span> {{ $pd->descricao }}</div>
                            <div class="text-end text-nowrap small">@if($pd->valor_moeda && $usd)US$ {{ __moeda($pd->valor_moeda) }}<br>@endif @if($pd->valor_brl)<b>R$ {{ __moeda($pd->valor_brl) }}</b>@endif</div>
                        </div>
                        @if($pd->status === 'aberta')
                            <button class="btn btn-sm btn-outline-success mt-2" data-bs-toggle="modal" data-bs-target="#modal-resolver"
                                data-pend="{{ $pd->id }}" data-desc="{{ $pd->descricao }}" data-qtd="{{ $pd->quantidade }}" data-valor="{{ $pd->valor_brl }}" data-temitem="{{ $pd->item && $pd->item->produto_id ? 1 : 0 }}">Resolver</button>
                        @else
                            <div class="small text-success mt-1"><i class="bx bx-check"></i> {{ $pd->resolucao }} · {{ optional($pd->resolvido_em)->format('d/m/Y') }}</div>
                        @endif
                    </div>
                @endforeach
            </div></div>
        </div>
    </div>

    <div class="d-flex gap-2 justify-content-end mt-3">
        @if($aberto)
        <form method="post" action="{{ route('compra-pedidos.cancelar', $pedido->id) }}" onsubmit="return confirm('Cancelar este pedido?');">@csrf<button class="btn btn-sm btn-outline-secondary">Cancelar pedido</button></form>
        @endif
        @if($pedido->status === 'rascunho' && $pedido->custos->isEmpty())
        <form method="post" action="{{ route('compra-pedidos.destroy', $pedido->id) }}" onsubmit="return confirm('Excluir este rascunho?');">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Excluir rascunho</button></form>
        @endif
    </div>
</div>

{{-- Pagamento --}}
<div class="modal fade" id="modal-pagamento" tabindex="-1"><div class="modal-dialog"><form class="modal-content" method="post" action="{{ route('compra-pedidos.custo', $pedido->id) }}">
    @csrf <input type="hidden" name="tipo" value="pagamento">
    <div class="modal-header"><h5 class="modal-title">Pagamento ao fornecedor</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body row g-3">
        <div class="col-6"><label class="form-label">Data</label><input type="date" name="data" class="form-control" value="{{ date('Y-m-d') }}" required></div>
        <div class="col-6"><label class="form-label">Saiu da conta</label>
            <select name="fin_conta_id" class="form-select"><option value="">— não lançar no extrato —</option>@foreach($contas as $ct)<option value="{{ $ct->id }}">{{ $ct->nome }}</option>@endforeach</select></div>
        @if($usd)
        <div class="col-12"><label class="form-label">Quanto do pedido foi pago (US$)</label>
            <input name="valor_moeda" inputmode="decimal" class="form-control pg-calc" id="pg-usd" value="{{ $resumo['falta_pagar_moeda'] > 0 ? number_format($resumo['falta_pagar_moeda'], 2, ',', '.') : '' }}">
            <div class="form-text">Falta pagar US$ {{ __moeda($resumo['falta_pagar_moeda']) }}.</div></div>
        <div class="col-6"><label class="form-label">Cotação do dólar</label><input name="cotacao" inputmode="decimal" class="form-control pg-calc" id="pg-cot" value="{{ $pedido->cotacao_prevista ? $n4($pedido->cotacao_prevista) : '' }}" placeholder="5,30"></div>
        <div class="col-6"><label class="form-label">% do frete</label><input name="pct_frete" inputmode="decimal" class="form-control pg-calc" id="pg-pct" value="{{ $pedido->pct_frete_previsto ? $qt($pedido->pct_frete_previsto) : '' }}" placeholder="0"></div>
        @endif
        <div class="col-12"><label class="form-label">Valor que saiu (R$)</label>
            <input name="valor_brl" inputmode="decimal" class="form-control form-control-lg fw-bold" id="pg-brl" value="{{ !$usd && $resumo['falta_pagar_moeda'] > 0 ? number_format($resumo['falta_pagar_moeda'], 2, ',', '.') : '' }}">
            @if($usd)<div class="form-text">Calculado: US$ × cotação + %. Pode corrigir para o valor exato do comprovante.</div>@endif</div>
        <div class="col-12"><label class="form-label">Observação</label><input name="descricao" class="form-control" placeholder="Opcional (ex.: PIX para o agente)"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button><button class="btn btn-success">Lançar pagamento</button></div>
</form></div></div>

{{-- Frete / transportadora / outro --}}
<div class="modal fade" id="modal-frete" tabindex="-1"><div class="modal-dialog"><form class="modal-content" method="post" action="{{ route('compra-pedidos.custo', $pedido->id) }}">
    @csrf
    <div class="modal-header"><h5 class="modal-title">Frete, transportadora ou outro custo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body row g-3">
        <div class="col-6"><label class="form-label">Tipo</label>
            <select name="tipo" class="form-select"><option value="transportadora">Transportadora</option><option value="frete">Frete</option><option value="outro">Outro custo</option></select></div>
        <div class="col-6"><label class="form-label">Data</label><input type="date" name="data" class="form-control" value="{{ date('Y-m-d') }}" required></div>
        <div class="col-12"><label class="form-label">Descrição</label><input name="descricao" class="form-control" placeholder="Ex.: Sabah Transportadora, 4 volumes"></div>
        <div class="col-6"><label class="form-label">Valor (R$)</label><input name="valor_brl" inputmode="decimal" class="form-control fw-bold" required></div>
        <div class="col-6"><label class="form-label">Saiu da conta</label>
            <select name="fin_conta_id" class="form-select"><option value="">— não lançar no extrato —</option>@foreach($contas as $ct)<option value="{{ $ct->id }}">{{ $ct->nome }}</option>@endforeach</select></div>
        <div class="col-12 small text-muted">Entra no custo dos produtos, rateado pelo valor de cada item.</div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button><button class="btn btn-primary">Lançar</button></div>
</form></div></div>

{{-- Nova pendência --}}
<div class="modal fade" id="modal-pendencia" tabindex="-1"><div class="modal-dialog"><form class="modal-content" method="post" action="{{ route('compra-pedidos.pendencia', $pedido->id) }}">
    @csrf
    <div class="modal-header"><h5 class="modal-title">Nova pendência</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body row g-3">
        <div class="col-6"><label class="form-label">Tipo</label><select name="tipo" class="form-select">@foreach(\App\Models\CompraPedidoPendencia::TIPOS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
        <div class="col-6"><label class="form-label">Item (opcional)</label><select name="item_id" class="form-select"><option value="">—</option>@foreach($pedido->itens as $i)<option value="{{ $i->id }}">{{ \Illuminate\Support\Str::limit($i->nome(), 40) }}</option>@endforeach</select></div>
        <div class="col-12"><label class="form-label">Descrição</label><input name="descricao" class="form-control" required placeholder="Ex.: 2 frascos quebrados, fornecedor vai descontar no próximo"></div>
        <div class="col-4"><label class="form-label">Qtd</label><input name="quantidade" inputmode="decimal" class="form-control"></div>
        @if($usd)<div class="col-4"><label class="form-label">US$</label><input name="valor_moeda" inputmode="decimal" class="form-control"></div>@endif
        <div class="col-4"><label class="form-label">R$</label><input name="valor_brl" inputmode="decimal" class="form-control" placeholder="auto"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button><button class="btn btn-warning">Registrar</button></div>
</form></div></div>

{{-- Resolver pendência --}}
<div class="modal fade" id="modal-resolver" tabindex="-1"><div class="modal-dialog"><form class="modal-content" method="post" id="form-resolver" data-base="{{ url('/pedidos-compra/' . $pedido->id . '/pendencias') }}">
    @csrf
    <div class="modal-header"><h5 class="modal-title">Resolver pendência</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <p class="small" id="res-desc"></p>
        <div class="form-check"><input class="form-check-input res-modo" type="radio" name="modo" value="chegou" id="rm1"><label class="form-check-label" for="rm1">Chegou depois — entrar no estoque</label></div>
        <div class="ps-4 mb-2 res-box" data-modo="chegou"><input name="quantidade" inputmode="decimal" class="form-control form-control-sm" style="max-width:120px" placeholder="qtd"></div>
        <div class="form-check"><input class="form-check-input res-modo" type="radio" name="modo" value="reembolso" id="rm2"><label class="form-check-label" for="rm2">Fornecedor devolveu o dinheiro</label></div>
        <div class="ps-4 mb-2 res-box row g-2" data-modo="reembolso">
            <div class="col-5"><input name="valor" inputmode="decimal" class="form-control form-control-sm" placeholder="R$"></div>
            <div class="col-7"><select name="fin_conta_id" class="form-select form-select-sm">@foreach($contas as $ct)<option value="{{ $ct->id }}">{{ $ct->nome }}</option>@endforeach</select></div>
            <div class="col-6"><input type="date" name="data" class="form-control form-control-sm" value="{{ date('Y-m-d') }}"></div>
        </div>
        <div class="form-check"><input class="form-check-input res-modo" type="radio" name="modo" value="abatido" id="rm3" checked><label class="form-check-label" for="rm3">Abatido em outro pedido</label></div>
        <div class="form-check"><input class="form-check-input res-modo" type="radio" name="modo" value="perda" id="rm4"><label class="form-check-label" for="rm4">Perda (não vai voltar)</label></div>
        <input name="obs" class="form-control form-control-sm mt-2" placeholder="Observação (ex.: abatido no pedido #12)">
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button><button class="btn btn-success">Resolver</button></div>
</form></div></div>
@endsection

@section('js')
<script>
(function () {
    const ler = (t) => { let s = String(t || '').replace(/[^\d.,]/g, ''); if (!s) return 0; if (s.includes(',')) s = s.replace(/\./g, '').replace(',', '.'); else if (/^\d{1,3}(\.\d{3})+$/.test(s)) s = s.replace(/\./g, ''); return parseFloat(s) || 0; };
    const fmt = (v) => (Number(v) || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    // pagamento em dólar: R$ = US$ × cotação × (1 + %)
    const usd = document.getElementById('pg-usd');
    if (usd) {
        const brl = document.getElementById('pg-brl');
        let editado = false;
        brl.addEventListener('input', () => (editado = true));
        const calc = () => { if (editado) return; const v = ler(usd.value) * ler(document.getElementById('pg-cot').value) * (1 + ler(document.getElementById('pg-pct').value) / 100); brl.value = v ? fmt(v) : ''; };
        document.querySelectorAll('.pg-calc').forEach((e) => e.addEventListener('input', calc));
        calc();
    }
    // resolver pendência
    const modal = document.getElementById('modal-resolver');
    modal.addEventListener('show.bs.modal', (e) => {
        const b = e.relatedTarget, f = document.getElementById('form-resolver');
        f.action = f.dataset.base + '/' + b.dataset.pend + '/resolver';
        document.getElementById('res-desc').textContent = b.dataset.desc;
        f.querySelector('[name=quantidade]').value = b.dataset.qtd ? String(parseFloat(b.dataset.qtd)).replace('.', ',') : '';
        f.querySelector('[name=valor]').value = b.dataset.valor ? fmt(b.dataset.valor) : '';
        document.getElementById('rm1').disabled = b.dataset.temitem !== '1';
        mostrar();
    });
    function mostrar() {
        const m = (document.querySelector('.res-modo:checked') || {}).value;
        document.querySelectorAll('.res-box').forEach((x) => (x.style.display = x.dataset.modo === m ? '' : 'none'));
    }
    document.querySelectorAll('.res-modo').forEach((r) => r.addEventListener('change', mostrar));
    // copiar o pedido para o fornecedor
    document.querySelectorAll('.btn-copiar').forEach((b) => b.addEventListener('click', () => {
        const txt = b.dataset.texto;
        const ok = () => { const o = b.innerHTML; b.innerHTML = '<i class="bx bx-check"></i> Copiado'; setTimeout(() => (b.innerHTML = o), 1500); };
        if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(txt).then(ok, () => prompt('Copie o pedido:', txt));
        else { const ta = document.createElement('textarea'); ta.value = txt; document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); ok(); } catch (e) { prompt('Copie o pedido:', txt); } ta.remove(); }
    }));
})();
</script>
@endsection
