<?php

namespace App\Services;

use App\Helpers\StockMove;
use App\Models\CategoriaConta;
use App\Models\Compra;
use App\Models\CompraPedido;
use App\Models\CompraPedidoCusto;
use App\Models\CompraPedidoItem;
use App\Models\CompraPedidoPendencia;
use App\Models\Estoque;
use App\Models\FinConta;
use App\Models\FinMovimento;
use App\Models\ItemCompra;
use App\Models\Produto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Regras do pedido de compra:
 *
 *  - taxa efetiva (R$ por US$) = o que saiu em reais nos pagamentos ÷ o que foi pago em dólar
 *    (já inclui o % do frete do agente). Antes de pagar, usa a cotação prevista + %.
 *  - custo unitário final = preço × taxa efetiva + fretes/transportadora rateados pelo valor do item.
 *  - entrada no estoque com o custo médio (mistura com o que já existe, igual à entrada pelo XML);
 *    o preço de venda NUNCA muda sozinho.
 *  - o que não chegou vira pendência (valor a receber/descontar do fornecedor).
 */
class PedidoCompra
{
    public static function ativo(): bool
    {
        static $ok = null;
        return $ok ??= Schema::hasTable('compra_pedidos');
    }

    public static function categoriaCompras($empresaId): int
    {
        $c = CategoriaConta::firstOrCreate(
            ['empresa_id' => $empresaId, 'tipo' => 'pagar', 'nome' => 'Compras'],
            Schema::hasColumn('categoria_contas', 'despesa') ? ['despesa' => 0] : []
        );
        return (int) $c->id;
    }

    /** Números do pedido para a tela e para o fechamento. */
    public static function resumo(CompraPedido $p): array
    {
        $p->loadMissing(['itens', 'custos', 'pendencias']);
        $fornecedor = round((float) $p->itens->sum(fn ($i) => $i->quantidade * $i->preco_moeda), 2);
        // % do agente entra antes da conversão: é o que se paga em dólar ao todo
        $total = round($fornecedor * $p->fatorAgente(), 2);
        $pag = $p->custos->where('tipo', 'pagamento');
        $pagoMoeda = round((float) $pag->sum('valor_moeda'), 2);
        $pagoBrl = round((float) $pag->sum('valor_brl'), 2);
        $fretes = round((float) $p->custos->where('tipo', '!=', 'pagamento')->sum('valor_brl'), 2);

        if ($p->moeda === 'BRL') {
            $taxa = 1.0;
        } elseif ($pagoMoeda > 0) {
            $taxa = $pagoBrl / $pagoMoeda;
        } else {
            $taxa = (float) ($p->cotacao_prevista ?: 0) * (1 + (float) ($p->pct_frete_previsto ?: 0) / 100);
        }
        // no pedido em real, "pago" é o próprio valor em reais
        if ($p->moeda === 'BRL') {
            $pagoMoeda = round((float) $pag->sum(fn ($c) => $c->valor_moeda ?: $c->valor_brl), 2);
        }
        $faltaPagar = round(max(0, $total - $pagoMoeda), 2);
        $estimadoBrl = round($pagoBrl + $faltaPagar * $taxa + $fretes, 2);
        $pendAbertas = $p->pendencias->where('status', 'aberta');

        return [
            'total_fornecedor' => $fornecedor,
            'agente_moeda' => round($total - $fornecedor, 2),
            'total_moeda' => $total,
            'pago_moeda' => $pagoMoeda,
            'falta_pagar_moeda' => $faltaPagar,
            'pago_brl' => $pagoBrl,
            'fretes_brl' => $fretes,
            'gasto_brl' => round($pagoBrl + $fretes, 2),
            'taxa' => $taxa,
            'estimado_brl' => $estimadoBrl,
            'falta_pagar_brl' => round($faltaPagar * $taxa, 2),
            'pendencias_abertas' => $pendAbertas->count(),
            'pendencias_brl' => round((float) $pendAbertas->sum('valor_brl'), 2),
            'situacao_pagamento' => $total <= 0 ? 'sem_itens' : ($faltaPagar <= 0.009 ? 'pago' : ($pagoMoeda > 0 ? 'parcial' : 'a_pagar')),
        ];
    }

    /** Custo unitário em R$ de cada item (preço × taxa + fretes rateados pelo valor). */
    public static function custosItens(CompraPedido $p, ?array $resumo = null): array
    {
        $r = $resumo ?? self::resumo($p);
        $out = [];
        $fator = $p->fatorAgente();
        foreach ($p->itens as $i) {
            $sub = $i->quantidade * $i->preco_moeda * $fator;
            $rateio = $r['total_moeda'] > 0 && $i->quantidade > 0 ? ($r['fretes_brl'] * $sub / $r['total_moeda']) / $i->quantidade : 0;
            $out[$i->id] = round($i->preco_moeda * $fator * $r['taxa'] + $rateio, 4);
        }
        return $out;
    }

