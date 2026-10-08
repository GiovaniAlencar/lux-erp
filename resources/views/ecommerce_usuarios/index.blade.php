@extends('default.layout',['title' => 'Usuários Site'])
@section('content')
<div class="page-content">
	<div class="card">
		<div class="card-body p-4">
			<h6 class="mb-3 text-uppercase">Usuários do Site (Catálogo)</h6>

			{!! Form::open()->fill(request()->all())->get() !!}
			<div class="row align-items-end">
				<div class="col-md-4">
					{!! Form::text('q', 'Buscar (nome, e-mail, telefone)') !!}
				</div>
				<div class="col-md-3">
					<label class="form-label">Status</label>
					<select name="status" class="form-select">
						<option value="">Todos</option>
						@foreach($statusLabels as $k => $lbl)
							<option value="{{ $k }}" @if(request('status')===$k) selected @endif>{{ $lbl }}</option>
						@endforeach
					</select>
				</div>
				<div class="col-md-3">
					<button class="btn btn-primary" type="submit"><i class="bx bx-search"></i> Pesquisar</button>
					<a class="btn btn-danger" href="{{ route('ecommerce-usuarios.index') }}"><i class="bx bx-eraser"></i> Limpar</a>
				</div>
			</div>
			{!! Form::close() !!}

			<hr/>
			<div class="table-responsive">
				<table class="table table-striped mb-0">
					<thead>
						<tr>
							<th>Nome</th>
							<th>E-mail</th>
							<th>Telefone</th>
							<th>Status</th>
							<th>Cliente ERP</th>
							<th>Cadastro</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						@forelse($data as $item)
						<tr>
							<td>{{ $item->name }}</td>
							<td>{{ $item->email }}</td>
							<td>{{ $item->phone }}</td>
							<td>
								@php $st = $item->status; @endphp
								<span class="badge bg-{{ $st==='pending'?'warning':($st==='active'?'success':($st==='rejected'?'danger':'secondary')) }}">
									{{ $statusLabels[$st] ?? $st }}
								</span>
							</td>
							<td>
								@if($item->cliente)
									#{{ $item->erp_cliente_id }} — {{ $item->cliente->razao_social }}
								@else
									—
								@endif
							</td>
							<td>{{ $item->created_at ? __data_pt($item->created_at, 1) : '—' }}</td>
							<td>
								<a href="{{ route('ecommerce-usuarios.show', $item->id) }}" class="btn btn-primary btn-sm">
									<i class="bx bx-show"></i> Abrir
								</a>
							</td>
						</tr>
						@empty
						<tr><td colspan="7" class="text-center">Nenhum usuário encontrado</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
			{!! $data->appends(request()->all())->links() !!}
		</div>
	</div>
</div>
@endsection
