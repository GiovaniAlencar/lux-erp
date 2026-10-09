<?php

namespace App\Http\Controllers;

use App\Helpers\StockMove;
use App\Helpers\PrecoCategoriaVenda;
use App\Models\AlteracaoEstoque;
use App\Models\Estoque;
use App\Models\Acessor;
use App\Models\Categoria;
use App\Models\CategoriaConta;
use App\Models\CategoriaProdutoEcommerce;
use App\Models\Certificado;
use App\Models\Cidade;
use App\Models\Contigencia;
use App\Models\Cliente;
use App\Models\ConfigNota;
use App\Models\ContaReceber;
use App\Models\DivisaoGrade;
use App\Models\Empresa;
use App\Models\FormaPagamento;
use App\Models\Funcionario;
use App\Models\GrupoCliente;
use App\Models\ItemOrcamento;
use App\Models\ItemVenda;
use App\Models\ListaPreco;
use App\Models\Marca;
use App\Models\Frete;
use App\Models\NaturezaOperacao;
use App\Models\Orcamento;
use App\Models\Venda;
use App\Models\VendaAuditoria;
use App\Models\RotaEntregaItem;
use App\Models\Usuario;
use App\Models\Pais;
use App\Models\Produto;
use App\Models\TelaPedido;
use App\Models\Transportadora;
use App\Models\Tributacao;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\NFService;
use Faker\Core\File as CoreFile;
use NFePHP\DA\NFe\Danfe;
use NFePHP\DA\NFe\DanfeSimples;
use File;
use Illuminate\Http\File as HttpFile;
use Dompdf\Dompdf;

class VendaController extends Controller
{
    public function index(Request $request)
    {
        if (!is_dir(public_path('xml_nfe'))) {
            mkdir(public_path('xml_nfe'), 0777, true);
        }
        if (!is_dir(public_path('xml_nfe_cancelada'))) {
            mkdir(public_path('xml_nfe_cancelada'), 0777, true);
        }
        if (!is_dir(public_path('xml_nfe_correcao'))) {
            mkdir(public_path('xml_nfe_correcao'), 0777, true);
        }
        $start_date = $request->get('start_date');
        $end_date = $request->get('end_date');
        $cliente_id = $request->get('cliente_id');
        $type_search = $request->get('type_search');
        $estado_emissao = $request->get('estado_emissao');
        $pesquisa_data = $request->get('pesquisa_data');
        $data_emissao = $request->get('data_emissao');
        $filial_id = $request->get('filial_id');
        $filter_usuario_id = $request->get('filter_usuario_id');
        $filter_status_pedido_raw = $request->input('filter_status_pedido', []);
        if (!is_array($filter_status_pedido_raw)) {
            $filter_status_pedido_raw = ($filter_status_pedido_raw !== null && $filter_status_pedido_raw !== '')
                ? [$filter_status_pedido_raw]
                : [];
        }
        $filter_status_pedido_selecionados = array_values(array_unique(array_filter(
            $filter_status_pedido_raw,
            static fn ($v) => $v !== null && $v !== ''
        )));
        $filter_status_pagamento = $request->get('filter_status_pagamento');
        $usuarioAdm = (bool) optional(Usuario::find(get_id_user()))->adm;
        $filter_somente_abertos = $usuarioAdm && $request->boolean('filter_somente_abertos');
        $local_padrao = __get_local_padrao();
        if (!$filial_id && $local_padrao) {
            $filial_id = $local_padrao;
        }
        // $data = Venda::where('empresa_id', $request->empresa_id)
        //     ->when(!empty($start_date), function ($query) use ($start_date, $pesquisa_data) {
        //         return $query->whereDate($pesquisa_data, '>=', $start_date);
        //     })
        //     ->when(!empty($end_date), function ($query) use ($end_date, $pesquisa_data) {
        //         return $query->whereDate($pesquisa_data, '<=', $end_date);
        //     })
        //     ->when(!empty($cliente_id), function ($query) use ($cliente_id) {
        //         return $query->where('cliente_id', $cliente_id);
        //     })
        //     ->when($estado_emissao != "", function ($query) use ($estado_emissao) {
        //         return $query->where('estado_emissao', $estado_emissao);
        //     })
        //     ->when(!empty($data_emissao), function ($query) use ($data_emissao) {
        //         return $query->whereDate('created_at', '<=', $data_emissao);
        //     })
        //     ->when($filial_id != 'todos', function ($query) use ($filial_id) {
        //         $filial_id = $filial_id == -1 ? null : $filial_id;
        //         return $query->where('filial_id', $filial_id);
        //     })
        //     ->orderBy('created_at', 'desc')
        //     ->paginate(env("PAGINACAO"));

        $queryVendas = Venda::where('empresa_id', $request->empresa_id)
            ->when(!empty($start_date) && !empty($pesquisa_data), function ($query) use ($start_date, $pesquisa_data) {
                return $query->whereDate($pesquisa_data, '>=', $start_date);
            })
            ->when(!empty($end_date) && !empty($pesquisa_data), function ($query) use ($end_date, $pesquisa_data) {
                return $query->whereDate($pesquisa_data, '<=', $end_date);
            })
            ->when(!empty($cliente_id), function ($query) use ($cliente_id) {
                return $query->where('cliente_id', $cliente_id);
            })
            ->when(!empty($filter_usuario_id), function ($query) use ($filter_usuario_id) {
                return $query->where('usuario_id', $filter_usuario_id);
            })
            ->when(count($filter_status_pedido_selecionados) > 0, function ($query) use ($filter_status_pedido_selecionados) {
                return $query->whereIn('status_pedido', $filter_status_pedido_selecionados);
            })
            ->when(!empty($filter_status_pagamento), function ($query) use ($filter_status_pagamento) {
                return $query->where('status_pagamento', $filter_status_pagamento);
            })
            ->when($filter_somente_abertos, function ($query) {
                return $query->where('fechada_caixa', false);
            })
            ->when($request->get('nf_externa') === 'pendente', function ($query) {
                return $query->whereHas('itens', function ($q) {
                    $q->where('fiscal', 1);
                })->where(function ($q) {
                    $q->whereNull('nf_externa_status')->orWhere('nf_externa_status', '!=', 'emitida');
                })->where('estado_emissao', '!=', 'cancelado')
                  ->where(function ($q) {
                      $q->whereNull('status_pedido')->orWhere('status_pedido', '!=', 'cancelada');
                  });
            })
            ->when($request->get('nf_externa') === 'emitida', function ($query) {
                return $query->where('nf_externa_status', 'emitida');
            })
            ->when($estado_emissao != "", function ($query) use ($estado_emissao) {
                return $query->where('estado_emissao', $estado_emissao);
            })
            ->when(!empty($data_emissao), function ($query) use ($data_emissao) {
                return $query->whereDate('created_at', '<=', $data_emissao);
            })
            ->when($filial_id != 'todos', function ($query) use ($filial_id) {
                $filial_id = $filial_id == -1 ? null : $filial_id;
                return $query->where('filial_id', $filial_id);
            });

        $data = (clone $queryVendas)
            ->with(['itens', 'cliente:id,razao_social,cpf_cnpj', 'usuario:id,nome'])
            ->orderBy('created_at', 'desc')
            ->paginate(env("PAGINACAO"));



        $config = ConfigNota::where('empresa_id', request()->empresa_id)
            ->first();
        $contigencia = $this->getContigencia(request()->empresa_id);

        // em_elaboracao: legado do SQL 04; preferir rodar 06_normalize_vendas_status_pedido_workflow.sql em produção.
        $labelStatusPedidoVenda = [
            'aguardando_confirmacao' => 'Aguardando confirmação',
            'em_elaboracao' => 'Aguardando confirmação',
            'confirmado' => 'Confirmado',
            'em_separacao' => 'Em separação',
            'separado' => 'Separado',
            'alteracao_pendente' => 'Alteração pendente',
            'em_rota_entrega' => 'Em rota de entrega',
            'ocorrencia_entrega' => 'Ocorrência na entrega',
            'entregue' => 'Entregue',
            'cancelada' => 'Cancelada',
        ];
        $labelStatusPagamentoVenda = [
            'pendente' => 'Pendente',
            'pago' => 'Pago',
            'parcial' => 'Parcial',
            'estornado' => 'Estornado',
        ];
        $usuariosFiltroVenda = Usuario::where('empresa_id', $request->empresa_id)
            ->where('ativo', 1)
            ->orderBy('nome')
            ->get(['id', 'nome']);

        $qtdAlteracaoPendente = Venda::where('empresa_id', $request->empresa_id)
            ->where('status_pedido', 'alteracao_pendente')
            ->count();

        $vendaIdsPagina = $data->getCollection()->pluck('id')->all();
        $rotaPorVenda = collect();
        if (!empty($vendaIdsPagina)) {
            $rotaPorVenda = RotaEntregaItem::with('rota')
                ->whereIn('venda_id', $vendaIdsPagina)
                ->whereHas('rota', function ($q) use ($request) {
                    $q->where('empresa_id', $request->empresa_id)
                        ->whereIn('status', ['rascunho', 'em_rota', 'finalizada']);
                })
                ->orderByDesc('id')
                ->get()
                ->unique('venda_id')
                ->keyBy('venda_id');
        }

        return view('vendas.index', compact(
            'data',
            'config',
            'contigencia',
            'filial_id',
            'labelStatusPedidoVenda',
            'labelStatusPagamentoVenda',
            'usuarioAdm',
            'usuariosFiltroVenda',
            'filter_status_pedido_selecionados',
            'filter_somente_abertos',
            'qtdAlteracaoPendente',
            'rotaPorVenda'
        ));
    }

    private function getContigencia($empresa_id)
    {
        $active = Contigencia::where('empresa_id', $empresa_id)
            ->where('status', 1)
            ->where('documento', 'NFe')
            ->first();
        return $active;
    }

