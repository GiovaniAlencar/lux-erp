<?php

namespace App\Console\Commands;

use App\Models\VendaComprovante;
use App\Services\Evolution;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Manda para o grupo do WhatsApp os comprovantes de venda que estão na fila `notificacoes`
 * (evento whatsapp_comprovante). Agendado a cada minuto. Falhou? tenta de novo em 5/10/20/40 min.
 */
class EnviarWhatsappComprovantes extends Command
{
    protected $signature = 'whatsapp:comprovantes {--limite=20}';
    protected $description = 'Envia os comprovantes de venda pendentes para o grupo do WhatsApp';

    public const EVENTO = 'whatsapp_comprovante';
    private const MAX_TENTATIVAS = 5;

    public function handle()
    {
        if (!Schema::hasTable('notificacoes') || !Evolution::configurada()) {
            return self::SUCCESS;
        }
        $fila = DB::table('notificacoes')
            ->where('evento', self::EVENTO)->where('status', 'pendente')->where('enviar_apos', '<=', now())
            ->orderBy('id')->limit(max(1, min(100, (int) $this->option('limite'))))->get();

        $ok = 0;
        foreach ($fila as $n) {
            $dados = json_decode((string) $n->dados, true) ?: [];
            $c = VendaComprovante::find((int) ($dados['comprovante_id'] ?? 0));
            if (!$c) {
                $this->finalizar($n, 'ignorado', 'Comprovante foi excluído antes do envio');
                continue;
            }
            try {
                Evolution::enviarArquivo((string) $n->telefone, $c->arquivo, (string) ($dados['legenda'] ?? ''),
                    'comprovante-pedido-' . $c->venda_id . '-' . $c->id . '.' . pathinfo($c->arquivo, PATHINFO_EXTENSION));
                $this->finalizar($n, 'enviado', null);
                $ok++;
            } catch (\Throwable $e) {
                $tent = (int) $n->tentativas + 1;
                $erro = mb_substr($e->getMessage(), 0, 1000);
                if ($tent >= self::MAX_TENTATIVAS) {
                    $this->finalizar($n, 'erro', $erro);
                } else {
                    DB::table('notificacoes')->where('id', $n->id)->update([
                        'tentativas' => $tent, 'erro' => $erro,
                        'enviar_apos' => now()->addMinutes(5 * (2 ** ($tent - 1))),
                    ]);
                }
                \Log::warning("whatsapp:comprovantes #{$n->id}: {$erro}");
            }
        }
        if ($fila->isNotEmpty()) {
            $this->info("{$ok} de {$fila->count()} comprovante(s) enviados ao grupo.");
        }
        return self::SUCCESS;
    }

    private function finalizar(object $n, string $status, ?string $erro): void
    {
        DB::table('notificacoes')->where('id', $n->id)->update([
            'status' => $status,
            'erro' => $erro,
            'tentativas' => (int) $n->tentativas + ($status === 'ignorado' ? 0 : 1),
            'canais_enviados' => $status === 'enviado' ? 'whatsapp' : $n->canais_enviados,
            'enviado_em' => $status === 'enviado' ? now() : null,
        ]);
    }

    /** Coloca o comprovante na fila (chamado ao anexar). Não faz nada se o envio estiver desligado. */
    public static function enfileirar(VendaComprovante $c, string $legenda): void
    {
        $grupo = Evolution::grupoComprovantes();
        if (!$grupo || !Schema::hasTable('notificacoes')) {
            return;
        }
        DB::table('notificacoes')->insert([
            'evento' => self::EVENTO,
            'origem' => 'erp',
            'cliente_id' => null,
            'telefone' => mb_substr($grupo, 0, 30),
            'dados' => json_encode(['comprovante_id' => $c->id, 'venda_id' => $c->venda_id, 'legenda' => $legenda], JSON_UNESCAPED_UNICODE),
            'status' => 'pendente',
            'tentativas' => 0,
            'enviar_apos' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
