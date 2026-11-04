<?php

namespace App\Services\Crm;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * CRM LUX — conversa com a central de atendimento (os dois sentidos), sempre assinado:
 *   X-Crm-Token:     CRM_CENTRAL_TOKEN (igual ao CRM_API_TOKEN da central);
 *   X-Crm-Timestamp: segundos desde 1970 (pedido com mais de 5 min de diferença é recusado);
 *   X-Crm-Signature: HMAC-SHA256(CRM_HMAC_SECRET, "<timestamp>.<corpo exato>").
 */
class Central
{
    public const MAX_DIFERENCA_SEG = 300;

    public static function configurada(): bool
    {
        $c = config('lux.crm');
        return !empty($c['central_url']) && strlen((string) $c['token']) >= 24 && strlen((string) $c['hmac_secret']) >= 32;
    }

    public static function assinar(string $secret, string $ts, string $body): string
    {
        return hash_hmac('sha256', $ts . '.' . $body, $secret);
    }

    /** Confere um pedido que veio da central. */
    public static function pedidoValido(Request $request, ?int $agora = null): bool
    {
        if (!self::configurada()) {
            return false;
        }
        $c = config('lux.crm');
        $token = (string) $request->header('X-Crm-Token', '');
        $ts = (string) $request->header('X-Crm-Timestamp', '');
        $sig = (string) $request->header('X-Crm-Signature', '');
        if ($token === '' || !hash_equals((string) $c['token'], $token)) {
            return false;
        }
        if (!preg_match('/^\d{9,11}$/', $ts) || abs(($agora ?? time()) - (int) $ts) > self::MAX_DIFERENCA_SEG) {
            return false;
        }
        return $sig !== '' && hash_equals(self::assinar((string) $c['hmac_secret'], $ts, $request->getContent()), $sig);
    }

    /**
     * Chama a central. Devolve ['http' => código, 'json' => array] — código 0 quando nem conectou.
     */
    public static function chamar(string $metodo, string $caminho, ?array $dados = null): array
    {
        $c = config('lux.crm');
        $body = $dados === null ? '' : json_encode($dados, JSON_UNESCAPED_UNICODE);
        $ts = (string) time();
        try {
            $req = Http::withHeaders([
                'X-Crm-Token' => (string) $c['token'],
                'X-Crm-Timestamp' => $ts,
                'X-Crm-Signature' => self::assinar((string) $c['hmac_secret'], $ts, $body),
                'Accept' => 'application/json',
            ])->timeout(25)->connectTimeout(8);
            $url = $c['central_url'] . $caminho;
            $res = $metodo === 'GET'
                ? $req->get($url)
                : $req->withBody($body, 'application/json')->send($metodo, $url);
            return ['http' => $res->status(), 'json' => $res->json() ?? []];
        } catch (\Throwable $e) {
            return ['http' => 0, 'json' => ['message' => 'central fora do ar: ' . mb_substr($e->getMessage(), 0, 150)]];
        }
    }

    public static function status(): array
    {
        if (!self::configurada()) {
            return ['ok' => false, 'texto' => 'não configurada'];
        }
        $r = self::chamar('GET', '/integracoes/erp/crm/status');
        if ($r['http'] === 200) {
            $w = $r['json']['whatsapp'] ?? '?';
            return ['ok' => $w === 'conectado', 'texto' => 'WhatsApp ' . $w];
        }
        if ($r['http'] === 404) {
            return ['ok' => false, 'texto' => 'a central recusou as chaves (confira token/segredo e o relógio do servidor)'];
        }
        return ['ok' => false, 'texto' => $r['json']['message'] ?? ('erro ' . $r['http'])];
    }

    public static function bloqueio(string $tel, bool $bloqueado): void
    {
        if (self::configurada()) {
            self::chamar('POST', '/integracoes/erp/crm/bloqueio', ['telefone' => $tel, 'bloqueado' => $bloqueado]);
        }
    }
}