    public function create(Request $request)
    {
        $dataValidate = [
            'clientes',
            'tributacaos',
            'produtos',
            'natureza_operacaos'
        ];
        $util = new Util();
        $validateEntry = $util->validateEntry($dataValidate, $request->empresa_id);
        if ($validateEntry != null) {
            session()->flash("flash_erro", $validateEntry['message']);
            return redirect($validateEntry['route']);
        }
        $paises = Pais::all();
        $grupos = GrupoCliente::where('empresa_id', $request->empresa_id)->get();
        $acessores = Acessor::where('empresa_id', $request->empresa_id)->get();
        $funcionarios = Funcionario::where('empresa_id', $request->empresa_id)->get();
        $formaPagamento = FormaPagamento::where('empresa_id', $request->empresa_id)->get();
        $categorias = Categoria::where('empresa_id', $request->empresa_id)->get();
        $marcas = Marca::where('empresa_id', $request->empresa_id)->get();
        $categoriasEcommerce = CategoriaProdutoEcommerce::where('empresa_id', request()->empresa_id)->get();
        $naturezaPadrao = NaturezaOperacao::where('empresa_id', $request->empresa_id)
            ->first();

        $naturezas = NaturezaOperacao::where('empresa_id', $request->empresa_id)
            ->get();
        $tributacao = Tributacao::where('empresa_id', $request->empresa_id)
            ->first();
        $listaPreco = ListaPreco::where('empresa_id', $request->empresa_id)->get();
        $subDivisoes = DivisaoGrade::where('empresa_id', $request->empresa_id)
            ->where('sub_divisao', true)
            ->get();
        $telasPedido = TelaPedido::where('empresa_id', $request->empresa_id)->get();
        $divisoes = DivisaoGrade::where('sub_divisao', false)->get();
        $config = ConfigNota::where('empresa_id', $request->empresa_id)->first();
        $cidades = Cidade::all();
        $transportadoras = Transportadora::where('empresa_id', $request->empresa_id)->get();
        $precoCategoriaJs = PrecoCategoriaVenda::configParaFrontend($categorias);
        return view('vendas.create', compact(
            'formaPagamento',
            'paises',
            'grupos',
            'telasPedido',
            'acessores',
            'funcionarios',
            'categorias',
            'marcas',
            'categoriasEcommerce',
            'naturezaPadrao',
            'naturezas',
            'tributacao',
            'listaPreco',
            'cidades',
            'config',
            'subDivisoes',
            'divisoes',
            'transportadoras',
            'precoCategoriaJs'
        ));
    }

    public function store(Request $request)
    {

        if ($request->type == 'venda') {
            try {
                $result = DB::transaction(function () use ($request) {
                    $this->garantirDocumentoClienteFiscal($request);
                    $this->sincronizarPrecosTabelaCategoria($request);
                    $valor_total = $this->somaItens($request);
                    $empresa = Empresa::findOrFail($request->empresa_id);
                    $natureza = NaturezaOperacao::findOrFail($request->natureza_id);
                    $frete_id = null;
                    // if($request->tipo_frete != '9'){
                    //     $dataFrete = [
                    //         'valor' => __convert_value_bd($request->valor_frete),
                    //         'placa' => $request->placa_frete ?? '',
                    //         'tipo' => $request->tipo_frete,
                    //         'uf' => $request->uf_frete ?? '',
                    //         'numeracaoVolumes' => $request->n_volumes_frete ?? '',
                    //         'peso_liquido' => $request->peso_liquido_frete ? __convert_value_bd($request->peso_liquido_frete) : 0,
                    //         'peso_bruto' => $request->peso_bruto_frete ? __convert_value_bd($request->peso_bruto_frete) : 0,
                    //         'especie' => $request->especie_frete ?? '',
                    //         'qtdVolumes' => $request->q_volumes_frete ?? ''
                    //     ];
                    //     $frete = Frete::create($dataFrete);
                    //     $frete_id = $frete->id;
                    // }
                    $request->merge([
                        'usuario_id' => get_id_user(),
                        'frete_id' => $frete_id,
                        'observacao' => $request->observacao ?? '',
                        'aviso_entrega' => $request->aviso_entrega ?? null,
                        'modo_preco_arabes' => $request->input('modo_preco_arabes', 'auto'),
                        'modo_preco_miniaturas' => $request->input('modo_preco_miniaturas', 'auto'),
                        'qtd_volumes' => $request->qtd_volumes ?? 0,
                        'peso_liquido' => $request->peso_liquido ?? 0,
                        'peso_bruto' => $request->peso_bruto ?? 0,
                        'transportadora_id' => $request->transportadora_id ? $request->transportadora_id : null,
                        'valor_total' => __convert_value_bd($valor_total),
                        'desconto' => $request->desconto ? __convert_value_bd($request->desconto) : 0,
                        'acrescimo' => $request->acrescimo ? __convert_value_bd($request->acrescimo) : 0,
                        'frete' => $request->frete ? __convert_value_bd($request->frete) : 0,
                        'estado_emissao' => 'novo',
                        'sequencia_cce' => $request->sequencia_cce ?? 0,
                        'chave' => $request->chave ?? 0,
                        'tipo_pagamento' => $request->tipo_pagamentos[0],
                        'filial_id' => $request->filial_id != -1 ? $request->filial_id : null,
                        'status_pedido' => 'aguardando_confirmacao',
                        'status_pagamento' => 'pendente',
                        'origem' => $request->origem ?: 'erp',
                        'pedido_ecommerce_id' => $request->pedido_ecommerce_id ?: 0,
                    ]);

                    $venda = Venda::create($request->all());

                    //verifica frete

                    // Validação de estoque com lock para evitar concorrência
                    $sumByProduct = [];
                    for ($i = 0; $i < sizeof($request->produto_id); $i++) {
                        $pid = (int)$request->produto_id[$i];
                        $q = __convert_value_bd($request->quantidade[$i]);
                        $sumByProduct[$pid] = ($sumByProduct[$pid] ?? 0) + $q;
                    }
                    $lockedStocks = [];
                    foreach ($sumByProduct as $pid => $sum) {
                        $row = Estoque::where('produto_id', $pid)
                            ->when($request->filial_id > 0, function ($q) use ($request) {
                                return $q->where('filial_id', $request->filial_id);
                            })
                            ->lockForUpdate()->first();
                        $available = $row ? (float)$row->quantidade : 0.0;
                        if ($available < (float)$sum - 0.0001) {
                            throw new \Exception($this->mensagemEstoqueInsuficiente($pid, $available, $sum));
                        }
                        $lockedStocks[$pid] = $row;
                    }

                    for ($i = 0; $i < sizeof($request->produto_id); $i++) {
                        $product = Produto::findOrFail($request->produto_id[$i]);
                        $cfop = 0;
                        if ($natureza->sobrescreve_cfop) {
                            $cfop = $natureza->CFOP_saida_estadual;
                        } else {
                            $cfop = $product->CFOP_saida_estadual;
                        }
                        ItemVenda::create([
                            'venda_id' => $venda->id,
                            'produto_id' => (int)$request->produto_id[$i],
                            'quantidade' => __convert_value_bd($request->quantidade[$i]),
                            'cfop' => $cfop,
                            'valor' => __convert_value_bd($request->valor_unitario[$i]),
                            'valor_custo' => $product->valor_compra,
                            'x_pedido' => $request->x_pedido[$i],
                            'num_item_pedido' => $request->num_item_pedido[$i],
                            'qtd_fiscal' => $this->qtdFiscalDoRequest($request, $i, $product),
                        ]);
                        // decrementa no mesmo lock
                        $qtdDec = __convert_value_bd($request->quantidade[$i]);
                        if (isset($lockedStocks[$product->id])) {
                            $lockedStocks[$product->id]->quantidade -= $qtdDec;
                            if ($lockedStocks[$product->id]->quantidade < 0.010) {
                                $lockedStocks[$product->id]->quantidade = 0;
                            }
                            $lockedStocks[$product->id]->save();
                        }
                    }
                    (new \App\Services\EstoqueFiscal())->consumirVenda($venda->id, ItemVenda::where('venda_id', $venda->id)->get());
                    if ($request->forma_pagamento != 'a_vista') {

                        for ($i = 0; $i < sizeof($request->data_vencimento); $i++) {
                            ContaReceber::create([
                                'venda_id' => $venda->id,
                                'cliente_id' => $request->cliente_id,
                                'data_vencimento' => $request->data_vencimento[$i],
                                'data_recebimento' => $request->data_vencimento[$i],
                                'valor_integral' => __convert_value_bd($request->valor_parcela[$i]),
                                'tipo_pagamento' => $request->tipo_pagamentos[$i],
                                'valor_recebido' => 0,
                                'status' => 0,
                                'referencia' => "Parcela $i+1 da Compra código $venda->id",
                                'categoria_id' => CategoriaConta::where('empresa_id', $request->empresa_id)->where('tipo', 'receber')->first()->id,
                                'empresa_id' => $request->empresa_id,
                                'juros' => 0,
                                'multa' => 0,
                                'venda_caixa_id' => null,
                                'observacao' => '',
                                'filial_id' => $request->filial_id != -1 ? $request->filial_id : null
                            ]);
                        }
                    }
                    $venda->load('cliente');
                    $this->registrarAuditoriaVenda(
                        $venda->id,
                        'venda_criada',
                        'Venda #' . $venda->id . ' registrada — ' . ($venda->cliente ? $venda->cliente->razao_social : 'Cliente'),
                        [
                            'valor_total' => (float)$venda->valor_total,
                            'status_pedido' => $venda->status_pedido,
                            'status_pagamento' => $venda->status_pagamento,
                        ]
                    );
                    return true;
                });
                session()->flash("flash_sucesso", "Venda adicionada com sucesso!");
            } catch (\Exception $e) {
                __saveLogError($e, request()->empresa_id);

                return redirect()->back()->withInput()->with('flash_erro', $e->getMessage());
            }

            return redirect()->route('vendas.index');
        } else {
            // ORCAMENTO
            try {
                $result = DB::transaction(function () use ($request) {
                    $valor_total = $this->somaItens($request);
                    //$config = ConfigNota::where('empresa_id', $request->empresa_id)->first();
                    $today = today();
                    $request->merge([
                        'usuario_id' => get_id_user(),
                        'observacao' => $request->observacao ?? '',
                        'qtd_volumes' => $request->qtd_volumes ?? 0,
                        'peso_liquido' => $request->peso_liquido ?? 0,
                        'peso_bruto' => $request->peso_bruto ?? 0,
                        'desconto' => $request->desconto ?? 0,
                        'valor_total' => __convert_value_bd($valor_total),
                        'estado' => 'NOVO',
                        'sequencia_cce' => $request->sequencia_cce ?? 0,
                        'chave' => $request->chave ?? 0,
                        'acrescimo' => $request->acrescimo ?? 0,
                        'email_enviado' => $request->email_enviado ?? 0,
                        //'validade_orcamento' => $config->validade_orcamento ?? 0,
                        'validade' => date("Y-m-d", strtotime("$today +7 day")),
                        'venda_id' => 0,
                        'filial_id' => $request->filial_id != -1 ? $request->filial_id   : null
                    ]);
                    $orcamento = Orcamento::create($request->all());
                    for ($i = 0; $i < sizeof($request->produto_id); $i++) {
                        $product = Produto::findOrFail($request->produto_id[$i]);
                        ItemOrcamento::create([
                            'orcamento_id' => $orcamento->id,
                            'produto_id' => (int)$request->produto_id[$i],
                            'quantidade' => __convert_value_bd($request->quantidade[$i]),
                            'valor' => __convert_value_bd($request->valor_unitario[$i]),
                            'altura' => $request->altura ?? 0,
                            'largura' => $request->largura ?? 0,
                            'profundidade' => $request->profundidade ?? 0,
                            'acrescimo_perca' => $request->acrescimo_perca ?? 0,
                            'esquerda' => $request->esquerda ?? 0,
                            'direita' => $request->direita ?? 0,
                            'inferior' => $request->inferior ?? 0,
                            'superior' => $request->superior ?? 0
                        ]);
                    }
                    // if ($request->forma_pagamento != 'a_vista') {
                    //     for ($i = 0; $i < sizeof($request->data_vencimento); $i++) {

                    //         // ContaReceber::create([
                    //         //     'venda_id' => $venda->id,
                    //         //     'cliente_id' => $request->cliente_id,
                    //         //     'data_vencimento' => $request->data_vencimento[$i],
                    //         //     'data_recebimento' => $request->data_vencimento[$i],
                    //         //     'valor_integral' => __convert_value_bd($request->valor_integral[$i]),
                    //         //     'valor_recebido' => 0,
                    //         //     'status' => 0,
                    //         //     'referencia' => "Parcela $i+1 da Compra código $venda->id",
                    //         //     'categoria_id' => CategoriaConta::where('empresa_id', $request->empresa_id)->first()->id,
                    //         //     'empresa_id' => $request->empresa_id,
                    //         //     'juros' => 0,
                    //         //     'multa' => 0,
                    //         //     'venda_caixa_id' => null,
                    //         //     'observacao' => '',
                    //         //     'tipo_pagamento' => $request->tipo_pagamento
                    //         // ]);
                    //     }
                    // }
                    // return true;
                });
                session()->flash("flash_sucesso", "Orçamento adicionado com sucesso!");
            } catch (\Exception $e) {
                session()->flash("flash_erro", "Algo deu errado: " . $e->getMessage());
                __saveLogError($e, request()->empresa_id);
            }
            return redirect()->route('orcamentoVenda.index');
        }
    }

