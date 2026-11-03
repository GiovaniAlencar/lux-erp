<?php

namespace App\Services;

use App\Models\Produto;
use Illuminate\Support\Facades\DB;

/**
 * Custo médio ponderado na entrada de mercadoria — mesma regra da entrada por XML e do pedido de compra.
 * Chame ANTES do pluStock (usa o estoque que existia antes da entrada). Não mexe no preço de venda.
 */
class CustoMedio
{
    public static function aplicar(Produto $produto, float $qtdEntrada, float $custoUnitario): void
    {
        if ($qtdEntrada <= 0 || $custoUnitario <= 0) {
            return;
        }
        $estoqueAntes = max(0, (float) DB::table('estoques')->where('produto_id', $produto->id)->sum('quantidade'));
        $custoAntes = (float) $produto->valor_compra;
        $produto->valor_compra = ($estoqueAntes > 0 && $custoAntes > 0)
            ? round(($estoqueAntes * $custoAntes + $qtdEntrada * $custoUnitario) / ($estoqueAntes + $qtdEntrada), 4)
            : round($custoUnitario, 4);
        if ((float) $produto->valor_venda > 0) {
            $produto->percentual_lucro = __percentual_lucro((float) $produto->valor_compra, (float) $produto->valor_venda);
        }
    }
}
