@extends('default.layout', ['title' => 'Motoboys e acertos'])
@section('content')
@php $m = fn ($v) => 'R$ ' . __moeda($v); @endphp
<div class="page-content">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h5 class="mb-0">Motoboys e acertos</h5>
            <div class="small text-muted">Ao finalizar a rota, o frete entra sozinho no acerto do motoboy e vira conta a pagar: <b>semanal</b> (sábado a sexta, vence no sábado) ou <b>avulso</b> (vence no mesmo dia). Para rotas antigas ou um acerto diferente, use <b>Novo acerto</b>.</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('rotas-entrega.index') }}" class="btn btn-light"><i class="bx bx-arrow-back"></i> Rotas de entrega</a>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-novo-acerto" @disabled($motoboys->where('ativo', true)->isEmpty())><i class="bx bx-plus"></i> Novo acerto</button>
        </div>
    </div>


    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card mb-3"><div class="card-body">
                <h6>A pagar</h6>
                @forelse($abertos as $a)
                    <a href="{{ route('entregadores.acerto', $a->id) }}" class="d-flex justify-content-between align-items-center border-top py-2 text-decoration-none text-body">
                        <span><b>{{ optional($a->motoboy)->nome }}</b> <span class="badge bg-light text-dark border">{{ $a->tipo === 'diario' ? 'avulso' : 'semanal' }}</span>
                            <div class="small text-muted">{{ $a->periodoTexto() }} · vence {{ $a->vencimento->format('d/m/Y') }}{{ $a->vencimento->isPast() && !$a->vencimento->isToday() ? ' (vencido)' : '' }}</div></span>
                        <span class="text-end"><b>{{ $m($a->total()) }}</b><div class="small text-muted">fretes {{ $m($a->valor_fretes) }}{{ $a->valor_extras > 0 ? ' + extras ' . $m($a->valor_extras) : '' }}</div></span>
                    </a>
                @empty
                    <p class="text-muted small mb-0">Nada a pagar aos motoboys.</p>
                @endforelse
            </div></div>
            <div class="card"><div class="card-body">
                <h6>Pagos recentemente</h6>
                @forelse($pagos as $a)
                    <a href="{{ route('entregadores.acerto', $a->id) }}" class="d-flex justify-content-between border-top py-1 small text-decoration-none text-body">
                        <span>{{ optional($a->motoboy)->nome }} · {{ $a->periodoTexto() }}</span>
                        <span>{{ $m($a->total()) }} · pago {{ optional($a->pago_em)->format('d/m/Y') }}</span>
                    </a>
                @empty
                    <p class="text-muted small mb-0">Nenhum ainda.</p>
                @endforelse
            </div></div>
        </div>
        <div class="col-lg-5">
            <div class="card"><div class="card-body">
                <h6>Motoboys cadastrados</h6>
                @foreach($motoboys as $mb)
                    <form method="post" action="{{ route('entregadores.salvar') }}" class="row g-1 align-items-center border-top py-2 {{ $mb->ativo ? '' : 'opacity-50' }}">
                        @csrf <input type="hidden" name="id" value="{{ $mb->id }}">
                        <div class="col-5"><input name="nome" class="form-control form-control-sm" value="{{ $mb->nome }}" required></div>
                        <div class="col-4"><select name="acerto" class="form-select form-select-sm">@foreach(\App\Models\Entregador::ACERTOS as $k => $v)<option value="{{ $k }}" @selected($mb->acerto === $k)>{{ $k === 'diario' ? 'Avulso' : 'Semanal' }}</option>@endforeach</select></div>
                        <div class="col-3 d-flex gap-1 align-items-center">
                            <input type="hidden" name="ativo" value="0"><input type="checkbox" class="form-check-input" name="ativo" value="1" @checked($mb->ativo) title="Ativo">
                            <button class="btn btn-sm btn-outline-secondary" title="Salvar"><i class="bx bx-save"></i></button>
                        </div>
                        <div class="col-12"><input name="telefone" class="form-control form-control-sm" value="{{ $mb->telefone }}" placeholder="Telefone (opcional)"></div>
                    </form>
                @endforeach
                <form method="post" action="{{ route('entregadores.salvar') }}" class="row g-1 align-items-center border-top pt-2 mt-1">
                    @csrf
                    <div class="col-12 small fw-semibold">Novo motoboy</div>
                    <div class="col-5"><input name="nome" class="form-control form-control-sm" placeholder="Nome" required></div>
                    <div class="col-4"><select name="acerto" class="form-select form-select-sm"><option value="semanal">Semanal</option><option value="diario">Avulso</option></select></div>
                    <div class="col-3"><button class="btn btn-sm btn-primary w-100">Adicionar</button></div>
                    <div class="col-12"><input name="telefone" class="form-control form-control-sm" placeholder="Telefone (opcional)"></div>
                </form>
                <div class="small text-muted mt-2"><b>Semanal:</b> o fixo — rotas de sábado a sexta, paga no sábado. <b>Avulso:</b> recebe no mesmo dia.</div>
            </div></div>
        </div>
    </div>
