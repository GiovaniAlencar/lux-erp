<?php

namespace App\Http\Controllers;

use App\Models\ConfigNota;
use App\Models\Produto;
use App\Services\NFeXmlParser;
use App\Support\ProdutoFiscal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Preenche os dados fiscais dos produtos a partir de XMLs de NF-e.
 * - XML emitido pela própria LUX (outro sistema): usa NCM, CEST, origem, CSOSN, CFOP, PIS/COFINS da nota.
 * - XML de fornecedor: usa NCM, CEST, EAN e origem (1→2, 6→7); CSOSN/CFOP são sugeridos (revenda).
 */
class ProdutoFiscalXmlController extends Controller
{
    use \App\Support\CasaProdutoXml;

    private const MAX_ARQUIVOS = 30;

    public function index()
    {
        return view('produtos.fiscal_xml.index');
    }

    public function analisar(Request $request)
    {
        $request->validate([
            'xmls' => 'required|array|max:' . self::MAX_ARQUIVOS,
            'xmls.*' => 'file|max:5120',
        ], [
            'xmls.required' => 'Selecione ao menos um XML.',
            'xmls.max' => 'Envie no máximo ' . self::MAX_ARQUIVOS . ' arquivos por vez.',
        ]);

        $empresaId = $request->empresa_id;
        $config = ConfigNota::where('empresa_id', $empresaId)->first();
        $cnpjLux = $config ? preg_replace('/\D/', '', (string) $config->cnpj) : '';

        $produtos = Produto::where('empresa_id', $empresaId)
            ->get(['id', 'nome', 'codBarras', 'fiscal', 'NCM', 'CEST', 'origem', 'CST_CSOSN',
                'CFOP_saida_estadual', 'CFOP_saida_inter_estadual', 'CST_PIS', 'CST_COFINS', 'unidade_venda']);
        $porEan = [];
        $porNome = [];
        foreach ($produtos as $p) {
            $ean = preg_replace('/\D/', '', (string) $p->codBarras);
            if (strlen($ean) >= 8) {
                $porEan[$ean][] = $p;
            }
            $porNome[$p->id] = $this->normalizarNome($p->nome);
        }
        $produtosPorId = $produtos->keyBy('id');

        $parser = new NFeXmlParser();
        $notas = [];
        $linhas = [];
        $falhas = [];
        $vistos = [];

        foreach ($request->file('xmls') as $arq) {
            try {
                $nota = $parser->parse(file_get_contents($arq->getRealPath()));
            } catch (\Throwable $e) {
                $falhas[] = $arq->getClientOriginalName() . ': ' . $e->getMessage();
                continue;
            }
            $propria = $cnpjLux !== '' && $nota['emit_cnpj'] === $cnpjLux;
            $notas[] = [
                'arquivo' => $arq->getClientOriginalName(),
                'numero' => $nota['numero'],
                'serie' => $nota['serie'],
                'data' => $nota['data'],
                'emitente' => $nota['emit_nome'],
                'propria' => $propria,
                'itens' => count($nota['itens']),
            ];

            foreach ($nota['itens'] as $it) {
                // mesmo item em vários XMLs: mostra uma vez só
                $chaveItem = ($it['ean'] ?: $this->normalizarNome($it['nome'])) . '|' . ($propria ? 'P' : 'F');
                if (isset($vistos[$chaveItem])) {
                    continue;
                }
                $vistos[$chaveItem] = true;

                [$produtoId, $match, $candidatos] = $this->casarProduto($it, $porEan, $porNome, $produtosPorId);
                $atual = $produtoId ? $produtosPorId[$produtoId] : null;

                $linhas[] = [
                    'xml' => $it,
                    'nota' => $nota['numero'] . ' · ' . $nota['emit_nome'],
                    'propria' => $propria,
                    'produto_id' => $produtoId,
                    'match' => $match,
                    'candidatos' => $candidatos,
                    'atual' => $atual,
                    'proposta' => $this->proposta($it, $propria, $atual),
                ];
            }
        }

        if (empty($linhas)) {
            return redirect()->route('produtos-fiscal-xml.index')
                ->with('flash_erro', 'Nenhum item lido. ' . implode(' | ', $falhas));
        }

        return view('produtos.fiscal_xml.preview', compact('notas', 'linhas', 'falhas', 'cnpjLux'));
    }

