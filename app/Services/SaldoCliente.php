<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Saldo (crédito) do cliente: quando paga a mais, a sobra fica guardada e pode ser usada
 * para pagar outro pedido. Saldo = créditos − débitos (tabela cliente_saldo_movimentos, SQL 32).
 */
class SaldoCliente
{
    public static function ativo(): bool
    {
        static $tem = null;
        return $tem ??= Schema::hasTable('cliente_saldo_movimentos');
    }

    public static function saldo(int $empresaId, int $clienteId): float
    {
        if (!self::ativo() || $clienteId <= 0) {
            return 0.0;
        }
        $v = DB::table('cliente_saldo_movimentos')->where('empresa_id', $empresaId)->where('cliente_id', $clienteId)
            ->selectRaw("COALESCE(SUM(CASE WHEN tipo = 'credito' THEN valor ELSE -valor END), 0) AS s")->value('s');
        return round((float) $v, 2);
    }

    public static function movimentos(int $empresaId, int $clienteId, int $limite = 30)
    {
        if (!self::ativo()) {
            return collect();
        }
        return DB::table('cliente_saldo_movimentos')->where('empresa_id', $empresaId)->where('cliente_id', $clienteId)
            ->orderByDesc('id')->limit($limite)->get();
    }

    /** Excluiu o recebimento: apaga o crédito que ele gerou / devolve o saldo que ele usou. */
    public static function desfazerDoComprovante(int $comprovanteId): void
    {
        if (self::ativo()) {
            DB::table('cliente_saldo_movimentos')->where('venda_comprovante_id', $comprovanteId)->delete();
        }
    }

    public static function creditar(int $empresaId, int $clienteId, float $valor, ?int $vendaId, string $descricao, ?int $usuarioId = null, ?int $comprovanteId = null): void
    {
        self::lancar('credito', $empresaId, $clienteId, $valor, $vendaId, $descricao, $usuarioId, $comprovanteId);
    }

    /** Usa o saldo. Lança exceção se o cliente não tiver saldo suficiente. */
    public static function debitar(int $empresaId, int $clienteId, float $valor, ?int $vendaId, string $descricao, ?int $usuarioId = null, ?int $comprovanteId = null): void
    {
        DB::transaction(function () use ($empresaId, $clienteId, $valor, $vendaId, $descricao, $usuarioId, $comprovanteId) {
            // trava as linhas do cliente para dois atendentes não usarem o mesmo saldo ao mesmo tempo
            DB::table('cliente_saldo_movimentos')->where('empresa_id', $empresaId)->where('cliente_id', $clienteId)->lockForUpdate()->get(['id']);
            $saldo = self::saldo($empresaId, $clienteId);
            if ($valor > $saldo + 0.009) {
                throw new \InvalidArgumentException('O cliente tem só R$ ' . __moeda($saldo) . ' de saldo.');
            }
            self::lancar('debito', $empresaId, $clienteId, $valor, $vendaId, $descricao, $usuarioId, $comprovanteId);
        });
    }

    private static function lancar(string $tipo, int $empresaId, int $clienteId, float $valor, ?int $vendaId, string $descricao, ?int $usuarioId, ?int $comprovanteId = null): void
    {
        $valor = round($valor, 2);
        if (!self::ativo() || $clienteId <= 0 || $valor <= 0) {
            return;
        }
        DB::table('cliente_saldo_movimentos')->insert([
            'empresa_id' => $empresaId,
            'cliente_id' => $clienteId,
            'venda_id' => $vendaId,
            'venda_comprovante_id' => $comprovanteId,
            'tipo' => $tipo,
            'valor' => $valor,
            'descricao' => mb_substr($descricao, 0, 255),
            'usuario_id' => $usuarioId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
