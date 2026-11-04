<?php

namespace App\Console\Commands;

use App\Services\Crm\Crm;
use App\Services\Crm\Fila;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * CRM LUX — rotina agendada (a cada minuto, sem sobreposição).
 *   1) compra nova encerra o ciclo do cliente (e marca venda do CRM quando ele tinha respondido);
 *   2) uma vez por dia, no horário de trabalho, monta a fila do dia (se "geração automática" estiver ligada).
 * Fase 2: não envia nada. O envio pela central entra na fase 4.
 */
class CrmProcessar extends Command
{
    protected $signature = 'crm:processar {--gerar : monta a fila agora, mesmo fora do horário}';
    protected $description = 'CRM: encerra ciclos por compra e monta a fila diária de contatos';

    public function handle()
    {
        if (!Crm::ativo()) {
            return self::SUCCESS;
        }
        $lock = Cache::lock('crm:processar', 300);
        if (!$lock->get()) {
            return self::SUCCESS;
        }
        try {
            foreach (DB::table('crm_config')->pluck('empresa_id')->merge(DB::table('crm_campanhas')->distinct()->pluck('empresa_id'))->unique() as $empresaId) {
                $empresaId = (int) $empresaId;
                $encerrados = Fila::verificarCompras($empresaId);
                if ($encerrados) {
                    $this->info("Empresa {$empresaId}: {$encerrados} ciclo(s) encerrado(s) por compra.");
                }
                $cfg = Crm::config($empresaId);
                $hoje = now()->format('Y-m-d');
                $noHorario = in_array((int) now()->isoWeekday(), array_map('intval', (array) $cfg['dias_semana']), true)
                    && (int) now()->format('G') >= (int) $cfg['hora_inicio'] && (int) now()->format('G') < (int) $cfg['hora_fim'];
                if ($this->option('gerar') || ($cfg['geracao_automatica'] && $noHorario && ($cfg['ultima_geracao'] ?? null) !== $hoje)) {
                    $n = Fila::gerar($empresaId);
                    Crm::salvarConfig($empresaId, ['ultima_geracao' => $hoje]);
                    $this->info("Empresa {$empresaId}: {$n} contato(s) entraram na fila.");
                }
            }
        } finally {
            $lock->release();
        }
        return self::SUCCESS;
    }
}
