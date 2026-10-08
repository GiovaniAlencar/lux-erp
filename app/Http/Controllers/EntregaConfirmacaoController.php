<?php

namespace App\Http\Controllers;

use App\Models\RotaEntrega;
use App\Models\RotaEntregaItem;
use App\Models\Venda;
use App\Models\VendaAuditoria;
use Illuminate\Http\Request;

class EntregaConfirmacaoController extends Controller
{
    public function show(string $token)
    {
        $item = RotaEntregaItem::with(['rota', 'venda.cliente'])
            ->where('token_confirmacao', $token)
            ->firstOrFail();

        $tiposOcorrencia = RotaEntregaItem::tiposOcorrencia();
        $jaRespondido = in_array($item->status_entrega, ['entregue', 'ocorrencia'], true);

        return view('rotas_entrega.confirmar', compact('item', 'tiposOcorrencia', 'jaRespondido'));
    }

    public function submit(Request $request, string $token)
    {
        $item = RotaEntregaItem::with('rota')->where('token_confirmacao', $token)->firstOrFail();

        if (in_array($item->status_entrega, ['entregue', 'ocorrencia'], true)) {
            return redirect()->route('entrega.confirmar', $token)
                ->with('flash_warning', 'Esta entrega já foi registrada.');
        }

        $request->validate([
            'tipo' => 'required|in:entregue,ocorrencia',
            'ocorrencia_tipo' => 'required_if:tipo,ocorrencia|nullable|string|max:60',
            'ocorrencia_observacao' => 'nullable|string|max:1000',
        ]);

        $tipo = $request->input('tipo');

        if ($tipo === 'entregue') {
            $item->status_entrega = 'entregue';
            $item->ocorrencia_tipo = null;
            $item->ocorrencia_observacao = null;
            $item->confirmado_em = now();
            $item->save();

            $this->atualizarStatusVenda($item, 'entregue');
            $this->auditoria($item, 'entrega_confirmada_motoboy', 'Entrega confirmada pelo link do motoboy.');
        } else {
            $item->status_entrega = 'ocorrencia';
            $item->ocorrencia_tipo = $request->input('ocorrencia_tipo');
            $item->ocorrencia_observacao = $request->input('ocorrencia_observacao');
            $item->confirmado_em = now();
            $item->save();

            $label = RotaEntregaItem::tiposOcorrencia()[$item->ocorrencia_tipo] ?? $item->ocorrencia_tipo;
            $this->atualizarStatusVenda($item, 'ocorrencia_entrega', $label);
            $this->auditoria(
                $item,
                'entrega_ocorrencia_motoboy',
                'Ocorrência na entrega: ' . $label,
                [
                    'ocorrencia_tipo' => $item->ocorrencia_tipo,
                    'ocorrencia_observacao' => $item->ocorrencia_observacao,
                ]
            );
        }

        $this->verificarRotaFinalizada($item->rota);

        return redirect()->route('entrega.confirmar', $token)
            ->with('flash_sucesso', $tipo === 'entregue' ? 'Entrega confirmada com sucesso!' : 'Ocorrência registrada.');
    }

    private function verificarRotaFinalizada(RotaEntrega $rota): void
    {
        $rota->load('itens');
        $pendentes = $rota->itens->where('status_entrega', 'pendente')->count();
        if ($pendentes === 0 && $rota->status === 'em_rota') {
            $rota->status = 'finalizada';
            $rota->save();
        }
    }

    private function atualizarStatusVenda(RotaEntregaItem $item, string $status, ?string $ocorrenciaLabel = null): void
    {
        $venda = Venda::find($item->venda_id);
        if (!$venda || in_array($venda->status_pedido, ['entregue', 'cancelada'], true)) {
            return;
        }

        $venda->status_pedido = $status;
        $venda->save();

        if ($status === 'entregue') {
            VendaAuditoria::create([
                'empresa_id' => $item->rota->empresa_id,
                'venda_id' => $item->venda_id,
                'usuario_id' => null,
                'acao' => 'workflow_marcar_entregue',
                'descricao' => 'Pedido marcado como entregue (confirmação do motoboy).',
                'meta' => ['rota_id' => $item->rota_entrega_id],
            ]);
        } elseif ($status === 'ocorrencia_entrega' && $ocorrenciaLabel) {
            VendaAuditoria::create([
                'empresa_id' => $item->rota->empresa_id,
                'venda_id' => $item->venda_id,
                'usuario_id' => null,
                'acao' => 'entrega_ocorrencia_motoboy',
                'descricao' => 'Ocorrência na entrega: ' . $ocorrenciaLabel,
                'meta' => [
                    'rota_id' => $item->rota_entrega_id,
                    'ocorrencia_tipo' => $item->ocorrencia_tipo,
                    'ocorrencia_observacao' => $item->ocorrencia_observacao,
                ],
            ]);
        }
    }

    private function auditoria(RotaEntregaItem $item, string $acao, string $descricao, array $meta = []): void
    {
        VendaAuditoria::create([
            'empresa_id' => $item->rota->empresa_id,
            'venda_id' => $item->venda_id,
            'usuario_id' => null,
            'acao' => $acao,
            'descricao' => $descricao,
            'meta' => $meta,
        ]);
    }
}
