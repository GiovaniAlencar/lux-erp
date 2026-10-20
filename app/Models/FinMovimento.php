<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Uma linha do extrato de uma conta financeira. Valor sempre positivo; o tipo diz se entra ou sai. */
class FinMovimento extends Model
{
    protected $table = 'fin_movimentos';

    protected $fillable = [
        'empresa_id', 'conta_id', 'tipo', 'valor', 'data', 'descricao', 'origem', 'categoria_id',
        'forma_pagamento', 'conta_pagar_id', 'conta_receber_id', 'venda_id', 'transferencia', 'comprovante', 'pendencia', 'pendencia_em', 'usuario_id',
    ];

    protected $casts = ['valor' => 'float', 'data' => 'date', 'pendencia_em' => 'datetime'];

    public const ORIGENS = [
        'pagamento' => 'Pagamento de conta',
        'recebimento' => 'Recebimento',
        'avulso' => 'Lançamento avulso',
        'transferencia' => 'Transferência',
        'ajuste' => 'Ajuste de saldo',
    ];

    public function conta()
    {
        return $this->belongsTo(FinConta::class, 'conta_id');
    }

    public function categoria()
    {
        return $this->belongsTo(CategoriaConta::class, 'categoria_id');
    }

    public function contaPagar()
    {
        return $this->belongsTo(ContaPagar::class, 'conta_pagar_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
