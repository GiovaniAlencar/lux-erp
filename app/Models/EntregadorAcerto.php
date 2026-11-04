<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Acerto do motoboy: soma dos fretes (repasse) e dos extras de um período, com as contas a pagar. SQL 39. */
class EntregadorAcerto extends Model
{
    protected $table = 'entregador_acertos';
    protected $fillable = ['empresa_id', 'entregador_id', 'tipo', 'periodo_inicio', 'periodo_fim', 'vencimento',
        'valor_fretes', 'valor_extras', 'conta_fretes_id', 'conta_extras_id', 'status', 'pago_em'];
    protected $casts = ['periodo_inicio' => 'date', 'periodo_fim' => 'date', 'vencimento' => 'date', 'pago_em' => 'datetime',
        'valor_fretes' => 'float', 'valor_extras' => 'float'];

    public function motoboy()
    {
        return $this->belongsTo(Entregador::class, 'entregador_id');
    }

    public function rotas()
    {
        return $this->hasMany(RotaEntrega::class, 'acerto_id')->orderBy('id');
    }

    public function total(): float
    {
        return round($this->valor_fretes + $this->valor_extras, 2);
    }

    public function periodoTexto(): string
    {
        return $this->tipo === 'diario' || $this->periodo_inicio->eq($this->periodo_fim)
            ? $this->periodo_inicio->format('d/m/Y')
            : $this->periodo_inicio->format('d/m') . ' a ' . $this->periodo_fim->format('d/m/Y');
    }
}
