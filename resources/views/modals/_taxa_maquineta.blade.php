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
					<input type="tel" id="tm_valor_base" class="form-control moeda" placeholder="0,00">
					<small class="text-muted">Preenche com o total do pedido, mas você pode alterar.</small>
				</div>
				<div class="col-12">
					<label class="form-label">Bandeira</label>
					<select id="tm_bandeira" class="form-select">
						<option value="master_visa">Master/Visa</option>
						<option value="hiper_elo_amex">Hiper/Elo/Amex</option>
					</select>
				</div>
				<div class="col-12">
					<label class="form-label">Parcelas</label>
					<select id="tm_parcelas" class="form-select">
						@for($i=1; $i<=12; $i++)
							<option value="{{ $i }}">{{ $i }}x</option>
						@endfor
					</select>
					<small class="text-muted d-block mt-1">% da maquineta: <span id="tm_percent">0,00%</span></small>
				</div>
				<div class="col-12">
					<div class="alert alert-secondary mb-0">
                        <div class="fw-bold">Total com taxa: <span id="tm_total_com_taxa">R$ 0,00</span></div>
						<div class="fw-bold">Acréscimo sugerido: <span id="tm_acrescimo_sugerido">R$ 0,00</span></div>
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
