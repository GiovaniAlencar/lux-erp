@php
    $oi = $oi ?? [];
    $tel = $oi['telefone'] ?? $item->telefone;
    $sitPag = $oi['situacao_pagamento'] ?? $item->situacao_pagamento;
    $prio = array_key_exists('prioridade', $oi) ? filter_var($oi['prioridade'], FILTER_VALIDATE_BOOLEAN) : $item->prioridade;
    $mostrarValor = array_key_exists('mostrar_valor_pedido', $oi) ? filter_var($oi['mostrar_valor_pedido'], FILTER_VALIDATE_BOOLEAN) : $item->mostrar_valor_pedido;
@endphp
<div class="col-12" data-item-wrapper="{{ $item->id }}">
    <div class="card rota-entrega-card {{ $prio ? 'prioridade' : '' }} rota-item-row" data-item-id="{{ $item->id }}">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <span class="fw-bold fs-5">#{{ $item->venda_id }}</span>
                <span class="text-muted ms-2">{{ $item->cliente_nome }}</span>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="badge rounded-pill px-3 py-2 {{ $item->badgeClassSituacaoPagamento() }}">
                    {{ $item->labelSituacaoPagamento() }}
                </span>
                <span class="badge rounded-pill px-3 py-2
                    @if($item->status_entrega === 'entregue') badge-entregue
                    @elseif($item->status_entrega === 'ocorrencia') badge-ocorrencia
                    @else badge-pendente @endif">
                    @if($item->status_entrega === 'entregue') Entregue
                    @elseif($item->status_entrega === 'ocorrencia')
                        Ocorrência: {{ $tiposOcorrencia[$item->ocorrencia_tipo] ?? $item->ocorrencia_tipo }}
                    @else Pendente
                    @endif
                </span>
                @if($editavel && !in_array($item->status_entrega, ['entregue', 'ocorrencia'], true))
                <button type="button"
                    class="rota-btn rota-btn-danger btn-sm py-1 px-2 btn-remover-item"
                    data-url="{{ route('rotas-entrega.itens.destroy', [$rota->id, $item->id]) }}"
                    data-venda-id="{{ $item->venda_id }}"
                    title="Cliente desistiu — tirar da rota">
                    <i class="bi bi-x-lg"></i> Remover
                </button>
                @endif
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6 col-lg-5">
                    <label class="rota-field-label">Cliente</label>
                    <input type="text" class="form-control rota-readonly-field" value="{{ $item->cliente_nome }}" readonly tabindex="-1">
                </div>
                <div class="col-md-6 col-lg-3">
                    <label class="rota-field-label">Telefone <span class="text-danger">*</span></label>
                    <input type="text" class="form-control rota-field {{ trim($tel ?? '') === '' ? 'border-danger' : '' }}" name="itens[{{ $item->id }}][telefone]" value="{{ $tel }}" placeholder="Obrigatório" {{ $editavel ? 'required' : 'readonly' }}>
                </div>
                <div class="col-md-6 col-lg-2">
                    <label class="rota-field-label">Frete motoboy</label>
                    <input type="text" class="form-control rota-readonly-field" value="R$ {{ __moeda($item->frete) }}" readonly tabindex="-1">
                </div>
                <div class="col-md-6 col-lg-2 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input type="hidden" name="itens[{{ $item->id }}][prioridade]" value="0">
                        <input type="checkbox" class="form-check-input rota-prioridade" name="itens[{{ $item->id }}][prioridade]" value="1" id="prio-{{ $item->id }}" {{ $prio ? 'checked' : '' }} {{ $editavel ? '' : 'disabled' }}>
                        <label class="form-check-label fw-semibold text-warning" for="prio-{{ $item->id }}">Prioridade</label>
                    </div>
                </div>

                <div class="col-12"><hr class="my-1 text-muted"></div>

                <div class="col-12">
                    <label class="rota-field-label">Pagamento (motoboy)</label>
                </div>
                <div class="col-md-4 col-lg-3">
                    <label class="rota-field-label">Valor do pedido</label>
                    <div class="rota-valor-pedido-box">
                        <div class="valor">R$ {{ __moeda($item->valor_total) }}</div>
                        @if($item->situacao_pagamento !== 'pago')
                        <div class="small rota-hint mt-1">
                            A cobrar:
                            <strong>R$ {{ __moeda($item->valorExibirPagamento()) }}</strong>
                        </div>
                        @endif
                        @php $divFiscalRota = $item->venda ? $item->venda->divisaoFiscal() : null; @endphp
                        @if($divFiscalRota && $divFiscalRota['tem_fiscal'])
                        <div class="small mt-1" title="Divisão do pedido em duas contas (uso interno)">
                            <span class="badge bg-primary">F</span> {{ config('lux.conta_fiscal') }}: <strong>R$ {{ __moeda($divFiscalRota['fiscal']) }}</strong>
                            @include('vendas.partials.pix_btn', ['pago' => ($item->situacao_pagamento === 'pago' || optional($item->venda)->status_pagamento === 'pago'), 'conta' => 'fiscal', 'valor' => $divFiscalRota['fiscal'], 'txid' => 'LUX' . $item->venda_id . 'F'])<br>
                            <span class="badge bg-secondary">2</span> {{ config('lux.conta_nao_fiscal') }}: <strong>R$ {{ __moeda($divFiscalRota['nao_fiscal']) }}</strong>
                            @include('vendas.partials.pix_btn', ['pago' => ($item->situacao_pagamento === 'pago' || optional($item->venda)->status_pagamento === 'pago'), 'conta' => 'nao_fiscal', 'valor' => $divFiscalRota['nao_fiscal'], 'txid' => 'LUX' . $item->venda_id . 'N'])
                        </div>
                        @endif
                    </div>
                </div>
                <div class="col-md-4 col-lg-3">
                    <label class="rota-field-label">Situação</label>
                    <select class="form-select form-select-sm rota-situacao-pagamento" name="itens[{{ $item->id }}][situacao_pagamento]" {{ $editavel ? '' : 'disabled' }}>
                        @foreach($situacoesPagamento as $k => $lbl)
                        <option value="{{ $k }}" {{ $sitPag === $k ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 col-lg-3">
                    <div class="form-check mt-4 pt-1">
                        <input type="hidden" name="itens[{{ $item->id }}][mostrar_valor_pedido]" value="0">
                        <input type="checkbox" class="form-check-input" name="itens[{{ $item->id }}][mostrar_valor_pedido]" value="1" id="mostrar-valor-{{ $item->id }}" {{ $mostrarValor ? 'checked' : '' }} {{ $editavel ? '' : 'disabled' }}>
                        <label class="form-check-label" for="mostrar-valor-{{ $item->id }}">Mostrar valor no TXT</label>
                    </div>
                </div>
                <div class="col-md-4 col-lg-3 campo-valor-restante {{ $sitPag === 'pago_parcial' ? '' : 'd-none' }}">
                    <label class="rota-field-label">Falta receber</label>
                    <input type="tel" class="form-control form-control-sm moeda" name="itens[{{ $item->id }}][valor_restante]" value="{{ $oi['valor_restante'] ?? ($item->valor_restante !== null ? __moeda($item->valor_restante) : '') }}" placeholder="0,00" {{ $editavel ? '' : 'readonly' }}>
                </div>
                @if($item->mostrar_parcelas && $item->qtd_parcelas > 1)
                <div class="col-md-12 col-lg-3">
                    <div class="small text-muted mt-2">
                        Cartão: {{ $item->qtd_parcelas }}x de R$ {{ __moeda($item->valor_parcela ?: ($item->valor_total / max(1, $item->qtd_parcelas))) }}
                    </div>
                </div>
                @endif

                <div class="col-12"><hr class="my-1 text-muted"></div>

                <div class="col-md-7 col-lg-6">
                    <label class="rota-field-label">Rua / Avenida</label>
                    <input type="text" class="form-control" name="itens[{{ $item->id }}][rua]" value="{{ $oi['rua'] ?? $item->rua }}" {{ $editavel ? '' : 'readonly' }}>
                </div>
                <div class="col-md-2 col-lg-2">
                    <label class="rota-field-label">Número</label>
                    <input type="text" class="form-control" name="itens[{{ $item->id }}][numero]" value="{{ $oi['numero'] ?? $item->numero }}" {{ $editavel ? '' : 'readonly' }}>
                </div>
                <div class="col-md-3 col-lg-4">
                    <label class="rota-field-label">Bairro</label>
                    <input type="text" class="form-control" name="itens[{{ $item->id }}][bairro]" value="{{ $oi['bairro'] ?? $item->bairro }}" {{ $editavel ? '' : 'readonly' }}>
                </div>
                <div class="col-12">
                    <label class="rota-field-label">Complemento / Referência</label>
                    <input type="text" class="form-control" name="itens[{{ $item->id }}][complemento]" value="{{ $oi['complemento'] ?? $item->complemento }}" placeholder="Apto, bloco, ponto de referência…" {{ $editavel ? '' : 'readonly' }}>
                </div>

                <div class="col-md-6">
                    <label class="rota-field-label">Observação de prioridade</label>
                    <input type="text" class="form-control" name="itens[{{ $item->id }}][observacao_prioridade]" value="{{ $oi['observacao_prioridade'] ?? $item->observacao_prioridade }}" placeholder="Ex.: entregar antes das 12h" {{ $editavel ? '' : 'readonly' }}>
                </div>
                <div class="col-md-6">
                    <label class="rota-field-label">Aviso / lembrete</label>
                    <input type="text" class="form-control {{ trim($oi['aviso_entrega'] ?? $item->aviso_entrega ?? '') !== '' ? 'border-warning' : '' }}" name="itens[{{ $item->id }}][aviso_entrega]" value="{{ $oi['aviso_entrega'] ?? $item->aviso_entrega }}" placeholder="Ex.: entrega só amanhã / cliente pediu depois das 18h" {{ $editavel ? '' : 'readonly' }}>
                </div>

                <div class="col-md-3">
                    <label class="rota-field-label">Embalagem</label>
                    <select class="form-select form-select-sm" name="itens[{{ $item->id }}][embalagem_tipo]" {{ $editavel ? '' : 'disabled' }}>
                        <option value="">—</option>
                        <option value="sacola" {{ ($oi['embalagem_tipo'] ?? $item->embalagem_tipo) === 'sacola' ? 'selected' : '' }}>Sacola</option>
                        <option value="caixa" {{ ($oi['embalagem_tipo'] ?? $item->embalagem_tipo) === 'caixa' ? 'selected' : '' }}>Caixa</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="rota-field-label">Qtd. emb.</label>
                    <input type="number" min="1" max="999" class="form-control form-control-sm" name="itens[{{ $item->id }}][embalagem_qtd]" value="{{ $oi['embalagem_qtd'] ?? $item->embalagem_qtd }}" {{ $editavel ? '' : 'readonly' }}>
                </div>
                @if($editavel && optional($item->venda)->cliente_id)
                <div class="col-md-4 d-flex align-items-end justify-content-end">
                    <button type="button"
                        class="rota-btn rota-btn-ghost btn-sm btn-agregar-cliente"
                        data-cliente-id="{{ $item->venda->cliente_id }}"
                        data-cliente-nome="{{ $item->cliente_nome }}">
                        <i class="bi bi-plus-lg"></i> Agregar pedido deste cliente
                    </button>
                </div>
                @endif

                <div class="col-md-6">
                    <label class="rota-field-label">Link de confirmação (motoboy)</label>
                    <div class="d-flex gap-2 align-items-start">
                        <div class="rota-link-box flex-grow-1">{{ $item->urlConfirmacao() }}</div>
                        <button type="button" class="rota-btn rota-btn-ghost btn-sm btn-copy-link flex-shrink-0" data-link="{{ $item->urlConfirmacao() }}" title="Copiar link">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
