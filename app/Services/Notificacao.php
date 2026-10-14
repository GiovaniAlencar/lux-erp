<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Coloca um acontecimento na fila `notificacoes`. Quem envia é o comando
 * `notificacoes:enviar` (agendado a cada minuto). Nunca derruba a operação.
 */
class Notificacao
{
    public static function registrar(string $evento, array $dest, array $dados = [], string $origem = 'erp'): void
    {
        try {
            if (!Schema::hasTable('notificacoes')) {
                return;
            }
            DB::table('notificacoes')->insert([
                'evento' => $evento,
                'origem' => $origem,
                'ecommerce_user_id' => $dest['ecommerce_user_id'] ?? null,
                'cliente_id' => $dest['cliente_id'] ?? null,
                'email' => $dest['email'] ?? null,
                'telefone' => $dest['telefone'] ?? null,
                'dados' => json_encode($dados, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Notificacao::registrar: ' . $e->getMessage());
        }
    }
}
