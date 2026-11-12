<?php

namespace App\Services;

use App\Models\Promocao;
use Illuminate\Support\Facades\DB;

/**
 * Promoções em lote (11/11/2026): monta a lista de produtos de uma campanha e cria
 * uma promoção de preço fixo para cada um, com as mesmas datas.
 *
 * De onde vêm os produtos:
 *  - lista colada (Excel/WhatsApp): uma linha por produto, "ID;preço" ou só "ID";
 *  - categoria: todos os produtos da categoria COM estoque.
 * Preço de quem veio sem preço: base (preço de 1 un., faixa 2 ou faixa 3) com ajuste
 * opcional (– R$ ou – %). Ex.: miniaturas = faixa 3 (12 un.) – R$ 3,00.
 */
class PromocaoLote
{
    public const BASES = [
        'preco_3' => 'Preço da faixa 3 (20 un.; miniaturas 12 un.)',
        'preco_2' => 'Preço da faixa 2 (10 un.; miniaturas 6 un.)',
        'valor_venda' => 'Preço normal (1 un.)',
    ];

    public const AJUSTES = [
        'nenhum' => 'Sem ajuste',
        'reais' => 'Menos R$',
        'percentual' => 'Menos %',
    ];

    public const MAX_LINHAS = 1000;

    /** "89,90" / "1.234,56" / "R$ 89.9" → float; vazio → null */
    public static function numero(?string $v): ?float
    {
        $v = trim(str_ireplace(['R$', ' '], '', (string) $v));
        if ($v === '') {
            return null;
        }
        if (str_contains($v, ',')) {
            $v = str_replace(['.', ','], ['', '.'], $v);
        }
        return is_numeric($v) ? (float) $v : null;
    }

    /**
     * Lê a lista colada. Aceita "ID;preço", "ID<tab>preço" (Excel), "ID preço", "ID|preço" ou só "ID".
     * @return array{0: array<int, ?float>, 1: string[]} [produto_id => preço ou null, linhas inválidas]
     */
    public static function lerLista(string $texto): array
    {
        $itens = [];
        $invalidas = [];
        foreach (preg_split('/\r\n|\r|\n/', $texto) as $n => $linha) {
            $linha = trim($linha);
            if ($linha === '') {
                continue;
            }
            $partes = preg_split('/[;\t|]+|\s+/', $linha, 2);
            $id = (int) preg_replace('/\D/', '', $partes[0] ?? '');
            $preco = isset($partes[1]) ? self::numero($partes[1]) : null;
            if ($id <= 0 || (isset($partes[1]) && trim($partes[1]) !== '' && $preco === null)) {
                $invalidas[] = 'linha ' . ($n + 1) . ': "' . mb_substr($linha, 0, 40) . '"';
                continue;
            }
            $itens[$id] = $preco; // repetido: vale a última linha
            if (count($itens) >= self::MAX_LINHAS) {
                break;
            }
        }
        return [$itens, $invalidas];
    }

    /** Aplica a base e o ajuste ao produto. Retorna [preço, texto da base usada]. */
    public static function precoPorFormula(object $p, string $base, string $ajuste, float $valorAjuste): array
    {
        $ordem = ['preco_3', 'preco_2', 'valor_venda'];
        $inicio = array_search($base, $ordem, true);
        $usada = null;
        $preco = null;
        foreach (array_slice($ordem, $inicio === false ? 0 : $inicio) as $campo) {
            if ((float) $p->{$campo} > 0) {
                $preco = (float) $p->{$campo};
                $usada = $campo;
                break;
            }
        }
        if ($preco === null) {
            return [null, null];
        }
        if ($ajuste === 'reais') {
            $preco -= $valorAjuste;
        } elseif ($ajuste === 'percentual') {
            $preco -= $preco * $valorAjuste / 100;
        }
        $rotulos = ['preco_3' => 'faixa 3', 'preco_2' => 'faixa 2', 'valor_venda' => 'preço normal'];
        return [round($preco, 2), $rotulos[$usada] . ($usada !== $base ? ' (não tem ' . $rotulos[$base] . ')' : '')];
    }

