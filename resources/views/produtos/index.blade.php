@extends('default.layout',['title' => 'Produtos'])
@section('content')
@php
    $tipoAtual = request('tipo', 'nome');
    $statusAtual = request('status', 'ativos');
@endphp
<style>
    .produtos-lista-wrap {
        overflow: visible;
        min-height: min(55vh, 480px);
    }
    .produtos-lista-scroll { overflow-x: auto; }
    .produtos-lista-wrap .table { font-size: .875rem; margin-bottom: 0; }
    .produtos-lista-wrap .table td,
    .produtos-lista-wrap .table th { padding: .4rem .5rem; vertical-align: middle; white-space: nowrap; }
    .produtos-lista-wrap .col-descricao { white-space: normal !important; overflow-wrap: anywhere; min-width: 160px; max-width: 280px; }
    .produtos-lista-wrap .badge-fiscal { font-size: .65rem; font-weight: 600; background: #4f46e5; color: #fff; letter-spacing: .02em; }
    .produtos-lista-wrap .col-categoria { white-space: normal; min-width: 90px; max-width: 140px; }
    .produtos-lista-wrap .img-round { width: 36px; height: 36px; object-fit: cover; }
    .produtos-lista-wrap .dropdown { position: static; }
    .produto-acoes-btn {
        white-space: nowrap;
        min-width: 76px;
        font-size: .8125rem;
        padding: .25rem .5rem;
    }
    .produto-acoes-menu {
        z-index: 1060 !important;
    }
    .produtos-page-card,
    .produtos-page-card .card-body {
        overflow: visible;
    }
    .produtos-toolbar .form-label { font-size: .75rem; margin-bottom: .2rem; color: #6b7280; }
    .produtos-toolbar .form-control,
    .produtos-toolbar .form-select { font-size: .875rem; padding: .35rem .5rem; min-height: 34px; }
    .produtos-toolbar .btn { font-size: .875rem; padding: .35rem .75rem; min-height: 34px; }
    .produtos-toolbar-actions { display: flex; flex-wrap: wrap; gap: .5rem; align-items: flex-end; }
    .badge-inativo { font-size: .7rem; }
    .offcanvas-produtos-filtros { width: min(420px, 95vw); }
</style>
<div class="page-content">
    <div class="card produtos-page-card">
        <div class="card-body p-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                <h6 class="mb-0 text-uppercase">Produtos</h6>
                <span class="text-muted small">{{ $data->total() }} registro(s)</span>
            </div>

            <div class="produtos-toolbar">
            {!! Form::open()->fill(request()->all())->get() !!}
            <div class="row g-2 align-items-end">
                <div class="col-6 col-md-1">
                    <label class="form-label">Tipo</label>
                    <select name="tipo" class="form-select form-select-sm">
                        <option value="nome" @selected($tipoAtual === 'nome')>Descrição</option>
                        <option value="id" @selected($tipoAtual === 'id')>ID</option>
                        <option value="codBarras" @selected($tipoAtual === 'codBarras')>Cód. barras</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Pesquisar</label>
                    <input type="text" name="nome" class="form-control form-control-sm" value="{{ request('nome') }}" placeholder="{{ $tipoAtual === 'id' ? 'Ex: 1234' : 'Nome do produto...' }}">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label">Categoria</label>
                    <select name="categoria_id" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        @foreach($categorias as $c)
                        <option value="{{ $c->id }}" @selected(request('categoria_id') == $c->id)>{{ $c->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label">Marca</label>
                    <select name="marca_id" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        @foreach($marcas as $m)
                        <option value="{{ $m->id }}" @selected(request('marca_id') == $m->id)>{{ $m->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label">Estoque</label>
                    <select name="estoque_faixa" class="form-select form-select-sm">
                        <option value="">Qualquer</option>
                        <option value="zerado" @selected(request('estoque_faixa') === 'zerado')>Zerado</option>
                        <option value="0_10" @selected(request('estoque_faixa') === '0_10')>0 a 10</option>
                        <option value="11_50" @selected(request('estoque_faixa') === '11_50')>11 a 50</option>
                        <option value="51_100" @selected(request('estoque_faixa') === '51_100')>51 a 100</option>
                        <option value="100_mais" @selected(request('estoque_faixa') === '100_mais')>Acima de 100</option>
                        <option value="negativo" @selected(request('estoque_faixa') === 'negativo')>Negativo</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label">Situação</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="ativos" @selected($statusAtual === 'ativos')>Ativos</option>
                        <option value="inativos" @selected($statusAtual === 'inativos')>Inativos</option>
                        <option value="todos" @selected($statusAtual === 'todos')>Todos</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label">Fiscal</label>
                    <select name="fiscal" class="form-select form-select-sm">
                        <option value="" @selected(request('fiscal', '') === '')>Todos</option>
                        <option value="1" @selected(request('fiscal') === '1')>Só fiscais</option>
                        <option value="0" @selected(request('fiscal') === '0')>Só não fiscais</option>
                    </select>
                </div>
                @if(empresaComFilial())
                <div class="col-6 col-md-2">
                    {!! __view_locais_select_filtro('Local', $filial_id ?? '', 'col-12') !!}
                </div>
                @endif
                <div class="col-12 col-md-auto">
                    <div class="produtos-toolbar-actions">
                        <button class="btn btn-primary btn-sm" type="submit"><i class="bx bx-search"></i> Buscar</button>
                        <a class="btn btn-outline-secondary btn-sm" href="{{ route('produtos.index') }}"><i class="bx bx-eraser"></i></a>
                        <button class="btn btn-outline-primary btn-sm @if(!empty($filtrosAvancadosAtivos)) active @endif" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasFiltrosProdutos">
                            <i class="bx bx-filter-alt"></i> Mais filtros
                        </button>
                        <a href="{{ route('produtos.create') }}" class="btn btn-success btn-sm"><i class="bx bx-plus"></i> Novo</a>
                        <a href="{{ route('produtos.import') }}" class="btn btn-warning btn-sm"><i class="bx bx-import"></i> Importar</a>
                    </div>
                </div>
            </div>

            {{-- Filtros avançados (offcanvas) --}}
            <div class="offcanvas offcanvas-end offcanvas-produtos-filtros" tabindex="-1" id="offcanvasFiltrosProdutos">
                <div class="offcanvas-header">
                    <h6 class="offcanvas-title">Filtros avançados</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
                </div>
                <div class="offcanvas-body">
                    <div class="mb-3">
                        <label class="form-label">Sem vendas nos últimos (dias)</label>
                        <input type="number" name="sem_vendas_dias" class="form-control" min="1" max="9999" value="{{ request('sem_vendas_dias') }}" placeholder="Ex: 15">
                        <small class="text-muted">Produtos sem saída (venda ou PDV) no período.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Última saída — de</label>
                        <input type="date" name="ultima_saida_de" class="form-control" value="{{ request('ultima_saida_de') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Última saída — até</label>
                        <input type="date" name="ultima_saida_ate" class="form-control" value="{{ request('ultima_saida_ate') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Itens por página</label>
                        <select name="per_page" class="form-select">
                            @foreach([25, 50, 75, 100] as $pp)
                            <option value="{{ $pp }}" @selected(($perPage ?? 50) == $pp)>{{ $pp }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="bx bx-search"></i> Aplicar filtros</button>
                </div>
            </div>
            </div>
            {!! Form::close() !!}
            </div>

            <div class="produtos-lista-wrap mt-2">
                <div class="produtos-lista-scroll">
                <table class="table table-striped table-hover">
                    <thead class="table-light">
                        <tr>
                            <th style="width:42px"></th>
                            <th style="width:88px">Ações</th>
                            <th style="width:56px">ID</th>
                            <th class="col-descricao">Descrição</th>
                            <th class="col-categoria">Categoria</th>
                            <th style="width:88px">Custo</th>
                            <th style="width:88px">Normal</th>
                            <th style="width:88px">Atac. 1</th>
                            <th style="width:88px">Atac. 2</th>
                            <th style="width:96px">Cadastro</th>
                            @if(empresaComFilial())
                            <th style="width:100px">Local</th>
                            @endif
                            <th style="width:72px">Estoque</th>
                            <th style="width:96px">Últ. compra</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($data as $p)
                        <tr @if($p->inativo) class="table-secondary" @endif>
                            <td><img class="img-round rounded" src="{{ $p->img }}" alt=""></td>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle produto-acoes-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">Ações</button>
                                    <ul class="dropdown-menu dropdown-menu-end produto-acoes-menu">
                                        <li><a class="dropdown-item" href="{{ route('produtos.edit', $p->id) }}">Editar</a></li>
                                        <li><a class="dropdown-item" href="{{ route('produtos.movimentacao', $p->id) }}">Movimentação</a></li>
                                        <li><a class="dropdown-item" href="{{ route('produtos.duplicar', $p->id) }}">Duplicar</a></li>
                                        <li><a class="dropdown-item" href="{{ route('produtos.etiqueta', $p->id) }}">Código de barras</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form action="{{ route('produtos.destroy', $p->id) }}" method="post" id="form-{{ $p->id }}">
                                                @method('delete')
                                                @csrf
                                                <button type="submit" class="dropdown-item text-danger btn-delete">{{ $p->inativo ? 'Excluir definitivo' : 'Inativar' }}</button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                            <td><span class="text-muted fw-semibold">{{ $p->id }}</span></td>
                            <td class="col-descricao">
                                {{ $p->nome }}{{ $p->str_grade }}
                                @if($p->inativo)
                                <span class="badge bg-secondary badge-inativo ms-1">Inativo</span>
                                @endif
                                @if($p->fiscal)
                                <div class="mt-1"><span class="badge badge-fiscal" title="NF-e emitida no outro sistema — cobrado na conta fiscal">Fiscal</span></div>
                                @endif
                            </td>
                            <td class="col-categoria small">{{ $p->categoria->nome ?? '—' }}</td>
                            <td>{{ __moeda($p->valor_compra) }}</td>
                            <td>{{ __moeda($p->valor_venda) }}</td>
                            <td>{{ $p->preco_2 !== null ? __moeda($p->preco_2) : '—' }}</td>
                            <td>{{ $p->preco_3 !== null ? __moeda($p->preco_3) : '—' }}</td>
                            <td>{{ __data_pt($p->created_at, false) }}</td>
                            @if(empresaComFilial())
                            <td><span class="small">{!! $p->locais_produto() !!}</span></td>
                            @endif
                            <td>
                                @if(empresaComFilial())
                                {{ $p->estoquePorLocal($p->locais) }}
                                @else
                                {{ $p->estoquePorLocal($filial_id) }}
                                @endif
                            </td>
                            <td>
                                @if(!empty($p->ultima_compra_at))
                                {{ __data_pt($p->ultima_compra_at, false) }}
                                @else
                                <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ empresaComFilial() ? 13 : 12 }}" class="text-center py-4 text-muted">Nenhum produto encontrado</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>

            <div class="mt-2">
                {!! $data->appends(request()->all())->links() !!}
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
(function () {
    document.querySelectorAll('.produto-acoes-btn').forEach(function (btn) {
        if (btn._produtoDropdownInit) return;
        btn._produtoDropdownInit = true;
        new bootstrap.Dropdown(btn, {
            popperConfig: function (defaultBsPopperConfig) {
                return Object.assign({}, defaultBsPopperConfig, {
                    strategy: 'fixed',
                    modifiers: [].concat(defaultBsPopperConfig.modifiers || [], [
                        { name: 'preventOverflow', options: { boundary: document.body } },
                        { name: 'flip', options: { fallbackPlacements: ['top-end', 'bottom-end'] } }
                    ])
                });
            }
        });
    });
})();
</script>
@endsection
