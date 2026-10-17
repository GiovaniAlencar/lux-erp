@extends('default.layout', ['title' => 'Extrato'])
@section('content')
<div class="page-content">
    <div class="card">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <h5 class="mb-0">Extrato{{ $conta ? ' · ' . $conta->nome : '' }}</h5>
                    <div class="small text-muted">Pagamentos de contas, lançamentos avulsos e transferências.</div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('fin.contas') }}" class="btn btn-light"><i class="bx bx-arrow-back"></i> Contas</a>
                    <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#modal-transferir" @disabled($contas->where('ativo', true)->count() < 2)><i class="bx bx-transfer"></i> Transferir</button>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-lancar" data-conta="{{ optional($conta)->id }}" @disabled($contas->where('ativo', true)->isEmpty())><i class="bx bx-plus-circle"></i> Lançar</button>
                </div>
            </div>

            <form method="get" class="row g-2 align-items-end mb-3">
                <div class="col-md-4"><label class="form-label">Conta</label>
                    <select name="conta_id" class="form-select" onchange="this.form.submit()">@foreach($contas as $c)<option value="{{ $c->id }}" @selected(optional($conta)->id === $c->id)>{{ $c->nome }}{{ $c->ativo ? '' : ' (inativa)' }}</option>@endforeach</select></div>
                <div class="col-6 col-md-3"><label class="form-label">De</label><input type="date" name="inicio" value="{{ $ini }}" class="form-control"></div>
                <div class="col-6 col-md-3"><label class="form-label">Até</label><input type="date" name="fim" value="{{ $fim }}" class="form-control"></div>
                <div class="col-md-2"><button class="btn btn-light w-100"><i class="bx bx-search"></i> Filtrar</button></div>
            </form>

            @if(!$conta)
                <div class="alert alert-light border">Cadastre uma conta em <a href="{{ route('fin.contas') }}">Contas e saldos</a>.</div>
            @else
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Data</th><th>Descrição</th><th>Categoria</th><th class="text-end">Entrada</th><th class="text-end">Saída</th><th class="text-end">Saldo</th><th></th></tr></thead>
                    <tbody>
                        <tr><td colspan="5" class="text-muted">Saldo anterior</td><td class="text-end fw-semibold">R$ {{ __moeda($saldoAnterior) }}</td><td></td></tr>
                        @forelse($movs as $m)
                        <tr>
                            <td class="text-nowrap">{{ $m->data->format('d/m/Y') }}</td>
                            <td>
                                {{ $m->descricao }}
                                <div class="small text-muted">
                                    {{ App\Models\FinMovimento::ORIGENS[$m->origem] ?? $m->origem }}{{ $m->forma_pagamento ? ' · ' . $m->forma_pagamento : '' }}{{ $m->usuario ? ' · ' . $m->usuario->nome : '' }}
                                    @if($m->conta_pagar_id) · <a href="{{ route('conta-pagar.pay', $m->conta_pagar_id) }}">ver conta</a>@endif @if(!empty($m->venda_id)) · <a href="{{ route('vendas.show', $m->venda_id) }}">ver venda</a>@endif
                                </div>
                            </td>
                            <td>{{ optional($m->categoria)->nome ?: '—' }}</td>
                            <td class="text-end text-success">{{ $m->tipo === 'entrada' ? 'R$ ' . __moeda($m->valor) : '' }}</td>
                            <td class="text-end text-danger">{{ $m->tipo === 'saida' ? 'R$ ' . __moeda($m->valor) : '' }}</td>
                            <td class="text-end">R$ {{ __moeda($m->saldo_apos) }}</td>
                            <td class="text-end text-nowrap">
                                @if($m->comprovante)<a href="{{ route('fin.comprovante', $m->id) }}" target="_blank" class="btn btn-sm btn-light" title="Comprovante"><i class="bx bx-paperclip"></i></a>@endif
                                <form method="post" action="{{ route('fin.estornar', $m->id) }}" class="d-inline" onsubmit="return confirm('Desfazer este lançamento?{{ $m->conta_pagar_id ? ' O valor volta a ficar em aberto na conta a pagar.' : '' }}{{ $m->transferencia ? ' As duas pontas da transferência serão desfeitas.' : '' }}{{ !empty($m->venda_id) ? ' O comprovante continua anexado na venda.' : '' }}')">
                                    @csrf
                                    <button class="btn btn-sm btn-danger" title="Desfazer"><i class="bx bx-undo"></i></button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Nenhum lançamento no período.</td></tr>
                        @endforelse
                        <tr><td colspan="5" class="fw-semibold">Saldo em {{ \Carbon\Carbon::parse($fim)->format('d/m/Y') }}</td><td class="text-end fw-bold {{ $saldoFinal < 0 ? 'text-danger' : '' }}">R$ {{ __moeda($saldoFinal) }}</td><td></td></tr>
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>
</div>
@include('financeiro._modais')
@endsection
