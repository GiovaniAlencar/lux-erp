<?php

namespace App\Console\Commands;

use App\Mail\NotificacaoLux;
use App\Services\NotificacaoModelos;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * Envia os e-mails da fila `notificacoes` (cadastro, pedido, avise-me, senha…).
 * Agendado a cada minuto (app/Console/Kernel.php). Falhou? tenta de novo com
 * intervalo crescente; depois de 5 tentativas fica como "erro" na tela Notificações.
 */
class EnviarNotificacoes extends Command
{
    protected $signature = 'notificacoes:enviar {--limite=40 : máximo de e-mails por execução}';
    protected $description = 'Envia os e-mails pendentes da fila de notificações';

    private const MAX_TENTATIVAS = 5;

    public function handle()
    {
        if (!Schema::hasTable('notificacoes')) {
            return self::SUCCESS;
        }
        if (!config('lux.notificacoes_email')) {
            $this->line('Envio de e-mails desligado (NOTIFICACOES_EMAIL=false).');
            return self::SUCCESS;
        }

        $fila = DB::table('notificacoes')
            ->where('status', 'pendente')
            ->where('evento', 'not like', 'whatsapp\\_%') // esses vão pelo WhatsApp (whatsapp:comprovantes)
            ->where('enviar_apos', '<=', now())
            ->orderBy('id')
            ->limit(max(1, min(200, (int) $this->option('limite'))))
            ->get();

        $enviados = 0;
        foreach ($fila as $n) {
            $dados = json_decode((string) $n->dados, true) ?: [];
            $email = trim((string) $n->email);

            // link de senha vencido não adianta mais enviar
            if ($n->evento === 'senha_recuperar' && strtotime((string) $n->created_at) < time() - 3600) {
                $this->finalizar($n, 'ignorado', 'Link expirou antes do envio', $dados);
                continue;
            }
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->finalizar($n, 'ignorado', 'Sem e-mail válido', $dados);
                continue;
            }
            $modelo = NotificacaoModelos::montar($n->evento, $dados);
            if (!$modelo) {
                $this->finalizar($n, 'ignorado', 'Evento sem modelo de e-mail', $dados);
                continue;
            }

            try {
                $anexos = [];
                if ($n->evento === 'nota_emitida') {
                    $nota = \App\Models\NotaFiscal::find((int) ($dados['nota_id'] ?? 0));
                    if (!$nota || $nota->status !== 'autorizada') {
                        $this->finalizar($n, 'ignorado', 'Nota não está mais autorizada', $dados);
                        continue;
                    }
                    $pdf = \App\Services\AvisoCliente::danfePdf($nota);
                    if (!$pdf) {
                        throw new \RuntimeException('XML da nota não encontrado para gerar a DANFE.');
                    }
                    $anexos[] = ['conteudo' => $pdf, 'nome' => 'NF-e-' . $nota->numero . '.pdf', 'mime' => 'application/pdf'];
                    $anexos[] = ['conteudo' => file_get_contents($nota->xmlPath()), 'nome' => $nota->chave . '.xml', 'mime' => 'application/xml'];
                }
                Mail::to($email)->send(new NotificacaoLux($modelo, $anexos));
                $this->finalizar($n, 'enviado', null, $dados);
                $enviados++;
            } catch (\Throwable $e) {
                $tent = (int) $n->tentativas + 1;
                $erro = mb_substr($e->getMessage(), 0, 1000);
                if ($tent >= self::MAX_TENTATIVAS) {
                    $this->finalizar($n, 'erro', $erro, $dados);
                } else {
                    DB::table('notificacoes')->where('id', $n->id)->update([
                        'tentativas' => $tent,
                        'erro' => $erro,
                        'enviar_apos' => now()->addMinutes(5 * (2 ** ($tent - 1))), // 5, 10, 20, 40 min
                    ]);
                }
                \Log::warning("notificacoes:enviar #{$n->id}: {$erro}");
            }
        }

        if ($fila->isNotEmpty()) {
            $this->info("{$enviados} de {$fila->count()} e-mail(s) enviados.");
        }
        return self::SUCCESS;
    }

    private function finalizar(object $n, string $status, ?string $erro, array $dados): void
    {
        unset($dados['token']); // o link de senha não fica guardado depois do envio
        DB::table('notificacoes')->where('id', $n->id)->update([
            'status' => $status,
            'erro' => $erro,
            'tentativas' => (int) $n->tentativas + ($status === 'ignorado' ? 0 : 1),
            'canais_enviados' => $status === 'enviado' ? 'email' : $n->canais_enviados,
            'enviado_em' => $status === 'enviado' ? now() : null,
            'dados' => json_encode($dados, JSON_UNESCAPED_UNICODE),
        ]);
    }
}
