<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendaAuditoria extends Model
{
    protected $fillable = [
        'empresa_id',
        'venda_id',
        'usuario_id',
        'acao',
        'descricao',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function venda()
    {
        return $this->belongsTo(Venda::class, 'venda_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
