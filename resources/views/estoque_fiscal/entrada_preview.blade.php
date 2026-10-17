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
                        Custo = produto + frete + seguro + outras + IPI + ICMS-ST − desconto da nota, mais o <strong>DIFAL</strong> e os <strong>outros custos</strong> que você informar em cada nota. Preço de venda só muda se você preencher.
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
                    @if(!$n['ja_lancada'])
                    <div class="row g-2 mt-2 align-items-end">
                        <div class="col-6 col-md-2">
                            <label class="form-label">DIFAL pago (%)</label>
                            <div class="input-group">
                                <input class="form-control ef-difal" name="notas[{{ $ni }}][difal_pct]" inputmode="decimal" placeholder="0">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label">Vencimento do DIFAL</label>
                            <input type="date" class="form-control" name="notas[{{ $ni }}][difal_vencimento]" value="{{ now()->endOfMonth()->format('Y-m-d') }}" title="Com DIFAL, o ERP cria uma conta a pagar com este vencimento">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label">Outros custos desta nota</label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input class="form-control ef-outros" name="notas[{{ $ni }}][outros_custos]" inputmode="decimal" placeholder="0,00">
                            </div>
                        </div>
                        <div class="col-12 col-md-5 ef-sub ef-resumo-nota pb-2"></div>
                    </div>
                    <div class="ef-sub mt-1">DIFAL: % sobre o valor da mercadoria de cada item (sem o ICMS-ST); gera uma conta a pagar "DIFAL" desta nota. Outros custos (frete pago à parte, taxas…): divididos entre os itens lançados pelo valor de cada um.</div>
                    <div class="row g-2 mt-2 align-items-end ef-precificar">
                        <div class="col-12 col-md-2">
                            <label class="form-label">Sugerir preço por</label>
                            <select class="form-select ef-modo">
                                <option value="markup" title="preço = custo × (1 + %)">Markup</option>
                                <option value="margem" title="preço = custo ÷ (1 − %)">Margem</option>
                            </select>
                        </div>
                        <div class="col-4 col-md-2">
                            <label class="form-label">Normal</label>
                            <div class="input-group"><input class="form-control ef-mk1" inputmode="decimal" placeholder="ex.: 60"><span class="input-group-text">%</span></div>
                        </div>
                        <div class="col-4 col-md-2">
                            <label class="form-label">Atacado 1</label>
                            <div class="input-group"><input class="form-control ef-mk2" inputmode="decimal" placeholder="ex.: 50"><span class="input-group-text">%</span></div>
                        </div>
                        <div class="col-4 col-md-2">
                            <label class="form-label">Atacado 2</label>
                            <div class="input-group"><input class="form-control ef-mk3" inputmode="decimal" placeholder="ex.: 45"><span class="input-group-text">%</span></div>
                        </div>
                        <div class="col-12 col-md-4 d-flex gap-2 align-items-end">
                            <button type="button" class="btn btn-primary ef-aplicar"><i class="bx bx-calculator"></i> Aplicar a todos</button>
                            <button type="button" class="btn btn-light ef-limpar-precos" title="Apagar os preços sugeridos (mantém os atuais)">Limpar preços</button>
                        </div>
                        <div class="col-12 ef-sub">Calcula sobre o <strong>custo médio real</strong> de cada item marcado e arredonda para cima, em reais inteiros (sem centavos). Os preços continuam editáveis um a um.</div>
                    </div>
                    @endif
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
                                <th style="min-width:150px">Custo un. real</th>
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
                            data-base-difal="{{ $x['base_difal'] ?? $x['custo_total'] }}"
                            data-estoque="{{ $r['estoque'] }}"
                            data-custo="{{ $pr ? (float) $pr->valor_compra : 0 }}"
                            data-preco="{{ $pr ? (float) $pr->valor_venda : 0 }}"
                            data-preco2="{{ $pr && $pr->preco_2 !== null ? (float) $pr->preco_2 : '' }}"
                            data-preco3="{{ $pr && $pr->preco_3 !== null ? (float) $pr->preco_3 : '' }}">
                            <td class="text-center"><input type="checkbox" class="form-check-input" name="{{ $base }}[aplicar]" value="1" {{ $r['produto_id'] && !$n['ja_lancada'] ? 'checked' : '' }} {{ $dis }}></td>
                            <td>
                                {{ $x['nome'] }}
                                <div class="ef-sub">cód {{ $x['cProd'] }}{{ $x['ean'] ? ' · EAN ' . $x['ean'] : '' }} · {{ $fq($x['qtd']) }} {{ $x['unidade'] }}</div>
                                <div class="ef-sub">
                                    produto R$ {{ __moeda($x['v_prod']) }}
                                    @if(($x['v_ipi'] ?? 0) > 0) · IPI R$ {{ __moeda($x['v_ipi']) }}@endif
                                    @if(($x['v_st'] ?? 0) > 0) · ICMS-ST R$ {{ __moeda($x['v_st']) }}@endif
                                    @if(($x['v_frete_outros'] ?? 0) > 0) · frete/outras R$ {{ __moeda($x['v_frete_outros']) }}@endif
                                    @if(($x['v_desc'] ?? 0) > 0) · desconto −R$ {{ __moeda($x['v_desc']) }}@endif
                                    · <strong>total da nota R$ {{ __moeda($x['custo_total']) }}</strong>
                                </div>
                                <input type="hidden" name="{{ $base }}[xml_nome]" value="{{ $x['nome'] }}">
                                <input type="hidden" name="{{ $base }}[custo_total]" value="{{ $x['custo_total'] }}">
                                <input type="hidden" name="{{ $base }}[base_difal]" value="{{ $x['base_difal'] ?? $x['custo_total'] }}">
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

    // DIFAL (% sobre a mercadoria sem ST) e outros custos (rateados pelo valor dos itens marcados)
    function extrasDoItem(tr, nota) {
        var difalPct = nota.querySelector('.ef-difal') ? num(nota.querySelector('.ef-difal').value) : 0;
        var outros = nota.querySelector('.ef-outros') ? num(nota.querySelector('.ef-outros').value) : 0;
        var soma = 0;
        nota.querySelectorAll('.ef-row').forEach(function (r) {
            var c = r.querySelector('input[type=checkbox]');
            if (c && c.checked) soma += Math.max(0, parseFloat(r.dataset.custoTotal || '0'));
        });
        var chk = tr.querySelector('input[type=checkbox]');
        var marcado = chk && chk.checked;
        var total = parseFloat(tr.dataset.custoTotal || '0');
        return {
            difal: parseFloat(tr.dataset.baseDifal || '0') * difalPct / 100,
            outros: (marcado && soma > 0) ? outros * Math.max(0, total) / soma : 0
        };
    }
    function resumoNota(nota) {
        var el = nota.querySelector('.ef-resumo-nota');
        if (!el) return;
        var difal = 0, outros = 0, base = 0;
        nota.querySelectorAll('.ef-row').forEach(function (r) {
            var c = r.querySelector('input[type=checkbox]');
            if (!c || !c.checked) return;
            var x = extrasDoItem(r, nota);
            difal += x.difal; outros += x.outros; base += parseFloat(r.dataset.custoTotal || '0');
        });
        el.innerHTML = 'Itens marcados: ' + m(base) + (difal > 0 ? ' + DIFAL ' + m(difal) : '') + (outros > 0 ? ' + outros ' + m(outros) : '') + ' = <b>' + m(base + difal + outros) + '</b> no custo';
    }

    function calc(tr) {
        var nota = tr.closest('.ef-nota');
        var normal = nota.querySelector('.ef-normal');
        var comNormal = normal ? normal.checked : false;
        var q = num(tr.querySelector('.ef-q').value);
        var totalNota = parseFloat(tr.dataset.custoTotal || '0');
        var x = extrasDoItem(tr, nota);
        var total = totalNota + x.difal + x.outros;
        var cu = q > 0 ? total / q : 0;
        var det = '';
        if (q > 0 && (x.difal > 0 || x.outros > 0)) {
            det = '<div class="ef-sub">nota ' + m(totalNota / q) + (x.difal > 0 ? ' + DIFAL ' + m(x.difal / q) : '') + (x.outros > 0 ? ' + outros ' + m(x.outros / q) : '') + '</div>';
        }
        tr.querySelector('.ef-cu').innerHTML = q > 0 ? '<b>' + m(cu) + '</b>' + det : '—';

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
    function recalcNota(nota) { nota.querySelectorAll('.ef-row').forEach(calc); resumoNota(nota); }

    // preço sugerido a partir do custo real: markup (custo × (1+%)) ou margem (custo ÷ (1−%)), arredondado p/ cima em reais
    function sugerir(custo, pctTxt, modo) {
        if (String(pctTxt).trim() === '' || !(custo > 0)) return null;
        var p = num(pctTxt);
        var preco = modo === 'margem' ? (p < 100 ? custo / (1 - p / 100) : null) : custo * (1 + p / 100);
        return preco ? Math.ceil(preco - 0.0001) : null;
    }
    function aplicarPrecos(nota) {
        var modo = nota.querySelector('.ef-modo').value;
        var pcts = ['.ef-mk1', '.ef-mk2', '.ef-mk3'].map(function (c) { return nota.querySelector(c).value; });
        if (pcts.every(function (v) { return String(v).trim() === ''; })) { alert('Informe ao menos um percentual.'); return; }
        try { localStorage.setItem('lux_precificar', JSON.stringify({ modo: modo, pcts: pcts })); } catch (er) {}
        var n = 0;
        nota.querySelectorAll('.ef-row').forEach(function (tr) {
            var chk = tr.querySelector('input[type=checkbox]');
            if (!chk || !chk.checked) return;
            var custo = num(tr.querySelector('.ef-cr').value);
            ['.ef-p1', '.ef-p2', '.ef-p3'].forEach(function (cls, i) {
                var v = sugerir(custo, pcts[i], modo);
                if (v !== null) tr.querySelector(cls).value = String(v).replace('.', ',') + ',00';
            });
            calc(tr); n++;
        });
        if (!n) alert('Nenhum item marcado para lançar nesta nota.');
    }
    document.querySelectorAll('.ef-nota').forEach(function (nota) {
        nota.addEventListener('input', function (e) {
            if (e.target.matches('.ef-difal, .ef-outros')) {
                if (e.target.matches('.ef-difal')) { try { localStorage.setItem('lux_difal_pct', e.target.value); } catch (er) {} }
                recalcNota(nota);
            }
        });
        nota.addEventListener('change', function (e) {
            if (e.target.matches('.ef-normal, .ef-row input[type=checkbox]')) recalcNota(nota);
        });
        var btn = nota.querySelector('.ef-aplicar');
        if (btn) btn.addEventListener('click', function () { aplicarPrecos(nota); });
        var limpar = nota.querySelector('.ef-limpar-precos');
        if (limpar) limpar.addEventListener('click', function () {
            nota.querySelectorAll('.ef-p1, .ef-p2, .ef-p3').forEach(function (i) { i.value = ''; });
            nota.querySelectorAll('.ef-row').forEach(calc);
        });
        try {
            var salvo = JSON.parse(localStorage.getItem('lux_precificar') || 'null');
            if (salvo && nota.querySelector('.ef-modo')) {
                nota.querySelector('.ef-modo').value = salvo.modo || 'markup';
                ['.ef-mk1', '.ef-mk2', '.ef-mk3'].forEach(function (c, i) { nota.querySelector(c).value = (salvo.pcts || [])[i] || ''; });
            }
        } catch (er) {}
        // lembra o último % de DIFAL usado neste computador
        var d = nota.querySelector('.ef-difal');
        if (d && !d.value) { try { d.value = localStorage.getItem('lux_difal_pct') || ''; } catch (er) {} }
        recalcNota(nota);
    });
})();
</script>
@endsection
