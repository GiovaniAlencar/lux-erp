@extends('default.layout', ['title' => 'Conferir entrada fiscal'])
@section('css')
<style>
    .ef-t { font-size: .8rem; }
    .ef-t td { vertical-align: top; }
    .ef-t input.form-control { font-size: .8rem; padding: .2rem .35rem; }
    .ef-novo { font-weight: 600; }
    .ef-sub { color: #6b7280; font-size: .72rem; }
</style>
@endsection
@section('content')
@php
    $fq = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, ',', ''), '0'), ',');
    $badges = ['ean' => ['bg-success', 'cód. barras'], 'nome' => ['bg-success', 'nome igual'], 'parecido' => ['bg-info', 'nome parecido'], 'duvida' => ['bg-warning text-dark', 'confira'], 'nenhum' => ['bg-danger', 'não achado']];
@endphp
<div class="page-content">
    <div class="card border-top border-0 border-4 border-primary">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-0 text-primary">Conferir entrada da nota</h5>
                    <small class="text-muted">
                        Quantidade em <strong>unidades de venda</strong> (nota em caixa? ajuste — o custo unitário recalcula).
                        Custo = produto + frete + seguro + outras + IPI + ICMS-ST − desconto da nota. Preço de venda só muda se você preencher.
                    </small>
                </div>
                <a href="{{ route('estoque-fiscal.entrada') }}" class="btn btn-light btn-sm"><i class="bx bx-arrow-back"></i> Voltar</a>
            </div>
            @if(!empty($falhas))<div class="alert alert-warning small py-2">Não lidos: {{ implode(' | ', $falhas) }}</div>@endif

            <form method="post" action="{{ route('estoque-fiscal.entrada.confirmar') }}" onsubmit="return confirm('Lançar a(s) nota(s)?')">
                @csrf
                @foreach($notas as $ni => $n)
                @php $nf = $n['nf']; @endphp
                <div class="border rounded p-3 mb-3 ef-nota {{ $n['ja_lancada'] ? 'bg-light' : '' }}">
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <div>
                            <div class="fw-semibold">NF {{ $nf['numero'] }}/{{ $nf['serie'] }} · {{ $nf['emit_nome'] }} · {{ $nf['data'] ? \Carbon\Carbon::parse($nf['data'])->format('d/m/Y') : '' }}</div>
                            <div class="small text-muted font-monospace">{{ $nf['chave'] }}</div>
                            <div class="small">Fornecedor: {!! $n['fornecedor'] ? e($n['fornecedor']->razao_social) : '<span class="text-danger">não cadastrado (a compra não será registrada no histórico de compras)</span>' !!}</div>
                        </div>
                        <div class="text-end">
                            @if($n['ja_lancada'])
                            <span class="badge bg-danger">Já lançada — será ignorada</span>
                            @else
                            <label class="form-check-label fw-semibold">
                                <input type="checkbox" class="form-check-input ef-normal" name="notas[{{ $ni }}][estoque_normal]" value="1" checked>
                                Dar entrada também no estoque normal e recalcular o custo médio real
                            </label>
                            <div class="ef-sub">Desmarcado: só soma no estoque com nota (custo fiscal).</div>
                            @endif
                        </div>
                    </div>
                    <input type="hidden" name="notas[{{ $ni }}][chave]" value="{{ $nf['chave'] }}">
                    <input type="hidden" name="notas[{{ $ni }}][numero]" value="{{ $nf['numero'] }}">
                    <input type="hidden" name="notas[{{ $ni }}][emitente]" value="{{ $nf['emit_nome'] }}">
                    <input type="hidden" name="notas[{{ $ni }}][fornecedor_id]" value="{{ optional($n['fornecedor'])->id }}">

                    <div class="table-responsive mt-2">
                    <table class="table table-sm table-bordered align-middle mb-0 ef-t">
                        <thead class="table-light">
                            <tr>
                                <th>Lançar</th>
                                <th style="min-width:200px">Item da nota</th>
                                <th style="min-width:200px">Produto (ID)</th>
                                <th style="width:90px">Qtd (un)</th>
                                <th>Custo un. da nota</th>
                                <th>Custo médio real <span class="ef-sub">(editável)</span></th>
                                <th style="min-width:250px">Preço de venda <span class="ef-sub">(vazio = mantém)</span></th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($n['itens'] as $ii => $r)
                        @php
                            $x = $r['xml']; $b = $badges[$r['match']]; $base = "notas[$ni][itens][$ii]"; $pr = $r['produto'];
                            $dis = $n['ja_lancada'] ? 'disabled' : '';
                        @endphp
                        <tr class="ef-row"
                            data-custo-total="{{ $x['custo_total'] }}"
                            data-estoque="{{ $r['estoque'] }}"
                            data-custo="{{ $pr ? (float) $pr->valor_compra : 0 }}"
                            data-preco="{{ $pr ? (float) $pr->valor_venda : 0 }}"
                            data-preco2="{{ $pr && $pr->preco_2 !== null ? (float) $pr->preco_2 : '' }}"
                            data-preco3="{{ $pr && $pr->preco_3 !== null ? (float) $pr->preco_3 : '' }}">
                            <td class="text-center"><input type="checkbox" class="form-check-input" name="{{ $base }}[aplicar]" value="1" {{ $r['produto_id'] && !$n['ja_lancada'] ? 'checked' : '' }} {{ $dis }}></td>
                            <td>
                                {{ $x['nome'] }}
                                <div class="ef-sub">cód {{ $x['cProd'] }}{{ $x['ean'] ? ' · EAN ' . $x['ean'] : '' }} · {{ $fq($x['qtd']) }} {{ $x['unidade'] }} · total R$ {{ __moeda($x['custo_total']) }}</div>
                                <input type="hidden" name="{{ $base }}[xml_nome]" value="{{ $x['nome'] }}">
                                <input type="hidden" name="{{ $base }}[custo_total]" value="{{ $x['custo_total'] }}">
                            </td>
                            <td>
                                <div class="input-group input-group-sm">
                                    <input type="number" class="form-control ef-pid" name="{{ $base }}[produto_id]" value="{{ $r['produto_id'] }}" style="max-width:90px" {{ $dis }}>
                                    <span class="input-group-text"><span class="badge {{ $b[0] }}">{{ $b[1] }}</span></span>
                                </div>
                                <div class="small ef-pnome">{{ optional($pr)->nome }}</div>
                                @if(!$r['produto_id'] && count($r['candidatos']))
                                <div class="ef-sub">Parecidos: @foreach($r['candidatos'] as $c)#{{ $c['id'] }} {{ \Illuminate\Support\Str::limit($c['nome'], 30) }} ({{ $c['pct'] }}%)@if(!$loop->last); @endif @endforeach</div>
                                @endif
                            </td>
                            <td><input class="form-control ef-q" name="{{ $base }}[quantidade]" value="{{ $fq($x['qtd']) }}" {{ $dis }}></td>
                            <td class="ef-cu"></td>
                            <td class="ef-cm" style="min-width:170px">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">R$</span>
                                    <input class="form-control ef-cr" name="{{ $base }}[novo_custo_real]" {{ $dis }} title="Calculado automaticamente — pode alterar">
                                </div>
                                <div class="ef-sub ef-cm-info"></div>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <input class="form-control ef-p1" name="{{ $base }}[novo_preco]" placeholder="Normal" {{ $dis }}>
                                    <input class="form-control ef-p2" name="{{ $base }}[novo_preco_2]" placeholder="Atac. 1" {{ $dis }}>
                                    <input class="form-control ef-p3" name="{{ $base }}[novo_preco_3]" placeholder="Atac. 2" {{ $dis }}>
                                </div>
                                <div class="ef-sub ef-margem mt-1"></div>
                            </td>
                        </tr>
                        @endforeach
                        </tbody>
                    </table>
                    </div>
                </div>
                @endforeach
                <button class="btn btn-success" type="submit"><i class="bx bx-check"></i> Lançar</button>
            </form>
        </div>
    </div>
