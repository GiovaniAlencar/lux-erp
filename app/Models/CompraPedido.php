<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Pedido de compra (dólar ou real): itens, pagamentos/fretes, entrada no estoque e pendências. SQL 33. */
class CompraPedido extends Model
{
    protected $table = 'compra_pedidos';

    protected $fillable = [
        'empresa_id', 'fornecedor_id', 'fornecedor_nome', 'moeda', 'status', 'data_pedido', 'previsao_pagamento',
        'cotacao_prevista', 'pct_frete_previsto', 'total_moeda', 'pct_agente', 'observacao', 'compra_id', 'entrada_estoque',
        'recebido_em', 'usuario_id',
    ];

    protected $casts = [
        'data_pedido' => 'date', 'previsao_pagamento' => 'date', 'recebido_em' => 'datetime',
        'cotacao_prevista' => 'float', 'pct_frete_previsto' => 'float', 'total_moeda' => 'float', 'pct_agente' => 'float',
    ];

    public const STATUS = [
        'rascunho' => 'Rascunho',
        'pedido' => 'Pedido feito',
        'recebido' => 'Recebido',
        'cancelado' => 'Cancelado',
    ];

    public function itens()
    {
        return $this->hasMany(CompraPedidoItem::class, 'pedido_id')->orderBy('id');
    }

    public function custos()
    {
        return $this->hasMany(CompraPedidoCusto::class, 'pedido_id')->orderBy('data')->orderBy('id');
    }

    public function pendencias()
    {
        return $this->hasMany(CompraPedidoPendencia::class, 'pedido_id')->orderBy('id');
    }

    public function fornecedor()
    {
        return $this->belongsTo(Fornecedor::class, 'fornecedor_id');
    }

    public function nomeFornecedor(): string
    {
        $f = $this->fornecedor;
        return $f ? (string) ($f->nome_fantasia ?: $f->razao_social) : (string) ($this->fornecedor_nome ?: 'Sem fornecedor');
    }

    /** Multiplicador do % do agente sobre o valor do fornecedor (1 = sem agente). */
    public function fatorAgente(): float
    {
        return 1 + max(0, (float) ($this->attributes['pct_agente'] ?? 0)) / 100;
    }

    /** Texto do pedido para mandar ao fornecedor (WhatsApp/e-mail), com os preços dele (sem o % do agente). */
    public function textoFornecedor(bool $comPrecos = true): string
    {
        $sim = $this->simbolo();
        $n = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
        $t = ['*Pedido de compra' . ($this->id ? ' #' . $this->id : '') . '* — ' . optional($this->data_pedido)->format('d/m/Y')];
        $t[] = '';
        $qtd = 0;
        $total = 0;
        foreach ($this->itens as $k => $i) {
            $nome = trim((string) ($i->descricao ?: optional($i->produto)->nome ?: 'Item'));
            $sub = $i->quantidade * $i->preco_moeda;
            $qtd += $i->quantidade;
            $total += $sub;
            $t[] = ($k + 1) . ') ' . $n($i->quantidade) . ' × ' . $nome
                . ($comPrecos ? ' — ' . $sim . ' ' . number_format($i->preco_moeda, 2, ',', '.') . ' = ' . $sim . ' ' . number_format($sub, 2, ',', '.') : '');
        }
        $t[] = '';
        $t[] = 'Total: ' . $n($qtd) . ' un.' . ($comPrecos ? ' · ' . $sim . ' ' . number_format($total, 2, ',', '.') : '');
        return implode("\n", $t);
    }

    public function simbolo(): string
    {
        return $this->moeda === 'USD' ? 'US$' : 'R$';
    }
}
