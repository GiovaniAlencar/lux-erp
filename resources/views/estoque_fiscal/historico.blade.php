@extends('default.layout', ['title' => 'Histórico do estoque fiscal'])
@section('content')
@php $fq = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ','); @endphp
<div class="page-content">
    <div class="card border-top border-0 border-4 border-primary">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-0 text-primary">Estoque fiscal · {{ $produto->nome }}</h5>
                    <small class="text-muted">Saldo atual com nota: <strong>{{ $fq($produto->estoque_fiscal) }}</strong> · custo fiscal médio <strong>R$ {{ __moeda($produto->custo_fiscal) }}</strong> · custo real <strong>R$ {{ __moeda($produto->valor_compra) }}</strong></small>
                </div>
                <a href="{{ route('estoque-fiscal.index') }}" class="btn btn-light btn-sm"><i class="bx bx-arrow-back"></i> Voltar</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead class="table-light"><tr><th>Quando</th><th>Tipo</th><th class="text-end">Qtd</th><th class="text-end">Saldo</th><th class="text-end">Custo un.</th><th>Documento</th><th>Obs.</th><th>Usuário</th></tr></thead>
                    <tbody>
                    @forelse($movs as $m)
                        <tr>
                            <td>{{ $m->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ \App\Models\EstoqueFiscalMovimento::TIPOS[$m->tipo] ?? $m->tipo }}</td>
                            <td class="text-end {{ $m->quantidade < 0 ? 'text-danger' : 'text-success' }}">{{ $m->quantidade > 0 ? '+' : '' }}{{ $fq($m->quantidade) }}</td>
                            <td class="text-end">{{ $fq($m->saldo_apos) }}</td>
                            <td class="text-end">{{ $m->custo_unitario !== null ? __moeda($m->custo_unitario) : '' }}</td>
                            <td>
                                @if($m->venda_id)<a href="{{ route('vendas.show', $m->venda_id) }}">{{ $m->documento }}</a>@else{{ $m->documento }}@endif
                                @if($m->chave)<div class="small text-muted font-monospace">{{ $m->chave }}</div>@endif
                            </td>
                            <td class="small">{{ $m->observacao }}</td>
                            <td class="small">{{ optional($m->usuario)->nome }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">Sem movimentações.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {!! $movs->links() !!}
        </div>
    </div>
</div>
@endsection
