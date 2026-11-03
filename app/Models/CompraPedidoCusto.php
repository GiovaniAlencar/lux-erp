<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompraPedidoCusto extends Model
{
    protected $table = 'compra_pedido_custos';

    protected $fillable = [
        'pedido_id', 'tipo', 'data', 'valor_moeda', 'cotacao', 'pct_frete', 'valor_brl', 'descricao',
        'fin_conta_id', 'fin_movimento_id', 'usuario_id',
    ];

    protected $casts = ['data' => 'date', 'valor_moeda' => 'float', 'cotacao' => 'float', 'pct_frete' => 'float', 'valor_brl' => 'float'];

    public const TIPOS = [
        'pagamento' => 'Pagamento ao fornecedor',
        'frete' => 'Frete',
        'transportadora' => 'Transportadora',
        'outro' => 'Outro custo',
    ];

    public function conta()
    {
        return $this->belongsTo(FinConta::class, 'fin_conta_id');
    }
}
