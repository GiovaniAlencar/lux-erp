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
                    <div class="col-md-3 text-left">
                        <br>
                        <button class="btn btn-primary" type="submit"> <i class="bx bx-search"></i>Pesquisar</button>
                        <a id="clear-filter" class="btn btn-danger"
                        href="{{ route('promocoes.index') }}"><i class="bx bx-eraser"></i> Limpar</a>
                    </div>
                </div>
                {!!Form::close()!!}

                <div class="card mt-2">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table mb-0 table-striped">
                                <thead class="">
                                    <tr>
                                        <th>Produto</th>
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
                                        <td colspan="8" class="text-center">Nada encontrado</td>
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
