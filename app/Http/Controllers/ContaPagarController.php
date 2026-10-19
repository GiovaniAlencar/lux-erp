<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CategoriaConta;
use App\Models\ContaPagar;
use App\Models\ConfigNota;
use App\Models\Fornecedor;
use Illuminate\Support\Facades\DB;

class ContaPagarController extends Controller
{
    public function index(Request $request)
    {
        $fornecedores = Fornecedor::where('empresa_id', request()->empresa_id)->get();
        $start_date = $request->get('start_date');
        $end_date = $request->get('end_date');
        $fornecedor_id = $request->get('fornecedor_id');
        $type_search = in_array($request->get('type_search'), ['created_at', 'data_vencimento', 'data_pagamento'], true)
            ? $request->get('type_search') : 'data_vencimento';
        $categoria_id = $request->get('categoria_id');
        $status = $request->get('status');
        $filial_id = $request->get('filial_id');
        $local_padrao = __get_local_padrao();
        if (!$filial_id && $local_padrao) {
            $filial_id = $local_padrao;
        }
        $data = ContaPagar::where('empresa_id', $request->empresa_id)
            ->when(!empty($start_date), function ($query) use ($start_date, $type_search) {
                return $query->whereDate($type_search, '>=', $start_date);
            })
            ->when(!empty($end_date), function ($query) use ($end_date, $type_search) {
                return $query->whereDate($type_search, '<=', $end_date);
            })
            ->when(!empty($fornecedor_id), function ($query) use ($fornecedor_id) {
                return $query->where('fornecedor_id', $fornecedor_id);
            })
            ->when($status != "", function ($query) use ($status) {
                return $query->where('status', $status);
            })
            ->when(!empty($categoria_id), fn ($query) => $query->where('categoria_id', (int) $categoria_id))
            ->when(in_array($request->get('despesa'), ['0', '1'], true) && self::temDespesa(), fn ($query) => $query->where('despesa', (int) $request->get('despesa')))
            ->when($filial_id != 'todos', function ($query) use ($filial_id) {
                $filial_id = $filial_id == -1 ? null : $filial_id;
                return $query->where('filial_id', $filial_id);
            })
            ->orderBy('data_vencimento', 'asc')
            ->paginate(env("PAGINACAO"));
        $categorias = $this->categoriasPagar($request->empresa_id);
        $contasFin = \App\Models\FinConta::daEmpresa($request->empresa_id);
        return view('conta_pagar.index', compact('data', 'fornecedores', 'filial_id', 'categorias', 'contasFin'));
    }

    /**
     * Categorias da LUX (aparecem primeiro; criadas na primeira vez) e se entram como DESPESA
     * no fechamento do mês. Entregas = repasse do frete cobrado no pedido; Compras = estoque/custo.
     */
    public const CATEGORIAS_PADRAO = [
        'Perfumes quebrados/faltando' => true,
        'Entregas' => false,
        'Compras' => false,
        'Mídias' => true,
        'Taxas de pagamento' => true,
        'Diferenças/juros' => true,
        'Despesas' => true,
    ];

    /** O SQL 29 (marca de despesa) já foi aplicado? */
    public static function temDespesa(): bool
    {
        static $ok = null;
        return $ok ??= \Illuminate\Support\Facades\Schema::hasColumn('conta_pagars', 'despesa')
            && \Illuminate\Support\Facades\Schema::hasColumn('categoria_contas', 'despesa');
    }

    private function categoriasPagar($empresaId)
    {
        foreach (self::CATEGORIAS_PADRAO as $nome => $ehDespesa) {
            CategoriaConta::firstOrCreate(
                ['empresa_id' => $empresaId, 'tipo' => 'pagar', 'nome' => $nome],
                self::temDespesa() ? ['despesa' => $ehDespesa] : []
            );
        }
        $ordem = array_flip(array_map('mb_strtolower', array_keys(self::CATEGORIAS_PADRAO)));
        return CategoriaConta::where('empresa_id', $empresaId)->where('tipo', 'pagar')->get()
            ->sortBy(fn ($c) => [$ordem[mb_strtolower($c->nome)] ?? 99, mb_strtolower($c->nome)])->values();
    }

