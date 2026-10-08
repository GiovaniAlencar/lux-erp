@extends('default.layout', ['title' => 'Rotas de entrega'])
@section('content')
<div class="page-content">
    <div class="card">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h6 class="mb-0 text-uppercase">Rotas de entrega</h6>
                <a href="{{ route('vendas.index') }}" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-cart"></i> Ir para vendas
                </a>
            </div>

            <form method="get" action="{{ route('rotas-entrega.index') }}" class="mb-3">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3 col-lg-2">
                        <label class="form-label small mb-1" for="start_date">Data inicial</label>
                        <input type="date" name="start_date" id="start_date" class="form-control form-control-sm"
                            value="{{ $start_date ?? '' }}">
                    </div>
                    <div class="col-md-3 col-lg-2">
                        <label class="form-label small mb-1" for="end_date">Data final</label>
                        <input type="date" name="end_date" id="end_date" class="form-control form-control-sm"
                            value="{{ $end_date ?? '' }}">
                    </div>
                    <div class="col-md-auto d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="bi bi-search"></i> Filtrar
                        </button>
                        <a href="{{ route('rotas-entrega.index') }}" class="btn btn-sm btn-outline-secondary">
                            Limpar
                        </a>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Data</th>
                            <th>Motoboy</th>
                            <th>Status</th>
                            <th>Entregas</th>
                            <th>Pago motoboy</th>
                            <th>Total frete</th>
                            <th>Criado por</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rotas as $rota)
                        <tr>
                            <td><strong>{{ $rota->id }}</strong></td>
                            <td class="text-nowrap">{{ __data_pt($rota->created_at, 1) }}</td>
                            <td>{{ $rota->motoboy_nome ?: '—' }}</td>
                            <td>
                                <span class="badge bg-{{ $rota->status === 'em_rota' ? 'primary' : ($rota->status === 'finalizada' ? 'success' : ($rota->status === 'cancelada' ? 'secondary' : 'warning')) }}">
                                    {{ $rota->labelStatus() }}
                                </span>
                            </td>
                            <td>{{ $rota->itens->count() }}</td>
                            <td>
                                @if($rota->motoboy_pago)
                                <span class="badge bg-success">Pago</span>
                                @if($rota->motoboy_pago_em)
                                <div class="small text-muted">{{ __data_pt($rota->motoboy_pago_em, 0) }}</div>
                                @endif
                                @elseif(in_array($rota->status, ['em_rota', 'finalizada'], true))
                                <span class="badge bg-warning text-dark">Pendente</span>
                                @else
                                <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-nowrap">R$ {{ __moeda($rota->totalFrete()) }}</td>
                            <td>{{ optional($rota->usuario)->nome ?? '—' }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('rotas-entrega.show', $rota->id) }}" class="btn btn-sm btn-primary">Abrir</a>
                                @if($rota->podeExcluir())
                                <form action="{{ route('rotas-entrega.destroy', $rota->id) }}" method="post" class="d-inline" onsubmit="return confirm('Excluir rota #{{ $rota->id }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Excluir</button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">Nenhuma rota encontrada{{ ($start_date || $end_date) ? ' com os filtros informados' : '' }}. Selecione vendas na lista e clique em «Gerar rota».</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($rotas->hasPages())
            <div class="mt-3">
                {!! $rotas->links() !!}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
