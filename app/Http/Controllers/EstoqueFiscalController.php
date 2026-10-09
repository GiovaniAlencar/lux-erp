<?php

namespace App\Http\Controllers;

use App\Models\EstoqueFiscalMovimento;
use App\Models\Produto;
use App\Services\EstoqueFiscal;
use App\Services\NFeXmlParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Saldo de estoque com nota (fiscal): consulta, ajuste manual, histórico e entrada pelo XML do fornecedor.
 * Não mexe no estoque normal nem no custo.
 */
class EstoqueFiscalController extends Controller
{
    use \App\Support\CasaProdutoXml;

    public function index(Request $request)
    {
        $busca = trim((string) $request->get('busca', ''));
        $estoqueSql = '(SELECT COALESCE(SUM(e.quantidade), 0) FROM estoques e WHERE e.produto_id = produtos.id)';
        $produtos = Produto::where('empresa_id', $request->empresa_id)
            ->where(function ($q) {
                $q->where('fiscal', 1)->orWhere('estoque_fiscal', '!=', 0);
            })
            ->when($busca !== '', function ($q) use ($busca) {
                is_numeric($busca) ? $q->where('id', (int) $busca) : $q->where('nome', 'like', "%{$busca}%");
            })
            ->select('produtos.id', 'produtos.nome', 'produtos.fiscal', 'produtos.estoque_fiscal', 'produtos.custo_fiscal', 'produtos.valor_compra')
            ->selectRaw("$estoqueSql as estoque_total")
            ->orderBy('nome')
            ->paginate(100)
            ->appends($request->all());

        return view('estoque_fiscal.index', compact('produtos', 'busca'));
    }

    public function historico(Request $request, $produtoId)
    {
        $produto = Produto::where('empresa_id', $request->empresa_id)->findOrFail($produtoId);
        $movs = EstoqueFiscalMovimento::with('usuario:id,nome')
            ->where('produto_id', $produto->id)
            ->orderByDesc('id')
            ->paginate(100);
        return view('estoque_fiscal.historico', compact('produto', 'movs'));
    }

    public function ajuste(Request $request)
    {
        $request->validate([
            'produto_id' => 'required|integer',
            'operacao' => 'required|in:somar,subtrair,definir',
            'quantidade' => 'required',
            'observacao' => 'required|string|min:5|max:255',
        ], ['observacao.required' => 'Informe o motivo do ajuste.', 'observacao.min' => 'Motivo muito curto.']);

        $produto = Produto::where('empresa_id', $request->empresa_id)->findOrFail((int) $request->produto_id);
        $q = abs((float) __convert_value_bd((string) $request->quantidade));
        $delta = match ($request->operacao) {
            'somar' => $q,
            'subtrair' => -$q,
            default => $q - (float) $produto->estoque_fiscal,
        };
        if (abs($delta) < 0.0005) {
            return back()->with('flash_erro', 'Nada a ajustar.');
        }
        try {
            DB::transaction(fn () => (new EstoqueFiscal())->ajuste($produto, $delta, $request->observacao));
        } catch (\Throwable $e) {
            return back()->with('flash_erro', $e->getMessage());
        }
        return back()->with('flash_sucesso', 'Saldo fiscal de "' . $produto->nome . '" ajustado.');
    }

    // ---------------- Entrada pelo XML da nota do fornecedor ----------------

    public function entrada()
    {
        return view('estoque_fiscal.entrada');
    }

