<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Devolucao;
use App\Models\ConfigNota;
use App\Models\FormaPagamento;
use App\Models\Compra;

class Venda extends Model
{
    protected $fillable = [
        'cliente_id', 'usuario_id', 'frete_id', 'valor_total', 'forma_pagamento', 'numero_nfe',
        'natureza_id', 'chave', 'estado_emissao', 'observacao', 'desconto',
        'transportadora_id', 'sequencia_cce', 'tipo_pagamento', 'empresa_id',
        'pedido_ecommerce_id', 'origem', 'bandeira_cartao', 'cnpj_cartao', 'cAut_cartao',
        'descricao_pag_outros', 'acrescimo', 'frete', 'data_entrega', 'pedido_nuvemshop_id',
        'nSerie', 'data_emissao', 'filial_id', 'status_pedido', 'status_pagamento',
        'modo_preco_arabes', 'modo_preco_miniaturas', 'aviso_entrega',
        'versao_pedido', 'versao_ficha_impressa', 'versao_pedido_pdf',
        'ficha_impressa_em', 'pedido_pdf_em',
        'fechada_caixa', 'fechada_em', 'fechada_por_usuario_id',
        'nf_externa_status', 'nf_externa_numero', 'nf_externa_valor', 'nf_externa_em', 'nf_externa_usuario_id'
    ];

    protected $casts = [
        'fechada_caixa' => 'boolean',
        'fechada_em' => 'datetime',
        'ficha_impressa_em' => 'datetime',
        'pedido_pdf_em' => 'datetime',
        'nf_externa_em' => 'datetime',
    ];

    public function filial(){
        return $this->belongsTo(Filial::class, 'filial_id');
    }
    
    public function vendedor_setado()
    {
        return $this->belongsTo(Usuario::class, 'vendedor_id');
    }

