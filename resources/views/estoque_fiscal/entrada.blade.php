@extends('default.layout', ['title' => 'Entrada no estoque fiscal'])
@section('content')
<div class="page-content">
    <div class="card border-top border-0 border-4 border-primary">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-0 text-primary">Entrada no estoque fiscal pelo XML</h5>
                    <small class="text-muted">Suba o XML da nota de compra do fornecedor. Soma as quantidades no saldo com nota — não mexe no estoque normal nem no custo. A mesma nota não entra duas vezes.</small>
                </div>
                <a href="{{ route('estoque-fiscal.index') }}" class="btn btn-light btn-sm"><i class="bx bx-arrow-back"></i> Estoque fiscal</a>
            </div>
            <form method="post" action="{{ route('estoque-fiscal.entrada.analisar') }}" enctype="multipart/form-data" class="col-lg-6">
                @csrf
                <input type="file" name="xmls[]" class="form-control" accept=".xml,text/xml" multiple required>
                <div class="form-text">Até 20 notas por vez. Dica: o XML pode ser baixado em Entradas › Notas recebidas (SEFAZ).</div>
                <button class="btn btn-primary mt-3" type="submit"><i class="bx bx-search-alt"></i> Ler e conferir</button>
            </form>
        </div>
    </div>
</div>
@endsection
