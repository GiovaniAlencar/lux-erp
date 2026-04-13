<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Models\NaturezaOperacao;
use App\Models\Estoque;
use App\Models\ItemVenda;
use App\Models\ItemCompra;
use App\Models\ItemVendaCaixa;
use App\Models\AlteracaoEstoque;

class Produto extends Model
{
	protected $fillable = [
		'nome',
		'categoria_id',
		'cor',
		'valor_venda',
		'NCM',
		'CST_CSOSN',
		'CST_PIS',
		'CST_COFINS',
		'CST_IPI',
		'unidade_compra',
		'unidade_venda',
		'composto',
		'codBarras',
		'conversao_unitaria',
		'valor_livre',
		'perc_icms',
		'perc_pis',
		'perc_cofins',
		'perc_ipi',
		'CFOP_saida_estadual',
		'CFOP_saida_inter_estadual',
		'codigo_anp',
		'descricao_anp',
		'perc_iss',
		'cListServ',
		'imagem',
		'alerta_vencimento',
		'valor_compra',
		'gerenciar_estoque',
		'estoque_minimo',
		'referencia',
		'empresa_id',
		'largura',
		'comprimento',
		'altura',
		'peso_liquido',
		'peso_bruto',
		'limite_maximo_desconto',
		'pRedBC',
		'cBenef',
		'percentual_lucro',
		'CST_CSOSN_EXP',
		'referencia_grade',
		'grade',
		'str_grade',
		'perc_glp',
		'perc_gnn',
		'perc_gni',
		'valor_partida',
		'unidade_tributavel',
		'quantidade_tributavel',
		'perc_icms_interestadual',
		'perc_icms_interno',
		'perc_fcp_interestadual',
		'inativo',
		'CEST',
		'sub_categoria_id',
		'marca_id',
		'referencia_balanca',
		'renavam',
		'placa',
		'chassi',
		'combustivel',
		'ano_modelo',
		'cor_veiculo',
		'reajuste_automatico',
		'valor_locacao',
		'lote',
		'vencimento',
		'origem',
		'tipo_dimensao',
		'perc_comissao',
		'acrescimo_perca',
		'nuvemshop_id',
		'info_tecnica_composto',
		'CST_CSOSN_entrada',
		'CST_PIS_entrada',
		'CST_COFINS_entrada',
		'CST_IPI_entrada',
		'CFOP_entrada_estadual',
		'CFOP_entrada_inter_estadual',
		'derivado_petroleo',
		'custo_assessor',
		'envia_controle_pedidos',
		'cenq_ipi',
		'tela_pedido_id',
		'ifood_id',
		'modBCST',
		'modBC',
		'pICMSST',
		'locais',
		'perc_frete',
		'perc_outros',
		'perc_mlv',
		'perc_mva',
		'perc_reducao'
	];

	public function locais_produto()
	{
		$locais_ativos = $this->locais ? json_decode($this->locais) : [];
		$html = "";
		try {
			foreach ($locais_ativos as $l) {
				$f = Filial::find($l);
				if ($l == '-1') {
					$html .= "Matriz | ";
				} else {
					if ($f != null) {
						$html .= "$f->descricao | ";
					}
				}
			}
			$html = substr($html, 0, strlen($html) - 2);
			if ($html == "") {
				$html = "Matriz";
			}
			return $html;
		} catch (\Exception $e) {
			return $html;
		}
	}

	public function getImgAttribute()
	{
		if (!$this->imagem) {
			return "/imgs/no_product.png";
		}
		return "/uploads/products/$this->imagem";
	}


	public function categoria()
	{
		return $this->belongsTo(Categoria::class, 'categoria_id');
	}

	public function subCategoria()
	{
		return $this->belongsTo(SubCategoria::class, 'sub_categoria_id');
	}

	public function empresa()
	{
		return $this->belongsTo(Empresa::class, 'empresa_id');
	}

	public function ibpt()
	{
		return $this->hasOne('App\Models\ProdutoIbpt', 'produto_id', 'id');
	}

	public function receita()
	{
		return $this->hasOne('App\Models\Receita', 'produto_id', 'id');
	}

	public function estoque()
	{
		return $this->hasOne(Estoque::class, 'produto_id', 'id');
	}

	public function delivery()
	{
		return $this->hasOne(ProdutoDelivery::class, 'produto_id', 'id')->with('pizza')->with('categoria');
	}

	public function ecommerce()
	{
		return $this->hasOne(ProdutoEcommerce::class, 'produto_id', 'id');
	}

	public function listaPreco()
	{
		return $this->hasMany('App\Models\ProdutoListaPreco', 'produto_id', 'id');
	}

	public function AlteracaoEstoque()
	{
		return $this->hasMany('App\Models\AlteracaoEstoque', 'produto_id', 'id');
	}

	public function valoresGrade()
	{
		$config = $this->empresa->configNota;

		$produtosGrade = Produto::where('referencia_grade', $this->referencia_grade)
			->get();
		$valores = "";
		foreach ($produtosGrade as $p) {
			$valores .= " " . number_format($p->valor_venda, $config != null ? $config->casas_decimais : 2, ',', '.') . " | ";
		}
		$valores = substr($valores, 0, strlen($valores) - 2);
		return $valores;
	}

	public static function mediaLucro()
	{
		$value = session('user_logged');
		$empresa_id = $value['empresa'];

		$media = Produto::selectRaw("AVG(percentual_lucro) as media")
			->where('empresa_id', $empresa_id)
			->first();
		if ($media != null) {
			return number_format($media->media, 2) . "%";
		} else {
			return "--";
		}
	}

