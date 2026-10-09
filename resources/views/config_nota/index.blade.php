@extends('default.layout', ['title' => 'Emitente'])
@section('content')
<div class="page-content">
    <div class="card border-top border-0 border-4 border-primary">
        <div class="card-body p-5">
            <div class="page-breadcrumb d-sm-flex align-items-center mb-3">
            </div>
            <div class="card-title d-flex align-items-center">
                <h5 class="mb-0 text-primary">Emitente</h5>
            </div>
            <hr>
            @if(!empty($statusNfe))
            <div class="border rounded p-3 mb-4" id="lux-status-nfe">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
                    <h6 class="mb-0">Situação da NF-e</h6>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-status-sefaz">
                        <i class="bx bx-wifi"></i> Testar comunicação com a SEFAZ
                    </button>
                </div>
                <div class="row g-2 small">
                    @foreach($statusNfe as $st)
                    <div class="col-md-6 col-xl-4">
                        <span class="badge {{ $st['ok'] ? 'bg-success' : 'bg-danger' }} me-1">{{ $st['ok'] ? 'OK' : 'PENDENTE' }}</span>
                        <strong>{{ $st['nome'] }}:</strong> <span class="text-muted">{{ $st['detalhe'] }}</span>
                    </div>
                    @endforeach
                </div>
                <div class="small mt-2" id="lux-status-sefaz-retorno"></div>
            </div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('btn-status-sefaz');
    if (!btn) return;
    btn.addEventListener('click', function () {
        var out = document.getElementById('lux-status-sefaz-retorno');
        btn.disabled = true;
        out.innerHTML = '<span class="text-muted">Consultando a SEFAZ…</span>';
        fetch("{{ route('configNF.status-sefaz') }}", { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) {
                return r.text().then(function (t) {
                    try { return JSON.parse(t); }
                    catch (e) { return { ok: false, msg: 'HTTP ' + r.status + ' — ' + t.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 300) }; }
                });
            })
            .then(function (j) {
                var cls = j.ok ? 'text-success' : 'text-danger';
                out.innerHTML = '<span class="' + cls + ' fw-semibold">' + (j.ok ? 'Comunicação OK' : 'Falhou') + '</span> · ' +
                    (j.ambiente ? j.ambiente + ' · ' : '') + String(j.msg || '').replace(/</g, '&lt;');
            })
            .catch(function () { out.innerHTML = '<span class="text-danger">Erro ao consultar.</span>'; })
            .finally(function () { btn.disabled = false; });
    });
});
</script>
            @endif
            {!! Form::open()
            ->fill($item)
            ->post()
            ->route('configNF.store')
            ->multipart() !!}
            <div class="pl-lg-4">
                @include('config_nota._forms')
            </div>
            {!! Form::close() !!}
        </div>
    </div>
</div>
@endsection
