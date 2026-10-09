@extends('default.layout', ['title' => 'Importar dados fiscais (XML)'])
@section('content')
<div class="page-content">
    <div class="card border-top border-0 border-4 border-primary">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-0 text-primary">Importar dados fiscais pelo XML</h5>
                    <small class="text-muted">Preenche NCM, CEST, origem, CSOSN, CFOP e PIS/COFINS dos produtos a partir de notas fiscais — com conferência antes de gravar.</small>
                </div>
                <a href="{{ route('produtos.index') }}" class="btn btn-light btn-sm"><i class="bx bx-arrow-back"></i> Produtos</a>
            </div>

            <div class="row g-4">
                <div class="col-lg-6">
                    <form method="post" action="{{ route('produtos-fiscal-xml.analisar') }}" enctype="multipart/form-data">
                        @csrf
                        <label class="form-label fw-semibold">Arquivos XML de NF-e</label>
                        <input type="file" name="xmls[]" class="form-control" accept=".xml,text/xml" multiple required>
                        <div class="form-text">Pode selecionar vários de uma vez (até 30). Itens repetidos aparecem uma vez só.</div>
                        @error('xmls')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        <button class="btn btn-primary mt-3" type="submit"><i class="bx bx-search-alt"></i> Ler XMLs e conferir</button>
                    </form>
                </div>
                <div class="col-lg-6">
                    <div class="border rounded p-3 small bg-light">
                        <div class="fw-semibold mb-2">Como os dados são usados</div>
                        <ul class="mb-2 ps-3">
                            <li><strong>NF emitida pela LUX</strong> (ex.: do outro sistema): usa tudo da nota — NCM, CEST, origem, CSOSN, CFOP e CST de PIS/COFINS.</li>
                            <li><strong>NF de fornecedor</strong> (compra): usa NCM, CEST, código de barras e origem (importação direta <code>1</code> vira <code>2</code> na revenda). CSOSN/CFOP são sugeridos: <code>102</code> / <code>5102·6102</code>, ou <code>500</code> / <code>5405·6404</code> se a nota indicar substituição tributária.</li>
                        </ul>
                        Os itens são ligados aos produtos pelo <strong>código de barras</strong> ou pelo <strong>nome</strong>. Na próxima tela você confere, troca o produto se precisar, ajusta qualquer campo e escolhe quais gravar. Confirme a tributação com o seu contador.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
