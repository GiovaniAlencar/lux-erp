{{-- Dados fiscais (NF-e). Sempre enviados; obrigatórios quando "Fiscal" = Sim. --}}
@php
    $__pf = isset($item) ? $item : null;
    $__v = function ($campo, $padrao = '') use ($__pf) {
        $old = old($campo);
        if ($old !== null) return $old;
        return $__pf ? ($__pf->{$campo} ?? $padrao) : $padrao;
    };
    $__csosnLista = collect(\App\Models\Produto::listaCSTCSOSN())->only(\App\Support\ProdutoFiscal::CSOSN_SIMPLES)->all();
@endphp
<div class="lux-dados-fiscais mt-4 {{ (int) $__v('fiscal', 0) === 1 ? '' : 'd-none' }}">
    <div class="produto-secao-titulo">Dados fiscais (NF-e) <small class="text-muted fw-normal">— obrigatórios para produto fiscal</small></div>
    <div class="alert alert-light border small py-2 mb-3">
        Simples Nacional. Dica: dá para preencher automaticamente em <a href="{{ route('produtos-fiscal-xml.index') }}" target="_blank">Produtos › Importar dados fiscais (XML)</a>.
    </div>
    <div class="row g-3">
        <div class="col-md-3">
            <label class="form-label lux-fiscal-req" for="inp-NCM">NCM</label>
            <input type="text" name="NCM" id="inp-NCM" class="form-control @error('NCM') is-invalid @enderror" maxlength="10" value="{{ $__v('NCM', $trib?->ncm_padrao ?? '') }}" placeholder="33030010">
            @error('NCM')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
            <label class="form-label" for="inp-CEST">CEST <small class="text-muted">(se tiver ST)</small></label>
            <input type="text" name="CEST" id="inp-CEST" class="form-control @error('CEST') is-invalid @enderror" maxlength="9" value="{{ $__v('CEST') }}" placeholder="2002000">
            @error('CEST')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label lux-fiscal-req" for="inp-origem">Origem</label>
            <select name="origem" id="inp-origem" class="form-select @error('origem') is-invalid @enderror">
                @foreach(\App\Models\Produto::origens() as $k => $lbl)
                <option value="{{ $k }}" @selected((string) $__v('origem', '0') === (string) $k)>{{ $k }} - {{ \Illuminate\Support\Str::limit($lbl, 70) }}</option>
                @endforeach
            </select>
            @error('origem')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label lux-fiscal-req" for="inp-CST_CSOSN">CSOSN</label>
            <select name="CST_CSOSN" id="inp-CST_CSOSN" class="form-select @error('CST_CSOSN') is-invalid @enderror">
                <option value="">Selecione</option>
                @foreach($__csosnLista as $k => $lbl)
                <option value="{{ $k }}" @selected((string) $__v('CST_CSOSN', $config->CST_CSOSN_padrao ?? '') === (string) $k)>{{ \Illuminate\Support\Str::limit($lbl, 90) }}</option>
                @endforeach
            </select>
            @error('CST_CSOSN')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
            <label class="form-label lux-fiscal-req" for="inp-CFOP_saida_estadual">CFOP dentro do estado</label>
            <input type="text" name="CFOP_saida_estadual" id="inp-CFOP_saida_estadual" class="form-control @error('CFOP_saida_estadual') is-invalid @enderror" maxlength="5" value="{{ $__v('CFOP_saida_estadual', '5102') }}" placeholder="5102">
            @error('CFOP_saida_estadual')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
            <label class="form-label lux-fiscal-req" for="inp-CFOP_saida_inter_estadual">CFOP fora do estado</label>
            <input type="text" name="CFOP_saida_inter_estadual" id="inp-CFOP_saida_inter_estadual" class="form-control @error('CFOP_saida_inter_estadual') is-invalid @enderror" maxlength="5" value="{{ $__v('CFOP_saida_inter_estadual', '6102') }}" placeholder="6102">
            @error('CFOP_saida_inter_estadual')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label lux-fiscal-req" for="inp-CST_PIS">CST PIS</label>
            <select name="CST_PIS" id="inp-CST_PIS" class="form-select @error('CST_PIS') is-invalid @enderror">
                @foreach(\App\Models\Produto::listaCST_PIS_COFINS() + ['99' => '99 - Outras Operações'] as $k => $lbl)
                <option value="{{ $k }}" @selected((string) $__v('CST_PIS', $config->CST_PIS_padrao ?? '49') === (string) $k)>{{ \Illuminate\Support\Str::limit($lbl, 60) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label lux-fiscal-req" for="inp-CST_COFINS">CST COFINS</label>
            <select name="CST_COFINS" id="inp-CST_COFINS" class="form-select @error('CST_COFINS') is-invalid @enderror">
                @foreach(\App\Models\Produto::listaCST_PIS_COFINS() + ['99' => '99 - Outras Operações'] as $k => $lbl)
                <option value="{{ $k }}" @selected((string) $__v('CST_COFINS', $config->CST_COFINS_padrao ?? '49') === (string) $k)>{{ \Illuminate\Support\Str::limit($lbl, 60) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="inp-CST_IPI">CST IPI</label>
            <select name="CST_IPI" id="inp-CST_IPI" class="form-select">
                @foreach(\App\Models\Produto::listaCST_IPI() as $k => $lbl)
                <option value="{{ $k }}" @selected((string) $__v('CST_IPI', $config->CST_IPI_padrao ?? '99') === (string) $k)>{{ $lbl }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>
<style>.lux-fiscal-req::after{content:' *';color:crimson}</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var sel = document.querySelector('select[name="fiscal"]');
    var box = document.querySelector('.lux-dados-fiscais');
    if (!sel || !box) return;
    var toggle = function () { box.classList.toggle('d-none', sel.value !== '1'); };
    sel.addEventListener('change', toggle);
    if (window.jQuery) jQuery(sel).on('change', toggle);
    toggle();
});
</script>
