<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EcommerceStockReservation extends Model
{
    protected $table = 'ecommerce_stock_reservations';
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'reserved_at',
        'expires_at',
        'status',
    ];

    protected $casts = [
        'quantity' => 'float',
        'reserved_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(EcommerceOrder::class, 'order_id');
    }
}