    private function somaItens($request)
    {
        $valor_total = 0;
        for ($i = 0; $i < sizeof($request->produto_id); $i++) {
            $valor_total += __convert_value_bd($request->subtotal_item[$i]);
        }
        return $valor_total;
    }

    /**
     * Recalcula valor_unitario[] e subtotal_item[] conforme tabela de preço por categoria.
     */
    private function sincronizarPrecosTabelaCategoria(Request $request): void
    {
        if (!is_array($request->produto_id) || count($request->produto_id) === 0) {
            return;
        }

        $categorias = Categoria::where('empresa_id', $request->empresa_id)->get();
        $modos = [
            PrecoCategoriaVenda::GRUPO_ARABES => $request->input('modo_preco_arabes', PrecoCategoriaVenda::MODO_AUTO),
            PrecoCategoriaVenda::GRUPO_MINIATURAS => $request->input('modo_preco_miniaturas', PrecoCategoriaVenda::MODO_AUTO),
        ];

        $valores = PrecoCategoriaVenda::calcularValoresUnitarios(
            $request->produto_id,
            $request->quantidade,
            $modos,
            $categorias
        );

        $valorUnitario = $request->valor_unitario ?? [];
        $subtotais = $request->subtotal_item ?? [];

        foreach ($valores as $i => $vu) {
            $qtd = (float) __convert_value_bd((string) ($request->quantidade[$i] ?? 0));
            $valorUnitario[$i] = __moeda($vu);
            $subtotais[$i] = __moeda($vu * $qtd);
        }

        $request->merge([
            'valor_unitario' => $valorUnitario,
            'subtotal_item' => $subtotais,
        ]);
    }

    public function edit(Request $request, $id)
    {
        $item = Venda::with(['itens.produto'])->findOrFail($id);
        if (!__valida_objeto($item)) {
            abort(403);
        }
        if ($item->fechada_caixa) {
            session()->flash('flash_erro', 'Esta venda está fechada no caixa e não pode ser alterada.');
            return redirect()->route('vendas.show', $item->id);
        }
        $dataValidate = [
            'categorias',
            'produtos',
            'clientes'
        ];
        $util = new Util();
        $validateEntry = $util->validateEntry($dataValidate, $request->empresa_id);
        if ($validateEntry != null) {
            session()->flash("flash_erro", $validateEntry['message']);
            return redirect($validateEntry['route']);
        }
        $paises = Pais::all();
        $cidades = Cidade::all();
        $clientes = Cliente::where('empresa_id', $request->empresa_id)->get();
        $transportadoras = Transportadora::where('empresa_id', $request->empresa_id)->get();
        $categorias = Categoria::where('empresa_id', $request->empresa_id)->get();
        $marcas = Marca::where('empresa_id', $request->empresa_id)->get();
        $categoriasEcommerce = CategoriaProdutoEcommerce::where('empresa_id', request()->empresa_id)->get();
        $naturezaPadrao = NaturezaOperacao::where('empresa_id', $request->empresa_id)
            ->first();
        $tributacao = Tributacao::where('empresa_id', $request->empresa_id)
            ->first();
        $listaPreco = ListaPreco::where('empresa_id', $request->empresa_id)->get();
        $subDivisoes = DivisaoGrade::where('empresa_id', $request->empresa_id)
            ->where('sub_divisao', true)
            ->get();
        $telasPedido = TelaPedido::where('empresa_id', $request->empresa_id)->get();

        $naturezas = NaturezaOperacao::where('empresa_id', $request->empresa_id)
            ->get();
        $config = ConfigNota::where('empresa_id', $request->empresa_id)->first();

        $divisoes = DivisaoGrade::where('sub_divisao', false)->get();
        $precoCategoriaJs = PrecoCategoriaVenda::configParaFrontend($categorias);
        return view(
            'vendas.edit',
            compact(
                'clientes',
                'transportadoras',
                'categorias',
                'marcas',
                'categoriasEcommerce',
                'naturezaPadrao',
                'naturezas',
                'tributacao',
                'item',
                'listaPreco',
                'divisoes',
                'subDivisoes',
                'telasPedido',
                'config',
                'cidades',
                'paises',
                'precoCategoriaJs'
            )
        );
    }

    private function _validate(Request $request)
    {
        $rules = [
            'cliente_id' => 'required',
            'natureza_id' => 'required',
            'produto_id' => 'required'
        ];
        $messages = [
            'cliente_id.required' => 'Campo Obrigatório',
            'natureza_id.required' => 'Campo Obrigatório'
        ];
        $this->validate($request, $rules, $messages);
    }


    public function update(Request $request, $id)
    {
        $vendaPre = Venda::findOrFail($id);
        if (!__valida_objeto($vendaPre)) {
            abort(403);
        }
        if ($vendaPre->fechada_caixa) {
            session()->flash('flash_erro', 'Esta venda está fechada no caixa e não pode ser alterada.');
            return redirect()->route('vendas.index');
        }
        if ($vendaPre->notaFiscalAutorizada()) {
            session()->flash('flash_erro', 'Esta venda tem NF-e autorizada. Cancele a NF-e antes de alterar o pedido.');
            return redirect()->route('vendas.show', $vendaPre->id);
        }
        $this->_validate($request);
        if ($request->type == 'venda') {
            try {
                $result = DB::transaction(function () use ($request, $id) {
                    $this->garantirDocumentoClienteFiscal($request);
                    $item = Venda::findOrFail($id);
                    $statusAntes = $item->status_pedido;
                    $item->load(['itens.produto']);
                    $itensAntes = $this->itensParaAuditoria($item->itens);
                    $cabecalhoAntes = [
                        'desconto' => (float)$item->desconto,
                        'acrescimo' => (float)$item->acrescimo,
                        'frete' => (float)$item->frete,
                    ];
                    $natureza = NaturezaOperacao::findOrFail($request->natureza_id);
                    $this->sincronizarPrecosTabelaCategoria($request);
                    $valor_total = $this->somaItens($request);
                    $frete_id = null;
                    $freteAux = $item->frete;
                    // if($request->tipo_frete != '9'){

                    //     $dataFrete = [
                    //         'valor' => __convert_value_bd($request->valor_frete),
                    //         'placa' => $request->placa_frete ?? '',
                    //         'tipo' => $request->tipo_frete,
                    //         'uf' => $request->uf_frete ?? '',
                    //         'numeracaoVolumes' => $request->n_volumes_frete ?? '',
                    //         'peso_liquido' => $request->peso_liquido_frete ? __convert_value_bd($request->peso_liquido_frete) : 0,
                    //         'peso_bruto' => $request->peso_bruto_frete ? __convert_value_bd($request->peso_bruto_frete) : 0,
                    //         'especie' => $request->especie_frete ?? '',
                    //         'qtdVolumes' => $request->q_volumes_frete ?? ''
                    //     ];
                    //     $frete = Frete::create($dataFrete);
                    //     $frete_id = $frete->id;
                    // }
                    $request->merge([
                        'frete_id' => $frete_id,
                        'usuario_id' => get_id_user(),
                        'transportadora_id' => $request->transportadora_id ? $request->transportadora_id : null,
                        'observacao' => $request->observacao ?? '',
                        'aviso_entrega' => $request->aviso_entrega ?? null,
                        'modo_preco_arabes' => $request->input('modo_preco_arabes', $item->modo_preco_arabes ?? 'auto'),
                        'modo_preco_miniaturas' => $request->input('modo_preco_miniaturas', $item->modo_preco_miniaturas ?? 'auto'),
                        'qtd_volumes' => $request->qtd_volumes ?? 0,
                        'peso_liquido' => $request->peso_liquido ?? 0,
                        'peso_bruto' => $request->peso_bruto ?? 0,
                        'desconto' => $request->desconto ? __convert_value_bd($request->desconto) : 0,
                        'acrescimo' => $request->acrescimo ? __convert_value_bd($request->acrescimo) : 0,
                        'frete' => $request->frete ? __convert_value_bd($request->frete) : 0,
                        'valor_total' => $valor_total,
                        'sequencia_cce' => $request->sequencia_cce ?? 0,
                        'chave' => $request->chave ?? 0,
                        'filial_id' => $request->filial_id != -1 ? $request->filial_id : null
                    ]);
                    $item->fill($request->all())->save();
                    $stockMove = new StockMove();
                    $itens = $item->itens;
                    (new \App\Services\EstoqueFiscal())->estornarVenda($item->id, $itens);
                    $this->revertStock($itens);
                    $item->itens()->delete();
                    $item->duplicatas()->delete();
                    // Validação de estoque com lock antes de recriar itens
                    $sumByProduct = [];
                    for ($i = 0; $i < sizeof($request->produto_id); $i++) {
                        $pid = (int)$request->produto_id[$i];
                        $q = __convert_value_bd($request->quantidade[$i]);
                        $sumByProduct[$pid] = ($sumByProduct[$pid] ?? 0) + $q;
                    }
                    $lockedStocks = [];
                    foreach ($sumByProduct as $pid => $sum) {
                        $row = Estoque::where('produto_id', $pid)
                            ->when($request->filial_id > 0, function ($q) use ($request) {
                                return $q->where('filial_id', $request->filial_id);
                            })
                            ->lockForUpdate()->first();
                        $available = $row ? (float)$row->quantidade : 0.0;
                        if ($available < (float)$sum - 0.0001) {
                            throw new \Exception($this->mensagemEstoqueInsuficiente($pid, $available, $sum));
                        }
                        $lockedStocks[$pid] = $row;
                    }
                    for ($i = 0; $i < sizeof($request->produto_id); $i++) {
                        $product = Produto::findOrFail($request->produto_id[$i]);
                        $cfop = 0;
                        if ($natureza->sobrescreve_cfop) {
                            $cfop = $natureza->CFOP_saida_estadual;
                        } else {
                            $cfop = $product->CFOP_saida_estadual;
                        }

                        ItemVenda::create([
                            'venda_id' => $id,
                            'produto_id' => (int)$request->produto_id[$i],
                            'quantidade' => __convert_value_bd($request->quantidade[$i]),
                            'cfop' => $cfop,
                            'valor' => __convert_value_bd($request->valor_unitario[$i]),
                            'valor_custo' => $product->valor_compra,
                            'x_pedido' => $request->x_pedido[$i],
                            'num_item_pedido' => $request->num_item_pedido[$i],
                            'qtd_fiscal' => $this->qtdFiscalDoRequest($request, $i, $product),
                        ]);
                        // decrementa no mesmo lock
                        $qtdDec = __convert_value_bd($request->quantidade[$i]);
                        if (isset($lockedStocks[$product->id])) {
                            $lockedStocks[$product->id]->quantidade -= $qtdDec;
                            if ($lockedStocks[$product->id]->quantidade < 0.010) {
                                $lockedStocks[$product->id]->quantidade = 0;
                            }
                            $lockedStocks[$product->id]->save();
                        }
                    }
                    (new \App\Services\EstoqueFiscal())->consumirVenda($item->id, ItemVenda::where('venda_id', $item->id)->get());

                    if ($request->forma_pagamento != 'a_vista') {
                        for ($i = 0; $i < sizeof($request->data_vencimento); $i++) {
                            ContaReceber::create([
                                'venda_id' => $item->id,
                                'cliente_id' => $request->cliente_id,
                                'data_vencimento' => $request->data_vencimento[$i],
                                'data_recebimento' => $request->data_vencimento[$i],
                                'valor_integral' => __convert_value_bd($request->valor_parcela[$i]),
                                'valor_recebido' => 0,
                                'status' => 0,
                                'referencia' => "Parcela $i+1 da Compra código $item->id",
                                'categoria_id' => CategoriaConta::where('empresa_id', $request->empresa_id)->where('tipo', 'receber')->first()->id,
                                'empresa_id' => $request->empresa_id,
                                'juros' => 0,
                                'multa' => 0,
                                'venda_caixa_id' => null,
                                'observacao' => '',
                                'tipo_pagamento' => $request->tipo_pagamento,
                                'filial_id' => $request->filial_id != -1 ? $request->filial_id : null
                            ]);
                        }
                    }
                    // if($freteAux){
                    //     $freteAux->delete();
                    // }
                    $virouAlteracaoPendente = in_array($statusAntes, ['separado', 'em_separacao', 'em_rota_entrega'], true);
                    if ($virouAlteracaoPendente) {
                        $item->refresh();
                        $item->status_pedido = 'alteracao_pendente';
                        $item->save();
                    }
                    $item->refresh();
                    $itensDepois = $this->itensDepoisFromRequest($request);
                    $cabecalhoDepois = [
                        'desconto' => (float)$item->desconto,
                        'acrescimo' => (float)$item->acrescimo,
                        'frete' => (float)$item->frete,
                    ];
                    $linhasItens = $this->linhasResumoAlteracaoItens($itensAntes, $itensDepois);
                    $linhasCab = $this->linhasResumoCabecalhoVenda($cabecalhoAntes, $cabecalhoDepois);
                    $descricaoAlteracao = $this->montarDescricaoAlteracaoVenda(
                        $linhasItens,
                        $linhasCab,
                        $virouAlteracaoPendente,
                        $statusAntes
                    );
                    $this->registrarAuditoriaVenda(
                        $item->id,
                        'venda_alterada',
                        $descricaoAlteracao,
                        [
                            'status_pedido_antes' => $statusAntes,
                            'status_pedido_depois' => $item->status_pedido,
                            'itens_antes' => $itensAntes,
                            'itens_depois' => $itensDepois,
                        ]
                    );
                    $this->registrarMovimentacoesEdicaoVenda($item, $itensAntes, $itensDepois);
                    $item->versao_pedido = (int) ($item->versao_pedido ?: 1) + 1;
                    $item->save();
                    \App\Helpers\EcommerceSync::syncFromVenda($item->fresh(['itens.produto']), true);
                    return ['virou_alteracao' => $virouAlteracaoPendente];
                });
                if (!empty($result['virou_alteracao'])) {
                    session()->flash(
                        'flash_warning',
                        'Atenção: pedido com alteração pendente — confira os itens (separação/rota) antes de seguir.'
                    );
                }
                session()->flash("flash_sucesso", "Venda atualizada com sucesso!");
            } catch (\Exception $e) {
                __saveLogError($e, request()->empresa_id);

                return redirect()->back()->withInput()->with('flash_erro', $e->getMessage());
            }
        }

        return redirect()->route('vendas.index');
    }