	public static function verificaCadastrado($ean, $nome, $referencia)
	{
		$value = session('user_logged');
		$empresa_id = $value['empresa'];

		$result = null;
		$result = Produto::where('referencia', $referencia)
			->where('empresa_id', $empresa_id)
			->first();

		if (!$result) {
			$result = Produto::where('nome', $nome)
				->where('empresa_id', $empresa_id)
				->first();
		}

		if (!$result) {
			$result = Produto::where('codBarras', $ean)
				->where('codBarras', '!=', 'SEM GTIN')
				->where('empresa_id', $empresa_id)
				->first();
		} else {
			if ($result->codBarras != 0 && $result->codBarras != $ean) {
				return null;
			}
		}

		//verifica por codBarras e nome o PROD

		return $result;
	}

	public static function unidadesMedida()
	{
		return [
			"AMPOLA" => "AMPOLA",
			"BALDE" => "BALDE",
			"BANDEJ" => "BANDEJ",
			"BARRA" => "BARRA",
			"BISNAG" => "BISNAG",
			"BLOCO" => "BLOCO",
			"BOBINA" => "BOBINA",
			"BOMB" => "BOMB",
			"CAPS" => "CAPS",
			"CART" => "CART",
			"CENTO" => "CENTO",
			"CJ" => "CJ",
			"CM" => "CM",
			"CM2" => "CM2",
			"CX" => "CX",
			"CX2" => "CX2",
			"CX3" => "CX3",
			"CX5" => "CX5",
			"CX10" => "CX10",
			"CX12" => "CX12",
			"CX15" => "CX15",
			"CX20" => "CX20",
			"CX25" => "CX25",
			"CX50" => "CX50",
			"CX100" => "CX100",
			"DISP" => "DISP",
			"DUZIA" => "DUZIA",
			"EMBAL" => "EMBAL",
			"FARDO" => "FARDO",
			"FOLHA" => "FOLHA",
			"FRASCO" => "FRASCO",
			"GALAO" => "GALAO",
			"GF" => "GF",
			"GRAMAS" => "GRAMAS",
			"JOGO" => "JOGO",
			"KG" => "KG",
			"KIT" => "KIT",
			"LATA" => "LATA",
			"LITRO" => "LITRO",
			"M" => "M",
			"M2" => "M2",
			"M3" => "M3",
			"MILHEI" => "MILHEI",
			"ML" => "ML",
			"MWH" => "MWH",
			"PACOTE" => "PACOTE",
			"PALETE" => "PALETE",
			"PARES" => "PARES",
			"PC" => "PC",
			"POTE" => "POTE",
			"K" => "K",
			"RESMA" => "RESMA",
			"ROLO" => "ROLO",
			"SACO" => "SACO",
			"SACOLA" => "SACOLA",
			"TAMBOR" => "TAMBOR",
			"TANQUE" => "TANQUE",
			"TON" => "TON",
			"TUBO" => "TUBO",
			"UN" => "UN",
			"VASIL" => "VASIL",
			"VIDRO" => "VIDRO"
		];
	}

	public static function listaCST()
	{
		return [
			'00' => 'Tributa integralmente',
			'10' => 'Tributada e com cobrança do ICMS por substituição tributária',
			'20' => 'Com redução da Base de Calculo',
			'30' => 'Isenta / não tributada e com cobrança do ICMS por substituição tributária',
			'40' => 'Isenta',
			'41' => 'Não tributada',
			'50' => 'Com suspensão',
			'51' => 'Com diferimento',
			'60' => 'ICMS cobrado anteriormente por substituição tributária',
			'61' => '61 - ICMS Monofásico',
			'70' => 'Com redução da BC e cobrança do ICMS por substituição tributária',
			'90' => 'Outras'
		];
	}

	public static function listaCSOSN()
	{
		return [
			'101' => 'Tributada pelo Simples Nacional com permissão de crédito',
			'102' => 'Tributada pelo Simples Nacional sem permissão de crédito',
			'103' => 'Isenção do ICMS no Simples Nacional para faixa de receita bruta',
			'201' => 'Tributada pelo Simples Nacional com permissão de crédito e com cobrança do ICMS por substituição tributária',
			'202' => 'Tributada pelo Simples Nacional sem permissão de crédito e com cobrança do ICMS por substituição tributária',
			'203' => 'Isenção do ICMS no Simples Nacional para faixa de receita bruta e com cobrança do ICMS por substituição tributária',
			'300' => 'Imune',
			'400' => 'Não tributada pelo Simples Nacional',
			'500' => 'ICMS cobrado anteriormente por substituição tributária (substituído) ou por antecipação',
			'900' => 'Outros',
			'61' => '61 - ICMS Monofásico',
		];
	}

	public static function listaCSTCSOSN()
	{
		return [
			'00' => '00 - Tributa integralmente',
			'10' => '10 - Tributada e com cobrança do ICMS por substituição tributária',
			'20' => '20 - Com redução da Base de Calculo',
			'30' => '30 - Isenta / não tributada e com cobrança do ICMS por substituição tributária',
			'40' => '40 - Isenta',
			'41' => '41 - Não tributada',
			'50' => '50 - Com suspensão',
			'51' => '51 - Com diferimento',
			'60' => '60 - ICMS cobrado anteriormente por substituição tributária',
			'61' => '61 - ICMS Monofásico',
			'70' => '70 - Com redução da BC e cobrança do ICMS por substituição tributária',
			'90' => '90 - Outras',
			'101' => '101 - Tributada pelo Simples Nacional com permissão de crédito',
			'102' => '102 - Tributada pelo Simples Nacional sem permissão de crédito',
			'103' => '103 - Isenção do ICMS no Simples Nacional para faixa de receita bruta',
			'201' => '201 - Tributada pelo Simples Nacional com permissão de crédito e com cobrança do ICMS por substituição tributária',
			'202' => '202 - Tributada pelo Simples Nacional sem permissão de crédito e com cobrança do ICMS por substituição tributária',
			'203' => '203 - Isenção do ICMS no Simples Nacional para faixa de receita bruta e com cobrança do ICMS por substituição tributária',
			'300' => '300 - Imune',
			'400' => '400 - Não tributada pelo Simples Nacional',
			'500' => '500 - ICMS cobrado anteriormente por substituição tributária (substituído) ou por antecipação',
			'900' => '900 - Outros'
		];
	}

