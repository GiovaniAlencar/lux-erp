<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\EcommerceOrder;
use App\Models\EcommerceStockReservation;

class ExpireEcommerceOrders extends Command
{
    protected $signature = 'ecommerce:expire-orders';
    protected $description = 'Cancela pedidos do site em aguardando_confirmacao há mais de 24h e libera reservas';

    public function handle()
    {
        $orders = EcommerceOrder::where('status', 'aguardando_confirmacao')
            ->where(function ($q) {
                $q->where(function ($w) {
                    $w->whereNotNull('expires_at')->where('expires_at', '<', now());
                })->orWhere(function ($w) {
                    $w->whereNull('expires_at')
                        ->where('created_at', '<', now()->subHours(24));
                });
            })
            ->get();

        $count = 0;
        foreach ($orders as $order) {
            DB::transaction(function () use ($order, &$count) {
                $order->status = 'cancelado';
                $order->cancelled_reason = 'Expirado automaticamente após 24h sem confirmação';
                $order->save();

                EcommerceStockReservation::where('order_id', $order->id)
                    ->where('status', 'active')
                    ->update(['status' => 'released']);
                $count++;
            });
        }

        $this->info("Pedidos expirados: {$count}");
        return 0;
    }
}
