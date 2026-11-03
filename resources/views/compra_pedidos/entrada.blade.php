@extends('default.layout', ['title' => 'Conferir chegada · pedido #' . $pedido->id])
@section('css')
<style>
    .pe-t { font-size: .82rem; }
    .pe-t td { vertical-align: top; }
    .pe-t input.form-control { font-size: .82rem; padding: .2rem .35rem; }
    .pe-sub { color: #6b7280; font-size: .72rem; }
</style>
@endsection
@section('content')
@php
    $usd = $pedido->moeda === 'USD';
    $sim = $pedido->simbolo();
    $qt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',');
    $fator = $pedido->fatorAgente();
@endphp
<div class="page-content">
    <form method="post" action="{{ route('compra-pedidos.entrada', $pedido->id) }}" id="form-entrada">
        @csrf
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
            <div>
                <h5 class="mb-1">Custos e preços · pedido #{{ $pedido->id }} · {{ $pedido->nomeFornecedor() }}</h5>
                <div class="small text-muted">
                    Custo unitário = preço{{ $fator > 1 ? ' + ' . $qt($pedido->pct_agente) . '% do agente' : '' }}{{ $usd ? ' × dólar' : '' }} + fretes rateados pelo valor do item.
                    @if($usd)Dólar saiu a <b>{{ $resumo['taxa'] ? 'R$ ' . number_format($resumo['taxa'], 4, ',', '') : '—' }}</b> ({{ $resumo['pago_moeda'] > 0 ? 'pagamentos lançados' : 'cotação prevista' }}).@endif
                    Fretes R$ {{ __moeda($resumo['fretes_brl']) }}. Preço de venda só muda se você preencher.
                </div>
            </div>
            <a href="{{ route('compra-pedidos.show', $pedido->id) }}" class="btn btn-light"><i class="bx bx-arrow-back"></i> Voltar ao pedido</a>
        </div>

        @if($resumo['falta_pagar_moeda'] > 0)
        <div class="alert alert-warning small py-2">Ainda falta pagar {{ $sim }} {{ __moeda($resumo['falta_pagar_moeda']) }}. Dá para conferir e dar entrada assim mesmo: o custo usa {{ $resumo['pago_moeda'] > 0 ? 'o dólar dos pagamentos já feitos' : 'a cotação prevista' }}. Se ainda vai pagar ou lançar frete, deixe para dar entrada depois — esta tela só calcula.</div>
        @endif

        <div class="card mb-3"><div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-2">
                    <label class="form-label">Sugerir preço por</label>
                    <select class="form-select pe-modo">
                        <option value="markup" title="preço = custo × (1 + %)">Markup</option>
                        <option value="margem" title="preço = custo ÷ (1 − %)">Margem</option>
                    </select>
                </div>
                <div class="col-4 col-md-2"><label class="form-label">Normal</label><div class="input-group"><input class="form-control pe-mk1" inputmode="decimal" placeholder="ex.: 60"><span class="input-group-text">%</span></div></div>
                <div class="col-4 col-md-2"><label class="form-label">Atacado 1</label><div class="input-group"><input class="form-control pe-mk2" inputmode="decimal" placeholder="ex.: 50"><span class="input-group-text">%</span></div></div>
                <div class="col-4 col-md-2"><label class="form-label">Atacado 2</label><div class="input-group"><input class="form-control pe-mk3" inputmode="decimal" placeholder="ex.: 45"><span class="input-group-text">%</span></div></div>
                <div class="col-12 col-md-4 d-flex gap-2">
                    <button type="button" class="btn btn-primary pe-aplicar"><i class="bx bx-calculator"></i> Aplicar a todos</button>
                    <button type="button" class="btn btn-light pe-limpar">Limpar preços</button>
                </div>
                <div class="col-12 pe-sub">Calcula sobre o <b>custo médio novo</b> de cada item e arredonda para cima, em reais inteiros. Os preços continuam editáveis um a um.</div>
            </div>
        </div></div>

        <div class="card mb-3"><div class="card-body">
            <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0 pe-t">
                <thead class="table-light"><tr>
                    <th style="min-width:190px">Produto</th>
                    <th class="text-end">Pedido</th>
                    <th style="width:90px">Chegou</th>
                    <th style="min-width:115px">Custo un. do pedido</th>
                    <th style="min-width:145px">Custo médio novo <span class="pe-sub">(editável)</span></th>
                    <th style="min-width:230px">Preço de venda <span class="pe-sub">(vazio = mantém)</span></th>
                </tr></thead>
                <tbody>
                @foreach($pedido->itens as $i)
                    @php $pr = $i->produto; $cu = (float) ($custos[$i->id] ?? 0); $b = "receb[{$i->id}]"; @endphp
                    <tr class="pe-row" data-cu="{{ $cu }}"
                        data-estoque="{{ $pr ? max(0, (float) ($estoques[$pr->id] ?? 0)) : 0 }}"
                        data-custo="{{ $pr ? (float) $pr->valor_compra : 0 }}"
                        data-preco="{{ $pr ? (float) $pr->valor_venda : 0 }}"
                        data-preco2="{{ $pr && $pr->preco_2 !== null ? (float) $pr->preco_2 : '' }}"
                        data-preco3="{{ $pr && $pr->preco_3 !== null ? (float) $pr->preco_3 : '' }}">
                        <td>
                            <div class="fw-semibold pe-nome">{{ $i->nome() }}</div>
                            @if($i->produto_id && $i->descricao)<div class="pe-sub">no fornecedor: {{ $i->descricao }}</div>@endif
                            @if(!$i->produto_id)
                                <select name="{{ $b }}[produto_id]" class="form-select form-select-sm mt-1 sel-prod-entrada"><option value="">escolha o produto do cadastro…</option></select>
                                <div class="pe-sub text-danger">sem cadastro: escolha o produto para entrar no estoque</div>
                            @endif
                            <div class="pe-sub pe-info"></div>
                        </td>
                        <td class="text-end">{{ $qt($i->quantidade) }}</td>
                        <td><input name="{{ $b }}[qtd]" inputmode="decimal" class="form-control pe-q" value="{{ $qt($i->quantidade) }}"></td>
                        <td>
                            <b>R$ {{ __moeda($cu) }}</b>
                            <div class="pe-sub">{{ $sim }} {{ number_format($i->preco_moeda, 2, ',', '.') }}{{ $fator > 1 ? ' + agente' : '' }}{{ $usd ? ' × ' . number_format($resumo['taxa'], 4, ',', '') : '' }}</div>
                        </td>
                        <td>
                            <div class="input-group input-group-sm"><span class="input-group-text">R$</span>
                                <input class="form-control pe-cr" name="{{ $b }}[novo_custo]" title="Calculado automaticamente — pode alterar"></div>
                            <div class="pe-sub pe-cm-info"></div>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <input class="form-control pe-p1" name="{{ $b }}[novo_preco]" inputmode="decimal" placeholder="Normal">
                                <input class="form-control pe-p2" name="{{ $b }}[novo_preco_2]" inputmode="decimal" placeholder="Atac. 1">
                                <input class="form-control pe-p3" name="{{ $b }}[novo_preco_3]" inputmode="decimal" placeholder="Atac. 2">
                            </div>
                            <div class="pe-sub pe-margem mt-1"></div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
        </div></div>

        <div class="card"><div class="card-body">
            <div class="form-check">
                <input class="form-check-input" type="radio" name="modo" id="modo-estoque" value="estoque" checked>
                <label class="form-check-label" for="modo-estoque"><b>Dar entrada no estoque agora</b> com o custo médio novo</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="modo" id="modo-xml" value="xml">
                <label class="form-check-label" for="modo-xml">Só marcar como recebido — <b>vou dar entrada pelo XML da nota</b> (Estoque fiscal), para não entrar em dobro. Os preços preenchidos aqui são salvos mesmo assim.</label>
            </div>
            <div class="small text-muted mt-2">O que chegar a menos vira pendência com o fornecedor.</div>
            <div class="d-flex justify-content-end mt-2">
                <button class="btn btn-success" onclick="return confirm('Confirmar o recebimento deste pedido?');"><i class="bx bx-check"></i> Confirmar recebimento</button>
            </div>
        </div></div>
    </form>
