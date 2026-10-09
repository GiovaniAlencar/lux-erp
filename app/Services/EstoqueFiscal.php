<?php

namespace App\Services;

use App\Models\EstoqueFiscalMovimento;
use App\Models\Produto;

/**
 * Saldo de estoque com nota (fiscal). Sempre chamar dentro de DB::transaction.
 */
class EstoqueFiscal
{
    /** Baixa o saldo fiscal pelos itens da venda (qtd_fiscal). */
    public function consumirVenda(int $vendaId, iterable $itens): void
    {
        $porProduto = [];
        foreach ($itens as $it) {
            $q = round((float) ($it->qtd_fiscal ?? 0), 3);
            if ($q > 0) {
                $porProduto[(int) $it->produto_id] = ($porProduto[(int) $it->produto_id] ?? 0) + $q;
            }
        }
        foreach ($porProduto as $pid => $q) {
            $p = Produto::lockForUpdate()->find($pid);
            if (!$p) {
                continue;
            }
            $saldo = (float) $p->estoque_fiscal;
            if ($q > $saldo + 0.0005) {
                throw new \RuntimeException('Saldo fiscal insuficiente para "' . $p->nome . '": disponível '
                    . $this->fmt($saldo) . ', pedido como fiscal ' . $this->fmt($q)
                    . '. Mande o excedente para a conta 2.');
            }
            $this->mover($p, -$q, 'venda', ['venda_id' => $vendaId, 'documento' => 'Pedido #' . $vendaId]);
        }
    }

    /** Devolve o saldo fiscal dos itens (exclusão/edição da venda). */
    public function estornarVenda(int $vendaId, iterable $itens): void
    {
        $porProduto = [];
        foreach ($itens as $it) {
            $q = round((float) ($it->qtd_fiscal ?? 0), 3);
            if ($q > 0) {
                $porProduto[(int) $it->produto_id] = ($porProduto[(int) $it->produto_id] ?? 0) + $q;
            }
        }
        foreach ($porProduto as $pid => $q) {
            $p = Produto::lockForUpdate()->find($pid);
            if ($p) {
                $this->mover($p, $q, 'estorno', ['venda_id' => $vendaId, 'documento' => 'Pedido #' . $vendaId]);
            }
        }
    }

    public function entrada(Produto $p, float $q, array $extra = [], ?float $custoUnit = null): void
    {
        $p = Produto::lockForUpdate()->find($p->id);
        if ($custoUnit !== null && $custoUnit > 0) {
            // custo fiscal = média ponderada só das unidades com nota
            $saldo = max(0, (float) $p->estoque_fiscal);
            $atual = (float) $p->custo_fiscal;
            $p->custo_fiscal = ($saldo > 0 && $atual > 0)
                ? round(($saldo * $atual + abs($q) * $custoUnit) / ($saldo + abs($q)), 4)
                : round($custoUnit, 4);
            $extra['custo_unitario'] = round($custoUnit, 4);
        }
        $this->mover($p, abs($q), 'entrada_xml', $extra);
        if (!$p->fiscal) {
            $p->fiscal = 1;
            $p->save();
        }
    }

    public function ajuste(Produto $p, float $delta, string $obs): void
    {
        $p = Produto::lockForUpdate()->find($p->id);
        if ((float) $p->estoque_fiscal + $delta < -0.0005) {
            throw new \RuntimeException('O ajuste deixaria o saldo fiscal negativo.');
        }
        $this->mover($p, $delta, 'ajuste', ['observacao' => $obs]);
    }

    private function mover(Produto $p, float $delta, string $tipo, array $extra = []): void
    {
        $novo = round((float) $p->estoque_fiscal + $delta, 3);
        if ($novo < 0 && $novo > -0.0005) {
            $novo = 0;
        }
        $p->estoque_fiscal = $novo;
        $p->save();
        EstoqueFiscalMovimento::create(array_merge([
            'empresa_id' => $p->empresa_id,
            'produto_id' => $p->id,
            'tipo' => $tipo,
            'quantidade' => round($delta, 3),
            'saldo_apos' => $novo,
            'usuario_id' => function_exists('get_id_user') ? get_id_user() : null,
        ], $extra));
    }

    private function fmt(float $v): string
    {
        return rtrim(rtrim(number_format($v, 3, ',', '.'), '0'), ',');
    }
}
