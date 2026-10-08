@extends('default.layout',['title' => 'Vendas do Site'])
@section('content')
<div class="page-content">
	<div class="card">
		<div class="card-body p-4">
			<h6 class="mb-3 text-uppercase">Vendas do Site (Catálogo)</h6>

			{!! Form::open()->fill(request()->all())->get() !!}
			<div class="row align-items-end">
				<div class="col-md-4">
					{!! Form::text('q', 'Pedido, cliente, telefone') !!}
				</div>
				<div class="col-md-3">
					<label class="form-label">Status</label>
					<select name="status" class="form-select">
						<option value="">Aguardando confirmação (padrão)</option>
						<option value="__all__" @if(request('status')==='__all__') selected @endif>Todos</option>
						@foreach($statusLabels as $k => $lbl)
							<option value="{{ $k }}" @if(request('status')===$k) selected @endif>{{ $lbl }}</option>
						@endforeach
					</select>
				</div>
				<div class="col-md-3">
					<button class="btn btn-primary" type="submit"><i class="bx bx-search"></i> Pesquisar</button>
					<a class="btn btn-danger" href="{{ route('ecommerce-vendas.index') }}"><i class="bx bx-eraser"></i> Limpar</a>
				</div>
			</div>
			{!! Form::close() !!}

			<hr/>
			<div class="table-responsive">
				<table class="table table-striped mb-0">
					<thead>
						<tr>
							<th>Pedido</th>
							<th>Cliente</th>
							<th>Total</th>
							<th>Pagamento</th>
							<th>Frete</th>
							<th>Status</th>
							<th>Expira</th>
							<th>Criado</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						@forelse($data as $item)
						<tr>
							<td><strong>{{ $item->order_id }}</strong></td>
							<td>
								{{ $item->customer_name }}<br>
								<small class="text-muted">{{ $item->customer_phone }}</small>
							</td>
							<td>R$ {{ number_format($item->total, 2, ',', '.') }}</td>
							<td>
								@php
									$pm = strtolower((string)($item->payment_method ?? ''));
								@endphp
								@if($pm === 'cartao' || $pm === 'cartão' || $pm === 'card')
									Cartão
								@elseif($pm === 'pix')
									PIX
								@else
									{{ $item->payment_method ?: '—' }}
								@endif
							</td>
							<td>
								@if($item->shipping_status === 'fixed' && $item->shipping_price !== null)
									R$ {{ number_format($item->shipping_price, 2, ',', '.') }}
								@else
									A combinar
								@endif
							</td>
							<td>
								<span class="badge bg-{{ $item->status==='aguardando_confirmacao'?'warning':($item->status==='cancelado'?'danger':($item->status==='entregue'?'success':'info')) }}">
									{{ $statusLabels[$item->status] ?? $item->status }}
								</span>
								@if($item->podeRestaurar())
									<span class="badge bg-info" title="Pode ser restaurado sem redigitar">
										restaurável ({{ $item->diasRestantesParaRestaurar() }}d)
									</span>
								@endif
							</td>
							<td>{{ $item->expires_at ? __data_pt($item->expires_at, 1) : '—' }}</td>
							<td>{{ $item->created_at ? __data_pt($item->created_at, 1) : '—' }}</td>
							<td>
								<a href="{{ route('ecommerce-vendas.show', $item->id) }}" class="btn btn-primary btn-sm">
									<i class="bx bx-show"></i>
								</a>
							</td>
						</tr>
						@empty
						<tr><td colspan="8" class="text-center">Nenhum pedido encontrado</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
			{!! $data->appends(request()->all())->links() !!}
		</div>
	</div>
</div>
@endsection
