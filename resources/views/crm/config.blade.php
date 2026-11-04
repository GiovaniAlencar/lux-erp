@extends('default.layout', ['title' => 'CRM LUX · Configurações'])
@section('content')
@php $dias = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 7 => 'Dom']; @endphp
<div class="page-content">
    @include('crm._nav', ['tituloAba' => 'Configurações'])
    @if($errors->any())<div class="alert alert-danger small">{{ implode(' ', $errors->all()) }}</div>@endif

    <form method="post" action="{{ route('crm.config.salvar') }}">
        @csrf
        <div class="card mb-3"><div class="card-body">
            <h6>Regras gerais</h6>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="aprovar_antes" id="aprovar_antes" value="1" @checked($cfg['aprovar_antes'])>
                        <label class="form-check-label" for="aprovar_antes"><b>Aprovar antes de enviar</b> — a fila do dia só sai depois que você aprova (recomendado nas primeiras semanas)</label>
                    </div>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="geracao_automatica" id="geracao_automatica" value="1" @checked($cfg['geracao_automatica'])>
                        <label class="form-check-label" for="geracao_automatica"><b>Montar a fila sozinho</b> todo dia, no início do horário</label>
                    </div>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="envio_ligado" id="envio_ligado" value="1" @checked($cfg['envio_ligado']) @disabled(!$central)>
                        <label class="form-check-label" for="envio_ligado"><b>Envio automático pela central</b> — os aprovados saem sozinhos, um de cada vez, no horário e intervalo abaixo</label>
                    </div>
                    @if(!$central)<div class="small text-muted">Para ligar, configure a integração com a central (veja o quadro abaixo).</div>@endif
                </div>
                <div class="col-6 col-md-3"><label class="form-label">Limite por dia</label><input type="number" name="limite_diario" class="form-control" value="{{ $cfg['limite_diario'] }}" min="1" max="200"><div class="form-text">contatos novos + 2ª tentativa</div></div>
                <div class="col-6 col-md-3"><label class="form-label">Tentativas por ciclo</label><select name="tentativas" class="form-select"><option value="1" @selected($cfg['tentativas'] == 1)>1</option><option value="2" @selected($cfg['tentativas'] == 2)>2</option></select></div>
                <div class="col-6 col-md-2"><label class="form-label">Envia das</label><div class="input-group"><input type="number" name="hora_inicio" class="form-control" value="{{ $cfg['hora_inicio'] }}" min="0" max="23"><span class="input-group-text">h</span></div></div>
                <div class="col-6 col-md-2"><label class="form-label">até as</label><div class="input-group"><input type="number" name="hora_fim" class="form-control" value="{{ $cfg['hora_fim'] }}" min="1" max="24"><span class="input-group-text">h</span></div></div>
                <div class="col-md-8"><label class="form-label d-block">Dias da semana</label>
                    @foreach($dias as $k => $d)
                        <input type="checkbox" class="btn-check" name="dias_semana[]" id="d{{ $k }}" value="{{ $k }}" @checked(in_array($k, (array) $cfg['dias_semana']))>
                        <label class="btn btn-sm btn-outline-primary" for="d{{ $k }}">{{ $d }}</label>
                    @endforeach
                </div>
                <div class="col-6 col-md-3"><label class="form-label">Intervalo entre mensagens</label><div class="input-group"><input type="number" name="intervalo_min_seg" class="form-control" value="{{ $cfg['intervalo_min_seg'] }}"><span class="input-group-text">a</span><input type="number" name="intervalo_max_seg" class="form-control" value="{{ $cfg['intervalo_max_seg'] }}"><span class="input-group-text">seg</span></div><div class="form-text">sorteado entre os dois</div></div>
                <div class="col-6 col-md-3"><label class="form-label">2ª tentativa após</label><div class="input-group"><input type="number" name="segunda_tentativa_dias" class="form-control" value="{{ $cfg['segunda_tentativa_dias'] }}"><span class="input-group-text">dias</span></div></div>
                <div class="col-6 col-md-3"><label class="form-label">Mínimo entre campanhas</label><div class="input-group"><input type="number" name="intervalo_campanhas_dias" class="form-control" value="{{ $cfg['intervalo_campanhas_dias'] }}"><span class="input-group-text">dias</span></div><div class="form-text">por telefone, somando todas</div></div>
                <div class="col-6 col-md-3"><label class="form-label">Venda conta para o CRM até</label><div class="input-group"><input type="number" name="janela_atribuicao_dias" class="form-control" value="{{ $cfg['janela_atribuicao_dias'] }}"><span class="input-group-text">dias</span></div><div class="form-text">depois da resposta do cliente</div></div>
            </div>
        </div></div>

        <div class="card mb-3"><div class="card-body">
            <h6>Campanhas e mensagens</h6>
            <p class="small text-muted mb-2">
                Variáveis: <code>{nome}</code> (primeiro nome ou nome fantasia), <code>{produtos}</code> (até 3 produtos da última compra), <code>{dias}</code>.
                Para ter <b>variações</b> (o sistema alterna entre elas), separe os textos com uma linha contendo só <code>---</code>.
            </p>
            @foreach($camps as $c)
                <div class="border rounded p-3 mb-3">
                    <div class="row g-2 align-items-center mb-2">
                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="camp[{{ $c->id }}][ativa]" id="ca{{ $c->id }}" value="1" @checked($c->ativa)>
                                <label class="form-check-label fw-semibold" for="ca{{ $c->id }}">{{ $c->nome }}</label>
                            </div>
                        </div>
                        <div class="col-md-8">
                            @if($c->chave === 'interessado')
                                <div class="input-group input-group-sm" style="max-width:320px"><span class="input-group-text">1º contato</span><input type="number" name="camp[{{ $c->id }}][dias_min]" class="form-control" value="{{ $c->dias_min }}"><span class="input-group-text">horas após a classificação</span></div>
                                <input type="hidden" name="camp[{{ $c->id }}][dias_max]" value="">
                                <div class="small text-muted">Recebe quem conversou na central e foi classificado pelo atendente (Interessado, Orçamento enviado, Aguardando decisão, Não comprou) e ainda não comprou.</div>
                            @else
                                <div class="input-group input-group-sm" style="max-width:420px"><span class="input-group-text">Última compra entre</span><input type="number" name="camp[{{ $c->id }}][dias_min]" class="form-control" value="{{ $c->dias_min }}"><span class="input-group-text">e</span><input type="number" name="camp[{{ $c->id }}][dias_max]" class="form-control" value="{{ $c->dias_max }}"><span class="input-group-text">dias</span></div>
                            @endif
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6"><label class="form-label small mb-0">1ª mensagem</label><textarea name="camp[{{ $c->id }}][texto_1]" class="form-control form-control-sm" rows="5">{{ $c->texto_1 }}</textarea></div>
                        <div class="col-md-6"><label class="form-label small mb-0">2ª mensagem (sem resposta)</label><textarea name="camp[{{ $c->id }}][texto_2]" class="form-control form-control-sm" rows="5">{{ $c->texto_2 }}</textarea></div>
                    </div>
                </div>
            @endforeach
        </div></div>

        <div class="d-flex justify-content-end"><button class="btn btn-primary"><i class="bx bx-save"></i> Salvar configurações</button></div>
    </form>

    <div class="card mt-3"><div class="card-body">
        <h6>Integração com a central de atendimento</h6>
        @if(!$central)
            <p class="small text-muted mb-0">Desligada. No <code>.env</code> do ERP: <code>CRM_CENTRAL_URL</code> (endereço da API da central), <code>CRM_CENTRAL_TOKEN</code> e <code>CRM_HMAC_SECRET</code> — os dois últimos iguais a <code>CRM_API_TOKEN</code> e <code>CRM_HMAC_SECRET</code> do <code>.env</code> da central.</p>
        @else
            <p class="small mb-2">Central: <span class="badge bg-{{ $central['ok'] ? 'success' : 'danger' }}">{{ $central['texto'] }}</span></p>
            <form method="post" action="{{ route('crm.teste') }}" class="d-flex flex-wrap gap-2 align-items-center">
                @csrf
                <input type="text" name="telefone" class="form-control form-control-sm" style="max-width:200px" placeholder="(83) 9 9999-9999" value="{{ $telTeste }}">
                <button class="btn btn-sm btn-outline-primary"><i class="bx bx-send"></i> Enviar mensagem de teste</button>
                <span class="small text-muted">Chega no seu WhatsApp; responda para ver a conversa voltar na central com a etiqueta CRM.</span>
            </form>
        @endif
    </div></div>
</div>
@endsection