	public static function listaCST_PIS_COFINS()
	{
		return [
			'01' => '01 - Operação Tributável com Alíquota Básica',
			'02' => '02 - Operação Tributável com Alíquota por Unidade de Medida de Produto',
			'03' => '03 - Operação Tributável com Alíquota por Unidade de Medida de Produto',
			'04' => '04 - Operação Tributável Monofásica – Revenda a Alíquota Zero',
			'05' => '05 - Operação Tributável por Substituição Tributária',
			'06' => '06 - Operação Tributável a Alíquota Zero',
			'07' => '07 - Operação Isenta da Contribuição',
			'08' => '08 - Operação sem Incidência da Contribuição',
			'09' => '09 - Operação com Suspensão da Contribuição',
			'49' => '49 - Outras Operações de Saída'
		];
	}

	public static function listaCST_IPI()
	{
		return [
			'50' => '50 - Saída Tributada',
			'51' => '51 - Saída Tributável com Alíquota Zero',
			'52' => '52 - Saída Isenta',
			'53' => '53 - Saída Não Tributada',
			'54' => '54 - Saída Imune',
			'55' => '55 - Saída com Suspensão',
			'99' => '99 - Outras Saídas'
		];
	}

	public static function listaCST_IPI_Entrada()
	{
		return [
			'00' => '00 - Entrada com Recuperação de Crédito',
			'01' => '01 - Entrada Tributada com Alíquota Zero',
			'02' => '02 - Entrada Isenta',
			'03' => '03 - Entrada não Tributada',
			'04' => '04 - Entrada Imune',
			'05' => '05 - Entrada com Suspensão',
			'49' => '49 - Outras Entradas',
		];
	}

	public static function listaCST_PIS_COFINS_Entrada()
	{
		return [
			'50' => '50 - Operação com Direito a Crédito – Vinculado Exclusivamente a Receita Tributada no Mercado Interno',
			'51' => '51 - Operação com Direito a Crédito – Vinculado Exclusivamente a Receita Não Tributada no Mercado Interno',
			'52' => '52 - Operação com Direito a Crédito – Vinculado Exclusivamente a Receita de Exportação',
			'53' => '53 - Operação com Direito a Crédito – Vinculado a Receitas Tributadas e Não-Tributadas no Mercado Interno',
			'54' => '54 - Operação com Direito a Crédito – Vinculado a Receitas Tributadas no Mercado Interno e de Exportação',
			'55' => '55 - Operação com Direito a Crédito – Vinculado a Receitas Não-Tributadas no Mercado Interno e de Exportação',
			'56' => '56 - Operação com Direito a Crédito – Vinculado a Receitas Tributadas e Não-Tributadas no Mercado Interno, e de Exportação',
			'60' => '60 - Crédito Presumido – Operação de Aquisição Vinculada Exclusivamente a Receita Tributada no Mercado Interno',
			'61' => '61 - Crédito Presumido – Operação de Aquisição Vinculada Exclusivamente a Receita Não-Tributada no Mercado Interno',
			'62' => '62 - Crédito Presumido – Operação de Aquisição Vinculada Exclusivamente a Receita de Exportação',
			'63' => '63 - Crédito Presumido – Operação de Aquisição Vinculada a Receitas Tributadas e Não-Tributadas no Mercado Interno',
			'64' => '64 - Crédito Presumido – Operação de Aquisição Vinculada a Receitas Tributadas no Mercado Interno e de Exportação',
			'65' => '65 - Crédito Presumido – Operação de Aquisição Vinculada a Receitas Não-Tributadas no Mercado Interno e de Exportação',
			'66' => '66 - Crédito Presumido – Operação de Aquisição Vinculada a Receitas Tributadas e Não-Tributadas no Mercado Interno, e de Exportação',
			'67' => '67 - Crédito Presumido – Outras Operações',
			'70' => '70 - Operação de Aquisição sem Direito a Crédito',
			'71' => '71 - Operação de Aquisição com Isenção',
			'72' => '72 - Operação de Aquisição com Suspensão',
			'73' => '73 - Operação de Aquisição a Alíquota Zero',
			'74' => '74 - Operação de Aquisição sem Incidência da Contribuição',
			'75' => '75 - Operação de Aquisição por Substituição Tributária',
			'98' => '98 - Outras Operações de Entrada',
			'99' => '99 - Outras Operações',
		];
	}

	public static function firstNatureza($empresa_id)
	{
		return NaturezaOperacao::where('empresa_id', $empresa_id)
			->first();
	}