    public function create(Request $request)
    {
        $fornecedores = Fornecedor::where('empresa_id', $request->empresa_id)->orderBy('razao_social')->get(['id', 'razao_social']);
        $categorias = $this->categoriasPagar($request->empresa_id);
        $contasFin = \App\Models\FinConta::daEmpresa($request->empresa_id);
        return view('conta_pagar.create', compact('categorias', 'fornecedores', 'contasFin'));
    }

    public function edit(Request $request, $id)
    {
        $item = ContaPagar::findOrFail($id);
        if (!__valida_objeto($item)) {
            abort(403);
        }
        $fornecedores = Fornecedor::where('empresa_id', $request->empresa_id)->get();
        $categorias = CategoriaConta::where('empresa_id', $request->empresa_id)
            ->where('tipo', 'pagar')
            ->orderBy('nome')
            ->get();
        return view('conta_pagar.edit', compact('categorias', 'item', 'fornecedores'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'referencia' => 'required|string|max:255',
            'categoria_id' => 'required_without:categoria_nova|nullable|integer',
            'categoria_nova' => 'nullable|string|max:60',
            'valor_integral' => 'required|string|max:20',
            'data_vencimento' => 'required|date',
            'fornecedor_id' => 'nullable|integer',
            'tipo_pagamento' => 'nullable|in:' . implode(',', array_keys(ContaPagar::tiposPagamento())),
            'repetir' => 'nullable|integer|min:0|max:24',
            'paga' => 'nullable|boolean',
            'fin_conta_id' => 'required_if:paga,1|nullable|integer',
            'data_pagamento' => 'nullable|date|before_or_equal:today',
            'comprovante' => 'nullable|file|mimes:' . \App\Services\Financeiro::COMPROVANTE_MIMES . '|max:' . \App\Services\Financeiro::COMPROVANTE_MAX_KB,
        ], [
            'referencia.required' => 'Escreva a descrição da conta.',
            'categoria_id.required_without' => 'Escolha a categoria.',
            'valor_integral.required' => 'Informe o valor.',
            'data_vencimento.required' => 'Informe o vencimento.',
            'fin_conta_id.required_if' => 'Escolha de qual conta saiu o dinheiro.',
            'data_pagamento.before_or_equal' => 'A data do pagamento não pode ser no futuro.',
        ]);

        $empresa = $request->empresa_id;
        $nova = trim(strip_tags((string) $request->categoria_nova));
        $categoria = $nova !== ''
            ? CategoriaConta::firstOrCreate(['empresa_id' => $empresa, 'tipo' => 'pagar', 'nome' => mb_substr($nova, 0, 60)],
                self::temDespesa() ? ['despesa' => $request->boolean('despesa', true)] : [])
            : CategoriaConta::where('empresa_id', $empresa)->where('tipo', 'pagar')->find((int) $request->categoria_id);
        $valor = \App\Services\Financeiro::valorBr($request->valor_integral);
        if (!$categoria || $valor <= 0) {
            return back()->withInput()->with('flash_erro', !$categoria ? 'Categoria inválida.' : 'Informe um valor maior que zero.');
        }
        $fornecedorId = $request->fornecedor_id
            ? (int) optional(Fornecedor::where('empresa_id', $empresa)->find((int) $request->fornecedor_id))->id
            : 0;
        $paga = $request->boolean('paga');
        $contaFin = $paga ? \App\Models\FinConta::where('empresa_id', $empresa)->where('ativo', 1)->find((int) $request->fin_conta_id) : null;
        if ($paga && !$contaFin) {
            return back()->withInput()->with('flash_erro', 'Escolha de qual conta saiu o dinheiro.');
        }
        $repetir = (int) $request->input('repetir', 0);
        $filial = $request->filial_id && $request->filial_id != -1 ? $request->filial_id : null;

        try {
            $msg = DB::transaction(function () use ($request, $empresa, $categoria, $valor, $fornecedorId, $paga, $contaFin, $repetir, $filial) {
                $venc = \Carbon\Carbon::parse($request->data_vencimento);
                $base = [
                    'compra_id' => null,
                    'valor_integral' => $valor,
                    'valor_pago' => 0,
                    'status' => 0,
                    'referencia' => mb_substr(trim($request->referencia), 0, 255),
                    'categoria_id' => $categoria->id,
                    'empresa_id' => $empresa,
                    'fornecedor_id' => $fornecedorId,
                    'tipo_pagamento' => $request->tipo_pagamento ?: null,
                    'filial_id' => $filial,
                ];
                if (self::temDespesa()) {
                    // marcado na tela (vem com o padrão da categoria; a pessoa pode trocar)
                    $base['despesa'] = $request->has('despesa') ? $request->boolean('despesa') : (bool) ($categoria->despesa ?? true);
                }
                $primeira = ContaPagar::create($base + [
                    'data_vencimento' => $venc->format('Y-m-d'),
                    'data_pagamento' => $venc->format('Y-m-d'),
                ]);
                // repetir nos próximos meses (mesmo dia; em mês mais curto, vai para o último dia)
                for ($m = 1; $m <= $repetir; $m++) {
                    $d = $venc->copy()->addMonthsNoOverflow($m)->format('Y-m-d');
                    ContaPagar::create($base + ['data_vencimento' => $d, 'data_pagamento' => $d]);
                }
                $txt = 'Conta de R$ ' . __moeda($valor) . ' cadastrada' . ($repetir ? ' (+' . $repetir . ' ' . ($repetir === 1 ? 'mês' : 'meses') . ')' : '') . '.';

                if ($paga) {
                    \App\Services\Financeiro::pagar($primeira, $valor, $request->data_pagamento ?: date('Y-m-d'), $contaFin, [
                        'comprovante' => \App\Services\Financeiro::salvarComprovante($request->file('comprovante'), $empresa),
                        'forma_pagamento' => $request->tipo_pagamento ?: null,
                    ]);
                    $txt .= ' Paga com saída em ' . $contaFin->nome . '.';
                }
                return $txt;
            });
            session()->flash('flash_sucesso', $msg);
        } catch (\Exception $e) {
            session()->flash('flash_erro', 'Algo deu errado: ' . $e->getMessage());
            __saveLogError($e, request()->empresa_id);
            return back()->withInput();
        }
        return redirect()->route('conta-pagar.index');
    }

