<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotaFiscalItem extends Model
{
    protected $table = 'nota_fiscal_itens';

    protected $fillable = [
        'nota_fiscal_id', 'produto_id', 'item_venda_id', 'descricao', 'quantidade', 'valor_unitario',
    ];

    public function produto()
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }

    public function valorTotal(): float
    {
        return round((float) $this->quantidade * (float) $this->valor_unitario, 2);
    }
}
