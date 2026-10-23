@php $tmBandeiras = config('lux.maquineta.bandeiras', []); @endphp
<div class="modal fade" id="modal-taxa-maquineta" tabindex="-1" aria-hidden="true" aria-modal="true" role="dialog" style="overflow:scroll;">
	<div class="modal-dialog modal-md">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Calcular taxas da maquineta</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body row g-3">
				<div class="col-12">
					<label class="form-label">Valor base (total do pedido)</label>
					<input type="text" inputmode="decimal" id="tm_valor_base" class="form-control lux-dinheiro" placeholder="0,00" autocomplete="off">
					<small class="text-muted">Preenche com o total do pedido, mas você pode alterar.</small>
				</div>
				<div class="col-md-6">
					<label class="form-label">Bandeira</label>
					<select id="tm_bandeira" class="form-select">
						@foreach($tmBandeiras as $chave => $b)
							<option value="{{ $chave }}">{{ $b['rotulo'] }}</option>
						@endforeach
					</select>
				</div>
				<div class="col-md-6">
					<label class="form-label">Pagamento</label>
					<select id="tm_parcelas" class="form-select">
						<option value="debito">Débito</option>
						@for($i = 1; $i <= 18; $i++)
							<option value="{{ $i }}">Crédito {{ $i }}x</option>
						@endfor
					</select>
				</div>
				<div class="col-12">
					<div class="alert alert-secondary mb-0">
						<div class="small text-muted mb-1">Taxa da maquineta: <b id="tm_percent">0,00%</b></div>
						<div class="fw-bold">Total com taxa: <span id="tm_total_com_taxa">R$ 0</span> <span class="fw-normal text-muted" id="tm_valor_parcela"></span></div>
						<div class="fw-bold">Acréscimo sugerido: <span id="tm_acrescimo_sugerido">R$ 0</span></div>
					</div>
				</div>
				<div class="col-12">
					<a href="#" class="small" id="tm_ver_tabela">Ver todas as opções para este valor</a>
					<div id="tm_tabela" class="table-responsive mt-2" style="display:none;max-height:320px;overflow:auto">
						<table class="table table-sm table-hover mb-0 small">
							<thead><tr><th>Pagamento</th><th class="text-end">Taxa</th><th class="text-end">Total</th><th class="text-end">Parcela</th></tr></thead>
							<tbody></tbody>
						</table>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
				<button type="button" class="btn btn-primary" id="tm_btn_aplicar">Aplicar na venda</button>
			</div>
		</div>
	</div>
</div>
<script>
	window.TM_TAXAS = @json(collect($tmBandeiras)->map(fn ($b) => $b['taxas']));
</script>
