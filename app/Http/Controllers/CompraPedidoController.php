<?php

namespace App\Http\Controllers;

use App\Models\CompraPedido;
use App\Models\CompraPedidoCusto;
use App\Models\CompraPedidoItem;
use App\Models\CompraPedidoPendencia;
use App\Models\FinConta;
use App\Models\FinMovimento;
use App\Models\Fornecedor;
use App\Models\Produto;
use App\Services\Financeiro;
use App\Services\PedidoCompra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Pedidos de compra (Compras › Pedidos de compra): rascunho → pagamentos → fretes → entrada → pendências.
 * Mexer em dinheiro (pagamentos, fretes, reembolsos) e dar entrada: só administrador.
 */
class CompraPedidoController extends Controller
{
    private function exigeAtivo(): void
    {
        if (!PedidoCompra::ativo()) {
            abort(redirect('/')->with('flash_erro', 'Rode o SQL 33 (pedidos de compra) para usar esta tela.'));
        }
    }

    private function exigeAdm(): void
    {
        $u = session('user_logged');
        if (!($u['adm'] ?? false) && empty($u['super'])) {
            abort(403, 'Somente administradores lançam pagamentos e dão entrada nos pedidos de compra.');
        }
    }

    private function usuarioId(): ?int
    {
        return (int) (session('user_logged')['id'] ?? 0) ?: null;
    }

    private function pedido(Request $request, $id): CompraPedido
    {
        return CompraPedido::where('empresa_id', $request->empresa_id)->findOrFail((int) $id);
    }

    public function index(Request $request)
    {
        $this->exigeAtivo();
        $status = $request->get('status', 'abertos');
        $q = CompraPedido::with(['itens', 'custos', 'pendencias', 'fornecedor'])->where('empresa_id', $request->empresa_id);
        if ($status === 'abertos') {
            $q->whereIn('status', ['rascunho', 'pedido']);
        } elseif ($status === 'pendencias') {
            $q->whereHas('pendencias', fn ($w) => $w->where('status', 'aberta'));
        } elseif (isset(CompraPedido::STATUS[$status])) {
            $q->where('status', $status);
        }
        if ($request->filled('busca')) {
            $b = '%' . str_replace(['%', '_'], ['\%', '\_'], trim($request->busca)) . '%';
            $q->where(fn ($w) => $w->where('fornecedor_nome', 'like', $b)->orWhereHas('fornecedor', fn ($f) => $f->where('razao_social', 'like', $b)->orWhere('nome_fantasia', 'like', $b))->orWhere('observacao', 'like', $b));
        }
        $pedidos = $q->orderByRaw("FIELD(status, 'rascunho', 'pedido', 'recebido', 'cancelado')")->orderByDesc('id')->paginate(30)->withQueryString();
        $resumos = $pedidos->getCollection()->mapWithKeys(fn ($p) => [$p->id => PedidoCompra::resumo($p)]);

        // cartões: tudo que está em aberto (não só a página)
        $abertos = CompraPedido::with(['itens', 'custos', 'pendencias'])->where('empresa_id', $request->empresa_id)->whereIn('status', ['rascunho', 'pedido'])->get();
        $cards = [
            'abertos' => $abertos->count(),
            'estimado' => round($abertos->sum(fn ($p) => PedidoCompra::resumo($p)['estimado_brl']), 2),
            'a_pagar' => round($abertos->sum(fn ($p) => PedidoCompra::resumo($p)['falta_pagar_brl']), 2),
            'pendencias' => round((float) CompraPedidoPendencia::whereHas('pedido', fn ($w) => $w->where('empresa_id', $request->empresa_id))->where('status', 'aberta')->sum('valor_brl'), 2),
        ];
        return view('compra_pedidos.index', compact('pedidos', 'resumos', 'status', 'cards'));
    }

    public function create(Request $request)
    {
        $this->exigeAtivo();
        $pedido = new CompraPedido(['moeda' => 'USD', 'status' => 'rascunho', 'data_pedido' => now()]);
        return $this->form($request, $pedido);
    }

    public function edit(Request $request, $id)
    {
        $this->exigeAtivo();
        $pedido = $this->pedido($request, $id)->load('itens.produto');
        if (in_array($pedido->status, ['recebido', 'cancelado'], true)) {
            return redirect()->route('compra-pedidos.show', $pedido->id)->with('flash_erro', 'Pedido recebido/cancelado não pode ser alterado.');
        }
        return $this->form($request, $pedido);
    }

