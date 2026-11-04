@extends('default.layout', ['title' => 'Acerto do motoboy'])
@section('content')
@php $m = fn ($v) => 'R$ ' . __moeda($v); $aberto = $a->status === 'aberto'; @endphp
<div class="page-content">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h5 class="mb-1">Acerto · {{ optional($a->motoboy)->nome }}
                <span class="badge bg-{{ $aberto ? 'warning text-dark' : 'success' }} align-middle">{{ $aberto ? 'A pagar' : 'Pago em ' . optional($a->pago_em)->format('d/m/Y') }}</span></h5>
            <div class="small text-muted">{{ $a->tipo === 'diario' ? 'Avulso' : 'Semanal' }} · {{ $a->periodoTexto() }} · vence {{ $a->vencimento->format('d/m/Y') }}</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('entregadores.index') }}" class="btn btn-light"><i class="bx bx-arrow-back"></i> Motoboys</a>
            @if($aberto)
            <form method="post" action="{{ route('entregadores.excluir', $a->id) }}" onsubmit="return confirm('Excluir este acerto? As contas a pagar dele são apagadas e as rotas ficam livres para outro acerto.');">
                @csrf @method('DELETE') <button class="btn btn-outline-danger"><i class="bx bx-trash"></i> Excluir acerto</button>
            </form>
            @endif
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="small text-muted text-uppercase">Repasse de fretes</div><div class="fs-4 fw-bold">{{ $m($a->valor_fretes) }}</div><div class="small text-muted">não é despesa (o cliente pagou)</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="small text-muted text-uppercase">Extras</div><div class="fs-4 fw-bold text-danger">{{ $m($a->valor_extras) }}</div><div class="small text-muted">entra como despesa</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card h-100 border-primary"><div class="card-body"><div class="small text-muted text-uppercase">Total a pagar</div><div class="fs-4 fw-bold">{{ $m($a->total()) }}</div><div class="small text-muted">{{ $a->rotas->count() }} rota(s)</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body small">
            <div class="text-muted text-uppercase">Contas a pagar</div>
            @foreach([$a->conta_fretes_id => 'Fretes', $a->conta_extras_id => 'Extras'] as $tid => $rot)
                @if($tid && ($t = $titulos[$tid] ?? null))
                    <div>{{ $rot }}: {{ $m($t->valor_integral) }} · {{ (int) $t->status === 1 ? 'pago' : ((float) $t->valor_pago > 0 ? 'pago em parte' : 'em aberto') }}</div>
                @endif
            @endforeach
            <a href="{{ route('conta-pagar.index') }}" class="d-inline-block mt-1">Ver em Contas a pagar</a>
        </div></div></div>
    </div>

    <form method="post" action="{{ route('entregadores.extras', $a->id) }}">
        @csrf
        <div class="card mb-3"><div class="card-body">
            <h6>Entregas</h6>
            <p class="small text-muted">O repasse é a soma dos fretes das rotas (o mesmo valor de "Pagamento ao motoboy" na rota). Use <b>extra</b> para o que o motoboy recebe além do frete do pedido (2ª caixa, entrega que deu errado, volta…).</p>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>Rota</th><th>Cliente</th><th>Situação</th><th class="text-end">Frete</th><th style="width:130px">Extra (R$)</th><th style="min-width:200px">Motivo do extra</th></tr></thead>
                <tbody>
                @foreach($a->rotas as $r)
                    @foreach($r->itens as $i)
                        <tr>
                            <td class="small text-nowrap"><a href="{{ route('rotas-entrega.show', $r->id) }}" target="_blank">#{{ $r->id }}</a> · {{ $r->created_at->format('d/m') }}
                                @if($aberto && $loop->first)<button type="submit" form="tirar-{{ $r->id }}" class="btn btn-link btn-sm text-danger p-0 ms-1" title="Tirar esta rota do acerto" onclick="return confirm('Tirar a rota #{{ $r->id }} deste acerto?');"><i class="bx bx-x-circle"></i></button>@endif</td>
                            <td class="small">{{ $i->cliente_nome }} <span class="text-muted">· pedido #{{ $i->venda_id }}</span></td>
                            <td class="small">{{ $i->status_entrega === 'entregue' ? 'Entregue' : ($i->status_entrega === 'ocorrencia' ? 'Ocorrência' : ucfirst((string) $i->status_entrega)) }}</td>
                            <td class="text-end small">{{ $m($i->frete) }}</td>
                            <td><input name="extra[{{ $i->id }}][valor]" inputmode="decimal" class="form-control form-control-sm" value="{{ $i->motoboy_extra ? __moeda($i->motoboy_extra) : '' }}" @disabled(!$aberto)></td>
                            <td><input name="extra[{{ $i->id }}][motivo]" class="form-control form-control-sm" value="{{ $i->motoboy_extra_motivo }}" placeholder="Ex.: 2ª caixa, voltou sem entregar" list="motivos-extra" @disabled(!$aberto)></td>
                        </tr>
                    @endforeach
                @endforeach
                </tbody>
            </table></div>
            <datalist id="motivos-extra"><option value="2ª caixa"><option value="Entrega que deu errado"><option value="Voltou sem entregar"><option value="Entrega longe"></datalist>
            @if($aberto)<div class="d-flex justify-content-end mt-2"><button class="btn btn-outline-primary btn-sm"><i class="bx bx-save"></i> Salvar extras</button></div>@endif
        </div></div>
    </form>

    {{-- formulários de tirar rota (fora do formulário de extras) --}}
    @if($aberto)
        @foreach($a->rotas as $r)
            <form id="tirar-{{ $r->id }}" method="post" action="{{ route('entregadores.rota.remover', [$a->id, $r->id]) }}" class="d-none">@csrf @method('DELETE')</form>
        @endforeach
        @if($rotasLivres->isNotEmpty())
        <div class="card mb-3"><div class="card-body">
            <form method="post" action="{{ route('entregadores.rota.adicionar', $a->id) }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-8"><label class="form-label small mb-0">Adicionar rota a este acerto</label>
                    <select name="rota_id" class="form-select" required><option value="">— escolha a rota —</option>
                        @foreach($rotasLivres as $r)<option value="{{ $r->id }}">#{{ $r->id }} · {{ $r->created_at->format('d/m/Y') }} · {{ $r->motoboy_nome ?: 'sem motoboy' }} · {{ $r->itens->count() }} entrega(s) · frete {{ $m($r->totalFrete()) }}</option>@endforeach
                    </select></div>
                <div class="col-md-4"><button class="btn btn-outline-primary w-100"><i class="bx bx-plus"></i> Adicionar rota</button></div>
            </form>
        </div></div>
        @endif
    @endif

    @if($aberto && $a->total() > 0)
    <div class="card"><div class="card-body">
        <h6>Pagar o acerto</h6>
        <form method="post" action="{{ route('entregadores.pagar', $a->id) }}" class="row g-2 align-items-end" onsubmit="return confirm('Confirmar o pagamento de {{ $m($a->total()) }} a {{ addslashes(optional($a->motoboy)->nome) }}?');">
            @csrf
            <div class="col-md-4"><label class="form-label small mb-0">Saiu da conta</label>
                <select name="fin_conta_id" class="form-select" required><option value="">— escolha —</option>@foreach($contas as $ct)<option value="{{ $ct->id }}">{{ $ct->nome }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label small mb-0">Data</label><input type="date" name="data" class="form-control" value="{{ date('Y-m-d') }}" required></div>
            <div class="col-md-2"><label class="form-label small mb-0">Forma</label><select name="forma" class="form-select"><option>PIX</option><option>Dinheiro</option><option>Transferência</option></select></div>
            <div class="col-md-3"><button class="btn btn-success w-100"><i class="bx bx-check"></i> Pagar {{ $m($a->total()) }}</button></div>
            <div class="col-12 small text-muted">Lança as duas saídas no extrato (fretes na categoria Entregas, extras em Extras do motoboy) e dá baixa nas contas a pagar. Também dá para pagar direto em Contas a pagar: o acerto fecha sozinho.</div>
        </form>
    </div></div>
    @endif
</div>
@endsection
