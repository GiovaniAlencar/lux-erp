@extends('default.layout', ['title' => 'Importação em Massa — Detalhe'])
@section('content')
<div class="page-content">
    <div class="card border-top border-0 border-4 border-primary">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-0 text-primary">Importação #{{ $importacao->id }}</h5>
                    <small class="text-muted">
                        {{ $importacao->confirmado_em ? __data_pt($importacao->confirmado_em, false) : '—' }}
                        às {{ $importacao->confirmado_em ? \Carbon\Carbon::parse($importacao->confirmado_em)->format('H:i') : '—' }}
                        — {{ $importacao->usuario->nome ?? 'Usuário' }}
                    </small>
                </div>
                <a href="{{ route('estoque.importacaoMassa.index') }}" class="btn btn-light btn-sm"><i class="bx bx-arrow-back"></i> Voltar</a>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card p-3">
                        <small class="text-muted">Fornecedor</small>
                        <div class="fw-bold small">{{ $importacao->fornecedor->razao_social ?? '—' }}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-3">
                        <small class="text-muted">Compra</small>
                        <div class="fw-bold">
                            @if($importacao->compra_id)
                            <a href="{{ route('compras.show', $importacao->compra_id) }}">#{{ $importacao->compra_id }}</a>
                            @else
                            —
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-3">
                        <small class="text-muted">Itens válidos</small>
                        <div class="fw-bold">{{ $importacao->qtd_itens_validos }} / {{ $importacao->qtd_itens }}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-3">
                        <small class="text-muted">Total de unidades</small>
                        <div class="fw-bold">{{ number_format($importacao->qtd_unidades, 0, ',', '.') }}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-3">
                        <small class="text-muted">Valor total da compra</small>
                        <div class="fw-bold">R$ {{ __moeda($importacao->valor_total_compra) }}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-3">
                        <small class="text-muted">Arquivo</small>
                        <div class="fw-bold small text-break">{{ $importacao->arquivo_nome ?? '—' }}</div>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-striped">
                    <thead class="table-light">
                        <tr>
                            <th>Código</th>
                            <th>Produto</th>
                            <th>Est. anterior</th>
                            <th>Entrada</th>
                            <th>Est. final</th>
                            <th>Custo ant.</th>
                            <th>Custo novo</th>
                            <th>P1 ant.</th>
                            <th>P1 novo</th>
                            <th>P2 ant.</th>
                            <th>P2 novo</th>
                            <th>P3 ant.</th>
                            <th>P3 novo</th>
                            <th>Valor linha</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($importacao->itens as $item)
                        <tr>
                            <td>{{ $item->codigo_informado }}</td>
                            <td>{{ $item->produto_nome }}</td>
                            <td>{{ number_format($item->estoque_anterior, 0, ',', '.') }}</td>
                            <td>{{ number_format($item->quantidade, 0, ',', '.') }}</td>
                            <td>{{ number_format($item->estoque_final, 0, ',', '.') }}</td>
                            <td>{{ $item->custo_anterior !== null ? __moeda($item->custo_anterior) : '—' }}</td>
                            <td>{{ __moeda($item->custo_novo) }}</td>
                            <td>{{ $item->preco_1_anterior !== null ? __moeda($item->preco_1_anterior) : '—' }}</td>
                            <td>{{ __moeda($item->preco_1_novo) }}</td>
                            <td>{{ $item->preco_2_anterior !== null ? __moeda($item->preco_2_anterior) : '—' }}</td>
                            <td>{{ $item->preco_2_novo !== null ? __moeda($item->preco_2_novo) : '—' }}</td>
                            <td>{{ $item->preco_3_anterior !== null ? __moeda($item->preco_3_anterior) : '—' }}</td>
                            <td>{{ $item->preco_3_novo !== null ? __moeda($item->preco_3_novo) : '—' }}</td>
                            <td>R$ {{ __moeda($item->valor_linha) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
