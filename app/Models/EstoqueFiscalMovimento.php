<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstoqueFiscalMovimento extends Model
{
    protected $table = 'estoque_fiscal_movimentos';

    protected $fillable = [
        'empresa_id', 'produto_id', 'tipo', 'quantidade', 'saldo_apos', 'custo_unitario', 'venda_id',
        'chave', 'documento', 'observacao', 'usuario_id',
    ];

    public const TIPOS = [
        'entrada_xml' => 'Entrada (XML)',
        'ajuste' => 'Ajuste manual',
        'venda' => 'Venda',
        'estorno' => 'Estorno de venda',
    ];

    public function produto()
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
