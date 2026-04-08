<div class="modal fade" id="modal-endereco-cliente" aria-modal="true" role="dialog" style="overflow:scroll;" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Endereço do Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        {!! Form::tel('cep', 'CEP')->attrs(['class' => 'cep']) !!}
                    </div>
                    <div class="col-md-8">
                        {!! Form::text('rua', 'Rua') !!}
                    </div>
                    <div class="col-md-4">
                        {!! Form::tel('numero', 'Número') !!}
                    </div>
                    <div class="col-md-4">
                        {!! Form::text('bairro', 'Bairro') !!}
                    </div>
                    <div class="col-md-12">
                        {!! Form::text('complemento', 'Complemento') !!}
                    </div>
                    <div class="col-md-12">
                        {!! Form::select('cidade_id', 'Cidade')->attrs(['class' => 'select2'])->options([]) !!}
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary px-4" id="btn-update-endereco">Salvar</button>
            </div>
        </div>
    </div>
</div>


