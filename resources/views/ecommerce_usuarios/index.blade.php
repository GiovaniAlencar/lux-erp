@extends('default.layout',['title' => 'Usuários do site'])
@section('content')
@php
    $stAtual = request('status', '');
    $novosAtual = request('novos', '');
    $semFiltro = $stAtual === '' && $novosAtual === '' && !request('q');
    $tom = ['pending' => 'warning', 'active' => 'success', 'rejected' => 'danger', 'blocked' => 'danger', 'inactive' => 'muted'];

    $quando = function ($d) {
        if (empty($d)) return null;
        $c = \Carbon\Carbon::parse($d);
        $min = (int) $c->diffInMinutes(now());
        if ($min < 1) return 'agora';
        if ($min < 60) return 'há ' . $min . ' min';
        if ($min < 60 * 24 && $c->isToday()) return 'hoje, ' . $c->format('H:i');
        if ($c->isYesterday()) return 'ontem, ' . $c->format('H:i');
        $dias = (int) $c->copy()->startOfDay()->diffInDays(now()->startOfDay());
        if ($dias <= 30) return 'há ' . $dias . ' dias';
        return $c->format('d/m/Y');
    };
    $variacao = function (int $agora, int $antes) {
        if ($antes === 0) return $agora > 0 ? ['+' . $agora, 'up'] : ['igual', 'eq'];
        $pct = (int) round(($agora - $antes) / $antes * 100);
        if ($pct === 0) return ['igual', 'eq'];
        return [($pct > 0 ? '+' : '') . $pct . '%', $pct > 0 ? 'up' : 'down'];
    };
    [$varTxt, $varDir] = $variacao($resumo['semana'], $resumo['semana_anterior']);

    $cards = [
        [
            'rotulo' => 'Usuários aprovados', 'valor' => $resumo['aprovados'], 'icone' => 'person-check', 'tom' => 'success',
            'sub' => $resumo['acessaram_7d'] . ' entraram no site nos últimos 7 dias',
            'url' => route('ecommerce-usuarios.index', ['status' => 'active']), 'on' => $stAtual === 'active',
        ],
        [
            'rotulo' => 'Aguardando aprovação', 'valor' => $resumo['pendentes'], 'icone' => 'hourglass-split', 'tom' => 'warning',
            'sub' => $resumo['pendentes'] ? ('o mais antigo espera há ' . ($resumo['pendente_dias'] === 0 ? 'menos de 1 dia' : $resumo['pendente_dias'] . ($resumo['pendente_dias'] === 1 ? ' dia' : ' dias'))) : 'nenhum na fila',
            'url' => route('ecommerce-usuarios.index', ['status' => 'pending']), 'on' => $stAtual === 'pending', 'alerta' => $resumo['pendentes'] > 0,
        ],
        [
            'rotulo' => 'Novos cadastros hoje', 'valor' => $resumo['hoje'], 'icone' => 'person-plus', 'tom' => 'info',
            'sub' => 'ontem: ' . $resumo['ontem'],
            'url' => route('ecommerce-usuarios.index', ['novos' => 'hoje']), 'on' => $novosAtual === 'hoje',
        ],
        [
            'rotulo' => 'Cadastros em 7 dias', 'valor' => $resumo['semana'], 'icone' => 'graph-up-arrow', 'tom' => 'gold',
            'sub' => '<span class="eu-var ' . $varDir . '">' . e($varTxt) . '</span> vs. 7 dias anteriores · ' . $resumo['aprovados_semana'] . ' aprovados',
            'sub_html' => true,
            'url' => route('ecommerce-usuarios.index', ['novos' => '7d']), 'on' => $novosAtual === '7d',
        ],
    ];
@endphp

