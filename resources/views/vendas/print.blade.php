<!DOCTYPE html>
<html>

<head>
	<title></title>

	<style type="text/css">
		.content {
			margin-top: -30px;
		}

		body {
			font-family: Arial, sans-serif;
			background-color: #f9f9f9;
			padding: 20px;
		}

		h1 {
			font-size: 24px;
			color: #333;
		}

		table {
			width: 100%;
			border-collapse: collapse;
			margin-top: 20px;
		}

		th,
		td {
			padding: 6px;
			text-align: left;
			font-size: 14px;
		}

		th {
			background-color: #f2f2f2;
			color: #333;
		}

		td {
			background-color: #fff;
		}

		/* Estilos para zebra-stripping */
		tbody tr:nth-child(odd) {
			background-color: #f9f9f9;
		}

		tbody tr:nth-child(even) {
			background-color: #eaeaea;
		}

		a {
			text-decoration: none;
			color: #0066cc;
		}

		a:hover {
			text-decoration: underline;
		}

		.titulo {
			font-size: 18px;
			color: #333;
			font-weight: bold;
		}

		.b-top {
			border-top: 1px solid #ddd;
		}

		.b-bottom {
			border-bottom: 1px solid #ddd;
		}

		.strong-text {
			font-weight: bold;
			color: #333;
		}

		.center-text {
			text-align: center;
		}

		.align-right {
			text-align: right;
		}
	</style>
</head>