    /**
     * Mensagem legível de estoque insuficiente (nome do produto + quantidades).
     */
    private function mensagemEstoqueInsuficiente(int $pid, float $available, float $sum): string
    {
        $p = Produto::find($pid);
        $nome = $p ? $p->nome : 'Produto #' . $pid;
        $fmt = static function ($v) {
            $s = number_format((float) $v, 4, ',', '.');

            return rtrim(rtrim($s, '0'), ',') ?: '0';
        };

        return 'Estoque insuficiente para "' . $nome . '" (cód. ' . $pid . '). Disponível: ' . $fmt($available) . '. Solicitado: ' . $fmt($sum) . '.';
    }

    private function revertStock($itens)
    {
        $stockMove = new StockMove();
        $filialRaw = $itens[0]->venda->filial_id ?? null;
        $filialPlu = ($filialRaw !== null && (int) $filialRaw > 0) ? (int) $filialRaw : -1;

        foreach ($itens as $i) {
            $stockMove->pluStock(
                $i->produto_id,
                (float) __convert_value_bd($i->quantidade),
                -1,
                $filialPlu
            );
        }
    }

    public function show(Request $request, $id)
    {
        $item = Venda::findOrFail($id);
        if (!__valida_objeto($item)) {
            abort(403);
        }
        $auditorias = VendaAuditoria::with('usuario')
            ->where('venda_id', $id)
            ->where('empresa_id', request()->empresa_id)
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $usuarioAdm = (bool) optional(Usuario::find(get_id_user()))->adm;

        return view('vendas.show', compact('item', 'auditorias', 'usuarioAdm'));
    }

    public function importacao()
    {
        return view('importacao.index');
    }

    public function destroy($id)
    {
        $item = Venda::findOrFail($id);
        if (!__valida_objeto($item)) {
            abort(403);
        }
        if ($item->fechada_caixa) {
            session()->flash('flash_erro', 'Esta venda está fechada no caixa e não pode ser excluída.');
            return redirect()->route('vendas.index');
        }
        if ($item->notaFiscalAutorizada()) {
            session()->flash('flash_erro', 'Esta venda tem NF-e autorizada. Cancele a NF-e antes de excluir o pedido.');
            return redirect()->route('vendas.index');
        }
        try {
            DB::transaction(function () use ($item) {
                $item->loadMissing('itens.produto');
                (new \App\Services\EstoqueFiscal())->estornarVenda($item->id, $item->itens);
                $this->revertStock($item->itens);
                $this->registrarMovimentacoesExclusaoVenda($item);
                $item->delete();
            });
            session()->flash("flash_sucesso", "Venda deletada!");
        } catch (\Exception $e) {
            session()->flash("flash_erro", "Algo deu errado: " . $e->getMessage());
            __saveLogError($e, request()->empresa_id);
        }
        return redirect()->route('vendas.index');
    }

    public function xmlTemp($id)
    {
        $item = Venda::findOrFail($id);

        if (!__valida_objeto($item)) {
            abort(403);
        }
        $config = ConfigNota::where('empresa_id', request()->empresa_id)
            ->first();

        $cnpj = preg_replace('/[^0-9]/', '', $config->cnpj);

        $nfe_service = new NFService([
            "atualizacao" => date('Y-m-d h:i:s'),
            "tpAmb" => (int)$config->ambiente,
            "razaosocial" => $config->razao_social,
            "siglaUF" => $config->cidade->uf,
            "cnpj" => $cnpj,
            "schemes" => "PL_009_V4",
            "versao" => "4.00",
            "tokenIBPT" => "AAAAAAA",
            "CSC" => $config->csc,
            "CSCid" => $config->csc_id
        ], $config);

        $nfe = $nfe_service->gerarNFe($item);
        if (!isset($nfe['erros_xml'])) {
            $xml = $nfe['xml'];
            return response($xml)
                ->header('Content-Type', 'application/xml');
        } else {
            // print_r($nfe['erros_xml']);
            foreach ($nfe['erros_xml'] as $err) {
                echo $err;
            }
        }
    }

    public function danfeTemp($id)
    {
        $item = Venda::findOrFail($id);
        if (!__valida_objeto($item)) {
            abort(403);
        }
        $config = ConfigNota::where('empresa_id', request()->empresa_id)
            ->first();
        $cnpj = preg_replace('/[^0-9]/', '', $config->cnpj);
        $nfe_service = new NFService([
            "atualizacao" => date('Y-m-d h:i:s'),
            "tpAmb" => (int)$config->ambiente,
            "razaosocial" => $config->razao_social,
            "siglaUF" => $config->cidade->uf,
            "cnpj" => $cnpj,
            "schemes" => "PL_009_V4",
            "versao" => "4.00",
            "tokenIBPT" => "AAAAAAA",
            "CSC" => $config->csc,
            "CSCid" => $config->csc_id
        ], $config);
        $nfe = $nfe_service->gerarNFe($item);
        if (!isset($nfe['erros_xml'])) {
            $xml = $nfe['xml'];
            try {
                $logo = null;
                $danfe = new Danfe($xml);
                $danfe->setVUnComCasasDec($config->casas_decimais);
                $pdf = $danfe->render($logo);
                header("Content-Disposition: ; filename=DANFE TEMPORÁRIA");
                return response($pdf)
                    ->header('Content-Type', 'application/pdf');
            } catch (InvalidArgumentException $e) {
                echo "Ocorreu um erro durante o processamento :" . $e->getMessage();
            }
        } else {
            print_r($nfe['erros_xml']);
        }
    }

