{{-- Abas do CRM LUX --}}
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h5 class="mb-0">CRM LUX <span class="small text-muted fw-normal">· {{ $tituloAba }}</span></h5>
        @if(empty($cfg['envio_ligado']))<div class="small text-muted">Envio automático <b>desligado</b> (fase de montagem e conferência): nada é enviado aos clientes ainda.</div>@endif
    </div>
    <ul class="nav nav-pills small">
        @foreach(['crm.index' => 'Visão geral', 'crm.fila' => 'Fila de contatos', 'crm.historico' => 'Histórico', 'crm.config' => 'Configurações'] as $rota => $rot)
            <li class="nav-item"><a class="nav-link py-1 {{ request()->routeIs($rota) ? 'active' : '' }}" href="{{ route($rota) }}">{{ $rot }}</a></li>
        @endforeach
    </ul>
</div>
