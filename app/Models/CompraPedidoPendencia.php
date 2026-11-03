<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompraPedidoPendencia extends Model
{
    protected $table = 'compra_pedido_pendencias';

    protected $fillable = [
        'pedido_id', 'item_id', 'tipo', 'descricao', 'quantidade', 'valor_moeda', 'valor_brl', 'status',
        'resolucao', 'resolvido_em', 'usuario_id',
    ];

    protected $casts = ['quantidade' => 'float', 'valor_moeda' => 'float', 'valor_brl' => 'float', 'resolvido_em' => 'datetime'];

    public const TIPOS = [
        'falta' => 'Faltou',
        'avaria' => 'Chegou quebrado/avariado',
        'desconto' => 'Desconto a receber',
        'outro' => 'Outro',
    ];

    public function item()
    {
        return $this->belongsTo(CompraPedidoItem::class, 'item_id');
    }

    public function pedido()
    {
        return $this->belongsTo(CompraPedido::class, 'pedido_id');
    }
}
