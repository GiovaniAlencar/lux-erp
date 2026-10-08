@if(!empty($precoCategoriaJs))
@php
    $modoArabes = old('modo_preco_arabes', isset($item) ? ($item->modo_preco_arabes ?? 'auto') : 'auto');
    $modoMiniaturas = old('modo_preco_miniaturas', isset($item) ? ($item->modo_preco_miniaturas ?? 'auto') : 'auto');
@endphp
<input type="hidden" name="modo_preco_arabes" id="inp-modo-preco-arabes" value="{{ $modoArabes }}">
<input type="hidden" name="modo_preco_miniaturas" id="inp-modo-preco-miniaturas" value="{{ $modoMiniaturas }}">

<style>
    .painel-preco-categoria .card-preco-grupo {
        border: 1px solid var(--bs-border-color, #dee2e6);
        border-radius: 10px;
        padding: 1rem 1.1rem;
        background: var(--bs-body-bg, #fff);
        height: 100%;
    }
    .painel-preco-categoria .grupo-titulo {
        font-weight: 700;
        letter-spacing: .04em;
        font-size: .85rem;
        text-transform: uppercase;
    }
    .badge-tabela-normal { background: #6c757d; color: #fff; }
    .badge-tabela-atacado-1 { background: #ffc107; color: #212529; }
    .badge-tabela-atacado-2 { background: #198754; color: #fff; }
    .progress-tabela-preco {
        height: 8px;
        border-radius: 999px;
        background: rgba(0,0,0,.08);
        overflow: hidden;
    }
    .progress-tabela-preco > span {
        display: block;
        height: 100%;
        background: linear-gradient(90deg, #ffc107, #198754);
        transition: width .25s ease;
    }
    html.dark-theme .painel-preco-categoria .card-preco-grupo {
        background: #171717;
        border-color: rgba(255,255,255,.1);
    }
</style>

<div class="painel-preco-categoria row g-3 mb-3" id="painel-preco-categoria">
    @foreach($precoCategoriaJs as $grupo => $cfg)
    @php
        $modoGrupo = $grupo === 'arabes' ? $modoArabes : ($grupo === 'miniaturas' ? $modoMiniaturas : 'auto');
        $tierLabel = match($modoGrupo) {
            'atacado_1' => 'Atacado 1',
            'atacado_2' => 'Atacado 2',
            'normal' => 'Normal',
            default => 'Automático',
        };
        $tierBadge = match($modoGrupo) {
            'atacado_1' => 'badge-tabela-atacado-1',
            'atacado_2' => 'badge-tabela-atacado-2',
            default => 'badge-tabela-normal',
        };
    @endphp
    <div class="col-md-6">
        <div class="card-preco-grupo" data-grupo="{{ $grupo }}" data-categoria-id="{{ $cfg['categoria_id'] }}">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                <div class="grupo-titulo">{{ $cfg['label'] }}</div>
                <span class="badge rounded-pill {{ $tierBadge }} badge-tier-ativo" data-grupo="{{ $grupo }}">{{ $tierLabel }}</span>
            </div>
            <div class="small text-muted mb-2">
                Quantidade: <strong class="qtd-grupo" data-grupo="{{ $grupo }}">0</strong>
            </div>
            <div class="mb-2">
                <label class="form-label small mb-1">Modo de Preço</label>
                <select class="form-select form-select-sm select-modo-preco" data-grupo="{{ $grupo }}">
                    <option value="auto" @if($modoGrupo==='auto') selected @endif>Automático</option>
                    <option value="normal" @if($modoGrupo==='normal') selected @endif>Normal</option>
                    <option value="atacado_1" @if($modoGrupo==='atacado_1') selected @endif>Atacado 1</option>
                    <option value="atacado_2" @if($modoGrupo==='atacado_2') selected @endif>Atacado 2</option>
                </select>
            </div>
            <div class="progress-tabela-preco mb-2" title="Progresso para próxima faixa">
                <span class="barra-progresso" data-grupo="{{ $grupo }}" style="width:0%"></span>
            </div>
            <div class="small texto-progresso" data-grupo="{{ $grupo }}">Adicione itens desta categoria.</div>
            <div class="small text-muted fracao-progresso mt-1" data-grupo="{{ $grupo }}"></div>
        </div>
    </div>
    @endforeach
</div>
@endif
