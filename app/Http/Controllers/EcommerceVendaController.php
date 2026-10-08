<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\EcommerceOrder;
use App\Models\EcommerceStockReservation;
use App\Models\EcommerceUser;
use App\Models\Venda;
use App\Models\ItemVenda;
use App\Models\Produto;
use App\Models\Estoque;
use App\Models\Promocao;
use App\Models\NaturezaOperacao;
use App\Models\Cliente;
use App\Helpers\EcommerceSync;

class EcommerceVendaController extends Controller
{
    public function index(Request $request)
    {
        $statusFilter = $request->status;
        $data = EcommerceOrder::with(['user', 'cliente', 'items'])
            ->when($statusFilter === '__all__', function ($q) {
                return $q;
            }, function ($q) use ($statusFilter, $request) {
                if ($statusFilter) {
                    return $q->where('status', $statusFilter);
                }
                if (!$request->filled('q')) {
                    return $q->where('status', 'aguardando_confirmacao');
                }
                return $q;
            })
            ->when($request->q, function ($q) use ($request) {
                $term = $request->q;
                return $q->where(function ($w) use ($term) {
                    $w->where('order_id', 'LIKE', "%{$term}%")
                        ->orWhere('customer_name', 'LIKE', "%{$term}%")
                        ->orWhere('customer_phone', 'LIKE', "%{$term}%")
                        ->orWhere('customer_email', 'LIKE', "%{$term}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate(env('PAGINACAO', 30));

        $statusLabels = EcommerceOrder::statusLabels();
        return view('ecommerce_vendas.index', compact('data', 'statusLabels'));
    }

    public function show($id)
    {
        $item = EcommerceOrder::with(['items.produto', 'user.cliente', 'cliente', 'venda'])->findOrFail($id);
        $statusLabels = EcommerceOrder::statusLabels();
        return view('ecommerce_vendas.show', compact('item', 'statusLabels'));
    }

    public function confirmar(Request $request, $id)
    {
        $order = EcommerceOrder::with(['items', 'user'])->findOrFail($id);

        if ($order->status !== 'aguardando_confirmacao') {
            session()->flash('flash_erro', 'Este pedido não está aguardando confirmação.');
            return redirect()->route('ecommerce-vendas.show', $order->id);
        }

        $clienteId = (int)($request->cliente_id
            ?: ($order->erp_cliente_id ?: optional($order->user)->erp_cliente_id));

        if ($clienteId <= 0) {
            session()->flash('flash_erro', 'Vincule um cliente ERP ao usuário do site antes de confirmar.');
            return redirect()->route('ecommerce-vendas.show', $order->id);
        }

        $cliente = Cliente::find($clienteId);
        if (!$cliente || !__valida_objeto($cliente)) {
            session()->flash('flash_erro', 'Cliente ERP inválido.');
            return redirect()->route('ecommerce-vendas.show', $order->id);
        }

        $naturezaId = (int)($request->natureza_id ?: NaturezaOperacao::where('empresa_id', request()->empresa_id)->value('id') ?: 1);

        $idsRemover = collect($request->input('remover_itens', []))
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->values();

        $itensIncluidos = $order->items->whereNotIn('id', $idsRemover)->values();
        $itensRemovidos = $order->items->whereIn('id', $idsRemover)->values();

        if ($itensIncluidos->isEmpty()) {
            session()->flash('flash_erro', 'Não é possível remover todos os itens do pedido. Para descartar o pedido inteiro, use "Cancelar".');
            return redirect()->route('ecommerce-vendas.show', $order->id);
        }

        try {
            $venda = DB::transaction(function () use ($order, $cliente, $naturezaId, $request, $itensIncluidos, $itensRemovidos) {
                $frete = $request->filled('frete')
                    ? (float)str_replace(',', '.', $request->frete)
                    : (float)($order->shipping_price ?? 0);
                $desconto = $request->filled('desconto')
                    ? (float)str_replace(',', '.', $request->desconto)
                    : (float)($order->discount ?? 0);

                $subtotal = 0;
                foreach ($itensIncluidos as $it) {
                    $subtotal += ((float)$it->product_price) * ((float)$it->quantity);
                }
                // valor_total no ERP = soma dos itens incluídos (sem frete)
                $valorItens = $subtotal;
                $valorTotalSite = $subtotal - $desconto + $frete;

                // Baixa estoque definitivo (reserva vira consumo) — somente dos itens incluídos
                $sumByProduct = [];
                foreach ($itensIncluidos as $it) {
                    $pid = (int)$it->product_id;
                    $sumByProduct[$pid] = ($sumByProduct[$pid] ?? 0) + (float)$it->quantity;
                }

                $locked = [];
                foreach ($sumByProduct as $pid => $sum) {
                    $row = Estoque::where('produto_id', $pid)->lockForUpdate()->first();
                    $available = $row ? (float)$row->quantidade : 0;
                    if ($available + 0.0001 < $sum) {
                        throw new \Exception("Estoque insuficiente para produto #{$pid}. Disponível: {$available}");
                    }
                    $locked[$pid] = $row;
                }

                $enderecoObs = trim(implode(' | ', array_filter([
                    $order->delivery_street
                        ? ($order->delivery_street . ', ' . $order->delivery_number
                            . ($order->delivery_complement ? ' - ' . $order->delivery_complement : ''))
                        : null,
                    $order->delivery_neighborhood,
                    ($order->delivery_city && $order->delivery_state)
                        ? ($order->delivery_city . '/' . $order->delivery_state)
                        : null,
                    $order->delivery_zip_code ? ('CEP ' . $order->delivery_zip_code) : null,
                ])));

                $obs = trim(($order->notes ?: '') . ($enderecoObs ? "\nEndereço site: " . $enderecoObs : ''));

                if ($itensRemovidos->isNotEmpty()) {
                    $listaRemovidos = $itensRemovidos->map(function ($it) {
                        return '#' . $it->product_id . ' ' . $it->product_name . ' (qtd ' . $it->quantity . ')';
                    })->implode('; ');
                    $obs = trim($obs . "\nItens indisponíveis removidos do pedido do site: " . $listaRemovidos);
                }

                $venda = Venda::create([
                    'cliente_id' => $cliente->id,
                    'usuario_id' => get_id_user(),
                    'natureza_id' => $naturezaId,
                    'empresa_id' => request()->empresa_id,
                    'valor_total' => $valorItens,
                    'desconto' => $desconto,
                    'acrescimo' => 0,
                    'frete' => $frete,
                    'forma_pagamento' => 'a_vista',
                    'tipo_pagamento' => '01',
                    'estado_emissao' => 'novo',
                    'observacao' => $obs,
                    'pedido_ecommerce_id' => $order->id,
                    'origem' => 'site',
                    'status_pedido' => 'confirmado',
                    'status_pagamento' => 'pendente',
                    'chave' => '',
                    'sequencia_cce' => 0,
                    'numero_nfe' => 0,
                ]);

                $natureza = NaturezaOperacao::find($naturezaId);
                foreach ($itensIncluidos as $it) {
                    $product = Produto::find($it->product_id);
                    $cfop = 0;
                    if ($product && $natureza) {
                        $cfop = $natureza->sobrescreve_cfop
                            ? $natureza->CFOP_saida_estadual
                            : ($product->CFOP_saida_estadual ?? 0);
                    }
                    ItemVenda::create([
                        'venda_id' => $venda->id,
                        'produto_id' => (int)$it->product_id,
                        'quantidade' => (float)$it->quantity,
                        'valor' => (float)$it->product_price,
                        'valor_custo' => $product ? ($product->valor_compra ?? 0) : 0,
                        'cfop' => $cfop ?: 0,
                        'x_pedido' => $order->order_id,
                        'num_item_pedido' => (string)$it->id,
                    ]);

                    $pid = (int)$it->product_id;
                    if (isset($locked[$pid]) && $locked[$pid]) {
                        $locked[$pid]->quantidade -= (float)$it->quantity;
                        if ($locked[$pid]->quantidade < 0.010) {
                            $locked[$pid]->quantidade = 0;
                        }
                        $locked[$pid]->save();
                    }

                    // Item comprado pelo preço promocional vigente: consome unidades do
                    // limite da promoção só agora, na confirmação (reserva ainda não conta).
                    if ($it->promocao_id) {
                        $promocao = Promocao::where('id', $it->promocao_id)->lockForUpdate()->first();
                        if ($promocao) {
                            $promocao->quantidade_utilizada = (int)$promocao->quantidade_utilizada + (int)$it->quantity;
                            $promocao->save();
                        }
                    }
                }

                EcommerceStockReservation::where('order_id', $order->id)
                    ->where('status', 'active')
                    ->whereIn('product_id', $itensIncluidos->pluck('product_id')->all())
                    ->update(['status' => 'consumed']);

                if ($itensRemovidos->isNotEmpty()) {
                    EcommerceStockReservation::where('order_id', $order->id)
                        ->where('status', 'active')
                        ->whereIn('product_id', $itensRemovidos->pluck('product_id')->all())
                        ->update(['status' => 'released']);
                }

                // Atualiza endereço do cliente com o do pedido (se veio preenchido)
                if ($order->delivery_street) {
                    $cliente->rua = $order->delivery_street;
                    $cliente->numero = $order->delivery_number ?: $cliente->numero;
                    $cliente->complemento = $order->delivery_complement;
                    $cliente->bairro = $order->delivery_neighborhood ?: $cliente->bairro;
                    if ($order->delivery_zip_code) {
                        $cliente->cep = $order->delivery_zip_code;
                    }
                    $cliente->save();
                }

                $order->erp_cliente_id = $cliente->id;
                $order->venda_id = $venda->id;
                $order->status = 'confirmado';
                $order->payment_status = 'pendente';
                $order->shipping_price = $frete;
                if ($frete > 0) {
                    $order->shipping_status = 'fixed';
                }
                $order->discount = $desconto;
                $order->subtotal = $subtotal;
                $order->total = $valorTotalSite;
                $order->save();

                if ($order->user && !$order->user->erp_cliente_id) {
                    $order->user->erp_cliente_id = $cliente->id;
                    $order->user->save();
                }

                return $venda;
            });

            $msgSucesso = 'Pedido confirmado! Venda #' . $venda->id . ' gerada (origem: site).';
            if ($itensRemovidos->isNotEmpty()) {
                $msgSucesso .= ' ' . $itensRemovidos->count() . ' item(ns) indisponível(is) foram removidos e não entraram na venda.';
            }
            session()->flash('flash_sucesso', $msgSucesso);
            return redirect()->route('vendas.show', $venda->id);
        } catch (\Exception $e) {
            session()->flash('flash_erro', 'Erro ao confirmar: ' . $e->getMessage());
            __saveLogError($e, request()->empresa_id);
            return redirect()->route('ecommerce-vendas.show', $order->id);
        }
    }

    public function cancelar(Request $request, $id)
    {
        $order = EcommerceOrder::findOrFail($id);

        if (!in_array($order->status, ['aguardando_confirmacao', 'confirmado'], true)) {
            // permite cancelar só se ainda não avançou demais sem venda fechada
        }
        if ($order->status === 'cancelado') {
            session()->flash('flash_erro', 'Pedido já está cancelado.');
            return redirect()->route('ecommerce-vendas.show', $order->id);
        }
        if ($order->venda_id) {
            session()->flash('flash_erro', 'Pedido já virou venda #' . $order->venda_id . '. Cancele pela tela de vendas se necessário.');
            return redirect()->route('ecommerce-vendas.show', $order->id);
        }

        try {
            DB::transaction(function () use ($order, $request) {
                $order->status = 'cancelado';
                $order->cancelled_reason = $request->cancelled_reason ?: 'Cancelado no ERP';
                $order->save();

                EcommerceStockReservation::where('order_id', $order->id)
                    ->where('status', 'active')
                    ->update(['status' => 'released']);
            });
            session()->flash('flash_sucesso', 'Pedido cancelado e estoque liberado.');
        } catch (\Exception $e) {
            session()->flash('flash_erro', 'Erro ao cancelar: ' . $e->getMessage());
        }

        return redirect()->route('ecommerce-vendas.show', $order->id);
    }

    /**
     * Restaura um pedido cancelado (inclusive por expiração automática, ex.: pedido de
     * sábado que expirou antes de alguém confirmar) para "aguardando_confirmacao",
     * evitando redigitar tudo. Disponível por até EcommerceOrder::DIAS_RESTAURAR_CANCELADO dias.
     */
    public function restaurar(Request $request, $id)
    {
        $order = EcommerceOrder::findOrFail($id);

        if (!$order->podeRestaurar()) {
            session()->flash('flash_erro', 'Este pedido não pode mais ser restaurado (prazo de '
                . EcommerceOrder::DIAS_RESTAURAR_CANCELADO . ' dias esgotado ou pedido já convertido em venda).');
            return redirect()->route('ecommerce-vendas.show', $order->id);
        }

        $order->status = 'aguardando_confirmacao';
        $order->expires_at = now()->addHours(24);
        $order->cancelled_reason = null;
        $order->save();

        session()->flash('flash_sucesso', 'Pedido restaurado! Está novamente aguardando confirmação (não é necessário redigitar).');
        return redirect()->route('ecommerce-vendas.show', $order->id);
    }

    public function atualizarValores(Request $request, $id)
    {
        $order = EcommerceOrder::findOrFail($id);
        if ($order->venda_id) {
            session()->flash('flash_erro', 'Pedido já convertido em venda. Altere na venda.');
            return redirect()->route('ecommerce-vendas.show', $order->id);
        }

        $frete = (float)str_replace(',', '.', $request->shipping_price ?? 0);
        $desconto = (float)str_replace(',', '.', $request->discount ?? 0);
        $subtotal = (float)$order->subtotal;
        $order->shipping_price = $frete;
        $order->discount = $desconto;
        $order->shipping_status = $frete > 0 ? 'fixed' : ($order->shipping_status ?: 'to_combine');
        $order->total = $subtotal - $desconto + $frete;
        $order->save();

        session()->flash('flash_sucesso', 'Valores atualizados.');
        return redirect()->route('ecommerce-vendas.show', $order->id);
    }
}
