@extends('relatorios.default')
@section('content')

@php
$somaEstoque = 0; $somaValorEstoque = 0; $somaValorCusto = 0;
$negativos = 0; $semCusto = []; $custoAcimaVenda = []; $somaFiscal = 0; $somaCustoFiscal = 0;
foreach ($produtos as $p) {
	$q = (float) $p->quantidade;
	if ($q < 0) { $negativos++; continue; } // negativo é erro de lançamento: não abate do valor do estoque
	$somaEstoque += $q;
	$somaValorEstoque += $p->valor_venda * $q;
	$somaValorCusto += $p->valor_compra * $q;
	if ($q > 0 && (float) $p->valor_compra <= 0) $semCusto[] = $p;
	if ($q > 0 && $p->valor_venda > 0 && $p->valor_compra > $p->valor_venda) $custoAcimaVenda[] = $p;
	if (!empty($temFiscal)) {
		$somaFiscal += (float) $p->estoque_fiscal;
		$somaCustoFiscal += (float) $p->estoque_fiscal * (float) ($p->custo_fiscal ?? 0);
	}
}
@endphp

<p>Categoria: {{$categoria}} &nbsp;|&nbsp; Produtos listados: {{ sizeof($produtos) }}
	@if(!empty($limite)) <strong>(limitado a {{ $limite }} — os totais podem estar incompletos)</strong> @endif
</p>

@if($negativos || count($semCusto) || count($custoAcimaVenda))
<p style="color:#b45309">
	Atenção:
	@if(count($semCusto)) {{ count($semCusto) }} produto(s) com estoque e <strong>sem custo</strong> (não entram no valor de custo). @endif
	@if(count($custoAcimaVenda)) {{ count($custoAcimaVenda) }} produto(s) com <strong>custo maior que o preço</strong>. @endif
	@if($negativos) {{ $negativos }} produto(s) com estoque negativo (ignorados nos totais). @endif
	Eles estão marcados com * na lista.
</p>
@endif

<table class="table-sm table-borderless"
style="border-bottom: 1px solid rgb(206, 206, 206); margin-bottom:10px;  width: 100%;">
<thead>
	<tr>
		<th width="28%" class="text-left">Produto</th>
		<th width="9%" class="text-left">Estoque atual</th>
		@if(!empty($temFiscal))<th width="7%" class="text-left">Com nota</th>@endif
		<th width="9%" class="text-left">Custo</th>
		<th width="8%" class="text-left">Margem de lucro</th>
		<th width="9%" class="text-left">Valor de venda</th>
		<th width="10%" class="text-left">Projeção total de vendas</th>
		<th width="10%" class="text-left">Valor total em estoque</th>
		<th width="9%" class="text-left">Data da últ. compra</th>
	</tr>
</thead>

@foreach($produtos as $key => $p)
@php
$q = (float) $p->quantidade;
$alerta = $q < 0 || ($q > 0 && (float) $p->valor_compra <= 0) || ($q > 0 && $p->valor_venda > 0 && $p->valor_compra > $p->valor_venda);
@endphp
<tr class="@if($key%2 == 0) pure-table-odd @endif" @if($alerta) style="color:#b45309" @endif>
	<td>@if($alerta)* @endif{{$p->nome}} {{$p->str_grade}}
		@if($p->referencia != "")
		| Ref: {{$p->referencia}}
		@endif
		@if($p->inativo) (inativo) @endif
	</td>
	@if($p->unidade_venda == 'UNID' || $p->unidade_venda == 'UN')
	<td>{{number_format($q)}} {{$p->unidade_venda}}</td>
	@else
	<td>{{number_format($q, 3, ',', '.')}} {{$p->unidade_venda}}</td>
	@endif
	@if(!empty($temFiscal))<td>{{ (float) $p->estoque_fiscal ? number_format($p->estoque_fiscal, 0, ',', '.') : '' }}</td>@endif
	<td>R$ {{number_format($p->valor_compra, 2, ',', '.')}}</td>
	<td>{{number_format($p->percentual_lucro, 2)}}%</td>
	<td>R$ {{number_format($p->valor_venda, 2, ',', '.')}}</td>
	<td>R$ {{number_format($p->valor_venda*$q, 2, ',', '.')}}</td>
	<td>R$ {{number_format($p->valor_compra*$q, 2, ',', '.')}}</td>
	<td>{{$p->data_ultima_compra}}</td>
</tr>
@endforeach
</table>

<table style="width: 100%;">
	<tbody>
		<tr class="text-left">
			<th width="15%">Quantidade estoque</th>
			<th width="15%"><strong>{{number_format($somaEstoque, 2, ',', '.')}}</strong></th>
			<th width="15%">Soma valor de venda</th>
			<th width="15%"><strong>R$ {{number_format($somaValorEstoque, 2, ',', '.')}}</strong></th>
			<th width="15%">Soma valor de custo</th>
			<th width="15%"><strong>R$ {{number_format($somaValorCusto, 2, ',', '.')}}</strong></th>
		</tr>
		@if(!empty($temFiscal) && $somaFiscal > 0)
		<tr class="text-left">
			<th>Unidades com nota</th>
			<th><strong>{{number_format($somaFiscal, 0, ',', '.')}}</strong></th>
			<th>Custo das unidades com nota</th>
			<th><strong>R$ {{number_format($somaCustoFiscal, 2, ',', '.')}}</strong></th>
			<th colspan="2" style="font-weight:normal">(controle à parte: não somar ao total acima)</th>
		</tr>
		@endif
	</tbody>
</table>

@endsection
