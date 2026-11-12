@extends('default.layout',['title' => 'Promoções'])
@section('content')
<div class="page-content">
    <div class="card ">
        <div class="card-body p-4">
            <div class="page-breadcrumb d-sm-flex align-items-center mb-3">
                <div class="ms-auto">
                    <a href="{{ route('promocoes.create')}}" type="button" class="btn btn-success">
                        <i class="bx bx-plus"></i> Nova promoção
                    </a>
                    <a href="{{ route('promocoes.lote') }}" class="btn btn-primary">
                        <i class="bx bx-layer-plus"></i> Promoções em lote
                    </a>
                </div>
            </div>
            <hr>
            <div class="col">
                <h6 class="mb-0 text-uppercase mt-4">Promoções</h6>

                {!!Form::open()->fill(request()->all())
                ->get()
                !!}
                <div class="row">
                    <div class="col-md-4">
                        {!!Form::text('produto', 'Pesquisar por produto')!!}
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Campanha</label>
                        <select name="campanha" class="form-select">
                            <option value="">Todas</option>
                            @foreach($campanhas as $nomeCamp => $totalCamp)
                                <option value="{{ $nomeCamp }}" @selected(request('campanha') === $nomeCamp)>{{ $nomeCamp }} ({{ $totalCamp }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 text-left">
                        <br>
                        <button class="btn btn-primary" type="submit"> <i class="bx bx-search"></i>Pesquisar</button>
                        <a id="clear-filter" class="btn btn-danger"
                        href="{{ route('promocoes.index') }}"><i class="bx bx-eraser"></i> Limpar</a>
                    </div>
                </div>
                {!!Form::close()!!}

                @if(request('campanha') && isset($campanhas[request('campanha')]))
                <form method="post" action="{{ route('promocoes.campanha.encerrar') }}" class="mt-2"
                    onsubmit="return confirm('Desativar as {{ $campanhas[request('campanha')] }} promoção(ões) ativas da campanha {{ addslashes(request('campanha')) }}?');">
                    @csrf
                    <input type="hidden" name="campanha" value="{{ request('campanha') }}">
                    <button class="btn btn-outline-danger btn-sm"><i class="bx bx-stop-circle"></i> Encerrar a campanha "{{ request('campanha') }}"</button>
                </form>
                @endif

                <div class="card mt-2">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table mb-0 table-striped">
                                <thead class="">
                                    <tr>
                                        <th>Produto</th>
                                        <th>Campanha</th>
                                        <th>Tipo</th>
                                        <th>Valor</th>
                                        <th>Desde</th>
                                        <th>Até</th>
                                        <th>Unidades (usadas/limite)</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($data as $item)
                                    <tr>
                                        <td>#{{ $item->produto_id }} — {{ optional($item->produto)->nome }}</td>
                                        <td>{{ $item->observacao }}</td>
                                        <td>{{ \App\Models\Promocao::tipos()[$item->tipo] ?? $item->tipo }}</td>
                                        <td>
                                            @if($item->tipo === 'percentual')
                                                {{ __moeda($item->valor) }}%
                                            @else
                                                R$ {{ __moeda($item->valor) }}
                                            @endif
                                        </td>
                                        <td>{{ __data_pt($item->data_inicio, 0) }}</td>
                                        <td>{{ __data_pt($item->data_fim, 0) }}</td>
                                        <td>{{ $item->quantidade_utilizada }} / {{ $item->quantidade_limite ?? '∞' }}</td>
                                        <td>
                                            @php $status = $item->labelStatus(); @endphp
                                            <span class="badge bg-{{ $status === 'Vigente' ? 'success' : ($status === 'Desativada' ? 'secondary' : ($status === 'Agendada' ? 'info' : 'danger')) }}">
                                                {{ $status }}
                                            </span>
                                        </td>
                                        <td>
                                            <form action="{{ route('promocoes.destroy', $item->id) }}" method="post" id="form-{{$item->id}}">
                                                @method('delete')
                                                <a href="{{ route('promocoes.edit', $item) }}" class="btn btn-warning btn-sm text-white">
                                                    <i class="bx bx-edit"></i>
                                                </a>
                                                @csrf
                                                <button type="button" class="btn btn-delete btn-sm btn-danger">
                                                    <i class="bx bx-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="9" class="text-center">Nada encontrado</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                {!! $data->appends(request()->all())->links() !!}
            </div>
        </div>
    </div>
</div>
@endsection