<style>
    .eu-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; margin-bottom: 1rem; }
    .eu-head h1 { font-size: 1.35rem; font-weight: 700; margin: 0; color: var(--lx-text); letter-spacing: -.01em; }
    .eu-head p { margin: .15rem 0 0; color: var(--lx-muted); font-size: .85rem; }

    .eu-cards { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; margin-bottom: 1rem; }
    @media (max-width: 1199.98px) { .eu-cards { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 575.98px) { .eu-cards { grid-template-columns: 1fr; } }
    .eu-card { position: relative; display: block; background: var(--lx-surface); border: 1px solid var(--lx-border); border-radius: var(--lx-radius); padding: 1rem 1.1rem; text-decoration: none; color: var(--lx-text); box-shadow: var(--lx-shadow); transition: border-color .15s, transform .15s; }
    .eu-card:hover { border-color: var(--lx-border-strong); color: var(--lx-text); transform: translateY(-1px); }
    .eu-card.on { border-color: var(--lx-gold); box-shadow: 0 0 0 1px var(--lx-gold) inset; }
    .eu-card-top { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
    .eu-card-rot { font-size: .8rem; font-weight: 500; color: var(--lx-text-2); }
    .eu-ico { width: 34px; height: 34px; border-radius: 9px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.05rem; }
    .eu-ico.t-success { background: var(--lx-success-soft); color: var(--lx-success); }
    .eu-ico.t-warning { background: var(--lx-warning-soft); color: var(--lx-warning); }
    .eu-ico.t-info { background: var(--lx-info-soft); color: var(--lx-info); }
    .eu-ico.t-gold { background: var(--lx-gold-soft); color: var(--lx-gold-strong); }
    .eu-num { font-size: 2rem; font-weight: 700; line-height: 1.1; margin-top: .35rem; font-variant-numeric: tabular-nums; letter-spacing: -.02em; }
    .eu-sub { font-size: .76rem; color: var(--lx-muted); margin-top: .2rem; }
    .eu-card.alerta { border-color: var(--lx-warning); }
    .eu-card.alerta .eu-num { color: var(--lx-warning); }
    .eu-var { font-weight: 700; }
    .eu-var.up { color: var(--lx-success); }
    .eu-var.down { color: var(--lx-danger); }

    .eu-filtros { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; background: var(--lx-surface); border: 1px solid var(--lx-border); border-radius: var(--lx-radius); padding: .75rem; margin-bottom: 1rem; }
    .eu-busca { position: relative; flex: 1 1 280px; }
    .eu-busca i { position: absolute; left: .7rem; top: 50%; transform: translateY(-50%); color: var(--lx-muted); pointer-events: none; }
    .eu-busca input { padding-left: 2.1rem; }
    .eu-filtros select { flex: 0 0 190px; }
    @media (max-width: 575.98px) { .eu-filtros select { flex: 1 1 100%; } }

    .eu-tabela-wrap { background: var(--lx-surface); border: 1px solid var(--lx-border); border-radius: var(--lx-radius); overflow: hidden; }
    .eu-tabela { width: 100%; font-size: .875rem; border-collapse: collapse; margin: 0; }
    .eu-tabela th { background: var(--lx-surface-2); color: var(--lx-muted); font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; padding: .6rem .9rem; border-bottom: 1px solid var(--lx-border); white-space: nowrap; }
    .eu-tabela td { padding: .7rem .9rem; border-bottom: 1px solid var(--lx-border); vertical-align: middle; color: var(--lx-text-2); }
    .eu-tabela tbody tr:last-child td { border-bottom: 0; }
    .eu-tabela tbody tr[data-href] { cursor: pointer; }
    .eu-tabela tbody tr:hover td { background: var(--lx-surface-2); }
    .eu-tabela tr.is-pend td:first-child { box-shadow: 3px 0 0 var(--lx-warning) inset; }
    .eu-nome { font-weight: 600; color: var(--lx-text); }
    .eu-meta { font-size: .76rem; color: var(--lx-muted); margin-top: .1rem; }
    .eu-tipo { display: inline-block; font-size: .65rem; font-weight: 700; padding: 0 .35rem; border-radius: 4px; background: var(--lx-surface-3); color: var(--lx-text-2); margin-right: .25rem; }
    .eu-contato { overflow-wrap: anywhere; max-width: 240px; }
    .eu-contato a { color: var(--lx-text-2); text-decoration: none; }
    .eu-contato a:hover { color: var(--lx-text); text-decoration: underline; }
    .eu-contato .bi-whatsapp { color: #25d366; }
    .eu-pill { display: inline-flex; align-items: center; gap: .3rem; padding: .2rem .6rem; border-radius: 999px; font-size: .75rem; font-weight: 600; white-space: nowrap; }
    .eu-pill.t-success { background: var(--lx-success-soft); color: var(--lx-success); }
    .eu-pill.t-warning { background: var(--lx-warning-soft); color: var(--lx-warning); }
    .eu-pill.t-danger { background: var(--lx-danger-soft); color: var(--lx-danger); }
    .eu-pill.t-muted { background: var(--lx-surface-3); color: var(--lx-muted); }
    .eu-quando { white-space: nowrap; font-size: .82rem; }
    .eu-vazio { text-align: center; padding: 3rem 1rem; color: var(--lx-muted); }
    .eu-vazio i { font-size: 2rem; display: block; margin-bottom: .5rem; opacity: .6; }

    @media (max-width: 767.98px) {
        .eu-tabela thead { display: none; }
        .eu-tabela, .eu-tabela tbody, .eu-tabela td { display: block; width: 100%; }
        .eu-tabela tbody tr { display: grid; grid-template-columns: 1fr auto; gap: .35rem .75rem; padding: .8rem .9rem; border-bottom: 1px solid var(--lx-border); }
        .eu-tabela tbody tr:last-child { border-bottom: 0; }
        .eu-tabela td { border: 0; padding: 0; background: transparent !important; }
        .eu-tabela td.c-nome, .eu-tabela td.c-contato, .eu-tabela td.c-cli { grid-column: 1 / -1; }
        .eu-tabela td.c-hide-sm { display: none; }
        .eu-tabela tr.is-pend td:first-child { box-shadow: none; }
    }
</style>

<div class="page-content">
    <div class="eu-head">
        <div>
            <h1>Usuários do site</h1>
            <p>Cadastros feitos no catálogo online — aprove para liberar preços e pedidos.</p>
        </div>
    </div>

    <div class="eu-cards">
        @foreach($cards as $c)
        <a href="{{ $c['on'] ? route('ecommerce-usuarios.index') : $c['url'] }}" class="eu-card {{ $c['on'] ? 'on' : '' }} {{ !empty($c['alerta']) ? 'alerta' : '' }}" title="{{ $c['on'] ? 'Mostrar todos' : 'Filtrar a lista' }}">
            <div class="eu-card-top">
                <span class="eu-card-rot">{{ $c['rotulo'] }}</span>
                <span class="eu-ico t-{{ $c['tom'] }}"><i class="bi bi-{{ $c['icone'] }}"></i></span>
            </div>
            <div class="eu-num">{{ number_format($c['valor'], 0, ',', '.') }}</div>
            <div class="eu-sub">{!! !empty($c['sub_html']) ? $c['sub'] : e($c['sub']) !!}</div>
        </a>
        @endforeach
    </div>

    <form method="get" action="{{ route('ecommerce-usuarios.index') }}" class="eu-filtros">
        @if($novosAtual)<input type="hidden" name="novos" value="{{ $novosAtual }}">@endif
        <div class="eu-busca">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input type="search" name="q" class="form-control" value="{{ request('q') }}" placeholder="Buscar por nome, e-mail ou telefone" aria-label="Buscar">
        </div>
        <select name="status" class="form-select" aria-label="Situação" onchange="this.form.submit()">
            <option value="">Todas as situações</option>
            @foreach($statusLabels as $k => $lbl)
                <option value="{{ $k }}" @selected($stAtual === $k)>{{ $lbl }}</option>
            @endforeach
        </select>
        <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Buscar</button>
        @if(!$semFiltro)
        <a class="btn btn-outline-secondary" href="{{ route('ecommerce-usuarios.index') }}"><i class="bi bi-x-lg"></i> Limpar</a>
        @endif
    </form>

    <div class="eu-tabela-wrap">
        <div class="table-responsive">
            <table class="eu-tabela">
                <thead>
                    <tr>
                        <th>Usuário</th>
                        <th>Contato</th>
                        <th>Situação</th>
                        <th>Cliente no ERP</th>
                        <th>Cadastro · acesso</th>
                        <th class="text-end"><span class="visually-hidden">Ações</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($data as $item)
                    @php
                        $st = $item->status;
                        $fone = $item->phone;
                        $wa = $fone ? preg_replace('/\D/', '', $fone) : '';
                        if ($wa && strlen($wa) <= 11) $wa = '55' . $wa;
                        $url = route('ecommerce-usuarios.show', $item->id);
                    @endphp
                    <tr class="{{ $st === 'pending' ? 'is-pend' : '' }}" data-href="{{ $url }}">
                        <td class="c-nome">
                            <div class="eu-nome">{{ $item->name }}</div>
                            <div class="eu-meta">
                                @if($item->tipo_pessoa)<span class="eu-tipo">{{ $item->tipo_pessoa }}</span>@endif
                                {{ $item->razao_social && $item->razao_social !== $item->name ? $item->razao_social . ' · ' : '' }}{{ $item->documento ?: 'sem documento' }}
                            </div>
                        </td>
                        <td class="c-contato eu-contato">
                            <div><a href="mailto:{{ $item->email }}">{{ $item->email }}</a></div>
                            @if($fone)
                            <div class="eu-meta">
                                @if(strlen($wa) >= 12)
                                <a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> {{ $fone }}</a>
                                @else
                                {{ $fone }}
                                @endif
                            </div>
                            @endif
                        </td>
                        <td>
                            <span class="eu-pill t-{{ $tom[$st] ?? 'muted' }}">{{ $statusLabels[$st] ?? $st }}</span>
                            @if($st === 'pending' && $item->created_at)
                            <div class="eu-meta">esperando {{ str_replace('há ', '', $quando($item->created_at)) }}</div>
                            @endif
                        </td>
                        <td class="c-cli">
                            @if($item->cliente)
                                <span class="eu-meta">#{{ $item->erp_cliente_id }}</span> {{ $item->cliente->razao_social }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="eu-quando c-hide-sm">
                            <span title="{{ $item->created_at ? __data_pt($item->created_at, 1) : '' }}">{{ $quando($item->created_at) ?? '—' }}</span>
                            <div class="eu-meta">acesso: {{ $quando($item->last_login) ?? 'nunca' }}</div>
                        </td>
                        <td class="text-end">
                            @if($st === 'pending')
                            <a href="{{ $url }}" class="btn btn-sm btn-primary text-nowrap">Analisar</a>
                            @else
                            <a href="{{ $url }}" class="btn btn-sm btn-outline-secondary">Abrir</a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6">
                            <div class="eu-vazio"><i class="bi bi-people"></i>Nenhum usuário encontrado.</div>
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
document.querySelectorAll('.eu-tabela tbody tr[data-href]').forEach(function (tr) {
    tr.addEventListener('click', function (e) {
        if (e.target.closest('a, button')) return;
        if (window.getSelection && String(window.getSelection()).length) return;
        window.location.href = tr.dataset.href;
    });
});
</script>
@endsection
