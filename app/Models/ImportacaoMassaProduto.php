<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportacaoMassaProduto extends Model
{
    protected $fillable = [
        'empresa_id',
        'usuario_id',
        'fornecedor_id',
        'compra_id',
        'arquivo_nome',
        'qtd_itens',
        'qtd_itens_validos',
        'qtd_itens_erro',
        'qtd_unidades',
        'valor_total_compra',
        'confirmado_em',
    ];

    protected $casts = [
        'confirmado_em' => 'datetime',
        'qtd_unidades' => 'float',
        'valor_total_compra' => 'float',
    ];

    public function itens()
    {
        return $this->hasMany(ImportacaoMassaProdutoItem::class, 'importacao_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function fornecedor()
    {
        return $this->belongsTo(Fornecedor::class, 'fornecedor_id');
    }

    public function compra()
    {
        return $this->belongsTo(Compra::class, 'compra_id');
    }
}
