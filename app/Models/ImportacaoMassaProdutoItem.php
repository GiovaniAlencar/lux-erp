<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportacaoMassaProdutoItem extends Model
{
    protected $table = 'importacao_massa_produto_itens';

    protected $fillable = [
        'importacao_id',
        'produto_id',
        'codigo_informado',
        'produto_nome',
        'estoque_anterior',
        'quantidade',
        'estoque_final',
        'custo_anterior',
        'custo_novo',
        'preco_1_anterior',
        'preco_1_novo',
        'preco_2_anterior',
        'preco_2_novo',
        'preco_3_anterior',
        'preco_3_novo',
        'valor_linha',
        'valido',
        'erro',
    ];

    protected $casts = [
        'valido' => 'boolean',
        'estoque_anterior' => 'float',
        'quantidade' => 'float',
        'estoque_final' => 'float',
        'valor_linha' => 'float',
    ];

    public function importacao()
    {
        return $this->belongsTo(ImportacaoMassaProduto::class, 'importacao_id');
    }

    public function produto()
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }
}