    private function form(Request $request, CompraPedido $pedido)
    {
        $fornecedores = Fornecedor::where('empresa_id', $request->empresa_id)->orderBy('razao_social')->get(['id', 'razao_social', 'nome_fantasia']);
        $ultimo = CompraPedido::where('empresa_id', $request->empresa_id)->where('moeda', 'USD')->whereNotNull('cotacao_prevista')->latest('id')->first();
        return view('compra_pedidos.form', compact('pedido', 'fornecedores', 'ultimo'));
    }

    public function store(Request $request)
    {
        $this->exigeAtivo();
        $p = new CompraPedido(['empresa_id' => $request->empresa_id, 'usuario_id' => $this->usuarioId(), 'status' => 'rascunho']);
        return $this->salvar($request, $p);
    }

    public function update(Request $request, $id)
    {
        $this->exigeAtivo();
        $p = $this->pedido($request, $id);
        if (in_array($p->status, ['recebido', 'cancelado'], true)) {
            return back()->with('flash_erro', 'Pedido recebido/cancelado não pode ser alterado.');
        }
        return $this->salvar($request, $p);
    }

    private function salvar(Request $request, CompraPedido $p)
    {
        $request->validate([
            'fornecedor_id' => 'nullable|integer',
            'fornecedor_nome' => 'nullable|string|max:150',
            'moeda' => 'required|in:USD,BRL',
            'data_pedido' => 'nullable|date',
            'previsao_pagamento' => 'nullable|date',
            'observacao' => 'nullable|string|max:5000',
            'itens' => 'required|array|min:1',
            'itens.*.produto_id' => 'nullable|integer',
            'itens.*.descricao' => 'nullable|string|max:255',
        ], ['itens.required' => 'Coloque pelo menos um item no pedido.']);

        $itens = [];
        foreach ((array) $request->input('itens', []) as $l) {
            $qtd = Financeiro::valorBr($l['quantidade'] ?? '');
            $preco = Financeiro::valorBr($l['preco'] ?? '');
            $prodId = (int) ($l['produto_id'] ?? 0) ?: null;
            $desc = trim((string) ($l['descricao'] ?? ''));
            if ($qtd <= 0 || (!$prodId && $desc === '')) {
                continue;
            }
            if ($prodId && !Produto::where('empresa_id', $request->empresa_id)->whereKey($prodId)->exists()) {
                $prodId = null;
            }
            $itens[] = ['id' => (int) ($l['id'] ?? 0), 'produto_id' => $prodId, 'descricao' => $desc ?: null, 'quantidade' => $qtd, 'preco_moeda' => $preco];
        }
        if (!$itens) {
            return back()->withInput()->with('flash_erro', 'Coloque pelo menos um item com quantidade.');
        }
        $fornId = (int) $request->fornecedor_id ?: null;
        if ($fornId && !Fornecedor::where('empresa_id', $request->empresa_id)->whereKey($fornId)->exists()) {
            $fornId = null;
        }

        DB::transaction(function () use ($request, $p, $itens, $fornId) {
            $p->fill([
                'fornecedor_id' => $fornId,
                'fornecedor_nome' => $fornId ? null : ($request->fornecedor_nome ?: null),
                'moeda' => $request->moeda,
                'data_pedido' => $request->data_pedido ?: date('Y-m-d'),
                'previsao_pagamento' => $request->previsao_pagamento ?: null,
                'cotacao_prevista' => $request->moeda === 'USD' && $request->filled('cotacao_prevista') ? Financeiro::valorBr($request->cotacao_prevista) : null,
                'pct_frete_previsto' => $request->moeda === 'USD' && $request->filled('pct_frete_previsto') ? Financeiro::valorBr($request->pct_frete_previsto) : null,
                ...(\Illuminate\Support\Facades\Schema::hasColumn('compra_pedidos', 'pct_agente')
                    ? ['pct_agente' => $request->filled('pct_agente') ? min(100, max(0, Financeiro::valorBr($request->pct_agente))) : null] : []),
                'observacao' => $request->observacao,
            ]);
            if ($request->input('acao') === 'confirmar' && $p->status === 'rascunho') {
                $p->status = 'pedido';
            }
            $p->total_moeda = round(array_sum(array_map(fn ($i) => $i['quantidade'] * $i['preco_moeda'], $itens)) * $p->fatorAgente(), 2);
            $p->save();

            $manter = [];
            foreach ($itens as $i) {
                $item = $i['id'] ? CompraPedidoItem::where('pedido_id', $p->id)->find($i['id']) : null;
                $item = $item ?? new CompraPedidoItem(['pedido_id' => $p->id]);
                $item->fill(['produto_id' => $i['produto_id'], 'descricao' => $i['descricao'], 'quantidade' => $i['quantidade'], 'preco_moeda' => $i['preco_moeda']]);
                $item->pedido_id = $p->id;
                $item->save();
                $manter[] = $item->id;
            }
            CompraPedidoItem::where('pedido_id', $p->id)->whereNotIn('id', $manter)->delete();
        });

        return redirect()->route('compra-pedidos.show', $p->id)->with('flash_sucesso', $p->status === 'rascunho' ? 'Rascunho salvo.' : 'Pedido salvo.');
    }

