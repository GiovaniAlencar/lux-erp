@php
    if (! isset($__vs)) {
        $__vs = function ($key) {
            $v = old($key);
            if ($v === null) {
                return '';
            }

            return is_array($v) ? '' : (string) $v;
        };
    }
    $__fv = function ($key, $itemValue = '') use ($__vs) {
        if (session()->hasOldInput()) {
            return $__vs($key);
        }

        return $itemValue;
    };
    $__oldItens = session()->hasOldInput() && is_array(old('produto_id')) ? old('produto_id') : null;
    $__oldPagamentos = session()->hasOldInput() && is_array(old('data_vencimento')) ? old('data_vencimento') : null;
    $precoCategoriaJs = $precoCategoriaJs ?? [];
@endphp
<div class="card-body p-4">
    <input type="hidden" value="{{$config->parcelamento_maximo}}" id="parcelamento_maximo">

    <!-- Box: Informações da venda -->
    <div class="card mb-3">
        <div class="card-body p-3">
            <h6 class="mb-3 text-uppercase">Informações da Venda</h6>
            <div class="row">
                {{-- Natureza removida da interface; enviada como hidden com padrão "venda" --}}
                <input type="hidden" name="natureza_id" id="inp-natureza_id" value="{{ $config->nat_op_padrao ?? optional($naturezaPadrao)->id }}">
                @isset($item)
                <div class="col-12 mt-2">
                    <label for="inp-cliente_id" class="required">Cliente</label>
                    <div class="input-group">
                        <select class="form-control select2 cliente_id" name="cliente_id" id="inp-cliente_id">
                            @if(session()->hasOldInput() && old('cliente_id'))
                            @php $clienteOld = \App\Models\Cliente::find(old('cliente_id')); @endphp
                            @if($clienteOld)
                            <option value="{{ $clienteOld->id }}" selected>{{ $clienteOld->razao_social }} - {{ $clienteOld->cpf_cnpj }}</option>
                            @endif
                            @else
                            <option value="{{ $item->cliente_id }}">{{ $item->cliente->razao_social }} - {{ $item->cliente->cpf_cnpj }}</option>
                            @endif
                        </select>
                        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modal-cliente">
                            <i class="bi bi-plus"></i>
                        </button>
                        <button class="btn btn-outline-secondary" type="button" id="btn-open-edit-endereco" title="Editar endereço">
                            <i class="bi bi-geo-alt"></i>
                        </button>
                        <button class="btn btn-outline-secondary" type="button" id="btn-open-edit-perfil" title="Editar perfil">
                            <i class="bi bi-person"></i>
                        </button>
                    </div>
                </div>
                @else
                <div class="col-12 mt-2">
                    <label for="inp-cliente_id" class="">Cliente</label>
                    <div class="input-group">
                        <select class="form-control select2 cliente_id" name="cliente_id" id="inp-cliente_id">
                            @if(old('cliente_id'))
                            @php $clienteOld = \App\Models\Cliente::find(old('cliente_id')); @endphp
                            @if($clienteOld)
                            <option value="{{ $clienteOld->id }}" selected>{{ $clienteOld->razao_social }} - {{ $clienteOld->cpf_cnpj }}</option>
                            @endif
                            @endif
                        </select>
                        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modal-cliente">
                            <i class="bi bi-plus"></i>
                        </button>
                        <button class="btn btn-outline-secondary" type="button" id="btn-open-edit-endereco" title="Editar endereço">
                            <i class="bi bi-geo-alt"></i>
                        </button>
                        <button class="btn btn-outline-secondary" type="button" id="btn-open-edit-perfil" title="Editar perfil">
                            <i class="bi bi-person"></i>
                        </button>
                    </div>
                </div>
                @endisset
            </div>
        </div>
    </div>

    <!-- Box: Itens -->
    <div class="card mb-3">
        <div class="card-body p-3">
            <h6 class="mb-3 text-uppercase">Itens</h6>
            @include('vendas.partials.painel_preco_categoria')
            <div class="row">
                <div class="col-md-5">
                    <div class="form-group">
                        <label for="inp-produto_id" class="">Produto</label>
                        <div class="input-group">
                            <select class="form-control select2" name="produto_id_linha" id="inp-produto_id"></select>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    {!! Form::tel('quantidade_linha', 'Quantidade')->id('inp-quantidade')->attrs(['class' => 'qtd']) !!}
                </div>
                <div class="col-md-2">
                    {!! Form::tel('valor_unitario_linha', 'Valor unitário')->id('inp-valor_unitario')->attrs(['class' => 'moeda value_unit']) !!}
                </div>
                <div class="col-md-2">
                    {!! Form::tel('subtotal_linha', 'Subtotal')->id('inp-subtotal')->attrs(['class' => 'moeda']) !!}
                </div>
                <div class="col-md-1">
                    <br>
                    <button class="btn btn-success btn-add-item" type="button">
                        <i class="bi bi-plus"></i>
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 table-striped mt-2 table-itens">
                        <colgroup>
                            <col class="d-none d-xl-table-column" style="width: 6%;">
                            <col style="width: 10%;">
                            <col style="width: 52%;">
                            <col style="width: 6%;">
                            <col style="width: 12%;">
                            <col style="width: 14%;">
                        </colgroup>
                        <thead>
                            <tr>
                                <th class="d-none d-xl-table-cell">CÓDIGO</th>
                                <th>NOME</th>
                                <th>VALOR UNITÁRIO</th>
                                <th>QUANTIDADE</th>
                                <th>SUBTOTAL</th>
                                <th class="text-end">AÇÕES</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if($__oldItens)
                            @foreach($__oldItens as $i => $pid)
                            <tr class="tr_{{ $i }}">
                                <td class="d-none d-xl-table-cell">
                                    <input readonly type="tel" name="produto_id[]" class="form-control form-control-sm" value="{{ $pid }}">
                                </td>
                                <td>
                                    <input readonly type="text" name="produto_nome[]" class="form-control form-control-sm" value="{{ old('produto_nome.'.$i, '') }}">
                                </td>
                                <td>
                                    <input readonly type="tel" name="valor_unitario[]" class="form-control form-control-sm value_unit_row" value="{{ old('valor_unitario.'.$i, '') }}">
                                </td>
                                <td>
                                    <input readonly type="tel" name="quantidade[]" class="form-control form-control-sm qtd-item qtd_row" value="{{ old('quantidade.'.$i, '') }}">
                                </td>
                                <td>
                                    <input type="hidden" name="x_pedido[]" class="x_pedido_row" value="{{ old('x_pedido.'.$i, '') }}">
                                    <input type="hidden" name="num_item_pedido[]" class="num_item_pedido_row" value="{{ old('num_item_pedido.'.$i, '') }}">
                                    <input readonly type="tel" name="subtotal_item[]" class="form-control form-control-sm subtotal-item" value="{{ old('subtotal_item.'.$i, '') }}">
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-danger btn-delete-row">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-warning btn-edit" onclick="editItem('{{ $i }}')">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                            @elseif(isset($item) && sizeof($item->itens) > 0)
                            @foreach($item->itens as $productItem)
                            @include('vendas.partials.row_product_edit', [
                                'productItem' => $productItem,
                                'rand' => $loop->index,
                                'categorias' => $categorias ?? collect(),
                            ])
                            @endforeach
                            @else
                            <tr class="empty-state">
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="bi bi-cart" style="font-size: 2rem;"></i>
                                    <div class="mt-2">Nenhum item adicionado</div>
                                    <small class="d-block">Clique em "Adicionar Item" para começar</small>
                                </td>
                            </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>



    <!-- Box: Frete e ajustes -->
    <div class="card mb-3">
        <div class="card-body p-3">
            <h6 class="mb-3 text-uppercase">Frete e Ajustes</h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="inp-desconto" class="form-label">Desconto</label>
                    <div class="input-group">
                        <span class="input-group-text">R$</span>
                        <input type="tel" name="desconto" id="inp-desconto" class="form-control moeda desconto"
                            value="{{ $__fv('desconto', isset($item) ? __moeda($item->desconto) : '') }}"
                            placeholder="0,00">
                    </div>
                </div>
                <div class="col-md-4">
                    <label for="inp-acrescimo" class="form-label">Acréscimo</label>
                    <div class="input-group">
                        <span class="input-group-text">R$</span>
                        <input type="tel" name="acrescimo" id="inp-acrescimo" class="form-control moeda acrescimo"
                            value="{{ $__fv('acrescimo', isset($item) ? __moeda($item->acrescimo) : '') }}"
                            placeholder="0,00">
                    </div>
                </div>
                <div class="col-md-4">
                    <label for="inp-frete" class="form-label">Frete</label>
                    <div class="input-group frete-group">
                        <span class="input-group-text">R$</span>
                        <input type="tel" name="frete" id="inp-frete" class="form-control moeda frete"
                            value="{{ $__fv('frete', isset($item) ? __moeda($item->frete) : '') }}"
                            placeholder="0,00">
                    </div>
                </div>
                <div class="col-12">
                    <label for="inp-observacao" class="form-label">Informação adicional</label>
                    <textarea name="observacao" id="inp-observacao" class="form-control" rows="3"
                        placeholder="Observações sobre a venda…">{{ $__fv('observacao', isset($item) ? ($item->observacao ?? '') : '') }}</textarea>
                </div>
                <div class="col-md-12 mt-2">
                    <label for="inp-aviso-entrega" class="form-label">Aviso de entrega</label>
                    <input type="text" name="aviso_entrega" id="inp-aviso-entrega" class="form-control"
                        placeholder="Ex.: entregar só amanhã / cliente pediu depois das 18h"
                        value="{{ $__fv('aviso_entrega', isset($item) ? ($item->aviso_entrega ?? '') : '') }}">
                    <div class="form-text">Aparece na rota e na guia impressa para evitar esquecimentos.</div>
                </div>
            </div>
            {{-- Mantido para o JS de totais (resumo lateral) --}}
            <span class="d-none total-venda" aria-hidden="true">@isset($item)R$ {{ __moeda($item->valor_total) }}@else R$ 0,00 @endif</span>
        </div>
    </div>

    <!-- Box: Pagamento (abaixo de Frete e Ajustes) -->
    <div class="card mb-3 payment-card">
        <div class="card-body p-3">
            <h6 class="mb-3 text-uppercase">Pagamento</h6>
            <div class="row div-pagamento mt-1">
                <div class="col-lg-6">
                    <h5 class="mt-2">Selecione a forma de pagamento</h5>
                    <div class="row g-3 align-items-end">
                        <div class="col-12">
                            {!! Form::select(
                            'tipo_pagamento',
                            'Tipo de pagamento',
                            App\Models\Venda::tiposPagamento(),
                            session()->hasOldInput() ? $__vs('tipo_pagamento') : (isset($item) ? $item->tipo_pagamento : null)
                            )->attrs([
                            'class' => 'select2'
                            ]) !!}
                        </div>
                        <div class="col-12">
                            {!! Form::select(
                            'forma_pagamento',
                            'Forma de pagamento',
                            [
                            '' => 'Selecione a forma de pagamento',
                            'a_vista' => 'A vista',
                            '30_dias' => '30 Dias',
                            'personalizado' => 'Personalizado',
                            ],
                            session()->hasOldInput() ? $__vs('forma_pagamento') : (isset($item) ? $item->forma_pagamento : null)
                            )->attrs(['class' => 'form-select']) !!}
                        </div>
                        <div class="col-md-3">
                            {!! Form::text('qtd_parcelas', 'Qtd de parcelas')->attrs(['class' => '', 'data-mask' => '00']) !!}
                        </div>
                        <div class="col-md-4 data_vencimento">
                            {!! Form::date('data_vencimento_linha', 'Data vencimento')->id('inp-data_vencimento')->attrs(['class' => '']) !!}
                        </div>
                        <div class="col-md-3">
                            {!! Form::tel('valor_integral_linha', 'Valor da parcela')->id('inp-valor_integral')->attrs(['class' => 'moeda']) !!}
                        </div>
                        <div class="col-auto">
                            <button type="button" class="btn btn-primary btn-add-payment"><i class="bi bi-plus-lg"></i> <span class="d-none d-sm-inline ms-1">Adicionar</span></button>
                        </div>
                        <div class="col-auto">
                            <button type="button" onclick="renderizarPagamento()" id="btn-personalizado" data-bs-toggle="modal" data-bs-target="#modal-pagamento_personalizado" class="btn btn-outline-secondary disabled"><i class="bi bi-list-ul"></i></button>
                        </div>
                        <div class="col-auto">
                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modal-taxa-maquineta">
                                <i class="bi bi-calculator"></i> Calcular taxas da maquineta
                            </button>
                        </div>
                        <input type="hidden" name="bandeira_cartao" id="inp-bandeira_cartao" value="99">
                    </div>
                </div>
                <div class="col-lg-6 mt-4 mt-lg-0">
                    <div class="table-responsive">
                        <table class="table mb-0 table-striped mt-2 table-payment">
                            <thead>
                                <tr>
                                    <th>Tipo de pagamento</th>
                                    <th>Vencimento</th>
                                    <th>Valor</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if($__oldPagamentos)
                                @foreach($__oldPagamentos as $i => $dataVenc)
                                <tr>
                                    <td>
                                        <input readonly type="text" name="nome_pagamento[]" class="form-control" value="{{ old('nome_pagamento.'.$i, old('forma_pagamento_parcela.'.$i, '')) }}">
                                        <input readonly type="hidden" name="tipo_pagamentos[]" value="{{ old('tipo_pagamentos.'.$i, '') }}">
                                    </td>
                                    <td>
                                        <input type="date" name="data_vencimento[]" class="form-control" value="{{ $dataVenc }}">
                                    </td>
                                    <td>
                                        <input readonly type="text" name="valor_parcela[]" class="form-control valor_integral" value="{{ old('valor_parcela.'.$i, '') }}">
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-danger btn-delete-row">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                                @elseif(isset($item))
                                @if(sizeof($item->duplicatas) > 0)
                                @foreach($item->duplicatas as $duplicatas)
                                <tr>
                                    <td>
                                        <input readonly type="tel" name="forma_pagamento_parcela[]" class="form-control" value="{{ $duplicatas->getTipoPagamento() }}">
                                    </td>
                                    <td>
                                        <input type="date" name="data_vencimento[]" class="form-control" value="{{ $duplicatas->data_vencimento }}">
                                    </td>
                                    <td>
                                        <input readonly type="text" name="valor_parcela[]" class="form-control valor_integral" value="{{ __moeda($duplicatas->valor_integral) }}">
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-danger btn-delete-row">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                                @else
                                <tr>
                                    <td>
                                        <input readonly type="hidden" name="forma_pagamento" class="form-control" value="{{ $item->forma_pagamento }}">
                                        <input readonly type="tel" name="forma_pagamento_get" class="form-control" value="{{ $item->getTipoPagamento() }}">
                                    </td>
                                    <td>
                                        <input readonly type="text" name="data_vencimento[]" class="form-control" value="{{ __data_pt($item->data_registro, 0) }}">
                                    </td>
                                    <td>
                                        <input readonly type="text" name="valor_parcela[]" class="form-control valor_integral" value="{{ __moeda($item->valor_total) }}">
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-danger btn-delete-row">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endif
                                @endisset
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td>Soma pagamento</td>
                                    @isset($item)
                                    <td class="sum-payment">R$ {{ __moeda($item->valor_total) }}</td>
                                    @else
                                    <td class="sum-payment">R$ 0,00</td>
                                    @endif
                                    <td>
                                        <button class="btn btn-outline-danger btn-sm" type="button" id="remover_parcelas">Remover parcelas</button>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <input type="hidden" value="" id="type" name="type">
</div>
<!-- Ações finais continuam no card de Resumo na coluna direita -->