	public static function lista_ANP()
	{
		return [
			'210101001' => 	'GAS COMBUSTIVEL',
			'420301002' =>	'OUTROS OLEOS DIESEL',
			'210201001' =>	'PROPANO',
			'420301003' =>	'OLEO DIESEL FORA DE ESPECIFICACAO',
			'210201002' =>	'PROPANO ESPECIAL',
			'510101001' =>	'OLEO COMBUSTIVEL A1',
			'210201003' =>	'PROPENO',
			'510101002' =>	'OLEO COMBUSTIVEL A2',
			'210202001' =>	'BUTANO',
			'510101003' =>	'OLEO COMBUSTIVEL A FORA DE ESPECIFICACAO',
			'210202002' =>	'BUTANO ESPECIAL',
			'510102001' =>	'OLEO COMBUSTIVEL B1',
			'210202003' =>	'BUTADIENO',
			'510102002' =>	'OLEO COMBUSTIVEL B2',
			'210203001' =>	'GLP',
			'510102003' =>	'OLEO COMBUSTIVEL B FORA DE ESPECIFICACAO',
			'210203002' =>	'GLP FORA DE ESPECIFICACAO',
			'510201001' =>	'OLEO COMBUSTIVEL MARITIMO',
			'210204001' =>	'GAS LIQUEFEITO INTERMEDIARIO',
			'510201002' =>	'OLEO COMBUSTIVEL MARÍTIMO FORA DE ESPECIFICACAO',
			'210204002' =>	'OUTROS GASES LIQUEFEITOS',
			'510201003' =>	'OLEO COMBUSTIVEL MARÍTIMO MISTURA (MF)',
			'210301001' =>	'ETANO',
			'510301001' =>	'OUTROS OLEOS COMBUSTIVEIS',
			'210301002' =>	'ETENO',
			'510301002' =>	'ÓLEOS COMBUSTIVEIS PARA EXPORTACAO',
			'210302001' =>	'OUTROS GASES	',
			'510301003' =>	'OLEO COMBUSTIVEL PARA GERAAOO ELETRICA',
			'210302002' =>	'GAS INTERMEDIARIO',
			'540101001' =>	'COQUE VERDE',
			'210302003' =>	'GAS DE XISTO',
			'540101002' =>	'COQUE CALCINADO',
			'210302004' =>	'GAS ACIDO',
			'810101001' =>	'ETANOL HIDRATADO COMUM',
			'220101001' =>	'GAS NATURAL UMIDO',
			'810101002' =>	'ETANOL HIDRATADO ADITIVADO',
			'220101002' =>	'GAS NATURAL SECO',
			'810101003' =>	'ETANOL HIDRATADO FORA DE ESPECIFICACAO',
			'220101003' =>	'GAS NATURAL COMPRIMIDO',
			'810102001' =>	'ETANOL ANIDRO',
			'220101004' =>	'GAS NATURAL LIQUEFEITO',
			'810102002' =>	'ETANOL ANIDRO FORA DE ESPECIFICACAO',
			'220101005' =>	'GAS NATURAL VEICULAR',
			'810102003' =>	'ETANOL ANIDRO PADRAO',
			'220101006' =>	'GAS NATURAL VEICULAR PADRAO',
			'810102004' =>	'ETANOL ANIDRO COM CORANTE',
			'220102001' =>	'GASOLINA NATURAL (C5+)',
			'810201001' =>	'ALCOOL METILICO',
			'220102002' =>	'LIQUIDO DE GAS NATURAL',
			'810201002' =>	'OUTROS ALCOOIS',
			'320101001' =>	'GASOLINA A COMUM',
			'820101001' =>	'BIODIESEL B100',
			'320101002' =>	'GASOLINA A PREMIUM	',
			'820101002' =>	'DIESEL B4 S1800 - COMUM',
			'320101003' =>	'GASOLINA A FORA DE ESPECIFICACAO',
			'820101003' =>	'OLEO DIESEL B S1800 - COMUM',
			'320102001' =>	'GASOLINA C COMUM',
			'820101004' =>	'DIESEL B10',
			'320102002' =>	'GASOLINA C ADITIVADA',
			'820101005' =>	'DIESEL B15',
			'320102003' =>	'GASOLINA C PREMIUM',
			'820101006' =>	'DIESEL B20 S1800 - COMUM',
			'320102004' =>	'GASOLINA C FORA DE ESPECIFICACAO',
			'820101007' =>	'DIESEL B4 S1800 - ADITIVADO',
			'320103001' =>	'GASOLINA AUTOMOTIVA PADRAO	',
			'820101008' =>	'DIESEL B4 S500 - COMUM',
			'320103002' =>	'OUTRAS GASOLINAS AUTOMOTIVAS',
			'820101009' =>	'DIESEL B4 S500 - ADITIVADO',
			'320201001' =>	'GASOLINA DE AVIACAO',
			'820101010' =>	'BIODIESEL FORA DE ESPECIFICACAO',
			'320201002' =>	'GASOLINA DE AVIAÇÃO FORA DE ESPECIFICACAO',
			'820101011' =>	'OLEO DIESEL B S1800 - ADITIVADO',
			'320301001' =>	'OUTRAS GASOLINAS',
			'820101012' =>	'OLEO DIESEL B S500 - COMUM',
			'320301002' =>	'GASOLINA PARA EXPORTACAO',
			'820101013' =>	'OLEO DIESEL B S500 - ADITIVADO',
			'410101001' =>	'QUEROSENE DE AVIACAO',
			'820101014' =>	'DIESEL B20 S1800 - ADITIVADO',
			'410101002' =>	'QUEROSENE DE AVIAÇÃO FORA DE ESPECIFICACAO	',
			'820101015' =>	'DIESEL B20 S500 - COMUM',
			'410102001' =>	'QUEROSENE ILUMINANTE	',
			'820101016' =>	'DIESEL B20 S500 - ADITIVADO',
			'410102002' =>	'QUEROSENE ILUMINANTE FORA DE ESPECIFICACAO	',
			'820101017' =>	'DIESEL MARÍTIMO - DMA B2',
			'410103001' =>	'OUTROS QUEROSENES	',
			'820101018' =>	'DIESEL MARITIMO - DMA B5',
			'420101003' =>	'OLEO DIESEL A S1800 - FORA DE ESPECIFICACAO',
			'820101019' =>	'DIESEL MARITIMO - DMB B2',
			'420101004' =>	'OLEO DIESEL A S1800 - COMUM',
			'820101020' =>	'DIESEL MARITIMO - DMB B5',
			'420101005' =>	'OLEO DIESEL A S1800 - ADITIVADO',
			'820101021' =>	'DIESEL NAUTICO B2 ESPECIAL - 200 PPM ENXOFRE',
			'420102003' =>	'OLEO DIESEL A S500 - FORA DE ESPECIFICACAO',
			'820101022' =>	'DIESEL B2 ESPECIAL - 200 PPM ENXOFRE',
			'420102004' =>	'OLEO DIESEL A S500 - COMUM',
			'820101025' =>	'DIESEL B30',
			'420102005' =>	'OLEO DIESEL A S500 - ADITIVADO	',
			'820101026' =>	'DIESEL B S1800 PARA GERACAO DE ENERGIA ELETRICA',
			'420102006' =>	'OLEO DIESEL A S50	',
			'820101027' =>	'DIESEL B S500 PARA GERACAO DE ENERGIA ELETRICA',
			'420104001' =>	'OLEO DIESEL AUTOMOTIVO ESPECIAL - ENXOFRE 200 PPM	',
			'820101028' =>	'OLEO DIESEL B S50 - ADITIVADO',
			'420105001' =>	'OLEO DIESEL A S10',
			'820101029' =>	'OLEO DIESEL B S50 - COMUM',
			'420201001' =>	'DMA - MGO',
			'820101030' =>	'DIESEL B20 S50 COMUM',
			'420201002' =>	'OLEO DIESEL MARITIMO FORA DE ESPECIFICACAO',
			'820101031' =>	'DIESEL B20 S50 ADITIVADO',
			'420201003' =>	'DMB - MDO',
			'820101032' =>	'DIESEL B S50 PARA GERACAO DE ENERGIA ELETRICA',
			'420202001' =>	'OLEO DIESEL NAUTICO ESPECIAL - ENXOFRE 200 PPM',
			'820101033' =>	'OLEO DIESEL B S10 - ADITIVADO',
			'420301001' =>	'OLEO DIESEL PADRAO',
			'820101034' =>	'OLEO DIESEL B S10 - COMUM'
		];
	}

