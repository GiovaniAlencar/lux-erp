<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Avise-me quando chegar" do site: quando o estoque de um produto volta a ficar
 * positivo, cria uma notificação (fila `notificacoes`) para cada cliente que pediu
 * e marca o aviso como feito. O envio (e-mail/WhatsApp) é do módulo de notificações.
 */
class AvisoReposicao
{
    public static function produtoVoltou(int $produtoId): void
    {
        try {
            if (!Schema::hasTable('ecommerce_avisos_reposicao') || !Schema::hasTable('notificacoes')) {
                return;
            }
            $pendentes = DB::table('ecommerce_avisos_reposicao as a')
                ->join('ecommerce_users as u', 'u.id', '=', 'a.ecommerce_user_id')
                ->where('a.produto_id', $produtoId)
                ->whereNull('a.avisado_em')
                ->where('u.status', 'active')
                ->select('a.id', 'u.id as user_id', 'u.name', 'u.email', 'u.phone', 'u.erp_cliente_id')
                ->get();
            if ($pendentes->isEmpty()) {
                return;
            }
            $nome = DB::table('produtos')->where('id', $produtoId)->value('nome');
            foreach ($pendentes as $p) {
                DB::table('notificacoes')->insert([
                    'evento' => 'produto_disponivel',
                    'origem' => 'erp',
                    'ecommerce_user_id' => $p->user_id,
                    'cliente_id' => $p->erp_cliente_id,
                    'email' => $p->email,
                    'telefone' => $p->phone,
                    'dados' => json_encode(['nome' => $p->name, 'produto_id' => $produtoId, 'produto' => $nome], JSON_UNESCAPED_UNICODE),
                ]);
            }
            DB::table('ecommerce_avisos_reposicao')->whereIn('id', $pendentes->pluck('id'))->update(['avisado_em' => now()]);
        } catch (\Throwable $e) {
            // aviso nunca pode atrapalhar a movimentação de estoque
            \Log::warning('AvisoReposicao: ' . $e->getMessage());
        }
    }

    /**
     * Vitrine "Reposições" do site: guarda quando o produto voltou ao estoque.
     * Produto recém-cadastrado não conta como reposição (aparece em "Novidades").
     */
    public static function marcarReposto(int $produtoId, bool $estoqueNovo): void
    {
        try {
            static $temColuna = null;
            $temColuna ??= Schema::hasColumn('produtos', 'reposto_em');
            if (!$temColuna) {
                return;
            }
            $q = DB::table('produtos')->where('id', $produtoId);
            if ($estoqueNovo) {
                // linha de estoque criada agora: só é reposição se o produto já existia antes
                $q->where('created_at', '<', now()->subDays(2));
            }
            $q->update(['reposto_em' => now()]);
        } catch (\Throwable $e) {
            \Log::warning('AvisoReposicao::marcarReposto: ' . $e->getMessage());
        }
    }
}
