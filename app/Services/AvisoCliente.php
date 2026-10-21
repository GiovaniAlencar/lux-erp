<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\ConfigNota;
use App\Models\NotaFiscal;
use App\Models\Venda;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use NFePHP\DA\NFe\Danfe;

/**
 * Avisos automáticos ao cliente (e-mail + WhatsApp do número do atendimento):
 *   - pedido confirmado (já está sendo separado)
 *   - saiu para entrega (aguarde no endereço)
 *   - NF-e emitida (e-mail com DANFE + XML; WhatsApp com o PDF da DANFE)
 *
 * Só coloca na fila `notificacoes`; quem envia são os comandos agendados
 * (notificacoes:enviar para e-mail e whatsapp:clientes para WhatsApp).
 * Cada aviso sai no máximo uma vez por venda/nota. Nunca derruba a operação.
 */
class AvisoCliente
{
    public const WPP_CONFIRMADO = 'whatsapp_cliente_confirmado';
    public const WPP_ROTA = 'whatsapp_cliente_rota';
    public const WPP_NOTA = 'whatsapp_cliente_nota';

    public const EMAIL_CONFIRMADO = 'pedido_confirmado';
    public const EMAIL_NOTA = 'nota_emitida';

    public static function pedidoConfirmado(Venda $v): void
    {
        self::seguro(function () use ($v) {
            $cli = $v->cliente_id ? Cliente::find($v->cliente_id) : null;
            if (!$cli) {
                return;
            }
            $ref = 'venda#' . $v->id;
            $dados = ['venda_id' => $v->id, 'pedido' => $v->id, 'nome' => self::nome($cli)];
            self::email(self::EMAIL_CONFIRMADO, $ref, $cli, $dados);
            self::whatsapp(self::WPP_CONFIRMADO, $ref, $cli, $dados);
        });
    }

    /** @param iterable<int> $vendaIds vendas que acabaram de ir para "em rota de entrega" */
    public static function emRota(iterable $vendaIds): void
    {
        self::seguro(function () use ($vendaIds) {
            $ids = collect($vendaIds)->map(fn ($i) => (int) $i)->filter()->unique()->values();
            if ($ids->isEmpty()) {
                return;
            }
            $vendas = Venda::whereIn('id', $ids)->where('status_pedido', 'em_rota_entrega')->get();
            foreach ($vendas as $v) {
                $cli = $v->cliente_id ? Cliente::find($v->cliente_id) : null;
                if ($cli) {
                    self::whatsapp(self::WPP_ROTA, 'venda#' . $v->id, $cli,
                        ['venda_id' => $v->id, 'pedido' => $v->id, 'nome' => self::nome($cli)]);
                }
            }
        });
    }

    public static function notaAutorizada(NotaFiscal $nota): void
    {
        self::seguro(function () use ($nota) {
            $cliId = $nota->cliente_id ?: optional($nota->venda_id ? Venda::find($nota->venda_id) : null)->cliente_id;
            $cli = $cliId ? Cliente::find($cliId) : null;
            if (!$cli || $nota->status !== 'autorizada') {
                return;
            }
            $ref = 'nota#' . $nota->id;
            $dados = [
                'nota_id' => $nota->id,
                'numero' => (string) $nota->numero,
                'pedido' => $nota->venda_id,
                'nome' => self::nome($cli),
            ];
            self::email(self::EMAIL_NOTA, $ref, $cli, $dados);
            self::whatsapp(self::WPP_NOTA, $ref, $cli, $dados);
        });
    }

    /* ------------------------------------------------------------------ textos do WhatsApp */

    public static function textoWhatsapp(string $evento, array $d): string
    {
        $ola = ($d['nome'] ?? '') !== '' ? 'Olá, ' . $d['nome'] . '!' : 'Olá!';
        $pedido = $d['pedido'] ?? '';
        switch ($evento) {
            case self::WPP_CONFIRMADO:
                return "{$ola} 😊\n\nSeu pedido *#{$pedido}* foi confirmado e já está sendo separado pela nossa equipe.\n\nAvisaremos por aqui quando ele sair para entrega.\n\n*LUX Perfumes*";
            case self::WPP_ROTA:
                return "{$ola} 🛵\n\nSeu pedido *#{$pedido}* saiu para entrega e chega em breve.\n\nPor favor, aguarde no endereço de entrega.\n\n*LUX Perfumes*";
            case self::WPP_NOTA:
                $p = $pedido ? " do pedido *#{$pedido}*" : '';
                return "{$ola}\n\nSegue a nota fiscal (NF-e nº {$d['numero']}){$p}.\n\n*LUX Perfumes*";
        }
        return '';
    }

