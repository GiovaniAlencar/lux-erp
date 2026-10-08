@section('css')
<style type="text/css">
    .ck-editor__editable { width: 100%; min-height: 300px; }

    .produto-secao-titulo {
        font-size: .95rem;
        font-weight: 600;
        color: var(--color-default, #4f46e5);
        margin-bottom: .75rem;
        padding-bottom: .35rem;
        border-bottom: 1px solid #e9ecef;
    }

    .produto-precificacao {
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: .5rem;
        padding: 1rem 1.25rem;
        color: #111827;
    }

    .produto-preco-linha .preco-linha-label {
        font-weight: 600;
        font-size: .875rem;
        color: #374151;
        padding-top: 1.85rem;
    }

    .produto-custo-ref { max-width: 240px; }

    /* Grid e alturas uniformes (42px) */
    .produto-form-grid .row + .row { margin-top: 0; }

    .produto-form-grid .form-label,
    .produto-form-grid label.col-form-label,
    .produto-form-grid .form-group > label {
        font-size: .875rem;
        margin-bottom: .35rem;
    }

    .produto-form-grid .form-group { margin-bottom: 0; }

    .produto-form-grid input.form-control:not([type="file"]):not(textarea),
    .produto-form-grid select.form-select,
    .produto-form-grid .form-group .form-control:not(textarea),
    .produto-form-grid .form-group .form-select {
        height: 42px;
        min-height: 42px;
        padding: .375rem .75rem;
    }

    .produto-form-grid .input-group {
        flex-wrap: nowrap;
        align-items: stretch;
    }

    .produto-form-grid .input-group > .form-control {
        height: 42px;
        min-height: 42px;
        flex: 1 1 auto;
        min-width: 0;
    }

    .produto-form-grid .input-group > .btn {
        height: 42px;
        min-height: 42px;
        width: 42px;
        min-width: 42px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        border-top-left-radius: 0 !important;
        border-bottom-left-radius: 0 !important;
    }

    .produto-form-grid .input-group .select2-container {
        flex: 1 1 auto;
        width: 1% !important;
        min-width: 0;
    }

    .produto-form-grid .input-group .select2-container .select2-selection--single,
    .produto-form-grid .input-group .select2-container--bootstrap4 .select2-selection {
        height: 42px !important;
        min-height: 42px !important;
        display: flex !important;
        align-items: center;
        border-top-right-radius: 0 !important;
        border-bottom-right-radius: 0 !important;
    }

    .produto-form-grid .input-group .select2-container--default .select2-selection--single .select2-selection__rendered,
    .produto-form-grid .input-group .select2-container--bootstrap4 .select2-selection__rendered {
        line-height: 42px !important;
        padding-left: .75rem;
    }

    .produto-form-grid .input-group .select2-container--default .select2-selection--single .select2-selection__arrow,
    .produto-form-grid .input-group .select2-container--bootstrap4 .select2-selection--arrow {
        height: 40px;
        top: 50%;
        transform: translateY(-50%);
    }

    .produto-form-grid input.form-control.bg-light {
        height: 42px;
        background-color: #f3f4f6 !important;
        color: #374151;
    }

    /* Tema escuro */
    html.dark-theme .produto-secao-titulo {
        border-bottom-color: rgba(255, 255, 255, 0.12);
        color: var(--color-default, #c4b5fd);
    }

    html.dark-theme .produto-precificacao {
        background: #1a1a1a;
        border-color: rgba(255, 255, 255, 0.14);
        color: #f3f4f6;
    }

    html.dark-theme .produto-preco-linha .preco-linha-label {
        color: #f3f4f6;
    }

    html.dark-theme .produto-precificacao .text-muted,
    html.dark-theme .produto-precificacao small {
        color: #9ca3af !important;
    }

    html.dark-theme .produto-precificacao .form-label,
    html.dark-theme .produto-precificacao label {
        color: #e5e7eb !important;
    }

    html.dark-theme .produto-precificacao hr {
        border-color: rgba(255, 255, 255, 0.12);
        opacity: 1;
    }

    html.dark-theme .produto-form-grid input.form-control.bg-light {
        background-color: rgba(255, 255, 255, 0.08) !important;
        color: #f3f4f6 !important;
        border-color: rgba(255, 255, 255, 0.22);
    }

    html.dark-theme .produto-form-grid .input-group .select2-container--bootstrap4 .select2-selection,
    html.dark-theme .produto-form-grid .input-group .select2-container .select2-selection--single {
        background-color: #1e1e1e;
        border-color: rgba(255, 255, 255, 0.22);
        color: #e5e7eb;
    }
</style>
@endsection

@php
$config = App\Models\ConfigNota::configStatic();
$custoItem = isset($item) ? (float) $item->valor_compra : 0;
$percAtacado1 = '';
$percAtacado2 = '';
if (isset($item) && $custoItem > 0) {
    if ($item->preco_2 !== null) {
        $percAtacado1 = __moeda((($item->preco_2 - $custoItem) / $custoItem) * 100);
    }
    if ($item->preco_3 !== null) {
        $percAtacado2 = __moeda((($item->preco_3 - $custoItem) / $custoItem) * 100);
    }
}
$trib = $tributacao ?? null;
@endphp

<div class="row g-3">
<div class="col-12 produto-form-grid">
    <input type="hidden" value="{{ csrf_token() }}" id="token">
    <input type="hidden" name="reajuste_automatico" id="inp-reajuste_automatico" value="1">
    <input type="hidden" name="unidade_compra" value="{{ isset($item) ? $item->unidade_compra : 'UN' }}">
    <input type="hidden" name="unidade_venda" value="{{ isset($item) ? $item->unidade_venda : 'UN' }}">
    <input type="hidden" name="conversao_unitaria" value="{{ isset($item) ? $item->conversao_unitaria : 1 }}">

    <p class="mb-0 mt-2" style="color: crimson">* Campos obrigatórios</p>

    {{-- Identificação --}}
    <div class="produto-secao-titulo mt-3">Identificação</div>

    <div class="row g-3">
        @isset($item)
        <div class="col-md-2">
            <label class="form-label">ID</label>
            <input type="text" class="form-control bg-light" value="{{ $item->id }}" readonly tabindex="-1">
        </div>
        <div class="col-md-6">
            {!! Form::text('nome', 'Descrição')->required() !!}
        </div>
        @else
        <div class="col-md-8">
            {!! Form::text('nome', 'Descrição')->required() !!}
        </div>
        @endisset
        <div class="col-md-4">
            <label for="inp-codBarras" class="form-label">Código de barras</label>
            <div class="input-group">
                <input type="tel" id="inp-codBarras" class="form-control ignore" name="codBarras" value="{{ isset($item) ? $item->codBarras : '' }}">
                <button type="button" class="btn btn-primary" id="btn-codBarras" title="Gerar código">
                    <i class="bx bx-barcode-reader"></i>
                </button>
            </div>
        </div>
    </div>

    @if(empresaComFilial())
    <div class="row g-3">
        @isset($item)
            {!! __view_locais_edit($item->locais, 'Disponibilidade') !!}
        @else
            {!! __view_locais('Disponibilidade') !!}
        @endisset
    </div>
    @else
        @isset($item)
            {!! __view_locais_edit($item->locais, 'Disponibilidade') !!}
        @else
            {!! __view_locais('Disponibilidade') !!}
        @endisset
    @endif

    <div class="row g-3">
        <div class="col-md-4">
            <label for="inp-categoria_id" class="form-label required">Categoria</label>
            <div class="input-group">
                <select class="form-control select2" name="categoria_id" id="inp-categoria_id" required>
                    <option value="">Selecione a categoria</option>
                    @foreach ($categorias as $c)
                    <option @isset($item) @if ($item->categoria_id == $c->id) selected @endif @endif value="{{ $c->id }}">{{ $c->nome }}</option>
                    @endforeach
                </select>
                @if (!isset($not_submit))
                <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modal-categoria" title="Nova categoria">
                    <i class="bx bx-plus"></i>
                </button>
                @endif
            </div>
        </div>
        <div class="col-md-4">
            <label for="inp-sub_categoria_id" class="form-label">Sub categoria</label>
            <div class="input-group">
                <select class="form-control select2 ignore" name="sub_categoria_id" id="inp-sub_categoria_id">
                    <option value="">Selecione</option>
                    @isset($item)
                    <option selected value="{{ $item->sub_categoria_id }}">{{ $item->subCategoria }}</option>
                    @endif
                </select>
                @if (!isset($not_submit))
                <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modal-sub_categoria" title="Nova subcategoria">
                    <i class="bx bx-plus"></i>
                </button>
                @endif
            </div>
        </div>
        <div class="col-md-4">
            <label for="inp-marca_id" class="form-label">Marca</label>
            <div class="input-group">
                <select class="form-control select2 ignore" name="marca_id" id="inp-marca_id">
                    <option value="">Selecione</option>
                    @isset($marcas)
                    @foreach ($marcas as $m)
                    <option value="{{ $m->id }}" @isset($item) @if($item->marca_id == $m->id) selected @endif @endif>{{ $m->nome }}</option>
                    @endforeach
                    @endisset
                </select>
                @if (!isset($not_submit))
                <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modal-marca" title="Nova marca">
                    <i class="bx bx-plus"></i>
                </button>
                @endif
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-4">
            {!! Form::select('gerenciar_estoque', 'Gerenciar estoque', [0 => 'Não', 1 => 'Sim'])->attrs(['class' => 'form-select']) !!}
        </div>
        <div class="col-md-4">
            {!! Form::select('inativo', 'Inativo', [0 => 'Não', 1 => 'Sim'])->attrs(['class' => 'form-select']) !!}
        </div>
        @isset($item)
        <div class="d-none">
            {!! Form::select('grade', 'Tipo grade', [0 => 'Não', 1 => 'Sim'])->attrs(['class' => 'form-select']) !!}
        </div>
        @else
        <div class="col-md-4">
            {!! Form::select('grade', 'Tipo grade', [0 => 'Não', 1 => 'Sim'])->attrs(['class' => 'form-select']) !!}
        </div>
        @endisset
    </div>

    {{-- Precificação --}}
    <div class="produto-secao-titulo mt-4">Precificação</div>
    <div class="produto-precificacao">
            <div class="row g-3">
                <div class="col-md-3 produto-custo-ref">
                    {!! Form::tel('valor_compra', 'Custo (referência)')->attrs(['class' => 'moeda'])->value(isset($item) ? __moeda($item->valor_compra) : '')->required() !!}
                </div>
                <div class="col-md-9 d-flex align-items-end">
                    <small class="text-muted mb-2">
                        <i class="bx bx-info-circle"></i>
                        Altere o custo, o % de lucro ou o preço — os demais campos da mesma linha são recalculados e arredondados em 2 casas.
                    </small>
                </div>
            </div>
            <hr class="my-2 text-muted">

            <div class="row g-3 align-items-end produto-preco-linha mb-2">
                <div class="col-md-2 preco-linha-label">Normal</div>
                <div class="col-md-4">
                    {!! Form::tel('percentual_lucro', '% de lucro')->value(isset($item) ? __moeda($item->percentual_lucro) : $config->percentual_lucro_padrao)->attrs(['class' => 'perc js-preco-perc'])->required() !!}
                </div>
                <div class="col-md-4">
                    {!! Form::tel('valor_venda', 'Preço')->attrs(['class' => 'moeda js-preco-valor'])->value(isset($item) ? __moeda($item->valor_venda) : '')->required() !!}
                </div>
            </div>

            <div class="row g-3 align-items-end produto-preco-linha mb-2">
                <div class="col-md-2 preco-linha-label">Atacado 1</div>
                <div class="col-md-4">
                    <label for="inp-percentual_lucro_atacado_1" class="form-label">% de lucro</label>
                    <input type="tel" class="form-control perc js-preco-perc" id="inp-percentual_lucro_atacado_1" data-par="atacado_1" value="{{ $percAtacado1 }}">
                </div>
                <div class="col-md-4">
                    {!! Form::tel('preco_2', 'Preço')->attrs(['class' => 'moeda js-preco-valor', 'data-par' => 'atacado_1'])->value(isset($item) && $item->preco_2 !== null ? __moeda($item->preco_2) : '') !!}
                </div>
            </div>

            <div class="row g-3 align-items-end produto-preco-linha">
                <div class="col-md-2 preco-linha-label">Atacado 2</div>
                <div class="col-md-4">
                    <label for="inp-percentual_lucro_atacado_2" class="form-label">% de lucro</label>
                    <input type="tel" class="form-control perc js-preco-perc" id="inp-percentual_lucro_atacado_2" data-par="atacado_2" value="{{ $percAtacado2 }}">
                </div>
                <div class="col-md-4">
                    {!! Form::tel('preco_3', 'Preço')->attrs(['class' => 'moeda js-preco-valor', 'data-par' => 'atacado_2'])->value(isset($item) && $item->preco_3 !== null ? __moeda($item->preco_3) : '') !!}
                </div>
            </div>
        </div>

    {{-- Dimensões (Melhor Envio) --}}
    <div class="produto-secao-titulo mt-4">Dimensões e peso <small class="text-muted fw-normal">(opcional — Melhor Envio)</small></div>
    <div class="row g-3">
        <div class="col-md-2">
            {!! Form::select('tipo_dimensao', 'Tipo', [2 => '--', 1 => 'Área', 0 => 'Dimensão'])->value(isset($item) ? $item->tipo_dimensao : 2)->attrs(['class' => 'form-select']) !!}
        </div>
        <div class="col-md-2">
            {!! Form::tel('largura', 'Largura (cm)')->value(isset($item) ? $item->largura : '')->attrs(['class' => 'tel']) !!}
        </div>
        <div class="col-md-2">
            {!! Form::tel('altura', 'Altura (cm)')->value(isset($item) ? $item->altura : '')->attrs(['class' => 'tel']) !!}
        </div>
        <div class="col-md-2">
            {!! Form::tel('comprimento', 'Comprimento (cm)')->value(isset($item) ? $item->comprimento : '')->attrs(['class' => 'tel']) !!}
        </div>
        <div class="col-md-2">
            {!! Form::tel('peso_liquido', 'Peso líquido (kg)')->value(isset($item) ? $item->peso_liquido : '')->attrs(['class' => 'tel']) !!}
        </div>
        <div class="col-md-2">
            {!! Form::tel('peso_bruto', 'Peso bruto (kg)')->value(isset($item) ? $item->peso_bruto : '')->attrs(['class' => 'tel']) !!}
        </div>
    </div>

    {{-- Imagem --}}
    <div class="produto-secao-titulo mt-4">Imagem</div>
    @if (!isset($not_submit))
    <div id="image-preview" class="_image-preview col-md-4">
        <label for="" id="image-label" class="_image-label">Selecione a imagem</label>
        <input type="file" name="image" id="image-upload" class="_image-upload" accept="image/*" />
        @isset($item)
        @if ($item->imagem)
        <img src="/uploads/products/{{ $item->imagem }}" class="img-default">
        @else
        <img src="/imgs/no_product.png" class="img-default">
        @endif
        @else
        <img src="/imgs/no_product.png" class="img-default">
        @endif
    </div>
    @endif

    {{-- Campos legados (mantidos no banco, ocultos na tela) --}}
    @isset($item)
    <input type="hidden" name="referencia" value="{{ $item->referencia }}">
    <input type="hidden" name="estoque_minimo" value="{{ $item->estoque_minimo }}">
    <input type="hidden" name="limite_maximo_desconto" value="{{ $item->limite_maximo_desconto }}">
    <input type="hidden" name="alerta_vencimento" value="{{ $item->alerta_vencimento }}">
    <input type="hidden" name="CEST" value="{{ $item->CEST }}">
    <input type="hidden" name="referencia_balanca" value="{{ $item->referencia_balanca }}">
    <input type="hidden" name="perc_comissao" value="{{ $item->perc_comissao }}">
    <input type="hidden" name="envia_controle_pedidos" value="{{ $item->envia_controle_pedidos }}">
    <input type="hidden" name="tela_pedido_id" value="{{ $item->tela_pedido_id }}">
    <input type="hidden" name="delivery" value="0">
    <input type="hidden" name="locacao" value="{{ $item->locacao }}">
    <input type="hidden" name="valor_locacao" value="{{ $item->valor_locacao }}">
    <input type="hidden" name="composto" value="{{ $item->composto }}">
    <input type="hidden" name="derivado_petroleo" value="{{ $item->derivado_petroleo }}">
    <input type="hidden" name="ecommerce" value="0">
    <input type="hidden" name="NCM" value="{{ $item->NCM }}">
    <input type="hidden" name="CST_CSOSN" value="{{ $item->CST_CSOSN }}">
    <input type="hidden" name="CST_PIS" value="{{ $item->CST_PIS }}">
    <input type="hidden" name="CST_COFINS" value="{{ $item->CST_COFINS }}">
    <input type="hidden" name="CST_IPI" value="{{ $item->CST_IPI }}">
    <input type="hidden" name="CST_CSOSN_EXP" value="{{ $item->CST_CSOSN_EXP }}">
    <input type="hidden" name="CST_PIS_entrada" value="{{ $item->CST_PIS_entrada }}">
    <input type="hidden" name="CST_COFINS_entrada" value="{{ $item->CST_COFINS_entrada }}">
    <input type="hidden" name="CST_IPI_entrada" value="{{ $item->CST_IPI_entrada }}">
    @else
    <input type="hidden" name="referencia" value="">
    <input type="hidden" name="estoque_minimo" value="0">
    <input type="hidden" name="limite_maximo_desconto" value="0">
    <input type="hidden" name="alerta_vencimento" value="0">
    <input type="hidden" name="CEST" value="">
    <input type="hidden" name="referencia_balanca" value="0">
    <input type="hidden" name="perc_comissao" value="0">
    <input type="hidden" name="envia_controle_pedidos" value="0">
    <input type="hidden" name="tela_pedido_id" value="0">
    <input type="hidden" name="delivery" value="0">
    <input type="hidden" name="locacao" value="0">
    <input type="hidden" name="valor_locacao" value="0">
    <input type="hidden" name="composto" value="0">
    <input type="hidden" name="derivado_petroleo" value="0">
    <input type="hidden" name="ecommerce" value="0">
    <input type="hidden" name="NCM" value="{{ $trib?->ncm_padrao ?? '' }}">
    <input type="hidden" name="CST_CSOSN" value="{{ $config->CST_CSOSN_padrao }}">
    <input type="hidden" name="CST_PIS" value="{{ $config->CST_PIS_padrao }}">
    <input type="hidden" name="CST_COFINS" value="{{ $config->CST_COFINS_padrao }}">
    <input type="hidden" name="CST_IPI" value="{{ $config->CST_IPI_padrao }}">
    <input type="hidden" name="CST_CSOSN_EXP" value="{{ $config->CST_CSOSN_padrao }}">
    <input type="hidden" name="CST_PIS_entrada" value="{{ $config->CST_PIS_padrao }}">
    <input type="hidden" name="CST_COFINS_entrada" value="{{ $config->CST_COFINS_padrao }}">
    <input type="hidden" name="CST_IPI_entrada" value="{{ $config->CST_IPI_padrao }}">
    @endisset

    <input type="hidden" class="divisoes ignore" id="divisoes" value="{{ json_encode($divisoes) }}" name="">
    <input type="hidden" class="subDivisoes ignore" id="subDivisoes" value="{{ json_encode($subDivisoes) }}" name="">

    <div class="mt-5">
        @isset($not_submit)
        <button type="button" class="btn btn-primary px-5" id="btn-store-produto">Salvar</button>
        @else
        <button type="submit" class="btn btn-primary px-5">Salvar</button>
        @endif
    </div>
</div>
</div>

@section('js')
<script src="/js/grade.js"></script>
<script src="/js/product.js"></script>
<script type="text/javascript" src="/assets/js/jquery.uploadPreview.min.js"></script>
@endsection

@include('modals._categoria')
@include('modals._sub_categoria')
@include('modals._marca')
@include('modals._grade')
@include('modals._grade2')