    public function update(Request $request, $id)
    {
        $item = ContaPagar::findOrFail($id);
        try {
            $request->merge([
                'valor_integral' => \App\Services\Financeiro::valorBr($request->valor_integral),
                'filial_id' => $request->filial_id == -1 ? null : $request->filial_id
            ]);
            if ((float) $request->valor_integral + 0.009 < (float) $item->valor_pago) {
                return back()->with('flash_erro', 'O valor não pode ficar menor que o que já foi pago (R$ ' . __moeda($item->valor_pago) . ').');
            }
            $item->filial_id = $request->filial_id;
            // pago / valor pago só mudam pelo "Pagar" (que lança a saída no extrato)
            $item->fill($request->except(array_merge(['status', 'valor_pago', 'empresa_id'], self::temDespesa() ? [] : ['despesa'])))->save();
            \App\Services\Financeiro::recalcularTitulo($item->fresh());
            session()->flash("flash_sucesso", "Conta a pagar atualizada!");
        } catch (\Exception $e) {
            session()->flash("flash_erro", "Algo deu errado: " . $e->getMessage());
            __saveLogError($e, request()->empresa_id);
        }
        return redirect()->route('conta-pagar.index');
    }

    private function _validate(Request $request)
    {
        $rules = [
            'fornecedor_id' => 'required',
            'referencia' => 'required',
            'valor_integral' => 'required',
            'data_vencimento' => 'required',
        ];
        $messages = [
            'referencia.required' => 'O campo referencia é obrigatório.',
            'fornecedor_id.required' => 'O campo fornecedor é obrigatório.',
            'valor_integral.required' => 'O campo valor é obrigatório.',
            'data_vencimento.required' => 'O campo vencimento é obrigatório.'
        ];
        $this->validate($request, $rules, $messages);
    }

