<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('ecommerce:expire-orders')->everyFifteenMinutes();
        $schedule->command('notificacoes:enviar')->everyMinute()->withoutOverlapping(10);
        $schedule->command('whatsapp:comprovantes')->everyMinute()->withoutOverlapping(10);
        // Avisos aos clientes pelo número do atendimento: UM por minuto (antes eram até 20
        // de 1,5 em 1,5 s — uma rota com 13 clientes saía em 26 s). Rajada de mensagens
        // iguais por cliente não oficial é sinal de spam para o WhatsApp (11/11/2026).
        $schedule->command('whatsapp:clientes --limite=1')->everyMinute()->withoutOverlapping(10);
        $schedule->command('rotas:geocodificar')->everyMinute()->withoutOverlapping(10);
        $schedule->command('crm:processar')->everyMinute()->withoutOverlapping(10);
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