<body>

	<div class="content">
		<table>
			<tr>
				@if($config->logo != "")
				<td style="width: 150px;">
					<img src="{{'data:image/png;base64,' . base64_encode(file_get_contents(@public_path('uploads/configEmitente/').$config->logo))}}" width="100px;">
				</td>
				@else
				<td style="width: 150px;">
					<img src="{{'data:image/png;base64,' . base64_encode(file_get_contents(@public_path('imgs/slym.png')))}}" width="100px;">
				</td>
				@endif

				<td style="width: 400px;">
					<center><label class="titulo">PEDIDO DE VENDA #{{ $item->id }}</label></center>
				</td>
			</tr>
		</table>
	</div>

	<br>
	<table>
		<tr>
			<td><strong>Dados da empresa</strong></td>
			<td></td>
		</tr>
		<tr>
			<td class="b-top">Razão social: <strong>{{$config->razao_social}}</strong></td>
			<td class="b-top">Telefone: <strong>{{$config->fone}}</strong></td>
		</tr>
	</table>

	<table>
		<tr>
			<td><strong>Dados do cliente</strong></td>
			<td></td>
		</tr>
		<tr>
			<td class="b-top">Nome: <strong>{{$item->cliente->razao_social}}</strong></td>
			<td class="b-top">Telefone: <strong>{{$item->cliente->telefone}}</strong></td>
		</tr>
		<tr>
			<td class="b-top" style="width: 60%;">Endereço: <strong>{{$item->cliente->rua}}, {{$item->cliente->numero}} - {{$item->cliente->bairro}} - {{$item->cliente->cidade->nome}} ({{$item->cliente->cidade->uf}})</strong></td>
			<td class="b-top">CEP: <strong>{{$item->cliente->cep}}</strong></td>
		</tr>
	</table>

	<table>
		<tr>
			<td class="b-top b-bottom" style="height: 20px;"><strong>PRODUTOS:</strong></td>
		</tr>
	</table>

	<table>
		<thead>
			<tr>
				<th>Código</th>
				<th>Descrição</th>
				<th>Qtd.</th>
				<th>Valor Unitário</th>
				<th>Valor Total</th>
			</tr>
		</thead>
		<tbody>
			@php
			$somaItens = 0;
			$somaTotalItens = 0;
			$tipoDimensao = false;
			$tipoReceita = false;
			$casasDecimais = $config->casas_decimais;
			$casasDecimaisQtd = 2;
			@endphp
			@foreach($item->itens as $i)
			<tr>
				<td class="b-top">{{$i->produto->id}} {{$i->produto->referencia != "" ? "/ " . $i->produto->referencia : "" }}</td>
				<td class="b-top">{{$i->produto->nome}}</td>
				<td class="b-top">{{number_format($i->quantidade, $casasDecimaisQtd, ',', '.')}}</td>
				<td class="b-top">R$ {{number_format($i->valor, $casasDecimais, ',', '.')}}</td>
				<td class="b-top">R$ {{number_format($i->quantidade * $i->quantidade_dimensao * $i->valor, $casasDecimais, ',', '.')}}</td>
			</tr>
			@php
			$somaItens += $i->quantidade;
			$somaTotalItens += $i->quantidade * $i->valor * $i->quantidade_dimensao;
			if($i->altura > 0 || $i->esquerda > 0){
			$tipoDimensao = true;
			}
			if($i->produto->receita){
			$tipoReceita = true;
			}
			@endphp
			@endforeach
		</tbody>
	</table>

	<table>
		<tr>
			<td class="b-top b-bottom center-text"><strong>Quantidade Total: {{$somaItens}}</strong></td>
			<td class="b-top b-bottom center-text"><strong>Valor Total dos Itens: {{number_format($somaTotalItens, $casasDecimais, ',', '.')}}</strong></td>
		</tr>
	</table>

	@if(sizeof($item->duplicatas) > 0)
	<table>
		<tr>
			<td class="b-bottom" style="height: 50px;"><strong>FATURA:</strong></td>
		</tr>
	</table>
	<table>
		<tr>
			<td class="b-bottom">Vencimento</td>
			<td class="b-bottom">Valor</td>
		</tr>
		@foreach($item->duplicatas as $key => $d)
		<tr>
			<td class="b-bottom"><strong>{{ \Carbon\Carbon::parse($d->data_vencimento)->format('d/m/Y')}}</strong></td>
			<td class="b-bottom"><strong>{{number_format($d->valor_integral, $casasDecimais, ',', '.')}}</strong></td>
		</tr>
		@endforeach
	</table>
	@endif

	<br>

	<table>
		<tr>
			<td>Forma de pagamento: <strong>{{$item->forma_pagamento == 'a_vista' ? 'À vista' : $item->forma_pagamento}} @if($item->getFormaPagamento($item->empresa_id) != null)  <span style="color: #8950FC">{{ $item->getFormaPagamento($item->empresa_id)->infos }}</span>@endif</strong></td>
		</tr>
	</table>
	<table>
		<tr>
			<td class="">
				Data da venda: <strong>{{\Carbon\Carbon::parse($item->created_at)->format('d/m/Y H:i')}}</strong>
			</td>
			@if($item->vendedor_id)
			<td class="">
				Vendedor: <strong>{{ $item->vendedor_setado->funcionario->nome }}</strong>
			</td>
			@endif
			<td class="">
				@if($item->data_entrega != null)
				Data da entrega: <strong>{{\Carbon\Carbon::parse($item->data_entrega)->format('d/m/Y')}}</strong>
				@endif
			</td>
		</tr>
	</table>

	<table>
		<tr>
			<td class="">
				Desconto (-):
				<strong>
					{{number_format($item->desconto, 2, ',', '.')}}
				</strong>
			</td>

			<td class="">
				Acrescimo (+):
				<strong>
					{{number_format($item->acrescimo, 2, ',', '.')}}
				</strong>
			</td>

			<td class="">
				Frete (+):
				<strong>
					@if($item->frete)
					{{number_format($item->frete, 2, ',', '.')}}
					@else
					0,00
					@endif
				</strong>
			</td>

			<td class="">
				Valor Líquido:
				<strong>
					{{number_format($item->valor_total - $item->desconto + $item->acrescimo + $item->frete, $casasDecimais, ',', '.')}}
				</strong>
			</td>

		</tr>
	</table>

	@if($item->observacao != "" || $config->campo_obs_pedido != "")
	<table>
		<tr>
			<td class="">
				<span>Observação:
					<strong>{{$config->campo_obs_pedido}}
						{{$item->observacao}}
					</strong>
				</span>
			</td>
		</tr>
	</table>
	@endif

	<br><br>
	<table>
		<tr>
			<td class="">
				<strong>
					________________________________________
				</strong><br>
				<span style="font-size: 11px;">{{$config->razao_social}}</span>

			</td>

			<td class="">
				<strong>
					________________________________________
				</strong><br>
				<span style="font-size: 11px;">{{$item->cliente->razao_social}}</span>
			</td>
		</tr>
	</table>

	@if($tipoDimensao)
	<div class="page_break"></div>
	<table>
		<tr>
			<td class="">
				<strong>Dados do cliente</strong>
			</td>
		</tr>
	</table>
	<table>
		<tr>
			<td class="b-top">
				Nome: <strong>{{$item->cliente->razao_social}}</strong>
			</td>
			<td class="b-top">
				CPF/CNPJ: <strong>{{$item->cliente->cpf_cnpj}}</strong>
			</td>
		</tr>
	</table>
	<table>
		<tr>
			<td class="b-top">
				Endereço: <strong>{{$item->cliente->rua}}, {{$item->cliente->numero}} - {{$item->cliente->bairro}} - {{$item->cliente->cidade->nome}} ({{$item->cliente->cidade->uf}})</strong>
			</td>

			<td class="b-top">
				Telefone: <strong>{{$item->cliente->telefone}}</strong>
			</td>
		</tr>
	</table>

	<table>
		<tr>
			<td class="b-top">
				Nº Doc: <strong>{{$item->id}}</strong>
			</td>
			<td class="b-top">

			</td>
		</tr>
	</table>

	<table>
		<tr>
			<td class="b-top b-bottom" style="height: 50px;">
				<strong>MERCADORIAS:</strong>
			</td>
		</tr>
	</table>

	<table>
		<thead>
			<tr>
				<td class="">
					Cod
				</td>
				<td class="">
					Descrição
				</td>
				<td class="">
					Qtd. Dim.
				</td>
				<td class="">
					Qtd.
				</td>
			</tr>
		</thead>

		@php
		$somaItens = 0;
		$somaTotalItens = 0;
		$tipoDimensao = false;

		@endphp
		<tbody>
			@foreach($item->itens as $i)
			<tr>
				<th class="b-top">{{$i->produto->id}}</th>
				<th class="b-top">
					{{$i->produto->nome}}
					{{$i->produto->grade ? " (" . $i->produto->str_grade . ")" : ""}}
					@if($i->produto->lote != "")
					| Lote: {{$i->produto->lote}},
					Vencimento: {{$i->produto->vencimento}}
					@endif
					@if($i->produto->tipo_dimensao != '')
					@if($i->produto->tipo_dimensao == 'area')
					[Altura: {{$i->altura}}, Largura: {{$i->largura}}, Profundidade: {{$i->profundidade}}]
					@else
					[Superior: {{$i->superior}}, Infeior: {{$i->inferior}}, Esquerda: {{$i->esquerda}}, Direita: {{$i->direita}}]

					@endif
					@endif
				</th class="b-top">
				<th class="b-top">{{number_format($i->quantidade, 2, ',', '.')}}</th>
				<th class="b-top">{{number_format($i->quantidade_dimensao, 2, ',', '.')}}</th>

			</tr>
			@php
			$somaItens += $i->quantidade;
			$somaTotalItens += $i->quantidade * $i->valor;
			if($i->altura > 0 || $i->esquerda > 0){
			$tipoDimensao = true;
			}
			@endphp

			@endforeach
		</tbody>
	</table>
	<br>


	<br>
	<table>
		<tr>
			<td class="">
				<strong>Vendedor:
					{{$item->usuario->nome}}
				</strong>
			</td>
			<td class="">
				Data da venda: <strong>{{\Carbon\Carbon::parse($item->created_at)->format('d/m/Y H:i')}}</strong>
			</td>
			<td class="">
				Data da entrega: <strong>{{\Carbon\Carbon::parse($item->data_entrega)->format('d/m/Y')}}</strong>
			</td>
		</tr>
	</table>

	@if($item->observacao != "")
	<table>
		<tr>
			<td class="">
				<strong>Observação:
					{{$item->observacao}}
				</strong>
			</td>
		</tr>
	</table>
	@endif
	@endif

	@if($tipoReceita)

	<div class="page_break"></div>
	<table>
		<tr>

			@if($config->logo != "")
			<td class="" style="width: 150px;">
				<img src="{{'data:image/png;base64,' . base64_encode(file_get_contents(@public_path('logos/').$config->logo))}}" width="100px;">
			</td>
			@else
			<td class="" style="width: 150px;">
				<img src="{{'data:image/png;base64,' . base64_encode(file_get_contents(@public_path('imgs/slym.png')))}}" width="100px;">
			</td>
			@endif
		</tr>
	</table>
	<table>
		<tr>
			<td class="">
				<strong>Dados do cliente</strong>
			</td>
		</tr>
	</table>
	<table>
		<tr>
			<td class="b-top">
				Nome: <strong>{{$item->cliente->razao_social}}</strong>
			</td>
			<td class="b-top">
				CPF/CNPJ: <strong>{{$item->cliente->cpf_cnpj}}</strong>
			</td>
		</tr>
	</table>
	<table>
		<tr>
			<td class="b-top">
				Endereço: <strong>{{$item->cliente->rua}}, {{$item->cliente->numero}} - {{$item->cliente->bairro}} - {{$item->cliente->cidade->nome}} ({{$item->cliente->cidade->uf}})</strong>
			</td>

			<td class="b-top">
				Telefone: <strong>{{$item->cliente->telefone}}</strong>
			</td>
		</tr>
	</table>

	<table>
		<tr>
			<td class="b-top">
				Nº Doc: <strong>{{$item->id}}</strong>
			</td>
			<td class="b-top">

			</td>
		</tr>
	</table>

	<table>
		<tr>
			<td class="b-top b-bottom" style="height: 50px;">
				<strong>Produtos:</strong>
			</td>
		</tr>
	</table>
	<!-- receitas -->
	<table>
		<thead>
			<tr>
				<td class="">
					Cod
				</td>
				<td class="">
					Descrição
				</td>

				<td class="">
					Qtd.
				</td>
			</tr>
		</thead>

		@php
		$somaItens = 0;
		$somaTotalItens = 0;
		$tipoDimensao = false;

		@endphp
		<tbody>
			@foreach($item->itens as $i)
			<tr>
				<th class="b-top">{{$i->produto->id}}</th>
				<th class="b-top">
					{{$i->produto->nome}}
					{{$i->produto->grade ? " (" . $i->produto->str_grade . ")" : ""}}
					@if($i->produto->lote != "")
					| Lote: {{$i->produto->lote}},
					Vencimento: {{$i->produto->vencimento}}
					@endif

				</th class="b-top">
				<th class="b-top">{{number_format($i->quantidade, 2, ',', '.')}}</th>

			</tr>
			@php
			$somaItens += $i->quantidade;
			$somaTotalItens += $i->quantidade * $i->valor;

			@endphp

			<tr>
				<td colspan="3">Composição do item:</td>
			</tr>
			@foreach($i->produto->receita->itens as $ir)
			<tr>
				<th style="text-align: left;" class="b-bottom" colspan="2">{{$ir->produto->nome}}</th>
				<th class="b-bottom">{{$ir->quantidade}} {{$ir->medida}}</th>
			</tr>
			@endforeach
			@endforeach
		</tbody>
	</table>
	<br>

	<h4>Informação tecnica do(s) produto(s):</h4>
	@foreach($item->itens as $i)
	@if($i->produto->info_tecnica_composto != "")
	<p><strong>{{$i->produto->nome}}: {!! $i->produto->info_tecnica_composto !!}</p>
	@endif
	@endforeach

	@endif
</body>

</html>