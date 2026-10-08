<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RotaEntregaItem extends Model
{
    protected $table = 'rota_entrega_itens';

    protected $fillable = [
        'rota_entrega_id',
        'venda_id',
        'ordem',
        'cliente_nome',
        'telefone',
        'rua',
        'numero',
        'bairro',
        'complemento',
        'embalagem_tipo',
        'embalagem_qtd',
        'valor_total',
        'frete',
        'qtd_parcelas',
        'mostrar_parcelas',
        'mostrar_valor_pedido',
        'situacao_pagamento',
        'valor_restante',
        'valor_parcela',
        'tipo_pagamento',
        'tipo_pagamento_nome',
        'prioridade',
        'observacao_prioridade',
        'aviso_entrega',
        'confirmado_saida',
        'status_entrega',
        'ocorrencia_tipo',
        'ocorrencia_observacao',
        'token_confirmacao',
        'confirmado_em',
    ];

    protected $casts = [
        'valor_total' => 'float',
        'frete' => 'float',
        'valor_restante' => 'float',
        'valor_parcela' => 'float',
        'mostrar_parcelas' => 'boolean',
        'mostrar_valor_pedido' => 'boolean',
        'prioridade' => 'boolean',
        'confirmado_saida' => 'boolean',
        'confirmado_em' => 'datetime',
        'embalagem_qtd' => 'integer',
    ];

    public static function booted(): void
    {
        static::creating(function (RotaEntregaItem $item) {
            if (empty($item->token_confirmacao)) {
                do {
                    $token = Str::random(10);
                } while (static::where('token_confirmacao', $token)->exists());

                $item->token_confirmacao = $token;
            }
        });
    }

    public function rota()
    {
        return $this->belongsTo(RotaEntrega::class, 'rota_entrega_id');
    }

    public function venda()
    {
        return $this->belongsTo(Venda::class, 'venda_id');
    }

    public function enderecoFormatado(): string
    {
        $rua = trim($this->rua ?? '');
        $numero = trim($this->numero ?? '');
        $bairro = trim($this->bairro ?? '');

        return trim($rua . (strlen($numero) ? ", $numero" : '') . (strlen($bairro) ? " - $bairro" : ''));
    }

    public function urlConfirmacao(): string
    {
        return url('/entrega/' . $this->token_confirmacao);
    }

    public static function tiposOcorrencia(): array
    {
        return [
            'cliente_ausente' => 'Cliente ausente',
            'endereco_nao_encontrado' => 'Endereço não encontrado',
            'recusou_recebimento' => 'Recusou recebimento',
            'reagendar' => 'Reagendar entrega',
            'outro' => 'Outro',
        ];
    }

    public function labelStatusEntrega(): string
    {
        return match ($this->status_entrega) {
            'entregue' => 'Entregue',
            'ocorrencia' => 'Ocorrência',
            default => 'Pendente',
        };
    }

    public static function situacoesPagamento(): array
    {
        return [
            'pago' => 'Pago',
            'pagamento_entrega' => 'Pagamento na entrega',
            'pago_parcial' => 'Pago parcial',
        ];
    }

    public function labelSituacaoPagamento(): string
    {
        return self::situacoesPagamento()[$this->situacao_pagamento] ?? $this->situacao_pagamento;
    }

    public function badgeClassSituacaoPagamento(): string
    {
        return match ($this->situacao_pagamento) {
            'pago' => 'rota-pag-pago',
            'pago_parcial' => 'rota-pag-parcial',
            default => 'rota-pag-entrega',
        };
    }

    public function valorExibirPagamento(): float
    {
        if ($this->situacao_pagamento === 'pago_parcial') {
            $rest = (float) ($this->valor_restante ?? 0);

            return $rest > 0 ? $rest : (float) $this->valor_total;
        }

        return (float) $this->valor_total;
    }

    public function textoPagamentoMotoboy(): string
    {
        if ($this->situacao_pagamento === 'pago') {
            return 'PAGO';
        }

        if ($this->situacao_pagamento === 'pago_parcial') {
            $txt = 'PAGO PARCIAL';
            if ((float) $this->valor_restante > 0) {
                $txt .= ' — falta R$ ' . number_format((float) $this->valor_restante, 2, ',', '.');
            }

            return $txt;
        }

        $nome = $this->tipo_pagamento_nome ?: 'Pagamento na entrega';

        if ($this->mostrar_parcelas) {
            $parcela = (float) $this->valor_parcela;
            $parcelaTxt = $parcela > 0
                ? number_format($parcela, 2, ',', '.')
                : number_format((float) $this->valor_total / max(1, $this->qtd_parcelas), 2, ',', '.');

            if ($this->qtd_parcelas > 1) {
                return $nome . ' — cobrar na entrega em ' . $this->qtd_parcelas . 'x de R$ ' . $parcelaTxt;
            }

            return $nome . ' — cobrar na entrega R$ ' . $parcelaTxt;
        }

        if ($this->mostrar_valor_pedido) {
            return $nome . ' — cobrar R$ ' . number_format((float) $this->valor_total, 2, ',', '.');
        }

        return $nome . ' — cobrar na entrega';
    }
}
