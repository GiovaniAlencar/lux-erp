<?php

namespace App\Http\Controllers;

use App\Helpers\StockMove;
use App\Models\Compra;
use App\Models\Estoque;
use App\Models\Fornecedor;
use App\Models\ImportacaoMassaProduto;
use App\Models\ImportacaoMassaProdutoItem;
use App\Models\ItemCompra;
use App\Models\Produto;
use App\Services\ImportacaoMassaProdutoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportacaoMassaProdutoController extends Controller
{
    public function __construct(
        private ImportacaoMassaProdutoService $service
    ) {
    }

    public function index(Request $request)
    {
        $historico = ImportacaoMassaProduto::where('empresa_id', $request->empresa_id)
            ->whereNotNull('confirmado_em')
            ->with(['usuario:id,nome', 'fornecedor:id,razao_social', 'compra:id'])
            ->when($request->filled('fornecedor_id'), function ($q) use ($request) {
                $q->where('fornecedor_id', (int) $request->fornecedor_id);
            })
            ->when($request->filled('start_date'), function ($q) use ($request) {
                $q->whereDate('confirmado_em', '>=', $request->start_date);
            })
            ->when($request->filled('end_date'), function ($q) use ($request) {
                $q->whereDate('confirmado_em', '<=', $request->end_date);
            })
            ->orderByDesc('confirmado_em')
            ->paginate(50)
            ->withQueryString();

        $fornecedores = Fornecedor::where('empresa_id', $request->empresa_id)
            ->orderBy('razao_social')
            ->get(['id', 'razao_social', 'cpf_cnpj']);

        return view('estoque.importacao_massa.index', compact('historico', 'fornecedores'));
    }

    public function downloadModelo()
    {
        $csv = implode(',', ImportacaoMassaProdutoService::CABECALHO) . "\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="modelo_importacao_massa_produtos.csv"',
        ]);
    }

    public function processar(Request $request)
    {
        $request->validate([
            'arquivo' => 'required|file|max:10240',
        ], [
            'arquivo.required' => 'Selecione um arquivo CSV.',
        ]);

        $ext = strtolower($request->file('arquivo')->getClientOriginalExtension());
        if (!in_array($ext, ['csv', 'txt'], true)) {
            return response()->json([
                'ok' => false,
                'message' => 'O arquivo deve ser CSV (.csv).',
            ], 422);
        }

        try {
            $path = $request->file('arquivo')->getRealPath();
            $rawRows = $this->service->parseCsvFile($path);

            if (count($rawRows) === 0) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Nenhum registro encontrado no arquivo.',
                ], 422);
            }

            $preview = $this->service->montarPreview($rawRows, (int) $request->empresa_id);
            $token = Str::uuid()->toString();

            session([
                'importacao_massa_preview' => [
                    'token' => $token,
                    'empresa_id' => (int) $request->empresa_id,
                    'arquivo_nome' => $request->file('arquivo')->getClientOriginalName(),
                    'linhas' => $preview['linhas'],
                    'resumo' => $preview['resumo'],
                ],
            ]);

            return response()->json([
                'ok' => true,
                'token' => $token,
                'linhas' => $preview['linhas'],
                'resumo' => $preview['resumo'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function confirmar(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'fornecedor_id' => 'required|integer',
        ], [
            'fornecedor_id.required' => 'Selecione o fornecedor.',
        ]);

        if (!Fornecedor::where('empresa_id', $request->empresa_id)
            ->where('id', $request->fornecedor_id)
            ->exists()) {
            session()->flash('flash_erro', 'Fornecedor inválido.');
            return redirect()->route('estoque.importacaoMassa.index');
        }

        $preview = session('importacao_massa_preview');
        if (!$preview || ($preview['token'] ?? '') !== $request->token) {
            session()->flash('flash_erro', 'Pré-visualização expirada. Processe o arquivo novamente.');
            return redirect()->route('estoque.importacaoMassa.index');
        }

        if ((int) ($preview['empresa_id'] ?? 0) !== (int) $request->empresa_id) {
            abort(403);
        }

        if (!empty($preview['resumo']['tem_erros'])) {
            session()->flash('flash_erro', 'Corrija os erros antes de confirmar a importação.');
            return redirect()->route('estoque.importacaoMassa.index');
        }

        $linhasValidas = array_values(array_filter(
            $preview['linhas'],
            fn ($l) => !empty($l['valido'])
        ));

        if (count($linhasValidas) === 0) {
            session()->flash('flash_erro', 'Nenhum produto válido para importar.');
            return redirect()->route('estoque.importacaoMassa.index');
        }

        try {
            $importacaoId = DB::transaction(function () use ($preview, $linhasValidas, $request) {
                $resumo = $preview['resumo'];
                $linhasComEntrada = array_values(array_filter(
                    $linhasValidas,
                    fn ($l) => (float) ($l['quantidade'] ?? 0) > 0
                ));

                $compra = null;
                if (count($linhasComEntrada) > 0) {
                    $compra = Compra::create([
                        'empresa_id' => (int) $request->empresa_id,
                        'fornecedor_id' => (int) $request->fornecedor_id,
                        'usuario_id' => get_id_user(),
                        'observacao' => mb_substr(
                            'Importação em massa | ' . ($preview['arquivo_nome'] ?? 'CSV'),
                            0,
                            255
                        ),
                        'estado' => 'aprovado',
                        'total' => (float) $resumo['valor_total_compra'],
                        'desconto' => 0,
                        'valor_frete' => 0,
                        'qtd_volumes' => 0,
                        'peso_liquido' => 0,
                        'peso_bruto' => 0,
                        'tipo' => 0,
                        'natureza_id' => 0,
                        'tipo_pagamento' => '',
                        'filial_id' => null,
                    ]);
                }

                $importacao = ImportacaoMassaProduto::create([
                    'empresa_id' => (int) $request->empresa_id,
                    'usuario_id' => get_id_user(),
                    'fornecedor_id' => (int) $request->fornecedor_id,
                    'compra_id' => $compra?->id,
                    'arquivo_nome' => $preview['arquivo_nome'] ?? null,
                    'qtd_itens' => (int) $resumo['itens_processados'],
                    'qtd_itens_validos' => (int) $resumo['produtos_validos'],
                    'qtd_itens_erro' => (int) $resumo['produtos_erro'],
                    'qtd_unidades' => (float) $resumo['total_unidades'],
                    'valor_total_compra' => (float) $resumo['valor_total_compra'],
                    'confirmado_em' => now(),
                ]);

                $stockMove = new StockMove();

                foreach ($linhasValidas as $linha) {
                    $produto = Produto::where('empresa_id', $request->empresa_id)
                        ->where('id', $linha['produto_id'])
                        ->lockForUpdate()
                        ->firstOrFail();

                    $estoqueAnterior = (float) ($linha['estoque_atual'] ?? 0);
                    $quantidade = (float) $linha['quantidade'];

                    $produto->valor_compra = (float) $linha['custo_novo'];
                    $produto->valor_venda = (float) $linha['preco_1_novo'];
                    // vazio no CSV = preço único (sem atacado 1/2)
                    $produto->preco_2 = $linha['preco_2_novo'] !== null && $linha['preco_2_novo'] !== '' ? (float) $linha['preco_2_novo'] : null;
                    $produto->preco_3 = $linha['preco_3_novo'] !== null && $linha['preco_3_novo'] !== '' ? (float) $linha['preco_3_novo'] : null;
                    $produto->save();

                    if ($quantidade > 0) {
                        $stockMove->pluStock(
                            $produto->id,
                            $quantidade,
                            (float) $linha['custo_novo'],
                            -1
                        );

                        if ($compra) {
                            ItemCompra::create([
                                'compra_id' => $compra->id,
                                'produto_id' => $produto->id,
                                'quantidade' => $quantidade,
                                'valor_unitario' => (float) $linha['custo_novo'],
                                'unidade_compra' => $produto->unidade_compra,
                            ]);
                        }
                    }

                    $estoqueDepois = Estoque::where('produto_id', $produto->id)
                        ->whereNull('filial_id')
                        ->first();
                    $estoqueNovo = $estoqueDepois ? (float) $estoqueDepois->quantidade : $estoqueAnterior + $quantidade;

                    ImportacaoMassaProdutoItem::create([
                        'importacao_id' => $importacao->id,
                        'produto_id' => $produto->id,
                        'codigo_informado' => (int) $linha['codigo'],
                        'produto_nome' => $produto->nome,
                        'estoque_anterior' => $estoqueAnterior,
                        'quantidade' => $quantidade,
                        'estoque_final' => (float) ($linha['estoque_final'] ?? $estoqueNovo),
                        'custo_anterior' => $linha['custo_atual'],
                        'custo_novo' => $linha['custo_novo'],
                        'preco_1_anterior' => $linha['preco_1_atual'],
                        'preco_1_novo' => $linha['preco_1_novo'],
                        'preco_2_anterior' => $linha['preco_2_atual'],
                        'preco_2_novo' => $linha['preco_2_novo'],
                        'preco_3_anterior' => $linha['preco_3_atual'],
                        'preco_3_novo' => $linha['preco_3_novo'],
                        'valor_linha' => (float) $linha['valor_linha'],
                        'valido' => true,
                    ]);
                }

                return $importacao->id;
            });

            session()->forget('importacao_massa_preview');
            session()->flash(
                'flash_sucesso',
                count($linhasValidas) . ' produto(s) atualizado(s) com sucesso.'
            );

            return redirect()->route('estoque.importacaoMassa.show', $importacaoId);
        } catch (\Throwable $e) {
            session()->flash('flash_erro', 'Erro ao confirmar importação: ' . $e->getMessage());
            __saveLogError($e, $request->empresa_id);

            return redirect()->route('estoque.importacaoMassa.index');
        }
    }

    public function show(Request $request, $id)
    {
        $importacao = ImportacaoMassaProduto::where('empresa_id', $request->empresa_id)
            ->whereNotNull('confirmado_em')
            ->with(['itens', 'usuario:id,nome', 'fornecedor:id,razao_social', 'compra:id'])
            ->findOrFail($id);

        return view('estoque.importacao_massa.show', compact('importacao'));
    }
}
