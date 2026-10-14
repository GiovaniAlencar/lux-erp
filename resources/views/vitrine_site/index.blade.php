@extends('default.layout',['title' => 'Vitrine do Site'])
@section('content')
<div class="page-content">
	<div class="card">
		<div class="card-body p-4">
			<h6 class="mb-1 text-uppercase">Vitrine do site — Destaques</h6>
			<p class="small text-muted mb-3">
				Produtos que aparecem na seção <b>"Destaques"</b> da home, nessa ordem. Sem estoque, o produto some do site sozinho.
				Também dá para marcar "Destacar no site" direto no cadastro do produto.
			</p>

			<form method="post" action="{{ route('vitrine-site.adicionar') }}" class="row g-2 align-items-end mb-4">
				@csrf
				<div class="col-md-6">
					<label class="form-label">Adicionar produto (código ou nome)</label>
					<input type="text" name="produto" class="form-control" placeholder="Ex.: 595 ou Asad Bourbon" required>
				</div>
				<div class="col-md-3">
					<label class="form-label">Destacar até (opcional)</label>
					<input type="date" name="destaque_ate" class="form-control">
				</div>
				<div class="col-md-3">
					<button class="btn btn-primary w-100"><i class="bx bx-plus"></i> Adicionar</button>
				</div>
			</form>

			@if($destaques->isEmpty())
				<div class="alert alert-light border">Nenhum produto em destaque. A seção "Destaques" fica escondida no site até você adicionar algum.</div>
			@else
			<form method="post" action="{{ route('vitrine-site.atualizar') }}">
				@csrf
				<div class="table-responsive">
					<table class="table table-sm align-middle">
						<thead>
							<tr>
								<th style="width:90px">Ordem</th>
								<th>Produto</th>
								<th>Estoque</th>
								<th style="width:170px">Destacar até</th>
								<th>Situação no site</th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							@foreach($destaques as $p)
							@php
								$estoque = (float) optional($p->estoque)->quantidade;
								$vencido = $p->destaque_ate && \Carbon\Carbon::parse($p->destaque_ate)->endOfDay()->isPast();
							@endphp
							<tr>
								<td><input type="number" min="0" name="itens[{{ $p->id }}][ordem]" value="{{ (int) $p->destaque_ordem }}" class="form-control form-control-sm"></td>
								<td><b>#{{ $p->id }}</b> {{ $p->nome }}</td>
								<td>{{ rtrim(rtrim(number_format($estoque, 3, ',', '.'), '0'), ',') }}</td>
								<td><input type="date" name="itens[{{ $p->id }}][ate]" value="{{ $p->destaque_ate ? \Carbon\Carbon::parse($p->destaque_ate)->format('Y-m-d') : '' }}" class="form-control form-control-sm"></td>
								<td>
									@if($vencido)
										<span class="badge bg-secondary">Prazo encerrado</span>
									@elseif($estoque <= 0)
										<span class="badge bg-warning text-dark">Sem estoque (oculto)</span>
									@elseif($p->inativo)
										<span class="badge bg-secondary">Inativo</span>
									@else
										<span class="badge bg-success">Aparecendo</span>
									@endif
								</td>
								<td class="text-end">
									<a href="{{ route('vitrine-site.remover', $p->id) }}" class="btn btn-sm btn-outline-danger" onclick="return confirm('Tirar dos destaques?')">Tirar</a>
								</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
				<button class="btn btn-success"><i class="bx bx-save"></i> Salvar ordem e prazos</button>
			</form>
			@endif

			<hr class="my-4">
			<h6 class="mb-1 text-uppercase">Aguardando reposição ("Avise-me")</h6>
			<p class="small text-muted mb-2">Clientes que pediram aviso de produto esgotado. Quando o estoque voltar, o aviso é gerado automaticamente.</p>
			@if($aguardando->isEmpty())
				<div class="small text-muted">Ninguém aguardando no momento.</div>
			@else
				<div class="table-responsive">
					<table class="table table-sm align-middle mb-0">
						<thead><tr><th>Produto</th><th>Clientes esperando</th><th>Último pedido de aviso</th><th>Estoque</th></tr></thead>
						<tbody>
							@foreach($aguardando as $a)
							<tr>
								<td><b>#{{ $a->id }}</b> {{ $a->nome }}</td>
								<td><span class="badge bg-primary">{{ $a->clientes }}</span></td>
								<td>{{ \Carbon\Carbon::parse($a->ultimo)->format('d/m/Y H:i') }}</td>
								<td>{{ rtrim(rtrim(number_format((float) $a->estoque, 3, ',', '.'), '0'), ',') }}</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			@endif

			<hr class="my-4">
			<h6 class="mb-1 text-uppercase">Promoções no site (automático)</h6>
			<p class="small text-muted mb-2">A seção <b>"Promoções"</b> mostra sozinha os produtos com promoção vigente hoje. Para mudar, use o módulo de Promoções.</p>
			@if($promocoes->isEmpty())
				<div class="small text-muted">Nenhuma promoção vigente — a seção fica escondida no site.</div>
			@else
				<ul class="small mb-0">
					@foreach($promocoes as $pr)
						<li>#{{ $pr->produto_id }} {{ $pr->nome }} — até {{ \Carbon\Carbon::parse($pr->data_fim)->format('d/m/Y') }}</li>
					@endforeach
				</ul>
			@endif
		</div>
	</div>
</div>
@endsection
