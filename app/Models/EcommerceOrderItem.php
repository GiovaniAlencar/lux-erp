<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EcommerceOrderItem extends Model
{
    protected $table = 'ecommerce_order_items';
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'product_price',
        'quantity',
        'total_price',
        'promocao_id',
    ];

    public function order()
    {
        return $this->belongsTo(EcommerceOrder::class, 'order_id');
    }

    public function produto()
    {
        return $this->belongsTo(Produto::class, 'product_id');
    }

    /**
     * Promoção (App\Models\Promocao) aplicada a este item no momento da compra no site,
     * quando o preço enviado pelo checkout bateu com o preço promocional vigente.
     * Nula quando o item foi comprado no preço padrão/tabela de atacado.
     */
    public function promocao()
    {
        return $this->belongsTo(Promocao::class, 'promocao_id');
    }
}
