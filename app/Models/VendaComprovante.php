<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendaComprovante extends Model
{
    protected $table = 'venda_comprovantes';

    public const CONTAS = ['fiscal', 'nao_fiscal', 'outra'];

    protected $fillable = [
        'empresa_id', 'venda_id', 'usuario_id', 'arquivo', 'nome_original', 'conta', 'fin_conta_id', 'fin_movimento_id',
        'valor', 'data_pagamento', 'observacao',
    ];

    protected $casts = ['valor' => 'float', 'data_pagamento' => 'date'];

    public function finConta()
    {
        return $this->belongsTo(FinConta::class, 'fin_conta_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public static function nomeConta(?string $conta): string
    {
        return match ($conta) {
            'fiscal' => (string) config('lux.conta_fiscal'),
            'nao_fiscal' => (string) config('lux.conta_nao_fiscal'),
            'outra' => 'Outra (dinheiro, cartão…)',
            default => '—',
        };
    }
}
