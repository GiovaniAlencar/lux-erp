@extends('default.layout', ['title' => 'Fechamento do mês'])
@section('content')
@php
    $m = fn ($v) => 'R$ ' . __moeda($v);
    $nomeMes = \Carbon\Carbon::createFromFormat('Y-m-d', $mes . '-01')->locale('pt_BR')->translatedFormat('F \d\e Y');
    $socios = $entradas['socios'] ?? [];
    $extras = $entradas['extras'] ?? [];
@endphp
<div class="page-content">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h5 class="mb-0">Fechamento de {{ $nomeMes }}
                @if($fechado)<span class="badge bg-success align-middle">Fechado em {{ \Carbon\Carbon::parse($reg->fechado_em)->format('d/m/Y H:i') }}</span>@else<span class="badge bg-warning text-dark align-middle">Aberto · números ao vivo</span>@endif
            </h5>
            <div class="small text-muted">{{ $r['periodo'][0] }} a {{ $r['periodo'][1] }} · calculado em {{ $r['calculado_em'] }}</div>
        </div>
        <form class="d-flex gap-2 align-items-center" method="get">
            <input type="month" name="mes" value="{{ $mes }}" class="form-control form-control-sm" onchange="this.form.submit()">
            <button type="button" class="btn btn-sm btn-outline-secondary text-nowrap" id="btn-copiar"><i class="bx bx-copy"></i> Copiar texto</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()" title="Imprimir"><i class="bx bx-printer"></i></button>
        </form>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="small text-muted text-uppercase">Vendas</div><div class="fs-4 fw-bold">{{ $m($r['faturamento']) }}</div><div class="small text-muted">custo dos produtos {{ $m($r['cmv']) }}</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="small text-muted text-uppercase">Lucro bruto</div><div class="fs-4 fw-bold">{{ $m($r['lucro_bruto']) }}</div><div class="small text-muted">margem {{ number_format($r['margem'], 1, ',', '') }}%</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="small text-muted text-uppercase">Despesas</div><div class="fs-4 fw-bold text-danger">{{ $m($r['despesas']) }}</div><div class="small text-muted">{{ count($r['despesas_grupos']) }} categoria(s)</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card h-100 border-success"><div class="card-body"><div class="small text-muted text-uppercase">Líquido do mês</div><div class="fs-4 fw-bold {{ $r['liquido'] < 0 ? 'text-danger' : 'text-success' }}">{{ $m($r['liquido']) }}</div><div class="small text-muted">lucro bruto − despesas</div></div></div></div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card mb-3"><div class="card-body">
                <h6>Despesas do mês</h6>
                <p class="small text-muted">Contas a pagar marcadas como <b>despesa</b> (pelo vencimento) e lançamentos avulsos de despesa no extrato.</p>
                @forelse($r['despesas_grupos'] as $k => $g)
                    <div class="border-top py-2">
                        <a class="d-flex justify-content-between text-decoration-none text-body" data-bs-toggle="collapse" href="#dg{{ $k }}">
                            <span class="fw-semibold">{{ $g['categoria'] }} <span class="text-muted small fw-normal">({{ count($g['itens']) }})</span></span>
                            <span class="fw-semibold">{{ $m($g['total']) }}</span>
                        </a>
                        <div class="collapse" id="dg{{ $k }}">
                            <table class="table table-sm small mb-0 mt-1">
                                @foreach($g['itens'] as $i)
                                    <tr><td class="text-muted text-nowrap" style="width:80px">{{ $i['data'] }}</td><td>{{ $i['descricao'] }} @if(!$i['pago'])<span class="badge bg-light text-dark border">a pagar</span>@endif</td><td class="text-end text-nowrap">{{ $m($i['valor']) }}</td></tr>
                                @endforeach
                            </table>
                        </div>
                    </div>
                @empty
                    <p class="text-muted small mb-0">Nenhuma despesa no mês. Lance em Contas a pagar (marcando "despesa") ou como saída avulsa com categoria de despesa.</p>
                @endforelse
                <div class="d-flex justify-content-between border-top pt-2 fw-bold"><span>Total de despesas</span><span class="text-danger">{{ $m($r['despesas']) }}</span></div>
            </div></div>

            <div class="card mb-3"><div class="card-body">
                <h6>Posição da loja em {{ $r['periodo'][1] }}</h6>
                <table class="table table-sm align-middle mb-0">
                    <tr><td>Saldo nas contas <div class="small text-muted">@foreach($r['contas'] as $c){{ $c['nome'] }}: {{ $m($c['saldo']) }}@if(!$loop->last) · @endif @endforeach</div></td><td class="text-end fw-semibold text-nowrap">{{ $m($r['saldo_final']) }}</td></tr>
                    <tr><td>Custo do estoque <div class="small text-muted">quantidade × custo atual de cada produto</div></td><td class="text-end fw-semibold text-nowrap">{{ $m($r['estoque']) }}</td></tr>
                    <tr><td><a data-bs-toggle="collapse" href="#rec">Contas a receber</a> <span class="small text-muted">({{ count($r['receber']) }})</span>
                        <div class="collapse small" id="rec">@foreach($r['receber'] as $x)<div class="d-flex justify-content-between"><span>{{ $x['nome'] }}</span><span>{{ $m($x['valor']) }}</span></div>@endforeach</div></td><td class="text-end fw-semibold text-nowrap">{{ $m($r['a_receber']) }}</td></tr>
                    @if($r['extras_receber'])<tr><td>Extras a receber (informados)</td><td class="text-end fw-semibold">{{ $m($r['extras_receber']) }}</td></tr>@endif
                    <tr><td><a data-bs-toggle="collapse" href="#ped">Pedidos de compra a caminho</a> <span class="small text-muted">(já pagos)</span>
                        <div class="collapse small" id="ped">@foreach($r['pedidos'] as $x)<div class="d-flex justify-content-between"><span>{{ $x['nome'] }}</span><span>{{ $m($x['pago']) }}{{ $x['falta_pagar'] > 0 ? ' · falta pagar ~' . $m($x['falta_pagar']) : '' }}</span></div>@endforeach</div></td><td class="text-end fw-semibold text-nowrap">{{ $m($r['a_caminho']) }}</td></tr>
                    <tr><td><a data-bs-toggle="collapse" href="#pf">Pendências com fornecedor</a>
                        <div class="collapse small" id="pf">@foreach($r['pend_fornecedor'] as $x)<div class="d-flex justify-content-between"><span>{{ $x['nome'] }}</span><span>{{ $m($x['valor']) }}</span></div>@endforeach</div></td><td class="text-end fw-semibold text-nowrap">{{ $m($r['pend_total']) }}</td></tr>
                    <tr class="table-light"><td><a data-bs-toggle="collapse" href="#pag">A pagar</a> <div class="small text-muted">contas a pagar em aberto {{ $m($r['a_pagar_contas']) }} + sócios {{ $m($r['socios_a_pagar']) }}{{ $r['extras_pagar'] ? ' + extras ' . $m($r['extras_pagar']) : '' }}</div>
                        <div class="collapse small" id="pag">@foreach($r['pagar'] as $x)<div class="d-flex justify-content-between"><span>{{ $x['vence'] }} {{ $x['nome'] }}</span><span>{{ $m($x['valor']) }}</span></div>@endforeach</div></td><td class="text-end fw-semibold text-danger text-nowrap">− {{ $m($r['a_pagar']) }}</td></tr>
                    <tr class="table-success"><td class="fw-bold">Total da loja</td><td class="text-end fs-5 fw-bold text-nowrap">{{ $m($r['total_loja']) }}</td></tr>
                </table>
                <div class="small text-muted mt-2">Saldo de abertura do mês: {{ $m($r['saldo_abertura']) }}.</div>
            </div></div>
        </div>

        <div class="col-lg-5">
            <form method="post" action="{{ route('fin.fechamento.salvar') }}" class="card mb-3" id="form-fech">
                @csrf <input type="hidden" name="mes" value="{{ $mes }}">
                <div class="card-body">
                    <h6>Divisão entre sócios</h6>
                    <div class="d-flex justify-content-between small mb-2"><span>Líquido do mês</span><b>{{ $m($r['liquido']) }}</b></div>
                    <label class="form-label small mb-1">Reinvestimento (fica na loja antes de dividir)</label>
                    <input name="reinvestimento" inputmode="decimal" class="form-control form-control-sm mb-3" value="{{ $entradas['reinvestimento'] ? __moeda($entradas['reinvestimento']) : '' }}" placeholder="0,00" @disabled($fechado)>

                    <div id="socios">
                    @foreach($socios as $si => $s)
                        @php $calc = collect($r['socios'])->firstWhere('nome', $s['nome']); @endphp
                        <div class="border rounded p-2 mb-2 socio" data-i="{{ $si }}">
                            <div class="row g-2 align-items-center">
                                <div class="col-7"><input name="socios[{{ $si }}][nome]" class="form-control form-control-sm fw-semibold" value="{{ $s['nome'] }}" @disabled($fechado)></div>
                                <div class="col-5"><div class="input-group input-group-sm"><input name="socios[{{ $si }}][pct]" inputmode="decimal" class="form-control" value="{{ rtrim(rtrim(number_format((float) $s['pct'], 2, ',', ''), '0'), ',') }}" @disabled($fechado)><span class="input-group-text">%</span></div></div>
                            </div>
                            <div class="small text-muted mt-1">Parte: <b>{{ $m($calc['parte'] ?? 0) }}</b> · retiradas {{ $m($calc['retiradas_total'] ?? 0) }} · <b class="{{ ($calc['saldo'] ?? 0) < 0 ? 'text-danger' : 'text-success' }}">fica {{ $m($calc['saldo'] ?? 0) }}</b></div>
                            <div class="retiradas mt-1">
                                @foreach($s['retiradas'] ?? [] as $ri => $x)
                                    <div class="row g-1 mb-1"><div class="col-7"><input name="socios[{{ $si }}][retiradas][{{ $ri }}][descricao]" class="form-control form-control-sm" value="{{ $x['descricao'] }}" placeholder="retirada / desconto" @disabled($fechado)></div><div class="col-5"><input name="socios[{{ $si }}][retiradas][{{ $ri }}][valor]" inputmode="decimal" class="form-control form-control-sm" value="{{ __moeda($x['valor']) }}" @disabled($fechado)></div></div>
                                @endforeach
                            </div>
                            @unless($fechado)<button type="button" class="btn btn-link btn-sm p-0 add-ret">+ retirada</button>@endunless
                        </div>
                    @endforeach
                    </div>
                    @unless($fechado)<button type="button" class="btn btn-sm btn-outline-secondary" id="add-socio">+ sócio</button>@endunless
                    @if(abs($r['socios_pct'] - 100) > 0.01 && count($r['socios']))<div class="small text-warning mt-2">As porcentagens somam {{ rtrim(rtrim(number_format($r['socios_pct'], 2, ',', ''), '0'), ',') }}% (não 100%).</div>@endif

                    <hr>
                    <h6>Valores extras <span class="small text-muted fw-normal">(o que não está no sistema)</span></h6>
                    <div id="extras">
                        @foreach($extras as $xi => $x)
                            <div class="row g-1 mb-1"><div class="col-4"><select name="extras[{{ $xi }}][tipo]" class="form-select form-select-sm" @disabled($fechado)><option value="receber" @selected($x['tipo'] === 'receber')>a receber</option><option value="pagar" @selected($x['tipo'] === 'pagar')>a pagar</option></select></div><div class="col-5"><input name="extras[{{ $xi }}][descricao]" class="form-control form-control-sm" value="{{ $x['descricao'] }}" @disabled($fechado)></div><div class="col-3"><input name="extras[{{ $xi }}][valor]" inputmode="decimal" class="form-control form-control-sm" value="{{ __moeda($x['valor']) }}" @disabled($fechado)></div></div>
                        @endforeach
                    </div>
                    @unless($fechado)<button type="button" class="btn btn-link btn-sm p-0" id="add-extra">+ valor extra</button>@endunless
                </div>
                <div class="card-footer d-flex flex-wrap gap-2 justify-content-end">
                    @if($fechado)
                        <button type="submit" form="form-reabrir" class="btn btn-outline-secondary btn-sm">Reabrir mês</button>
                    @else
                        <button name="acao" value="salvar" class="btn btn-light btn-sm"><i class="bx bx-refresh"></i> Salvar e recalcular</button>
                        <button name="acao" value="fechar" class="btn btn-success btn-sm" onclick="return confirm('Fechar o mês? Os números ficam guardados como estão agora.');"><i class="bx bx-lock"></i> Fechar o mês</button>
                    @endif
                </div>
            </form>
            <form id="form-reabrir" method="post" action="{{ route('fin.fechamento.reabrir') }}">@csrf<input type="hidden" name="mes" value="{{ $mes }}"></form>

            @if($historico->isNotEmpty())
            <div class="card"><div class="card-body small">
                <h6>Meses</h6>
                @foreach($historico as $h)
                    <a href="{{ route('fin.fechamento', ['mes' => $h->mes]) }}" class="d-flex justify-content-between text-decoration-none {{ $h->mes === $mes ? 'fw-bold' : '' }}">
                        <span>{{ \Carbon\Carbon::createFromFormat('Y-m-d', $h->mes . '-01')->locale('pt_BR')->translatedFormat('M/Y') }}</span>
                        <span class="{{ $h->status === 'fechado' ? 'text-success' : 'text-warning' }}">{{ $h->status === 'fechado' ? 'fechado' : 'aberto' }}</span>
                    </a>
                @endforeach
            </div></div>
            @endif
        </div>
    </div>
    <textarea id="texto-relatorio" class="d-none">{{ $texto }}</textarea>
