<?php

namespace App\Helpers;

use App\Models\Categoria;
use App\Models\Produto;
use Illuminate\Support\Collection;

class PrecoCategoriaVenda
{
    public const GRUPO_ARABES = 'arabes';
    public const GRUPO_MINIATURAS = 'miniaturas';

    public const MODO_AUTO = 'auto';
    public const MODO_NORMAL = 'normal';
    public const MODO_ATACADO_1 = 'atacado_1';
    public const MODO_ATACADO_2 = 'atacado_2';

    public const TIER_NORMAL = 'normal';
    public const TIER_ATACADO_1 = 'atacado_1';
    public const TIER_ATACADO_2 = 'atacado_2';

    private static function definicoesGrupos(): array
    {
        return [
            self::GRUPO_ARABES => [
                'label' => 'Árabes / Francês',
                'nomes_categoria' => [
                    'arabes', 'árabes', 'arabe', 'árabe',
                    'frances', 'francês', 'francesa', 'france',
                ],
                'faixas' => [
                    ['min' => 1, 'max' => 9, 'tier' => self::TIER_NORMAL],
                    ['min' => 10, 'max' => 19, 'tier' => self::TIER_ATACADO_1],
                    ['min' => 20, 'max' => null, 'tier' => self::TIER_ATACADO_2],
                ],
                'metas' => [
                    ['qty' => 10, 'tier' => self::TIER_ATACADO_1],
                    ['qty' => 20, 'tier' => self::TIER_ATACADO_2],
                ],
            ],
            self::GRUPO_MINIATURAS => [
                'label' => 'Miniaturas',
                'nomes_categoria' => ['miniaturas', 'miniatura'],
                'faixas' => [
                    ['min' => 1, 'max' => 5, 'tier' => self::TIER_NORMAL],
                    ['min' => 6, 'max' => 11, 'tier' => self::TIER_ATACADO_1],
                    ['min' => 12, 'max' => null, 'tier' => self::TIER_ATACADO_2],
                ],
                'metas' => [
                    ['qty' => 6, 'tier' => self::TIER_ATACADO_1],
                    ['qty' => 12, 'tier' => self::TIER_ATACADO_2],
                ],
            ],
        ];
    }

    public static function normalizarNome(string $nome): string
    {
        $nome = trim(mb_strtolower($nome, 'UTF-8'));
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nome);