    public function show(Request $request, $id)
    {
        $this->exigeAtivo();
        $pedido = $this->pedido($request, $id)->load(['itens.produto', 'custos.conta', 'pendencias.item', 'fornecedor']);
        $resumo = PedidoCompra::resumo($pedido);
        $custos = PedidoCompra::custosItens($pedido, $resumo);
        $contas = FinConta::daEmpresa($request->empresa_id);
        return view('compra_pedidos.show', compact('pedido', 'resumo', 'custos', 'contas'));
    }

    public function cancelar(Request $request, $id)
    {
        $this->exigeAtivo();
        $p = $this->pedido($request, $id);
        if ($p->status === 'recebido') {
            return back()->with('flash_erro', 'Pedido já recebido não pode ser cancelado.');
        }
        $p->status = 'cancelado';
        $p->save();
        return back()->with('flash_sucesso', 'Pedido cancelado. Pagamentos já lançados continuam no extrato (estorne lá se for o caso).');
    }

    public function destroy(Request $request, $id)
    {
        $this->exigeAtivo();
        $p = $this->pedido($request, $id);
        if ($p->status !== 'rascunho' || $p->custos()->exists()) {
            return back()->with('flash_erro', 'Só dá para excluir rascunho sem pagamentos.');
        }
        DB::transaction(function () use ($p) {
            $p->itens()->delete();
            $p->pendencias()->delete();
            $p->delete();
        });
        return redirect()->route('compra-pedidos.index')->with('flash_sucesso', 'Rascunho excluído.');
    }

    public function custo(Request $request, $id)
    {
        $this->exigeAtivo();
        $this->exigeAdm();
        $p = $this->pedido($request, $id);
        if ($p->status === 'cancelado') {
            return back()->with('flash_erro', 'Pedido cancelado.');
        }
        $request->validate([
            'tipo' => 'required|in:' . implode(',', array_keys(CompraPedidoCusto::TIPOS)),
            'data' => 'required|date',
            'fin_conta_id' => 'nullable|integer',
            'descricao' => 'nullable|string|max:255',
        ]);
        $valorMoeda = $request->filled('valor_moeda') ? Financeiro::valorBr($request->valor_moeda) : null;
        $cotacao = $request->filled('cotacao') ? Financeiro::valorBr($request->cotacao) : null;
        $pct = $request->filled('pct_frete') ? Financeiro::valorBr($request->pct_frete) : null;
        $brl = Financeiro::valorBr($request->valor_brl);
        if ($request->tipo === 'pagamento' && $p->moeda === 'USD') {
            if (!$valorMoeda || $valorMoeda <= 0) {
                return back()->with('flash_erro', 'Informe quanto do pedido (em dólar) foi pago.');
            }
            if ($brl <= 0 && $cotacao) {
                $brl = round($valorMoeda * $cotacao * (1 + (float) $pct / 100), 2);
            }
        } elseif ($request->tipo === 'pagamento') {
            $valorMoeda = $brl; // pedido em real
        }
        if ($brl <= 0) {
            return back()->with('flash_erro', 'Informe o valor em reais que saiu da conta.');
        }
        try {
            PedidoCompra::lancarCusto($p, [
                'tipo' => $request->tipo, 'data' => $request->data, 'valor_moeda' => $valorMoeda, 'cotacao' => $cotacao,
                'pct_frete' => $pct, 'valor_brl' => $brl, 'descricao' => $request->descricao, 'fin_conta_id' => $request->fin_conta_id,
            ], $this->usuarioId());
        } catch (\Throwable $e) {
            return back()->with('flash_erro', 'Não foi possível lançar: ' . $e->getMessage());
        }
        return back()->with('flash_sucesso', (CompraPedidoCusto::TIPOS[$request->tipo] ?? 'Custo') . ' de R$ ' . __moeda($brl) . ' lançado' . ($request->fin_conta_id ? ' e debitado da conta' : '') . '.');
    }