</div>
@endsection

@section('js')
<script>
(function () {
    document.getElementById('btn-copiar').addEventListener('click', function () {
        const t = document.getElementById('texto-relatorio').value;
        navigator.clipboard.writeText(t).then(() => { this.innerHTML = '<i class="bx bx-check"></i> Copiado'; });
    });
    const socios = document.getElementById('socios');
    let ns = socios.querySelectorAll('.socio').length;
    socios.addEventListener('click', (e) => {
        const b = e.target.closest('.add-ret');
        if (!b) return;
        const box = b.closest('.socio'), i = box.dataset.i, lista = box.querySelector('.retiradas'), k = lista.children.length + Date.now() % 1000;
        lista.insertAdjacentHTML('beforeend', '<div class="row g-1 mb-1"><div class="col-7"><input name="socios[' + i + '][retiradas][' + k + '][descricao]" class="form-control form-control-sm" placeholder="retirada / desconto"></div><div class="col-5"><input name="socios[' + i + '][retiradas][' + k + '][valor]" inputmode="decimal" class="form-control form-control-sm" placeholder="0,00"></div></div>');
    });
    const addS = document.getElementById('add-socio');
    if (addS) addS.addEventListener('click', () => {
        const i = ns++;
        socios.insertAdjacentHTML('beforeend', '<div class="border rounded p-2 mb-2 socio" data-i="' + i + '"><div class="row g-2"><div class="col-7"><input name="socios[' + i + '][nome]" class="form-control form-control-sm" placeholder="Nome"></div><div class="col-5"><div class="input-group input-group-sm"><input name="socios[' + i + '][pct]" class="form-control" placeholder="0"><span class="input-group-text">%</span></div></div></div><div class="retiradas mt-1"></div><button type="button" class="btn btn-link btn-sm p-0 add-ret">+ retirada</button></div>');
    });
    const addE = document.getElementById('add-extra');
    if (addE) addE.addEventListener('click', () => {
        const k = Date.now();
        document.getElementById('extras').insertAdjacentHTML('beforeend', '<div class="row g-1 mb-1"><div class="col-4"><select name="extras[' + k + '][tipo]" class="form-select form-select-sm"><option value="receber">a receber</option><option value="pagar">a pagar</option></select></div><div class="col-5"><input name="extras[' + k + '][descricao]" class="form-control form-control-sm" placeholder="Ex.: Gordinho cred"></div><div class="col-3"><input name="extras[' + k + '][valor]" inputmode="decimal" class="form-control form-control-sm" placeholder="0,00"></div></div>');
    });
})();
</script>
@endsection
