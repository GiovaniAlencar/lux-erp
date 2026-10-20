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

// Lista os grupos do número conectado na Evolution, para achar o ID do grupo dos comprovantes:
//   php artisan lux:whatsapp-grupos "LUX COMPROVANTES"
Artisan::command('lux:whatsapp-grupos {busca?}', function ($busca = null) {
    if (!\App\Services\Evolution::configurada()) {
        $this->error('Configure EVOLUTION_URL, EVOLUTION_KEY e EVOLUTION_INSTANCIA no .env (e rode php artisan optimize:clear).');
        return 1;
    }
    $grupos = collect(\App\Services\Evolution::grupos())
        ->when($busca, fn ($c) => $c->filter(fn ($g) => mb_stripos($g['nome'], $busca) !== false));
    if ($grupos->isEmpty()) {
        $this->warn('Nenhum grupo encontrado. O número da instância está dentro do grupo?');
        return 1;
    }
    $this->table(['Nome do grupo', 'ID (use em WHATSAPP_GRUPO_COMPROVANTES)'], $grupos->map(fn ($g) => [$g['nome'], $g['id']])->all());
    return 0;
})->purpose('Lista os grupos do WhatsApp (Evolution) com o ID de cada um');

// Manda uma mensagem de teste para o grupo dos comprovantes.
Artisan::command('lux:whatsapp-teste', function () {
    $g = \App\Services\Evolution::grupoComprovantes();
    if (!$g) {
        $this->error('Envio desligado: confira EVOLUTION_* e WHATSAPP_GRUPO_COMPROVANTES no .env.');
        return 1;
    }
    \App\Services\Evolution::enviarTexto($g, '✅ Teste do ERP LUX: os comprovantes de venda vão chegar aqui.');
    $this->info('Mensagem enviada para ' . $g);
    return 0;
})->purpose('Testa o envio para o grupo dos comprovantes');
