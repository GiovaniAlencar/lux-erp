<div class="row g-3">
    <div class="col-md-4">
        {!! Form::text('cpf_cnpj', 'CPF/CNPJ')->required()->attrs(['class' => 'cpf_cnpj']) !!}
    </div>
    <div class="col-md-8">
        {!! Form::text('razao_social', 'Razão social')->required()->attrs(['class' => '']) !!}
    </div>

    <div class="col-md-4">
        {!! Form::tel('celular', 'Celular')->attrs(['class' => 'fone']) !!}
    </div>

    <div class="col-12"><hr></div>
    <h5>Endereço</h5>

    <div class="col-md-2">
        {!! Form::tel('cep', 'CEP')->attrs(['class' => 'cep'])->required() !!}
    </div>
    <div class="col-md-6">
        {!! Form::text('rua', 'Rua')->required() !!}
    </div>
    <div class="col-md-2">
        {!! Form::tel('numero', 'Número')->required() !!}
    </div>
    <div class="col-md-2">
        {!! Form::text('bairro', 'Bairro')->required() !!}
    </div>
    <div class="col-md-4">
        {!! Form::text('complemento', 'Complemento')->attrs(['class' => 'ignore']) !!}
    </div>
    <div class="col-md-4">
        {!! Form::select('cidade_id', 'Cidade')->required()->options([])->attrs(['class' => 'select2']) !!}
    </div>

    <div class="col-12 mt-4">
        <button type="button" class="btn btn-primary px-5" id="btn-store-cliente">Salvar</button>
    </div>
</div>