    public function duplicatas()
    {
        return $this->hasMany(ContaReceber::class, 'venda_id', 'id');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function pedidoNuvemShop()
    {
        return $this->belongsTo(NuvemShopPedido::class, 'pedido_nuvemshop_id');
    }

    public function natureza()
    {
        return $this->belongsTo(NaturezaOperacao::class, 'natureza_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function vendedor()
    {
        $usuario = Usuario::find($this->usuario_id);
        if ($usuario->funcionario) return $usuario->funcionario->nome;
        else return '--';
    }

    public function frete()
    {
        return $this->belongsTo(Frete::class, 'frete_id');
    }

    public function transportadora()
    {
        return $this->belongsTo(Transportadora::class, 'transportadora_id');
    }

    public function itens()
    {
        return $this->hasMany(ItemVenda::class, 'venda_id', 'id');
    }

    public function auditorias()
    {
        return $this->hasMany(VendaAuditoria::class, 'venda_id', 'id');
    }

    public function referencias()
    {
        return $this->hasMany(NFeReferecia::class, 'venda_id', 'id');
    }

    public static function tiposPagamento()
    {
        return [
            '01' => 'Dinheiro',
            '02' => 'Cheque',
            '03' => 'Cartão de Crédito',
            '04' => 'Cartão de Débito',
            '05' => 'Crédito Loja',
            '06' => 'Crediário',
            '10' => 'Vale Alimentação',
            '11' => 'Vale Refeição',
            '12' => 'Vale Presente',
            '13' => 'Vale Combustível',
            '14' => 'Duplicata Mercantil',
            '15' => 'Boleto Bancário',
            '16' => 'Depósito Bancário',
            '17' => 'Pagamento Instantâneo (PIX)',
            '90' => 'Sem Pagamento',
            '99' => 'Outros',
        ];
    }

    public static function bandeiras()
    {
        return [
            '01' => 'Visa',
            '02' => 'Mastercard',
            '03' => 'American Express',
            '04' => 'Sorocred',
            '05' => 'Diners Club',
            '06' => 'Elo',
            '07' => 'Hipercard',
            '08' => 'Aura',
            '09' => 'Cabal',
            '99' => 'Outros'
        ];
    }

    public static function getTipo($tipo)
    {
        if(isset(Venda::tiposPagamento()[$tipo])){
			return Venda::tiposPagamento()[$tipo];
		}else{
			return "Não identificado";
		}
        // $tipos = Venda::tiposPagamento();
        // return $tipos[$tipo];
    }

    public static function getTipoPagamento2($tipo){
		if(isset(VendaCaixa::tiposPagamento()[$tipo])){
			return VendaCaixa::tiposPagamento()[$tipo];
		}else{
			return "Não identificado";
		}
	}

    public static function filtroData($dataInicial, $dataFinal, $estado, $tipoPesquisaData, $numero_nfe)
    {
        $value = session('user_logged');
        $empresa_id = $value['empresa'];
        $c = Venda::select('vendas.*')
            ->whereBetween($tipoPesquisaData, [
                $dataInicial,
                $dataFinal
            ])
            ->where('vendas.empresa_id', $empresa_id)
            ->where('vendas.forma_pagamento', '!=', 'conta_crediario');

        if ($estado != 'TODOS') $c->where('vendas.estado', $estado);
        if ($numero_nfe != "") {
            $c->where('NfNumero', $numero_nfe);
        }
        return $c->get();
    }

    public static function filtroDataCliente(
        $cliente,
        $dataInicial,
        $dataFinal,
        $estado,
        $tipoPesquisa,
        $tipoPesquisaData,
        $numero_nfe
    ) {

        $value = session('user_logged');
        $empresa_id = $value['empresa'];
        $c = Venda::select('vendas.*')
            ->join('clientes', 'clientes.id', '=', 'vendas.cliente_id')
            ->where('clientes.' . $tipoPesquisa, 'LIKE', "%$cliente%")
            ->where('vendas.forma_pagamento', '!=', 'conta_crediario')
            ->where('vendas.empresa_id', $empresa_id)

            ->whereBetween($tipoPesquisaData, [
                $dataInicial,
                $dataFinal
            ]);
        if ($numero_nfe != "") {
            $c->where('NfNumero', $numero_nfe);
        }

        if ($estado != 'TODOS') $c->where('vendas.estado', $estado);
        return $c->get();
    }

    public static function filtroCliente($cliente, $estado, $tipoPesquisa, $numero_nfe)
    {
        $value = session('user_logged');
        $empresa_id = $value['empresa'];
        $c = Venda::select('vendas.*')
            ->join('clientes', 'clientes.id', '=', 'vendas.cliente_id')
            ->where('clientes.' . $tipoPesquisa, 'LIKE', "%$cliente%")
            ->where('vendas.empresa_id', $empresa_id)
            ->where('vendas.forma_pagamento', '!=', 'conta_crediario');

        if ($estado != 'TODOS') $c->where('vendas.estado', $estado);
        if ($numero_nfe != "") {
            $c->where('NfNumero', $numero_nfe);
        }
        return $c->get();
    }

    public static function filtroEstado($estado, $numero_nfe)
    {
        $value = session('user_logged');
        $empresa_id = $value['empresa'];
        $c = Venda::where('vendas.estado', $estado)
            ->where('vendas.empresa_id', $empresa_id)
            ->where('vendas.forma_pagamento', '!=', 'conta_crediario');

        if ($numero_nfe != "") {
            $c->where('NfNumero', $numero_nfe);
        }
        return $c->get();
    }


    public static function filtroDataApp($dataInicial, $dataFinal, $estado, $empresa_id)
    {

        $c = Venda::select('vendas.*')
            ->whereBetween('data_registro', [
                $dataInicial,
                $dataFinal
            ])
            ->where('vendas.empresa_id', $empresa_id)
            ->where('vendas.forma_pagamento', '!=', 'conta_crediario');

        if ($estado != 'TODOS') $c->where('vendas.estado', $estado);

        return $c->get();
    }

    public static function filtroDataClienteApp($cliente, $dataInicial, $dataFinal, $estado, $empresa_id)
    {

        $c = Venda::select('vendas.*')
            ->join('clientes', 'clientes.id', '=', 'vendas.cliente_id')
            ->where('clientes.razao_social', 'LIKE', "%$cliente%")
            ->where('vendas.forma_pagamento', '!=', 'conta_crediario')
            ->where('vendas.empresa_id', $empresa_id)

            ->whereBetween('data_registro', [
                $dataInicial,
                $dataFinal
            ]);

        if ($estado != 'TODOS') $c->where('vendas.estado', $estado);
        return $c->get();
    }

    public static function filtroClienteApp($cliente, $estado, $empresa_id)
    {

        $c = Venda::select('vendas.*')
            ->join('clientes', 'clientes.id', '=', 'vendas.cliente_id')
            ->where('clientes.razao_social', 'LIKE', "%$cliente%")
            ->where('vendas.empresa_id', $empresa_id)
            ->where('vendas.forma_pagamento', '!=', 'conta_crediario');

        if ($estado != 'TODOS') $c->where('vendas.estado', $estado);

        return $c->get();
    }

    public static function filtroEstadoApp($estado, $empresa_id)
    {

        $c = Venda::where('vendas.estado', $estado)
            ->where('vendas.empresa_id', $empresa_id)
            ->where('vendas.forma_pagamento', '!=', 'conta_crediario');
        return $c->get();
    }

    public function getTipoPagamento()
    {
        foreach (Venda::tiposPagamento() as $key => $t) {
            if ($this->tipo_pagamento == $key) return $t;
        }
    }

    public static function getTipoPagamentoNFe($tipo)
    {
        $values = [
            'Dinheiro' => '01',
            'Cheque' => '02',
            'Cartão de Crédito' => '03',
            'Cartão de Débito' => '04',
            'Crédito Loja' => '05',
            'Crediário' => '06',
            'Vale Alimentação' => '10',
            'Vale Refeição' => '11',
            'Vale Presente' => '12',
            'Vale Combustível' => '13',
            'Duplicata Mercantil' => '14',
            'Boleto Bancário' => '15',
            'Depósito Bancário' => '16',
            'Pagamento Instantâneo (PIX)' => '17',
            'Sem Pagamento' => '90',
            'Outros' => '99',
        ];
        try {
            return $values[$tipo];
        } catch (\Exception $e) {
            return $values["Dinheiro"];
        }
    }

    public function estadoEmissao()
    {
        if ($this->estado_emissao == 'aprovado') {
            return "<span class='btn btn-sm btn-success'>Aprovado</span>";
        } else if ($this->estado_emissao == 'cancelado') {
            return "<span class='btn btn-sm btn-danger'>Cancelado</span>";
        } else if ($this->estado_emissao == 'rejeitado') {
            return "<span class='btn btn-sm btn-warning'>Rejeitado</span>";
        }
        return "<span class='btn btn-sm btn-info'>Novo</span>";
    }

    public static function estados()
    {
        return [
            "AC",
            "AL",
            "AM",
            "AP",
            "BA",
            "CE",
            "DF",
            "ES",
            "GO",
            "MA",
            "MG",
            "MS",
            "MT",
            "PA",
            "PB",
            "PE",
            "PI",
            "PR",
            "RJ",
            "RN",
            "RS",
            "RO",
            "RR",
            "SC",
            "SE",
            "SP",
            "TO",

        ];
    }

    public function multiplo()
    {
        return "Outros";
    }

    public function taxaFormaPagamento()
    {
        $value = session('user_logged');
        $empresa_id = $value['empresa'];
        $formaPag = FormaPagamento::where('nome', $this->forma_pagamento)
            ->where('empresa_id', $empresa_id)
            ->first();
        if ($formaPag != null) {
            if ($formaPag->tipo_taxa == 'perc') {
                return number_format($formaPag->taxa, 2, ',', '.') . '%';
            } else {
                return 'R$ ' . number_format($formaPag->taxa, 2, ',', '.');
            }
        } else {
            return "0,00";
        }
    }

    /**
     * Divide o total da venda entre a conta FISCAL (itens com NF-e emitida no outro sistema)
     * e a conta NÃO FISCAL (restante). Uso interno: vendedor e fechamento de caixa.
     *
     * Regras:
     * - cada item usa a marcação "fiscal" gravada no item no momento da venda;
     * - frete, desconto e acréscimo ficam TODOS na parte NÃO FISCAL (conta 2);
     *   a parte fiscal fica com o valor cheio dos itens fiscais (= valor da NF-e);
     * - se o pedido só tem itens fiscais (não existe conta 2), tudo vai para o fiscal;
     * - se o desconto for maior que a conta 2 inteira, o que sobrar desconta do fiscal;
     * - o não fiscal é calculado como (total - fiscal), então a soma sempre fecha no centavo.
     *
     * @return array{fiscal: float, nao_fiscal: float, total: float, itens_fiscal: float, itens_nao_fiscal: float, tem_fiscal: bool, misto: bool}
     */
    public function divisaoFiscal(): array
    {
        $itensFiscal = 0.0;
        $itensNaoFiscal = 0.0;

        foreach ($this->itens as $it) {
            $qf = min((float) $it->quantidade, max(0, (float) ($it->qtd_fiscal ?? 0)));
            $fis = round((float) $it->valor * $qf, 2);
            $itensFiscal += $fis;
            $itensNaoFiscal += round((float) $it->valor * (float) $it->quantidade, 2) - $fis;
        }

        return self::calcularDivisaoFiscal(
            $itensFiscal,
            $itensNaoFiscal,
            (float) $this->valor_total,
            (float) ($this->desconto ?? 0),
            (float) ($this->acrescimo ?? 0),
            (float) ($this->frete ?? 0)
        );
    }

    /**
     * Regra pura da divisão (usada pela venda e pelos totais da listagem).
     */
    public static function calcularDivisaoFiscal(
        float $itensFiscal,
        float $itensNaoFiscal,
        float $valorTotal,
        float $desconto,
        float $acrescimo,
        float $frete
    ): array {
        $total = round($valorTotal - $desconto + $acrescimo + $frete, 2);

        $fiscal = 0.0;
        if ($itensFiscal > 0) {
            if ($itensNaoFiscal <= 0) {
                // não existe conta 2: frete/desconto/acréscimo ficam no fiscal
                $fiscal = $total;
            } else {
                $baseNaoFiscal = $itensNaoFiscal + $acrescimo + $frete;
                $excedente = max(0, $desconto - $baseNaoFiscal);
                $fiscal = $itensFiscal - $excedente;
            }
            $fiscal = round(max(0, min($fiscal, $total)), 2);
        }

        return [
            'fiscal' => $fiscal,
            'nao_fiscal' => round($total - $fiscal, 2),
            'total' => $total,
            'itens_fiscal' => round($itensFiscal, 2),
            'itens_nao_fiscal' => round($itensNaoFiscal, 2),
            'tem_fiscal' => $itensFiscal > 0,
            'misto' => $itensFiscal > 0 && $itensNaoFiscal > 0,
        ];
    }

    /**
     * Soma a divisão fiscal de várias vendas de uma vez (1 query, sem carregar models).
     * Recebe uma query de Venda já filtrada.
     */
    public static function resumoDivisaoFiscal($queryVendas): array
    {
        $ids = (clone $queryVendas)->reorder()->select('vendas.id');

        $rows = \Illuminate\Support\Facades\DB::table('vendas as v')
            ->leftJoin('item_vendas as iv', 'iv.venda_id', '=', 'v.id')
            ->whereIn('v.id', $ids)
            ->groupBy('v.id', 'v.valor_total', 'v.desconto', 'v.acrescimo', 'v.frete')
            ->select(
                'v.id',
                'v.valor_total',
                'v.desconto',
                'v.acrescimo',
                'v.frete',
                \Illuminate\Support\Facades\DB::raw('COALESCE(SUM(ROUND(iv.valor * LEAST(iv.qtd_fiscal, iv.quantidade), 2)), 0) as itens_fiscal'),
                \Illuminate\Support\Facades\DB::raw('COALESCE(SUM(ROUND(iv.valor * iv.quantidade, 2) - ROUND(iv.valor * LEAST(iv.qtd_fiscal, iv.quantidade), 2)), 0) as itens_nao_fiscal')
            )
            ->get();

        $res = ['fiscal' => 0.0, 'nao_fiscal' => 0.0, 'total' => 0.0, 'qtd' => 0, 'qtd_com_fiscal' => 0];
        foreach ($rows as $r) {
            $d = self::calcularDivisaoFiscal(
                (float) $r->itens_fiscal,
                (float) $r->itens_nao_fiscal,
                (float) $r->valor_total,
                (float) ($r->desconto ?? 0),
                (float) ($r->acrescimo ?? 0),
                (float) ($r->frete ?? 0)
            );
            $res['fiscal'] += $d['fiscal'];
            $res['nao_fiscal'] += $d['nao_fiscal'];
            $res['total'] += $d['total'];
            $res['qtd']++;
            if ($d['tem_fiscal']) {
                $res['qtd_com_fiscal']++;
            }
        }
        $res['fiscal'] = round($res['fiscal'], 2);
        $res['nao_fiscal'] = round($res['nao_fiscal'], 2);
        $res['total'] = round($res['total'], 2);

        return $res;
    }

    /**
     * Situação da NF-e externa: null (venda sem item fiscal), 'pendente', 'emitida' ou 'divergente'
     * (marcada como emitida, mas o valor fiscal da venda mudou depois).
     */
    public function situacaoNfExterna(?array $div = null): ?string
    {
        $div = $div ?? $this->divisaoFiscal();
        if (!$div['tem_fiscal']) {
            return $this->nf_externa_status === 'emitida' ? 'divergente' : null;
        }
        if ($this->nf_externa_status !== 'emitida') {
            return 'pendente';
        }
        if ($this->nf_externa_valor !== null && abs((float) $this->nf_externa_valor - $div['fiscal']) > 0.009) {
            return 'divergente';
        }
        return 'emitida';
    }

    public function nfExternaUsuario()
    {
        return $this->belongsTo(Usuario::class, 'nf_externa_usuario_id');
    }

    /** Itens fiscais (para lançar a NF-e no outro sistema). */
    public function itensFiscais()
    {
        return $this->itens->filter(function ($it) {
            return (float) ($it->qtd_fiscal ?? 0) > 0;
        })->values();
    }

    /** NF-e autorizada (emitida pelo ERP) ligada a esta venda, se houver. */
    public function notaFiscalAutorizada()
    {
        return NotaFiscal::where('venda_id', $this->id)->where('status', 'autorizada')->first();
    }

    public function valorLiquido()
    {
        $value = session('user_logged');
        $empresa_id = $value['empresa'];
        $formaPag = FormaPagamento::where('nome', $this->forma_pagamento)
            ->where('empresa_id', $empresa_id)
            ->first();
        if ($formaPag != null) {
            $total = $this->valor_total + $this->acrescimo - $this->desconto;
            $valor = 0;
            if ($formaPag->tipo_taxa == 'perc') {
                $valor = $total - (($total * $formaPag->taxa) / 100);
            } else {
                $valor = $total - $formaPag->taxa;
            }

            return $valor;
        } else {
            return $this->valor_total - $this->desconto + $this->acrescimo;
        }
    }

    public function valorDespesaOperacionais()
    {
        $value = session('user_logged');
        $empresa_id = $value['empresa'];
        $formaPag = FormaPagamento::where('nome', $this->forma_pagamento)
            ->where('empresa_id', $empresa_id)
            ->first();
        if ($formaPag != null) {
            $total = $this->valor_total + $this->acrescimo - $this->desconto;
            $valor = 0;
            if ($formaPag->tipo_taxa == 'perc') {
                $valor = (($total * $formaPag->taxa) / 100);
            } else {
                $valor = $formaPag->taxa;
            }

            return 'R$ ' . number_format($valor, 2, ',', '.');
        } else {
            return "R$ 0,00";
        }
    }

    public function getFormaPagamento($empresa_id)
    {
        $forma = FormaPagamento::where('chave', $this->forma_pagamento)
            ->where('empresa_id', $empresa_id)
            ->first();

        return $forma;
    }

    public static function randSuccess()
    {
        $arr = [
            'success.json',
            'success2.json',
            'success3.json',
            'success4.json',
        ];
        $rand = rand(0, sizeof($arr) - 1);
        return $arr[$rand];
    }
}
