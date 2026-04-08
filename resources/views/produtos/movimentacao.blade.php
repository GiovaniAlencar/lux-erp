@extends('default.layout',['title' => 'Movimentação'])
@section('content')
<div class="page-content">
    <div class="card border-top border-0 border-4 border-primary">
        <div class="card-body p-4">
            <div class="page-breadcrumb d-sm-flex align-items-center mb-3">
                <div class="ms-auto">
                    <a href="{{ route('estoque.index')}}" type="button" class="btn btn-light btn-sm">
                        <i class="bx bx-arrow-back"></i> Voltar
                    </a>
                </div>
            </div>
            <div class="card-title d-flex align-items-center">
                <h5 class="mb-0 text-primary">Movimentação do produto:
                    <strong style="color: black" id="prod">{{$item->nome}}</strong>
                </h5>
            </div>
            <hr>
            <a href="{{ route('movimentacao.print', [$item->id]) }}" class="btn btn-info" href="" id="imprimir">
                <i class="bx bx-printer"></i>Imprimir
            </a>
            <div class="table-resposive mt-3">
                <table class="table mb-0 table-striped">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Quantidade</th>
                            <th>Valor</th>
                            <th>Data</th>
                            <th>teste</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($movimentacoes as $e)
                        <tr>
                            <td>{{ $e['tipo'] }}</td>
                            <td>{{ $e['quantidade'] }}</td>
                            <td>{{ __moeda($e['valor']) }}</td>
                            <td>{{ __data_pt($e['data'], 1) }}</td>
                            <td>
                                {{ $e['tipo'] }}

                                @if ($e['tipo_model'] === 'Vendas')
                                <a href="{{ route('vendas.print', $e['id']) }}" class="btn btn-sm btn-outline-primary ms-2" title="Ver Venda">
                                    <i class="bx bx-show"></i>
                                </a>
                                @elseif ($e['tipo_model'] === 'PDV')
                                <a href="{{ route('pdv.show', $e['id']) }}" class="btn btn-sm btn-outline-secondary ms-2" title="Ver PDV">
                                    <i class="bx bx-show"></i>
                                </a>
                                @elseif ($e['tipo_model'] === 'Compras')
                                <a href="{{ route('compras.show', $e['id']) }}" class="btn btn-sm btn-outline-success ms-2" title="Ver Compra">
                                    <i class="bx bx-show"></i>
                                </a>
                                @endif
                            </td>

                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center">Nada encontrado</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection