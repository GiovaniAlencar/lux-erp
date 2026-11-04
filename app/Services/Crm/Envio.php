<?php

namespace App\Services\Crm;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CRM LUX — envio pela central (fase 3).
 *
 * Segurança contra mensagem repetida: o envio é "reservado" (status processando) com um UPDATE
 * condicional antes de chamar a central, e a central só manda uma vez por uuid — mesmo que o ERP
 * caia no meio e tente de novo, o cliente não recebe duas vezes.
 */
class Envio
{
    public const MAX_FALHAS = 3;

    /**
     * Envia um envio da fila agora.
     * $manual = clique no botão (vale também para quem ainda está aguardando aprovação: clicar = aprovar).
     * Retorna [ok(bool), mensagem(string), parar(bool: não adianta tentar outros agora)].
     */
    public static function enviar(int $empresaId, int $envioId, ?int $usuarioId = null, bool $manual = false): array
    {
        if (!Central::configurada()) {
            return [false, 'A integração com a central não está configurada (.env do ERP).', true];
        }
        $permitidos = $manual ? ['aguardando_aprovacao', 'pendente', 'erro'] : ['pendente'];
        $reservou = DB::table('crm_envios')->where('empresa_id', $empresaId)->where('id', $envioId)
            ->whereIn('status', $permitidos)
            ->update([
                'status' => 'processando', 'erro' => null, 'updated_at' => now(),
                'aprovado_por' => DB::raw('COALESCE(aprovado_por, ' . (int) $usuarioId . ')'),
                'aprovado_em' => DB::raw('COALESCE(aprovado_em, NOW())'),
            ]);
        if (!$reservou) {
            return [false, 'Este envio já foi feito ou está saindo agora.', false];
        }
        $e = DB::table('crm_envios')->where('id', $envioId)->first();
        $c = DB::table('crm_ciclos')->where('id', $e->ciclo_id)->first();
        $camp = $c ? DB::table('crm_campanhas')->where('id', $c->campanha_id)->first() : null;
        if (!$c || !in_array($c->status, ['ativo'], true)) {
            DB::table('crm_envios')->where('id', $envioId)->update(['status' => 'cancelado', 'erro' => 'contato não está mais em andamento', 'updated_at' => now()]);
            return [false, 'Este contato não está mais em andamento.', false];
        }
        if (Crm::bloqueado($empresaId, $e->telefone)) {
            Fila::encerrar((int) $c->id, 'bloqueado', 'pediu para não receber mensagens');
            DB::table('crm_envios')->where('id', $envioId)->update(['status' => 'cancelado', 'erro' => 'telefone bloqueado', 'updated_at' => now()]);
            return [false, 'Este telefone pediu para não receber mensagens.', false];
        }

        $ctx = json_decode((string) $c->contexto, true) ?: [];
        $r = Central::chamar('POST', '/integracoes/erp/crm/enviar', [
            'envio_uuid' => $e->uuid,
            'ciclo_id' => (int) $c->id,
            'campanha' => $camp->chave ?? 'crm',
            'campanha_nome' => $camp->nome ?? 'CRM',
            'tentativa' => (int) $e->tentativa,
            'telefone' => $e->telefone,
            'nome' => $c->nome,
            'texto' => $e->texto,
            'contexto' => array_intersect_key($ctx, array_flip(['venda_id', 'data', 'valor', 'produtos', 'dias'])) ?: null,
        ]);
        return self::tratarResposta($empresaId, $e, $c, $r);
    }

