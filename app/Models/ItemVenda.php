<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemVenda extends Model
{
    protected $fillable = [
        'produto_id', 'venda_id', 'quantidade', 'valor', 'cfop', 'altura', 'largura', 'profundidade',
        'acrescimo_perca', 'esquerda', 'direita', 'inferior', 'superior', 'valor_custo', 
        'quantidade_dimensao', 'x_pedido', 'num_item_pedido', 'fiscal'
    ];

    protected $casts = [
        'fiscal' => 'boolean',
    ];

    /**
     * Ao criar o item, copia a marcação "fiscal" do cadastro do produto (se não veio explícita).
     * Vale para todos os caminhos que criam itens (venda manual, edição, clone, e-commerce...).
     */
    protected static function booted()
    {
        static::creating(function (ItemVenda $item) {
            if (!array_key_exists('fiscal', $item->getAttributes()) || $item->getAttributes()['fiscal'] === null) {
                $produto = $item->produto_id ? Produto::find($item->produto_id) : null;
                $item->fiscal = $produto ? (bool) ($produto->fiscal ?? false) : false;
            }
        });
    }

    public function subtotal(): float
    {
        return round((float) $this->valor * (float) $this->quantidade, 2);
    }

    public function produto(){
        return $this->belongsTo(Produto::class, 'produto_id');
    }

    public function venda(){
        return $this->belongsTo(Venda::class, 'venda_id');
    }
    
    public function percentualUf($uf){
        $tributacao = TributacaoUf
        ::where('uf', $uf)
        ->where('produto_id', $this->produto_id)
        ->first();

        return $tributacao;
    }

}
