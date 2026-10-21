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
        $schedule->command('whatsapp:clientes')->everyMinute()->withoutOverlapping(10);
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
