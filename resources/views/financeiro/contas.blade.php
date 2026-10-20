@extends('default.layout', ['title' => 'Contas e saldos'])
@section('content')
@php $ic = ['banco' => 'bx-building-house', 'caixa' => 'bx-money', 'cartao' => 'bx-credit-card', 'outro' => 'bx-wallet']; @endphp
<div class="page-content">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h5 class="mb-0">Contas e saldos</h5>
            <div class="small text-muted">Saldo de cada conta = saldo inicial + entradas − saídas do extrato.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#modal-transferir" @disabled($contas->where('ativo', true)->count() < 2)><i class="bx bx-transfer"></i> Transferir</button>
            <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#modal-lancar" @disabled($contas->where('ativo', true)->isEmpty())><i class="bx bx-plus-circle"></i> Lançar entrada/saída</button>
            <button class="btn btn-primary" onclick="finNovaConta()"><i class="bx bx-plus"></i> Nova conta</button>
        </div>
    </div>

    @if(isset($pendencias) && $pendencias->isNotEmpty())
    <div class="alert alert-warning d-flex gap-2 align-items-start">
        <i class="bx bx-error fs-4"></i>
        <div class="flex-grow-1">
            <b>{{ $pendencias->count() }} {{ $pendencias->count() === 1 ? 'lançamento precisa' : 'lançamentos precisam' }} de atenção</b>
            <div class="small">Comprovante excluído da venda, mas o dinheiro continua na conta. Abra o extrato e escolha <b>Desfazer</b> (estornar) ou <b>Vincular</b> a outra venda.</div>
            <ul class="small mb-0 mt-1 ps-3">
                @foreach($pendencias->take(8) as $pm)
                <li>R$ {{ __moeda($pm->valor) }} · {{ optional($pm->conta)->nome }} · {{ $pm->data->format('d/m/Y') }} —
                    <a href="{{ route('fin.extrato', ['conta_id' => $pm->conta_id, 'inicio' => $pm->data->format('Y-m-01'), 'fim' => max($pm->data->format('Y-m-t'), date('Y-m-d'))]) }}">abrir extrato</a></li>
                @endforeach
            </ul>
        </div>
    </div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="small text-muted text-uppercase">Saldo total</div><div class="fs-4 fw-bold {{ $total < 0 ? 'text-danger' : '' }}">R$ {{ __moeda($total) }}</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="small text-muted text-uppercase">A pagar vencido</div><div class="fs-4 fw-bold {{ $aPagar['vencido'] > 0 ? 'text-danger' : '' }}">R$ {{ __moeda($aPagar['vencido']) }}</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="small text-muted text-uppercase">Vence hoje · próximos 7 dias</div><div class="fs-5 fw-bold">R$ {{ __moeda($aPagar['hoje']) }} <span class="text-muted fw-normal">·</span> R$ {{ __moeda($aPagar['semana']) }}</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="small text-muted text-uppercase">Este mês</div>
            <div class="fw-semibold"><span class="text-success">+ R$ {{ __moeda($mes->entradas) }}</span> · <span class="text-danger">− R$ {{ __moeda($mes->saidas) }}</span></div>
            <div class="small text-muted">resultado R$ {{ __moeda($mes->entradas - $mes->saidas) }} (sem transferências)</div></div></div></div>
    </div>

    @if($contas->isEmpty())
        <div class="card"><div class="card-body text-center py-5">
            <i class="bx bx-wallet fs-1 text-muted"></i>
            <h6 class="mt-2">Cadastre as contas da empresa</h6>
            <p class="text-muted small mb-3">Ex.: <b>Itaú (fiscal)</b>, <b>Conta 2 (Orion)</b>, <b>Caixa da loja</b> e <b>maquininha/cartão</b>. Informe o saldo de hoje de cada uma.</p>
            <button class="btn btn-primary" onclick="finNovaConta()"><i class="bx bx-plus"></i> Nova conta</button>
        </div></div>
    @else
    <div class="row g-3">
        @foreach($contas as $c)
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100 {{ $c->ativo ? '' : 'opacity-50' }}">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <div class="fw-semibold"><i class="bx {{ $ic[$c->tipo] ?? 'bx-wallet' }} me-1"></i>{{ $c->nome }}</div>
                            <div class="small text-muted">{{ App\Models\FinConta::TIPOS[$c->tipo] ?? $c->tipo }}@if($c->padrao) · <span class="badge bg-primary">padrão</span>@endif @if(!$c->ativo) · inativa @endif</div>
                        </div>
                        <button class="btn btn-sm btn-light" title="Editar" onclick='finEditarConta(@json($c))'><i class="bx bx-edit"></i></button>
                    </div>
                    <div class="fs-3 fw-bold my-2 {{ $saldos[$c->id] < 0 ? 'text-danger' : '' }}">R$ {{ __moeda($saldos[$c->id]) }}</div>
                    <a href="{{ route('fin.extrato', ['conta_id' => $c->id]) }}" class="btn btn-sm btn-light mt-auto"><i class="bx bx-list-ul"></i> Ver extrato</a>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>
@include('financeiro._modais')
@endsection
@section('js')
<script>
function finNovaConta() {
    ['fc-id', 'fc-nome', 'fc-saldo'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('fc-tipo').value = 'banco';
    document.getElementById('fc-padrao').checked = false;
    document.getElementById('fc-ativo-wrap').style.display = 'none';
    document.getElementById('fc-titulo').textContent = 'Nova conta';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modal-conta')).show();
}
function finEditarConta(c) {
    document.getElementById('fc-id').value = c.id;
    document.getElementById('fc-nome').value = c.nome;
    document.getElementById('fc-tipo').value = c.tipo;
    document.getElementById('fc-saldo').value = Number(c.saldo_inicial).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
    document.getElementById('fc-data').value = (c.data_saldo_inicial || '').substring(0, 10);
    document.getElementById('fc-padrao').checked = !!c.padrao;
    document.getElementById('fc-ativo').checked = !!c.ativo;
    document.getElementById('fc-ativo-wrap').style.display = '';
    document.getElementById('fc-titulo').textContent = 'Editar conta';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modal-conta')).show();
}
</script>
@endsection
