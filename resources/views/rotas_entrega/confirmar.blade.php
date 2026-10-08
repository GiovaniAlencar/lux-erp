<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Confirmar entrega — Pedido #{{ $item->venda_id }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f3f4f6; min-height: 100vh; }
        .card-entrega { max-width: 480px; margin: 0 auto; border: none; border-radius: 1rem; box-shadow: 0 8px 30px rgba(0,0,0,.08); }
        .prio-badge { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
    </style>
</head>
<body class="py-4 px-3">
    <div class="card card-entrega">
        <div class="card-body p-4">
            <h1 class="h5 mb-1">Pedido #{{ $item->venda_id }}</h1>
            <p class="text-muted small mb-3">{{ $item->cliente_nome }}</p>

            @if(session('flash_sucesso'))
            <div class="alert alert-success py-2 small">{{ session('flash_sucesso') }}</div>
            @endif
            @if(session('flash_warning'))
            <div class="alert alert-warning py-2 small">{{ session('flash_warning') }}</div>
            @endif

            <div class="mb-3 small">
                <div><strong>Endereço:</strong> {{ $item->enderecoFormatado() }}</div>
                @if($item->complemento)<div><strong>Compl.:</strong> {{ $item->complemento }}</div>@endif
                @if($item->telefone)<div><strong>Telefone:</strong> {{ $item->telefone }}</div>@endif
                <div><strong>Pagamento:</strong> {{ $item->textoPagamentoMotoboy() }}</div>
            </div>

            @if($item->prioridade)
            <div class="alert prio-badge py-2 small mb-3">
                <strong>Prioridade</strong>
                @if($item->observacao_prioridade)<br>{{ $item->observacao_prioridade }}@endif
            </div>
            @endif

            @if($jaRespondido)
            <div class="alert alert-{{ $item->status_entrega === 'entregue' ? 'success' : 'warning' }}">
                <strong>{{ $item->labelStatusEntrega() }}</strong>
                @if($item->status_entrega === 'ocorrencia')
                <div class="small mt-1">{{ $tiposOcorrencia[$item->ocorrencia_tipo] ?? $item->ocorrencia_tipo }}</div>
                @if($item->ocorrencia_observacao)<div class="small">{{ $item->ocorrencia_observacao }}</div>@endif
                @endif
                <div class="small text-muted mt-2">{{ optional($item->confirmado_em)->format('d/m/Y H:i') }}</div>
            </div>
            @else
            <form method="post" action="{{ route('entrega.confirmar.submit', $item->token_confirmacao) }}">
                @csrf
                <div class="d-grid gap-2 mb-3">
                    <button type="submit" name="tipo" value="entregue" class="btn btn-success btn-lg">Confirmar entrega</button>
                </div>

                <hr>
                <p class="small text-muted mb-2">Não foi possível entregar? Registre uma ocorrência:</p>

                <div class="mb-2">
                    <label class="form-label small">Motivo</label>
                    <select name="ocorrencia_tipo" class="form-select form-select-sm" id="ocorrencia_tipo">
                        <option value="">Selecione…</option>
                        @foreach($tiposOcorrencia as $k => $lbl)
                        <option value="{{ $k }}">{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small">Detalhes (opcional)</label>
                    <textarea name="ocorrencia_observacao" class="form-control form-control-sm" rows="2" maxlength="1000"></textarea>
                </div>
                <button type="submit" name="tipo" value="ocorrencia" class="btn btn-outline-danger w-100" onclick="return document.getElementById('ocorrencia_tipo').value !== '';">
                    Registrar ocorrência
                </button>
            </form>
            @endif
        </div>
    </div>
</body>
</html>