        return $ascii !== false ? strtolower($ascii) : $nome;
    }

    public static function grupoPorNomeCategoria(?string $nomeCategoria): ?string
    {
        if ($nomeCategoria === null || trim($nomeCategoria) === '') {
            return null;
        }

        $norm = self::normalizarNome($nomeCategoria);
        foreach (self::definicoesGrupos() as $grupo => $def) {
            foreach ($def['nomes_categoria'] as $alias) {
                if (self::normalizarNome($alias) === $norm) {
                    return $grupo;
                }
            }
        }

        if (str_contains($norm, 'miniatur')) {
            return self::GRUPO_MINIATURAS;
        }
        if (str_contains($norm, 'arabe') || str_contains($norm, 'franc')) {
            return self::GRUPO_ARABES;
        }

        return null;
    }

    public static function grupoPorCategoriaId(int $categoriaId, ?Collection $categorias = null): ?string
    {
        if ($categoriaId <= 0) {
            return null;
        }

        if ($categorias !== null && $categorias->isNotEmpty()) {
            $cat = $categorias->firstWhere('id', $categoriaId);
        } else {
            $cat = null;
        }

        if (!$cat) {
            $cat = Categoria::find($categoriaId);
        }

        return $cat ? self::grupoPorNomeCategoria($cat->nome) : null;
    }

    public static function resolverCategoriaIds(Collection $categorias): array
    {
        $map = [];
        foreach (self::definicoesGrupos() as $grupo => $def) {
            foreach ($categorias as $cat) {
                if (self::grupoPorNomeCategoria($cat->nome) === $grupo) {
                    $map[$grupo] = (int) $cat->id;
                    break;
                }
            }
        }

        return $map;
    }

    /** @return array<string, array<int, int>> */
    public static function resolverCategoriaIdsPorGrupo(Collection $categorias): array
    {
        $map = [];
        foreach (self::definicoesGrupos() as $grupo => $def) {
            $map[$grupo] = [];
            foreach ($categorias as $cat) {
                if (self::grupoPorNomeCategoria($cat->nome) === $grupo) {
                    $map[$grupo][] = (int) $cat->id;
                }
            }
        }

        return $map;
    }

    public static function configParaFrontend(Collection $categorias): array
    {
        $idsPorGrupo = self::resolverCategoriaIdsPorGrupo($categorias);
        $out = [];
        foreach (self::definicoesGrupos() as $grupo => $def) {
            if (empty($idsPorGrupo[$grupo])) {
                continue;
            }
            $out[$grupo] = [
                'categoria_id' => $idsPorGrupo[$grupo][0],
                'categoria_ids' => $idsPorGrupo[$grupo],
                'label' => $def['label'],
                'metas' => $def['metas'],
            ];
        }

        return $out;
    }

    public static function tierAutomatico(string $grupo, float $quantidadeTotal): string
    {
        $def = self::definicoesGrupos()[$grupo] ?? null;
        if ($def === null) {
            return self::TIER_NORMAL;
        }

        $q = (int) floor($quantidadeTotal + 0.0001);
        foreach ($def['faixas'] as $faixa) {
            $max = $faixa['max'];
            if ($q >= $faixa['min'] && ($max === null || $q <= $max)) {
                return $faixa['tier'];
            }
        }

        return self::TIER_NORMAL;
    }

    public static function tierPorModo(string $modo, string $grupo, float $quantidadeTotal): string
    {
        if ($modo === self::MODO_NORMAL) {
            return self::TIER_NORMAL;
        }
        if ($modo === self::MODO_ATACADO_1) {
            return self::TIER_ATACADO_1;
        }
        if ($modo === self::MODO_ATACADO_2) {
            return self::TIER_ATACADO_2;
        }

        return self::tierAutomatico($grupo, $quantidadeTotal);
    }

    public static function precoPorTier(Produto $produto, string $tier): float
    {
        $normal = (float) ($produto->valor_venda ?? 0);
        $p2 = $produto->preco_2 !== null ? (float) $produto->preco_2 : null;
        $p3 = $produto->preco_3 !== null ? (float) $produto->preco_3 : null;

        if ($tier === self::TIER_ATACADO_2) {
            return $p3 ?? $p2 ?? $normal;
        }
        if ($tier === self::TIER_ATACADO_1) {
            return $p2 ?? $normal;
        }

        return $normal;
    }

    public static function labelTier(string $tier): string
    {
        return match ($tier) {
            self::TIER_ATACADO_1 => 'Atacado 1',
            self::TIER_ATACADO_2 => 'Atacado 2',
            default => 'Normal',
        };
    }

    public static function badgeClassTier(string $tier): string
    {
        return match ($tier) {
            self::TIER_ATACADO_1 => 'badge-tabela-atacado-1',
            self::TIER_ATACADO_2 => 'badge-tabela-atacado-2',
            default => 'badge-tabela-normal',
        };
    }

    /**
     * @param  array<int|string>  $produtoIds
     * @param  array<int|string>  $quantidades
     * @param  array<string, string>  $modosPorGrupo
     * @return array<int, float>  índice => valor unitário
     */
    public static function calcularValoresUnitarios(
        array $produtoIds,
        array $quantidades,
        array $modosPorGrupo,
        ?Collection $categorias = null
    ): array {
        $produtos = Produto::whereIn('id', array_unique(array_map('intval', $produtoIds)))->get()->keyBy('id');

        $qtdPorGrupo = [];
        $grupoPorIndice = [];

        foreach ($produtoIds as $i => $pid) {
            $pid = (int) $pid;
            $qtd = (float) __convert_value_bd((string) ($quantidades[$i] ?? 0));
            $produto = $produtos->get($pid);
            if (!$produto) {
                continue;
            }
            $grupo = self::grupoPorCategoriaId((int) $produto->categoria_id, $categorias);
            $grupoPorIndice[$i] = $grupo;
            if ($grupo !== null) {
                $qtdPorGrupo[$grupo] = ($qtdPorGrupo[$grupo] ?? 0) + $qtd;
            }
        }

        $tierPorGrupo = [];
        foreach ($qtdPorGrupo as $grupo => $total) {
            $modo = $modosPorGrupo[$grupo] ?? self::MODO_AUTO;
            $tierPorGrupo[$grupo] = self::tierPorModo($modo, $grupo, $total);
        }

        $valores = [];
        foreach ($produtoIds as $i => $pid) {
            $pid = (int) $pid;
            $produto = $produtos->get($pid);
            if (!$produto) {
                continue;
            }
            $grupo = $grupoPorIndice[$i] ?? null;
            if ($grupo === null) {
                $valores[$i] = (float) $produto->valor_venda;
                continue;
            }
            $tier = $tierPorGrupo[$grupo] ?? self::TIER_NORMAL;
            $valores[$i] = self::precoPorTier($produto, $tier);
        }

        return $valores;
    }

    public static function infoPainel(string $grupo, float $quantidadeTotal, string $modo, string $tierAtivo): array
    {
        $def = self::definicoesGrupos()[$grupo];
        $q = (int) floor($quantidadeTotal + 0.0001);
        $labelTier = self::labelTier($tierAtivo);

        $proximaMeta = null;
        foreach ($def['metas'] as $meta) {
            if ($tierAtivo === self::TIER_ATACADO_2) {
                break;
            }
            if ($modo !== self::MODO_AUTO) {
                break;
            }
            if ($q < $meta['qty'] && ($proximaMeta === null || $meta['qty'] < $proximaMeta['qty'])) {
                $proximaMeta = $meta;
            }
        }

        $mensagem = '';
        $progressoAtual = 0;
        $progressoMeta = 0;

        if ($modo !== self::MODO_AUTO) {
            $mensagem = $labelTier . ' (manual)';
        } elseif ($tierAtivo === self::TIER_ATACADO_2) {
            $mensagem = 'Atacado 2 ativo';
            $ultima = end($def['metas']);
            $progressoAtual = $q;
            $progressoMeta = $ultima['qty'] ?? $q;
        } elseif ($proximaMeta !== null) {
            $faltam = max(0, $proximaMeta['qty'] - $q);
            $tierLabel = self::labelTier($proximaMeta['tier']);
            if ($tierAtivo !== self::TIER_NORMAL) {
                $mensagem = $labelTier . ' ativo. Faltam ' . $faltam . ' unidades para liberar ' . $tierLabel;
            } else {
                $mensagem = 'Faltam ' . $faltam . ' unidades para liberar ' . $tierLabel;
            }
            $progressoAtual = $q;
            $progressoMeta = $proximaMeta['qty'];
        } else {
            $mensagem = $labelTier . ' ativo';
        }

        return [
            'quantidade' => $q,
            'tier' => $tierAtivo,
            'tier_label' => $labelTier,
            'mensagem' => $mensagem,
            'progresso_atual' => $progressoAtual,
            'progresso_meta' => $progressoMeta,
        ];
    }
}
