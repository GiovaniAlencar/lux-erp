<?php

namespace App\Console\Commands;

use App\Models\NotaFiscal;
use App\Services\AvisoCliente;
use App\Services\Evolution;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Manda os avisos de WhatsApp aos clientes (pedido confirmado, saiu para entrega, NF-e)
 * pela instância do número do atendimento (EVOLUTION_INSTANCIA_CLIENTES).
 * Agendado a cada minuto; só envia dentro do horário (AVISOS_HORA_INICIO/FIM).
 * Falhou? tenta de novo em 5/10/20/40 min.
 */
class EnviarWhatsappClientes extends Command
{
    protected $signature = 'whatsapp:clientes {--limite=20}';
    protected $description = 'Envia os avisos de WhatsApp pendentes aos clientes';

    private const MAX_TENTATIVAS = 5;

    public function handle()
    {
        $instancia = Evolution::instanciaClientes();
        if (!Schema::hasTable('notificacoes') || !$instancia || !AvisoCliente::dentroDoHorario()) {
            return self::SUCCESS;
        }
        $fila = DB::table('notificacoes')
            ->whereIn('evento', [AvisoCliente::WPP_CONFIRMADO, AvisoCliente::WPP_ROTA, AvisoCliente::WPP_NOTA])
            ->where('status', 'pendente')->where('enviar_apos', '<=', now())
            ->orderBy('id')->limit(max(1, min(100, (int) $this->option('limite'))))->get();

        $ok = 0;
        foreach ($fila as $n) {
            $dados = json_decode((string) $n->dados, true) ?: [];
            try {
                $texto = AvisoCliente::textoWhatsapp($n->evento, $dados);
                if ($n->evento === AvisoCliente::WPP_NOTA) {
                    $nota = NotaFiscal::find((int) ($dados['nota_id'] ?? 0));
                    if (!$nota || $nota->status !== 'autorizada') {
                        $this->finalizar($n, 'ignorado', 'Nota não está mais autorizada');
                        continue;
                    }
                    $pdf = AvisoCliente::danfePdf($nota);
                    if (!$pdf) {
                        throw new \RuntimeException('XML da nota não encontrado para gerar a DANFE.');
                    }
                    Evolution::enviarDocumento((string) $n->telefone, $pdf, 'application/pdf',
                        'NF-e-' . $nota->numero . '.pdf', $texto, $instancia);
                } else {
                    Evolution::enviarTexto((string) $n->telefone, $texto, $instancia);
                }
                $this->finalizar($n, 'enviado', null);
                $ok++;
                usleep(1500000); // pequeno intervalo entre mensagens
            } catch (\Throwable $e) {
                $tent = (int) $n->tentativas + 1;
                $erro = mb_substr($e->getMessage(), 0, 1000);
                if ($tent >= self::MAX_TENTATIVAS) {
                    $this->finalizar($n, 'erro', $erro);
                } else {
                    DB::table('notificacoes')->where('id', $n->id)->update([
                        'tentativas' => $tent, 'erro' => $erro,
                        'enviar_apos' => AvisoCliente::proximoHorario(now()->addMinutes(5 * (2 ** ($tent - 1)))),
                    ]);
                }
                \Log::warning("whatsapp:clientes #{$n->id}: {$erro}");
            }
        }
        if ($fila->isNotEmpty()) {
            $this->info("{$ok} de {$fila->count()} aviso(s) enviados aos clientes.");
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
}
