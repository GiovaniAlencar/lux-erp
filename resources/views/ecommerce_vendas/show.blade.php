@extends('default.layout',['title' => 'Pedido Site'])
@section('content')
<div class="page-content">
	<div class="card mb-3">
		<div class="card-body p-4">
			<div class="d-flex justify-content-between">
				<div>
					<h5 class="mb-1">Pedido {{ $item->order_id }}</h5>
					<span class="badge bg-{{ $item->status==='aguardando_confirmacao'?'warning':($item->status==='cancelado'?'danger':'info') }}">
						{{ $statusLabels[$item->status] ?? $item->status }}
					</span>
					@if($item->venda_id)
						<a href="{{ route('vendas.show', $item->venda_id) }}" class="badge bg-success text-decoration-none">Venda #{{ $item->venda_id }}</a>
					@endif
					<span class="badge bg-dark">Origem: site</span>
				</div>
				<a href="{{ route('ecommerce-vendas.index') }}" class="btn btn-outline-secondary btn-sm">Voltar</a>
			</div>
		</div>
	</div>

	<div class="row">
		<div class="col-lg-7">
			<div class="card mb-3">
				<div class="card-body p-4">
					<h6 class="text-uppercase mb-3">Cliente</h6>
					<p class="mb-1"><strong>{{ $item->customer_name }}</strong></p>
					<p class="mb-1">{{ $item->customer_email }} · {{ $item->customer_phone }}</p>
					@if($item->user)
						<p class="mb-1">
							Usuário site #{{ $item->user->id }}
							@if($item->user->erp_cliente_id)
								· Cliente ERP #{{ $item->user->erp_cliente_id }}
								<a href="{{ route('ecommerce-usuarios.show', $item->user->id) }}">ver usuário</a>
							@else
								· <span class="text-danger">sem cliente ERP</span>
								<a href="{{ route('ecommerce-usuarios.show', $item->user->id) }}">aprovar/vincular</a>
							@endif
						</p>
					@endif

					<h6 class="text-uppercase mt-4 mb-2">Endereço de entrega</h6>
					@if($item->delivery_street)
						<p class="mb-0">
							{{ $item->delivery_street }}, {{ $item->delivery_number }}
							@if($item->delivery_complement) — {{ $item->delivery_complement }} @endif<br>
							{{ $item->delivery_neighborhood }} — {{ $item->delivery_city }}/{{ $item->delivery_state }}<br>
							CEP {{ $item->delivery_zip_code }}
						</p>
					@else
						<p class="text-muted">Não informado</p>
					@endif

					@if($item->notes)
						<h6 class="text-uppercase mt-4 mb-2">Observações</h6>
						<p>{{ $item->notes }}</p>
					@endif
				</div>
			</div>

			<div class="card mb-3">
				<div class="card-body p-4">
					<h6 class="text-uppercase mb-3">Itens</h6>
					@if($item->status === 'aguardando_confirmacao')
					<div class="small text-muted mb-2">
						Marque <strong>Indisponível</strong> nos itens que faltam em estoque para removê-los do pedido e importar apenas o restante.
					</div>
					@endif
					<div class="table-responsive">
						<table class="table table-sm">
							<thead>
								<tr>
									<th>Produto</th>
									<th>Qtd</th>
									<th>Unit.</th>
									<th>Total</th>
									@if($item->status === 'aguardando_confirmacao')
									<th class="text-center">Indisponível</th>
									@endif
								</tr>
							</thead>
							<tbody>
								@foreach($item->items as $it)
								<tr class="item-pedido-row" id="item-pedido-row-{{ $it->id }}">
									<td>#{{ $it->product_id }} — {{ $it->product_name }}</td>
									<td>{{ $it->quantity }}</td>
									<td>R$ {{ number_format($it->product_price, 2, ',', '.') }}</td>
									<td>R$ {{ number_format($it->total_price, 2, ',', '.') }}</td>
									@if($item->status === 'aguardando_confirmacao')
									<td class="text-center">
										<input type="checkbox" class="form-check-input chk-remover-item"
											name="remover_itens[]" value="{{ $it->id }}"
											form="form-confirmar-pedido"
											data-row="item-pedido-row-{{ $it->id }}">
									</td>
									@endif
								</tr>
								@endforeach
							</tbody>
						</table>
					</div>
					<div class="text-end">
						<div>Subtotal: R$ {{ number_format($item->subtotal, 2, ',', '.') }}</div>
						<div>Desconto: R$ {{ number_format($item->discount ?? 0, 2, ',', '.') }}</div>
						<div>
							Frete:
							@if($item->shipping_status === 'fixed' && $item->shipping_price !== null)
								R$ {{ number_format($item->shipping_price, 2, ',', '.') }}
							@else
								A combinar
							@endif
						</div>
						<div class="fw-bold">Total: R$ {{ number_format($item->total, 2, ',', '.') }}</div>
						<div class="small text-muted">
							Forma de pagamento:
							@php
								$pm = strtolower((string)($item->payment_method ?? ''));
								$pmLabel = $pm === 'cartao' || $pm === 'cartão' || $pm === 'card' ? 'Cartão' : ($pm === 'pix' ? 'PIX' : ($item->payment_method ?: '—'));
							@endphp
							<strong>{{ $pmLabel }}</strong>
						</div>
						<div class="small text-muted">Status pagamento (site): {{ $item->payment_status ?? 'pendente' }}</div>
					</div>
				</div>
			</div>
		</div>

		<div class="col-lg-5">
			@if($item->status === 'aguardando_confirmacao')
			<div class="card mb-3">
				<div class="card-body p-4">
					<h6 class="text-uppercase mb-3">Ajustar valores</h6>
					<form method="post" action="{{ route('ecommerce-vendas.atualizar-valores', $item->id) }}">
						@csrf
						<div class="mb-2">
							<label class="form-label">Frete (R$)</label>
							<input type="text" name="shipping_price" class="form-control" value="{{ number_format($item->shipping_price ?? 0, 2, ',', '') }}">
						</div>
						<div class="mb-2">
							<label class="form-label">Desconto (R$)</label>
							<input type="text" name="discount" class="form-control" value="{{ number_format($item->discount ?? 0, 2, ',', '') }}">
						</div>
						<button type="submit" class="btn btn-outline-primary btn-sm">Salvar valores</button>
					</form>
				</div>
			</div>

			<div class="card mb-3 border-success">
				<div class="card-body p-4">
					<h6 class="text-uppercase mb-3 text-success">Confirmar → gerar venda</h6>
					<div class="small text-muted mb-2 itens-removidos-aviso d-none">
						<i class="bx bx-info-circle"></i> <span class="qtd-removidos">0</span> item(ns) marcado(s) como indisponível não entrarão na venda.
					</div>
					<form method="post" id="form-confirmar-pedido" action="{{ route('ecommerce-vendas.confirmar', $item->id) }}">
						@csrf
						<div class="mb-2">
							<label class="form-label">Cliente ERP (ID)</label>
							<input type="number" name="cliente_id" class="form-control"
								value="{{ $item->erp_cliente_id ?: optional($item->user)->erp_cliente_id }}"
								placeholder="Obrigatório se usuário ainda não tiver vínculo">
						</div>
						<div class="mb-2">
							<label class="form-label">Frete na venda (R$)</label>
							<input type="text" name="frete" class="form-control" value="{{ number_format($item->shipping_price ?? 0, 2, ',', '') }}">
						</div>
						<div class="mb-2">
							<label class="form-label">Desconto na venda (R$)</label>
							<input type="text" name="desconto" class="form-control" value="{{ number_format($item->discount ?? 0, 2, ',', '') }}">
						</div>
						<button type="submit" class="btn btn-success w-100" id="btn-confirmar-pedido">
							<i class="bx bx-check"></i> Confirmar pedido
						</button>
					</form>
				</div>
			</div>

			<div class="card mb-3 border-danger">
				<div class="card-body p-4">
					<h6 class="text-uppercase mb-3 text-danger">Cancelar</h6>
					<form method="post" action="{{ route('ecommerce-vendas.cancelar', $item->id) }}">
						@csrf
						<input type="text" name="cancelled_reason" class="form-control mb-2" placeholder="Motivo">
						<button type="submit" class="btn btn-danger w-100" onclick="return confirm('Cancelar e liberar reserva de estoque?')">
							Cancelar pedido
						</button>
					</form>
				</div>
			</div>
			@elseif(!$item->venda_id && $item->status !== 'cancelado')
			<div class="card mb-3">
				<div class="card-body p-4">
					<form method="post" action="{{ route('ecommerce-vendas.cancelar', $item->id) }}">
						@csrf
						<input type="text" name="cancelled_reason" class="form-control mb-2" placeholder="Motivo">
						<button type="submit" class="btn btn-outline-danger">Cancelar</button>
					</form>
				</div>
			</div>
			@endif

			@if($item->podeRestaurar())
			<div class="card mb-3 border-info">
				<div class="card-body p-4">
					<h6 class="text-uppercase mb-2 text-info">Restaurar pedido</h6>
					<p class="small text-muted">
						Este pedido foi cancelado (inclusive se foi por expiração automática, ex.: pedido feito no sábado
						que expirou sem confirmação). Você pode trazê-lo de volta para "aguardando confirmação" sem
						precisar digitar tudo novamente. Prazo: até {{ \App\Models\EcommerceOrder::DIAS_RESTAURAR_CANCELADO }}
						dias após o cancelamento — restam {{ $item->diasRestantesParaRestaurar() }} dia(s).
					</p>
					<form method="post" action="{{ route('ecommerce-vendas.restaurar', $item->id) }}">
						@csrf
						<button type="submit" class="btn btn-info w-100" onclick="return confirm('Restaurar este pedido para aguardando confirmação?')">
							<i class="bx bx-undo"></i> Restaurar pedido
						</button>
					</form>
				</div>
			</div>
			@endif

			@if($item->cancelled_reason)
				<div class="alert alert-warning">{{ $item->cancelled_reason }}</div>
			@endif
			@if($item->expires_at && $item->status === 'aguardando_confirmacao')
				<div class="alert alert-info">Expira em: {{ __data_pt($item->expires_at, 1) }}</div>
			@endif
		</div>
	</div>
