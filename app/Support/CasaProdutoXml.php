<?php

namespace App\Support;

/**
 * Liga itens de XML de NF-e a produtos do sistema (código de barras, nome igual ou parecido).
 */
trait CasaProdutoXml
{
    /**
     * Monta os índices usados por casarProduto().
     * @return array{0: array, 1: array, 2: \Illuminate\Support\Collection} [porEan, porNome, produtosPorId]
     */
    protected function indicesProdutos($produtos): array
    {
        $porEan = [];
        $porNome = [];
        foreach ($produtos as $p) {
            $ean = preg_replace('/\D/', '', (string) $p->codBarras);
            if (strlen($ean) >= 8) {
                $porEan[$ean][] = $p;
            }
            $porNome[$p->id] = $this->normalizarNome($p->nome);
        }
        return [$porEan, $porNome, $produtos->keyBy('id')];
    }

    protected function casarProduto(array $it, array $porEan, array $porNome, $produtosPorId): array
    {
        if ($it['ean'] !== '' && isset($porEan[$it['ean']])) {
            $p = $porEan[$it['ean']][0];
            return [$p->id, 'ean', [['id' => $p->id, 'nome' => $p->nome, 'pct' => 100]]];
        }

        $alvo = $this->normalizarNome($it['nome']);
        $scores = [];
        foreach ($porNome as $id => $nome) {
            if ($nome === $alvo) {
                $scores[$id] = 100.0;
                continue;
            }
            // filtro rápido: precisa compartilhar ao menos 2 palavras
            $comum = count(array_intersect(explode(' ', $alvo), explode(' ', $nome)));
            if ($comum < 2) {
                continue;
            }
            similar_text($alvo, $nome, $pct);
            if ($pct >= 55) {
                $scores[$id] = $pct;
            }
        }
        arsort($scores);
        $cand = [];
        foreach (array_slice($scores, 0, 5, true) as $id => $pct) {
            $cand[] = ['id' => $id, 'nome' => $produtosPorId[$id]->nome, 'pct' => (int) round($pct)];
        }
        if (!empty($cand) && $cand[0]['pct'] >= 80) {
            return [$cand[0]['id'], $cand[0]['pct'] >= 100 ? 'nome' : 'parecido', $cand];
        }
        return [null, empty($cand) ? 'nenhum' : 'duvida', $cand];
    }

    protected function normalizarNome(?string $s): string
    {
        $s = (string) $s;
        if (function_exists('iconv')) {
            $c = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if ($c !== false) {
                $s = $c;
            }
        }
        $s = strtoupper(preg_replace('/[^A-Za-z0-9]+/', ' ', $s));
        return trim(preg_replace('/\s+/', ' ', $s));
    }

}
