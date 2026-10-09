@extends('default.layout', ['title' => 'Conferir dados fiscais (XML)'])
@section('css')
<style>
    .fx-wrap { overflow-x: auto; }
    .fx-table { font-size: .78rem; }
    .fx-table th { white-space: nowrap; position: sticky; top: 0; background: #f8f9fa; z-index: 1; }
    html.dark-theme .fx-table th { background: #2a2a2a; }
    .fx-table input.form-control, .fx-table select.form-select { font-size: .78rem; padding: .2rem .35rem; min-width: 64px; }
    .fx-table td { vertical-align: top; }
    .fx-xml { color: #6b7280; font-size: .72rem; }
    tr.fx-off { opacity: .45; }
    .fx-diff { background: #fef9c3 !important; }
</style>
@endsection
@section('content')
@php $csosns = collect(\App\Models\Produto::listaCSTCSOSN())->only(\App\Support\ProdutoFiscal::CSOSN_SIMPLES)->keys(); @endphp
<div class="page-content">
    <div class="card border-top border-0 border-4 border-primary">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-0 text-primary">Conferir dados fiscais</h5>
                    <small class="text-muted">{{ count($linhas) }} item(ns) de {{ count($notas) }} nota(s). Campos em amarelo mudam o que está no cadastro.</small>
                </div>
                <a href="{{ route('produtos-fiscal-xml.index') }}" class="btn btn-light btn-sm"><i class="bx bx-arrow-back"></i> Voltar</a>
            </div>

            <div class="d-flex flex-wrap gap-2 mb-3 small">
                @foreach($notas as $n)
                <span class="border rounded px-2 py-1">
                    NF {{ $n['numero'] }}/{{ $n['serie'] }} · {{ $n['emitente'] }} · {{ $n['itens'] }} itens
                    @if($n['propria'])<span class="badge bg-primary">emitida pela LUX</span>@else<span class="badge bg-secondary">fornecedor</span>@endif
                </span>
                @endforeach
            </div>
            @if(!empty($falhas))
            <div class="alert alert-warning small py-2">Não lidos: {{ implode(' | ', $falhas) }}</div>
            @endif
            @if($cnpjLux === '')
            <div class="alert alert-warning small py-2">O CNPJ do emitente não está na configuração fiscal; todas as notas foram tratadas como de fornecedor.</div>
            @endif

            <form method="post" action="{{ route('produtos-fiscal-xml.aplicar') }}" id="fx-form">
                @csrf
                <div class="d-flex flex-wrap gap-3 align-items-center mb-2 small">
                    <label class="form-check-label"><input type="checkbox" class="form-check-input" id="fx-todos" checked> Marcar/desmarcar todos</label>
                    <span class="text-muted">Só são gravadas as linhas marcadas em "Gravar" e com produto definido.</span>
                </div>
                <div class="fx-wrap">
                <table class="table table-sm table-bordered fx-table align-middle">
                    <thead>
                        <tr>
                            <th>Gravar</th>
                            <th style="min-width:230px">Item da nota</th>
                            <th style="min-width:230px">Produto no sistema (ID)</th>
                            <th>Fiscal</th>
                            <th>NCM</th>
                            <th>CEST</th>
                            <th>Origem</th>
                            <th>CSOSN</th>
                            <th>CFOP est.</th>
                            <th>CFOP inter.</th>
                            <th>PIS</th>
                            <th>COFINS</th>
                            <th>EAN</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($linhas as $i => $l)
                        @php
                            $x = $l['xml']; $pr = $l['proposta']; $at = $l['atual'];
                            $diff = function ($campo) use ($pr, $at) {
                                if (!$at) return '';
                                return (string) ($at->{$campo} ?? '') !== (string) $pr[$campo] ? 'fx-diff' : '';
                            };
                            $badge = ['ean' => ['bg-success', 'cód. barras'], 'nome' => ['bg-success', 'nome igual'], 'parecido' => ['bg-info', 'nome parecido'], 'duvida' => ['bg-warning text-dark', 'confira'], 'nenhum' => ['bg-danger', 'não achado']][$l['match']];
                        @endphp
                        <tr class="fx-row {{ $l['produto_id'] ? '' : 'fx-off' }}">
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input fx-aplicar" name="rows[{{ $i }}][aplicar]" value="1" {{ $l['produto_id'] ? 'checked' : '' }}>
                                <input type="hidden" name="rows[{{ $i }}][xml_nome]" value="{{ $x['nome'] }}">
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $x['nome'] }}</div>
                                <div class="fx-xml">NF {{ $l['nota'] }} · cód {{ $x['cProd'] }}</div>
                                <div class="fx-xml">XML: NCM {{ $x['ncm'] ?: '—' }} · CFOP {{ $x['cfop'] ?: '—' }} · {{ $x['csosn'] ? 'CSOSN ' . $x['csosn'] : 'CST ' . ($x['cst'] ?: '—') }} · orig {{ $x['orig'] !== '' ? $x['orig'] : '—' }}</div>
                            </td>
                            <td>
                                <div class="input-group input-group-sm mb-1">
                                    <input type="number" class="form-control fx-pid" name="rows[{{ $i }}][produto_id]" value="{{ $l['produto_id'] }}" placeholder="ID" style="max-width:90px">
                                    <span class="input-group-text"><span class="badge {{ $badge[0] }}">{{ $badge[1] }}</span></span>
                                </div>
                                <div class="small fx-pnome">{{ $at ? $at->nome : '' }}</div>
                                @if(count($l['candidatos']) > 1 || !$l['produto_id'])
                                <select class="form-select form-select-sm mt-1 fx-cand">
                                    <option value="">Outros parecidos…</option>
                                    @foreach($l['candidatos'] as $c)
                                    <option value="{{ $c['id'] }}">#{{ $c['id'] }} · {{ \Illuminate\Support\Str::limit($c['nome'], 45) }} ({{ $c['pct'] }}%)</option>
                                    @endforeach
                                </select>
                                @endif
                            </td>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input" name="rows[{{ $i }}][marcar_fiscal]" value="1" {{ $at && $at->fiscal ? 'checked' : '' }} title="Marcar produto como fiscal">
                            </td>
                            <td><input class="form-control {{ $diff('NCM') }}" name="rows[{{ $i }}][NCM]" value="{{ $pr['NCM'] }}" maxlength="10"></td>
                            <td><input class="form-control {{ $diff('CEST') }}" name="rows[{{ $i }}][CEST]" value="{{ $pr['CEST'] }}" maxlength="9"></td>
                            <td>
                                <select class="form-select {{ $diff('origem') }}" name="rows[{{ $i }}][origem]">
                                    @foreach(range(0, 8) as $o)<option value="{{ $o }}" @selected($pr['origem'] === (string) $o)>{{ $o }}</option>@endforeach
                                </select>
                            </td>
                            <td>
                                <select class="form-select {{ $diff('CST_CSOSN') }}" name="rows[{{ $i }}][CST_CSOSN]">
                                    @foreach($csosns as $c)<option value="{{ $c }}" @selected($pr['CST_CSOSN'] === (string) $c)>{{ $c }}</option>@endforeach
                                </select>
                            </td>
                            <td><input class="form-control {{ $diff('CFOP_saida_estadual') }}" name="rows[{{ $i }}][CFOP_saida_estadual]" value="{{ $pr['CFOP_saida_estadual'] }}" maxlength="4"></td>
                            <td><input class="form-control {{ $diff('CFOP_saida_inter_estadual') }}" name="rows[{{ $i }}][CFOP_saida_inter_estadual]" value="{{ $pr['CFOP_saida_inter_estadual'] }}" maxlength="4"></td>
                            <td><input class="form-control {{ $diff('CST_PIS') }}" name="rows[{{ $i }}][CST_PIS]" value="{{ $pr['CST_PIS'] }}" maxlength="2"></td>
                            <td><input class="form-control {{ $diff('CST_COFINS') }}" name="rows[{{ $i }}][CST_COFINS]" value="{{ $pr['CST_COFINS'] }}" maxlength="2"></td>
                            <td class="small">
                                @if($x['ean'])
                                {{ $x['ean'] }}
                                <input type="hidden" name="rows[{{ $i }}][ean]" value="{{ $x['ean'] }}">
                                <label class="d-block"><input type="checkbox" class="form-check-input" name="rows[{{ $i }}][gravar_ean]" value="1" checked> gravar se vazio</label>
                                @else — @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                </div>
                <button type="submit" class="btn btn-success"><i class="bx bx-check"></i> Gravar linhas marcadas</button>
            </form>
        </div>
    </div>
</div>
@endsection
@section('js')
<script>
(function () {
    var urlProduto = "{{ url('produtos-fiscal-xml/produto') }}/";
    function buscar(row, id) {
        var nome = row.querySelector('.fx-pnome');
        if (!id) { nome.textContent = ''; row.classList.add('fx-off'); return; }
        fetch(urlProduto + encodeURIComponent(id), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (j) {
                if (j && j.ok) { nome.textContent = j.nome; nome.classList.remove('text-danger'); row.classList.remove('fx-off'); }
                else { nome.textContent = 'ID não encontrado'; nome.classList.add('text-danger'); row.classList.add('fx-off'); }
            });
    }
    document.querySelectorAll('.fx-row').forEach(function (row) {
        var pid = row.querySelector('.fx-pid');
        var cand = row.querySelector('.fx-cand');
        var chk = row.querySelector('.fx-aplicar');
        pid.addEventListener('change', function () { buscar(row, pid.value); if (pid.value) chk.checked = true; });
        if (cand) cand.addEventListener('change', function () {
            if (!cand.value) return;
            pid.value = cand.value; buscar(row, cand.value); chk.checked = true;
        });
    });
    var todos = document.getElementById('fx-todos');
    todos.addEventListener('change', function () {
        document.querySelectorAll('.fx-row').forEach(function (row) {
            var pid = row.querySelector('.fx-pid');
            row.querySelector('.fx-aplicar').checked = todos.checked && !!pid.value;
        });
    });
})();
</script>
@endsection
