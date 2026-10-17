<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Conta financeira da empresa (banco, caixa em dinheiro, cartão…) com saldo e extrato. */
class FinConta extends Model
{
    protected $table = 'fin_contas';

    protected $fillable = ['empresa_id', 'nome', 'tipo', 'saldo_inicial', 'data_saldo_inicial', 'padrao', 'ativo'];

    protected $casts = ['saldo_inicial' => 'float', 'padrao' => 'boolean', 'ativo' => 'boolean'];

    public const TIPOS = ['banco' => 'Banco', 'caixa' => 'Caixa (dinheiro)', 'cartao' => 'Cartão / maquininha', 'outro' => 'Outra'];

    public function movimentos()
    {
        return $this->hasMany(FinMovimento::class, 'conta_id');
    }

    /** Saldo na data (inclusive) — sem data, saldo atual. */
    public function saldo(?string $ate = null): float
    {
        $q = $this->movimentos();
        if ($ate) {
            $q->where('data', '<=', $ate);
        }
        $r = $q->selectRaw("COALESCE(SUM(CASE WHEN tipo = 'entrada' THEN valor ELSE -valor END), 0) AS s")->value('s');
        return round($this->saldo_inicial + (float) $r, 2);
    }

    public static function daEmpresa($empresaId, bool $soAtivas = true)
    {
        return static::where('empresa_id', $empresaId)
            ->when($soAtivas, fn ($q) => $q->where('ativo', 1))
            ->orderByDesc('padrao')->orderBy('nome')->get();
    }
}
