<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompraPedidoItem extends Model
{
    protected $table = 'compra_pedido_itens';
    public $timestamps = false;

    protected $fillable = ['pedido_id', 'produto_id', 'descricao', 'quantidade', 'preco_moeda', 'qtd_recebida', 'custo_final'];

    protected $casts = ['quantidade' => 'float', 'preco_moeda' => 'float', 'qtd_recebida' => 'float', 'custo_final' => 'float'];

    public function produto()
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }

    public function nome(): string
    {
        return (string) (optional($this->produto)->nome ?: $this->descricao ?: 'Item');
    }

    public function subtotal(): float
    {
        return round($this->quantidade * $this->preco_moeda, 2);
    }
}
