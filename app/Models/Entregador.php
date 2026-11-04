<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Motoboy cadastrado para as rotas de entrega (SQL 39). Tabela "entregadores" — a "motoboys" antiga
 * é de outro módulo do sistema e não é usada aqui.
 * acerto: semanal (sábado a sexta) ou diario (avulso, recebe no dia).
 */
class Entregador extends Model
{
    protected $table = 'entregadores';
    protected $fillable = ['empresa_id', 'nome', 'telefone', 'acerto', 'ativo'];
    protected $casts = ['ativo' => 'boolean'];

    public const ACERTOS = ['semanal' => 'Semanal (sábado a sexta)', 'diario' => 'Avulso (recebe no dia)'];
}
