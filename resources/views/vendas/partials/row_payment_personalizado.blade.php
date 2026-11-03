@php
    $data = date('Y-m-d');
@endphp

@for ($i=0; $i < $parcelas; $i++)
@php
$data = date('Y-m-d', strtotime("+$intervalo days",strtotime($data)));
@endphp
<tr>
	<td>
		<input readonly type="text" name="nome_pagamento[]" class="form-control" value="{{ $tipo }}">
        <input readonly type="hidden" name="tipo_pagamentos[]" class="form-control"
        value="{{ $tipoPagamento }}">
        <input type="hidden" name="na_entrega[]" value="{{ $naEntrega }}">
        <small class="d-block mt-1 {{ $naEntrega === '1' ? 'text-warning' : 'text-success' }}">{{ $naEntrega === '1' ? 'Cobrar na entrega' : 'Já pago' }}</small>
	</td>
	<td>
		<input readonly type="date" name="data_vencimento[]" class="form-control" value="{{ $data }}">
	</td>
	<td>
		<input readonly type="text" name="valor_parcela[]" class="form-control valor_integral" value="{{ __moeda($valorParcela) }}">
	</td>

	<td>
		<button class="btn btn-sm btn-danger btn-delete-row">
			<i class="bx bx-trash"></i>
		</button>
	</td>
</tr>
@endfor
