@extends('default.layout', ['title' => 'CRM LUX'])
@section('content')
@php $m = fn ($v) => 'R$ ' . __moeda($v); $porId = $camps->keyBy('id'); @endphp
<div class="page-content">
    @include('crm._nav', ['tituloAba' => 'Visão geral'])

    <form class="row g-2 align-items-end mb-3" method="get">
        <div class="col-6 col-md-2"><label class="form-label small mb-0">De</label><input type="date" name="inicio" class="form-control form-control-sm" value="{{ $ini->format('Y-m-d') }}"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-0">Até</label><input type="date" name="fim" class="form-control form-control-sm" value="{{ $fim->format('Y-m-d') }}"></div>
        <div class="col-8 col-md-3"><label class="form-label small mb-0">Campanha</label>
            <select name="campanha" class="form-select form-select-sm"><option value="">Todas</option>@foreach($camps as $c)<option value="{{ $c->id }}" @selected($campId === (int) $c->id)>{{ $c->nome }}</option>@endforeach</select></div>
        <div class="col-4 col-md-2"><button class="btn btn-sm btn-primary w-100">Filtrar</button></div>
    </form>

    @php
        $cards = [
            ['Elegíveis agora', $n['elegiveis'], 'podem entrar na fila hoje', route('crm.fila', ['aba' => 'elegiveis'])],
            ['Aguardando aprovação', $n['aguardando'], 'confira e aprove', route('crm.fila')],
            ['Aprovados na fila', $n['na_fila'], 'aguardando o envio', route('crm.fila', ['aba' => 'aprovados'])],
            ['Mensagens enviadas', $n['enviados'], 'no período', null],
            ['Respostas', $n['respostas'], 'no período', null],
            ['Aguardando 2ª tentativa', $n['segunda'], 'sem resposta ainda', null],
            ['Atendimentos assumidos', $n['assumidos'], 'respostas com atendente', null],
            ['Vendas pelo CRM', $n['convertidos'], $m($n['faturamento']), null],
        ];
    @endphp
    <div class="row g-3 mb-3">
        @foreach($cards as [$t, $v, $s, $link])
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
            <div class="small text-muted text-uppercase">{{ $t }}</div>
            <div class="fs-3 fw-bold">@if($link)<a href="{{ $link }}" class="text-decoration-none">{{ $v }}</a>@else{{ $v }}@endif</div>
            <div class="small text-muted">{{ $s }}</div>
        </div></div></div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-lg-7"><div class="card h-100"><div class="card-body">
            <h6>Por campanha <span class="small text-muted fw-normal">(ciclos iniciados no período)</span></h6>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>Campanha</th><th class="text-end">Iniciados</th><th class="text-end">Responderam</th><th class="text-end">Compraram (CRM)</th><th class="text-end">Faturamento</th></tr></thead>
                <tbody>
                @foreach($camps as $c)
                    @php $g = $porCampanha[$c->id] ?? collect(); $tot = $g->sum('n'); $resp = $g->whereIn('status', ['respondido', 'convertido'])->sum('n'); $conv = $g->where('status', 'convertido'); @endphp
                    <tr>
                        <td>{{ $c->nome }} @if(!$c->ativa)<span class="badge bg-light text-muted border">desligada</span>@endif
                            <div class="small text-muted">{{ $c->chave === 'interessado' ? 'vem da central (fase 3)' : $c->dias_min . ($c->dias_max ? '–' . $c->dias_max : '+') . ' dias sem comprar' }}</div></td>
                        <td class="text-end">{{ $tot }}</td>
                        <td class="text-end">{{ $resp }} @if($tot)<span class="small text-muted">({{ round($resp / $tot * 100) }}%)</span>@endif</td>
                        <td class="text-end">{{ $conv->sum('n') }}</td>
                        <td class="text-end">{{ $m($conv->sum('v')) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        </div></div></div>
        <div class="col-lg-5"><div class="card h-100"><div class="card-body">
            <h6>Clientes que ficaram de fora hoje</h6>
            <p class="small text-muted">Estão na faixa de alguma campanha, mas uma regra impede o contato.</p>
            @forelse($foraPorMotivo as $mot => $q)
                <div class="d-flex justify-content-between border-top py-1 small"><span>{{ ucfirst($mot) }}</span><b>{{ $q }}</b></div>
            @empty
                <p class="small text-muted mb-0">Ninguém.</p>
            @endforelse
            <a href="{{ route('crm.fila', ['aba' => 'fora']) }}" class="small d-inline-block mt-2">Ver a lista</a>
        </div></div></div>
    </div>
</div>
@endsection