</div>
{{-- Novo acerto à mão: escolhe o motoboy, as rotas e o vencimento --}}
<div class="modal fade" id="modal-novo-acerto" tabindex="-1"><div class="modal-dialog modal-lg"><form class="modal-content" method="post" action="{{ route('entregadores.novo') }}">
    @csrf
    <div class="modal-header"><h5 class="modal-title">Novo acerto</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="row g-2 mb-3">
            <div class="col-md-7"><label class="form-label">Motoboy</label>
                <select name="entregador_id" class="form-select" required><option value="">— escolha —</option>@foreach($motoboys->where('ativo', true) as $mb)<option value="{{ $mb->id }}">{{ $mb->nome }} ({{ $mb->acerto === 'diario' ? 'avulso' : 'semanal' }})</option>@endforeach</select></div>
            <div class="col-md-5"><label class="form-label">Vencimento</label><input type="date" name="vencimento" class="form-control" value="{{ date('Y-m-d') }}" required></div>
        </div>
        <label class="form-label mb-1">Rotas que entram <span class="small text-muted">(finalizadas, ainda não pagas e fora de outro acerto)</span></label>
        @if($rotasLivres->isEmpty())
            <p class="small text-muted mb-0">Nenhuma rota livre nos últimos 60 dias.</p>
        @else
        <div class="table-responsive" style="max-height:340px;overflow:auto"><table class="table table-sm align-middle mb-1">
            <thead><tr><th></th><th>Rota</th><th>Data</th><th>Motoboy digitado</th><th class="text-end">Entregas</th><th class="text-end">Frete</th></tr></thead>
            <tbody>
            @foreach($rotasLivres as $r)
                <tr>
                    <td><input type="checkbox" class="form-check-input na-rota" name="rotas[]" value="{{ $r->id }}" data-frete="{{ $r->totalFrete() }}"></td>
                    <td><a href="{{ route('rotas-entrega.show', $r->id) }}" target="_blank">#{{ $r->id }}</a></td>
                    <td class="small">{{ $r->created_at->format('d/m/Y') }}</td>
                    <td class="small">{{ $r->motoboy_nome ?: '—' }}</td>
                    <td class="text-end small">{{ $r->itens->count() }}</td>
                    <td class="text-end small">{{ $m($r->totalFrete()) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <div class="text-end fw-semibold">Fretes marcados: <span id="soma-rotas">R$ 0,00</span></div>
        @endif
        <div class="small text-muted mt-2">Os extras (2ª caixa, entrega que deu errado…) você lança depois, dentro do acerto.</div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button><button class="btn btn-primary">Criar acerto</button></div>
</form></div></div>
@endsection
@section('js')
<script>
    document.querySelectorAll('.na-rota').forEach((c) => c.addEventListener('change', () => {
        let t = 0; document.querySelectorAll('.na-rota:checked').forEach((x) => (t += parseFloat(x.dataset.frete || 0)));
        document.getElementById('soma-rotas').textContent = 'R$ ' + t.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }));
</script>
@endsection
