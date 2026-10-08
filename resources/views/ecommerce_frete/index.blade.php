@extends('default.layout',['title' => 'Frete por Bairro'])
@section('content')
<div class="page-content">
	<div class="card">
		<div class="card-body p-4">
			<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
				<div>
					<h6 class="mb-1 text-uppercase">Frete por bairro (Site)</h6>
					<p class="small text-muted mb-0">Valores usados no checkout. Bairros sem cadastro ficam como <strong>a combinar</strong>.</p>
				</div>
			</div>

			{!! Form::open()->fill(request()->all())->get() !!}
			<div class="row align-items-end">
				<div class="col-md-4">
					{!! Form::text('q', 'Buscar bairro / cidade') !!}
				</div>
				<div class="col-md-3">
					<label class="form-label">Status</label>
					<select name="active" class="form-select">
						<option value="">Todos</option>
						<option value="1" @if(request('active')==='1') selected @endif>Ativos</option>
						<option value="0" @if(request('active')==='0') selected @endif>Inativos</option>
					</select>
				</div>
				<div class="col-md-3">
					<button class="btn btn-primary" type="submit"><i class="bx bx-search"></i> Pesquisar</button>
					<a class="btn btn-danger" href="{{ route('ecommerce-frete.index') }}"><i class="bx bx-eraser"></i> Limpar</a>
				</div>
			</div>
			{!! Form::close() !!}

			<hr/>

			<div class="card border mb-4">
				<div class="card-body">
					<h6 class="mb-3">Novo bairro</h6>
					<form method="post" action="{{ route('ecommerce-frete.store') }}">
						@csrf
						<div class="row g-2 align-items-end">
							<div class="col-md-3">
								<label class="form-label">Bairro *</label>
								<input type="text" name="neighborhood" class="form-control" required maxlength="120" value="{{ old('neighborhood') }}">
							</div>
							<div class="col-md-2">
								<label class="form-label">Cidade</label>
								<input type="text" name="city" class="form-control" value="{{ old('city', 'João Pessoa') }}" maxlength="120">
							</div>
							<div class="col-md-1">
								<label class="form-label">UF</label>
								<input type="text" name="state" class="form-control" value="{{ old('state', 'PB') }}" maxlength="2">
							</div>
							<div class="col-md-2">
								<label class="form-label">Frete (R$) *</label>
								<input type="text" name="shipping_price" class="form-control moeda" required value="{{ old('shipping_price') }}" placeholder="0,00">
							</div>
							<div class="col-md-2">
								<label class="form-label">Obs.</label>
								<input type="text" name="notes" class="form-control" maxlength="255" value="{{ old('notes') }}">
							</div>
							<div class="col-md-2">
								<div class="form-check mb-2">
									<input type="hidden" name="active" value="0">
									<input type="checkbox" class="form-check-input" name="active" value="1" id="novo-active" checked>
									<label class="form-check-label" for="novo-active">Ativo</label>
								</div>
								<button type="submit" class="btn btn-success w-100"><i class="bx bx-plus"></i> Adicionar</button>
							</div>
						</div>
					</form>
				</div>
			</div>

			<div class="table-responsive">
				<table class="table table-striped mb-0 align-middle">
					<thead>
						<tr>
							<th>Bairro</th>
							<th>Cidade/UF</th>
							<th>Frete</th>
							<th>Obs.</th>
							<th>Status</th>
							<th style="min-width:280px"></th>
						</tr>
					</thead>
					<tbody>
						@forelse($data as $item)
						<tr>
							<td colspan="6" class="p-0">
								<form method="post" action="{{ route('ecommerce-frete.update', $item->id) }}" class="p-2">
									@csrf
									@method('PUT')
									<div class="row g-2 align-items-center">
										<div class="col-md-2">
											<input type="text" name="neighborhood" class="form-control form-control-sm" required value="{{ $item->neighborhood }}">
										</div>
										<div class="col-md-2">
											<div class="input-group input-group-sm">
												<input type="text" name="city" class="form-control" value="{{ $item->city }}">
												<input type="text" name="state" class="form-control" style="max-width:56px" value="{{ $item->state }}" maxlength="2">
											</div>
										</div>
										<div class="col-md-2">
											<input type="text" name="shipping_price" class="form-control form-control-sm moeda" required value="{{ number_format($item->shipping_price, 2, ',', '.') }}">
										</div>
										<div class="col-md-2">
											<input type="text" name="notes" class="form-control form-control-sm" value="{{ $item->notes }}">
										</div>
										<div class="col-md-1">
											<input type="hidden" name="active" value="0">
											<div class="form-check">
												<input type="checkbox" class="form-check-input" name="active" value="1" id="active-{{ $item->id }}" {{ $item->active ? 'checked' : '' }}>
												<label class="form-check-label" for="active-{{ $item->id }}">Ativo</label>
											</div>
										</div>
										<div class="col-md-3 text-end">
											<button type="submit" class="btn btn-primary btn-sm"><i class="bx bx-save"></i> Salvar</button>
											<a href="{{ route('ecommerce-frete.toggle', $item->id) }}" class="btn btn-outline-secondary btn-sm">
												{{ $item->active ? 'Desativar' : 'Ativar' }}
											</a>
										</div>
									</div>
								</form>
								<form method="post" action="{{ route('ecommerce-frete.destroy', $item->id) }}" class="d-inline px-2 pb-2" onsubmit="return confirm('Remover este bairro?');">
									@csrf
									@method('DELETE')
									<button type="submit" class="btn btn-outline-danger btn-sm"><i class="bx bx-trash"></i> Remover</button>
								</form>
							</td>
						</tr>
						@empty
						<tr><td colspan="6" class="text-center py-4">Nenhum bairro cadastrado</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
			{!! $data->appends(request()->all())->links() !!}
		</div>
	</div>
</div>
@endsection
