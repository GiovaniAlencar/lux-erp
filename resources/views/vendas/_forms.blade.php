<div class="card-body p-4">
    <input type="hidden" value="{{$config->parcelamento_maximo}}" id="parcelamento_maximo">

    <!-- Box: Informações da venda -->
    <div class="card mb-3">
        <div class="card-body p-3">
            <h6 class="mb-3 text-uppercase">Informações da Venda</h6>
            <div class="row">
                {{-- Natureza removida da interface; enviada como hidden com padrão "venda" --}}
                <input type="hidden" name="natureza_id" id="inp-natureza_id" value="{{ $config->nat_op_padrao ?? optional($naturezaPadrao)->id }}">
                <div class="col-md-3 mt-2">
                    {!! Form::select(
                    'lista_preco',
                    'Lista de preço',
                    [null => 'Selecione'] + $listaPreco->pluck('nome', 'id')->all(),
                    )->attrs(['class' => 'form-select']) !!}
                </div>
                @isset($item)
                <div class="col-md-5 mt-2">
                    <label for="inp-cliente_id" class="required">Cliente</label>
                    <div class="input-group">
                        <select class="form-control select2 cliente_id" name="cliente_id" id="inp-cliente_id">
                            @isset($item)
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
                <div class="col-md-5 mt-2">
                    <label for="inp-cliente_id" class="">Cliente</label>
                    <div class="input-group">
                        <select class="form-control select2 cliente_id" name="cliente_id" id="inp-cliente_id"></select>
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
            <div class="row">
            <div class="col-md-5">
                <div class="form-group">
                    <label for="inp-produto_id" class="">Produto</label>
                    <div class="input-group">
                        <select class="form-control select2" name="produto_id" id="inp-produto_id"></select>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                {!! Form::tel('quantidade', 'Quantidade')->attrs(['class' => 'qtd']) !!}
            </div>
            <div class="col-md-2">
                {!! Form::tel('valor_unitario', 'Valor unitário')->attrs(['class' => 'moeda value_unit']) !!}
            </div>
            <div class="col-md-2">
                {!! Form::tel('subtotal', 'Subtotal')->attrs(['class' => 'moeda']) !!}
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
                        @if(isset($item) && sizeof($item->itens) > 0)
                        @foreach($item->itens as $product)
                        <tr class="tr_{{ $loop->index }}">
                            <td class="d-none d-xl-table-cell">
                                <input readonly type="tel" name="produto_id[]" class="form-control form-control-sm" value="{{ $product->produto_id }}">
                            </td>
                            <td>
                                <input readonly type="text" name="produto_nome[]" class="form-control form-control-sm" value="{{ $product->produto->nome }}">
                            </td>
                            <td>
                                <input readonly type="tel" name="valor_unitario[]" class="form-control form-control-sm value_unit_row" value="{{ __moeda($product->valor) }}">
                            </td>
                            <td>
                                <input readonly type="tel" name="quantidade[]" class="form-control form-control-sm qtd-item qtd_row" value="{{ __estoque($product->quantidade) }}">
                            </td>
                            <td>
                                <input type="hidden" value="{{ $product->x_pedido }}" name="x_pedido[]" id="x_pedido_row" class="x_pedido_row" value="">
                                <input type="hidden" value="{{ $product->num_item_pedido }}" name="num_item_pedido[]" id="num_item_pedido_row" class="num_item_pedido_row" value="">
                                <input readonly type="tel" name="subtotal_item[]" class="form-control form-control-sm subtotal-item" value="{{ __moeda($product->valor * $product->quantidade) }}">
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <button type="button" class="btn btn-sm btn-danger btn-delete-row">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-warning btn-edit" onclick="editItem('{{ $loop->index }}')">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
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
                <!-- <div class="col-md-3 mt-5">
                    <button class="btn btn-warning" type="button" data-bs-toggle="modal" data-bs-target="#modal-ref-nfe">
                        <i class="bx bx-list-ol"></i>Referenciar NFe
                    </button>
                </div> -->
						<div class="col-sm-2 mt-1">
                    <br>
                    {!! Form::date('data_entrega', 'Data de entrega')->attrs(['class' => '']) !!}
                </div>
						<div class="col-sm-2 mt-1">
                    <br>
                    {!! Form::tel('desconto', 'Desconto')->attrs(['class' => 'moeda desconto'])->value(isset($item) ?
                    __moeda($item->desconto) : '') !!}
                </div>
						<div class="col-sm-2 mt-1">
                    <br>
                    {!! Form::tel('acrescimo', 'Acréscimo')->attrs(['class' => 'moeda acrescimo'])->value(isset($item) ?
                    __moeda($item->acrescimo) : '') !!}
                </div>
						<div class="col-sm-2 mt-1">
                    <br>
                    {!! Form::tel('frete', 'Frete')->attrs(['class' => 'moeda frete'])->value(isset($item) ?
                    __moeda($item->frete) : '') !!}
                </div>
						<div class="col-sm-2 mt-1">
                    <br>
                    {!! Form::text('observacao', 'Informação adicional')->attrs(['class' => '']) !!}
                </div>
                <div class="row mt-2">
                    <div class="col-auto">
                        <h6 class="mb-0">Valor Total: <strong class="total-venda">@isset($item)R$ {{ __moeda($item->valor_total) }}@else R$ 0,00 @endif</strong></h6>
                    </div>
                </div>
            </div>
            
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
                            {!! Form::select('tipo_pagamento', 'Tipo de pagamento', App\Models\Venda::tiposPagamento())->attrs([
                            'class' => 'select2'])->value(isset($item) ? $item->tipo_pagamento : '') !!}
                        </div>
                        <div class="col-12">
                            {!! Form::select('forma_pagamento', 'Forma de pagamento', [
                            '' => 'Selecione a forma de pagamento',
                            'a_vista' => 'A vista',
                            '30_dias' => '30 Dias',
                            'personalizado' => 'Personalizado',
                            ])->attrs(['class' => 'form-select']) !!}
                        </div>
                        <div class="col-md-3">
                            {!! Form::text('qtd_parcelas', 'Qtd de parcelas')->attrs(['class' => '', 'data-mask' => '00']) !!}
                        </div>
                        <div class="col-md-4 data_vencimento">
                            {!! Form::date('data_vencimento', 'Data vencimento')->attrs(['class' => '']) !!}
                        </div>
                        <div class="col-md-3">
                            {!! Form::tel('valor_integral', 'Valor da parcela')->attrs(['class' => 'moeda']) !!}
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
                                @isset($item)
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