    public function importStore(Request $request)
    {
        $tabela = $request->tabela;
        $data = json_decode($request->data);
        $public = env('SERVIDOR_WEB') ? 'public/' : '';
        foreach ($data as $d) {
            if ($request->input('ch_' . $d->chave)) {
                $cliente = json_decode(json_encode($d->cliente), true);
                if ($cliente) {
                    $cliente = $this->insereCliente($cliente);
                } else {
                }
                $produtos = json_decode(json_encode($d->produtos), true);
                $itens = $this->insereProdutos($produtos);
                if ($tabela == 'vendas') {
                    if ($cliente != null) {
                        $vendaId = $this->salvarVenda($d, $cliente, $produtos);
                        $this->gravarItensVenda($vendaId, $itens);

                        $fatura = json_decode(json_encode($d->fatura), true);
                        $this->salvarFatura($vendaId, $fatura);

                        File::copy($d->file, $public . "xml_nfe/" . $d->chave . ".xml");
                    }
                } else {
                    $vendaId = $this->salvarVendaCaixa($d, $produtos);
                    $this->gravarItensVendaCaixa($vendaId, $itens);

                    File::copy($d->file, $public . "xml_nfce/" . $d->chave . ".xml");
                }
            }
        }
        session()->flash('flash_sucesso', 'Importação concluida!!');
        return redirect()->route('vendas.importacao');
    }



    public function gerarXml($id)
    {
        $certificado = Certificado::where('empresa_id', request()->empresa_id)
            ->first();

        if ($certificado == null) {
            echo "Necessário o certificado para realizar esta ação!";
            die;
        }
        $venda = Venda::find($id);

        if (valida_objeto($venda)) {
            $config = ConfigNota::where('empresa_id', request()->empresa_id)
                ->first();
            $cnpj = preg_replace('/[^0-9]/', '', $config->cnpj);
            $nfe_service = new NFService([
                "atualizacao" => date('Y-m-d h:i:s'),
                "tpAmb" => (int)$config->ambiente,
                "razaosocial" => $config->razao_social,
                "siglaUF" => $config->UF,
                "cnpj" => $cnpj,
                "schemes" => "PL_009_V4",
                "versao" => "4.00",
                "tokenIBPT" => "AAAAAAA",
                "CSC" => $config->csc,
                "CSCid" => $config->csc_id,
            ], $config);
            $nfe = $nfe_service->gerarNFe($id);
            if (!isset($nfe['erros_xml'])) {
                $xml = $nfe_service->sign($nfe['xml']);

                return response($xml)
                    ->header('Content-Type', 'application/xml');
            } else {
                foreach ($nfe['erros_xml'] as $e) {
                    echo $e;
                }
            }
        } else {
            return redirect('/403');
        }
    }

    public function printStatusJson($id)
    {
        $item = Venda::findOrFail($id);
        if (!__valida_objeto($item)) {
            return response()->json(['message' => 'Acesso negado.'], 403);
        }
        if (!$this->podeImprimirFichaSeparacao($item)) {
            return response()->json(['message' => 'Confirme o pedido antes de imprimir a ficha de separação.'], 400);
        }
        $this->aplicarTransicaoImpressaoPedido($item);
        $item->refresh();

        return response()->json([
            'ok' => true,
            'status_pedido' => $item->status_pedido,
            'status_pagamento' => $item->status_pagamento,
            'versao_pedido' => (int) $item->versao_pedido,
            'versao_ficha_impressa' => $item->versao_ficha_impressa,
        ]);
    }

    private function podeImprimirFichaSeparacao(Venda $item): bool
    {
        if ($item->fechada_caixa || $item->estado_emissao === 'cancelado' || $item->status_pedido === 'cancelada') {
            return false;
        }

        return in_array($item->status_pedido, [
            'confirmado',
            'em_separacao',
            'separado',
            'alteracao_pendente',
            'em_rota_entrega',
            'entregue',
        ], true);
    }

    private function registrarImpressaoFichaSeparacao(Venda $item): void
    {
        $versao = (int) ($item->versao_pedido ?: 1);
        $this->aplicarTransicaoImpressaoPedido($item);
        $item->versao_ficha_impressa = $versao;
        $item->ficha_impressa_em = now();
        $item->save();
        $this->registrarAuditoriaVenda(
            $item->id,
            'ficha_separacao_impressa',
            'Ficha de separação impressa (versão ' . $versao . ' do pedido).',
            [
                'versao_pedido' => $versao,
                'versao_ficha_impressa' => $versao,
                'status_pedido' => $item->status_pedido,
            ]
        );
    }

    private function registrarDownloadPedidoPdf(Venda $item): void
    {
        $versao = (int) ($item->versao_pedido ?: 1);
        $item->versao_pedido_pdf = $versao;
        $item->pedido_pdf_em = now();
        $item->save();
        $this->registrarAuditoriaVenda(
            $item->id,
            'pedido_pdf_baixado',
            'PDF do pedido baixado (versão ' . $versao . ').',
            [
                'versao_pedido' => $versao,
                'versao_pedido_pdf' => $versao,
            ]
        );
    }

    /**
     * Confirmação → em separação ao imprimir a ficha de separação.
     */
    private function aplicarTransicaoImpressaoPedido(Venda $item): void
    {
        if ($item->fechada_caixa || $item->estado_emissao === 'cancelado' || $item->status_pedido === 'cancelada') {
            return;
        }
        if ($item->status_pedido !== 'confirmado') {
            return;
        }
        $item->status_pedido = 'em_separacao';
        $item->save();
        $this->registrarAuditoriaVenda(
            $item->id,
            'fluxo_impressao_em_separacao',
            'Status passou para «Em separação» ao imprimir/visualizar o pedido.',
            ['status_pedido' => $item->status_pedido]
        );
        \App\Helpers\EcommerceSync::syncFromVenda($item, false);
    }

    public function print($id)
    {
        $item = Venda::with([
            'cliente.cidade',
            'itens.produto.categoria',
            'itens.produto.subCategoria',
            'duplicatas',
            'usuario',
            'vendedor_setado.funcionario',
        ])->find($id);
        if (!__valida_objeto($item)) {
            abort(403);
        }
        if (request()->boolean('download')) {
            $this->registrarDownloadPedidoPdf($item);
            $item->refresh();
        }
        $item->load([
            'cliente.cidade',
            'itens.produto.categoria',
            'itens.produto.subCategoria',
            'duplicatas',
            'usuario',
            'vendedor_setado.funcionario',
        ]);
        $config = ConfigNota::where('empresa_id', $item->empresa_id)
            ->first();
        $p = view('vendas.print', compact('config', 'item'));
        $domPdf = new Dompdf(["enable_remote" => true]);
        $domPdf->loadHtml($p);
        $pdf = ob_get_clean();
        $domPdf->setPaper("A4");
        $domPdf->render();
        $domPdf->stream("Pedido de Venda $id.pdf", ['Attachment' => request()->boolean('download')]);
    }

    /**
     * PDF apenas com a ficha de separação.
     */
    public function printFichaSeparacao($id)
    {
        $item = Venda::with([
            'cliente.cidade',
            'itens.produto.categoria',
            'itens.produto.subCategoria',
        ])->find($id);
        if (!__valida_objeto($item)) {
            abort(403);
        }
        if (!$this->podeImprimirFichaSeparacao($item)) {
            session()->flash('flash_erro', 'Confirme o pedido antes de imprimir a ficha de separação.');
            return redirect()->route('vendas.index');
        }

        $versaoFicha = (int) ($item->versao_pedido ?: 1);
        $this->registrarImpressaoFichaSeparacao($item);
        $item->refresh();
        $config = ConfigNota::where('empresa_id', $item->empresa_id)->first();
        $p = view('vendas.print_ficha_document', compact('config', 'item', 'versaoFicha'));
        $domPdf = new Dompdf(["enable_remote" => true]);
        $domPdf->loadHtml($p);
        $pdf = ob_get_clean();
        $domPdf->setPaper("A4");
        $domPdf->render();
        $domPdf->stream("Ficha separação pedido $id.pdf", ['Attachment' => request()->boolean('download')]);
    }

    public function routesTxt(Request $request)
    {
        $ids = $request->input('ids', []);
        if (!is_array($ids) || count($ids) == 0) {
            return response()->json(['message' => 'Sem ids'], 400);
        }
        $confirmarAlteracoes = (bool) $request->input('confirmar_alteracoes', false);
        $vendas = Venda::with(['cliente', 'duplicatas'])
            ->whereIn('id', $ids)
            ->where('empresa_id', request()->empresa_id)
            ->get();

        $pendentesAlteracao = $vendas->filter(function ($v) {
            return $v->status_pedido === 'alteracao_pendente';
        });
        if ($pendentesAlteracao->count() > 0 && !$confirmarAlteracoes) {
            return response()->json([
                'needs_confirmation' => true,
                'message' => 'Existem vendas com alteração pendente de conferência física. Confirme para gerar a rota.',
            ], 422);
        }

        foreach ($vendas as $v) {
            if ($v->fechada_caixa) {
                return response()->json(['message' => 'A venda #' . $v->id . ' está fechada no caixa.'], 423);
            }
        }

        $vendas = $vendas->sort(function ($a, $b) {
            $bairroA = $this->normalizeRouteText(optional($a->cliente)->bairro);
            $bairroB = $this->normalizeRouteText(optional($b->cliente)->bairro);

            $prioridadeA = $this->bairroRoutePriority($bairroA);
            $prioridadeB = $this->bairroRoutePriority($bairroB);

            if ($prioridadeA !== $prioridadeB) {
                return $prioridadeA <=> $prioridadeB;
            }

            if ($bairroA !== $bairroB) {
                return $bairroA <=> $bairroB;
            }

            $ruaA = $this->normalizeRouteText(optional($a->cliente)->rua);
            $ruaB = $this->normalizeRouteText(optional($b->cliente)->rua);

            if ($ruaA !== $ruaB) {
                return $ruaA <=> $ruaB;
            }

            $nomeA = $this->normalizeRouteText(optional($a->cliente)->razao_social);
            $nomeB = $this->normalizeRouteText(optional($b->cliente)->razao_social);

            return $nomeA <=> $nomeB;
        })->values();

        $lines = [];
        $bairroAtual = null;
        foreach ($vendas as $v) {
            $c = $v->cliente;
            if (!$c) continue;
            $nome = trim($c->razao_social ?? '');
            $fone = trim($c->celular ?? ($c->telefone ?? ''));
            $rua = trim($c->rua ?? '');
            $numero = trim($c->numero ?? '');
            $bairro = trim($c->bairro ?? '');
            $complemento = trim($c->complemento ?? '');
            $valorTotal = floatval($v->valor_total) - floatval($v->desconto) + floatval($v->acrescimo) + floatval($v->frete);
            $valorFmt = number_format($valorTotal, 2, ',', '.');
            $end = trim($rua . (strlen($numero) ? ", $numero" : "") . (strlen($bairro) ? " - $bairro" : ""));
            $bairroFormatado = strlen($bairro) ? mb_strtoupper($bairro, 'UTF-8') : 'SEM BAIRRO';

            if ($bairroAtual !== $bairroFormatado) {
                if (count($lines) > 0) {
                    $lines[] = "";
                }
                $lines[] = "=== " . $bairroFormatado . " ===";
                $lines[] = "";
                $bairroAtual = $bairroFormatado;
            }

            // Nome com número do pedido entre parênteses
            $lines[] = $nome . " (" . $v->id . ")";
            $lines[] = $fone;
            $lines[] = $end;
            if (strlen($complemento) > 0) {
                $lines[] = $complemento;
            }
            // última linha com valor destacado entre asteriscos
            // exibir quantidade de parcelas SOMENTE para cartão de crédito (03)
            $qtdParcelas = $v->duplicatas ? $v->duplicatas->count() : 0;
            if ($qtdParcelas <= 0) $qtdParcelas = 1;
            $mostrarParcelas = false;
            if ($v->tipo_pagamento === '03') {
                $mostrarParcelas = true;
            } elseif ($v->duplicatas && $v->duplicatas->count() > 0) {
                foreach ($v->duplicatas as $dup) {
                    if ($dup->tipo_pagamento === '03') { $mostrarParcelas = true; break; }
                }
            }
            if ($mostrarParcelas) {
                $lines[] = "*R$ " . $valorFmt . "*  (" . $qtdParcelas . "x)";
            } else {
                $lines[] = "*R$ " . $valorFmt . "*";
            }
            $lines[] = "-----------------------";
            $lines[] = "";
        }
        $content = implode("\r\n", $lines);
        $filename = "rotas-" . date('Ymd-His') . ".txt";

        Venda::whereIn('id', $ids)
            ->where('empresa_id', request()->empresa_id)
            ->where('estado_emissao', '!=', 'cancelado')
            ->where('status_pedido', '!=', 'cancelada')
            ->update(['status_pedido' => 'em_rota_entrega']);

        \App\Helpers\EcommerceSync::syncByVendaIds($ids, false);

        return response($content)
            ->header('Content-Type', 'text/plain; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    private function bairroRoutePriority($bairro)
    {
        $ordem = $this->bairroRouteOrder();
        return $ordem[$bairro] ?? 999;
    }

    private function bairroRouteOrder()
    {
        $bairros = [
            'geisel',
            'ernesto geisel',
            'grotao',
            'joao paulo ii',
            'cuia',
            'jose americo',
            'funcionarios',
            'cristo redentor',
            'jaguaribe',
            'bancarios',
            'agua fria',
            'mangabeira',
            'valentina',
            'gramame',
            'paratibe',
            'colinas do sul',
            'planalto boa esperanca',
            'mucumagro',
        ];

        $ordem = [];
        foreach ($bairros as $index => $bairro) {
            $ordem[$this->normalizeRouteText($bairro)] = $index;
        }

        return $ordem;
    }

    private function normalizeRouteText($value)
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }

        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($ascii !== false) {
            $value = $ascii;
        }

        $value = mb_strtolower($value, 'UTF-8');
        $value = preg_replace('/[^a-z0-9]+/i', ' ', $value);

        return trim($value);
    }

