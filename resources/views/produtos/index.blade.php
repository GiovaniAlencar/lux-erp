@extends('default.layout',['title' => 'Produtos'])
@section('content')
@php
    $tipoAtual = request('tipo', 'nome');
    $statusAtual = request('status', 'ativos');
    $faixaAtual = request('estoque_faixa', '');
    $ordemAtual = $ordem ?? 'recentes';

    // link de ordenação: clica uma vez crescente, de novo decrescente
    $ordenarPor = function (string $campo) use ($ordemAtual) {
        $nova = $ordemAtual === $campo ? $campo . '_desc' : $campo;
        return request()->fullUrlWithQuery(['ordem' => $nova, 'page' => null]);
    };
    $setaOrdem = function (string $campo) use ($ordemAtual) {
        if ($ordemAtual === $campo) return '<i class="bi bi-arrow-up-short"></i>';
        if ($ordemAtual === $campo . '_desc') return '<i class="bi bi-arrow-down-short"></i>';
        return '<i class="bi bi-arrow-down-up lx-sort-off"></i>';
    };

    // atalhos do topo
    $semFiltros = !request()->hasAny(['nome', 'categoria_id', 'marca_id', 'fiscal', 'sem_vendas_dias', 'ultima_saida_de', 'ultima_saida_ate']);
    $atalhos = [
        ['rotulo' => 'Ativos', 'qtd' => $resumo['ativos'], 'url' => route('produtos.index'), 'on' => $semFiltros && $faixaAtual === '' && $statusAtual === 'ativos', 'tom' => ''],
        ['rotulo' => 'Sem estoque', 'qtd' => $resumo['sem'], 'url' => route('produtos.index', ['estoque_faixa' => 'sem']), 'on' => $faixaAtual === 'sem', 'tom' => 'danger'],
        ['rotulo' => 'Estoque baixo', 'qtd' => $resumo['baixo'], 'url' => route('produtos.index', ['estoque_faixa' => 'baixo', 'ordem' => 'estoque']), 'on' => $faixaAtual === 'baixo', 'tom' => 'warning'],
        ['rotulo' => 'Parados há 30+ dias', 'qtd' => $resumo['parados'], 'url' => route('produtos.index', ['sem_vendas_dias' => 30, 'ordem' => 'venda']), 'on' => request('sem_vendas_dias') == 30, 'tom' => 'info'],
        ['rotulo' => 'Inativos', 'qtd' => $resumo['inativos'], 'url' => route('produtos.index', ['status' => 'inativos']), 'on' => $statusAtual === 'inativos', 'tom' => 'muted'],
    ];

    $qtdFmt = function ($v) {
        $v = (float) $v;
        return rtrim(rtrim(number_format($v, 3, ',', '.'), '0'), ',');
    };
    $quando = function ($data) {
        if (empty($data)) return null;
        $d = \Carbon\Carbon::parse($data)->startOfDay();
        $dias = (int) $d->diffInDays(now()->startOfDay());
        if ($dias === 0) return 'hoje';
        if ($dias === 1) return 'ontem';
        if ($dias <= 60) return 'há ' . $dias . ' dias';
        return $d->format('d/m/Y');
    };
    $temFiltroAvancado = !empty($filtrosAvancadosAtivos);
    $qtdFiltros = collect(['categoria_id', 'marca_id', 'estoque_faixa', 'fiscal'])->filter(fn ($k) => filled(request($k)))->count() + ($statusAtual !== 'ativos' ? 1 : 0);
@endphp

