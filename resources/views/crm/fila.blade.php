@extends('default.layout', ['title' => 'CRM LUX · Fila de contatos'])
@section('content')
@php
    $m = fn ($v) => 'R$ ' . __moeda($v);
    $porId = $camps->keyBy('id');
    $corCamp = ['reposicao' => 'success', 'esfriando' => 'warning text-dark', 'inativo' => 'secondary', 'interessado' => 'info text-dark'];
@endphp
<div class="page-content">
    @include('crm._nav', ['tituloAba' => 'Fila de contatos'])

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <ul class="nav nav-tabs">
            <li class="nav-item"><a class="nav-link {{ $aba === 'aguardando' ? 'active' : '' }}" href="{{ route('crm.fila') }}">Aguardando aprovação <span class="badge bg-primary">{{ $contagem['aguardando'] }}</span></a></li>
            <li class="nav-item"><a class="nav-link {{ $aba === 'aprovados' ? 'active' : '' }}" href="{{ route('crm.fila', ['aba' => 'aprovados']) }}">Aprovados <span class="badge bg-secondary">{{ $contagem['aprovados'] }}</span></a></li>
            <li class="nav-item"><a class="nav-link {{ $aba === 'enviados' ? 'active' : '' }}" href="{{ route('crm.fila', ['aba' => 'enviados']) }}">Enviados <span class="badge bg-success">{{ $contagem['enviados'] }}</span></a></li>
            <li class="nav-item"><a class="nav-link {{ $aba === 'elegiveis' ? 'active' : '' }}" href="{{ route('crm.fila', ['aba' => 'elegiveis']) }}">Elegíveis</a></li>
            <li class="nav-item"><a class="nav-link {{ $aba === 'fora' ? 'active' : '' }}" href="{{ route('crm.fila', ['aba' => 'fora']) }}">Fora das regras</a></li>
        </ul>
        <form method="post" action="{{ route('crm.fila.gerar') }}" onsubmit="return confirm('Montar a fila agora com até {{ $cfg['limite_diario'] }} contato(s) por dia?');">
            @csrf <button class="btn btn-primary btn-sm"><i class="bx bx-list-plus"></i> Montar fila do dia</button>
        </form>
    </div>

    @if(in_array($aba, ['aguardando', 'aprovados'], true))
        @if($envios->isEmpty())
            <div class="card"><div class="card-body text-muted">
                {{ $aba === 'aguardando' ? 'Nada aguardando aprovação. Use "Montar fila do dia" para trazer os clientes elegíveis (até o limite diário).' : 'Nenhum contato aprovado esperando envio.' }}
            </div></div>
        @else
        <form method="post" action="{{ route('crm.fila.aprovar') }}" id="form-aprovar">
            @csrf
            @if($aba === 'aguardando')
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="form-check"><input class="form-check-input" type="checkbox" id="marcar-todos" checked><label class="form-check-label small" for="marcar-todos">Marcar todos</label></div>
                <button class="btn btn-success btn-sm"><i class="bx bx-check-double"></i> Aprovar marcados</button>
            </div>
            @endif
        </form>
        @foreach($envios as $e)
            @php $ctx = json_decode($e->contexto ?? '{}', true) ?: []; $camp = $porId[$e->campanha_id] ?? null; @endphp
            <div class="card mb-2"><div class="card-body py-2">
                <div class="row g-2">
                    <div class="col-lg-4">
                        <div class="d-flex gap-2 align-items-start">
                            @if($aba === 'aguardando')<input type="checkbox" class="form-check-input mt-1 chk-envio" name="ids[]" value="{{ $e->id }}" form="form-aprovar" checked>@endif
                            <div>
                                <div class="fw-semibold">{{ $e->nome }}
                                    @if($e->cliente_id)<a href="{{ route('clientes.edit', $e->cliente_id) }}" target="_blank" class="small" title="Abrir cadastro"><i class="bx bx-link-external"></i></a>@endif</div>
                                <div class="small text-muted">{{ \App\Services\Crm\Crm::formatarTelefone($e->telefone) }} · tentativa {{ $e->tentativa }}</div>
                                @if($e->status === 'erro')<div class="small text-danger"><b>Não enviado:</b> {{ $e->erro }}</div>
                                @elseif($e->status === 'processando')<div class="small text-primary">Enviando…</div>
                                @elseif($e->erro)<div class="small text-warning">{{ $e->erro }}@if($e->agendado_para > now()) · próxima tentativa {{ \Carbon\Carbon::parse($e->agendado_para)->format('d/m H:i') }}@endif</div>@endif
                                @if($camp)<span class="badge bg-{{ $corCamp[$camp->chave] ?? 'secondary' }}">{{ $camp->nome }}</span>@endif
                                <div class="small mt-1">{{ $e->motivo }}</div>
                                @if(!empty($ctx['valor']))
                                    <div class="small text-muted">Pedido <a href="{{ route('vendas.show', $ctx['venda_id']) }}" target="_blank">#{{ $ctx['venda_id'] }}</a> · {{ $m($ctx['valor']) }} · {{ rtrim(rtrim(number_format($ctx['unidades'] ?? 0, 2, ',', ''), '0'), ',') }} un.</div>
                                    <div class="small text-muted" title="{{ implode(' · ', $ctx['produtos_todos'] ?? []) }}">{{ \Illuminate\Support\Str::limit(implode(' · ', $ctx['produtos_todos'] ?? []), 110) }}</div>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <form method="post" action="{{ route('crm.envio.texto', $e->id) }}" class="d-flex gap-2 align-items-start">
                            @csrf
                            <textarea name="texto" class="form-control form-control-sm txt-envio" id="txt-{{ $e->id }}" rows="3">{{ $e->texto }}</textarea>
                            <button class="btn btn-sm btn-outline-secondary" title="Salvar texto"><i class="bx bx-save"></i></button>
                        </form>
                    </div>
                    <div class="col-lg-2 d-flex flex-column gap-1">
                        @if($central && $e->status !== 'processando')
                        <form method="post" action="{{ route('crm.envio.central', $e->id) }}" class="form-central" data-txt="txt-{{ $e->id }}" onsubmit="return confirm('Enviar agora pela central para {{ addslashes($e->nome) }}?');">
                            @csrf <input type="hidden" name="texto" value="">
                            <button class="btn btn-sm btn-primary w-100" title="Sai pelo WhatsApp da central; a resposta cai na conversa do cliente"><i class="bx bx-send"></i> {{ $e->status === 'erro' ? 'Tentar de novo' : 'Enviar pela central' }}</button>
                        </form>
                        @endif
                        <a href="#" class="btn btn-sm btn-{{ $central ? 'outline-success' : 'success' }} w-100 btn-whats" data-tel="{{ $e->telefone }}" data-txt="txt-{{ $e->id }}" title="Abre o WhatsApp com a mensagem pronta"><i class="bx bxl-whatsapp"></i> Abrir no WhatsApp</a>
                        <form method="post" action="{{ route('crm.envio.enviado', $e->id) }}" onsubmit="return confirm('Confirmar que a mensagem foi enviada para {{ addslashes($e->nome) }}?');">
                            @csrf <button class="btn btn-sm btn-outline-success w-100">✔ Já enviei</button>
                        </form>
                        <form method="post" action="{{ route('crm.envio.cancelar', $e->id) }}" onsubmit="return confirm('Tirar {{ addslashes($e->nome) }} da fila? Não entra de novo por esta mesma compra.');">
                            @csrf <button class="btn btn-sm btn-outline-secondary w-100">Tirar da fila</button>
                        </form>
                        <form method="post" action="{{ route('crm.bloquear') }}" onsubmit="return confirm('Este telefone não vai receber mais nenhuma mensagem do CRM. Confirmar?');">
                            @csrf <input type="hidden" name="telefone" value="{{ $e->telefone }}"><input type="hidden" name="observacao" value="Bloqueado na fila do CRM">
                            <button class="btn btn-sm btn-outline-danger w-100">Não contatar</button>
                        </form>
                    </div>
                </div>
            </div></div>
        @endforeach
        @endif
    @elseif($aba === 'enviados')
        <div class="card"><div class="card-body">
            <p class="small text-muted">Já receberam mensagem. Respondeu? marque <b>Respondeu</b> (a 2ª mensagem não sai e, se comprar em até {{ $cfg['janela_atribuicao_dias'] }} dias, a venda conta para o CRM). Sem resposta em {{ $cfg['segunda_tentativa_dias'] }} dias, a 2ª mensagem entra sozinha em "Aguardando aprovação". Compra nova encerra sozinho.</p>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>Cliente</th><th>Campanha</th><th>Mensagens</th><th>Situação</th><th style="width:260px"></th></tr></thead>
                <tbody>
                @forelse($andamento as $c)
                    @php $camp = $porId[$c->campanha_id] ?? null; @endphp
                    <tr>
                        <td>{{ $c->nome }}<div class="small text-muted">{{ \App\Services\Crm\Crm::formatarTelefone($c->telefone) }}</div></td>
                        <td>@if($camp)<span class="badge bg-{{ $corCamp[$camp->chave] ?? 'secondary' }}">{{ $camp->nome }}</span>@endif</td>
                        <td class="small">@foreach($c->envios as $en)<div>{{ $en->tentativa }}ª em {{ \Carbon\Carbon::parse($en->enviado_em)->format('d/m H:i') }}</div>@endforeach</td>
                        <td class="small">{{ $c->status === 'respondido' ? 'Respondeu em ' . \Carbon\Carbon::parse($c->respondido_em)->format('d/m') : 'Aguardando resposta' }}</td>
                        <td class="d-flex gap-1 flex-wrap">
                            <a class="btn btn-sm btn-outline-success" target="_blank" rel="noopener" href="https://wa.me/{{ $c->telefone }}"><i class="bx bxl-whatsapp"></i></a>
                            @if($c->status === 'ativo')
                            <form method="post" action="{{ route('crm.ciclo.respondeu', $c->id) }}">@csrf<button class="btn btn-sm btn-success">Respondeu</button></form>
                            @endif
                            <form method="post" action="{{ route('crm.ciclo.encerrar', $c->id) }}" onsubmit="return confirm('Encerrar este contato sem mais mensagens?');">@csrf<button class="btn btn-sm btn-outline-secondary">Encerrar</button></form>
                            <form method="post" action="{{ route('crm.bloquear') }}" onsubmit="return confirm('Este telefone não vai receber mais nenhuma mensagem do CRM. Confirmar?');">@csrf<input type="hidden" name="telefone" value="{{ $c->telefone }}"><input type="hidden" name="observacao" value="Pediu para não receber"><button class="btn btn-sm btn-outline-danger">Não contatar</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted">Ninguém aguardando resposta.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div></div>
    @else
        <div class="card"><div class="card-body">
            <p class="small text-muted">
                @if($aba === 'elegiveis')
                    Clientes que estão na faixa de alguma campanha ativa e passam em todas as regras. "Montar fila do dia" traz os primeiros, até o limite diário ({{ $cfg['limite_diario'] }}), começando pela reposição.
                @else
                    Estão na faixa de uma campanha, mas não podem receber contato agora.
                @endif
            </p>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>Cliente</th><th>Telefone</th><th>Campanha</th><th>Última compra</th><th class="text-end">Dias</th>@if($aba === 'fora')<th>Motivo</th>@endif</tr></thead>
                <tbody>
                @forelse($elegiveis as $e)
                    @php $camp = $camps[$e['campanha']] ?? null; @endphp
                    <tr>
                        <td>@if($e['cliente_id'])<a href="{{ route('clientes.edit', $e['cliente_id']) }}" target="_blank">{{ $e['nome'] }}</a>@else{{ $e['nome'] }}@endif
                            @if(!empty($e['classificacao']))<div class="small text-muted">{{ \App\Services\Crm\Fila::CLASSIFICACAO[$e['classificacao']] ?? $e['classificacao'] }}{{ $e['atendente'] ? ' · ' . $e['atendente'] : '' }}</div>@endif</td>
                        <td class="text-nowrap small">{{ $e['telefone'] ? \App\Services\Crm\Crm::formatarTelefone($e['telefone']) : '—' }}</td>
                        <td><span class="badge bg-{{ $corCamp[$e['campanha']] ?? 'secondary' }}">{{ optional($camp)->nome }}</span></td>
                        <td class="small">@if($e['venda_id'])<a href="{{ route('vendas.show', $e['venda_id']) }}" target="_blank">{{ \Carbon\Carbon::parse($e['ultima'])->format('d/m/Y') }}</a>@else conversa em {{ \Carbon\Carbon::parse($e['ultima'])->format('d/m/Y') }}@endif</td>
                        <td class="text-end">{{ $e['dias'] }}</td>
                        @if($aba === 'fora')<td class="small text-muted">{{ $e['fora'] }}</td>@endif
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">Ninguém.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div></div>
    @endif
</div>
@endsection
@section('js')
<script>
    // abre o WhatsApp com o texto como está na caixa (mesmo sem salvar)
    document.querySelectorAll('.btn-whats').forEach((b) => b.addEventListener('click', (e) => {
        e.preventDefault();
        const txt = document.getElementById(b.dataset.txt).value;
        window.open('https://wa.me/' + b.dataset.tel + '?text=' + encodeURIComponent(txt), '_blank', 'noopener');
    }));
    // "Enviar pela central" leva o texto como está na caixa (mesmo sem salvar)
    document.querySelectorAll('.form-central').forEach((f) => f.addEventListener('submit', () => {
        f.querySelector('input[name=texto]').value = document.getElementById(f.dataset.txt).value;
    }));
    const t = document.getElementById('marcar-todos');
    if (t) t.addEventListener('change', () => document.querySelectorAll('.chk-envio').forEach((c) => (c.checked = t.checked)));
</script>
@endsection