</div>
@endsection
@section('js')
<script>
(function () {
    var urlInfo = "{{ url('estoque-fiscal/produto-info') }}/";
    function num(v) { v = String(v == null ? '' : v).trim(); if (v.indexOf(',') >= 0) v = v.replace(/\./g, '').replace(',', '.'); var n = parseFloat(v); return isNaN(n) ? 0 : n; }
    function m(n) { return 'R$ ' + n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function pct(c, p) { return c > 0 ? ((p - c) / c * 100) : 0; }
    function fp(n) { return n.toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + '%'; }

    function calc(tr) {
        var nota = tr.closest('.ef-nota');
        var normal = nota.querySelector('.ef-normal');
        var comNormal = normal ? normal.checked : false;
        var q = num(tr.querySelector('.ef-q').value);
        var total = parseFloat(tr.dataset.custoTotal || '0');
        var cu = q > 0 ? total / q : 0;
        tr.querySelector('.ef-cu').innerHTML = q > 0 ? m(cu) : '—';

        var est = Math.max(0, parseFloat(tr.dataset.estoque || '0'));
        var custo = parseFloat(tr.dataset.custo || '0');
        var novo = custo;
        if (comNormal && q > 0) novo = (est > 0 && custo > 0) ? (est * custo + q * cu) / (est + q) : cu;
        var cr = tr.querySelector('.ef-cr');
        if (cr.dataset.manual !== '1') cr.value = novo.toFixed(2).replace('.', ',');
        else novo = num(cr.value);
        tr.querySelector('.ef-cm-info').innerHTML = 'atual ' + m(custo) +
            (cr.dataset.manual === '1' ? ' · <b>alterado por você</b> <a href="#" class="ef-cr-reset">↺ automático</a>'
                : (comNormal ? ' · média com ' + est.toLocaleString('pt-BR') + ' un em estoque' : ' · sem entrada no estoque normal'));

        var p1 = num(tr.querySelector('.ef-p1').value) || parseFloat(tr.dataset.preco || '0');
        var p2raw = tr.querySelector('.ef-p2').value, p3raw = tr.querySelector('.ef-p3').value;
        var p2 = p2raw ? num(p2raw) : (tr.dataset.preco2 ? parseFloat(tr.dataset.preco2) : null);
        var p3 = p3raw ? num(p3raw) : (tr.dataset.preco3 ? parseFloat(tr.dataset.preco3) : null);
        var txt = 'Margem com o custo ' + (comNormal ? 'novo' : 'atual') + ': normal ' + m(p1) + ' (' + fp(pct(novo, p1)) + ')';
        if (p2 !== null) txt += ' · atac.1 ' + m(p2) + ' (' + fp(pct(novo, p2)) + ')';
        if (p3 !== null) txt += ' · atac.2 ' + m(p3) + ' (' + fp(pct(novo, p3)) + ')';
        tr.querySelector('.ef-margem').textContent = txt;
    }

    document.querySelectorAll('.ef-row').forEach(function (tr) {
        calc(tr);
        tr.addEventListener('input', function (e) {
            if (e.target.classList.contains('ef-cr')) e.target.dataset.manual = '1';
            calc(tr);
        });
        tr.addEventListener('click', function (e) {
            if (!e.target.classList.contains('ef-cr-reset')) return;
            e.preventDefault();
            tr.querySelector('.ef-cr').dataset.manual = '';
            calc(tr);
        });
        var pid = tr.querySelector('.ef-pid');
        pid.addEventListener('change', function () {
            if (!pid.value) return;
            fetch(urlInfo + encodeURIComponent(pid.value), { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (j) {
                    var nome = tr.querySelector('.ef-pnome');
                    if (!j || !j.ok) { nome.textContent = 'ID não encontrado'; return; }
                    nome.textContent = j.nome;
                    tr.dataset.estoque = j.estoque; tr.dataset.custo = j.custo; tr.dataset.preco = j.preco;
                    tr.dataset.preco2 = j.preco_2 === null ? '' : j.preco_2; tr.dataset.preco3 = j.preco_3 === null ? '' : j.preco_3;
                    var chk = tr.querySelector('input[type=checkbox]'); if (chk) chk.checked = true;
                    calc(tr);
                });
        });
    });
    document.querySelectorAll('.ef-normal').forEach(function (c) {
        c.addEventListener('change', function () { c.closest('.ef-nota').querySelectorAll('.ef-row').forEach(calc); });
    });
})();
</script>
@endsection
