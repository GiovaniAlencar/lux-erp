@extends('default.layout', ['title' => 'NF-e — conferir e emitir'])
@section('css')
<style>
    .nf-card { border: 1px solid #e5e7eb; border-radius: 10px; }
    .nf-itens td, .nf-itens th { vertical-align: middle; font-size: .85rem; }
    .nf-itens input.form-control { font-size: .85rem; padding: .25rem .4rem; }
    .nf-pend li { margin-bottom: 2px; }
    .nf-total { font-size: 1.4rem; font-weight: 700; }
</style>
@endsection
@section('content')
@php
    $ed = $nota->editavel();
    $statusCls = ['rascunho' => 'bg-secondary', 'rejeitada' => 'bg-danger', 'autorizada' => 'bg-success', 'cancelada' => 'bg-dark'][$nota->status] ?? 'bg-secondary';
    $cli = $nota->cliente;
    $fmtQ = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, ',', ''), '0'), ',');
@endphp
<div class="page-content">
    <div class="card border-top border-0 border-4 border-primary">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                <div>
                    <h5 class="mb-1 text-primary">
                        NF-e {{ $nota->numero ? 'nº ' . $nota->numero . ' / série ' . $nota->serie : '(rascunho)' }}
                        <span class="badge {{ $statusCls }} align-middle">{{ \App\Models\NotaFiscal::STATUS[$nota->status] ?? $nota->status }}</span>
                        @if((int) $nota->ambiente === 2)<span class="badge bg-warning text-dark align-middle">HOMOLOGAÇÃO</span>@endif
                    </h5>
                    <small class="text-muted">
                        @if($nota->venda_id)Pedido <a href="{{ route('vendas.show', $nota->venda_id) }}">#{{ $nota->venda_id }}</a> · @endif
                        Só os itens com estoque fiscal. Frete, desconto e acréscimo do pedido ficam na conta 2 (salvo pedido 100% fiscal).
                    </small>
                </div>
                <div class="d-flex gap-2">
                    @if(in_array($nota->status, ['autorizada', 'cancelada']))
                    <a class="btn btn-outline-primary btn-sm" target="_blank" href="{{ route('notas-fiscais.danfe', $nota->id) }}"><i class="bx bx-printer"></i> DANFE</a>
                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('notas-fiscais.xml', $nota->id) }}"><i class="bx bx-download"></i> XML</a>
                    @endif
                    @if($nota->chave)
                    <form method="post" action="{{ route('notas-fiscais.consultar', $nota->id) }}" class="d-inline">
                        @csrf
                        <button class="btn btn-outline-dark btn-sm" type="submit" title="Consulta a chave na SEFAZ"><i class="bx bx-search-alt"></i> Consultar situação na SEFAZ</button>
                    </form>
                    @endif
                    <a class="btn btn-light btn-sm" href="{{ route('notas-fiscais.index') }}"><i class="bx bx-list-ul"></i> Notas fiscais</a>
                </div>
            </div>

            @if($nota->chave)
            <div class="small mb-3"><strong>Chave:</strong> <span class="font-monospace">{{ trim(chunk_split($nota->chave, 4, ' ')) }}</span>
                @if($nota->protocolo) · <strong>Protocolo:</strong> {{ $nota->protocolo }}@endif
                @if($nota->autorizada_em) · autorizada em {{ $nota->autorizada_em->format('d/m/Y H:i') }}@endif
            </div>
            @endif
            @if($nota->ultimo_retorno && $nota->status !== 'autorizada')
            <div class="alert alert-danger small py-2"><strong>Retorno da SEFAZ:</strong> {{ $nota->ultimo_retorno }}</div>
            @endif

            @if($ed)
                @if(!empty($pendencias))
                <div class="alert alert-warning">
                    <div class="fw-semibold mb-1"><i class="bx bx-error"></i> Pendências — corrija antes de transmitir:</div>
                    @foreach($pendencias as $grupo => $msgs)
                    <div class="small fw-semibold mt-1">{{ $grupo }}</div>
                    <ul class="small mb-1 nf-pend">@foreach($msgs as $m)<li>{{ $m }}</li>@endforeach</ul>
                    @endforeach
                    <div class="small mt-1">
                        @if(isset($pendencias['Destinatário']) && $cli)<a href="{{ route('clientes.edit', $cli->id) }}" target="_blank" class="me-3"><i class="bx bx-user"></i> Abrir cadastro do cliente</a>@endif
                        @if(isset($pendencias['Itens']))<a href="{{ route('produtos-fiscal-xml.index') }}" target="_blank" class="me-3"><i class="bx bx-file"></i> Importar dados fiscais (XML)</a>@endif
                        @if(isset($pendencias['Emitente']))<a href="{{ route('configNF.index') }}" target="_blank"><i class="bx bx-cog"></i> Configurar emitente</a>@endif
                        <span class="text-muted ms-2">Depois de corrigir, clique em "Salvar e conferir".</span>
                    </div>
                </div>
                @else
                <div class="alert alert-success small py-2"><i class="bx bx-check-circle"></i> Tudo certo para transmitir.</div>
                @endif
            @endif

            <form method="post" action="{{ route('notas-fiscais.salvar', $nota->id) }}" id="nf-form">
                @csrf
                <div class="row g-3 mb-3">
                    <div class="col-lg-5">
                        <div class="nf-card p-3 h-100">
                            <div class="small text-muted text-uppercase fw-semibold mb-1">Destinatário</div>
                            @if($cli)
                            <div class="fw-semibold">{{ $cli->razao_social }}</div>
                            <div class="small">{{ $cli->cpf_cnpj ?: 'sem CPF/CNPJ' }}{{ $cli->ie_rg ? ' · IE ' . $cli->ie_rg : '' }}{{ $cli->contribuinte ? ' · contribuinte' : '' }}</div>
                            <div class="small text-muted">{{ $cli->rua }}, {{ $cli->numero }} — {{ $cli->bairro }} · {{ $cli->cep }} · {{ optional($cli->cidade)->nome }}/{{ optional($cli->cidade)->uf }}</div>
                            <a class="small" href="{{ route('clientes.edit', $cli->id) }}" target="_blank">editar cliente</a>
                            @if((int) $nota->ambiente === 2)<div class="small text-warning mt-1">Em homologação a SEFAZ exige o nome "NF-E EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL" — o sistema troca sozinho.</div>@endif
                            @endif
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <div class="nf-card p-3 h-100">
                            <div class="row g-2">
                                <div class="col-md-5">
                                    <label class="form-label small mb-0">Data/hora de emissão</label>
                                    <input type="datetime-local" name="data_emissao" class="form-control form-control-sm" {{ $ed ? '' : 'disabled' }}
                                        value="{{ optional($nota->data_emissao)->format('Y-m-d\TH:i') }}" max="{{ now()->addMinutes(5)->format('Y-m-d\TH:i') }}" min="{{ now()->subDays(30)->format('Y-m-d\TH:i') }}">
                                    <div class="form-text">Pode ser retroativa (até 30 dias).</div>
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label small mb-0">Natureza de operação</label>
                                    <select name="natureza_id" class="form-select form-select-sm" {{ $ed ? '' : 'disabled' }}>
                                        @foreach($naturezas as $n)
                                        <option value="{{ $n->id }}" @selected((int) $nota->natureza_id === (int) $n->id)>{{ $n->natureza }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small mb-0">Pagamento</label>
                                    <select name="tipo_pagamento" class="form-select form-select-sm" {{ $ed ? '' : 'disabled' }}>
                                        @foreach(\App\Models\NotaFiscal::PAGAMENTOS as $k => $lbl)
                                        <option value="{{ $k }}" @selected($nota->tipo_pagamento === $k)>{{ $lbl }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label small mb-0">Informações complementares</label>
                                    <input type="text" name="info_complementar" class="form-control form-control-sm" maxlength="2000" value="{{ $nota->info_complementar }}" {{ $ed ? '' : 'disabled' }}>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered nf-itens">
                        <thead class="table-light">
                            <tr>
                                <th>Produto</th>
                                <th>NCM</th>
                                <th>CSOSN</th>
                                <th>CFOP</th>
                                <th style="width:120px">Quantidade</th>
                                <th style="width:140px">Valor unit.</th>
                                <th class="text-end" style="width:120px">Total</th>
                                @if($ed)<th class="text-center" style="width:70px">Remover</th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @php $ufEmit = optional(optional($config)->cidade)->uf; $ufDest = optional(optional($cli)->cidade)->uf; @endphp
                            @foreach($nota->itens as $it)
                            @php $p = $it->produto; @endphp
                            <tr>
                                <td>{{ $it->descricao }} <span class="text-muted small">#{{ $it->produto_id }}</span></td>
                                <td>{{ optional($p)->NCM }}</td>
                                <td>{{ optional($p)->CST_CSOSN }}</td>
                                <td>{{ $p ? ($ufEmit && $ufDest && $ufEmit !== $ufDest ? $p->CFOP_saida_inter_estadual : $p->CFOP_saida_estadual) : '' }}</td>
                                <td><input class="form-control nf-q" name="itens[{{ $it->id }}][quantidade]" value="{{ $fmtQ($it->quantidade) }}" {{ $ed ? '' : 'disabled' }}></td>
                                <td><input class="form-control nf-v" name="itens[{{ $it->id }}][valor_unitario]" value="{{ number_format((float) $it->valor_unitario, 2, ',', '.') }}" {{ $ed ? '' : 'disabled' }}></td>
                                <td class="text-end nf-t">{{ __moeda($it->valorTotal()) }}</td>
                                @if($ed)<td class="text-center"><input type="checkbox" class="form-check-input" name="remover[]" value="{{ $it->id }}"></td>@endif
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label small mb-0">Frete</label>
                        <input class="form-control form-control-sm nf-aj" name="valor_frete" value="{{ __moeda($nota->valor_frete) }}" {{ $ed ? '' : 'disabled' }}>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">Desconto</label>
                        <input class="form-control form-control-sm nf-aj" name="valor_desconto" value="{{ __moeda($nota->valor_desconto) }}" {{ $ed ? '' : 'disabled' }}>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">Outras despesas</label>
                        <input class="form-control form-control-sm nf-aj" name="valor_outros" value="{{ __moeda($nota->valor_outros) }}" {{ $ed ? '' : 'disabled' }}>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <div class="small text-muted">Produtos R$ <span id="nf-prod">{{ __moeda($nota->valor_produtos) }}</span></div>
                        <div class="nf-total">Total da NF-e R$ <span id="nf-total">{{ __moeda($nota->valor_total) }}</span></div>
                        @if($nota->venda)
                        @php $divV = $nota->venda->divisaoFiscal(); @endphp
                        <div class="small {{ abs($divV['fiscal'] - (float) $nota->valor_total) > 0.009 ? 'text-danger fw-semibold' : 'text-muted' }}">
                            Valor fiscal cobrado no pedido: R$ {{ __moeda($divV['fiscal']) }}
                        </div>
                        @endif
                    </div>
                </div>

                @if($ed)
                <input type="hidden" name="acao" id="nf-acao" value="salvar">
                <div class="d-flex flex-wrap gap-2 mt-4">
                    <button type="submit" class="btn btn-outline-primary" onclick="document.getElementById('nf-acao').value='salvar'"><i class="bx bx-save"></i> Salvar e conferir</button>
                    <button type="submit" class="btn btn-success" id="nf-btn-transmitir" {{ !empty($pendencias) ? 'disabled' : '' }}
                        title="{{ !empty($pendencias) ? 'Corrija as pendências e salve antes' : '' }}"
                        onclick="document.getElementById('nf-acao').value='transmitir'">
                        <i class="bx bx-send"></i> Salvar e transmitir {{ (int) $nota->ambiente === 2 ? '(homologação)' : '(PRODUÇÃO)' }}
                    </button>
                </div>
                @endif
            </form>

            @if($nota->status === 'cancelada')
            <div class="alert alert-dark mt-4 small">
                <strong>Nota cancelada</strong> em {{ optional($nota->cancelada_em)->format('d/m/Y H:i') }} — {{ $nota->motivo_cancelamento }}
                @if($nota->xmlEventoPath('cancelamento'))
                · <a href="{{ route('notas-fiscais.evento', [$nota->id, 'cancelamento']) }}" target="_blank">imprimir evento de cancelamento</a>
                @endif
                @if($nota->venda_id)
                · <a href="{{ route('notas-fiscais.criar-da-venda', $nota->venda_id) }}">emitir nova NF-e para o pedido</a>
                @endif
            </div>
            @endif

            @if($nota->status === 'autorizada')
            @php
                $horas = $nota->autorizada_em ? $nota->autorizada_em->diffInHours(now()) : 0;
            @endphp
            <div class="row g-3 mt-3">
                <div class="col-lg-6">
                    <div class="nf-card p-3 h-100">
                        <h6 class="mb-1">Carta de correção (CC-e)</h6>
                        <div class="small text-muted mb-2">
                            Corrige dados que <strong>não</strong> mudam valores, impostos, quantidades, CNPJ/CPF ou data. Ex.: endereço, transportadora, observações.
                            @if((int) $nota->sequencia_cce > 0)
                            <br>Cartas registradas: {{ $nota->sequencia_cce }}
                            @if($nota->xmlEventoPath('correcao')) · <a href="{{ route('notas-fiscais.evento', [$nota->id, 'correcao']) }}" target="_blank">imprimir última CC-e</a>@endif
                            @endif
                        </div>
                        <form method="post" action="{{ route('notas-fiscais.cce', $nota->id) }}" onsubmit="return confirm('Enviar a carta de correção para a SEFAZ?')">
                            @csrf
                            <textarea name="correcao" class="form-control form-control-sm" rows="3" minlength="15" maxlength="1000" required placeholder="Descreva a correção (mínimo 15 caracteres). A nova CC-e substitui as anteriores, então inclua todas as correções."></textarea>
                            <button class="btn btn-outline-primary btn-sm mt-2" type="submit"><i class="bx bx-edit"></i> Enviar CC-e</button>
                        </form>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="nf-card p-3 h-100 border-danger">
                        <h6 class="mb-1 text-danger">Cancelar NF-e</h6>
                        <div class="small text-muted mb-2">
                            Prazo da SEFAZ: até 24 horas após a autorização
                            ({{ $horas < 24 ? 'faltam cerca de ' . (24 - $horas) . 'h' : 'prazo normal já passou — a SEFAZ pode recusar' }}).
                            O pedido volta a ficar com "NF pendente".
                        </div>
                        <form method="post" action="{{ route('notas-fiscais.cancelar', $nota->id) }}" onsubmit="return confirm('Cancelar a NF-e {{ $nota->numero }}? Esta ação não pode ser desfeita.')">
                            @csrf
                            <textarea name="motivo" class="form-control form-control-sm" rows="3" minlength="15" maxlength="255" required placeholder="Justificativa (mínimo 15 caracteres)"></textarea>
                            <button class="btn btn-outline-danger btn-sm mt-2" type="submit"><i class="bx bx-x-circle"></i> Cancelar NF-e</button>
                        </form>
                    </div>
                </div>
            </div>
            @endif

            @if($ed)
            <form method="post" action="{{ route('notas-fiscais.excluir', $nota->id) }}" class="mt-3" onsubmit="return confirm('Excluir este rascunho de NF-e?')">
                @csrf
                <button class="btn btn-link text-danger btn-sm p-0" type="submit"><i class="bx bx-trash"></i> Excluir rascunho</button>
            </form>
            @endif
        </div>
    </div>
</div>
@endsection
@section('js')
<script>
(function () {
    function num(v) { v = String(v || '').trim(); if (v.indexOf(',') >= 0) v = v.replace(/\./g, '').replace(',', '.'); var n = parseFloat(v); return isNaN(n) ? 0 : n; }
    function moeda(n) { return n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function recalc() {
        var prod = 0;
        document.querySelectorAll('.nf-itens tbody tr').forEach(function (tr) {
            var q = tr.querySelector('.nf-q'), v = tr.querySelector('.nf-v'), rm = tr.querySelector('input[name="remover[]"]');
            if (!q || !v) return;
            var t = Math.round(num(q.value) * num(v.value) * 100) / 100;
            tr.querySelector('.nf-t').textContent = moeda(t);
            tr.style.opacity = rm && rm.checked ? .4 : 1;
            if (!(rm && rm.checked)) prod += t;
        });
        var f = num(document.querySelector('[name=valor_frete]').value),
            d = num(document.querySelector('[name=valor_desconto]').value),
            o = num(document.querySelector('[name=valor_outros]').value);
        document.getElementById('nf-prod').textContent = moeda(prod);
        document.getElementById('nf-total').textContent = moeda(Math.round((prod + f + o - d) * 100) / 100);
    }
    document.addEventListener('input', function (e) { if (e.target.closest('#nf-form')) recalc(); });
    document.addEventListener('change', function (e) { if (e.target.closest('#nf-form')) recalc(); });
    var form = document.getElementById('nf-form');
    if (form) form.addEventListener('submit', function () {
        if (document.getElementById('nf-acao').value === 'transmitir') {
            var b = document.getElementById('nf-btn-transmitir');
            setTimeout(function () { b.disabled = true; b.innerHTML = 'Transmitindo… aguarde'; }, 10);
        }
    });
})();
</script>
@endsection
