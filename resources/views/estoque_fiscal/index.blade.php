@extends('default.layout', ['title' => 'Estoque fiscal'])
@section('content')
@php $fq = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ','); @endphp
<div class="page-content">
    <div class="card border-top border-0 border-4 border-primary">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <div>
                    <h5 class="mb-0 text-primary">Estoque fiscal (com nota)</h5>
                    <small class="text-muted">Quantas unidades de cada produto têm nota de entrada. A venda só manda para a conta fiscal / NF-e até esse saldo. Não altera o estoque normal nem o custo.</small>
                </div>
                <a href="{{ route('estoque-fiscal.entrada') }}" class="btn btn-primary btn-sm"><i class="bx bx-import"></i> Entrada pelo XML da nota</a>
            </div>
            <form method="get" class="row g-2 mb-3">
                <div class="col-md-4"><input type="text" name="busca" value="{{ $busca }}" class="form-control form-control-sm" placeholder="Nome ou ID do produto"></div>
                <div class="col-auto"><button class="btn btn-sm btn-outline-primary"><i class="bx bx-search"></i> Buscar</button></div>
            </form>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th><th>Produto</th>
                            <th class="text-end">Estoque total</th>
                            <th class="text-end">Com nota (fiscal)</th>
                            <th class="text-end">Sem nota</th>
                            <th class="text-end" title="Custo médio de todo o estoque (com e sem nota)">Custo real</th>
                            <th class="text-end" title="Custo médio só das unidades com nota">Custo fiscal</th>
                            <th style="min-width:380px">Ajuste manual</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($produtos as $p)
                        @php $sem = (float) $p->estoque_total - (float) $p->estoque_fiscal; @endphp
                        <tr>
                            <td>{{ $p->id }}</td>
                            <td>{{ $p->nome }} @if(!$p->fiscal)<span class="badge bg-secondary">não marcado fiscal</span>@endif</td>
                            <td class="text-end">{{ $fq($p->estoque_total) }}</td>
                            <td class="text-end fw-bold text-primary">{{ $fq($p->estoque_fiscal) }}</td>
                            <td class="text-end {{ $sem < -0.0005 ? 'text-danger fw-bold' : '' }}" title="{{ $sem < -0.0005 ? 'Saldo com nota maior que o estoque total: confira' : '' }}">{{ $fq($sem) }}</td>
                            <td class="text-end">{{ __moeda($p->valor_compra) }}</td>
                            <td class="text-end">{{ (float) $p->custo_fiscal > 0 ? __moeda($p->custo_fiscal) : '—' }}</td>
                            <td>
                                <form method="post" action="{{ route('estoque-fiscal.ajuste') }}" class="d-flex gap-1" onsubmit="return confirm('Confirmar ajuste do saldo fiscal?')">
                                    @csrf
                                    <input type="hidden" name="produto_id" value="{{ $p->id }}">
                                    <select name="operacao" class="form-select form-select-sm" style="max-width:110px">
                                        <option value="definir">Definir</option>
                                        <option value="somar">Somar</option>
                                        <option value="subtrair">Subtrair</option>
                                    </select>
                                    <input name="quantidade" class="form-control form-control-sm" style="max-width:80px" placeholder="Qtd" required>
                                    <input name="observacao" class="form-control form-control-sm" placeholder="Motivo (obrigatório)" required minlength="5" maxlength="255">
                                    <button class="btn btn-sm btn-outline-secondary" type="submit">OK</button>
                                </form>
                            </td>
                            <td><a href="{{ route('estoque-fiscal.historico', $p->id) }}" class="btn btn-sm btn-light" title="Histórico"><i class="bx bx-history"></i></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted py-4">Nenhum produto fiscal.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {!! $produtos->links() !!}
        </div>
    </div>
</div>
@endsection
