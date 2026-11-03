<tr>
	<td>
		<input readonly type="text" name="nome_pagamento[]" class="form-control" value="{{ $tipo }}">
        <input readonly type="hidden" name="tipo_pagamentos[]" class="form-control"
        value="{{ $tipo_pagamento }}">
        <input type="hidden" name="na_entrega[]" value="{{ $naEntrega }}">
        <small class="d-block mt-1 {{ $naEntrega === '1' ? 'text-warning' : 'text-success' }}">{{ $naEntrega === '1' ? 'Cobrar na entrega' : 'Já pago' }}</small>
	</td>
	<td>
		<input type="date" name="data_vencimento[]" class="form-control" value="{{ $data_vencimento }}">
	</td>
	<td>
		<input readonly type="text" name="valor_parcela[]" class="form-control valor_integral" value="{{ $valor_integral }}">
	</td>

	<td>
		<button class="btn btn-sm btn-danger btn-delete-row">
			<i class="bx bx-trash"></i>
		</button>
	</td>
</tr>
