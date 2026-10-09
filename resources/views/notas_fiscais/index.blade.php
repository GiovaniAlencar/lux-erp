@extends('default.layout', ['title' => 'Notas fiscais (NF-e)'])
@section('content')
@php $cls = ['rascunho' => 'bg-secondary', 'rejeitada' => 'bg-danger', 'autorizada' => 'bg-success', 'cancelada' => 'bg-dark']; @endphp
<div class="page-content">
    <div class="card border-top border-0 border-4 border-primary">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-0 text-primary">Notas fiscais (NF-e)</h5>
                    <small class="text-muted">NF-e emitidas pelo ERP a partir da parte fiscal dos pedidos.</small>
                </div>
            </div>

            <form method="get" class="row g-2 align-items-end mb-3">
                <div class="col-md-2">
                    <label class="form-label small mb-0">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        @foreach(\App\Models\NotaFiscal::STATUS as $k => $l)
                        <option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label small mb-0">Ambiente</label>
                    <select name="ambiente" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="1" @selected(request('ambiente') === '1')>Produção</option>
                        <option value="2" @selected(request('ambiente') === '2')>Homolog.</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0">Emissão de</label>
                    <input type="date" name="data_inicial" class="form-control form-control-sm" value="{{ request('data_inicial') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0">até</label>
                    <input type="date" name="data_final" class="form-control form-control-sm" value="{{ request('data_final') }}">
                </div>
                <div class="col-md-1">
                    <label class="form-label small mb-0">Nº NF</label>
                    <input type="number" name="numero" class="form-control form-control-sm" value="{{ request('numero') }}">
                </div>
                <div class="col-md-1">
                    <label class="form-label small mb-0">Pedido</label>
                    <input type="number" name="venda_id" class="form-control form-control-sm" value="{{ request('venda_id') }}">
                </div>
                <div class="col-md-1">
                    <label class="form-label small mb-0">Cliente</label>
                    <input type="text" name="cliente" class="form-control form-control-sm" value="{{ request('cliente') }}">
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button class="btn btn-primary btn-sm" type="submit"><i class="bx bx-search"></i> Filtrar</button>
                    <a class="btn btn-light btn-sm" href="{{ route('notas-fiscais.index') }}"><i class="bx bx-eraser"></i></a>
                </div>
            </form>

            <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
                <div class="small text-muted">Total autorizado no filtro: <strong>R$ {{ __moeda($totalAutorizadas) }}</strong></div>
                <div class="d-flex gap-2">
                    <a class="btn btn-outline-success btn-sm" href="{{ route('notas-fiscais.exportar', request()->query()) }}"><i class="bx bx-spreadsheet"></i> Exportar (Excel/CSV)</a>
                    <a class="btn btn-outline-dark btn-sm" href="{{ route('notas-fiscais.xmls-zip', request()->query()) }}" title="NF-e autorizadas e canceladas do filtro, com eventos"><i class="bx bx-archive"></i> Baixar XMLs (ZIP) p/ contador</a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Nº / Série</th>
                            <th>Emissão</th>
                            <th>Cliente</th>
                            <th>Pedido</th>
                            <th class="text-end">Valor</th>
                            <th>Status</th>
                            <th>Amb.</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($notas as $n)
                        <tr>
                            <td>{{ $n->numero ? $n->numero . ' / ' . $n->serie : '—' }}</td>
                            <td>{{ optional($n->data_emissao)->format('d/m/Y H:i') }}</td>
                            <td>{{ optional($n->cliente)->razao_social }}<div class="small text-muted">{{ optional($n->cliente)->cpf_cnpj }}</div></td>
                            <td>@if($n->venda_id)<a href="{{ route('vendas.show', $n->venda_id) }}">#{{ $n->venda_id }}</a>@endif</td>
                            <td class="text-end">R$ {{ __moeda($n->valor_total) }}</td>
                            <td><span class="badge {{ $cls[$n->status] ?? 'bg-secondary' }}">{{ \App\Models\NotaFiscal::STATUS[$n->status] ?? $n->status }}</span></td>
                            <td>{!! (int) $n->ambiente === 2 ? '<span class="badge bg-warning text-dark">Homolog.</span>' : '<span class="badge bg-primary">Produção</span>' !!}</td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('notas-fiscais.conferir', $n->id) }}">{{ $n->editavel() ? 'Conferir' : 'Abrir' }}</a>
                                @if(in_array($n->status, ['autorizada', 'cancelada']))
                                <a class="btn btn-sm btn-outline-secondary" target="_blank" href="{{ route('notas-fiscais.danfe', $n->id) }}" title="DANFE"><i class="bx bx-printer"></i></a>
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('notas-fiscais.xml', $n->id) }}" title="XML"><i class="bx bx-download"></i></a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">Nenhuma nota. Emita a partir de um pedido com itens fiscais (botão "Emitir NF-e" no pedido).</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {!! $notas->links() !!}
        </div>
    </div>
</div>
@endsection