    public function excluirCusto(Request $request, $id, $custoId)
    {
        $this->exigeAtivo();
        $this->exigeAdm();
        $p = $this->pedido($request, $id);
        $c = CompraPedidoCusto::where('pedido_id', $p->id)->findOrFail((int) $custoId);
        PedidoCompra::excluirCusto($c);
        return back()->with('flash_sucesso', 'Lançamento excluído (e estornado da conta).');
    }

    public function entrada(Request $request, $id)
    {
        $this->exigeAtivo();
        $this->exigeAdm();
        $p = $this->pedido($request, $id);
        if (!in_array($p->status, ['rascunho', 'pedido'], true)) {
            return back()->with('flash_erro', 'Este pedido não está aguardando entrega.');
        }
        $receb = [];
        $num = fn ($v) => trim((string) $v) !== '' ? Financeiro::valorBr($v) : null;
        foreach ((array) $request->input('receb', []) as $itemId => $l) {
            $receb[(int) $itemId] = [
                'qtd' => Financeiro::valorBr($l['qtd'] ?? '0'),
                'produto_id' => (int) ($l['produto_id'] ?? 0) ?: null,
                'novo_custo' => $num($l['novo_custo'] ?? ''),
                'precos' => array_filter([
                    'valor_venda' => $num($l['novo_preco'] ?? ''),
                    'preco_2' => $num($l['novo_preco_2'] ?? ''),
                    'preco_3' => $num($l['novo_preco_3'] ?? ''),
                ], fn ($v) => $v !== null && $v > 0),
            ];
        }
        try {
            $r = PedidoCompra::darEntrada($p, $receb, $request->input('modo', 'estoque') === 'estoque', $this->usuarioId());
        } catch (\Throwable $e) {
            return back()->with('flash_erro', 'Não foi possível dar entrada: ' . $e->getMessage());
        }
        $msg = 'Pedido recebido' . ($request->input('modo', 'estoque') === 'estoque' ? ' e lançado no estoque com o custo final.' : '. Lembre de dar entrada pelo XML da nota.')
            . ($r['faltas'] ? ' ' . $r['faltas'] . ' item(ns) com falta viraram pendência.' : '');
        $res = redirect()->route('compra-pedidos.show', $p->id)->with('flash_sucesso', $msg);
        if ($r['avisos']) {
            $res->with('flash_erro', implode(' ', $r['avisos']));
        }
        return $res;
    }

    /** Conferência de chegada: quantidade, custo médio novo e preços (igual à entrada pelo XML). */
    public function entradaForm(Request $request, $id)
    {
        $this->exigeAtivo();
        $this->exigeAdm();
        $pedido = $this->pedido($request, $id)->load(['itens.produto', 'custos', 'pendencias', 'fornecedor']);
        if (!in_array($pedido->status, ['rascunho', 'pedido'], true)) {
            return redirect()->route('compra-pedidos.show', $pedido->id)->with('flash_erro', 'Este pedido não está aguardando entrega.');
        }
        $resumo = PedidoCompra::resumo($pedido);
        $custos = PedidoCompra::custosItens($pedido, $resumo);
        $estoques = DB::table('estoques')->whereIn('produto_id', $pedido->itens->pluck('produto_id')->filter())->whereNull('filial_id')
            ->pluck('quantidade', 'produto_id');
        return view('compra_pedidos.entrada', compact('pedido', 'resumo', 'custos', 'estoques'));
    }

    public function pendencia(Request $request, $id)
    {
        $this->exigeAtivo();
        $p = $this->pedido($request, $id);
        $request->validate([
            'tipo' => 'required|in:' . implode(',', array_keys(CompraPedidoPendencia::TIPOS)),
            'descricao' => 'required|string|max:255',
            'item_id' => 'nullable|integer',
        ]);
        $item = $request->item_id ? CompraPedidoItem::where('pedido_id', $p->id)->find((int) $request->item_id) : null;
        $qtd = $request->filled('quantidade') ? Financeiro::valorBr($request->quantidade) : null;
        $brl = $request->filled('valor_brl') ? Financeiro::valorBr($request->valor_brl) : null;
        if ($brl === null && $item && $qtd) {
            $brl = round($qtd * (float) ($item->custo_final ?: PedidoCompra::custosItens($p)[$item->id] ?? 0), 2);
        }
        CompraPedidoPendencia::create([
            'pedido_id' => $p->id,
            'item_id' => $item?->id,
            'tipo' => $request->tipo,
            'descricao' => $request->descricao,
            'quantidade' => $qtd,
            'valor_moeda' => $request->filled('valor_moeda') ? Financeiro::valorBr($request->valor_moeda) : ($item && $qtd ? round($qtd * $item->preco_moeda, 2) : null),
            'valor_brl' => $brl,
            'status' => 'aberta',
            'usuario_id' => $this->usuarioId(),
        ]);
        return back()->with('flash_sucesso', 'Pendência registrada.');
    }