    public function entradaAnalisar(Request $request)
    {
        $request->validate([
            'xmls' => 'required|array|max:20',
            'xmls.*' => 'file|max:5120',
        ], ['xmls.required' => 'Selecione ao menos um XML.']);

        $produtos = Produto::where('empresa_id', $request->empresa_id)
            ->get(['id', 'nome', 'codBarras', 'fiscal', 'estoque_fiscal', 'custo_fiscal', 'valor_compra', 'valor_venda', 'preco_2', 'preco_3']);
        [$porEan, $porNome, $produtosPorId] = $this->indicesProdutos($produtos);
        $estoques = \App\Models\Estoque::whereIn('produto_id', $produtos->pluck('id'))->whereNull('filial_id')
            ->pluck('quantidade', 'produto_id');

        $parser = new NFeXmlParser();
        $notas = [];
        $falhas = [];
        foreach ($request->file('xmls') as $arq) {
            try {
                $nf = $parser->parse(file_get_contents($arq->getRealPath()));
            } catch (\Throwable $e) {
                $falhas[] = $arq->getClientOriginalName() . ': ' . $e->getMessage();
                continue;
            }
            $jaLancada = $nf['chave'] !== '' && EstoqueFiscalMovimento::where('empresa_id', $request->empresa_id)
                ->where('tipo', 'entrada_xml')->where('chave', $nf['chave'])->exists();
            $fornecedor = $this->fornecedorPorCnpj($request->empresa_id, $nf['emit_cnpj']);
            $itens = [];
            foreach ($nf['itens'] as $it) {
                [$pid, $match, $cand] = $this->casarProduto($it, $porEan, $porNome, $produtosPorId);
                $itens[] = [
                    'xml' => $it,
                    'produto_id' => $pid,
                    'produto' => $pid ? $produtosPorId[$pid] : null,
                    'estoque' => $pid ? (float) ($estoques[$pid] ?? 0) : 0,
                    'match' => $match,
                    'candidatos' => $cand,
                ];
            }
            $notas[] = ['nf' => $nf, 'ja_lancada' => $jaLancada, 'itens' => $itens, 'fornecedor' => $fornecedor,
                'arquivo' => $arq->getClientOriginalName()];
        }
        if (empty($notas)) {
            return redirect()->route('estoque-fiscal.entrada')->with('flash_erro', 'Nenhuma nota lida. ' . implode(' | ', $falhas));
        }
        return view('estoque_fiscal.entrada_preview', compact('notas', 'falhas'));
    }

    /** Dados do produto para a prévia (quando o ID é trocado na tela). */
    public function produtoInfo(Request $request, $id)
    {
        $p = Produto::where('empresa_id', $request->empresa_id)->find((int) $id);
        if (!$p) {
            return response()->json(['ok' => false], 404);
        }
        $est = (float) \App\Models\Estoque::where('produto_id', $p->id)->whereNull('filial_id')->value('quantidade');
        return response()->json([
            'ok' => true, 'id' => $p->id, 'nome' => $p->nome,
            'estoque' => $est, 'custo' => (float) $p->valor_compra,
            'preco' => (float) $p->valor_venda, 'preco_2' => $p->preco_2 !== null ? (float) $p->preco_2 : null,
            'preco_3' => $p->preco_3 !== null ? (float) $p->preco_3 : null,
        ]);
    }