	public function somaVendas()
	{
		$sql = \DB::table('item_vendas')
			->select(\DB::raw('SUM(quantidade) as quantidade'))
			->where('produto_id', $this->id)
			->first();
		return $sql->quantidade ? $sql->quantidade : 0;
	}

	public function estoqueAtual()
	{
		$estoque = $this->estoque;
		// if($this->gerenciar_estoque == 0) return '--';

		if (!$estoque) return 0;
		if ($estoque) {
			if ($this->unidade_venda == 'UN' || $this->unidade_venda == 'UNID') {
				return number_format($estoque->quantidade);
			}
			return $estoque->quantidade;
		}
	}

	public function estoqueAtual2()
	{
		$estoque = $this->estoque;
		if (!$estoque) return 0;
		return $estoque->quantidade;
	}

	public function emVendas()
	{
		return $this->hasMany('App\Models\ItemVenda', 'produto_id', 'id');
	}

	public function emVendaCaixas()
	{
		return $this->hasMany('App\Models\ItemVendaCaixa', 'produto_id', 'id');
	}

	public function emCompras()
	{
		return $this->hasMany('App\Models\ItemCompra', 'produto_id', 'id');
	}

	public function emAlteracaoEstoque()
	{
		return $this->hasMany('App\Models\AlteracaoEstoque', 'produto_id', 'id');
	}


	private function criaArray($objeto, $tipo)
{
	$valor = 0;
	$linkId = null;
	$quantidade = isset($objeto->quantidade_mov) ? (float) $objeto->quantidade_mov : (float) $objeto->quantidade;

	if ($tipo == 'Compras') {
		$valor = $objeto->valor_unitario;
		$linkId = $objeto->compra_id ?? $objeto->id;
	} elseif ($tipo == 'Vendas') {
		$valor = $objeto->valor;
		$linkId = $objeto->venda_id ?? $objeto->id;
	} elseif ($tipo == 'PDV') {
		$valor = $objeto->valor;
		$linkId = $objeto->venda_caixa_id ?? $objeto->id;
	} else {
		$valor = $objeto->valor ?? 0;
		$linkId = $objeto->id;
	}

	$row = [
		'quantidade' => $quantidade,
		'tipo' => $tipo,
		'valor' => $valor,
		'data' => $objeto->created_at,
		'id' => $linkId,
		'tipo_model' => $tipo,
		'observacao' => $objeto->observacao ?? '',
		'estoque_anterior' => $objeto->estoque_anterior ?? null,
		'estoque_novo' => $objeto->estoque_novo ?? null,
	];

	if ($tipo === 'Vendas') {
		$row['venda_id'] = $objeto->venda_id;
		$row['cliente_venda'] = optional(optional($objeto->venda)->cliente)->razao_social;
	} elseif ($tipo === 'Compras') {
		$row['compra_id'] = $objeto->compra_id;
	} elseif ($tipo === 'PDV') {
		$row['pdv_id'] = $objeto->venda_caixa_id ?? null;
	} elseif (strpos((string)$tipo, 'Alteração de Estoque') === 0) {
		$row['tipo_model'] = 'AlteracaoEstoque';
		$row['alteracao_acao'] = $objeto->acao ?? null;
		$row['alteracao_origem'] = $objeto->origem ?? null;
		$row['alteracao_origem_id'] = $objeto->origem_id ?? null;
		$row['alteracao_pedido_ref'] = $objeto->pedido_id ?? null;
		$row['alteracao_tipo_codigo'] = $objeto->tipo;
		if (in_array((string) ($objeto->acao ?? ''), ['edicao_venda_estorno', 'edicao_venda_saida'], true)) {
			$row['tipo'] = $objeto->acao === 'edicao_venda_estorno'
				? 'Estorno (edição venda)'
				: 'Ajuste venda (+)';
			$row['valor'] = $this->resolverValorMovimentoEdicaoVenda($objeto);
		}
	}

	$pri = 10;
	if ($tipo === 'Compras') {
		$pri = 12;
	} elseif ($tipo === 'PDV') {
		$pri = 15;
	} elseif ($tipo === 'Vendas') {
		$pri = 20;
	} elseif (strpos((string) $tipo, 'Alteração de Estoque') === 0) {
		$acao = (string) ($objeto->acao ?? '');
		if ($acao === 'edicao_venda_estorno') {
			$pri = 92;
		} elseif ($acao === 'edicao_venda_saida') {
			$pri = 91;
		} else {
			$pri = 50;
		}
	}
	$row['mov_priority'] = $pri;
	$row['mov_sort_id'] = (int) ($objeto->id ?? 0);

	return $row;
}



