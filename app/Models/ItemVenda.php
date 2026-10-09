<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemVenda extends Model
{
    protected $fillable = [
        'produto_id', 'venda_id', 'quantidade', 'valor', 'cfop', 'altura', 'largura', 'profundidade',
        'acrescimo_perca', 'esquerda', 'direita', 'inferior', 'superior', 'valor_custo', 
        'quantidade_dimensao', 'x_pedido', 'num_item_pedido', 'fiscal', 'qtd_fiscal'
    ];

    protected $casts = [
        'fiscal' => 'boolean',
    ];

    /**
     * Parte fiscal do item = qtd_fiscal (unidades com nota). Definida na venda pelo saldo fiscal.
     * Caminhos que não informam (site, clone etc.) ficam como não fiscal (conta 2).
     */
    protected static function booted()
    {
        static::saving(function (ItemVenda $item) {
            $attrs = $item->getAttributes();
            if (!array_key_exists('qtd_fiscal', $attrs) || $attrs['qtd_fiscal'] === null) {
                $item->qtd_fiscal = 0;
            }
            $q = (float) $item->qtd_fiscal;
            $max = (float) $item->quantidade;
            if ($q < 0) {
                $q = 0;
            }
            if ($q > $max) {
                $q = $max;
            }
            $item->qtd_fiscal = round($q, 3);
            $item->fiscal = $q > 0;
        });
    }

    public function qtdNaoFiscal(): float
    {
        return max(0, round((float) $this->quantidade - (float) $this->qtd_fiscal, 3));
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
