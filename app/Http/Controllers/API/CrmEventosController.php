<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\Crm\Central;
use App\Services\Crm\Crm;
use App\Services\Crm\Fila;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CRM LUX — avisos que a central manda (assinados com HMAC; sem assinatura certa = 404).
 *
 *   POST /api/central/crm/eventos  { event_id, tipo, ocorrido_em, dados }
 *
 * Tipos: mensagem_recebida (cliente respondeu → para a 2ª tentativa), mensagem_entregue,
 * conversa_assumida (quem atendeu), opt_out / opt_in (pediu para sair / voltou),
 * classificacao (atendente classificou a conversa → oportunidade "falou e não comprou").
 * Cada event_id é processado uma vez só (crm_eventos.evento_id é único).
 */
class CrmEventosController extends Controller
{
    private const CLASSIFICACOES = ['interessado', 'orcamento_enviado', 'aguardando_decisao', 'nao_comprou', 'comprou'];

    private function empresaId(): int
    {
        return (int) config('lux.central_atendimento.empresa_id', 1);
    }

    public function receber(Request $request)
    {
        if (!Crm::ativo() || !Central::pedidoValido($request)) {
            abort(404);
        }
        $eventoId = (string) $request->input('event_id', '');
        $tipo = (string) $request->input('tipo', '');
        $d = (array) $request->input('dados', []);
        if (!preg_match('/^[0-9a-f-]{36}$/i', $eventoId) || $tipo === '') {
            return response()->json(['success' => false, 'message' => 'evento inválido'], 422);
        }
        $emp = $this->empresaId();
        $tel = Crm::celular($d['telefone'] ?? null);
        $envio = !empty($d['envio_uuid']) ? DB::table('crm_envios')->where('uuid', (string) $d['envio_uuid'])->where('empresa_id', $emp)->first() : null;
        $cicloId = $envio->ciclo_id ?? null;

        try {
            DB::transaction(function () use ($emp, $eventoId, $tipo, $d, $tel, $envio, $cicloId) {
                $novo = DB::table('crm_eventos')->insertOrIgnore([
                    'empresa_id' => $emp, 'evento_id' => $eventoId, 'tipo' => mb_substr($tipo, 0, 40), 'telefone' => $tel,
                    'ciclo_id' => $cicloId, 'envio_uuid' => $envio->uuid ?? null,
                    'dados' => json_encode($d, JSON_UNESCAPED_UNICODE), 'created_at' => now(),
                ]);
                if (!$novo) {
                    return; // repetido: já processado
                }
                $this->aplicar($emp, $tipo, $d, $tel, $envio, $cicloId);
            });
        } catch (\Throwable $e) {
            Log::error('CRM: falha ao processar evento da central', ['tipo' => $tipo, 'erro' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'erro ao processar'], 500); // a central tenta de novo
        }
        return response()->json(['success' => true]);
    }

    private function aplicar(int $emp, string $tipo, array $d, ?string $tel, ?object $envio, ?int $cicloId): void
    {
        $atendente = isset($d['atendente']) ? mb_substr((string) $d['atendente'], 0, 120) : null;
        switch ($tipo) {
            case 'mensagem_recebida':
                if ($cicloId) {
                    Fila::marcarRespondido($emp, $cicloId, null);
                }
                break;

            case 'mensagem_entregue':
                if ($envio) {
                    DB::table('crm_envios')->where('id', $envio->id)->where('status', 'enviado')->update(['status' => 'entregue', 'updated_at' => now()]);
                }
                break;

            case 'conversa_assumida':
                if ($cicloId && $atendente) {
                    DB::table('crm_ciclos')->where('id', $cicloId)->whereNull('atendente')->update(['atendente' => $atendente, 'updated_at' => now()]);
                }
                break;

            case 'opt_out':
                if ($tel) {
                    Fila::bloquear($emp, $tel, ($d['origem'] ?? '') === 'central' ? 'central' : 'cliente', ($d['origem'] ?? '') === 'central' ? 'marcado na central' : 'pediu para sair (respondeu no WhatsApp)', null);
                }
                break;

            case 'opt_in':
                if ($tel) {
                    DB::table('crm_bloqueios')->where('empresa_id', $emp)->where('telefone', $tel)->whereIn('origem', ['cliente', 'central'])->delete();
                }
                break;

            case 'classificacao':
                $this->classificar($emp, $d, $tel, $cicloId, $atendente);
                break;
        }
    }

    /** Conversa classificada na central: abre/atualiza a oportunidade do telefone (uma aberta por telefone). */
    private function classificar(int $emp, array $d, ?string $tel, ?int $cicloId, ?string $atendente): void
    {
        if (!$tel) {
            return;
        }
        $cl = $d['classificacao'] ?? null;
        if ($cicloId && $atendente) {
            DB::table('crm_ciclos')->where('id', $cicloId)->whereNull('atendente')->update(['atendente' => $atendente, 'updated_at' => now()]);
        }
        $aberta = DB::table('crm_oportunidades')->where('empresa_id', $emp)->where('telefone', $tel)->whereIn('status', ['aberta', 'em_campanha'])->orderByDesc('id')->first();

        if ($cl === null || $cl === 'comprou' || !in_array($cl, self::CLASSIFICACOES, true)) {
            // classificação limpa ou "comprou": nada para retomar
            if ($aberta && $aberta->status === 'aberta') {
                DB::table('crm_oportunidades')->where('id', $aberta->id)->update(['status' => 'encerrada', 'classificacao' => $cl ?? $aberta->classificacao, 'updated_at' => now()]);
            }
            return;
        }
        $dados = [
            'classificacao' => $cl,
            'atendente' => $atendente,
            'conversa_externa_id' => isset($d['conversa_id']) ? mb_substr((string) $d['conversa_id'], 0, 64) : null,
            'data_conversa' => now(),
            'updated_at' => now(),
        ];
        if ($aberta) {
            DB::table('crm_oportunidades')->where('id', $aberta->id)->update($dados);
            return;
        }
        DB::table('crm_oportunidades')->insert($dados + [
            'empresa_id' => $emp,
            'telefone' => $tel,
            'nome' => isset($d['nome']) ? mb_substr((string) $d['nome'], 0, 150) : null,
            'cliente_id' => Crm::clientePorTelefone($emp, $tel),
            'status' => 'aberta',
            'created_at' => now(),
        ]);
    }
}