    public function aplicar(Request $request)
    {
        $rows = (array) $request->input('rows', []);
        $empresaId = $request->empresa_id;
        $ok = 0;
        $erros = [];

        DB::transaction(function () use ($rows, $empresaId, &$ok, &$erros) {
            foreach ($rows as $i => $r) {
                if (empty($r['aplicar'])) {
                    continue;
                }
                $pid = (int) ($r['produto_id'] ?? 0);
                $produto = $pid ? Produto::where('empresa_id', $empresaId)->find($pid) : null;
                $rotulo = 'Linha ' . ((int) $i + 1) . ' (' . ($r['xml_nome'] ?? '') . ')';
                if (!$produto) {
                    $erros[] = $rotulo . ': produto não encontrado (ID ' . $pid . ')';
                    continue;
                }

                $dados = ProdutoFiscal::normalizar([
                    'NCM' => $r['NCM'] ?? '',
                    'CEST' => $r['CEST'] ?? '',
                    'origem' => $r['origem'] ?? '',
                    'CST_CSOSN' => $r['CST_CSOSN'] ?? '',
                    'CFOP_saida_estadual' => $r['CFOP_saida_estadual'] ?? '',
                    'CFOP_saida_inter_estadual' => $r['CFOP_saida_inter_estadual'] ?? '',
                    'CST_PIS' => $r['CST_PIS'] ?? '',
                    'CST_COFINS' => $r['CST_COFINS'] ?? '',
                ]);
                $dados['unidade_venda'] = $produto->unidade_venda ?: 'UN';

                $marcarFiscal = !empty($r['marcar_fiscal']);
                if ($marcarFiscal || $produto->fiscal) {
                    $falhas = ProdutoFiscal::erros($dados);
                    if (!empty($falhas)) {
                        $erros[] = $rotulo . ' → ' . $produto->nome . ': ' . implode('; ', $falhas);
                        continue;
                    }
                }

                unset($dados['unidade_venda']);
                $produto->fill($dados);
                $ean = preg_replace('/\D/', '', (string) ($r['ean'] ?? ''));
                $eanAtual = preg_replace('/\D/', '', (string) $produto->codBarras);
                if (!empty($r['gravar_ean']) && strlen($ean) >= 8 && strlen($eanAtual) < 8) {
                    $produto->codBarras = $ean;
                }
                if ($marcarFiscal) {
                    $produto->fiscal = 1;
                }
                $produto->save();
                $ok++;
            }
        });

        $msg = $ok . ' produto(s) atualizado(s).';
        if (!empty($erros)) {
            session()->flash('flash_erro', 'Não aplicados: ' . implode(' || ', array_slice($erros, 0, 15))
                . (count($erros) > 15 ? ' (+' . (count($erros) - 15) . ')' : ''));
        }
        session()->flash('flash_sucesso', $msg);

        return redirect()->route('produtos-fiscal-xml.index');
    }

    /** Busca produto por ID (para trocar o produto da linha na prévia). */
    public function produto(Request $request, $id)
    {
        $p = Produto::where('empresa_id', $request->empresa_id)->find((int) $id);
        if (!$p) {
            return response()->json(['ok' => false], 404);
        }
        return response()->json(['ok' => true, 'id' => $p->id, 'nome' => $p->nome, 'fiscal' => (bool) $p->fiscal]);
    }

    // ---------------------------------------------------------------------

    /** Valores sugeridos para cada campo fiscal. */
    private function proposta(array $it, bool $propria, $atual): array
    {
        $temSt = in_array($it['csosn'], ['201', '202', '203', '500'], true)
            || in_array($it['cst'], ['10', '30', '60', '70'], true)
            || in_array(substr($it['cfop'], 1), ['401', '403', '405', '404'], true);

        if ($propria) {
            $csosn = $it['csosn'] ?: ($temSt ? '500' : '102');
            [$cfopE, $cfopI] = $this->parCfop($it['cfop'] ?: ($temSt ? '5405' : '5102'));
            $origem = $it['orig'] !== '' ? $it['orig'] : '0';
            $pis = $it['cst_pis'] ?: '49';
            $cofins = $it['cst_cofins'] ?: '49';
        } else {
            // revenda de mercadoria comprada de fornecedor
            $csosnAtual = $atual ? (string) $atual->CST_CSOSN : '';
            $csosn = in_array($csosnAtual, ProdutoFiscal::CSOSN_SIMPLES, true) ? $csosnAtual : ($temSt ? '500' : '102');
            $comSt = in_array($csosn, ProdutoFiscal::CSOSN_COM_ST, true);
            $cfopE = $atual && preg_match('/^5\d{3}$/', (string) $atual->CFOP_saida_estadual) && $atual->CFOP_saida_estadual !== '5101'
                ? $atual->CFOP_saida_estadual : ($comSt ? '5405' : '5102');
            $cfopI = $atual && preg_match('/^6\d{3}$/', (string) $atual->CFOP_saida_inter_estadual) && $atual->CFOP_saida_inter_estadual !== '6101'
                ? $atual->CFOP_saida_inter_estadual : ($comSt ? '6404' : '6102');
            $mapa = ['1' => '2', '6' => '7'];
            $origem = $it['orig'] !== '' ? ($mapa[$it['orig']] ?? $it['orig']) : '0';
            $pis = $atual && preg_match('/^\d{2}$/', (string) $atual->CST_PIS) ? $atual->CST_PIS : '49';
            $cofins = $atual && preg_match('/^\d{2}$/', (string) $atual->CST_COFINS) ? $atual->CST_COFINS : '49';
        }

        return [
            'NCM' => $it['ncm'],
            'CEST' => $it['cest'],
            'origem' => (string) $origem,
            'CST_CSOSN' => $csosn,
            'CFOP_saida_estadual' => $cfopE,
            'CFOP_saida_inter_estadual' => $cfopI,
            'CST_PIS' => $pis,
            'CST_COFINS' => $cofins,
        ];
    }

    /** A partir de um CFOP de saída, devolve [dentro do estado, fora do estado]. */
    private function parCfop(string $cfop): array
    {
        $cfop = preg_replace('/\D/', '', $cfop);
        $especiais = ['5405' => '6404', '5403' => '6403', '5401' => '6401'];
        if (str_starts_with($cfop, '5')) {
            return [$cfop, (string) ($especiais[$cfop] ?? ('6' . substr($cfop, 1)))];
        }
        if (str_starts_with($cfop, '6')) {
            $inv = array_flip($especiais);
            return [(string) ($inv[$cfop] ?? ('5' . substr($cfop, 1))), $cfop];
        }
        return ['5102', '6102'];
    }
}