<style>
    .lx-prod-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; margin-bottom: 1rem; }
    .lx-prod-head h1 { font-size: 1.35rem; font-weight: 700; margin: 0; color: var(--lx-text); letter-spacing: -.01em; }
    .lx-prod-head p { margin: .15rem 0 0; color: var(--lx-muted); font-size: .85rem; }
    .lx-prod-head .lx-acts { display: flex; gap: .5rem; flex-wrap: wrap; }

    .lx-chips { display: flex; gap: .5rem; flex-wrap: wrap; margin-bottom: 1rem; }
    .lx-chip { display: inline-flex; align-items: center; gap: .5rem; padding: .45rem .8rem; border-radius: 999px; border: 1px solid var(--lx-border); background: var(--lx-surface); color: var(--lx-text-2); font-size: .82rem; font-weight: 500; text-decoration: none; transition: border-color .15s, background .15s; }
    .lx-chip:hover { border-color: var(--lx-border-strong); color: var(--lx-text); }
    .lx-chip b { font-weight: 700; font-variant-numeric: tabular-nums; color: var(--lx-text); }
    .lx-chip .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--lx-gold); }
    .lx-chip.t-danger .dot { background: var(--lx-danger); }
    .lx-chip.t-warning .dot { background: var(--lx-warning); }
    .lx-chip.t-info .dot { background: var(--lx-info); }
    .lx-chip.t-muted .dot { background: var(--lx-muted); }
    .lx-chip.on { border-color: var(--lx-gold); background: var(--lx-gold-soft); color: var(--lx-text); }

    .lx-filtros { background: var(--lx-surface); border: 1px solid var(--lx-border); border-radius: var(--lx-radius); padding: .75rem; margin-bottom: 1rem; }
    .lx-filtros .form-label { font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--lx-muted); margin-bottom: .25rem; }
    .lx-busca { position: relative; display: flex; }
    .lx-busca select { max-width: 132px; border-top-right-radius: 0; border-bottom-right-radius: 0; border-right: 0; flex: 0 0 auto; }
    .lx-busca .lx-busca-inp { position: relative; flex: 1; }
    .lx-busca .lx-busca-inp input { border-top-left-radius: 0; border-bottom-left-radius: 0; padding-left: 2.1rem; }
    .lx-busca .lx-busca-inp i { position: absolute; left: .7rem; top: 50%; transform: translateY(-50%); color: var(--lx-muted); pointer-events: none; }
    .lx-filtros-acts { display: flex; gap: .5rem; align-items: flex-end; flex-wrap: wrap; }
    .lx-filtros-acts .btn { white-space: nowrap; }
    .lx-btn-dot { position: relative; }
    .lx-btn-dot::after { content: ''; position: absolute; top: 4px; right: 4px; width: 7px; height: 7px; border-radius: 50%; background: var(--lx-gold); }

    .lx-res-bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .5rem; margin-bottom: .5rem; font-size: .82rem; color: var(--lx-muted); }
    .lx-res-bar b { color: var(--lx-text-2); }
    .lx-res-bar select { width: auto; font-size: .82rem; padding-top: .25rem; padding-bottom: .25rem; }

    .lx-tabela-wrap { background: var(--lx-surface); border: 1px solid var(--lx-border); border-radius: var(--lx-radius); overflow: hidden; }
    .lx-tabela-scroll { overflow-x: auto; }
    .lx-tabela { width: 100%; margin: 0; font-size: .875rem; border-collapse: separate; border-spacing: 0; }
    .lx-tabela thead th { background: var(--lx-surface-2); color: var(--lx-muted); font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; padding: .6rem .75rem; border-bottom: 1px solid var(--lx-border); white-space: nowrap; }
    .lx-tabela thead th a { color: inherit; text-decoration: none; display: inline-flex; align-items: center; gap: .15rem; }
    .lx-tabela thead th a:hover { color: var(--lx-text); }
    .lx-tabela .lx-sort-off { opacity: .35; font-size: .7rem; }
    .lx-tabela tbody td { padding: .6rem .75rem; border-bottom: 1px solid var(--lx-border); vertical-align: middle; color: var(--lx-text-2); background: transparent; }
    .lx-tabela tbody tr:last-child td { border-bottom: 0; }
    .lx-tabela tbody tr[data-href] { cursor: pointer; }
    .lx-tabela tbody tr:hover td { background: var(--lx-surface-2); }
    .lx-tabela tbody tr.is-inativo td { opacity: .6; }
    .lx-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }

    .lx-p { display: flex; align-items: center; gap: .75rem; min-width: 260px; }
    .lx-p img { width: 44px; height: 44px; border-radius: 8px; object-fit: cover; flex: 0 0 44px; background: var(--lx-surface-3); border: 1px solid var(--lx-border); }
    .lx-p-nome { font-weight: 600; color: var(--lx-text); line-height: 1.3; }
    .lx-p-meta { font-size: .76rem; color: var(--lx-muted); margin-top: .15rem; display: flex; flex-wrap: wrap; gap: .25rem .5rem; align-items: center; }
    .lx-tag { display: inline-flex; align-items: center; gap: .25rem; font-size: .68rem; font-weight: 600; padding: .1rem .45rem; border-radius: 999px; line-height: 1.4; }
    .lx-tag.fiscal { background: var(--lx-info-soft); color: var(--lx-info); }
    .lx-tag.inativo { background: var(--lx-surface-3); color: var(--lx-muted); }
    .lx-tag.oculto { background: var(--lx-warning-soft); color: var(--lx-warning); }

    .lx-preco { font-weight: 600; color: var(--lx-text); }
    .lx-margem { display: block; font-size: .7rem; font-weight: 500; color: var(--lx-muted); }
    .lx-margem.baixa { color: var(--lx-danger); }
    .lx-estq { display: inline-flex; align-items: center; justify-content: center; min-width: 46px; padding: .2rem .55rem; border-radius: 999px; font-weight: 700; font-size: .8rem; font-variant-numeric: tabular-nums; }
    .lx-estq.ok { background: var(--lx-success-soft); color: var(--lx-success); }
    .lx-estq.baixo { background: var(--lx-warning-soft); color: var(--lx-warning); }
    .lx-estq.zero { background: var(--lx-danger-soft); color: var(--lx-danger); }
    .lx-estq.grade { background: var(--lx-surface-3); color: var(--lx-text-2); font-weight: 500; font-size: .72rem; border-radius: 6px; white-space: normal; text-align: left; }
    .lx-data { white-space: nowrap; font-size: .82rem; }
    .lx-data small { display: block; color: var(--lx-muted); font-size: .7rem; }

    .lx-acoes { display: inline-flex; gap: .25rem; }
    .lx-ico-btn { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; border: 1px solid var(--lx-border); background: var(--lx-surface); color: var(--lx-text-2); font-size: 1rem; padding: 0; text-decoration: none; }
    .lx-ico-btn:hover { border-color: var(--lx-border-strong); color: var(--lx-text); background: var(--lx-surface-2); }
    .lx-ico-btn.dropdown-toggle::after { display: none; }
    .produto-acoes-menu { z-index: 1060 !important; font-size: .875rem; }
    .produto-acoes-menu .dropdown-item i { width: 1.25rem; display: inline-block; opacity: .75; }

    .lx-vazio { text-align: center; padding: 3rem 1rem; color: var(--lx-muted); }
    .lx-vazio i { font-size: 2rem; display: block; margin-bottom: .5rem; opacity: .6; }
    .offcanvas-produtos-filtros { width: min(420px, 95vw); }

    /* celular: cada produto vira um cartão */
    @media (max-width: 767.98px) {
        .lx-tabela thead { display: none; }
        .lx-tabela, .lx-tabela tbody, .lx-tabela td { display: block; width: 100%; }
        .lx-tabela tbody tr { border-bottom: 1px solid var(--lx-border); padding: .75rem; display: grid; grid-template-columns: 1fr 1fr 1fr; gap: .6rem .75rem; }
        .lx-tabela tbody tr:last-child { border-bottom: 0; }
        .lx-tabela tbody td { border: 0; padding: 0; background: transparent !important; }
        .lx-tabela td[data-l]::before { content: attr(data-l); display: block; font-size: .65rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--lx-muted); margin-bottom: .1rem; }
        .lx-tabela td.c-prod { grid-column: 1 / -1; }
        .lx-tabela td.c-acoes { grid-column: 1 / -1; text-align: right; }
        .lx-tabela td.c-hide-sm { display: none; }
        .lx-tabela td.text-center { text-align: left !important; }
        .lx-num { text-align: left; }
        .lx-p { min-width: 0; }
        .lx-filtros:not(.aberto) .lx-f-extra { display: none; }
    }
