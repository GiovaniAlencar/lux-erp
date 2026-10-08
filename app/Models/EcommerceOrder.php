<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EcommerceOrder extends Model
{
    protected $table = 'ecommerce_orders';

    protected $fillable = [
        'order_id',
        'user_id',
        'erp_cliente_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'subtotal',
        'discount',
        'total',
        'status',
        'payment_method',
        'payment_status',
        'notes',
        'whatsapp_message',
        'delivery_street',
        'delivery_number',
        'delivery_complement',
        'delivery_neighborhood',
        'delivery_city',
        'delivery_state',
        'delivery_zip_code',
        'address_id',
        'shipping_price',
        'shipping_status',
        'venda_id',
        'expires_at',
        'cancelled_reason',
    ];

    protected $casts = [
        'subtotal' => 'float',
        'discount' => 'float',
        'total' => 'float',
        'shipping_price' => 'float',
        'expires_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(EcommerceOrderItem::class, 'order_id');
    }

    public function user()
    {
        return $this->belongsTo(EcommerceUser::class, 'user_id');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'erp_cliente_id');
    }

    public function venda()
    {
        return $this->belongsTo(Venda::class, 'venda_id');
    }

    public function reservations()
    {
        return $this->hasMany(EcommerceStockReservation::class, 'order_id');
    }

    /** Janela, em dias, em que um pedido cancelado (inclusive por expiração automática) pode ser restaurado. */
    const DIAS_RESTAURAR_CANCELADO = 3;

    /**
     * Um pedido do sábado (ou de véspera de dias sem expediente) pode expirar sozinho
     * (ver App\Console\Commands\ExpireEcommerceOrders) antes que alguém confirme.
     * Esta janela permite trazê-lo de volta para "aguardando_confirmacao" sem redigitar tudo.
     */
    public function podeRestaurar(): bool
    {
        if ($this->status !== 'cancelado' || $this->venda_id) {
            return false;
        }

        if (!$this->updated_at) {
            return false;
        }

        return $this->updated_at->copy()->addDays(self::DIAS_RESTAURAR_CANCELADO)->isFuture();
    }

    /** Quantos dias inteiros ainda restam para restaurar (0 se hoje é o último dia). */
    public function diasRestantesParaRestaurar(): int
    {
        if (!$this->podeRestaurar()) {
            return 0;
        }

        $limite = $this->updated_at->copy()->addDays(self::DIAS_RESTAURAR_CANCELADO)->startOfDay();

        return max(0, now()->startOfDay()->diffInDays($limite));
    }

    public static function statusLabels(): array
    {
        return [
            'aguardando_confirmacao' => 'Aguardando confirmação',
            'confirmado' => 'Confirmado',
            'em_separacao' => 'Em separação',
            'separado' => 'Separado',
            'alteracao_pendente' => 'Alteração pendente',
            'em_rota_entrega' => 'Em rota de entrega',
            'entregue' => 'Entregue',
            'cancelado' => 'Cancelado',
        ];
    }
}