    private static function tratarResposta(int $empresaId, object $e, object $c, array $r): array
    {
        $j = $r['json'];
        $status = $j['status'] ?? null;
        $msg = (string) ($j['message'] ?? '');

        if ($r['http'] === 200 && ($j['success'] ?? false)) {
            DB::table('crm_envios')->where('id', $e->id)->update([
                'status' => 'enviado', 'enviado_em' => now(), 'id_externo' => $j['message_id'] ?? null, 'erro' => null, 'updated_at' => now(),
            ]);
            return [true, 'Mensagem enviada para ' . $c->nome . '.', false];
        }

        $falhas = (int) $e->tentativas_envio + 1;
        // $contar = false: problema do nosso lado (WhatsApp desconectado, chaves) — não gasta as tentativas do envio
        $volta = fn (string $erro, ?\DateTimeInterface $quando = null, bool $contar = true) => DB::table('crm_envios')->where('id', $e->id)->update([
            'status' => 'pendente', 'erro' => mb_substr($erro, 0, 500), 'tentativas_envio' => $contar ? $falhas : (int) $e->tentativas_envio,
            'agendado_para' => $quando ?? now(), 'updated_at' => now(),
        ]);

        if ($status === 'bloqueado') {
            Fila::bloquear($empresaId, $e->telefone, 'cliente', 'pediu para sair (central)', null);
            return [false, 'O cliente pediu para não receber mensagens — bloqueado.', false];
        }
        if ($status === 'sem_whatsapp') {
            DB::table('crm_envios')->where('id', $e->id)->update(['status' => 'erro', 'erro' => 'número sem WhatsApp', 'updated_at' => now()]);
            Fila::encerrar((int) $c->id, 'encerrado', 'número sem WhatsApp');
            return [false, 'Este número não tem WhatsApp. Contato encerrado — atualize o cadastro.', false];
        }
        if ($status === 'em_atendimento') {
            // está conversando agora com alguém da equipe: tenta amanhã (sem contar como falha)
            DB::table('crm_envios')->where('id', $e->id)->update([
                'status' => 'pendente', 'erro' => 'cliente em atendimento — remarcado para amanhã', 'agendado_para' => now()->addDay()->startOfDay(), 'updated_at' => now(),
            ]);
            return [false, 'O cliente está em atendimento agora; o envio foi remarcado para amanhã.', false];
        }
        if ($status === 'whatsapp_desconectado') {
            $volta('WhatsApp da central desconectado', now()->addMinutes(15), false);
            return [false, 'O WhatsApp da central está desconectado. Nada foi enviado.', true];
        }
        if ($r['http'] === 404) {
            $volta('a central recusou as chaves', now()->addMinutes(30), false);
            return [false, 'A central recusou o pedido: confira CRM_CENTRAL_TOKEN / CRM_HMAC_SECRET e o horário do servidor.', true];
        }
        if ($r['http'] === 422 && ($j['permanente'] ?? $status !== 'erro')) {
            DB::table('crm_envios')->where('id', $e->id)->update(['status' => 'erro', 'erro' => mb_substr($msg ?: 'dados recusados pela central', 0, 500), 'updated_at' => now()]);
            return [false, 'A central recusou: ' . ($msg ?: 'dados inválidos'), false];
        }
        // falha temporária (rede, central reiniciando, Evolution instável): tenta de novo mais tarde
        if ($falhas >= self::MAX_FALHAS) {
            DB::table('crm_envios')->where('id', $e->id)->update(['status' => 'erro', 'erro' => mb_substr($msg ?: 'falha ao enviar', 0, 500), 'tentativas_envio' => $falhas, 'updated_at' => now()]);
            return [false, 'Não foi possível enviar depois de ' . $falhas . ' tentativas: ' . $msg, true];
        }
        $volta($msg ?: 'falha ao enviar', now()->addMinutes(10 * $falhas));
        return [false, 'Falhou agora (' . ($msg ?: 'erro ' . $r['http']) . '). Tenta de novo sozinho em alguns minutos.', true];
    }

    /** Envio que ficou "processando" (ERP caiu no meio): volta para a fila — a central não repete o mesmo uuid. */
    public static function destravar(int $empresaId): int
    {
        return DB::table('crm_envios')->where('empresa_id', $empresaId)->where('status', 'processando')
            ->where('updated_at', '<', now()->subMinutes(10))
            ->update(['status' => 'pendente', 'erro' => 'retomado depois de interrupção', 'updated_at' => now()]);
    }

    /**
     * Envio automático (rotina a cada minuto): no máximo UM por vez, respeitando horário, dias,
     * limite diário e o intervalo sorteado entre mensagens.
     */
    public static function processarFila(int $empresaId): ?string
    {
        $cfg = Crm::config($empresaId);
        if (empty($cfg['envio_ligado']) || !Central::configurada()) {
            return null;
        }
        self::destravar($empresaId);
        $h = (int) now()->format('G');
        if (!in_array((int) now()->isoWeekday(), array_map('intval', (array) $cfg['dias_semana']), true)
            || $h < (int) $cfg['hora_inicio'] || $h >= (int) $cfg['hora_fim']) {
            return null;
        }
        $chave = 'crm:proximo_envio:' . $empresaId;
        $proximo = Cache::get($chave);
        if ($proximo && now()->timestamp < (int) $proximo) {
            return null;
        }
        $hoje = DB::table('crm_envios')->where('empresa_id', $empresaId)->where('enviado_em', '>=', now()->startOfDay())->count();
        if ($hoje >= (int) $cfg['limite_diario']) {
            return null;
        }
        $e = DB::table('crm_envios')->where('empresa_id', $empresaId)->where('status', 'pendente')
            ->where('agendado_para', '<=', now())->orderBy('agendado_para')->orderBy('id')->first();
        if (!$e) {
            return null;
        }
        [$ok, $msg, $parar] = self::enviar($empresaId, (int) $e->id);
        $espera = random_int((int) $cfg['intervalo_min_seg'], max((int) $cfg['intervalo_min_seg'], (int) $cfg['intervalo_max_seg']));
        if ($parar && !$ok) {
            $espera = max($espera, 600);
        }
        Cache::put($chave, now()->timestamp + $espera, now()->addHours(3));
        return $msg;
    }

    /** Mensagem de teste para o número do .env (não entra em campanha nenhuma). */
    public static function teste(string $tel, string $texto): array
    {
        $r = Central::chamar('POST', '/integracoes/erp/crm/enviar', [
            'envio_uuid' => (string) Str::uuid(),
            'ciclo_id' => null,
            'campanha' => 'teste',
            'campanha_nome' => 'Teste do CRM',
            'tentativa' => 1,
            'telefone' => $tel,
            'nome' => null,
            'texto' => $texto,
        ]);
        if ($r['http'] === 200 && ($r['json']['success'] ?? false)) {
            return [true, 'Teste enviado para ' . Crm::formatarTelefone($tel) . '. Responda pelo celular para ver a conversa voltar na central.'];
        }
        return [false, 'Teste não enviado: ' . ($r['json']['message'] ?? ('erro ' . $r['http']))];
    }
}
