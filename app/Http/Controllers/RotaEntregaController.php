<?php

namespace App\Http\Controllers;

use App\Helpers\RotaEntregaHelper;
use App\Helpers\EcommerceSync;
use App\Models\RotaEntrega;
use App\Models\RotaEntregaItem;
use App\Models\Venda;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RotaEntregaController extends Controller
{
    public function index(Request $request)
    {
        $start_date = $request->get('start_date');
        $end_date = $request->get('end_date');

        $rotas = RotaEntrega::with(['usuario', 'itens', 'motoboyPagoPor'])
            ->where('empresa_id', $request->empresa_id)
            ->when(!empty($start_date), function ($query) use ($start_date) {
                return $query->whereDate('created_at', '>=', $start_date);
            })
            ->when(!empty($end_date), function ($query) use ($end_date) {
                return $query->whereDate('created_at', '<=', $end_date);
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->appends($request->query());

        return view('rotas_entrega.index', compact('rotas', 'start_date', 'end_date'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'confirmar_alteracoes' => 'sometimes|boolean',
        ]);

        $ids = array_values(array_unique($request->input('ids', [])));
        $confirmarAlteracoes = (bool) $request->input('confirmar_alteracoes', false);
        $empresaId = (int) $request->empresa_id;

        $vendas = Venda::with(['cliente', 'duplicatas'])
            ->whereIn('id', $ids)
            ->where('empresa_id', $empresaId)
            ->get();

        if ($vendas->count() !== count($ids)) {
            return response()->json(['message' => 'Uma ou mais vendas não foram encontradas.'], 404);
        }

        $pendentes = $vendas->filter(fn ($v) => $v->status_pedido === 'alteracao_pendente');
        if ($pendentes->count() > 0 && !$confirmarAlteracoes) {
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

        $emOutraRota = RotaEntregaItem::with('rota')
            ->whereIn('venda_id', $ids)
            ->whereHas('rota', function ($q) use ($empresaId) {
                $q->where('empresa_id', $empresaId)
                    ->whereIn('status', ['rascunho', 'em_rota']);
            })
            ->get();

        if ($emOutraRota->isNotEmpty()) {
            $lista = $emOutraRota->map(function ($item) {
                return 'Pedido #' . $item->venda_id . ' já está na rota #' . $item->rota_entrega_id;
            })->unique()->values()->implode('; ');

            return response()->json(['message' => $lista], 409);
        }

        $rota = DB::transaction(function () use ($vendas, $empresaId) {
            $ordenadas = RotaEntregaHelper::ordenarVendas($vendas);

            $rota = RotaEntrega::create([
                'empresa_id' => $empresaId,
                'usuario_id' => get_id_user(),
                'status' => 'rascunho',
            ]);

            $ordem = 0;
            foreach ($ordenadas as $v) {
                $dados = RotaEntregaHelper::dadosItemFromVenda($v);
                RotaEntregaItem::create(array_merge($dados, [
                    'rota_entrega_id' => $rota->id,
                    'ordem' => $ordem++,
                ]));
            }

            return $rota;
        });

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'redirect' => route('rotas-entrega.show', $rota->id),
            ]);
        }

        return redirect()->route('rotas-entrega.show', $rota->id);
    }

    public function show($id)
    {
        $rota = RotaEntrega::with(['itens.venda', 'usuario', 'motoboyPagoPor'])
            ->where('empresa_id', request()->empresa_id)
            ->findOrFail($id);

        $tiposOcorrencia = RotaEntregaItem::tiposOcorrencia();
        $situacoesPagamento = RotaEntregaItem::situacoesPagamento();

        return view('rotas_entrega.show', compact('rota', 'tiposOcorrencia', 'situacoesPagamento'));
    }

    public function pedidosDisponiveis(Request $request, $id)
    {
        $rota = RotaEntrega::where('empresa_id', $request->empresa_id)->findOrFail($id);

        if (!in_array($rota->status, ['rascunho', 'em_rota'], true)) {
            return response()->json(['ok' => false, 'message' => 'Esta rota não pode mais ser editada.'], 423);
        }

        $q = trim((string) $request->get('q', ''));
        $clienteId = (int) $request->get('cliente_id', 0);
        $empresaId = (int) $request->empresa_id;
        $idsNaRota = $rota->itens()->pluck('venda_id')->all();
        $emOutraRotaIds = $this->vendasEmOutraRotaAtiva($empresaId, $rota->id);

        $query = Venda::with('cliente')
            ->where('empresa_id', $empresaId)
            ->where('estado_emissao', '!=', 'cancelado')
            ->where('status_pedido', '!=', 'cancelada')
            ->whereNotIn('status_pedido', ['entregue', 'cancelada'])
            ->where('fechada_caixa', false)
            ->whereNotIn('id', $idsNaRota)
            ->whereNotIn('id', $emOutraRotaIds);

        if ($clienteId > 0) {
            $query->where('cliente_id', $clienteId);
        }

        if ($q !== '') {
            if (preg_match('/^\d+$/', $q)) {
                $query->where('id', 'like', $q . '%');
            } else {
                $query->whereHas('cliente', function ($c) use ($q) {
                    $c->where('razao_social', 'like', '%' . $q . '%');
                });
            }
        }

        $labels = $this->labelsStatusPedido();
        $vendas = $query->orderByDesc('id')->limit(20)->get();

        return response()->json([
            'ok' => true,
            'pedidos' => $vendas->map(function ($v) use ($labels) {
                return [
                    'id' => $v->id,
                    'cliente_id' => $v->cliente_id,
                    'cliente' => optional($v->cliente)->razao_social ?? '—',
                    'bairro' => optional($v->cliente)->bairro ?? '',
                    'telefone' => RotaEntregaHelper::telefoneCliente($v),
                    'status' => $labels[$v->status_pedido] ?? $v->status_pedido,
                    'frete' => __moeda($v->frete ?? 0),
                    'alteracao_pendente' => $v->status_pedido === 'alteracao_pendente',
                ];
            })->values(),
        ]);
    }

    public function adicionarItens(Request $request, $id)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'confirmar_alteracoes' => 'sometimes|boolean',
        ]);

        $rota = RotaEntrega::where('empresa_id', $request->empresa_id)->findOrFail($id);

        if (!in_array($rota->status, ['rascunho', 'em_rota'], true)) {
            return $this->rotaJsonOuRedirect($request, false, 'Esta rota não pode mais ser editada.', 423);
        }

        $ids = array_values(array_unique($request->input('ids', [])));
        $confirmarAlteracoes = (bool) $request->input('confirmar_alteracoes', false);
        $empresaId = (int) $request->empresa_id;

        $idsNaRota = $rota->itens()->pluck('venda_id')->all();
        $jaNaRota = array_intersect($ids, $idsNaRota);
        if (!empty($jaNaRota)) {
            return $this->rotaJsonOuRedirect(
                $request,
                false,
                'Pedido(s) já estão nesta rota: #' . implode(', #', $jaNaRota) . '.',
                422
            );
        }

        $vendas = Venda::with(['cliente', 'duplicatas'])
            ->whereIn('id', $ids)
            ->where('empresa_id', $empresaId)
            ->get();

        if ($vendas->count() !== count($ids)) {
            return $this->rotaJsonOuRedirect($request, false, 'Uma ou mais vendas não foram encontradas.', 404);
        }

        $erro = $this->validarVendasParaInclusao($vendas, $empresaId, $rota->id, $confirmarAlteracoes);
        if ($erro !== null) {
            return $this->rotaJsonOuRedirect(
                $request,
                false,
                $erro['message'],
                $erro['status'],
                $erro['extra'] ?? []
            );
        }

        $novosItemIds = DB::transaction(function () use ($rota, $vendas, $empresaId) {
            $criados = [];
            foreach ($vendas as $v) {
                $dados = RotaEntregaHelper::dadosItemFromVenda($v);
                $item = RotaEntregaItem::create(array_merge($dados, [
                    'rota_entrega_id' => $rota->id,
                    'ordem' => 999,
                ]));
                $criados[] = $item->id;
            }

            RotaEntregaHelper::reordenarItensRota($rota);

            if ($rota->status === 'em_rota') {
                Venda::whereIn('id', $vendas->pluck('id'))
                    ->where('empresa_id', $empresaId)
                    ->where('estado_emissao', '!=', 'cancelado')
                    ->where('status_pedido', '!=', 'cancelada')
                    ->update(['status_pedido' => 'em_rota_entrega']);
                EcommerceSync::syncByVendaIds($vendas->pluck('id')->all(), false);
            }

            return $criados;
        });

        $rota = $rota->fresh(['itens.venda']);
        $editavel = true;
        $tiposOcorrencia = RotaEntregaItem::tiposOcorrencia();
        $situacoesPagamento = RotaEntregaItem::situacoesPagamento();

        $html = '';
        foreach ($rota->itens->whereIn('id', $novosItemIds) as $item) {
            $html .= view('rotas_entrega._item_card', [
                'rota' => $rota,
                'item' => $item,
                'editavel' => $editavel,
                'tiposOcorrencia' => $tiposOcorrencia,
                'situacoesPagamento' => $situacoesPagamento,
                'oi' => [],
            ])->render();
        }

        $ordem = $rota->itens->sortBy('ordem')->pluck('id')->values()->all();
        $msg = count($novosItemIds) === 1
            ? 'Pedido #' . $vendas->first()->id . ' adicionado à rota.'
            : count($novosItemIds) . ' pedidos adicionados à rota.';

        return $this->rotaJsonOuRedirect($request, true, $msg, 200, [
            'html' => $html,
            'ordem' => $ordem,
            'rota' => $this->dadosRotaJson($rota),
        ]);
    }

    public function salvar(Request $request, $id)
    {
        $rota = RotaEntrega::with(['itens.venda.cliente'])
            ->where('empresa_id', $request->empresa_id)
            ->findOrFail($id);

        if (!in_array($rota->status, ['rascunho', 'em_rota'], true)) {
            return $this->rotaJsonOuRedirect($request, false, 'Esta rota não pode mais ser editada.', 423);
        }

        if ($rota->itens->isEmpty()) {
            return $this->rotaJsonOuRedirect($request, false, 'A rota não possui entregas.', 422);
        }

        $request->validate([
            'motoboy_nome' => 'nullable|string|max:120',
            'itens' => 'required|array|min:1',
            'itens.*.telefone' => 'required|string|max:40',
            'itens.*.rua' => 'nullable|string|max:255',
            'itens.*.numero' => 'nullable|string|max:30',
            'itens.*.bairro' => 'nullable|string|max:120',
            'itens.*.complemento' => 'nullable|string|max:255',
            'itens.*.prioridade' => 'sometimes|boolean',
            'itens.*.observacao_prioridade' => 'nullable|string|max:500',
            'itens.*.aviso_entrega' => 'nullable|string|max:255',
            'itens.*.embalagem_tipo' => 'nullable|in:sacola,caixa',
            'itens.*.embalagem_qtd' => 'nullable|integer|min:1|max:999',
            'itens.*.confirmado_saida' => 'sometimes|boolean',
            'itens.*.mostrar_valor_pedido' => 'sometimes|boolean',
            'itens.*.situacao_pagamento' => 'required|in:pago,pagamento_entrega,pago_parcial',
            'itens.*.valor_restante' => 'nullable|string',
        ]);

        $itensInput = $request->input('itens', []);
        $semTelefone = [];

        foreach ($rota->itens as $item) {
            if (!isset($itensInput[$item->id])) {
                continue;
            }
            if (trim($itensInput[$item->id]['telefone'] ?? '') === '') {
                $semTelefone[] = '#' . $item->venda_id;
            }
        }

        if (!empty($semTelefone)) {
            $msg = 'Informe o telefone de todos os pedidos antes de salvar. Faltando: ' . implode(', ', $semTelefone) . '.';

            return $this->rotaJsonOuRedirect($request, false, $msg, 422);
        }

        DB::transaction(function () use ($rota, $request, $itensInput) {
            $rota->motoboy_nome = $request->input('motoboy_nome');
            $rota->status = 'em_rota';
            $rota->save();

            foreach ($rota->itens as $item) {
                if (!isset($itensInput[$item->id])) {
                    continue;
                }

                $raw = $itensInput[$item->id];
                $data = [
                    'telefone' => trim($raw['telefone'] ?? ''),
                    'rua' => $raw['rua'] ?? null,
                    'numero' => $raw['numero'] ?? null,
                    'bairro' => $raw['bairro'] ?? null,
                    'complemento' => $raw['complemento'] ?? null,
                    'prioridade' => filter_var($raw['prioridade'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'observacao_prioridade' => $raw['observacao_prioridade'] ?? null,
                    'aviso_entrega' => trim($raw['aviso_entrega'] ?? '') ?: null,
                    'embalagem_tipo' => $raw['embalagem_tipo'] ?? null,
                    'embalagem_qtd' => !empty($raw['embalagem_qtd']) ? (int) $raw['embalagem_qtd'] : null,
                    'confirmado_saida' => true,
                    'mostrar_valor_pedido' => filter_var($raw['mostrar_valor_pedido'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'situacao_pagamento' => $raw['situacao_pagamento'],
                    'valor_restante' => null,
                ];

                if (($raw['valor_restante'] ?? '') !== '') {
                    $data['valor_restante'] = (float) __convert_value_bd((string) $raw['valor_restante']);
                }

                if ($data['telefone'] !== '' && $item->venda && $item->venda->cliente) {
                    $cliente = $item->venda->cliente;
                    $cliente->celular = $data['telefone'];
                    $cliente->save();
                }

                $item->fill($data)->save();
                $this->sincronizarPagamentoVenda($item);
                $this->sincronizarAvisoVenda($item);
            }

            $vendaIds = $rota->fresh('itens')->itens->pluck('venda_id')->all();
            if (!empty($vendaIds)) {
                Venda::whereIn('id', $vendaIds)
                    ->where('empresa_id', $rota->empresa_id)
                    ->where('estado_emissao', '!=', 'cancelado')
                    ->where('status_pedido', '!=', 'cancelada')
                    ->update(['status_pedido' => 'em_rota_entrega']);
                EcommerceSync::syncByVendaIds($vendaIds, false);
            }
        });

        $rota = $rota->fresh('itens');

        return $this->rotaJsonOuRedirect($request, true, 'Rota salva. Os pedidos foram marcados como em rota de entrega.', 200, [
            'rota' => $this->dadosRotaJson($rota),
        ]);
    }

    public function updateItem(Request $request, $rotaId, $itemId)
    {
        $rota = RotaEntrega::where('empresa_id', request()->empresa_id)->findOrFail($rotaId);
        $item = RotaEntregaItem::where('rota_entrega_id', $rota->id)->findOrFail($itemId);

        if (!in_array($rota->status, ['rascunho', 'em_rota'], true)) {
            return response()->json(['message' => 'Esta rota não pode mais ser editada.'], 423);
        }

        $data = $request->validate([
            'telefone' => 'sometimes|nullable|string|max:40',
            'rua' => 'sometimes|nullable|string|max:255',
            'numero' => 'sometimes|nullable|string|max:30',
            'bairro' => 'sometimes|nullable|string|max:120',
            'complemento' => 'sometimes|nullable|string|max:255',
            'prioridade' => 'sometimes|boolean',
            'observacao_prioridade' => 'sometimes|nullable|string|max:500',
            'aviso_entrega' => 'sometimes|nullable|string|max:255',
            'embalagem_tipo' => 'sometimes|nullable|in:sacola,caixa',
            'embalagem_qtd' => 'sometimes|nullable|integer|min:1|max:999',
            'confirmado_saida' => 'sometimes|boolean',
            'mostrar_valor_pedido' => 'sometimes|boolean',
            'situacao_pagamento' => 'sometimes|in:pago,pagamento_entrega,pago_parcial',
            'valor_restante' => 'sometimes|nullable|numeric|min:0',
        ]);

        if (array_key_exists('valor_restante', $data) && $data['valor_restante'] !== null) {
            $data['valor_restante'] = is_numeric($data['valor_restante'])
                ? (float) $data['valor_restante']
                : (float) __convert_value_bd((string) $data['valor_restante']);
        }

        foreach (['prioridade', 'mostrar_valor_pedido', 'confirmado_saida'] as $boolField) {
            if (array_key_exists($boolField, $data)) {
                $data[$boolField] = filter_var($data[$boolField], FILTER_VALIDATE_BOOLEAN);
            }
        }

        if (isset($data['telefone'])) {
            $tel = trim($data['telefone']);
            $data['telefone'] = $tel;
            if ($tel !== '') {
                $item->load('venda.cliente');
                if ($item->venda && $item->venda->cliente) {
                    $cliente = $item->venda->cliente;
                    $cliente->celular = $tel;
                    $cliente->save();
                }
            }
        }

        $item->fill($data)->save();
        $this->sincronizarPagamentoVenda($item);
        $this->sincronizarAvisoVenda($item);

        return response()->json([
            'ok' => true,
            'item' => [
                'id' => $item->id,
                'prioridade' => (bool) $item->prioridade,
                'texto_pagamento' => $item->textoPagamentoMotoboy(),
            ],
            'total_frete' => $rota->fresh(['itens'])->totalFrete(),
        ]);
    }

    public function destroyItem(Request $request, $rotaId, $itemId)
    {
        $rota = RotaEntrega::with('itens')
            ->where('empresa_id', request()->empresa_id)
            ->findOrFail($rotaId);

        $item = RotaEntregaItem::where('rota_entrega_id', $rota->id)->findOrFail($itemId);

        if (!in_array($rota->status, ['rascunho', 'em_rota'], true)) {
            return $this->rotaJsonOuRedirect($request, false, 'Esta rota não pode mais ser editada.', 423);
        }

        if (in_array($item->status_entrega, ['entregue', 'ocorrencia'], true)) {
            return $this->rotaJsonOuRedirect($request, false, 'Não é possível remover um pedido já entregue ou com ocorrência.', 422);
        }

        $vendaIdRemovido = $item->venda_id;
        $itemIdRemovido = $item->id;

        DB::transaction(function () use ($item) {
            $item->load('venda');
            if ($item->venda && $item->venda->status_pedido === 'em_rota_entrega') {
                $item->venda->status_pedido = 'separado';
                $item->venda->save();
                EcommerceSync::syncFromVenda($item->venda, false);
            }
            $item->delete();
        });

        if ($rota->itens()->count() === 0) {
            $rota->delete();

            return $this->rotaJsonOuRedirect($request, true, 'Pedido removido. A rota ficou vazia e foi excluída.', 200, [
                'rota_vazia' => true,
                'redirect' => route('rotas-entrega.index'),
            ]);
        }

        $rota = $rota->fresh('itens');

        return $this->rotaJsonOuRedirect($request, true, 'Pedido #' . $vendaIdRemovido . ' removido da rota.', 200, [
            'item_id' => $itemIdRemovido,
            'venda_id' => $vendaIdRemovido,
            'rota' => $this->dadosRotaJson($rota),
        ]);
    }

    public function download($id)
    {
        $rota = RotaEntrega::with('itens')
            ->where('empresa_id', request()->empresa_id)
            ->findOrFail($id);

        if ($rota->itens->isEmpty()) {
            session()->flash('flash_erro', 'A rota não possui entregas.');
            return redirect()->route('rotas-entrega.show', $rota->id);
        }

        $semTel = $rota->itens->filter(fn ($i) => trim($i->telefone ?? '') === '');
        if ($semTel->isNotEmpty()) {
            session()->flash('flash_erro', 'Preencha o telefone de todos os pedidos antes de baixar a rota.');
            return redirect()->route('rotas-entrega.show', $rota->id);
        }

        if ($rota->status === 'rascunho') {
            session()->flash('flash_erro', 'Salve a rota antes de baixar o TXT.');
            return redirect()->route('rotas-entrega.show', $rota->id);
        }

        $content = RotaEntregaHelper::gerarTxtRota($rota);
        $filename = 'rota-' . $rota->id . '-' . date('Ymd-His') . '.txt';

        return response($content)
            ->header('Content-Type', 'text/plain; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    public function update(Request $request, $id)
    {
        $rota = RotaEntrega::where('empresa_id', request()->empresa_id)->findOrFail($id);

        if (!in_array($rota->status, ['rascunho', 'em_rota'], true)) {
            return response()->json(['message' => 'Esta rota não pode mais ser editada.'], 423);
        }

        $data = $request->validate([
            'motoboy_nome' => 'sometimes|nullable|string|max:120',
        ]);

        $rota->fill($data)->save();

        return response()->json(['ok' => true, 'motoboy_nome' => $rota->motoboy_nome]);
    }

    public function pagarMotoboy($id)
    {
        $rota = RotaEntrega::with('itens')
            ->where('empresa_id', request()->empresa_id)
            ->findOrFail($id);

        if (!$rota->podeRegistrarPagamentoMotoboy()) {
            session()->flash('flash_erro', 'Só é possível registrar pagamento em rotas salvas (em rota ou finalizadas).');
            return redirect()->route('rotas-entrega.show', $rota->id);
        }

        if ($rota->motoboy_pago) {
            session()->flash('flash_warning', 'Esta rota já está marcada como paga ao motoboy.');
            return redirect()->route('rotas-entrega.show', $rota->id);
        }

        $rota->motoboy_pago = true;
        $rota->motoboy_pago_em = now();
        $rota->motoboy_pago_usuario_id = get_id_user();
        $rota->save();

        session()->flash('flash_sucesso', 'Pagamento de R$ ' . __moeda($rota->totalFrete()) . ' ao motoboy registrado.');

        return redirect()->route('rotas-entrega.show', $rota->id);
    }

    public function desfazerPagamentoMotoboy($id)
    {
        $rota = RotaEntrega::where('empresa_id', request()->empresa_id)->findOrFail($id);

        if (!$rota->motoboy_pago) {
            session()->flash('flash_warning', 'Esta rota não está marcada como paga.');
            return redirect()->route('rotas-entrega.show', $rota->id);
        }

        $rota->motoboy_pago = false;
        $rota->motoboy_pago_em = null;
        $rota->motoboy_pago_usuario_id = null;
        $rota->save();

        session()->flash('flash_sucesso', 'Registro de pagamento ao motoboy desfeito.');

        return redirect()->route('rotas-entrega.show', $rota->id);
    }

    public function finalizar($id)
    {
        $rota = RotaEntrega::with('itens')
            ->where('empresa_id', request()->empresa_id)
            ->findOrFail($id);

        $pendentes = $rota->itens->where('status_entrega', 'pendente');
        if ($pendentes->isNotEmpty()) {
            $lista = $pendentes->map(fn ($i) => '#' . $i->venda_id)->implode(', ');
            session()->flash('flash_erro', 'Não é possível finalizar: ainda há pedidos sem confirmação na rota (' . $lista . '). Confirme pelo link do motoboy.');
            return redirect()->route('rotas-entrega.show', $rota->id);
        }

        $rota->status = 'finalizada';
        $rota->save();

        session()->flash('flash_sucesso', 'Rota finalizada.');

        return redirect()->route('rotas-entrega.index');
    }

    public function destroy($id)
    {
        $rota = RotaEntrega::with('itens')
            ->where('empresa_id', request()->empresa_id)
            ->findOrFail($id);

        if (!$rota->podeExcluir()) {
            session()->flash('flash_erro', 'Não é possível excluir: a rota está finalizada ou já possui entregas confirmadas.');
            return redirect()->back();
        }

        DB::transaction(function () use ($rota) {
            $vendaIds = $rota->itens->pluck('venda_id')->all();
            if (!empty($vendaIds)) {
                Venda::whereIn('id', $vendaIds)
                    ->where('empresa_id', $rota->empresa_id)
                    ->where('status_pedido', 'em_rota_entrega')
                    ->update(['status_pedido' => 'separado']);
                EcommerceSync::syncByVendaIds($vendaIds, false);
            }
            $rota->itens()->delete();
            $rota->delete();
        });

        session()->flash('flash_sucesso', 'Rota excluída.');

        return redirect()->route('rotas-entrega.index');
    }

    private function dadosRotaJson(RotaEntrega $rota): array
    {
        return [
            'id' => $rota->id,
            'status' => $rota->status,
            'status_label' => $rota->labelStatus(),
            'qtd_entregas' => $rota->itens->count(),
            'total_frete' => $rota->totalFrete(),
            'total_frete_fmt' => __moeda($rota->totalFrete()),
            'download_url' => route('rotas-entrega.download', $rota->id),
        ];
    }

    private function rotaJsonOuRedirect(Request $request, bool $ok, string $message, int $status = 200, array $extra = [])
    {
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(array_merge([
                'ok' => $ok,
                'message' => $message,
            ], $extra), $status);
        }

        session()->flash($ok ? 'flash_sucesso' : 'flash_erro', $message);

        if (!empty($extra['redirect'])) {
            return redirect($extra['redirect']);
        }

        if (!empty($extra['rota']['id'])) {
            return redirect()->route('rotas-entrega.show', $extra['rota']['id']);
        }

        return redirect()->back();
    }

    private function labelsStatusPedido(): array
    {
        return [
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
    }

    private function sincronizarPagamentoVenda(RotaEntregaItem $item): void
    {
        $item->loadMissing('venda');
        $venda = $item->venda;
        if (!$venda) {
            return;
        }

        if ($item->situacao_pagamento === 'pago' && $venda->status_pagamento !== 'pago') {
            $venda->status_pagamento = 'pago';
            $venda->save();
            EcommerceSync::syncFromVenda($venda, false);
        } elseif ($item->situacao_pagamento === 'pago_parcial' && $venda->status_pagamento === 'pendente') {
            $venda->status_pagamento = 'parcial';
            $venda->save();
            EcommerceSync::syncFromVenda($venda, false);
        }
    }

    private function sincronizarAvisoVenda(RotaEntregaItem $item): void
    {
        $item->loadMissing('venda');
        if (!$item->venda) {
            return;
        }

        $aviso = trim($item->aviso_entrega ?? '');
        if ($aviso !== '' && trim($item->venda->aviso_entrega ?? '') !== $aviso) {
            $item->venda->aviso_entrega = $aviso;
            $item->venda->save();
        }
    }

    private function vendasEmOutraRotaAtiva(int $empresaId, ?int $rotaIdExcluir = null): array
    {
        return RotaEntregaItem::whereHas('rota', function ($q) use ($empresaId, $rotaIdExcluir) {
            $q->where('empresa_id', $empresaId)
                ->whereIn('status', ['rascunho', 'em_rota']);
            if ($rotaIdExcluir !== null) {
                $q->where('id', '!=', $rotaIdExcluir);
            }
        })->pluck('venda_id')->unique()->values()->all();
    }

    private function validarVendasParaInclusao($vendas, int $empresaId, int $rotaIdAtual, bool $confirmarAlteracoes): ?array
    {
        $pendentes = $vendas->filter(fn ($v) => $v->status_pedido === 'alteracao_pendente');
        if ($pendentes->count() > 0 && !$confirmarAlteracoes) {
            return [
                'message' => 'Existem pedidos com alteração pendente de conferência física. Confirme para incluir na rota.',
                'status' => 422,
                'extra' => ['needs_confirmation' => true],
            ];
        }

        foreach ($vendas as $v) {
            if ($v->fechada_caixa) {
                return [
                    'message' => 'A venda #' . $v->id . ' está fechada no caixa.',
                    'status' => 423,
                ];
            }
        }

        $ids = $vendas->pluck('id')->all();
        $emOutraRota = RotaEntregaItem::with('rota')
            ->whereIn('venda_id', $ids)
            ->whereHas('rota', function ($q) use ($empresaId, $rotaIdAtual) {
                $q->where('empresa_id', $empresaId)
                    ->whereIn('status', ['rascunho', 'em_rota'])
                    ->where('id', '!=', $rotaIdAtual);
            })
            ->get();

        if ($emOutraRota->isNotEmpty()) {
            $lista = $emOutraRota->map(function ($item) {
                return 'Pedido #' . $item->venda_id . ' já está na rota #' . $item->rota_entrega_id;
            })->unique()->values()->implode('; ');

            return ['message' => $lista, 'status' => 409];
        }

        return null;
    }
}
