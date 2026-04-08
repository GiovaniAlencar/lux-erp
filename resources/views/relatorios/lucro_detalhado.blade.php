@extends('relatorios.default')
@section('content')

@if($data_inicial)
<h6>Data: {{$data_inicial}}</h6>
@endif
<table class="table-sm table-borderless"
style="border-bottom: 1px solid rgb(206, 206, 206); margin-bottom:10px;  width: 100%;">
<thead>
	<tr>
		<th width="12%" class="text-left">Data</th>
		<th width="8%" class="text-left">Hora</th>
		<th width="30%" class="text-left">Cliente</th>
		<th width="20%" class="text-left">Venda</th>
		<th width="20%" class="text-left">Compra</th>
		<th width="15%" class="text-left">Lucro</th>
		<th width="15%" class="text-left">%Lucro</th>
	</tr>
</thead>

@php
$somaLucro = 0;
$somaPerc = 0;
$somaCusto = 0;
$somaVenda = 0;
@endphp
@foreach($lucros as $key => $v)
<tr class="@if($key%2 == 0) pure-table-odd @endif">
	<td>{{$v['data']}}</td>
	<td>{{$v['horario']}}</td>
	<td>{{$v['cliente']}}</td>
	<td>{{__moeda($v['valor_venda'], 2)}}</td>
	<td>{{__moeda($v['valor_compra'], 2)}}</td>
	<td>{{__moeda($v['lucro'], 2)}}</td>
	<td>{{$v['lucro_percentual']}}</td>
</tr>

@php
$somaLucro += $v['lucro'];
$somaCusto += $v['valor_compra'];
$somaVenda += $v['valor_venda'];
$somaPerc += (float)($v['lucro_percentual']);
@endphp
@endforeach
</table>

<table style="width: 100%;">
	<tbody>
		<tr class="text-left">
			<th width="25%">Total Compra</th>
			<th width="25%"><strong>R$ {{__moeda($somaCusto, 2, ',', '.')}}</strong></th>
		</tr>
		<tr class="text-left">
			<th width="25%">Total Venda</th>
			<th width="25%"><strong>R$ {{__moeda($somaVenda, 2, ',', '.')}}</strong></th>
		</tr>
		<tr class="text-left">
			<th width="25%">Total lucro</th>
			<th width="25%"><strong>R$ {{__moeda($somaLucro, 2, ',', '.')}}</strong></th>
			<th width="25%">% médio</th>
			<th width="25%"><strong>{{__moeda((($somaVenda - $somaCusto) / ($somaCusto > 0 ? $somaCusto : 1)) * 100, 2, ',', '.')}}%</strong></th>
		</tr>
		<tr class="text-left">
			<th width="25%">Produtos vendidos</th>
			<th width="25%"><strong>{{$total_produtos}}</strong></th>
		</tr>
	</tbody>
</table>

@endsection