</div>
@endsection

@section('js')
<script>
(function () {
    function num(v) { v = String(v == null ? '' : v).trim(); if (v.indexOf(',') >= 0) v = v.replace(/\./g, '').replace(',', '.'); var n = parseFloat(v); return isNaN(n) ? 0 : n; }
    function m(n) { return 'R$ ' + n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function pct(c, p) { return c > 0 ? ((p - c) / c * 100) : 0; }
    function fp(n) { return n.toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + '%'; }
    function comEstoque() { return document.getElementById('modo-estoque').checked; }

    function calc(tr) {
        var q = num(tr.querySelector('.pe-q').value), cu = parseFloat(tr.dataset.cu || '0');
        var est = Math.max(0, parseFloat(tr.dataset.estoque || '0')), custo = parseFloat(tr.dataset.custo || '0');
        var novo = custo;
        if (comEstoque() && q > 0 && cu > 0) novo = (est > 0 && custo > 0) ? (est * custo + q * cu) / (est + q) : cu;
        var cr = tr.querySelector('.pe-cr');
        if (cr.dataset.manual !== '1') cr.value = novo ? novo.toFixed(2).replace('.', ',') : '';
        else novo = num(cr.value);
        tr.querySelector('.pe-cm-info').innerHTML = 'atual ' + m(custo) +
            (cr.dataset.manual === '1' ? ' · <b>alterado por você</b> <a href="#" class="pe-reset">↺ automático</a>'
                : (comEstoque() ? ' · média com ' + est.toLocaleString('pt-BR') + ' un em estoque' : ' · sem entrada no estoque'));
        tr.querySelector('.pe-info').textContent = 'estoque ' + est.toLocaleString('pt-BR') + ' un';
        var p1 = num(tr.querySelector('.pe-p1').value) || parseFloat(tr.dataset.preco || '0');
        var r2 = tr.querySelector('.pe-p2').value, r3 = tr.querySelector('.pe-p3').value;
        var p2 = r2 ? num(r2) : (tr.dataset.preco2 ? parseFloat(tr.dataset.preco2) : null);
        var p3 = r3 ? num(r3) : (tr.dataset.preco3 ? parseFloat(tr.dataset.preco3) : null);
        var txt = 'Margem com o custo novo: normal ' + m(p1) + ' (' + fp(pct(novo, p1)) + ')';
        if (p2 !== null) txt += ' · atac.1 ' + m(p2) + ' (' + fp(pct(novo, p2)) + ')';
        if (p3 !== null) txt += ' · atac.2 ' + m(p3) + ' (' + fp(pct(novo, p3)) + ')';
        tr.querySelector('.pe-margem').textContent = txt;
    }
    var rows = document.querySelectorAll('.pe-row');
    rows.forEach(function (tr) {
        calc(tr);
        tr.addEventListener('input', function (e) { if (e.target.classList.contains('pe-cr')) e.target.dataset.manual = '1'; calc(tr); });
        tr.addEventListener('click', function (e) {
            if (!e.target.classList.contains('pe-reset')) return;
            e.preventDefault(); tr.querySelector('.pe-cr').dataset.manual = ''; calc(tr);
        });
    });
    // custo calculado aqui é só para mostrar: o servidor recalcula; só vai o que você alterou à mão
    document.getElementById('form-entrada').addEventListener('submit', function () {
        rows.forEach(function (tr) { var cr = tr.querySelector('.pe-cr'); if (cr.dataset.manual !== '1') cr.value = ''; });
    });
    document.querySelectorAll('[name=modo]').forEach(function (r) { r.addEventListener('change', function () { rows.forEach(calc); }); });

    function sugerir(custo, pctTxt, modo) {
        if (String(pctTxt).trim() === '' || !(custo > 0)) return null;
        var p = num(pctTxt);
        var preco = modo === 'margem' ? (p < 100 ? custo / (1 - p / 100) : null) : custo * (1 + p / 100);
        return preco ? Math.ceil(preco - 0.0001) : null;
    }
    try {
        var s = JSON.parse(localStorage.getItem('lux_precificar') || 'null');
        if (s) { document.querySelector('.pe-modo').value = s.modo || 'markup'; ['.pe-mk1', '.pe-mk2', '.pe-mk3'].forEach(function (c, i) { document.querySelector(c).value = (s.pcts || [])[i] || ''; }); }
    } catch (er) {}
    document.querySelector('.pe-aplicar').addEventListener('click', function () {
        var modo = document.querySelector('.pe-modo').value;
        var pcts = ['.pe-mk1', '.pe-mk2', '.pe-mk3'].map(function (c) { return document.querySelector(c).value; });
        if (pcts.every(function (v) { return String(v).trim() === ''; })) { alert('Informe ao menos um percentual.'); return; }
        try { localStorage.setItem('lux_precificar', JSON.stringify({ modo: modo, pcts: pcts })); } catch (er) {}
        rows.forEach(function (tr) {
            var custo = num(tr.querySelector('.pe-cr').value);
            ['.pe-p1', '.pe-p2', '.pe-p3'].forEach(function (cls, i) {
                var v = sugerir(custo, pcts[i], modo);
                if (v !== null) tr.querySelector(cls).value = String(v) + ',00';
            });
            calc(tr);
        });
    });
    document.querySelector('.pe-limpar').addEventListener('click', function () {
        rows.forEach(function (tr) { ['.pe-p1', '.pe-p2', '.pe-p3'].forEach(function (c) { tr.querySelector(c).value = ''; }); calc(tr); });
    });

    if (window.jQuery && jQuery.fn.select2) {
        jQuery('.sel-prod-entrada').select2({
            theme: 'bootstrap4', width: '100%', placeholder: 'escolha o produto do cadastro…',
            ajax: { url: @json(route('compra-pedidos.produtos')), dataType: 'json', delay: 250, data: function (p) { return { q: p.term }; }, processResults: function (d) { return { results: d }; } },
        }).on('select2:select', function (e) {
            var d = e.params.data, tr = this.closest('tr');
            tr.dataset.estoque = d.estoque || 0; tr.dataset.custo = d.custo || 0; tr.dataset.preco = d.preco || 0;
            tr.dataset.preco2 = d.preco_2 === null || d.preco_2 === undefined ? '' : d.preco_2;
            tr.dataset.preco3 = d.preco_3 === null || d.preco_3 === undefined ? '' : d.preco_3;
            calc(tr);
        });
    }
})();
</script>
@endsection