    public function destroy($id)
    {
        $item = ContaPagar::findOrFail($id);
        if (!__valida_objeto($item)) {
            abort(403);
        }
        if (\App\Models\FinMovimento::where('conta_pagar_id', $item->id)->exists()) {
            session()->flash('flash_erro', 'Esta conta já tem pagamento registrado. Desfaça os pagamentos no extrato (Financeiro) antes de excluir.');
            return redirect()->route('conta-pagar.index');
        }
        try {
            $item->delete();
            session()->flash("flash_sucesso", "Conta removida!");
        } catch (\Exception $e) {
            session()->flash("flash_erro", "Algo deu errado: " . $e->getMessage());
            __saveLogError($e, request()->empresa_id);
        }
        return redirect()->route('conta-pagar.index');
    }

    public function pay($id)
    {
        $item = ContaPagar::findOrFail($id);
        if (!__valida_objeto($item)) {
            abort(403);
        }
        $contas = \App\Models\FinConta::daEmpresa($item->empresa_id);
        $baixas = \App\Models\FinMovimento::with('conta', 'usuario')
            ->where('conta_pagar_id', $item->id)->orderBy('data')->orderBy('id')->get();
        $restante = \App\Services\Financeiro::restante($item);
        return view('conta_pagar.pay', compact('item', 'contas', 'baixas', 'restante'));
    }

    /** Baixa total ou parcial, na conta escolhida, com comprovante opcional. */
    public function payPut(Request $request, $id)
    {
        $item = ContaPagar::findOrFail($id);
        if (!__valida_objeto($item)) {
            abort(403);
        }
        $request->validate([
            'valor_pago' => 'required|string|max:20',
            'data_pagamento' => 'required|date',
            'conta_id' => 'required|integer',
            'tipo_pagamento' => 'nullable|string|max:30',
            'comprovante' => 'nullable|file|mimes:' . \App\Services\Financeiro::COMPROVANTE_MIMES . '|max:' . \App\Services\Financeiro::COMPROVANTE_MAX_KB,
        ], [
            'conta_id.required' => 'Escolha de qual conta saiu o pagamento.',
            'comprovante.mimes' => 'Comprovante: envie foto (JPG/PNG) ou PDF.',
            'comprovante.max' => 'Comprovante muito grande (máx. 8 MB).',
        ]);
        $conta = \App\Models\FinConta::where('empresa_id', $item->empresa_id)->where('ativo', 1)->findOrFail((int) $request->conta_id);
        $valor = \App\Services\Financeiro::valorBr($request->valor_pago);
        try {
            \App\Services\Financeiro::pagar($item, $valor, $request->data_pagamento, $conta, [
                'forma_pagamento' => $request->tipo_pagamento,
                'comprovante' => \App\Services\Financeiro::salvarComprovante($request->file('comprovante'), $item->empresa_id),
            ]);
            $item->refresh();
            $falta = \App\Services\Financeiro::restante($item);
            session()->flash('flash_sucesso', $item->status
                ? 'Conta quitada! Pagamento de R$ ' . __moeda($valor) . ' saiu de ' . $conta->nome . '.'
                : 'Pagamento parcial de R$ ' . __moeda($valor) . ' registrado. Falta R$ ' . __moeda($falta) . '.');
        } catch (\Throwable $e) {
            session()->flash('flash_erro', 'Não foi possível registrar: ' . $e->getMessage());
            __saveLogError($e, request()->empresa_id);
            return back()->withInput();
        }
        return $item->status ? redirect()->route('conta-pagar.index') : redirect()->route('conta-pagar.pay', $item->id);
    }
}