    public function clone($id)
    {
        $item = Venda::find($id);
        if (!__valida_objeto($item)) {
            abort(403);
        }

        $semEstoque = $this->validaEstoque($item);

        $config = ConfigNota::where('empresa_id', request()->empresa_id)
            ->first();

        return view('vendas.clone', compact('config', 'semEstoque', 'item'));
    }

    private function validaEstoque($venda)
    {
        $semEstoque = [];
        foreach ($venda->itens as $item) {
            $p = $item->produto;
            $qtdDisponivel = $p->estoquePorLocal($item->filial_id);
            if ($item->quantidade > $qtdDisponivel && $p->gerenciar_estoque) {
                array_push($semEstoque, $p);
            }
        }

        return $semEstoque;
    }

    public function clonarPut(Request $request, $id)
    {
        $venda = Venda::findOrFail($id);
        $cliente_id = $request->cliente_id;

        if (!__valida_objeto($venda)) {
            abort(403);
        }

        if (!$cliente_id) {
            session()->flash("flash_erro", "Informe o cliente!");
            return redirect()->back();
        }

        try {
            $this->garantirDocumentoClienteFiscal(new Request([
                'produto_id' => $venda->itens->pluck('produto_id')->all(),
                'cliente_id' => $cliente_id,
            ]));
        } catch (\Exception $e) {
            session()->flash("flash_erro", $e->getMessage() . ' Ajuste o cadastro do cliente e clone novamente.');
            return redirect()->back();
        }

        $freteId = null;
        if ($venda->frete_id != NULL) {
            $frete = Frete::create([
                'placa' => $venda->frete->placa,
                'valor' => $venda->frete->valor,
                'tipo' => $venda->frete->tipo,
                'qtdVolumes' => $venda->frete->qtdVolumes,
                'uf' => $venda->frete->uf,
                'numeracaoVolumes' => $venda->frete->numeracaoVolumes,
                'especie' => $venda->frete->especie,
                'peso_liquido' => $venda->frete->peso_liquido,
                'peso_bruto' => $venda->frete->peso_bruto
            ]);
            $freteId = $frete->id;
        }

        $novaVenda = [
            'cliente_id' => $cliente_id,
            'usuario_id' => get_id_user(),
            'frete_id' => $freteId,
            'valor_total' => $venda->valor_total,
            'forma_pagamento' => $venda->forma_pagamento,
            'numero_nfe' => 0,
            'natureza_id' => $venda->natureza_id,
            'chave' => '',

            'estado_emissao' => 'novo',
            'observacao' => $venda->observacao,
            'desconto' => $venda->desconto,
            'acrescimo' => $venda->acrescimo,
            'frete' => $venda->frete,
            'transportadora_id' => $venda->transportadora_id,
            'sequencia_cce' => 0,
            'tipo_pagamento' => $venda->tipo_pagamento,
            'empresa_id' => $request->empresa_id,
            'bandeira_cartao' => $venda->bandeira_cartao,
            'cAut_cartao' => $venda->cAut_cartao,
            'cnpj_cartao' => $venda->cnpj_cartao,
            'descricao_pag_outros' => $venda->descricao_pag_outros,
            'filial_id' => $venda->filial_id,
            'status_pedido' => 'aguardando_confirmacao',
            'status_pagamento' => 'pendente',
        ];

        $result = Venda::create($novaVenda);

        $itens = $venda->itens;
        $stockMove = new StockMove();
        foreach ($itens as $i) {
            ItemVenda::create([
                'venda_id' => $result->id,
                'produto_id' => $i->produto_id,
                'quantidade' => $i->quantidade,
                'valor' => $i->valor,
                'cfop' => $i->cfop,
                'valor_custo' => $i->produto->valor_compra,
                'x_pedido' => $i->x_pedido,
                'num_item_pedido' => $i->num_item_pedido

            ]);

            $prod = Produto
                ::where('id', $i->produto_id)
                ->first();

            if (!empty($prod->receita)) {

                $receita = $prod->receita;
                foreach ($receita->itens as $rec) {

                    if (!empty($rec->produto->receita)) {
                        $receita2 = $rec->produto->receita;

                        foreach ($receita2->itens as $rec2) {
                            $stockMove->downStock(
                                $rec2->produto_id,
                                $i->quantidade *
                                    ($rec2->quantidade / $receita2->rendimento),
                                $venda->filial_id
                            );
                        }
                    } else {

                        $stockMove->downStock(
                            $rec->produto_id,
                            $i->quantidade *
                                ($rec->quantidade / $receita->rendimento),
                            $venda->filial_id
                        );
                    }
                }
            } else {
                $stockMove->downStock(
                    $i->produto_id,
                    $i->quantidade,
                    $venda->filial_id
                );
            }
        }

        if ($venda->forma_pagamento != 'a_vista' && $venda->forma_pagamento != 'conta_crediario') {
            $fatura = $venda->duplicatas;

            foreach ($fatura as $key => $f) {
                $valorParcela = str_replace(",", ".", $f['valor']);

                $resultFatura = ContaReceber::create([
                    'venda_id' => $result->id,
                    'data_vencimento' => $f->data_vencimento,
                    'data_recebimento' => $f->data_recebimento,
                    'valor_integral' => $f->valor_integral,
                    'valor_recebido' => 0,
                    'tipo_pagamento' => $f->tipo_pagamento,
                    'status' => false,
                    'entrada' => $f['entrada'],
                    'referencia' => "Parcela " . ($key + 1) . "/" . sizeof($fatura) . ", da Venda " . $result->id,
                    'categoria_id' => CategoriaConta::where('empresa_id', $request->empresa_id)->where('tipo', 'receber')->first()->id,
                    'empresa_id' => $request->empresa_id
                ]);
            }
        }

        session()->flash("flash_sucesso", "Venda duplicada com sucesso!");
        return redirect()->route('vendas.index');
    }

    private function usuarioEhAdministrador(): bool
    {
        $u = Usuario::find(get_id_user());

        return $u && (bool) $u->adm;
    }

    public function workflowStatus(Request $request)
    {
        $request->validate([
            'venda_id' => 'required|integer',
            'acao' => 'required|string|in:confirmar_pedido,marcar_separado,confirmar_alteracao,marcar_pago',
        ]);
        $v = Venda::findOrFail($request->venda_id);
        if (!__valida_objeto($v)) {
            abort(403);
        }
        if ($v->fechada_caixa) {
            return response()->json(['message' => 'Venda fechada no caixa.'], 423);
        }
        $acao = $request->acao;
        if ($acao === 'confirmar_pedido') {
            if (!in_array($v->status_pedido, ['aguardando_confirmacao', 'em_elaboracao'], true)) {
                return response()->json(['message' => 'Transição inválida para esta venda.'], 400);
            }
            $v->status_pedido = 'confirmado';
        } elseif ($acao === 'marcar_separado') {
            if ($v->status_pedido !== 'em_separacao') {
                return response()->json(['message' => 'Só é possível marcar como separado quando o status for "Em separação".'], 400);
            }
            $v->status_pedido = 'separado';
        } elseif ($acao === 'confirmar_alteracao') {
            if ($v->status_pedido !== 'alteracao_pendente') {
                return response()->json(['message' => 'Esta venda não está com alteração pendente.'], 400);
            }
            $v->status_pedido = 'separado';
        } elseif ($acao === 'marcar_pago') {
            if ($v->status_pagamento !== 'pendente') {
                return response()->json(['message' => 'O pagamento já foi registrado.'], 400);
            }
            $v->status_pagamento = 'pago';
        }
        $v->save();

        $rotulosWorkflow = [
            'confirmar_pedido' => 'Pedido confirmado (fluxo de separação).',
            'marcar_separado' => 'Marcado como separado.',
            'confirmar_alteracao' => 'Alteração conferida — voltou para separado.',
            'marcar_pago' => 'Pagamento registrado como pago.',
        ];
        $this->registrarAuditoriaVenda(
            $v->id,
            'workflow_' . $acao,
            $rotulosWorkflow[$acao] ?? $acao,
            [
                'status_pedido' => $v->status_pedido,
                'status_pagamento' => $v->status_pagamento,
            ]
        );

        \App\Helpers\EcommerceSync::syncFromVenda($v);

        return response()->json([
            'ok' => true,
            'status_pedido' => $v->status_pedido,
            'status_pagamento' => $v->status_pagamento,
        ]);
    }

