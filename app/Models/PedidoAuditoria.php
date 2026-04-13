<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PedidoAuditoria extends Model
{
    protected $fillable = [
        'empresa_id',
        'pedido_id',
        'usuario_id',
        'acao',
        'descricao',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function pedido()
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
