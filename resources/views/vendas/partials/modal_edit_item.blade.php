<div class="modal fade" id="modal-edit_item" aria-modal="true" role="dialog" style="overflow:scroll;" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>


            <div class="modal-body">
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
                @endphp
                <div class="row">
                    <div class="col-md-2">
                        {{-- names *_modal: não colidem com x_pedido[] / num_item_pedido[] da tabela (old() vira array e o Form quebra) --}}
                        <label for="inp-quantidade_modal" class="form-label">Quantidade</label>
                        <input type="tel" name="quantidade_modal" id="inp-quantidade_modal" class="form-control qtd" value="{{ $__vs('quantidade_modal') }}" autocomplete="off">
                    </div>

                    <div class="col-md-2">
                        <label for="inp-valor_modal" class="form-label">Valor unitário</label>
                        <input type="tel" name="valor_modal" id="inp-valor_modal" class="form-control moeda" value="{{ $__vs('valor_modal') }}" autocomplete="off">
                    </div>

                    <div class="col-md-5">
                        <label for="inp-x_pedido" class="form-label">Descriçao do pedido</label>
                        <input type="text" name="x_pedido_modal" id="inp-x_pedido" class="form-control" value="{{ $__vs('x_pedido_modal') }}" autocomplete="off">
                    </div>

                    <div class="col-md-3">
                        <label for="inp-num_item_pedido" class="form-label">Nº item do pedido</label>
                        <input type="text" name="num_item_pedido_modal" id="inp-num_item_pedido" class="form-control" value="{{ $__vs('num_item_pedido_modal') }}" autocomplete="off">
                    </div>

                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="salvarItem()" class="btn btn-primary px-5">Editar</button>
            </div>

        </div>
    </div>
</div>