    public function workflowMarcarEntregue(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        $bloqueados = [];
        foreach ($request->ids as $id) {
            $v = Venda::find($id);
            if (!$v || !__valida_objeto($v) || $v->fechada_caixa) {
                continue;
            }
            if ($v->status_pedido !== 'em_rota_entrega') {
                continue;
            }

            $itemRota = \App\Models\RotaEntregaItem::where('venda_id', $v->id)
                ->whereHas('rota', function ($q) {
                    $q->whereIn('status', ['rascunho', 'em_rota']);
                })
                ->first();

            if ($itemRota && $itemRota->status_entrega === 'pendente') {
                $bloqueados[] = '#' . $v->id;
                continue;
            }

            $v->status_pedido = 'entregue';
            $v->save();
            $this->registrarAuditoriaVenda(
                $v->id,
                'workflow_marcar_entregue',
                'Pedido marcado como entregue.',
                ['status_pedido' => $v->status_pedido]
            );
            \App\Helpers\EcommerceSync::syncFromVenda($v);
        }

        if (!empty($bloqueados)) {
            return response()->json([
                'ok' => false,
                'message' => 'Não é possível marcar como entregue sem confirmação na rota: ' . implode(', ', $bloqueados),
            ], 422);
        }

        return response()->json(['ok' => true]);
    }

    public function fecharCaixa($id)
    {
        if (!$this->usuarioEhAdministrador()) {
            abort(403);
        }
        $v = Venda::findOrFail($id);
        if (!__valida_objeto($v)) {
            abort(403);
        }
        if ($v->fechada_caixa) {
            return response()->json(['message' => 'Esta venda já está fechada.'], 400);
        }
        $v->fechada_caixa = true;
        $v->fechada_em = now();
        $v->fechada_por_usuario_id = get_id_user();
        $v->save();

        $this->registrarAuditoriaVenda(
            $v->id,
            'caixa_fechada',
            'Venda fechada no caixa (ADM).',
            ['fechada_em' => $v->fechada_em ? $v->fechada_em->toIso8601String() : null]
        );

        return response()->json([
            'ok' => true,
            'fechada_em_label' => $v->fechada_em ? __data_pt($v->fechada_em, 1) : null,
        ]);
    }

    /** Quantidade fiscal escolhida na tela para a linha $i (0 se o produto não for fiscal). */
    private function qtdFiscalDoRequest(Request $request, int $i, $product): float
    {
        if (!$product || !(int) $product->fiscal) {
            return 0.0;
        }
        $qtd = (float) __convert_value_bd((string) $request->quantidade[$i]);
        $raw = $request->input('qtd_fiscal.' . $i);
        if ($raw === null || $raw === '') {
            return 0.0;
        }
        $qf = (float) __convert_value_bd((string) $raw);
        return round(max(0, min($qf, $qtd)), 3);
    }

    /**
     * Venda com item fiscal exige CPF/CNPJ válido no cadastro do cliente.
     * Se veio "cliente_cpf_cnpj" na tela da venda, grava no cadastro do cliente.
     * Lança exceção (a venda volta para a tela com a mensagem) se faltar.
     */
    private function garantirDocumentoClienteFiscal(Request $request): void
    {
        $temFiscal = false;
        foreach ((array) $request->produto_id as $i => $pid) {
            $qf = (float) __convert_value_bd((string) ($request->input('qtd_fiscal.' . $i) ?? '0'));
            if ($qf > 0 && Produto::where('id', (int) $pid)->where('fiscal', 1)->exists()) {
                $temFiscal = true;
                break;
            }
        }
        if (!$temFiscal) {
            return;
        }

        $cliente = Cliente::find($request->cliente_id);
        if (!$cliente) {
            throw new \Exception('Venda com produto fiscal: selecione o cliente e informe o CPF/CNPJ.');
        }

        $docTela = trim((string) $request->input('cliente_cpf_cnpj', ''));
        if ($docTela !== '') {
            if (!\App\Helpers\Documento::valido($docTela)) {
                throw new \Exception('CPF/CNPJ informado é inválido: ' . $docTela);
            }
            $formatado = \App\Helpers\Documento::formatar($docTela);
            if ($cliente->cpf_cnpj !== $formatado) {
                $cliente->cpf_cnpj = $formatado;
                $cliente->save();
            }
            return;
        }

        if (!\App\Helpers\Documento::valido($cliente->cpf_cnpj)) {
            throw new \Exception('Venda com produto fiscal: o cliente "' . $cliente->razao_social . '" está sem CPF/CNPJ válido. Preencha o campo CPF/CNPJ abaixo do cliente.');
        }
    }

    /**
     * Marca / desmarca a NF-e da parte fiscal como emitida no outro sistema.
     * POST acao=emitida (com numero opcional) | acao=pendente
     */
    public function nfExterna(Request $request, $id)
    {
        $v = Venda::with('itens')->findOrFail($id);
        if (!__valida_objeto($v)) {
            abort(403);
        }

        $acao = $request->input('acao');
        if (!in_array($acao, ['emitida', 'pendente'], true)) {
            return response()->json(['message' => 'Ação inválida.'], 422);
        }

        $div = $v->divisaoFiscal();
        if ($acao === 'emitida' && !$div['tem_fiscal']) {
            return response()->json(['message' => 'Esta venda não tem itens fiscais.'], 422);
        }

        if ($acao === 'emitida') {
            $v->nf_externa_status = 'emitida';
            $v->nf_externa_numero = mb_substr(trim((string) $request->input('numero', '')), 0, 30) ?: null;
            $v->nf_externa_valor = $div['fiscal'];
            $v->nf_externa_em = now();
            $v->nf_externa_usuario_id = get_id_user();
            $descricao = 'NF-e fiscal marcada como emitida'
                . ($v->nf_externa_numero ? ' (nº ' . $v->nf_externa_numero . ')' : '')
                . ' — R$ ' . number_format($div['fiscal'], 2, ',', '.');
        } else {
            $v->nf_externa_status = 'pendente';
            $v->nf_externa_numero = null;
            $v->nf_externa_valor = null;
            $v->nf_externa_em = null;
            $v->nf_externa_usuario_id = get_id_user();
            $descricao = 'NF-e fiscal voltou para pendente';
        }
        $v->save();

        $this->registrarAuditoriaVenda($v->id, 'nf_externa_' . $acao, $descricao, [
            'numero' => $v->nf_externa_numero,
            'valor_fiscal' => $div['fiscal'],
        ]);

        return response()->json([
            'ok' => true,
            'situacao' => $v->situacaoNfExterna($div),
            'numero' => $v->nf_externa_numero,
            'em' => $v->nf_externa_em ? __data_pt($v->nf_externa_em, 1) : null,
        ]);
    }

    /**
     * ADM: reverte o fechamento no caixa para permitir edição / exclusão / workflow novamente.
     */
    public function reabrirCaixa($id)
    {
        if (!$this->usuarioEhAdministrador()) {
            abort(403);
        }
        $v = Venda::findOrFail($id);
        if (!__valida_objeto($v)) {
            abort(403);
        }
        if (!$v->fechada_caixa) {
            return response()->json(['message' => 'Esta venda não está fechada no caixa.'], 400);
        }
        $v->fechada_caixa = false;
        $v->fechada_em = null;
        $v->fechada_por_usuario_id = null;
        $v->save();

        $this->registrarAuditoriaVenda(
            $v->id,
            'caixa_reaberta',
            'Venda reaberta no caixa (ADM) — edições permitidas novamente.',
            []
        );

        return response()->json(['ok' => true]);
    }

    private function registrarAuditoriaVenda(int $vendaId, string $acao, string $descricao, array $meta = []): void
    {
        VendaAuditoria::create([
            'empresa_id' => request()->empresa_id,
            'venda_id' => $vendaId,
            'usuario_id' => get_id_user(),
            'acao' => $acao,
            'descricao' => mb_substr($descricao, 0, 512),
            'meta' => !empty($meta) ? $meta : null,
        ]);
    }

    /**
     * @param \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection $itens
     */
    private function itensParaAuditoria($itens): array
    {
        $out = [];
        foreach ($itens as $i) {
            $out[] = [
                'produto_id' => (int)$i->produto_id,
                'nome' => optional($i->produto)->nome ?? ('#' . $i->produto_id),
                'quantidade' => (float)$i->quantidade,
                'valor_unit' => (float)$i->valor,
            ];
        }

        return $out;
    }

    private function itensDepoisFromRequest(Request $request): array
    {
        $out = [];
        for ($i = 0; $i < sizeof($request->produto_id); $i++) {
            $pid = (int)$request->produto_id[$i];
            $p = Produto::find($pid);
            $out[] = [
                'produto_id' => $pid,
                'nome' => $p ? $p->nome : ('#' . $pid),
                'quantidade' => (float)__convert_value_bd($request->quantidade[$i]),
                'valor_unit' => (float)__convert_value_bd($request->valor_unitario[$i]),
            ];
        }

        return $out;
    }

    private function labelStatusPedidoVenda(string $st): string
    {
        $map = [
            'aguardando_confirmacao' => 'Aguardando confirmação',
            'confirmado' => 'Confirmado',
            'em_separacao' => 'Em separação',
            'separado' => 'Separado',
            'alteracao_pendente' => 'Alteração pendente',
            'em_rota_entrega' => 'Em rota de entrega',
            'ocorrencia_entrega' => 'Ocorrência na entrega',
            'entregue' => 'Entregue',
            'cancelada' => 'Cancelada',
        ];

        return $map[$st] ?? $st;
    }

    /**
     * Agrupa linhas pelo mesmo produto e mesmo preço unitário (evita misturar linhas com preços diferentes).
     *
     * @return array<string, array{produto_id:int,nome:string,qtd:float,vu:float}>
     */
    private function agregarItensAuditoriaPorProdutoEValor(array $rows): array
    {
        $m = [];
        foreach ($rows as $r) {
            $pid = (int)$r['produto_id'];
            $vu = round((float)($r['valor_unit'] ?? 0), 4);
            $key = $pid . '|' . sprintf('%.4f', $vu);
            if (!isset($m[$key])) {
                $m[$key] = [
                    'produto_id' => $pid,
                    'nome' => $r['nome'] ?? ('#' . $pid),
                    'qtd' => 0.0,
                    'vu' => $vu,
                ];
            }
            $m[$key]['qtd'] += (float)$r['quantidade'];
        }

        return $m;
    }

