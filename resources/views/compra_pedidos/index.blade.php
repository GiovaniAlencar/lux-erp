@extends('default.layout', ['title' => 'Pedidos de compra'])
@section('content')
@php
    $corStatus = ['rascunho' => 'secondary', 'pedido' => 'primary', 'recebido' => 'success', 'cancelado' => 'dark'];
    $corPag = ['pago' => 'success', 'parcial' => 'warning text-dark', 'a_pagar' => 'danger', 'sem_itens' => 'light text-dark'];
    $rotPag = ['pago' => 'Pago', 'parcial' => 'Pago em parte', 'a_pagar' => 'A pagar', 'sem_itens' => '—'];
@endphp
<div class="page-content">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h5 class="mb-0">Pedidos de compra</h5>
            <div class="small text-muted">Do pedido (em dólar ou real) ao pagamento, frete, entrada no estoque e pendências com o fornecedor.</div>
        </div>
        <a href="{{ route('compra-pedidos.create') }}" class="btn btn-primary"><i class="bx bx-plus"></i> Novo pedido</a>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="small text-muted text-uppercase">Pedidos em aberto</div><div class="fs-4 fw-bold">{{ $cards['abertos'] }}</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="small text-muted text-uppercase">Valor em aberto (estimado)</div><div class="fs-4 fw-bold">R$ {{ __moeda($cards['estimado']) }}</div><div class="small text-muted">pago + falta pagar + fretes</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="small text-muted text-uppercase">Falta pagar (estimado)</div><div class="fs-4 fw-bold {{ $cards['a_pagar'] > 0 ? 'text-danger' : '' }}">R$ {{ __moeda($cards['a_pagar']) }}</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="small text-muted text-uppercase">Pendências com fornecedor</div><div class="fs-4 fw-bold {{ $cards['pendencias'] > 0 ? 'text-warning' : '' }}">R$ {{ __moeda($cards['pendencias']) }}</div><a class="small" href="{{ route('compra-pedidos.index', ['status' => 'pendencias']) }}">ver pendências</a></div></div></div>
    </div>

    <div class="card"><div class="card-body">
        <form class="row g-2 align-items-end mb-3" method="get">
            <div class="col-md-3">
                <label class="form-label small">Mostrar</label>
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach(['abertos' => 'Em aberto (rascunho e pedido feito)', 'pendencias' => 'Com pendência aberta', 'recebido' => 'Recebidos', 'cancelado' => 'Cancelados', 'todos' => 'Todos'] as $k => $v)
                        <option value="{{ $k }}" @selected($status === $k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small">Buscar</label>
                <input name="busca" value="{{ request('busca') }}" class="form-control form-control-sm" placeholder="Fornecedor ou observação">
            </div>
            <div class="col-md-2"><button class="btn btn-sm btn-outline-primary w-100"><i class="bx bx-search"></i> Filtrar</button></div>
        </form>

        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr><th>#</th><th>Fornecedor</th><th>Data</th><th class="text-end">Pedido</th><th class="text-end">Gasto em R$</th><th>Pagamento</th><th>Situação</th><th></th></tr></thead>
                <tbody>
                @forelse($pedidos as $p)
                    @php $r = $resumos[$p->id]; @endphp
                    <tr>
                        <td class="text-muted">{{ $p->id }}</td>
                        <td>
                            <a href="{{ route('compra-pedidos.show', $p->id) }}" class="fw-semibold">{{ $p->nomeFornecedor() }}</a>
                            <div class="small text-muted">{{ $p->itens->count() }} item(ns){{ $p->observacao ? ' · ' . \Illuminate\Support\Str::limit($p->observacao, 50) : '' }}</div>
                        </td>
                        <td class="text-nowrap small">{{ optional($p->data_pedido)->format('d/m/Y') }}@if($p->previsao_pagamento && $r['situacao_pagamento'] !== 'pago')<div class="text-muted">pagar {{ $p->previsao_pagamento->format('d/m') }}</div>@endif</td>
                        <td class="text-end text-nowrap">{{ $p->simbolo() }} {{ __moeda($r['total_moeda']) }}</td>
                        <td class="text-end text-nowrap">R$ {{ __moeda($r['gasto_brl']) }}@if($r['falta_pagar_brl'] > 0)<div class="small text-muted">+ ~R$ {{ __moeda($r['falta_pagar_brl']) }} a pagar</div>@endif</td>
                        <td><span class="badge bg-{{ $corPag[$r['situacao_pagamento']] }}">{{ $rotPag[$r['situacao_pagamento']] }}</span></td>
                        <td>
                            <span class="badge bg-{{ $corStatus[$p->status] ?? 'secondary' }}">{{ \App\Models\CompraPedido::STATUS[$p->status] ?? $p->status }}</span>
                            @if($r['pendencias_abertas'])<span class="badge bg-warning text-dark" title="Pendências abertas">{{ $r['pendencias_abertas'] }} pend. · R$ {{ __moeda($r['pendencias_brl']) }}</span>@endif
                        </td>
                        <td class="text-end"><a href="{{ route('compra-pedidos.show', $p->id) }}" class="btn btn-sm btn-outline-secondary">Abrir</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">Nenhum pedido aqui. <a href="{{ route('compra-pedidos.create') }}">Criar o primeiro</a>.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $pedidos->links() }}
    </div></div>
</div>
@endsection