	public static function produtosDaGrade($referencia)
	{
		return Produto::where('referencia_grade', $referencia)
			->get();
	}

	public static function produtosDaGradeSomaEstoque($referencia)
	{
		$produtos = Produto::produtosDaGrade($referencia);
		$quantidade = 0;
		foreach ($produtos as $p) {
			$estoque = Estoque::where('produto_id', $p->id)->first();
			$quantidade += $estoque == null ? 0 : $estoque->quantidade;
		}

		return $quantidade;
	}

	public function getDescricaoAnp()
	{
		$lista = $this->lista_ANP();
		return $lista[$this->codigo_anp];
	}

	public static function origens()
	{
		return [
			'0' => 'NACIONAL',
			'1' => 'ESTRANGEIRA - IMPORTAÇÃO DIRETA',
			'2' => 'ESTRANGEIRA - ADQUIRIDA NO MERCADO INTERNO',
			'3' => 'NACIONAL, MERCADORIA OU BEM COM CONTEÚDO DE IMPORTAÇÃO SUPERIOR A 40%',
			'4' => 'NACIONAL, CUJA PRODUÇÃO TENHA SIDO FEITA EM CONFORMIDADE COM OS PROCESSOS PRODUTIVOS BÁSICOS DE QUE TRATAM O DECRETO-LEI Nº 288/67, E AS LEIS NºS 8.248/91, 8.387/91, 10.176/01 E 11 . 4 8 4 / 0 7',
			'5' => 'NACIONAL, MERCADORIA OU BEM COM CONTEÚDO DE IMPORTAÇÃO INFERIOR OU IGUAL A 40%',
			'6' => 'ESTRANGEIRA - IMPORTAÇÃO DIRETA, SEM SIMILAR NACIONAL, CONSTANTE EM LISTA DE RESOLUÇÃO CAMEX',
			'7' => 'ESTRANGEIRA - ADQUIRIDA NO MERCADO INTERNO, SEM SIMILAR NACIONAL, CONSTANTE EM LISTA DE RESOLUÇÃO CAMEX',
			'8' => 'NACIONAL, MERCADORIA OU BEM COM CONTEUDO DE IMPORTACAO SUPERIOR A 70%',
		];
	}

	public static function modalidadesDeterminacao()
	{
		return [
			'0' => 'Margem Valor Agregado (%)',
			'1' => 'Pauta (Valor)',
			'2' => 'Preço Tabelado Máx. (valor)',
			'3' => 'Valor da operação'
		];
	}

	public static function modalidadesDeterminacaoST()
	{
		return [
			'0' => 'Preço tabelado ou máximo sugerido',
			'1' => 'Lista Negativa (valor)',
			'2' => 'Lista Positiva (valor)',
			'3' => 'Lista Neutra (valor)',
			'4' => 'Margem Valor Agregado (%)',
			'5' => 'Pauta (valor)',
			'6' => 'Valor da Operação'
		];
	}


	public function unidadeQuebrada()
	{
		$unidades = [
			"M",
			"M2",
			"M3",
			"KG",
			"TON",
			"LITRO",
		];
		if (in_array($this->unidade_venda, $unidades)) {
			return 1;
		}
		return 0;
	}

	public function estoquePorLocais($locais)
	{
		$locais = json_decode($locais);
		$html = "";
		foreach ($locais as $l) {
			if ($l == -1) $l = null;
			$estoque = Estoque::where('produto_id', $this->id)
				->where('filial_id', $l)
				->first();
			$local = Filial::find($l);
			$qtd = 0;
			if ($estoque) {
				$qtd = $estoque->quantidade;
			}
			if ($local) {
				$html .= $local->descricao . ": $qtd |";
			} else {
				$html .= "Matriz: $qtd |";
			}
		}

		$html = substr($html, 0, strlen($html) - 1);
		return $html;
	}

	public function estoquePorLocal($filial_id)
	{
		if ($filial_id == "-1" || $filial_id == 'todos') {
			$filial_id = NUll;
		}
		if (!$this->grade) {
			$estoque = Estoque::where('produto_id', $this->id)
				->where('filial_id', $filial_id)
				->first();

			// if($this->gerenciar_estoque == 0) return '--';
			if (!$estoque) return 0;
			if ($estoque) {
				if ($this->unidade_venda == 'UN' || $this->unidade_venda == 'UNID') {
					return number_format($estoque->quantidade);
				}
				if (!$this->unidadeQuebrada()) {
					return number_format($estoque->quantidade, 0, '.', '');
				} else {
					return number_format($estoque->quantidade, 2, '.', '');
				}
			}
		} else {
			// $qtd = Produto::produtosDaGradeSomaEstoque($this->referencia_grade);
			// return $qtd;
			$grade = Produto::produtosDaGrade($this->referencia_grade);
			$str = "";
			foreach ($grade as $g) {
				$str .= "$g->str_grade = " . $g->estoqueAtual() . " | ";
			}
			$str = substr($str, 0, strlen($str) - 2);
			return $str;
		}
	}

