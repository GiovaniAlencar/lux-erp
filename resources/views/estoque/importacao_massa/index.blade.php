@extends('default.layout', ['title' => 'Importação em Massa'])
@section('css')
<style>
    .imp-massa-preview-wrap { overflow-x: auto; max-height: 420px; }
    .imp-massa-preview-wrap table { font-size: .78rem; white-space: nowrap; }
    .imp-massa-preview-wrap th { position: sticky; top: 0; background: #f8f9fa; z-index: 1; }
    html.dark-theme .imp-massa-preview-wrap th { background: #2a2a2a; }
    tr.linha-erro { background: #fee2e2 !important; }
    html.dark-theme tr.linha-erro { background: #451a1a !important; }
    .imp-massa-resumo .card { border: 1px solid #e5e7eb; }
    .btn-file { position: relative; overflow: hidden; }
    .btn-file input[type=file] {
        position: absolute; top: 0; right: 0; min-width: 100%; min-height: 100%;
        opacity: 0; cursor: pointer;
    }
</style>
@endsection
@section('content')
<div class="page-content">
    <div class="card border-top border-0 border-4 border-primary">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-0 text-primary">Importação em Massa</h5>
                    <small class="text-muted">Atualize estoque, custo e preços via CSV — com pré-visualização obrigatória.</small>
                </div>
                <a href="{{ route('estoque.index') }}" class="btn btn-light btn-sm"><i class="bx bx-arrow-back"></i> Voltar</a>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-8">
                    <div class="card h-100">
                        <div class="card-body">
                            <h6 class="mb-3">Arquivo CSV</h6>
                            <div class="row g-3 mb-3">
                                <div class="col-md-8">
                                    <label class="form-label small mb-1">Fornecedor <span class="text-danger">*</span></label>
                                    <select class="form-control select2" id="sel-fornecedor-import" required>
                                        <option value="">Selecione o fornecedor</option>
                                        @foreach($fornecedores as $f)
                                        <option value="{{ $f->id }}">{{ $f->razao_social }} @if($f->cpf_cnpj)- {{ $f->cpf_cnpj }}@endif</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <p class="small text-muted mb-3">
                                Colunas: <code>codigo,quantidade,custo,preco_1,preco_2,preco_3</code> — o código é o <strong>ID</strong> do produto no sistema.
                            </p>
                            <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                                <a href="{{ route('estoque.importacaoMassa.modelo') }}" class="btn btn-outline-primary btn-sm">
                                    <i class="bx bx-download"></i> Exportar Modelo CSV
                                </a>
                                <span class="btn btn-dark btn-sm btn-file">
                                    <i class="bx bx-file"></i> Selecionar Arquivo
                                    <input type="file" id="inp-arquivo-csv" accept=".csv,text/csv">
                                </span>
                                <span class="small text-muted" id="lbl-arquivo-nome">Nenhum arquivo</span>
                            </div>
                            <button type="button" class="btn btn-primary" id="btn-processar" disabled>
                                <i class="bx bx-cog"></i> Processar Arquivo
                            </button>

                            <div class="mt-3 d-none" id="box-progresso">
                                <div class="progress mb-2" style="height: 8px;">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated" id="bar-progresso" style="width: 0%"></div>
                                </div>
                                <div class="small text-muted" id="txt-progresso">Lendo arquivo...</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 bg-light">
                        <div class="card-body small">
                            <h6>Regras</h6>
                            <ul class="mb-0 ps-3">
                                <li>Estoque: <strong>soma</strong> a quantidade informada</li>
                                <li>Custo e preços: valor <strong>exato</strong> do CSV</li>
                                <li>Gera <strong>compra</strong> nas movimentações do produto</li>
                                <li>Sem recálculo automático de margem</li>
                                <li>Nada é gravado antes da confirmação</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div id="secao-preview" class="d-none">
                <h6 class="mb-2">Pré-visualização</h6>
                <div class="imp-massa-preview-wrap mb-3">
                    <table class="table table-bordered table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Produto</th>
                                <th>Estoque Atual</th>
                                <th>Entrada</th>
                                <th>Estoque Final</th>
                                <th>Custo Atual</th>
                                <th>Novo Custo</th>
                                <th>Preço Atual 1</th>
                                <th>Novo Preço 1</th>
                                <th>Preço Atual 2</th>
                                <th>Novo Preço 2</th>
                                <th>Preço Atual 3</th>
                                <th>Novo Preço 3</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-preview"></tbody>
                    </table>
                </div>

                <div class="row g-2 imp-massa-resumo mb-3" id="box-resumo"></div>

                <form method="post" action="{{ route('estoque.importacaoMassa.confirmar') }}" id="form-confirmar">
                    @csrf
                    <input type="hidden" name="token" id="inp-token-confirmar">
                    <input type="hidden" name="fornecedor_id" id="inp-fornecedor-confirmar">
                    <button type="button" class="btn btn-success btn-lg" id="btn-confirmar" disabled>
                        <i class="bx bx-check-circle"></i> Confirmar Importação
                    </button>
                </form>
            </div>

            <hr class="my-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <h6 class="mb-0">Histórico de importações</h6>
                <span class="small text-muted">{{ $historico->total() }} registro(s)</span>
            </div>

            <form method="get" action="{{ route('estoque.importacaoMassa.index') }}" class="row g-2 align-items-end mb-3">
                <div class="col-md-4">
                    <label class="form-label small mb-1" for="filt-fornecedor">Fornecedor</label>
                    <select class="form-control select2-filtro" name="fornecedor_id" id="filt-fornecedor">
                        <option value="">Todos</option>
                        @foreach($fornecedores as $f)
                        <option value="{{ $f->id }}" @selected(request('fornecedor_id') == $f->id)>
                            {{ $f->razao_social }} @if($f->cpf_cnpj)- {{ $f->cpf_cnpj }}@endif
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1" for="filt-start-date">Data inicial</label>
                    <input type="date" class="form-control form-control-sm" name="start_date" id="filt-start-date"
                        value="{{ request('start_date') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1" for="filt-end-date">Data final</label>
                    <input type="date" class="form-control form-control-sm" name="end_date" id="filt-end-date"
                        value="{{ request('end_date') }}">
                </div>
                <div class="col-md-4 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bx bx-search"></i> Filtrar</button>
                    <a href="{{ route('estoque.importacaoMassa.index') }}" class="btn btn-light btn-sm"><i class="bx bx-eraser"></i> Limpar</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-sm table-striped">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Hora</th>
                            <th>Usuário</th>
                            <th>Fornecedor</th>
                            <th>Itens</th>
                            <th>Unidades</th>
                            <th>Valor total</th>
                            <th>Compra</th>
                            <th>Arquivo</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($historico as $h)
                        <tr>
                            <td class="text-nowrap">{{ $h->confirmado_em ? __data_pt($h->confirmado_em, false) : '—' }}</td>
                            <td class="text-nowrap">{{ $h->confirmado_em ? \Carbon\Carbon::parse($h->confirmado_em)->format('H:i') : '—' }}</td>
                            <td>{{ $h->usuario->nome ?? '—' }}</td>
                            <td class="small">{{ $h->fornecedor->razao_social ?? '—' }}</td>
                            <td>{{ $h->qtd_itens_validos }} / {{ $h->qtd_itens }}</td>
                            <td>{{ number_format($h->qtd_unidades, 0, ',', '.') }}</td>
                            <td class="text-nowrap">R$ {{ __moeda($h->valor_total_compra) }}</td>
                            <td>
                                @if($h->compra_id)
                                <a href="{{ route('compras.show', $h->compra_id) }}">#{{ $h->compra_id }}</a>
                                @else
                                —
                                @endif
                            </td>
                            <td class="small text-truncate" style="max-width:140px" title="{{ $h->arquivo_nome ?? '' }}">{{ $h->arquivo_nome ?? '—' }}</td>
                            <td class="text-nowrap">
                                <a href="{{ route('estoque.importacaoMassa.show', $h->id) }}" class="btn btn-outline-primary btn-sm">Ver itens</a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">
                                Nenhuma importação encontrada{{ request()->hasAny(['fornecedor_id', 'start_date', 'end_date']) ? ' com os filtros informados' : '' }}.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($historico->hasPages())
            <div class="d-flex justify-content-center">
                {{ $historico->links() }}
            </div>
            @endif
        </div>
    </div>
</div>

<div class="modal fade" id="modalConfirmarImportacao" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar importação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Deseja realmente aplicar as alterações?</p>
                <p class="mb-0"><strong id="modal-qtd-produtos">0</strong> produtos serão atualizados.</p>
                <p class="text-danger small mt-2 mb-0">Esta ação não poderá ser desfeita.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="btn-modal-confirmar">Confirmar</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
@if(session()->has('flash_sucesso'))
toastr.success(@json(session('flash_sucesso')));
@endif
@if(session()->has('flash_erro'))
toastr.error(@json(session('flash_erro')));
@endif

(function () {
    const csrf = '{{ csrf_token() }}';
    const urlProcessar = '{{ route('estoque.importacaoMassa.processar') }}';
    let arquivoSelecionado = null;
    let previewToken = null;
    let resumoAtual = null;

    const fmtMoeda = (v) => {
        if (v === null || v === undefined || v === '') return '—';
        return parseFloat(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };
    const fmtQtd = (v) => {
        if (v === null || v === undefined || v === '') return '—';
        return parseFloat(v).toLocaleString('pt-BR', { maximumFractionDigits: 3 });
    };

    $('#inp-arquivo-csv').on('change', function () {
        arquivoSelecionado = this.files[0] || null;
        $('#lbl-arquivo-nome').text(arquivoSelecionado ? arquivoSelecionado.name : 'Nenhum arquivo');
        atualizarBotoes();
        $('#secao-preview').addClass('d-none');
    });

    $('#sel-fornecedor-import').on('change', function () {
        $('#inp-fornecedor-confirmar').val($(this).val() || '');
        atualizarBotoes();
    });

    function atualizarBotoes() {
        const temFornecedor = !!$('#sel-fornecedor-import').val();
        $('#btn-processar').prop('disabled', !arquivoSelecionado || !temFornecedor);
        const podeConfirmar = temFornecedor && previewToken && resumoAtual && !resumoAtual.tem_erros;
        $('#btn-confirmar').prop('disabled', !podeConfirmar);
    }

    $('#btn-processar').on('click', function () {
        if (!arquivoSelecionado) return;

        const fd = new FormData();
        fd.append('arquivo', arquivoSelecionado);
        fd.append('_token', csrf);

        $('#box-progresso').removeClass('d-none');
        $('#bar-progresso').css('width', '15%');
        $('#txt-progresso').text('Lendo arquivo...');
        $('#btn-processar').prop('disabled', true);

        let pct = 15;
        const timer = setInterval(function () {
            pct = Math.min(pct + 8, 90);
            $('#bar-progresso').css('width', pct + '%');
            if (pct >= 40) {
                $('#txt-progresso').text('Validando registros...');
            }
        }, 200);

        fetch(urlProcessar, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                clearInterval(timer);
                const total = data.resumo ? data.resumo.itens_processados : 0;
                $('#bar-progresso').css('width', '100%');
                $('#txt-progresso').text(total + ' de ' + total + ' registros processados');
                $('#btn-processar').prop('disabled', false);
                atualizarBotoes();

                if (!data.ok) {
                    swal('Erro', data.message || 'Falha ao processar.', 'error');
                    return;
                }

                previewToken = data.token;
                resumoAtual = data.resumo;
                $('#inp-fornecedor-confirmar').val($('#sel-fornecedor-import').val() || '');
                renderPreview(data.linhas, data.resumo);
                $('#secao-preview').removeClass('d-none');
            })
            .catch(err => {
                clearInterval(timer);
                $('#btn-processar').prop('disabled', false);
                atualizarBotoes();
                swal('Erro', 'Falha ao processar o arquivo.', 'error');
                console.error(err);
            });
    });

    function renderPreview(linhas, resumo) {
        const $tb = $('#tbody-preview').empty();
        linhas.forEach(function (l) {
            const cls = l.valido ? '' : 'linha-erro';
            const status = l.valido
                ? '<span class="text-success">OK</span>'
                : '<span class="text-danger">' + (l.erro || 'Erro') + '</span>';
            $tb.append(
                '<tr class="' + cls + '">' +
                '<td>' + l.codigo + '</td>' +
                '<td>' + (l.nome || '—') + '</td>' +
                '<td>' + fmtQtd(l.estoque_atual) + '</td>' +
                '<td>' + fmtQtd(l.quantidade) + '</td>' +
                '<td>' + fmtQtd(l.estoque_final) + '</td>' +
                '<td>' + fmtMoeda(l.custo_atual) + '</td>' +
                '<td>' + fmtMoeda(l.custo_novo) + '</td>' +
                '<td>' + fmtMoeda(l.preco_1_atual) + '</td>' +
                '<td>' + fmtMoeda(l.preco_1_novo) + '</td>' +
                '<td>' + fmtMoeda(l.preco_2_atual) + '</td>' +
                '<td>' + fmtMoeda(l.preco_2_novo) + '</td>' +
                '<td>' + fmtMoeda(l.preco_3_atual) + '</td>' +
                '<td>' + fmtMoeda(l.preco_3_novo) + '</td>' +
                '<td>' + status + '</td>' +
                '</tr>'
            );
        });

        $('#box-resumo').html(
            '<div class="col-md-3"><div class="card p-2"><small class="text-muted">Itens processados</small><div class="fw-bold">' + resumo.itens_processados + '</div></div></div>' +
            '<div class="col-md-3"><div class="card p-2"><small class="text-muted">Total de unidades</small><div class="fw-bold">' + fmtQtd(resumo.total_unidades) + '</div></div></div>' +
            '<div class="col-md-3"><div class="card p-2"><small class="text-muted">Valor total da compra</small><div class="fw-bold">R$ ' + fmtMoeda(resumo.valor_total_compra) + '</div></div></div>' +
            '<div class="col-md-3"><div class="card p-2"><small class="text-muted">Válidos / Erros</small><div class="fw-bold"><span class="text-success">' + resumo.produtos_validos + '</span> / <span class="text-danger">' + resumo.produtos_erro + '</span></div></div></div>'
        );

        $('#inp-token-confirmar').val(previewToken);
        $('#inp-fornecedor-confirmar').val($('#sel-fornecedor-import').val() || '');
        atualizarBotoes();
        $('#modal-qtd-produtos').text(resumo.produtos_validos);
    }

    $('#btn-confirmar').on('click', function () {
        if (!$('#sel-fornecedor-import').val()) {
            swal('Atenção', 'Selecione o fornecedor.', 'warning');
            return;
        }
        if (resumoAtual && resumoAtual.tem_erros) {
            swal('Atenção', 'Corrija os erros antes de confirmar.', 'warning');
            return;
        }
        new bootstrap.Modal(document.getElementById('modalConfirmarImportacao')).show();
    });

    $('#btn-modal-confirmar').on('click', function () {
        $('#inp-fornecedor-confirmar').val($('#sel-fornecedor-import').val() || '');
        $('#form-confirmar').trigger('submit');
    });

    if ($.fn.select2) {
        $('#sel-fornecedor-import').select2({ width: '100%' });
        $('.select2-filtro').select2({ width: '100%' });
    }
    atualizarBotoes();
})();
</script>
@endsection