    /** Pagamento, frete, transportadora ou outro custo: lança a saída na conta escolhida. */
    public static function lancarCusto(CompraPedido $p, array $d, ?int $usuarioId): CompraPedidoCusto
    {
        return DB::transaction(function () use ($p, $d, $usuarioId) {
            $conta = !empty($d['fin_conta_id']) ? FinConta::where('empresa_id', $p->empresa_id)->findOrFail((int) $d['fin_conta_id']) : null;
            $c = CompraPedidoCusto::create([
                'pedido_id' => $p->id,
                'tipo' => $d['tipo'],
                'data' => $d['data'],
                'valor_moeda' => $d['valor_moeda'] ?? null,
                'cotacao' => $d['cotacao'] ?? null,
                'pct_frete' => $d['pct_frete'] ?? null,
                'valor_brl' => round((float) $d['valor_brl'], 2),
                'descricao' => isset($d['descricao']) ? mb_substr((string) $d['descricao'], 0, 255) : null,
                'fin_conta_id' => $conta?->id,
                'usuario_id' => $usuarioId,
            ]);
            if ($conta && $c->valor_brl > 0) {
                $rot = CompraPedidoCusto::TIPOS[$c->tipo] ?? $c->tipo;
                $mov = FinMovimento::create([
                    'empresa_id' => $p->empresa_id,
                    'conta_id' => $conta->id,
                    'tipo' => 'saida',
                    'valor' => $c->valor_brl,
                    'data' => $c->data->format('Y-m-d'),
                    'descricao' => mb_substr($rot . ' · pedido de compra #' . $p->id . ' · ' . $p->nomeFornecedor()
                        . ($c->valor_moeda ? ' · ' . $p->simbolo() . ' ' . __moeda($c->valor_moeda) . ($c->cotacao ? ' × ' . rtrim(rtrim(number_format($c->cotacao, 4, ',', ''), '0'), ',') : '') . ($c->pct_frete ? ' + ' . rtrim(rtrim(number_format($c->pct_frete, 2, ',', ''), '0'), ',') . '%' : '') : '')
                        . ($c->descricao ? ' · ' . $c->descricao : ''), 0, 255),
                    'origem' => 'compra',
                    'categoria_id' => self::categoriaCompras($p->empresa_id),
                    'usuario_id' => $usuarioId,
                ]);
                $c->fin_movimento_id = $mov->id;
                $c->save();
            }
            if ($p->status === 'rascunho') {
                $p->status = 'pedido';
                $p->save();
            }
            return $c;
        });
    }

    public static function excluirCusto(CompraPedidoCusto $c): void
    {
        DB::transaction(function () use ($c) {
            if ($c->fin_movimento_id && ($m = FinMovimento::find($c->fin_movimento_id))) {
                Financeiro::estornar($m);
            }
            $c->delete();
        });
    }