    /** Resolve a pendência: chegou (entra no estoque), reembolso (entrada na conta) ou só anotar (abatido/perda). */
    public function resolverPendencia(Request $request, $id, $pendId)
    {
        $this->exigeAtivo();
        $this->exigeAdm();
        $p = $this->pedido($request, $id);
        $pend = CompraPedidoPendencia::where('pedido_id', $p->id)->where('status', 'aberta')->findOrFail((int) $pendId);
        $request->validate(['modo' => 'required|in:chegou,reembolso,abatido,perda', 'obs' => 'nullable|string|max:200']);
        $modo = $request->modo;

        try {
            DB::transaction(function () use ($request, $p, $pend, $modo) {
                $texto = trim((string) $request->obs);
                if ($modo === 'chegou') {
                    $item = $pend->item;
                    $qtd = $request->filled('quantidade') ? Financeiro::valorBr($request->quantidade) : (float) $pend->quantidade;
                    if (!$item || !$item->produto_id || $qtd <= 0) {
                        throw new \InvalidArgumentException('Esta pendência não tem produto/quantidade para entrar no estoque.');
                    }
                    $prod = Produto::where('empresa_id', $p->empresa_id)->lockForUpdate()->findOrFail($item->produto_id);
                    PedidoCompra::entrarEstoque($prod, $qtd, (float) $item->custo_final);
                    $item->qtd_recebida = (float) $item->qtd_recebida + $qtd;
                    $item->save();
                    $texto = 'Chegou depois: ' . rtrim(rtrim(number_format($qtd, 2, ',', ''), '0'), ',') . ' un. no estoque' . ($texto ? ' · ' . $texto : '');
                } elseif ($modo === 'reembolso') {
                    $conta = FinConta::where('empresa_id', $p->empresa_id)->findOrFail((int) $request->fin_conta_id);
                    $valor = Financeiro::valorBr($request->valor);
                    if ($valor <= 0) {
                        throw new \InvalidArgumentException('Informe o valor reembolsado.');
                    }
                    FinMovimento::create([
                        'empresa_id' => $p->empresa_id,
                        'conta_id' => $conta->id,
                        'tipo' => 'entrada',
                        'valor' => $valor,
                        'data' => $request->data ?: date('Y-m-d'),
                        'descricao' => mb_substr('Reembolso do fornecedor · pedido de compra #' . $p->id . ' · ' . $pend->descricao, 0, 255),
                        'origem' => 'compra',
                        'categoria_id' => PedidoCompra::categoriaCompras($p->empresa_id),
                        'usuario_id' => $this->usuarioId(),
                    ]);
                    $texto = 'Reembolsado R$ ' . __moeda($valor) . ' em ' . $conta->nome . ($texto ? ' · ' . $texto : '');
                } elseif ($modo === 'abatido') {
                    $texto = 'Abatido em outro pedido' . ($texto ? ': ' . $texto : '');
                } else {
                    $texto = 'Perda (não vai voltar)' . ($texto ? ': ' . $texto : '');
                }
                $pend->status = 'resolvida';
                $pend->resolucao = mb_substr($texto, 0, 255);
                $pend->resolvido_em = now();
                $pend->save();
            });
        } catch (\Throwable $e) {
            return back()->with('flash_erro', $e->getMessage());
        }
        return back()->with('flash_sucesso', 'Pendência resolvida.');
    }

    /** Busca de produtos para os itens do pedido. */
    public function produtos(Request $request)
    {
        $t = trim((string) $request->q);
        $q = Produto::where('empresa_id', $request->empresa_id)->where('inativo', 0)->with('estoque');
        if ($t !== '') {
            $q->where(fn ($w) => ctype_digit($t) ? $w->where('id', (int) $t)->orWhere('nome', 'like', "%$t%") : $w->where('nome', 'like', "%$t%"));
        }
        return response()->json($q->orderBy('nome')->limit(30)->get()->map(fn ($p) => [
            'id' => $p->id,
            'text' => $p->nome,
            'custo' => (float) $p->valor_compra,
            'estoque' => (float) optional($p->estoque)->quantidade,
            'preco' => (float) $p->valor_venda,
            'preco_2' => $p->preco_2 !== null ? (float) $p->preco_2 : null,
            'preco_3' => $p->preco_3 !== null ? (float) $p->preco_3 : null,
        ]));
    }
}