</div>

@if($item->status === 'aguardando_confirmacao')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var aviso = document.querySelector('.itens-removidos-aviso');
    var contador = document.querySelector('.qtd-removidos');
    var btnConfirmar = document.getElementById('btn-confirmar-pedido');

    function atualizarRemovidos() {
        var marcados = document.querySelectorAll('.chk-remover-item:checked');
        var qtd = marcados.length;

        document.querySelectorAll('.item-pedido-row').forEach(function (row) {
            row.classList.remove('text-decoration-line-through', 'text-muted');
        });
        marcados.forEach(function (chk) {
            var row = document.getElementById(chk.dataset.row);
            if (row) row.classList.add('text-decoration-line-through', 'text-muted');
        });

        if (aviso && contador) {
            contador.textContent = qtd;
            aviso.classList.toggle('d-none', qtd === 0);
        }
    }

    document.querySelectorAll('.chk-remover-item').forEach(function (chk) {
        chk.addEventListener('change', atualizarRemovidos);
    });

    if (btnConfirmar) {
        btnConfirmar.addEventListener('click', function (e) {
            var qtd = document.querySelectorAll('.chk-remover-item:checked').length;
            var msg = qtd > 0
                ? ('Confirmar pedido e gerar venda no ERP? ' + qtd + ' item(ns) marcado(s) como indisponível ficarão de fora. Estoque dos demais será baixado.')
                : 'Confirmar pedido e gerar venda no ERP? Estoque será baixado.';
            if (!confirm(msg)) {
                e.preventDefault();
            }
        });
    }

    atualizarRemovidos();
});
</script>
@endif
@endsection
