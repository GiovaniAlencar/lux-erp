<?php

namespace App\Helpers;

use App\Models\EcommerceOrder;
use App\Models\EcommerceOrderItem;
use App\Models\Produto;
use App\Models\Venda;
use Illuminate\Support\Facades\DB;

class EcommerceSync
{
    /**
     * Espelha status, valores e itens da venda ERP no pedido do site.
     *
     * Convenção ERP: valor_total = soma dos itens (sem frete).
     * Total do site = valor_total - desconto + acrescimo + frete.
     */
    public static function syncFromVenda(Venda $venda, bool $syncItems = true): void
    {
        $pedidoId = (int)($venda->pedido_ecommerce_id ?? 0);
        if ($pedidoId <= 0) {
            return;
        }

        $order = EcommerceOrder::find($pedidoId);
        if (!$order) {
            return;
        }

        $venda->loadMissing(['itens.produto']);

        $map = [
            'aguardando_confirmacao' => 'aguardando_confirmacao',
            'em_elaboracao' => 'aguardando_confirmacao',
            'confirmado' => 'confirmado',
            'em_separacao' => 'em_separacao',
            'separado' => 'separado',
            'alteracao_pendente' => 'alteracao_pendente',
            'em_rota_entrega' => 'em_rota_entrega',
            'entregue' => 'entregue',
            'cancelado' => 'cancelado',
            'cancelada' => 'cancelado',
        ];

        $statusPedido = $venda->status_pedido ?? 'confirmado';
        $order->status = $map[$statusPedido] ?? $statusPedido;
        $order->payment_status = $venda->status_pagamento ?? $order->payment_status;
        $order->venda_id = $venda->id;

        $subtotal = 0.0;
        if ($venda->itens && $venda->itens->count() > 0) {
            foreach ($venda->itens as $it) {
                $subtotal += ((float)$it->quantidade) * ((float)$it->valor);
            }
        } else {
            // fallback: valor_total no ERP é a soma dos itens
            $subtotal = (float)$venda->valor_total;
        }

        $desconto = (float)($venda->desconto ?? 0);
        $acrescimo = (float)($venda->acrescimo ?? 0);
        $frete = (float)($venda->frete ?? 0);

        $order->subtotal = round($subtotal, 2);
        $order->discount = round($desconto, 2);
        $order->shipping_price = round($frete, 2);
        if ($frete > 0) {
            $order->shipping_status = 'fixed';
        }
        $order->total = round($subtotal - $desconto + $acrescimo + $frete, 2);

        $order->save();

        if ($syncItems && $venda->itens) {
            self::syncItems($order, $venda);
        }
    }

    public static function syncByVendaIds(array $ids, bool $syncItems = false): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) {
            return;
        }

        $vendas = Venda::with(['itens.produto'])
            ->whereIn('id', $ids)
            ->where('pedido_ecommerce_id', '>', 0)
            ->get();

        foreach ($vendas as $venda) {
            self::syncFromVenda($venda, $syncItems);
        }
    }

    private static function syncItems(EcommerceOrder $order, Venda $venda): void
    {
        DB::transaction(function () use ($order, $venda) {
            EcommerceOrderItem::where('order_id', $order->id)->delete();

            foreach ($venda->itens as $it) {
                $qty = (float)$it->quantidade;
                $price = (float)$it->valor;
                $name = optional($it->produto)->nome;
                if (!$name) {
                    $name = 'Produto #' . $it->produto_id;
                }

                EcommerceOrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => (int)$it->produto_id,
                    'product_name' => $name,
                    'product_price' => $price,
                    'quantity' => (int)round($qty),
                    'total_price' => round($qty * $price, 2),
                ]);
            }
        });
    }
}
