@extends('default.layout',['title' => 'Usuário Site'])
@section('content')
<div class="page-content">
	<div class="card mb-3">
		<div class="card-body p-4">
			<div class="d-flex justify-content-between align-items-start mb-3">
				<div>
					<h5 class="mb-1">{{ $item->name }}</h5>
					<div class="text-muted">{{ $item->email }} · {{ $item->phone }}</div>
					<div class="mt-2">
						<span class="badge bg-{{ $item->status==='pending'?'warning':($item->status==='active'?'success':($item->status==='rejected'?'danger':'secondary')) }}">
							{{ $statusLabels[$item->status] ?? $item->status }}
						</span>
						@if($item->erp_cliente_id)
							<span class="badge bg-info">Cliente ERP #{{ $item->erp_cliente_id }}</span>
						@endif
					</div>
				</div>
				<a href="{{ route('ecommerce-usuarios.index') }}" class="btn btn-outline-secondary btn-sm">Voltar</a>
			</div>

			@if($item->cliente)
				<p class="mb-0"><strong>Vinculado:</strong> {{ $item->cliente->razao_social }}
					@if($item->cliente->telefone) · {{ $item->cliente->telefone }} @endif
				</p>
			@endif
			@if($item->rejection_reason)
				<p class="text-danger mt-2 mb-0"><strong>Motivo reprovação:</strong> {{ $item->rejection_reason }}</p>
			@endif
		</div>
	</div>

	<div class="row">
		<div class="col-lg-6">
			<div class="card mb-3">
				<div class="card-body p-4">
					<h6 class="text-uppercase mb-3">Aprovar / Vincular cliente</h6>

					@if($sugestoes->count())
						<p class="small text-muted">Sugestões por telefone/e-mail/nome:</p>
						<ul class="list-group mb-3">
							@foreach($sugestoes as $c)
							<li class="list-group-item d-flex justify-content-between align-items-center">
								<span>#{{ $c->id }} — {{ $c->razao_social }} <small class="text-muted">{{ $c->telefone ?: $c->celular }}</small></span>
								<form method="post" action="{{ route('ecommerce-usuarios.aprovar', $item->id) }}">
									@csrf
									<input type="hidden" name="erp_cliente_id" value="{{ $c->id }}">
									<button class="btn btn-success btn-sm" type="submit">Usar este</button>
								</form>
							</li>
							@endforeach
						</ul>
					@endif

					<form method="post" action="{{ route('ecommerce-usuarios.aprovar', $item->id) }}" class="mb-3">
						@csrf
						<div class="mb-2">
							<label class="form-label">ID do cliente ERP</label>
							<input type="number" name="erp_cliente_id" class="form-control" value="{{ old('erp_cliente_id', $item->erp_cliente_id) }}" required>
							<small class="text-muted">Busque em Cadastros → Clientes e informe o ID aqui.</small>
						</div>
						<div class="mb-2">
							<label class="form-label">Observação interna</label>
							<textarea name="admin_notes" class="form-control" rows="2">{{ old('admin_notes', $item->admin_notes) }}</textarea>
						</div>
						<button type="submit" class="btn btn-success"><i class="bx bx-check"></i> Aprovar e vincular</button>
					</form>

					<hr>
					<h6 class="mb-2">Criar cliente novo (raro)</h6>
					<form method="post" action="{{ route('ecommerce-usuarios.criar-cliente', $item->id) }}">
						@csrf
						<div class="row">
							<div class="col-md-6 mb-2">
								<input type="text" name="cpf_cnpj" class="form-control" placeholder="CPF/CNPJ (opcional)">
							</div>
							<div class="col-md-6 mb-2">
								<input type="text" name="cep" class="form-control" placeholder="CEP">
							</div>
							<div class="col-md-8 mb-2">
								<input type="text" name="rua" class="form-control" placeholder="Rua">
							</div>
							<div class="col-md-4 mb-2">
								<input type="text" name="numero" class="form-control" placeholder="Nº">
							</div>
							<div class="col-md-6 mb-2">
								<input type="text" name="bairro" class="form-control" placeholder="Bairro">
							</div>
							<div class="col-md-6 mb-2">
								<input type="number" name="cidade_id" class="form-control" value="1338" placeholder="ID cidade (1338 = JP)">
							</div>
						</div>
						<button type="submit" class="btn btn-outline-primary" onclick="return confirm('Criar cliente e aprovar este usuário?')">
							Criar cliente e aprovar
						</button>
					</form>
				</div>
			</div>
		</div>

		<div class="col-lg-6">
			<div class="card mb-3">
				<div class="card-body p-4">
					<h6 class="text-uppercase mb-3">Alterar senha (catálogo)</h6>
					<form method="post" action="{{ route('ecommerce-usuarios.alterar-senha', $item->id) }}">
						@csrf
						<div class="mb-2">
							<label class="form-label">Nova senha</label>
							<input type="password" name="password" class="form-control" required minlength="6">
						</div>
						<div class="mb-2">
							<label class="form-label">Confirmar senha</label>
							<input type="password" name="password_confirmation" class="form-control" required minlength="6">
						</div>
						<button type="submit" class="btn btn-warning"><i class="bx bx-key"></i> Salvar senha</button>
					</form>
				</div>
			</div>

			<div class="card mb-3">
				<div class="card-body p-4">
					<h6 class="text-uppercase mb-3">Outras ações</h6>
					<form method="post" action="{{ route('ecommerce-usuarios.reprovar', $item->id) }}" class="mb-3">
						@csrf
						<input type="text" name="rejection_reason" class="form-control mb-2" placeholder="Motivo da reprovação">
						<button type="submit" class="btn btn-danger" onclick="return confirm('Reprovar este cadastro?')">Reprovar</button>
					</form>
					@if($item->status === 'active')
						<form method="post" action="{{ route('ecommerce-usuarios.bloquear', $item->id) }}" class="d-inline">
							@csrf
							<button type="submit" class="btn btn-outline-danger">Bloquear</button>
						</form>
					@elseif(in_array($item->status, ['blocked','inactive','rejected'], true) && $item->erp_cliente_id)
						<form method="post" action="{{ route('ecommerce-usuarios.reativar', $item->id) }}" class="d-inline">
							@csrf
							<button type="submit" class="btn btn-outline-success">Reativar</button>
						</form>
					@endif
				</div>
			</div>
		</div>
	</div>
</div>
@endsection
