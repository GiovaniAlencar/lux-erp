<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotaFiscal extends Model
{
    protected $table = 'notas_fiscais';

    protected $fillable = [
        'empresa_id', 'venda_id', 'cliente_id', 'natureza_id', 'usuario_id', 'status', 'ambiente',
        'serie', 'numero', 'chave', 'protocolo', 'data_emissao', 'autorizada_em',
        'valor_produtos', 'valor_frete', 'valor_desconto', 'valor_outros', 'valor_total',
        'tipo_pagamento', 'info_complementar', 'ultimo_retorno', 'cancelada_em',
        'motivo_cancelamento', 'sequencia_cce',
    ];

    protected $casts = [
        'data_emissao' => 'datetime',
        'autorizada_em' => 'datetime',
        'cancelada_em' => 'datetime',
    ];

    public const STATUS = [
        'rascunho' => 'Rascunho',
        'rejeitada' => 'Rejeitada',
        'autorizada' => 'Autorizada',
        'cancelada' => 'Cancelada',
    ];

    public const PAGAMENTOS = [
        '17' => 'PIX',
        '01' => 'Dinheiro',
        '03' => 'Cartão de crédito',
        '04' => 'Cartão de débito',
        '15' => 'Boleto',
        '16' => 'Depósito bancário',
        '90' => 'Sem pagamento',
    ];

    public function itens()
    {
        return $this->hasMany(NotaFiscalItem::class, 'nota_fiscal_id');
    }

    public function venda()
    {
        return $this->belongsTo(Venda::class, 'venda_id');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function natureza()
    {
        return $this->belongsTo(NaturezaOperacao::class, 'natureza_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function editavel(): bool
    {
        return in_array($this->status, ['rascunho', 'rejeitada'], true);
    }

    public function recalcularTotais(): void
    {
        $this->loadMissing('itens');
        $prod = 0.0;
        foreach ($this->itens as $it) {
            $prod += $it->valorTotal();
        }
        $this->valor_produtos = round($prod, 2);
        $this->valor_total = round($prod + (float) $this->valor_frete + (float) $this->valor_outros - (float) $this->valor_desconto, 2);
    }

    /** XML da NF-e autorizada (também usado depois de cancelada, para o DANFE). */
    public function xmlPath(): ?string
    {
        if (!$this->chave) {
            return null;
        }
        $p = public_path('xml_nfe/' . $this->chave . '.xml');
        return file_exists($p) ? $p : null;
    }

    /** XML do evento: 'cancelamento' ou 'correcao' (última CC-e). */
    public function xmlEventoPath(string $tipo): ?string
    {
        if (!$this->chave) {
            return null;
        }
        $pasta = $tipo === 'cancelamento' ? 'xml_nfe_cancelada/' : 'xml_nfe_correcao/';
        $p = public_path($pasta . $this->chave . '.xml');
        return file_exists($p) ? $p : null;
    }
}
