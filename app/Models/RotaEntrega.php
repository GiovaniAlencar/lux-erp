<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RotaEntrega extends Model
{
    protected $table = 'rotas_entrega';

    protected $fillable = [
        'empresa_id',
        'usuario_id',
        'status',
        'motoboy_nome',
        'motoboy_pago',
        'motoboy_pago_em',
        'motoboy_pago_usuario_id',
        'observacao',
    ];

    protected $casts = [
        'motoboy_pago' => 'boolean',
        'motoboy_pago_em' => 'datetime',
    ];

    public function podeExcluir(): bool
    {
        if ($this->status === 'finalizada') {
            return false;
        }

        return !$this->itens()->whereIn('status_entrega', ['entregue', 'ocorrencia'])->exists();
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function motoboyPagoPor()
    {
        return $this->belongsTo(Usuario::class, 'motoboy_pago_usuario_id');
    }

    public function itens()
    {
        return $this->hasMany(RotaEntregaItem::class, 'rota_entrega_id')->orderBy('ordem');
    }

    public function labelStatus(): string
    {
        return match ($this->status) {
            'rascunho' => 'Rascunho',
            'em_rota' => 'Em rota',
            'finalizada' => 'Finalizada',
            'cancelada' => 'Cancelada',
            default => $this->status,
        };
    }

    public function totalFrete(): float
    {
        return (float) $this->itens->sum('frete');
    }

    public function labelPagamentoMotoboy(): string
    {
        return $this->motoboy_pago ? 'Pago ao motoboy' : 'Pendente';
    }

    public function podeRegistrarPagamentoMotoboy(): bool
    {
        return in_array($this->status, ['em_rota', 'finalizada'], true)
            && $this->itens()->count() > 0;
    }
}