    /**
     * Entrada: $receb = [item_id => ['qtd' => x, 'produto_id' => y]].
     * $comEstoque = false quando a entrada no estoque vai ser feita pelo XML da nota.
     */
    public static function darEntrada(CompraPedido $p, array $receb, bool $comEstoque, ?int $usuarioId): array
    {
        return DB::transaction(function () use ($p, $receb, $comEstoque, $usuarioId) {
            $p = CompraPedido::whereKey($p->id)->lockForUpdate()->firstOrFail();
            if ($p->status === 'recebido') {
                throw new \InvalidArgumentException('Este pedido já foi recebido.');
            }
            $p->load(['itens', 'custos', 'pendencias']);
            $r = self::resumo($p);
            $custos = self::custosItens($p, $r);
            $stock = new StockMove();
            $avisos = [];
            $faltas = 0;
            $compra = null;

            if ($comEstoque && $p->fornecedor_id) {
                $compra = Compra::create([
                    'empresa_id' => (int) $p->empresa_id,
                    'fornecedor_id' => (int) $p->fornecedor_id,
                    'usuario_id' => $usuarioId ?: get_id_user(),
                    'observacao' => mb_substr('Pedido de compra #' . $p->id . ($p->moeda === 'USD' ? ' · US$ ' . __moeda($r['total_moeda']) . ' · taxa R$ ' . number_format($r['taxa'], 4, ',', '') : ''), 0, 255),
                    'estado' => 'aprovado',
                    'total' => 0,
                    'desconto' => 0, 'valor_frete' => $r['fretes_brl'], 'qtd_volumes' => 0, 'peso_liquido' => 0, 'peso_bruto' => 0,
                    'tipo' => 0, 'natureza_id' => 0, 'tipo_pagamento' => '', 'filial_id' => null,
                ]);
            }
            $totalCompra = 0;

            foreach ($p->itens as $i) {
                $linha = $receb[$i->id] ?? [];
                $qtd = max(0, round((float) ($linha['qtd'] ?? $i->quantidade), 2));
                $prodId = (int) ($linha['produto_id'] ?? $i->produto_id);
                if ($prodId && $prodId !== (int) $i->produto_id) {
                    $i->produto_id = $prodId;
                }
                $cu = $custos[$i->id] ?? null;
                $i->qtd_recebida = $qtd;
                $i->custo_final = $cu;
                $i->save();

                if ($qtd + 0.001 < $i->quantidade) {
                    $falta = round($i->quantidade - $qtd, 2);
                    CompraPedidoPendencia::create([
                        'pedido_id' => $p->id,
                        'item_id' => $i->id,
                        'tipo' => 'falta',
                        'descricao' => mb_substr('Faltou ' . rtrim(rtrim(number_format($falta, 2, ',', ''), '0'), ',') . ' × ' . $i->nome(), 0, 255),
                        'quantidade' => $falta,
                        'valor_moeda' => round($falta * $i->preco_moeda * $p->fatorAgente(), 2),
                        'valor_brl' => round($falta * (float) $cu, 2),
                        'status' => 'aberta',
                        'usuario_id' => $usuarioId,
                    ]);
                    $faltas++;
                }

                if (!$comEstoque || $qtd <= 0) {
                    // entrada pelo XML depois: aqui só os preços, se você mudou algum
                    if (!empty($linha['precos']) && $i->produto_id && ($prod = Produto::where('empresa_id', $p->empresa_id)->find($i->produto_id))) {
                        self::aplicarPrecos($prod, $linha['precos']);
                        $prod->save();
                    }
                    continue;
                }
                $prod = $i->produto_id ? Produto::where('empresa_id', $p->empresa_id)->lockForUpdate()->find($i->produto_id) : null;
                if (!$prod) {
                    $avisos[] = '"' . $i->nome() . '" sem produto cadastrado: não entrou no estoque.';
                    continue;
                }
                self::entrarEstoque($prod, $qtd, (float) $cu, $stock, $linha['novo_custo'] ?? null, $linha['precos'] ?? []);
                $totalCompra += $qtd * (float) $cu;
                if ($compra) {
                    ItemCompra::create([
                        'compra_id' => $compra->id,
                        'produto_id' => $prod->id,
                        'quantidade' => $qtd,
                        'valor_unitario' => round((float) $cu, 4),
                        'unidade_compra' => $prod->unidade_compra,
                    ]);
                }
            }
            if ($compra) {
                $compra->total = round($totalCompra, 2);
                $compra->save();
            }

            $p->status = 'recebido';
            $p->recebido_em = now();
            $p->entrada_estoque = $comEstoque;
            $p->compra_id = $compra?->id;
            $p->save();
            return ['faltas' => $faltas, 'avisos' => $avisos];
        });
    }

    /**
     * Soma no estoque e recalcula o custo médio. O preço de venda só muda se você preencheu na conferência
     * ($precos = ['valor_venda' => x, 'preco_2' => y, 'preco_3' => z]); $custoManual substitui o médio calculado.
     */
    public static function entrarEstoque(Produto $prod, float $qtd, float $custoUnit, ?StockMove $stock = null, ?float $custoManual = null, array $precos = []): void
    {
        $stock = $stock ?? new StockMove();
        $antes = max(0, (float) Estoque::where('produto_id', $prod->id)->whereNull('filial_id')->value('quantidade'));
        $custoAntes = (float) $prod->valor_compra;
        if ($custoManual !== null && $custoManual > 0) {
            $prod->valor_compra = round($custoManual, 2);
        } elseif ($custoUnit > 0) {
            $prod->valor_compra = ($antes > 0 && $custoAntes > 0)
                ? round(($antes * $custoAntes + $qtd * $custoUnit) / ($antes + $qtd), 2)
                : round($custoUnit, 2);
        }
        $stock->pluStock($prod->id, $qtd, (float) $prod->valor_compra, -1);
        self::aplicarPrecos($prod, $precos);
        $prod->save();
    }

    /** Preços preenchidos na conferência (vazio = mantém) e o % de lucro coerente com o custo. */
    public static function aplicarPrecos(Produto $prod, array $precos): void
    {
        foreach (['valor_venda', 'preco_2', 'preco_3'] as $col) {
            if (isset($precos[$col]) && $precos[$col] !== null && (float) $precos[$col] > 0) {
                $prod->{$col} = round((float) $precos[$col], 2);
            }
        }
        if ((float) $prod->valor_compra > 0) {
            $prod->percentual_lucro = __percentual_lucro((float) $prod->valor_compra, (float) $prod->valor_venda);
        }
    }
}