	public function estoquePorLocalPavaVenda($filial_id)
	{
		if ($filial_id == "-1") {
			$filial_id = NUll;
		}
		$estoque = Estoque::where('produto_id', $this->id)
			->where('filial_id', $filial_id)
			->first();
		// if($this->gerenciar_estoque == 0) return '--';
		if (!$estoque) return 0;
		if ($estoque) {
			if ($this->unidade_venda == 'UN' || $this->unidade_venda == 'UNID') {
				return number_format($estoque->quantidade);
			}
			if (!$this->unidadeQuebrada()) {
				return number_format($estoque->quantidade, 0, '.', '');
			} else {
				return number_format($estoque->quantidade, 2, '.', '');
			}
		}
	}


	public static function modelosBalanca()
	{
		return [
			'Toledo'
		];
	}

	public function movimentacoes()
	{
		$arr = [];

		$ajustesEdicaoVenda = AlteracaoEstoque::where('produto_id', $this->id)
			->where('origem', 'venda')
			->whereIn('acao', ['edicao_venda_estorno', 'edicao_venda_saida'])
			->get(['origem_id', 'acao', 'quantidade']);
		$mapAjustePorVenda = [];
		foreach ($ajustesEdicaoVenda as $a) {
			$vid = (int) ($a->origem_id ?? 0);
			if ($vid <= 0) {
				continue;
			}
			if (!isset($mapAjustePorVenda[$vid])) {
				$mapAjustePorVenda[$vid] = ['estorno' => 0.0, 'saida' => 0.0];
			}
			if ($a->acao === 'edicao_venda_estorno') {
				$mapAjustePorVenda[$vid]['estorno'] += (float) $a->quantidade;
			} elseif ($a->acao === 'edicao_venda_saida') {
				$mapAjustePorVenda[$vid]['saida'] += (float) $a->quantidade;
			}
		}

		$emVendas = ItemVenda::where('produto_id', $this->id)
			->with(['venda.cliente'])
			->orderBy('id')
			->get();

		$porVenda = $emVendas->groupBy(fn ($it) => (int) ($it->venda_id ?? 0));
		foreach ($porVenda as $vid => $itens) {
			if ($vid <= 0 || $itens->isEmpty()) {
				continue;
			}
			$sumQ = (float) $itens->sum(fn ($i) => (float) $i->quantidade);
			$adj = $mapAjustePorVenda[$vid] ?? null;
			if ($sumQ < 0.00001 && $adj === null) {
				continue;
			}
			/** @var ItemVenda $rep */
			$rep = $itens->sortByDesc('id')->first();
			$clone = $rep->replicate();
			$clone->id = (int) $rep->id;
			$clone->quantidade = $sumQ;
			$vendaCab = $rep->relationLoaded('venda') ? $rep->getRelation('venda') : null;
			$dataOrdVenda = null;
			if ($vendaCab) {
				$dataOrdVenda = $vendaCab->data_registro ?? $vendaCab->created_at;
			}
			if ($dataOrdVenda !== null) {
				$clone->created_at = $dataOrdVenda;
			} else {
				$maxCreated = $itens->max('created_at');
				if ($maxCreated !== null) {
					$clone->created_at = $maxCreated;
				}
			}
			if ($sumQ > 0.00001) {
				$somaValQ = (float) $itens->sum(fn ($i) => (float) $i->quantidade * (float) $i->valor);
				$clone->valor = $somaValQ / $sumQ;
			}
			if ($adj !== null) {
				$clone->quantidade_mov = $sumQ + (float) $adj['estorno'] - (float) $adj['saida'];
			}
			if ($rep->relationLoaded('venda')) {
				$clone->setRelation('venda', $rep->getRelation('venda'));
			}

			$temp = $this->criaArray($clone, 'Vendas');
			array_push($arr, $temp);
		}

		$emVendaCaixas = ItemVendaCaixa::where('produto_id', $this->id)->get();
		$emCompras = ItemCompra::where('produto_id', $this->id)->with(['compra'])->get();
		$emAlteracaoEstoque = AlteracaoEstoque::where('produto_id', $this->id)->get();

		foreach ($emVendaCaixas as $m) {
			$temp = $this->criaArray($m, 'PDV');
			array_push($arr, $temp);
		}

		foreach ($emCompras as $m) {
			$temp = $this->criaArray($m, 'Compras');
			array_push($arr, $temp);
		}

		foreach ($emAlteracaoEstoque as $m) {
			$tipo = 'Alteração de Estoque ' . $m->tipo;
			$temp = $this->criaArray($m, $tipo);
			array_push($arr, $temp);
		}

		usort($arr, function ($a, $b) {
			$cmp = ($b['data'] ?? null) <=> ($a['data'] ?? null);
			if ($cmp !== 0) {
				return $cmp;
			}
			$cmpP = (int) ($b['mov_priority'] ?? 0) <=> (int) ($a['mov_priority'] ?? 0);
			if ($cmpP !== 0) {
				return $cmpP;
			}
			$ia = (int) ($a['mov_sort_id'] ?? 0);
			$ib = (int) ($b['mov_sort_id'] ?? 0);

			return $ib <=> $ia;
		});

		return $this->enriquecerMovimentacoesLinhasEstoque($arr);
	}