    /**
     * Monta a prévia. $cfg: origem (lista|categoria), lista, categoria_id, base, ajuste, valor_ajuste, se_existir (substituir|pular).
     * @return array{linhas: array, invalidas: string[]}
     */
    public static function previa(int $empresaId, array $cfg): array
    {
        $invalidas = [];
        $precosInformados = [];
        $q = DB::table('produtos as p')
            ->leftJoin('categorias as c', 'c.id', '=', 'p.categoria_id')
            ->leftJoinSub(
                DB::table('estoques')->select('produto_id', DB::raw('SUM(quantidade) as qtd'))->groupBy('produto_id'),
                'e',
                'e.produto_id',
                '=',
                'p.id'
            )
            ->where('p.empresa_id', $empresaId)
            ->select('p.id', 'p.nome', 'p.valor_venda', 'p.preco_2', 'p.preco_3', 'p.valor_compra', 'c.nome as categoria', DB::raw('COALESCE(e.qtd, 0) as estoque'));

        if (($cfg['origem'] ?? 'lista') === 'categoria') {
            $q->where('p.categoria_id', (int) ($cfg['categoria_id'] ?? 0))->where(DB::raw('COALESCE(e.qtd, 0)'), '>', 0);
        } else {
            [$precosInformados, $invalidas] = self::lerLista((string) ($cfg['lista'] ?? ''));
            if (!$precosInformados) {
                return ['linhas' => [], 'invalidas' => $invalidas];
            }
            $q->whereIn('p.id', array_keys($precosInformados));
        }
        $produtos = $q->orderBy('p.nome')->limit(self::MAX_LINHAS)->get()->keyBy('id');

        foreach (array_keys($precosInformados) as $id) {
            if (!$produtos->has($id)) {
                $invalidas[] = "ID {$id}: produto não encontrado";
            }
        }

        $vigentes = Promocao::where('empresa_id', $empresaId)
            ->whereIn('produto_id', $produtos->keys())
            ->where('ativo', 1)
            ->where('data_fim', '>=', now()->toDateString())
            ->get()
            ->groupBy('produto_id');

        $linhas = [];
        foreach ($produtos as $p) {
            $informado = $precosInformados[$p->id] ?? null;
            if ($informado !== null) {
                $preco = round($informado, 2);
                $origemPreco = 'informado';
            } else {
                [$preco, $origemPreco] = self::precoPorFormula($p, (string) ($cfg['base'] ?? 'preco_3'), (string) ($cfg['ajuste'] ?? 'nenhum'), (float) ($cfg['valor_ajuste'] ?? 0));
            }
            $avisos = [];
            $ok = true;
            if ($preco === null || $preco <= 0) {
                $ok = false;
                $avisos[] = 'sem preço para calcular';
            } elseif ($preco >= (float) $p->valor_venda) {
                $ok = false;
                $avisos[] = 'não é menor que o preço normal (' . __moeda($p->valor_venda) . ')';
            }
            if ($ok && (float) $p->valor_compra > 0 && $preco < (float) $p->valor_compra) {
                $avisos[] = 'ABAIXO DO CUSTO';
            }
            if ((float) $p->estoque <= 0) {
                $avisos[] = 'sem estoque (não aparece no site)';
            }
            $existentes = $vigentes->get($p->id);
            if ($existentes && $existentes->count()) {
                if (($cfg['se_existir'] ?? 'substituir') === 'pular') {
                    $ok = false;
                    $avisos[] = 'já tem promoção ativa (mantida)';
                } else {
                    $avisos[] = 'substitui a promoção ativa';
                }
            }
            $linhas[] = [
                'id' => (int) $p->id,
                'nome' => $p->nome,
                'categoria' => $p->categoria,
                'estoque' => (float) $p->estoque,
                'custo' => (float) $p->valor_compra,
                'preco' => (float) $p->valor_venda,
                'preco_2' => (float) $p->preco_2,
                'preco_3' => (float) $p->preco_3,
                'promo' => $preco,
                'origem_preco' => $origemPreco,
                'ok' => $ok,
                'avisos' => $avisos,
            ];
        }
        return ['linhas' => $linhas, 'invalidas' => $invalidas];
    }

    /** Cria as promoções das linhas ok. Retorna [criadas, substituídas]. */
    public static function salvar(int $empresaId, array $linhas, array $cfg): array
    {
        $criadas = 0;
        $substituidas = 0;
        $limite = trim((string) ($cfg['quantidade_limite'] ?? ''));
        DB::transaction(function () use ($empresaId, $linhas, $cfg, $limite, &$criadas, &$substituidas) {
            foreach ($linhas as $l) {
                if (!$l['ok']) {
                    continue;
                }
                if (($cfg['se_existir'] ?? 'substituir') === 'substituir') {
                    $substituidas += Promocao::where('empresa_id', $empresaId)->where('produto_id', $l['id'])
                        ->where('ativo', 1)->where('data_fim', '>=', now()->toDateString())
                        ->update(['ativo' => 0]);
                }
                Promocao::create([
                    'empresa_id' => $empresaId,
                    'produto_id' => $l['id'],
                    'tipo' => 'preco_fixo',
                    'valor' => $l['promo'],
                    'data_inicio' => $cfg['data_inicio'],
                    'data_fim' => $cfg['data_fim'],
                    'quantidade_limite' => $limite !== '' ? max(1, (int) $limite) : null,
                    'quantidade_utilizada' => 0,
                    'ativo' => 1,
                    'observacao' => mb_substr((string) $cfg['campanha'], 0, 255),
                ]);
                $criadas++;
            }
        });
        return [$criadas, $substituidas];
    }

    /** Campanhas (observação) com promoções ativas, para filtrar/encerrar. */
    public static function campanhasAtivas(int $empresaId): array
    {
        return Promocao::where('empresa_id', $empresaId)->where('ativo', 1)
            ->whereNotNull('observacao')->where('observacao', '!=', '')
            ->where('data_fim', '>=', now()->toDateString())
            ->select('observacao', DB::raw('COUNT(*) as total'))
            ->groupBy('observacao')->orderBy('observacao')
            ->pluck('total', 'observacao')->all();
    }
}
