<div class="row g-3">
    <div class="col-md-6">
        <label for="inp-produto_id" class="required">Produto</label>
        <select name="produto_id" id="inp-produto_id" class="form-control produto_id" required>
            @isset($item)
            <option value="{{ $item->produto_id }}" selected>{{ optional($item->produto)->nome }}</option>
            @endisset
        </select>
    </div>
    <div class="col-md-3">
        {!! Form::select('tipo', 'Tipo de promoção', \App\Models\Promocao::tipos())->attrs(['class' => 'form-select']) !!}
    </div>
    <div class="col-md-3">
        {!! Form::tel('valor', 'Valor (R$ ou %)')->attrs(['class' => 'moeda'])->required() !!}
    </div>
    <div class="col-md-3">
        {!! Form::date('data_inicio', 'Desde quando')->required() !!}
    </div>
    <div class="col-md-3">
        {!! Form::date('data_fim', 'Até quando')->required() !!}
    </div>
    <div class="col-md-3">
        {!! Form::tel('quantidade_limite', 'Para quantas unidades')->attrs(['placeholder' => 'Deixe em branco para ilimitado']) !!}
    </div>
    <div class="col-md-3">
        {!! Form::select('ativo', 'Status', ['1' => 'Ativa', '0' => 'Desativada']) !!}
    </div>
    <div class="col-md-12">
        {!! Form::text('observacao', 'Observação (opcional)') !!}
    </div>
    <div class="col-12 mt-4">
        <button class="btn btn-info px-5">Salvar</button>
    </div>
</div>
