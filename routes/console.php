<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Acerta as contas a receber das vendas já lançadas: cria o "restante" quando as parcelas não
// cobrem o total e dá baixa pelos comprovantes anexados. Pode rodar mais de uma vez.
//   php artisan lux:sincronizar-recebimentos        (todas as vendas com contas a receber ou comprovantes)
//   php artisan lux:sincronizar-recebimentos 123    (só a venda 123)
Artisan::command('lux:sincronizar-recebimentos {venda?}', function ($venda = null) {
    $ids = $venda ? [(int) $venda] : \App\Models\ContaReceber::whereNotNull('venda_id')->distinct()->pluck('venda_id')
        ->merge(\Illuminate\Support\Facades\Schema::hasTable('venda_comprovantes') ? \App\Models\VendaComprovante::distinct()->pluck('venda_id') : [])
        ->unique()->values()->all();
    $n = 0;
    foreach ($ids as $id) {
        $v = \App\Models\Venda::find($id);
        if (!$v) continue;
        \Illuminate\Support\Facades\DB::transaction(fn () => \App\Services\RecebimentoVenda::sincronizar($v));
        $n++;
    }
    $this->info("Vendas acertadas: {$n}");
})->purpose('Acerta contas a receber das vendas (restante + baixas dos comprovantes)');