</style>

<div class="page-content lx-prod">
    <div class="lx-prod-head">
        <div>
            <h1>Produtos</h1>
            <p>{{ number_format($resumo['ativos'], 0, ',', '.') }} produtos ativos no catálogo</p>
        </div>
        <div class="lx-acts">
            <a href="{{ route('produtos.import') }}" class="btn btn-outline-secondary"><i class="bi bi-upload"></i> Importar</a>
            <a href="{{ route('produtos.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Novo produto</a>
        </div>
    </div>

    <nav class="lx-chips" aria-label="Atalhos">
        @foreach($atalhos as $a)
        <a href="{{ $a['url'] }}" class="lx-chip {{ $a['tom'] ? 't-' . $a['tom'] : '' }} {{ $a['on'] ? 'on' : '' }}" @if($a['on']) aria-current="true" @endif>
            <span class="dot" aria-hidden="true"></span>{{ $a['rotulo'] }} <b>{{ number_format($a['qtd'], 0, ',', '.') }}</b>
        </a>
        @endforeach
    </nav>

    <form method="get" action="{{ route('produtos.index') }}" class="lx-filtros" id="form-produtos">
        <input type="hidden" name="ordem" value="{{ $ordemAtual }}">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-lg-4">
                <label class="form-label" for="inp-busca-produto">Buscar</label>
                <div class="lx-busca">
                    <select name="tipo" class="form-select" aria-label="Buscar por">
                        <option value="nome" @selected($tipoAtual === 'nome')>Nome</option>
                        <option value="id" @selected($tipoAtual === 'id')>ID</option>
                        <option value="codBarras" @selected($tipoAtual === 'codBarras')>Cód. barras</option>
                    </select>
                    <div class="lx-busca-inp">
                        <i class="bi bi-search" aria-hidden="true"></i>
                        <input type="search" name="nome" id="inp-busca-produto" class="form-control" value="{{ request('nome') }}" placeholder="{{ $tipoAtual === 'id' ? 'Ex.: 1234' : ($tipoAtual === 'codBarras' ? 'Código de barras' : 'Nome do produto') }}" autocomplete="off">
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3 col-lg-2 lx-f-extra">
                <label class="form-label">Categoria</label>
                <select name="categoria_id" class="form-select">
                    <option value="">Todas</option>
                    @foreach($categorias as $c)
                    <option value="{{ $c->id }}" @selected(request('categoria_id') == $c->id)>{{ $c->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2 lx-f-extra">
                <label class="form-label">Marca</label>
                <select name="marca_id" class="form-select">
                    <option value="">Todas</option>
                    @foreach($marcas as $m)
                    <option value="{{ $m->id }}" @selected(request('marca_id') == $m->id)>{{ $m->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2 lx-f-extra">
                <label class="form-label">Estoque</label>
                <select name="estoque_faixa" class="form-select">
                    <option value="">Qualquer</option>
                    <option value="sem" @selected($faixaAtual === 'sem')>Sem estoque</option>
                    <option value="baixo" @selected($faixaAtual === 'baixo')>Estoque baixo</option>
                    <option value="0_10" @selected($faixaAtual === '0_10')>0 a 10</option>
                    <option value="11_50" @selected($faixaAtual === '11_50')>11 a 50</option>
                    <option value="51_100" @selected($faixaAtual === '51_100')>51 a 100</option>
                    <option value="100_mais" @selected($faixaAtual === '100_mais')>Acima de 100</option>
                    <option value="negativo" @selected($faixaAtual === 'negativo')>Negativo</option>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2 lx-f-extra">
                <label class="form-label">Situação</label>
                <select name="status" class="form-select">
                    <option value="ativos" @selected($statusAtual === 'ativos')>Ativos</option>
                    <option value="inativos" @selected($statusAtual === 'inativos')>Inativos</option>
                    <option value="todos" @selected($statusAtual === 'todos')>Todos</option>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2 lx-f-extra">
                <label class="form-label">Nota fiscal</label>
                <select name="fiscal" class="form-select">
                    <option value="" @selected(request('fiscal', '') === '')>Todos</option>
                    <option value="1" @selected(request('fiscal') === '1')>Só fiscais</option>
                    <option value="0" @selected(request('fiscal') === '0')>Só não fiscais</option>
                </select>
            </div>
            @if($comFilial)
            <div class="col-6 col-md-3 col-lg-2 lx-f-extra">
                {!! __view_locais_select_filtro('Local', $filial_id ?? '', 'col-12') !!}
            </div>
            @endif
            <div class="col-12 col-md">
                <div class="lx-filtros-acts justify-content-md-end">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Buscar</button>
                    <button class="btn btn-outline-secondary d-md-none lx-f-toggle {{ $qtdFiltros ? 'lx-btn-dot' : '' }}" type="button" aria-expanded="false"><i class="bi bi-funnel"></i> Filtros{{ $qtdFiltros ? ' (' . $qtdFiltros . ')' : '' }}</button>
                    <button class="btn btn-outline-secondary {{ $temFiltroAvancado ? 'lx-btn-dot' : '' }}" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasFiltrosProdutos">
                        <i class="bi bi-sliders"></i> Mais filtros
                    </button>
                    <a class="btn btn-outline-secondary" href="{{ route('produtos.index') }}" title="Limpar filtros"><i class="bi bi-x-lg"></i> Limpar</a>
                </div>
            </div>
        </div>

        {{-- Filtros avançados (painel lateral) --}}
        <div class="offcanvas offcanvas-end offcanvas-produtos-filtros" tabindex="-1" id="offcanvasFiltrosProdutos" aria-labelledby="tit-filtros-prod">
            <div class="offcanvas-header">
                <h6 class="offcanvas-title" id="tit-filtros-prod">Mais filtros</h6>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
            </div>
            <div class="offcanvas-body">
                <div class="mb-3">
                    <label class="form-label">Sem vendas nos últimos (dias)</label>
                    <input type="number" name="sem_vendas_dias" class="form-control" min="1" max="9999" value="{{ request('sem_vendas_dias') }}" placeholder="Ex.: 30">
                    <small class="text-muted">Produtos sem saída (venda) no período.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Última venda — de</label>
                    <input type="date" name="ultima_saida_de" class="form-control" value="{{ request('ultima_saida_de') }}">
                </div>
                <div class="mb-3">
                    <label class="form-label">Última venda — até</label>
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
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Aplicar filtros</button>
            </div>
        </div>
    </form>

    <div class="lx-res-bar">
        <span>
            @if($data->total() > 0)
                Mostrando <b>{{ $data->firstItem() }}–{{ $data->lastItem() }}</b> de <b>{{ number_format($data->total(), 0, ',', '.') }}</b>
            @else
                Nenhum resultado
            @endif
        </span>
        <label class="d-flex align-items-center gap-2 mb-0">
            Ordenar por
            <select class="form-select" id="sel-ordem-produtos" aria-label="Ordenar por">
                @foreach([
                    'recentes' => 'Mais recentes',
                    'nome' => 'Nome (A–Z)',
                    'nome_desc' => 'Nome (Z–A)',
                    'estoque' => 'Menor estoque',
                    'estoque_desc' => 'Maior estoque',
                    'preco' => 'Menor preço',
                    'preco_desc' => 'Maior preço',
                    'venda' => 'Vendido há mais tempo',
                    'venda_desc' => 'Vendido recentemente',
                ] as $k => $rot)
                <option value="{{ $k }}" @selected($ordemAtual === $k)>{{ $rot }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <div class="lx-tabela-wrap">
        <div class="lx-tabela-scroll">
            <table class="lx-tabela">
                <thead>
                    <tr>
                        <th><a href="{{ $ordenarPor('nome') }}">Produto {!! $setaOrdem('nome') !!}</a></th>
                        <th class="lx-num">Custo</th>
                        <th class="lx-num"><a href="{{ $ordenarPor('preco') }}">Varejo {!! $setaOrdem('preco') !!}</a></th>
                        <th class="lx-num">Atac. 1</th>
                        <th class="lx-num">Atac. 2</th>
                        @if($comFilial)
                        <th>Local</th>
                        @endif
                        <th class="text-center"><a href="{{ $ordenarPor('estoque') }}">Estoque {!! $setaOrdem('estoque') !!}</a></th>
                        <th><a href="{{ $ordenarPor('venda') }}">Últ. venda {!! $setaOrdem('venda') !!}</a></th>
                        <th>Últ. compra</th>
                        <th class="text-end"><span class="visually-hidden">Ações</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($data as $p)
                    @php
                        $custo = (float) $p->valor_compra;
                        $venda = (float) $p->valor_venda;
                        $margem = $venda > 0 && $custo > 0 ? (($venda - $custo) / $venda) * 100 : null;
                        $est = (float) ($p->estoque_total ?? 0);
                        $minimo = (float) ($p->estoque_minimo ?: 5);
                        $clsEst = $est <= 0 ? 'zero' : ($est <= $minimo ? 'baixo' : 'ok');
                        $urlEdit = route('produtos.edit', $p->id);
                        $tVenda = $quando($p->ultima_saida_at);
                        $tCompra = $quando($p->ultima_compra_at);
                    @endphp
                    <tr class="{{ $p->inativo ? 'is-inativo' : '' }}" data-href="{{ $urlEdit }}">
                        <td class="c-prod">
                            <div class="lx-p">
                                <img src="{{ $p->img }}" alt="" loading="lazy" width="44" height="44">
                                <div>
                                    <a href="{{ $urlEdit }}" class="lx-p-nome text-reset text-decoration-none">{{ $p->nome }}{{ $p->str_grade }}</a>
                                    <div class="lx-p-meta">
                                        <span>#{{ $p->id }}</span>
                                        @if($p->categoria)<span>· {{ $p->categoria->nome }}</span>@endif
                                        @if($p->marca_id && isset($marcasNome[$p->marca_id]))<span>· {{ $marcasNome[$p->marca_id] }}</span>@endif
                                        @if($p->fiscal)<span class="lx-tag fiscal" title="Saldo com nota (estoque fiscal)"><i class="bi bi-receipt"></i> Fiscal · {{ $qtdFmt($p->estoque_fiscal) }} c/ nota</span>@endif
                                        @if($p->inativo)<span class="lx-tag inativo">Inativo</span>@endif
                                        @if(!empty($p->ocultar_site))<span class="lx-tag oculto" title="Não aparece no site">Oculto no site</span>@endif
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="lx-num" data-l="Custo">{{ $custo > 0 ? __moeda($custo) : '—' }}</td>
                        <td class="lx-num" data-l="Varejo">
                            <span class="lx-preco">{{ __moeda($venda) }}</span>
                            @if($margem !== null)
                            <span class="lx-margem {{ $margem < 15 ? 'baixa' : '' }}" title="Margem sobre o preço de varejo">margem {{ number_format($margem, 0, ',', '.') }}%</span>
                            @endif
                        </td>
                        <td class="lx-num" data-l="Atac. 1">{{ $p->preco_2 !== null ? __moeda($p->preco_2) : '—' }}</td>
                        <td class="lx-num" data-l="Atac. 2">{{ $p->preco_3 !== null ? __moeda($p->preco_3) : '—' }}</td>
                        @if($comFilial)
                        <td data-l="Local"><span class="small">{!! $p->locais_produto() !!}</span></td>
                        @endif
                        <td class="text-center" data-l="Estoque">
                            @if($p->grade)
                                <span class="lx-estq grade">{{ $p->estoquePorLocal($comFilial ? $p->locais : $filial_id) }}</span>
                            @else
                                <span class="lx-estq {{ $clsEst }}" title="{{ $clsEst === 'baixo' ? 'Estoque baixo' : ($clsEst === 'zero' ? 'Sem estoque' : 'Em estoque') }}">{{ $qtdFmt($est) }}</span>
                            @endif
                        </td>
                        <td class="lx-data" data-l="Últ. venda">
                            @if($tVenda) {{ $tVenda }} @else <span class="text-muted">nunca</span> @endif
                        </td>
                        <td class="lx-data c-hide-sm" data-l="Últ. compra">
                            @if($tCompra) {{ $tCompra }} @else <span class="text-muted">—</span> @endif
                            <small>cadastro {{ __data_pt($p->created_at, false) }}</small>
                        </td>
                        <td class="text-end c-acoes">
                            <div class="lx-acoes">
                                <a href="{{ $urlEdit }}" class="lx-ico-btn" title="Editar" aria-label="Editar {{ $p->nome }}"><i class="bi bi-pencil"></i></a>
                                <div class="dropdown">
                                    <button class="lx-ico-btn dropdown-toggle produto-acoes-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Mais ações" aria-label="Mais ações"><i class="bi bi-three-dots"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end produto-acoes-menu">
                                        <li><a class="dropdown-item" href="{{ route('produtos.movimentacao', $p->id) }}"><i class="bi bi-arrow-left-right"></i> Movimentação</a></li>
                                        <li><a class="dropdown-item" href="{{ route('produtos.duplicar', $p->id) }}"><i class="bi bi-copy"></i> Duplicar</a></li>
                                        <li><a class="dropdown-item" href="{{ route('produtos.etiqueta', $p->id) }}"><i class="bi bi-upc"></i> Código de barras</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form action="{{ route('produtos.destroy', $p->id) }}" method="post" id="form-{{ $p->id }}">
                                                @method('delete')
                                                @csrf
                                                <button type="submit" class="dropdown-item text-danger btn-delete"><i class="bi bi-{{ $p->inativo ? 'trash' : 'slash-circle' }}"></i> {{ $p->inativo ? 'Excluir definitivo' : 'Inativar' }}</button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ $comFilial ? 10 : 9 }}">
                            <div class="lx-vazio">
                                <i class="bi bi-box-seam"></i>
                                Nenhum produto encontrado com esses filtros.
                                <div class="mt-2"><a href="{{ route('produtos.index') }}">Limpar filtros</a></div>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {!! $data->appends(request()->all())->links() !!}
    </div>
</div>
@endsection

@section('js')
<script>
(function () {
    // menu de ações por cima da tabela (não corta no fim da lista)
    document.querySelectorAll('.produto-acoes-btn').forEach(function (btn) {
        if (btn._produtoDropdownInit) return;
        btn._produtoDropdownInit = true;
        new bootstrap.Dropdown(btn, {
            popperConfig: function (cfg) {
                return Object.assign({}, cfg, {
                    strategy: 'fixed',
                    modifiers: [].concat(cfg.modifiers || [], [
                        { name: 'preventOverflow', options: { boundary: document.body } },
                        { name: 'flip', options: { fallbackPlacements: ['top-end', 'bottom-end'] } }
                    ])
                });
            }
        });
    });

    // clicar na linha abre o produto (menos em botões, links e no menu)
    document.querySelectorAll('.lx-tabela tbody tr[data-href]').forEach(function (tr) {
        tr.addEventListener('click', function (e) {
            if (e.target.closest('a, button, form, .dropdown-menu, input, select')) return;
            if (window.getSelection && String(window.getSelection()).length) return;
            if (e.ctrlKey || e.metaKey) { window.open(tr.dataset.href, '_blank'); return; }
            window.location.href = tr.dataset.href;
        });
    });

    var form = document.getElementById('form-produtos');
    var tg = form && form.querySelector('.lx-f-toggle');
    if (tg) tg.addEventListener('click', function () {
        var aberto = form.classList.toggle('aberto');
        tg.setAttribute('aria-expanded', aberto ? 'true' : 'false');
    });
    var sel = document.getElementById('sel-ordem-produtos');
    if (sel && form) {
        sel.addEventListener('change', function () {
            form.querySelector('[name=ordem]').value = sel.value;
            form.submit();
        });
    }
    var tipo = form && form.querySelector('select[name=tipo]');
    var busca = document.getElementById('inp-busca-produto');
    if (tipo && busca) {
        var ph = { nome: 'Nome do produto', id: 'Ex.: 1234', codBarras: 'Código de barras' };
        tipo.addEventListener('change', function () { busca.placeholder = ph[tipo.value] || ''; busca.focus(); });
    }

    // tecla "/" vai direto para a busca
    document.addEventListener('keydown', function (e) {
        var tag = ((document.activeElement || {}).tagName || '').toLowerCase();
        if (e.key === '/' && ['input', 'textarea', 'select'].indexOf(tag) < 0) {
            e.preventDefault(); busca && busca.focus();
        }
    });
})();
</script>
@endsection
