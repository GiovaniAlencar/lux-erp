@extends('default.layout',['title' => 'Pagar Conta'])
@section('content')
@php
    $pagoTotal = (float) $item->valor_pago;
    $pct = (float) $item->valor_integral > 0 ? min(100, $pagoTotal / (float) $item->valor_integral * 100) : 0;
    $padrao = optional($contas->firstWhere('padrao', true))->id ?? optional($contas->first())->id;
@endphp
<div class="page-content">
    <div class="card">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                <div>
                    <h5 class="mb-1">Pagar conta</h5>
                    <div class="text-muted small">{{ $item->referencia }}{{ $item->fornecedor ? ' · ' . $item->fornecedor->razao_social : '' }}</div>
                </div>
                <a href="{{ route('conta-pagar.index') }}" class="btn btn-light btn-sm"><i class="bx bx-arrow-back"></i> Voltar</a>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3"><div class="border rounded p-3"><div class="small text-muted">Valor da conta</div><div class="fs-5 fw-bold">R$ {{ __moeda($item->valor_integral) }}</div></div></div>
                <div class="col-6 col-md-3"><div class="border rounded p-3"><div class="small text-muted">Já pago</div><div class="fs-5 fw-bold">R$ {{ __moeda($pagoTotal) }}</div></div></div>
                <div class="col-6 col-md-3"><div class="border rounded p-3"><div class="small text-muted">Falta pagar</div><div class="fs-5 fw-bold {{ $restante > 0 ? 'text-danger' : 'text-success' }}">R$ {{ __moeda($restante) }}</div></div></div>
                <div class="col-6 col-md-3"><div class="border rounded p-3"><div class="small text-muted">Vencimento</div><div class="fs-5 fw-bold">{{ __data_pt($item->data_vencimento, false) }}</div></div></div>
                <div class="col-12"><div class="progress" style="height:6px"><div class="progress-bar" style="width: {{ $pct }}%"></div></div></div>
            </div>

            @if($contas->isEmpty())
                <div class="alert alert-warning">
                    Nenhuma conta financeira cadastrada. Um administrador precisa cadastrar as contas (Itaú, Conta 2, Caixa…) em
                    <a href="{{ route('fin.contas') }}">Financeiro › Contas e saldos</a> antes de registrar pagamentos.
                </div>
            @elseif($restante > 0)
            <form method="post" action="{{ route('conta-pagar.payPut', [$item->id]) }}" enctype="multipart/form-data" class="border rounded p-3 mb-4">
                @csrf
                @method('PUT')
                <h6 class="mb-3">Registrar pagamento</h6>
                @if($errors->any())<div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>@endif
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Valor pago agora</label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="text" name="valor_pago" class="form-control lux-dinheiro" required value="{{ old('valor_pago', __moeda($restante)) }}">
                        </div>
                        <div class="form-text">Pode ser parcial — o restante continua em aberto.</div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Data do pagamento</label>
                        <input type="date" name="data_pagamento" class="form-control" required value="{{ old('data_pagamento', date('Y-m-d')) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Saiu da conta</label>
                        <select name="conta_id" class="form-select" required>
                            @foreach($contas as $c)
                                <option value="{{ $c->id }}" @selected((int) old('conta_id', $padrao) === $c->id)>{{ $c->nome }}{{ $c->padrao ? ' (padrão)' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Forma</label>
                        <select name="tipo_pagamento" class="form-select">
                            <option value="">—</option>
                            @foreach(App\Models\ContaPagar::tiposPagamento() as $k => $v)
                                <option value="{{ $k }}" @selected(old('tipo_pagamento', 'Pix') === $k)>{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Comprovante</label>
                        <input type="file" name="comprovante" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf,image/*,application/pdf">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary px-4"><i class="bx bx-check"></i> Registrar pagamento</button>
                    </div>
                </div>
            </form>
            @else
                <div class="alert alert-success">Esta conta está quitada.</div>
            @endif

            <h6 class="mb-2">Pagamentos registrados</h6>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead><tr><th>Data</th><th>Valor</th><th>Conta</th><th>Forma</th><th>Por</th><th>Comprovante</th></tr></thead>
                    <tbody>
                    @forelse($baixas as $b)
                        <tr>
                            <td>{{ $b->data->format('d/m/Y') }}</td>
                            <td class="fw-semibold">R$ {{ __moeda($b->valor) }}</td>
                            <td>{{ optional($b->conta)->nome }}</td>
                            <td>{{ $b->forma_pagamento ?: '—' }}</td>
                            <td>{{ optional($b->usuario)->nome ?: '—' }}</td>
                            <td>
                                @if($b->comprovante)
                                    <a href="{{ route('fin.comprovante', $b->id) }}" target="_blank" class="btn btn-sm btn-light"><i class="bx bx-paperclip"></i> Ver</a>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">
                            @if($item->status && $pagoTotal > 0) Paga antes do controle de extrato (sem detalhes). @else Nenhum pagamento ainda. @endif
                        </td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
