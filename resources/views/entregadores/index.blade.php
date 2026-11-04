@extends('default.layout', ['title' => 'Motoboys e acertos'])
@section('content')
@php $m = fn ($v) => 'R$ ' . __moeda($v); @endphp
<div class="page-content">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h5 class="mb-0">Motoboys e acertos</h5>
            <div class="small text-muted">Ao finalizar a rota, o frete entra sozinho no acerto do motoboy e vira conta a pagar: <b>semanal</b> (sábado a sexta, vence no sábado) ou <b>avulso</b> (vence no mesmo dia).</div>
        </div>
        <a href="{{ route('rotas-entrega.index') }}" class="btn btn-light"><i class="bx bx-arrow-back"></i> Rotas de entrega</a>
    </div>

    @if($semAcerto)
    <div class="alert alert-warning d-flex justify-content-between align-items-center py-2">
        <span>{{ $semAcerto }} rota(s) finalizada(s) com motoboy ainda fora de um acerto.</span>
        <form method="post" action="{{ route('entregadores.gerar') }}">@csrf<button class="btn btn-sm btn-warning">Colocar no acerto</button></form>
    </div>
    @endif

    @if($rotasSemMotoboy->isNotEmpty() && $motoboys->where('ativo', true)->isNotEmpty())
    <div class="card mb-3 border-warning"><div class="card-body">
        <h6>Rotas finalizadas sem motoboy da lista <span class="small text-muted fw-normal">(últimos 45 dias, ainda não pagas)</span></h6>
        <p class="small text-muted">Escolha o motoboy de cada uma e clique em "Colocar no acerto": o frete entra no acerto da semana (ou do dia) daquela rota.</p>
        <form method="post" action="{{ route('entregadores.vincular') }}">
            @csrf
            <div class="table-responsive"><table class="table table-sm align-middle mb-2">
                <thead><tr><th>Rota</th><th>Data</th><th>Motoboy digitado</th><th class="text-end">Entregas</th><th class="text-end">Frete</th><th style="width:240px">Motoboy</th></tr></thead>
                <tbody>
                @foreach($rotasSemMotoboy as $r)
                    @php $sugerido = $motoboys->first(fn ($mb) => $mb->ativo && $r->motoboy_nome && mb_strtolower(trim($mb->nome)) === mb_strtolower(trim($r->motoboy_nome))); @endphp
                    <tr>
                        <td><a href="{{ route('rotas-entrega.show', $r->id) }}" target="_blank">#{{ $r->id }}</a></td>
                        <td class="small">{{ $r->created_at->format('d/m/Y') }} <span class="text-muted">({{ $r->created_at->locale('pt_BR')->isoFormat('ddd') }})</span></td>
                        <td class="small">{{ $r->motoboy_nome ?: '—' }}</td>
                        <td class="text-end small">{{ $r->itens->where('status_entrega', 'entregue')->count() }}/{{ $r->itens->count() }}</td>
                        <td class="text-end small">{{ $m($r->itens->where('status_entrega', 'entregue')->sum('frete')) }}</td>
                        <td><select name="rota[{{ $r->id }}]" class="form-select form-select-sm">
                            <option value="">— não colocar —</option>
                            @foreach($motoboys->where('ativo', true) as $mb)<option value="{{ $mb->id }}" @selected($sugerido && $sugerido->id === $mb->id)>{{ $mb->nome }} ({{ $mb->acerto === 'diario' ? 'avulso' : 'semanal' }})</option>@endforeach
                        </select></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="d-flex justify-content-end"><button class="btn btn-warning btn-sm">Colocar no acerto</button></div>
        </form>
    </div></div>
    @endif

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
@endsection
