@extends('default.layout',['title' => 'Notificações'])
@section('content')
@php
	$cores = ['pendente' => 'warning text-dark', 'enviado' => 'success', 'erro' => 'danger', 'ignorado' => 'secondary'];
	$modelos = ['cadastro_recebido', 'cadastro_aprovado', 'pedido_recebido', 'produto_disponivel', 'senha_recuperar', 'pedido_confirmado', 'nota_emitida'];
	$fone = function ($t) {
		$d = preg_replace('/\D+/', '', (string) $t);
		if (str_starts_with($d, '55') && strlen($d) >= 12) { $d = substr($d, 2); }
		if (strlen($d) === 11) { return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 5) . '-' . substr($d, 7); }
		if (strlen($d) === 10) { return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 4) . '-' . substr($d, 6); }
		return $t;
	};
@endphp
<div class="page-content">
	<div class="card">
		<div class="card-body p-4">
			<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
				<div>
					<h6 class="mb-1 text-uppercase">Notificações</h6>
					<p class="small text-muted mb-0">E-mails e WhatsApp automáticos para os clientes. Saem sozinhos a cada minuto; se falhar, o sistema tenta de novo.</p>
				</div>
				<form method="post" action="{{ route('notificacoes-site.teste') }}" class="d-flex gap-2">
					@csrf
					<input type="email" name="email" class="form-control form-control-sm" placeholder="E-mail para teste" required style="min-width:220px">
					<button class="btn btn-sm btn-primary text-nowrap"><i class="bx bx-send"></i> Enviar teste</button>
				</form>
			</div>

			<div class="row g-2 mb-3">
				@foreach(['enviado' => 'Enviados', 'pendente' => 'Na fila', 'erro' => 'Com erro', 'ignorado' => 'Ignorados'] as $st => $rot)
				<div class="col-6 col-md-3">
					<div class="border rounded p-2 text-center">
						<div class="small text-muted">{{ $rot }} (7 dias)</div>
						<div class="fs-5 fw-bold">{{ (int) ($resumo[$st] ?? 0) }}</div>
					</div>
				</div>
				@endforeach
			</div>

			<p class="small mb-3">
				Ver modelos:
				@foreach($modelos as $ev)
					<a href="{{ route('notificacoes-site.preview', $ev) }}" target="_blank" class="me-2">{{ \App\Services\NotificacaoModelos::rotulo($ev) }}</a>
				@endforeach
			</p>

			<form method="get" class="row g-2 align-items-end mb-3">
				<div class="col-md-3">
					<label class="form-label small">Situação</label>
					<select name="status" class="form-select form-select-sm">
						<option value="">Todas</option>
						@foreach(['pendente','enviado','erro','ignorado'] as $st)
							<option value="{{ $st }}" @selected(request('status') === $st)>{{ ucfirst($st) }}</option>
						@endforeach
					</select>
				</div>
				<div class="col-md-3">
					<label class="form-label small">Tipo</label>
					<select name="evento" class="form-select form-select-sm">
						<option value="">Todos</option>
						@foreach($eventos as $ev)
							<option value="{{ $ev }}" @selected(request('evento') === $ev)>{{ \App\Services\NotificacaoModelos::rotulo($ev) }}</option>
						@endforeach
					</select>
				</div>
				<div class="col-md-4">
					<label class="form-label small">Buscar</label>
					<input type="text" name="busca" value="{{ request('busca') }}" class="form-control form-control-sm" placeholder="Cliente, e-mail, telefone ou nº do pedido">
				</div>
				<div class="col-md-2">
					<button class="btn btn-sm btn-outline-primary w-100"><i class="bx bx-search"></i> Filtrar</button>
				</div>
			</form>

			<div class="table-responsive">
				<table class="table table-sm align-middle">
					<thead>
						<tr>
							<th>#</th>
							<th>Criado em</th>
							<th>Tipo</th>
							<th>Para</th>
							<th>Situação</th>
							<th>Enviado em</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						@forelse($itens as $n)
						<tr>
							<td class="text-muted">{{ $n->id }}</td>
							<td class="text-nowrap">{{ \Carbon\Carbon::parse($n->created_at)->format('d/m/Y H:i') }}</td>
							<td>{{ \App\Services\NotificacaoModelos::rotulo($n->evento) }}</td>
							<td>
								@php
									$dd = json_decode((string) $n->dados, true) ?: [];
									$vendaId = str_starts_with((string) $n->origem, 'venda#') ? (int) substr($n->origem, 6) : (int) (is_numeric($dd['pedido'] ?? null) ? $dd['pedido'] : 0);
								@endphp
								@if($n->cliente_nome)
									<div class="fw-semibold">{{ $n->cliente_nome }}</div>
								@endif
								<div class="small text-muted">
									@if($n->telefone && !str_ends_with((string) $n->telefone, '@g.us'))
										<i class="bx bxl-whatsapp text-success"></i> {{ $fone($n->telefone) }}
									@elseif(str_ends_with((string) $n->telefone, '@g.us'))
										<i class="bx bxl-whatsapp text-success"></i> grupo de comprovantes
									@endif
									@if($n->email)
										<i class="bx bx-envelope"></i> {{ $n->email }}
									@endif
									@if(!$n->telefone && !$n->email) — @endif
								</div>
								@if($vendaId)
									<a href="{{ route('vendas.show', $vendaId) }}" class="small">Pedido #{{ $vendaId }}</a>
								@elseif(!empty($dd['numero']))
									<span class="small text-muted">NF-e {{ $dd['numero'] }}</span>
								@endif
							</td>
							<td>
								<span class="badge bg-{{ $cores[$n->status] ?? 'secondary' }}">{{ ucfirst($n->status) }}</span>
								@if($n->status === 'pendente' && $n->tentativas > 0)
									<span class="small text-muted">tentativa {{ $n->tentativas }}</span>
								@endif
								@if($n->erro)
									<div class="small text-danger text-break" style="max-width:380px">{{ \Illuminate\Support\Str::limit($n->erro, 160) }}</div>
								@endif
							</td>
							<td class="text-nowrap">{{ $n->enviado_em ? \Carbon\Carbon::parse($n->enviado_em)->format('d/m/Y H:i') : '—' }}</td>
							<td class="text-end">
								@if(in_array($n->status, ['erro','enviado','ignorado']) && !in_array($n->evento, \App\Services\NotificacaoModelos::SEM_REENVIO))
								<form method="post" action="{{ route('notificacoes-site.reenviar', $n->id) }}" class="d-inline">
									@csrf
									<button class="btn btn-sm btn-outline-secondary" title="Enviar de novo"><i class="bx bx-refresh"></i></button>
								</form>
								@endif
							</td>
						</tr>
						@empty
						<tr><td colspan="7" class="text-center text-muted py-4">Nenhuma notificação ainda.</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
			{{ $itens->links() }}
		</div>
	</div>
</div>
@endsection
