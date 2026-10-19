<div class="row g-3">
    <div class="col-md-2">
        {!!Form::text('referencia', 'Referência')->required()
        !!}
    </div>

    <div class="col-md-4">
        <label for="inp-fornecedor_id" class="required">Fornecedor</label>
        <div class="input-group">
            <select required class="form-control select2 fornecedor_id @if($errors->has('fornecedor_id')) is-invalid @endif" name="fornecedor_id" id="inp-fornecedor_id">
                @isset($item)
                <option value="{{ $item->fornecedor_id }}">{{ $item->fornecedor->razao_social }}</option>
                @endif
            </select>
            <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modal-fornecedor">
                <i class="bx bx-plus"></i></button>

            @if($errors->has('fornecedor_id'))
            <div class="invalid-feedback">
                {{ $errors->first('fornecedor_id') }}
            </div>
            @endif
        </div>
    </div>

    <div class="col-md-2">
        {!!Form::select('categoria_id', 'Categoria', $categorias->pluck('nome', 'id')->all())
        ->attrs(['class' => 'form-select'])->required()
        !!}
    </div>

    <div class="col-md-2">
        {!!Form::tel('valor_integral', 'Valor')
        ->attrs(['class' => 'lux-dinheiro'])->required()
        ->value(isset($item) ? __moeda($item->valor_integral) : '')
        !!}
    </div>

    <div class="col-md-2">
        {!!Form::date('data_vencimento', 'Vencimento')->required()
        !!}
    </div>

    <div class="col-md-2">
        {!!Form::select('tipo_pagamento', 'Tipo de pagamento', App\Models\ContaPagar::tiposPagamento())
        ->attrs(['class' => 'form-select'])
        !!}
    </div>

    {{-- pago/não pago só muda pelo botão "Pagar" da lista (lança a saída no extrato) --}}
    @if(\App\Http\Controllers\ContaPagarController::temDespesa())
    <div class="col-md-3">
        <label class="form-label" for="inp-despesa">Entra como despesa?</label>
        <select name="despesa" id="inp-despesa" class="form-select">
            <option value="1" @selected(!isset($item) || $item->despesa)>Sim (diminui o lucro do mês)</option>
            <option value="0" @selected(isset($item) && !$item->despesa)>Não (repasse ou custo do produto)</option>
        </select>
    </div>
    @endif

    @isset($item)
    {!! __view_locais_select_edit("Local", $item->filial_id) !!}
    @else
    {!! __view_locais_select() !!}
    @endif

    <hr>

    @if(!isset($item))
    <p class="text-danger">
        *Campo abaixo deve ser preenchido se ouver recorrência para este registro
    </p>

    <div class="col-md-2">
        {!!Form::tel('recorrencia', 'Data')
        ->attrs(['data-mask' => '00/00'])
        ->placeholder('mm/aa')
        !!}
    </div>
    @endif

    <div class="row tbl-recorrencia d-none mt-2">
    </div>

    <div class="col-12">
        <button type="submit" class="btn btn-primary px-5 float-end">Salvar</button>
    </div>
</div>
