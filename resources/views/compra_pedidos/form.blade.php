@extends('default.layout', ['title' => $pedido->exists ? 'Pedido de compra #' . $pedido->id : 'Novo pedido de compra'])
@section('content')
@php
    $itens = old('itens') ? collect(old('itens'))->map(fn ($i) => (object) $i) : $pedido->itens->map(fn ($i) => (object) [
        'id' => $i->id, 'produto_id' => $i->produto_id, 'produto_nome' => optional($i->produto)->nome, 'descricao' => $i->descricao,
        'quantidade' => rtrim(rtrim(number_format($i->quantidade, 2, ',', ''), '0'), ','), 'preco' => number_format($i->preco_moeda, 2, ',', '.'),
    ]);
    if ($itens->isEmpty()) { $itens = collect([(object) ['id' => '', 'produto_id' => '', 'produto_nome' => '', 'descricao' => '', 'quantidade' => '', 'preco' => '']]); }
    $moeda = old('moeda', $pedido->moeda ?: 'USD');
    $fmt = fn ($v, $d = 2) => $v !== null && $v !== '' ? rtrim(rtrim(number_format((float) $v, $d, ',', ''), '0'), ',') : '';
@endphp
<div class="page-content">
    <form method="post" action="{{ $pedido->exists ? route('compra-pedidos.update', $pedido->id) : route('compra-pedidos.store') }}" id="form-pedido">
        @csrf
        @if($pedido->exists) @method('PUT') @endif
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h5 class="mb-0">{{ $pedido->exists ? 'Pedido de compra #' . $pedido->id : 'Novo pedido de compra' }}</h5>
                <div class="small text-muted">Salve como rascunho enquanto monta; confirme quando fizer o pedido ao fornecedor.</div>
            </div>
            <a href="{{ $pedido->exists ? route('compra-pedidos.show', $pedido->id) : route('compra-pedidos.index') }}" class="btn btn-light"><i class="bx bx-arrow-back"></i> Voltar</a>
        </div>

        <div class="card mb-3"><div class="card-body row g-3">
            <div class="col-md-5">
                <label class="form-label">Fornecedor</label>
                <select name="fornecedor_id" class="form-select select2" id="sel-fornecedor">
                    <option value="">— não cadastrado (digite o nome ao lado) —</option>
                    @foreach($fornecedores as $f)
                        <option value="{{ $f->id }}" @selected((int) old('fornecedor_id', $pedido->fornecedor_id) === $f->id)>{{ $f->nome_fantasia ?: $f->razao_social }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3" id="box-forn-nome">
                <label class="form-label">Nome do fornecedor</label>
                <input name="fornecedor_nome" class="form-control" value="{{ old('fornecedor_nome', $pedido->fornecedor_nome) }}" placeholder="Ex.: Isabelle La Belle">
            </div>
            <div class="col-md-4">
                <label class="form-label d-block">Moeda do pedido</label>
                <div class="btn-group w-100" role="group">
                    <input type="radio" class="btn-check" name="moeda" id="moeda-usd" value="USD" @checked($moeda === 'USD')>
                    <label class="btn btn-outline-primary" for="moeda-usd">Dólar (US$)</label>
                    <input type="radio" class="btn-check" name="moeda" id="moeda-brl" value="BRL" @checked($moeda === 'BRL')>
                    <label class="btn btn-outline-primary" for="moeda-brl">Real (R$)</label>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Data do pedido</label>
                <input type="date" name="data_pedido" class="form-control" value="{{ old('data_pedido', optional($pedido->data_pedido)->format('Y-m-d') ?: date('Y-m-d')) }}">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Previsão de pagamento</label>
                <input type="date" name="previsao_pagamento" class="form-control" value="{{ old('previsao_pagamento', optional($pedido->previsao_pagamento)->format('Y-m-d')) }}">
            </div>
            @if(\Illuminate\Support\Facades\Schema::hasColumn('compra_pedidos', 'pct_agente'))
            <div class="col-6 col-md-2">
                <label class="form-label">% do agente</label>
                <div class="input-group">
                    <input name="pct_agente" inputmode="decimal" class="form-control" value="{{ old('pct_agente', $fmt($pedido->pct_agente)) }}" placeholder="opcional">
                    <span class="input-group-text">%</span>
                </div>
                <div class="form-text">Sobre o valor do fornecedor, antes do dólar.</div>
            </div>
            @endif
            <div class="col-6 col-md-2 so-usd">
                <label class="form-label">Cotação prevista (R$)</label>
                <input name="cotacao_prevista" inputmode="decimal" class="form-control" value="{{ old('cotacao_prevista', $fmt($pedido->cotacao_prevista ?? optional($ultimo)->cotacao_prevista, 4)) }}" placeholder="5,30">
                <div class="form-text">Só para estimar; a real entra no pagamento.</div>
            </div>
            <div class="col-6 col-md-2 so-usd">
                <label class="form-label">% do frete do agente</label>
                <input name="pct_frete_previsto" inputmode="decimal" class="form-control" value="{{ old('pct_frete_previsto', $fmt($pedido->pct_frete_previsto ?? optional($ultimo)->pct_frete_previsto)) }}" placeholder="Ex.: 3">
            </div>
        </div></div>

        <div class="card mb-3"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Itens</h6>
                <div class="d-flex gap-2">
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-success btn-copiar" data-precos="1" title="Copia a lista para colar no WhatsApp/e-mail do fornecedor"><i class="bx bx-copy"></i> Copiar pedido</button>
                        <button type="button" class="btn btn-outline-success btn-copiar" data-precos="0" title="Só produtos e quantidades">sem preços</button>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-item"><i class="bx bx-plus"></i> Item</button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle" id="tab-itens">
                    <thead><tr><th style="min-width:280px">Produto</th><th style="width:110px">Qtd</th><th style="width:150px">Preço unit. (<span class="sim">US$</span>)</th><th style="width:140px" class="text-end">Subtotal</th><th style="width:40px"></th></tr></thead>
                    <tbody>
                    @foreach($itens as $k => $i)
                        <tr class="linha-item">
                            <td>
                                <input type="hidden" name="itens[{{ $k }}][id]" value="{{ $i->id ?? '' }}">
                                <select name="itens[{{ $k }}][produto_id]" class="form-select form-select-sm sel-produto">
                                    @if(!empty($i->produto_id))<option value="{{ $i->produto_id }}" selected>{{ $i->produto_nome ?? ('#' . $i->produto_id) }}</option>@endif
                                </select>
                                <input name="itens[{{ $k }}][descricao]" class="form-control form-control-sm mt-1" value="{{ $i->descricao ?? '' }}" placeholder="ou descreva (item sem cadastro / nome no fornecedor)">
                            </td>
                            <td><input name="itens[{{ $k }}][quantidade]" inputmode="decimal" class="form-control form-control-sm inp-qtd" value="{{ $i->quantidade ?? '' }}"></td>
                            <td><input name="itens[{{ $k }}][preco]" inputmode="decimal" class="form-control form-control-sm inp-preco" value="{{ $i->preco ?? '' }}"></td>
                            <td class="text-end sub fw-semibold">—</td>
                            <td><button type="button" class="btn btn-sm btn-link text-danger btn-del" title="Tirar"><i class="bx bx-trash"></i></button></td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot><tr><td colspan="3" class="text-end">Total do fornecedor</td><td class="text-end fs-5 fw-bold" id="total">—</td><td></td></tr>
                    <tr id="linha-agente" style="display:none"><td colspan="3" class="text-end small">+ agente <span id="pct-ag"></span> → total a pagar</td><td class="text-end fw-semibold" id="total-agente">—</td><td></td></tr>
                    <tr class="so-usd"><td colspan="3" class="text-end small text-muted">Estimado em reais (cotação + %)</td><td class="text-end small text-muted" id="total-brl">—</td><td></td></tr></tfoot>
                </table>
            </div>
        </div></div>

        <div class="card mb-3"><div class="card-body">
            <label class="form-label">Observação</label>
            <textarea name="observacao" class="form-control" rows="2">{{ old('observacao', $pedido->observacao) }}</textarea>
        </div></div>

        <div class="d-flex flex-wrap gap-2 justify-content-end">
            @if(!$pedido->exists || $pedido->status === 'rascunho')
                <button name="acao" value="rascunho" class="btn btn-light"><i class="bx bx-save"></i> Salvar rascunho</button>
                <button name="acao" value="confirmar" class="btn btn-primary"><i class="bx bx-check"></i> Salvar e confirmar pedido</button>
            @else
                <button name="acao" value="salvar" class="btn btn-primary"><i class="bx bx-save"></i> Salvar</button>
            @endif
        </div>
    </form>
</div>
@endsection

@section('js')
<script>
(function () {
    const URL_PROD = @json(route('compra-pedidos.produtos'));
    const tbody = document.querySelector('#tab-itens tbody');
    const ler = (t) => { let s = String(t || '').replace(/[^\d.,]/g, ''); if (!s) return 0; if (s.includes(',')) s = s.replace(/\./g, '').replace(',', '.'); else if (/^\d{1,3}(\.\d{3})+$/.test(s)) s = s.replace(/\./g, ''); return parseFloat(s) || 0; };
    const fmt = (v) => (Number(v) || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const moeda = () => document.querySelector('[name=moeda]:checked').value;

    function select2Produto(sel) {
        if (!window.jQuery || !jQuery.fn.select2) return;
        jQuery(sel).select2({
            theme: 'bootstrap4', width: '100%', allowClear: true, placeholder: 'Buscar produto cadastrado...',
            ajax: { url: URL_PROD, dataType: 'json', delay: 250, data: (p) => ({ q: p.term }), processResults: (d) => ({ results: d }) },
            templateResult: (p) => p.id ? jQuery('<div>' + jQuery('<span>').text(p.text).html() + (p.estoque !== undefined ? '<div class="small text-muted">estoque ' + p.estoque + ' · custo R$ ' + fmt(p.custo) + '</div>' : '') + '</div>') : p.text,
        });
    }
    function totais() {
        let t = 0;
        tbody.querySelectorAll('tr').forEach((tr) => {
            const s = ler(tr.querySelector('.inp-qtd').value) * ler(tr.querySelector('.inp-preco').value);
            tr.querySelector('.sub').textContent = s ? fmt(s) : '—';
            t += s;
        });
        const sim = moeda() === 'USD' ? 'US$' : 'R$';
        document.querySelectorAll('.sim').forEach((e) => (e.textContent = sim));
        document.getElementById('total').textContent = sim + ' ' + fmt(t);
        const agEl = document.querySelector('[name=pct_agente]'), ag = agEl ? ler(agEl.value) : 0, tAg = t * (1 + ag / 100);
        document.getElementById('linha-agente').style.display = ag > 0 ? '' : 'none';
        document.getElementById('pct-ag').textContent = ag > 0 ? fmt(ag).replace(/,00$/, '') + '%' : '';
        document.getElementById('total-agente').textContent = sim + ' ' + fmt(tAg);
        const cot = ler(document.querySelector('[name=cotacao_prevista]').value), pct = ler(document.querySelector('[name=pct_frete_previsto]').value);
        document.getElementById('total-brl').textContent = cot ? 'R$ ' + fmt(tAg * cot * (1 + pct / 100)) : 'informe a cotação';
        document.querySelectorAll('.so-usd').forEach((e) => (e.style.display = moeda() === 'USD' ? '' : 'none'));
        const forn = document.getElementById('sel-fornecedor').value;
        document.getElementById('box-forn-nome').style.display = forn ? 'none' : '';
    }
    let n = tbody.querySelectorAll('tr').length;
    document.getElementById('btn-add-item').addEventListener('click', () => {
        const k = n++;
        const tr = document.createElement('tr');
        tr.className = 'linha-item';
        tr.innerHTML = '<td><input type="hidden" name="itens[' + k + '][id]" value=""><select name="itens[' + k + '][produto_id]" class="form-select form-select-sm sel-produto"></select>' +
            '<input name="itens[' + k + '][descricao]" class="form-control form-control-sm mt-1" placeholder="ou descreva (item sem cadastro / nome no fornecedor)"></td>' +
            '<td><input name="itens[' + k + '][quantidade]" inputmode="decimal" class="form-control form-control-sm inp-qtd"></td>' +
            '<td><input name="itens[' + k + '][preco]" inputmode="decimal" class="form-control form-control-sm inp-preco"></td>' +
            '<td class="text-end sub fw-semibold">—</td><td><button type="button" class="btn btn-sm btn-link text-danger btn-del"><i class="bx bx-trash"></i></button></td>';
        tbody.appendChild(tr);
        select2Produto(tr.querySelector('.sel-produto'));
        totais();
    });
    tbody.addEventListener('click', (e) => {
        const b = e.target.closest('.btn-del');
        if (b && tbody.querySelectorAll('tr').length > 1) { b.closest('tr').remove(); totais(); }
    });
    document.getElementById('form-pedido').addEventListener('input', totais);
    document.getElementById('form-pedido').addEventListener('change', totais);
    if (window.jQuery) jQuery('#sel-fornecedor').on('change', totais);
    tbody.querySelectorAll('.sel-produto').forEach(select2Produto);
    totais();

    // copiar o pedido para mandar ao fornecedor (preços do fornecedor, sem o % do agente)
    function textoPedido(comPrecos) {
        const sim = moeda() === 'USD' ? 'US$' : 'R$';
        const data = (document.querySelector('[name=data_pedido]').value || '').split('-').reverse().join('/');
        const qn = (v) => String(+v.toFixed(2)).replace('.', ',');
        const l = ['*Pedido de compra*' + (data ? ' — ' + data : ''), ''];
        let q = 0, t = 0, k = 0;
        tbody.querySelectorAll('tr').forEach((tr) => {
            const qtd = ler(tr.querySelector('.inp-qtd').value), preco = ler(tr.querySelector('.inp-preco').value);
            const sel = tr.querySelector('.sel-produto'), desc = tr.querySelector('[name$="[descricao]"]').value.trim();
            const nome = desc || (sel && sel.selectedOptions[0] ? sel.selectedOptions[0].textContent.trim() : '');
            if (!qtd || !nome) return;
            q += qtd; t += qtd * preco;
            l.push((++k) + ') ' + qn(qtd) + ' × ' + nome + (comPrecos ? ' — ' + sim + ' ' + fmt(preco) + ' = ' + sim + ' ' + fmt(qtd * preco) : ''));
        });
        l.push('', 'Total: ' + qn(q) + ' un.' + (comPrecos ? ' · ' + sim + ' ' + fmt(t) : ''));
        return k ? l.join('\n') : '';
    }
    document.querySelectorAll('.btn-copiar').forEach((b) => b.addEventListener('click', () => {
        const txt = textoPedido(b.dataset.precos === '1');
        if (!txt) { alert('Coloque os itens com quantidade primeiro.'); return; }
        const ok = () => { const o = b.innerHTML; b.innerHTML = '<i class="bx bx-check"></i> Copiado'; setTimeout(() => (b.innerHTML = o), 1500); };
        if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(txt).then(ok, () => prompt('Copie o pedido:', txt));
        else { const ta = document.createElement('textarea'); ta.value = txt; document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); ok(); } catch (e) { prompt('Copie o pedido:', txt); } ta.remove(); }
    }));
})();
</script>
@endsection
