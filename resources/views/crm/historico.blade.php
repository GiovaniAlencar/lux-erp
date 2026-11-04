@extends('default.layout', ['title' => 'CRM LUX · Histórico'])
@section('content')
@php
    $m = fn ($v) => 'R$ ' . __moeda($v);
    $porId = $camps->keyBy('id');
    $corStatus = ['ativo' => 'primary', 'respondido' => 'info text-dark', 'convertido' => 'success', 'encerrado' => 'secondary', 'bloqueado' => 'danger', 'cancelado' => 'light text-dark border'];
@endphp
<div class="page-content">
    @include('crm._nav', ['tituloAba' => 'Histórico', 'cfg' => \App\Services\Crm\Crm::config((int) request()->empresa_id)])

    <form class="row g-2 align-items-end mb-3" method="get">
        <div class="col-6 col-md-2"><label class="form-label small mb-0">De</label><input type="date" name="inicio" class="form-control form-control-sm" value="{{ $ini->format('Y-m-d') }}"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-0">Até</label><input type="date" name="fim" class="form-control form-control-sm" value="{{ $fim->format('Y-m-d') }}"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-0">Campanha</label>
            <select name="campanha" class="form-select form-select-sm"><option value="">Todas</option>@foreach($camps as $c)<option value="{{ $c->id }}" @selected((int) request('campanha') === (int) $c->id)>{{ $c->nome }}</option>@endforeach</select></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-0">Situação</label>
            <select name="status" class="form-select form-select-sm"><option value="">Todas</option>@foreach(\App\Services\Crm\Crm::STATUS_CICLO as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="col-8 col-md-2"><label class="form-label small mb-0">Nome ou telefone</label><input name="busca" class="form-control form-control-sm" value="{{ request('busca') }}"></div>
        <div class="col-4 col-md-2"><button class="btn btn-sm btn-primary w-100">Filtrar</button></div>
    </form>

    <div class="card mb-3"><div class="card-body">
        <div class="table-responsive"><table class="table table-sm align-middle mb-0">
            <thead><tr><th>Início</th><th>Cliente</th><th>Campanha</th><th>Por que entrou</th><th>Tentativas</th><th>Situação</th><th class="text-end">Venda</th></tr></thead>
            <tbody>
            @forelse($ciclos as $c)
                <tr>
                    <td class="small text-nowrap">{{ \Carbon\Carbon::parse($c->created_at)->format('d/m/Y') }}</td>
                    <td>{{ $c->nome }}<div class="small text-muted">{{ \App\Services\Crm\Crm::formatarTelefone($c->telefone) }}</div></td>
                    <td class="small">{{ optional($porId[$c->campanha_id] ?? null)->nome }}</td>
                    <td class="small">{{ $c->motivo }}</td>
                    <td class="small">
                        @foreach($envios[$c->id] ?? [] as $e)
                            <div title="{{ $e->texto }}">{{ $e->tentativa }}ª: {{ \App\Services\Crm\Crm::STATUS_ENVIO[$e->status] ?? $e->status }}{{ $e->enviado_em ? ' em ' . \Carbon\Carbon::parse($e->enviado_em)->format('d/m H:i') : '' }}</div>
                        @endforeach
                    </td>
                    <td><span class="badge bg-{{ $corStatus[$c->status] ?? 'secondary' }}">{{ \App\Services\Crm\Crm::STATUS_CICLO[$c->status] ?? $c->status }}</span>
                        @if($c->motivo_fim)<div class="small text-muted">{{ $c->motivo_fim }}</div>@endif
                        @if($c->atendente)<div class="small text-muted">atendente: {{ $c->atendente }}</div>@endif</td>
                    <td class="text-end small">@if($c->venda_id)<a href="{{ route('vendas.show', $c->venda_id) }}" target="_blank">#{{ $c->venda_id }}</a><br>{{ $m($c->venda_valor) }}@else — @endif</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-muted">Nenhum contato no período.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="mt-2">{{ $ciclos->links() }}</div>
    </div></div>

    <div class="card"><div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <h6 class="mb-0">Não contatar <span class="small text-muted fw-normal">({{ $bloqueios->count() }})</span></h6>
            <form method="post" action="{{ route('crm.bloquear') }}" class="d-flex gap-2">
                @csrf
                <input name="telefone" class="form-control form-control-sm" placeholder="Telefone com DDD" required>
                <input name="observacao" class="form-control form-control-sm" placeholder="Motivo (opcional)">
                <button class="btn btn-sm btn-outline-danger text-nowrap">Bloquear</button>
            </form>
        </div>
        @foreach($bloqueios as $b)
            <div class="d-flex justify-content-between border-top py-1 small">
                <span>{{ \App\Services\Crm\Crm::formatarTelefone($b->telefone) }} <span class="text-muted">· {{ $b->origem === 'cliente' ? 'pediu pelo WhatsApp' : ($b->origem === 'central' ? 'marcado na central' : 'marcado no ERP') }} · {{ \Carbon\Carbon::parse($b->created_at)->format('d/m/Y') }}{{ $b->observacao ? ' · ' . $b->observacao : '' }}</span></span>
                <form method="post" action="{{ route('crm.desbloquear', $b->id) }}" onsubmit="return confirm('Liberar este telefone para receber mensagens do CRM de novo?');">@csrf<button class="btn btn-link btn-sm p-0">liberar</button></form>
            </div>
        @endforeach
    </div></div>
</div>
@endsection
