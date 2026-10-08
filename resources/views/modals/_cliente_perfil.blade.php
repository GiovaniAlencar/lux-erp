<div class="modal fade" id="modal-perfil-cliente" aria-modal="true" role="dialog" style="overflow:scroll;" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Perfil do Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-12">
                        {!! Form::text('razao_social', 'Nome')->required() !!}
                    </div>
                    <div class="col-md-12">
                        {!! Form::text('cpf_cnpj', 'CPF/CNPJ')->attrs(['class' => 'cpf_cnpj']) !!}
                    </div>
                    <div class="col-md-6">
                        {!! Form::tel('celular', 'Celular')->attrs(['class' => 'fone']) !!}
                    </div>
                    <div class="col-md-6">
                        {!! Form::text('email', 'Email')->type('email') !!}
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary px-4" id="btn-update-perfil">Salvar</button>
            </div>
        </div>
    </div>
</div>


