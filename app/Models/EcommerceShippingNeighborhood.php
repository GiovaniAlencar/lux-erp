<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EcommerceShippingNeighborhood extends Model
{
    protected $table = 'ecommerce_shipping_neighborhoods';

    protected $fillable = [
        'neighborhood',
        'city',
        'state',
        'shipping_price',
        'active',
        'notes',
    ];

    protected $casts = [
        'shipping_price' => 'float',
        'active' => 'boolean',
    ];
}