    /* ------------------------------------------------------------------ DANFE */

    /** PDF da DANFE da nota (null se não houver XML). */
    public static function danfePdf(NotaFiscal $nota): ?string
    {
        $path = $nota->xmlPath();
        if (!$path) {
            return null;
        }
        $config = ConfigNota::where('empresa_id', $nota->empresa_id)->first();
        $logo = null;
        if ($config && $config->logo && file_exists(public_path('uploads/configEmitente/' . $config->logo))) {
            $logo = 'data://text/plain;base64,' . base64_encode(file_get_contents(public_path('uploads/configEmitente/' . $config->logo)));
        }
        $danfe = new Danfe(file_get_contents($path));
        $danfe->setVUnComCasasDec((int) ($config->casas_decimais ?? 2));
        return $danfe->render($logo);
    }

    /* ------------------------------------------------------------------ horário e telefone */

    /** Próximo momento permitido para mandar WhatsApp (agora, se estiver no horário). */
    public static function proximoHorario(?Carbon $agora = null): Carbon
    {
        $agora = $agora ? $agora->copy() : now();
        [$ini, $fim] = self::janela();
        if ($agora->hour >= $ini && $agora->hour < $fim) {
            return $agora;
        }
        $abre = $agora->copy()->setTime($ini, 0);
        return $agora->hour >= $fim ? $abre->addDay() : $abre;
    }

    public static function dentroDoHorario(?Carbon $agora = null): bool
    {
        $agora = $agora ?: now();
        [$ini, $fim] = self::janela();
        return $agora->hour >= $ini && $agora->hour < $fim;
    }

    private static function janela(): array
    {
        $ini = max(0, min(23, (int) config('lux.avisos_clientes.hora_inicio', 7)));
        $fim = max($ini + 1, min(24, (int) config('lux.avisos_clientes.hora_fim', 21)));
        return [$ini, $fim];
    }

    /** "(83) 99999-9999" → "5583999999999". null se não parecer um número brasileiro válido. */
    public static function telefoneWhatsapp(?string $tel): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $tel);
        $d = ltrim($d, '0');
        if (strlen($d) === 10 || strlen($d) === 11) {
            $d = '55' . $d;
        }
        if (!preg_match('/^55[1-9][0-9]\d{8,9}$/', $d)) {
            return null;
        }
        // celular com 9 dígitos precisa começar com 9
        if (strlen($d) === 13 && $d[4] !== '9') {
            return null;
        }
        return $d;
    }

    /* ------------------------------------------------------------------ fila */

    private static function ativo(): bool
    {
        return (bool) config('lux.avisos_clientes.ativo', true) && Schema::hasTable('notificacoes');
    }

    private static function email(string $evento, string $ref, Cliente $cli, array $dados): void
    {
        $email = trim((string) $cli->email);
        if (!self::ativo() || !filter_var($email, FILTER_VALIDATE_EMAIL) || self::jaExiste($evento, $ref)) {
            return;
        }
        self::inserir($evento, $ref, $cli, $email, null, $dados, now());
    }

    private static function whatsapp(string $evento, string $ref, Cliente $cli, array $dados): void
    {
        if (!self::ativo() || !Evolution::instanciaClientes()) {
            return;
        }
        $tel = self::telefoneWhatsapp($cli->celular) ?: self::telefoneWhatsapp($cli->telefone);
        if (!$tel || self::jaExiste($evento, $ref)) {
            return;
        }
        self::inserir($evento, $ref, $cli, null, $tel, $dados, self::proximoHorario());
    }

    private static function jaExiste(string $evento, string $ref): bool
    {
        return DB::table('notificacoes')->where('evento', $evento)->where('origem', $ref)->exists();
    }

    private static function inserir(string $evento, string $ref, Cliente $cli, ?string $email, ?string $tel, array $dados, Carbon $quando): void
    {
        DB::table('notificacoes')->insert([
            'evento' => $evento,
            'origem' => mb_substr($ref, 0, 20),
            'cliente_id' => $cli->id,
            'email' => $email,
            'telefone' => $tel,
            'dados' => json_encode($dados, JSON_UNESCAPED_UNICODE),
            'enviar_apos' => $quando,
        ]);
    }

    private static function nome(Cliente $cli): string
    {
        $n = trim((string) ($cli->razao_social ?: $cli->nome_fantasia));
        $p = preg_split('/\s+/', $n);
        return mb_convert_case(mb_strtolower($p[0] ?? ''), MB_CASE_TITLE, 'UTF-8');
    }

    private static function seguro(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            \Log::warning('AvisoCliente: ' . $e->getMessage());
        }
    }
}