	/**
	 * Acrescenta texto legível (estoque antes / novo) percorrendo do movimento mais recente para o passado.
	 *
	 * @param  array<int, array<string, mixed>>  $movDesc
	 * @return array<int, array<string, mixed>>
	 */
	private function enriquecerMovimentacoesLinhasEstoque(array $movDesc): array
	{
		$balance = (float) DB::table('estoques')->where('produto_id', $this->id)->sum('quantidade');

		foreach ($movDesc as &$row) {
			$q = abs((float)($row['quantidade'] ?? 0));
			$linhas = [];
			$tm = $row['tipo_model'] ?? '';

			if ($tm === 'AlteracaoEstoque' || strpos((string)$row['tipo'], 'Alteração de Estoque') === 0) {
				if (!empty($row['alteracao_acao']) && strpos((string) $row['alteracao_acao'], 'edicao_venda_') === 0) {
					$row['linhas_detalhe'] = [trim((string) ($row['observacao'] ?? ''))];
					$ea = $row['estoque_anterior'];
					$en = $row['estoque_novo'];
					if ($ea !== null && $en !== null && $ea !== '' && $en !== '') {
						$balance = (float) $ea;
					}
					continue;
				}
				$ea = $row['estoque_anterior'];
				$en = $row['estoque_novo'];
				if ($ea !== null && $en !== null && $ea !== '' && $en !== '') {
					$antes = (float) $ea;
					$depois = (float) $en;
					$linhas[] = 'Estoque anterior: ' . $this->fmtQtdMov($antes) . ' un.';
					if (!empty($row['observacao'])) {
						$linhas[] = trim((string) $row['observacao']);
					}
					if (!empty($row['alteracao_acao'])) {
						$linhas[] = 'Ação: ' . $row['alteracao_acao'] . '.';
						if (stripos((string) $row['alteracao_acao'], 'estorno') !== false) {
							$linhas[] = 'Tipo: estorno — quantidade devolvida ao estoque.';
						}
					}
					if (!empty($row['alteracao_pedido_ref'])) {
						$linhas[] = 'Tirado / referente ao pedido ou venda nº ' . $row['alteracao_pedido_ref'] . '.';
					}
					$linhas[] = 'Novo estoque: ' . $this->fmtQtdMov($depois) . ' un.';
					$balance = $antes;
				} else {
					$cod = $row['alteracao_tipo_codigo'] ?? null;
					$novo = $balance;
					if ($cod == 1 || $cod === '1' || $cod === 'entrada') {
						$antes = $balance - $q;
						$linhas[] = 'Estoque anterior: ' . $this->fmtQtdMov($antes) . ' un.';
						$linhas[] = 'Entrada manual: ' . $this->fmtQtdMov($q) . ' un.';
						$linhas[] = 'Novo estoque: ' . $this->fmtQtdMov($novo) . ' un.';
						$balance = $antes;
					} else {
						$antes = $balance + $q;
						$linhas[] = 'Estoque anterior: ' . $this->fmtQtdMov($antes) . ' un.';
						$linhas[] = 'Saída manual: ' . $this->fmtQtdMov($q) . ' un.';
						$linhas[] = 'Novo estoque: ' . $this->fmtQtdMov($novo) . ' un.';
						$balance = $antes;
					}
					if (!empty($row['observacao'])) {
						array_unshift($linhas, trim((string) $row['observacao']));
					}
				}
				$row['linhas_detalhe'] = $linhas;
				continue;
			}

			if ($tm === 'Vendas') {
				$novo = $balance;
				$antes = $balance + $q;
				$vid = $row['venda_id'] ?? $row['id'];
				$linhas[] = 'Estoque anterior: ' . $this->fmtQtdMov($antes) . ' un.';
				$linhas[] = 'Saída por venda nº ' . $vid . ' — ' . $this->fmtQtdMov($q) . ' un.';
				if (!empty($row['cliente_venda'])) {
					$linhas[] = 'Cliente: ' . $row['cliente_venda'] . '.';
				}
				$linhas[] = 'Novo estoque: ' . $this->fmtQtdMov($novo) . ' un.';
				$balance = $antes;
				$row['linhas_detalhe'] = $linhas;
				continue;
			}

			if ($tm === 'PDV') {
				$novo = $balance;
				$antes = $balance + $q;
				$pid = $row['pdv_id'] ?? $row['id'];
				$linhas[] = 'Estoque anterior: ' . $this->fmtQtdMov($antes) . ' un.';
				$linhas[] = 'Saída por PDV (caixa) nº ' . $pid . ' — ' . $this->fmtQtdMov($q) . ' un.';
				$linhas[] = 'Novo estoque: ' . $this->fmtQtdMov($novo) . ' un.';
				$balance = $antes;
				$row['linhas_detalhe'] = $linhas;
				continue;
			}

			if ($tm === 'Compras') {
				$novo = $balance;
				$antes = $balance - $q;
				$cid = $row['compra_id'] ?? $row['id'];
				$linhas[] = 'Estoque anterior: ' . $this->fmtQtdMov($antes) . ' un.';
				$linhas[] = 'Entrada por compra nº ' . $cid . ' — ' . $this->fmtQtdMov($q) . ' un.';
				$linhas[] = 'Novo estoque: ' . $this->fmtQtdMov($novo) . ' un.';
				$balance = $antes;
				$row['linhas_detalhe'] = $linhas;
				continue;
			}

			$row['linhas_detalhe'] = array_filter([$row['observacao'] ?? '']);
		}
		unset($row);

		return $movDesc;
	}

	private function fmtQtdMov(float $q): string
	{
		if (abs($q - round($q)) < 0.0001) {
			return (string)(int) round($q);
		}

		return rtrim(rtrim(number_format($q, 4, ',', ''), '0'), ',') ?: '0';
	}

	private function resolverValorMovimentoEdicaoVenda($alteracao): float
	{
		$obs = (string) ($alteracao->observacao ?? '');
		if (preg_match('/Valor unit.rio:\s*R\$\s*([0-9\.\,]+)/iu', $obs, $m)
			|| preg_match('/vl\s+unit\s+R\$\s*([0-9\.\,]+)/iu', $obs, $m)) {
			return (float) __convert_value_bd($m[1]);
		}
		$vendaId = (int) ($alteracao->origem_id ?? 0);
		if ($vendaId > 0) {
			$valor = ItemVenda::where('venda_id', $vendaId)
				->where('produto_id', $this->id)
				->orderByDesc('id')
				->value('valor');
			if ($valor !== null) {
				return (float) $valor;
			}
		}

		return (float) ($this->valor_venda ?? 0);
	}
}