    /**
     * @return string[]
     */
    private function linhasResumoAlteracaoItens(array $antes, array $depois): array
    {
        $aggAntes = $this->agregarItensAuditoriaPorProdutoEValor($antes);
        $aggDepois = $this->agregarItensAuditoriaPorProdutoEValor($depois);
        $keys = array_unique(array_merge(array_keys($aggAntes), array_keys($aggDepois)));
        sort($keys, SORT_STRING);
        $linhas = [];
        foreach ($keys as $key) {
            $a = $aggAntes[$key] ?? null;
            $d = $aggDepois[$key] ?? null;
            $nome = $this->nomeProdutoResumido($a['nome'] ?? $d['nome'] ?? 'produto');
            if ($a && !$d) {
                $linhas[] = 'Retirou: ' . $nome . ' — ' . $this->fmtQtdAuditoria($a['qtd']) . ' un.'
                    . ' (unit. R$ ' . __moeda($a['vu']) . ').';

                continue;
            }
            if (!$a && $d) {
                $linhas[] = 'Adicionou: ' . $nome . ' — ' . $this->fmtQtdAuditoria($d['qtd']) . ' un.'
                    . ' (unit. R$ ' . __moeda($d['vu']) . ').';

                continue;
            }
            if ($a && $d) {
                $dq = abs($a['qtd'] - $d['qtd']) > 0.0001;
                $dv = abs($a['vu'] - $d['vu']) > 0.0001;
                if ($dq) {
                    $linhas[] = 'Alterou quantidade de ' . $nome . ': de ' . $this->fmtQtdAuditoria($a['qtd'])
                        . ' para ' . $this->fmtQtdAuditoria($d['qtd']) . ' un. (unit. R$ ' . __moeda($d['vu']) . ').';
                }
                if ($dv) {
                    $linhas[] = 'Alterou preço unitário de ' . $nome . ': de R$ ' . __moeda($a['vu'])
                        . ' para R$ ' . __moeda($d['vu']) . '.';
                }
            }
        }

        return $linhas;
    }

    /**
     * @param array{desconto:float,acrescimo:float,frete:float} $antes
     * @param array{desconto:float,acrescimo:float,frete:float} $depois
     *
     * @return string[]
     */
    private function linhasResumoCabecalhoVenda(array $antes, array $depois): array
    {
        $linhas = [];
        $cmp = static function (float $x, float $y): bool {
            return abs($x - $y) > 0.009;
        };
        if ($cmp($antes['desconto'], $depois['desconto'])) {
            $linhas[] = 'Alterou desconto: de R$ ' . __moeda($antes['desconto']) . ' para R$ ' . __moeda($depois['desconto']) . '.';
        }
        if ($cmp($antes['acrescimo'], $depois['acrescimo'])) {
            $linhas[] = 'Alterou acréscimo: de R$ ' . __moeda($antes['acrescimo']) . ' para R$ ' . __moeda($depois['acrescimo']) . '.';
        }
        if ($cmp($antes['frete'], $depois['frete'])) {
            $linhas[] = 'Alterou frete: de R$ ' . __moeda($antes['frete']) . ' para R$ ' . __moeda($depois['frete']) . '.';
        }

        return $linhas;
    }

    /**
     * @param string[] $linhasItens
     * @param string[] $linhasCab
     */
    private function montarDescricaoAlteracaoVenda(array $linhasItens, array $linhasCab, bool $virouAlteracaoPendente, string $statusAntes): string
    {
        $partes = array_merge($linhasItens, $linhasCab);
        if ($partes === []) {
            $texto = 'Salvou a venda sem mudanças detectáveis nos itens nem em desconto/frete/acréscimo.';
        } else {
            $texto = implode("\n", $partes);
        }
        if ($virouAlteracaoPendente) {
            $texto .= "\n" . 'Pedido voltou para alteração pendente (estava: ' . $this->labelStatusPedidoVenda($statusAntes) . ').';
        }

        return $texto;
    }

    private function nomeProdutoResumido(string $nome): string
    {
        $nome = trim($nome);
        if ($nome === '') {
            return 'produto';
        }
        if (function_exists('mb_strlen') && mb_strlen($nome) > 48) {
            return mb_substr($nome, 0, 45) . '…';
        }
        if (strlen($nome) > 48) {
            return substr($nome, 0, 45) . '…';
        }

        return $nome;
    }

    private function fmtQtdAuditoria(float $q): string
    {
        if (abs($q - round($q)) < 0.0001) {
            return (string)(int)round($q);
        }

        return rtrim(rtrim(number_format($q, 4, ',', ''), '0'), ',') ?: '0';
    }

    /**
     * Após revertStock na exclusão da venda, registra estorno em alteracao_estoques (uma linha por produto).
     */
    private function registrarMovimentacoesExclusaoVenda(Venda $venda): void
    {
        $itens = $venda->itens;
        if ($itens->isEmpty()) {
            return;
        }

        $linhas = [];
        foreach ($itens as $i) {
            $linhas[] = [
                'produto_id' => $i->produto_id,
                'quantidade' => (float) __convert_value_bd($i->quantidade),
                'nome' => optional($i->produto)->nome ?? ('Produto #' . $i->produto_id),
                'valor_unit' => (float) __convert_value_bd($i->valor ?? 0),
            ];
        }

        $agrupadoQ = $this->agruparQtdPorProdutoAuditoria($linhas);
        $filialId = $venda->filial_id;

        foreach ($agrupadoQ as $pid => $info) {
            $q = (float) $info['quantidade'];
            if (abs($q) < 0.0001) {
                continue;
            }
            $pid = (int) $pid;

            $qEst = Estoque::where('produto_id', $pid);
            if ($filialId !== null && (int) $filialId > 0) {
                $qEst->where('filial_id', $filialId);
            }
            $estoqueNovo = (float) ($qEst->value('quantidade') ?? 0);
            $estoqueAnterior = $estoqueNovo - $q;

            $nome = $info['nome'];
            $obs = 'Exclusão venda #' . $venda->id . ' — ' . $this->nomeProdutoResumido($nome)
                . ': +' . $this->fmtQtdAuditoria($q) . ' un. ('
                . $this->fmtQtdAuditoria($estoqueAnterior) . ' → ' . $this->fmtQtdAuditoria($estoqueNovo) . ').';

            AlteracaoEstoque::create([
                'empresa_id' => $venda->empresa_id,
                'usuario_id' => get_id_user(),
                'produto_id' => $pid,
                'quantidade' => $q,
                'observacao' => mb_substr($obs, 0, 200),
                'tipo' => '1',
                'acao' => 'venda_exclusao_estorno',
                'origem' => 'venda',
                'origem_id' => $venda->id,
                'pedido_id' => $venda->id,
                'estoque_anterior' => $estoqueAnterior,
                'estoque_novo' => $estoqueNovo,
            ]);
        }
    }

    /**
     * Gera movimentações de estoque para ajustes na edição de venda.
     * Ex.: "Venda #4013: LATTAFA ASAD eram 2, estornou 1 e ficou 1."
     */
    private function registrarMovimentacoesEdicaoVenda(Venda $venda, array $itensAntes, array $itensDepois): void
    {
        $antes = $this->agruparQtdPorProdutoAuditoria($itensAntes);
        $depois = $this->agruparQtdPorProdutoAuditoria($itensDepois);
        $valorAntes = $this->agruparValorUnitPorProdutoAuditoria($itensAntes);
        $valorDepois = $this->agruparValorUnitPorProdutoAuditoria($itensDepois);
        $produtoIds = array_unique(array_merge(array_keys($antes), array_keys($depois)));

        foreach ($produtoIds as $pid) {
            $qAntes = (float) ($antes[$pid]['quantidade'] ?? 0);
            $qDepois = (float) ($depois[$pid]['quantidade'] ?? 0);
            if (abs($qAntes - $qDepois) < 0.0001) {
                continue;
            }

            $deltaEstoque = $qAntes - $qDepois; // >0 estorno (volta estoque), <0 saída adicional
            $filialId = $venda->filial_id;
            $qEst = Estoque::where('produto_id', $pid);
            if ($filialId !== null && (int) $filialId > 0) {
                $qEst->where('filial_id', $filialId);
            }
            $estoqueFinal = (float) ($qEst->value('quantidade') ?? 0);
            $estoqueAnterior = $estoqueFinal - $deltaEstoque;

            $nome = $antes[$pid]['nome'] ?? $depois[$pid]['nome'] ?? ('Produto #' . $pid);
            $qMudou = abs($qAntes - $qDepois);
            $valorUnit = $valorDepois[$pid] ?? $valorAntes[$pid] ?? 0.0;
            if ($deltaEstoque > 0) {
                $obs = 'Venda #' . $venda->id . ' | ' . $nome
                    . ': eram ' . $this->fmtQtdAuditoria($qAntes)
                    . ', estornou ' . $this->fmtQtdAuditoria($qMudou)
                    . ', ficou ' . $this->fmtQtdAuditoria($qDepois)
                    . ' | novo estoque: ' . $this->fmtQtdAuditoria($estoqueFinal) . ' un.'
                    . ' | vl unit R$ ' . __moeda($valorUnit);
                $tipo = '1';
                $acao = 'edicao_venda_estorno';
            } else {
                $obs = 'Venda #' . $venda->id . ' | ' . $nome
                    . ': eram ' . $this->fmtQtdAuditoria($qAntes)
                    . ', adicionou ' . $this->fmtQtdAuditoria($qMudou)
                    . ', ficou ' . $this->fmtQtdAuditoria($qDepois)
                    . ' | novo estoque: ' . $this->fmtQtdAuditoria($estoqueFinal) . ' un.'
                    . ' | vl unit R$ ' . __moeda($valorUnit);
                $tipo = '2';
                $acao = 'edicao_venda_saida';
            }

            AlteracaoEstoque::create([
                'empresa_id' => $venda->empresa_id,
                'usuario_id' => get_id_user(),
                'produto_id' => (int) $pid,
                'quantidade' => $qMudou,
                'observacao' => mb_substr($obs, 0, 200),
                'tipo' => $tipo,
                'acao' => $acao,
                'origem' => 'venda',
                'origem_id' => $venda->id,
                'pedido_id' => $venda->id,
                'estoque_anterior' => $estoqueAnterior,
                'estoque_novo' => $estoqueFinal,
            ]);
        }
    }

    /**
     * @return array<int, array{quantidade:float,nome:string}>
     */
    private function agruparQtdPorProdutoAuditoria(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $pid = (int) ($r['produto_id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            if (!isset($out[$pid])) {
                $out[$pid] = [
                    'quantidade' => 0.0,
                    'nome' => (string) ($r['nome'] ?? ('Produto #' . $pid)),
                ];
            }
            $out[$pid]['quantidade'] += (float) ($r['quantidade'] ?? 0);
        }

        return $out;
    }

    /**
     * Média ponderada do valor unitário por produto.
     *
     * @return array<int, float>
     */
    private function agruparValorUnitPorProdutoAuditoria(array $rows): array
    {
        $sumQtd = [];
        $sumValor = [];
        foreach ($rows as $r) {
            $pid = (int) ($r['produto_id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            $q = (float) ($r['quantidade'] ?? 0);
            $vu = (float) ($r['valor_unit'] ?? 0);
            $sumQtd[$pid] = ($sumQtd[$pid] ?? 0) + $q;
            $sumValor[$pid] = ($sumValor[$pid] ?? 0) + ($q * $vu);
        }
        $out = [];
        foreach ($sumQtd as $pid => $qTot) {
            if (abs((float) $qTot) < 0.0001) {
                continue;
            }
            $out[$pid] = ((float) ($sumValor[$pid] ?? 0)) / (float) $qTot;
        }

        return $out;
    }
}