    public function entradaConfirmar(Request $request)
    {
        $notas = (array) $request->input('notas', []);
        $empresaId = $request->empresa_id;
        $ok = 0;
        $avisos = [];
        DB::transaction(function () use ($notas, $empresaId, &$ok, &$avisos) {
            $svc = new EstoqueFiscal();
            $stockMove = new \App\Helpers\StockMove();
            foreach ($notas as $n) {
                $chave = preg_replace('/\D/', '', (string) ($n['chave'] ?? ''));
                $numero = (string) ($n['numero'] ?? '');
                $doc = mb_substr('NF ' . $numero . ' · ' . ($n['emitente'] ?? ''), 0, 60);
                if ($chave !== '' && EstoqueFiscalMovimento::where('empresa_id', $empresaId)
                    ->where('tipo', 'entrada_xml')->where('chave', $chave)->lockForUpdate()->exists()) {
                    $avisos[] = 'NF ' . $numero . ' já tinha sido lançada — ignorada.';
                    continue;
                }
                $comEstoqueNormal = !empty($n['estoque_normal']);

                // itens válidos desta nota
                $linhas = [];
                foreach ((array) ($n['itens'] ?? []) as $r) {
                    if (empty($r['aplicar'])) {
                        continue;
                    }
                    $p = Produto::where('empresa_id', $empresaId)->lockForUpdate()->find((int) ($r['produto_id'] ?? 0));
                    $q = (float) __convert_value_bd((string) ($r['quantidade'] ?? '0'));
                    $custoTotal = (float) __convert_value_bd((string) ($r['custo_total'] ?? '0'));
                    if (!$p || $q <= 0) {
                        $avisos[] = 'Item "' . ($r['xml_nome'] ?? '') . '" sem produto ou quantidade — ignorado.';
                        continue;
                    }
                    $linhas[] = [$p, $q, $custoTotal > 0 ? $custoTotal / $q : null, $r];
                }
                if (!$linhas) {
                    continue;
                }

                // compra (histórico) quando também entra no estoque normal e o fornecedor está cadastrado
                $compra = null;
                if ($comEstoqueNormal) {
                    $fornecedorId = (int) ($n['fornecedor_id'] ?? 0);
                    if ($fornecedorId > 0) {
                        $total = 0;
                        foreach ($linhas as [$p, $q, $cu]) {
                            $total += $q * (float) $cu;
                        }
                        $compra = \App\Models\Compra::create([
                            'empresa_id' => (int) $empresaId,
                            'fornecedor_id' => $fornecedorId,
                            'usuario_id' => get_id_user(),
                            'numero_nfe' => (int) $numero,
                            'chave' => $chave,
                            'observacao' => mb_substr('Entrada pelo XML (estoque fiscal) | ' . $doc, 0, 255),
                            'estado' => 'aprovado',
                            'total' => round($total, 2),
                            'desconto' => 0, 'valor_frete' => 0, 'qtd_volumes' => 0, 'peso_liquido' => 0, 'peso_bruto' => 0,
                            'tipo' => 0, 'natureza_id' => 0, 'tipo_pagamento' => '', 'filial_id' => null,
                        ]);
                    } else {
                        $avisos[] = 'NF ' . $numero . ': fornecedor não cadastrado — estoque e custo lançados, mas sem registro de compra.';
                    }
                }

                foreach ($linhas as [$p, $q, $cu, $r]) {
                    $svc->entrada($p, $q, [
                        'chave' => $chave ?: null,
                        'documento' => $doc,
                        'observacao' => mb_substr('Item XML: ' . ($r['xml_nome'] ?? ''), 0, 255),
                    ], $cu);

                    $p->refresh();
                    // custo médio REAL informado/confirmado na conferência (vazio = cálculo automático)
                    $custoManual = trim((string) ($r['novo_custo_real'] ?? ''));
                    $custoManualV = $custoManual !== '' ? round((float) __convert_value_bd($custoManual), 2) : null;
                    if ($comEstoqueNormal) {
                        // automático: mistura com o estoque que já existe (com e sem nota)
                        $estoqueAntes = max(0, (float) \App\Models\Estoque::where('produto_id', $p->id)->whereNull('filial_id')->value('quantidade'));
                        if ($custoManualV !== null && $custoManualV > 0) {
                            $p->valor_compra = $custoManualV;
                        } elseif ($cu !== null) {
                            $custoAntes = (float) $p->valor_compra;
                            $p->valor_compra = ($estoqueAntes > 0 && $custoAntes > 0)
                                ? round(($estoqueAntes * $custoAntes + $q * $cu) / ($estoqueAntes + $q), 2)
                                : round($cu, 2);
                        }
                        $stockMove->pluStock($p->id, $q, (float) $p->valor_compra, -1);
                        if ($compra) {
                            \App\Models\ItemCompra::create([
                                'compra_id' => $compra->id,
                                'produto_id' => $p->id,
                                'quantidade' => $q,
                                'valor_unitario' => round((float) $cu, 4),
                                'unidade_compra' => $p->unidade_compra,
                            ]);
                        }
                    }

                    if (!$comEstoqueNormal && $custoManualV !== null && $custoManualV > 0) {
                        $p->valor_compra = $custoManualV; // alterado à mão mesmo sem entrada no estoque normal
                    }

                    // preço de venda: só muda se foi preenchido na conferência
                    foreach (['novo_preco' => 'valor_venda', 'novo_preco_2' => 'preco_2', 'novo_preco_3' => 'preco_3'] as $campo => $coluna) {
                        $v = trim((string) ($r[$campo] ?? ''));
                        if ($v !== '') {
                            $p->{$coluna} = round((float) __convert_value_bd($v), 2);
                        }
                    }
                    // mantém o % de lucro coerente com o custo e o preço atuais (sem mexer no preço)
                    if ((float) $p->valor_compra > 0) {
                        $p->percentual_lucro = __percentual_lucro((float) $p->valor_compra, (float) $p->valor_venda);
                    }
                    $p->save();
                    $ok++;
                }
            }
        });

        $r = redirect()->route('estoque-fiscal.index')->with('flash_sucesso', $ok . ' item(ns) lançado(s).');
        if ($avisos) {
            $r->with('flash_erro', implode(' | ', $avisos));
        }
        return $r;
    }

    private function fornecedorPorCnpj($empresaId, string $cnpj)
    {
        if ($cnpj === '') {
            return null;
        }
        return \App\Models\Fornecedor::where('empresa_id', $empresaId)
            ->whereRaw("REPLACE(REPLACE(REPLACE(cpf_cnpj, '.', ''), '/', ''), '-', '') = ?", [$cnpj])
            ->first();
    }
}
