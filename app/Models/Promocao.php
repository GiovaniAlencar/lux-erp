<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Promocao extends Model
{
    protected $table = 'promocoes';

    protected $fillable = [
        'empresa_id',
        'produto_id',
        'tipo',
        'valor',
        'data_inicio',
        'data_fim',
        'quantidade_limite',
        'quantidade_utilizada',
        'ativo',
        'observacao',
    ];

    // data_inicio/data_fim ficam como string 'Y-m-d' (sem cast) de propósito: o pacote de
    // formulário usado nas telas (netojose/laravel-bootstrap-4-forms) lê o atributo via
    // ArrayAccess do model para preencher o <input type="date">, e um cast para Carbon
    // faria o valor virar "Y-m-d H:i:s" (via __toString), deixando o campo em branco no
    // formulário de edição. As datas são tratadas com Carbon::parse() nos métodos abaixo.
    protected $casts = [
        'valor' => 'float',
        'quantidade_limite' => 'integer',
        'quantidade_utilizada' => 'integer',
        'ativo' => 'boolean',
    ];

    public function produto()
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }

    public static function tipos(): array
    {
        return [
            'percentual' => 'Percentual (%)',
            'preco_fixo' => 'Preço promocional fixo (R$)',
        ];
    }

    /**
     * Está dentro do período de vigência (independente de ativo/estoque de unidades).
     */
    public function dentroDoPeriodo(): bool
    {
        $hoje = now()->startOfDay();

        return $hoje->gte(\Carbon\Carbon::parse($this->data_inicio)->startOfDay())
            && $hoje->lte(\Carbon\Carbon::parse($this->data_fim)->endOfDay());
    }

    /**
     * Ainda há unidades disponíveis dentro do limite cadastrado (sem limite = sempre disponível).
     */
    public function dentroDoLimite(): bool
    {
        if ($this->quantidade_limite === null) {
            return true;
        }

        return $this->quantidade_utilizada < $this->quantidade_limite;
    }

    /**
     * Vigente agora: ativa, dentro do período e ainda com unidades disponíveis.
     */
    public function estaVigente(): bool
    {
        return $this->ativo && $this->dentroDoPeriodo() && $this->dentroDoLimite();
    }

    public function labelStatus(): string
    {
        if (!$this->ativo) {
            return 'Desativada';
        }
        if (!$this->dentroDoLimite()) {
            return 'Esgotada';
        }
        if (now()->startOfDay()->lt(\Carbon\Carbon::parse($this->data_inicio)->startOfDay())) {
            return 'Agendada';
        }
        if (now()->startOfDay()->gt(\Carbon\Carbon::parse($this->data_fim)->endOfDay())) {
            return 'Expirada';
        }

        return 'Vigente';
    }

    /**
     * Calcula o preço promocional a partir de um preço base.
     */
    public function precoPromocional(float $precoBase): float
    {
        if ($this->tipo === 'preco_fixo') {
            return (float) $this->valor;
        }

        $desconto = $precoBase * ((float) $this->valor / 100);

        return max(0, round($precoBase - $desconto, 2));
    }
}
