<?php

namespace App\Services;
use NFePHP\NFe\Tools;
use NFePHP\Common\Certificate;
use App\Models\Certificado;
use NFePHP\NFe\Common\Standardize;

error_reporting(E_ALL);
ini_set('display_errors', 'On');

class DFeService{

	private $config; 
	private $tools;
	protected $empresa_id = null;

	public function __construct($config, $emitente){
		if($emitente->arquivo == null){
			abort(403, "realize o upload do certificado");
		}
		$this->empresa_id = $emitente->empresa_id;

		$this->config = $config;
		$this->tools = new Tools(json_encode($config), Certificate::readPfx($emitente->arquivo, $emitente->senha));
		$this->tools->model(55);
		$this->tools->setEnvironment(1);
		
	}

	/**
	 * Busca as notas recebidas na SEFAZ (Distribuição DF-e), respeitando as regras dela:
	 * - continua sempre do último NSU que a própria SEFAZ devolveu (guardado por empresa/filial);
	 * - para assim que não houver mais documentos (cStat 137) ou chegar ao maxNSU;
	 * - depois disso, ou se a SEFAZ acusar "Consumo Indevido" (656), só deixa consultar de novo após 1 hora.
	 * Retorna a lista de documentos (pode ser vazia) ou ['erro' => 1, 'message' => ...].
	 */
	public function novaConsulta($nsu){
		$controle = $this->lerControle();
		if (!empty($controle['proxima']) && time() < $controle['proxima']) {
			return [
				"erro" => 1,
				"message" => "A SEFAZ só permite uma nova consulta a partir das " . date('H:i', $controle['proxima'])
					. ". (Quando não há notas novas, ela exige 1 hora de intervalo entre as consultas.)"
			];
		}

		$ultNSU = max((int) $nsu, (int) ($controle['ult_nsu'] ?? 0));
		$maxNSU = $ultNSU;
		$arrayDocs = [];
		$esperar = false;

		for ($i = 0; $i < 5; $i++) {
			try {
				$resp = $this->tools->sefazDistDFe($ultNSU);
			} catch (\Exception $e) {
				return ["erro" => 1, "message" => $e->getMessage()];
			}

			$dom = new \DOMDocument();
			$dom->loadXML($resp);
			$node = $dom->getElementsByTagName('retDistDFeInt')->item(0);
			if (!$node) {
				return ["erro" => 1, "message" => "Resposta inesperada da SEFAZ. Tente novamente mais tarde."];
			}
			$tag = fn ($n) => optional($node->getElementsByTagName($n)->item(0))->nodeValue;
			$cStat = (string) $tag('cStat');
			$xMotivo = (string) $tag('xMotivo');
			$respUlt = (int) $tag('ultNSU');
			$respMax = (int) $tag('maxNSU');

			if ($cStat === '656') {
				// a SEFAZ informa o ultNSU correto mesmo na rejeição: guarda para a próxima consulta
				$this->salvarControle(max($ultNSU, $respUlt), time() + 3660);
				return [
					"erro" => 1,
					"message" => "A SEFAZ bloqueou novas consultas por 1 hora (consumo indevido). Tente de novo a partir das "
						. date('H:i', time() + 3660) . "."
				];
			}
			if ($cStat === '137') { // nenhum documento novo
				$ultNSU = max($ultNSU, $respUlt);
				$esperar = true;
				break;
			}
			if ($cStat !== '138') { // outro erro: não insiste
				$this->salvarControle($ultNSU, null);
				return ["erro" => 1, "message" => "[$cStat] $xMotivo"];
			}

			$lote = $node->getElementsByTagName('loteDistDFeInt')->item(0);
			if ($lote) {
				foreach ($lote->getElementsByTagName('docZip') as $doc) {
					$content = gzdecode(base64_decode($doc->nodeValue));
					$xml = @simplexml_load_string($content);
					if (!$xml) {
						continue;
					}
					$arrayDocs[] = [
						'documento' => $xml->CNPJ,
						'nome' => $xml->xNome,
						'data_emissao' => $xml->dhEmi,
						'valor' => $xml->vNF,
						'num_prot' => $xml->nProt,
						'chave' => $xml->chNFe,
						'nsu' => (int) $doc->getAttribute('NSU'),
						'tipo' => 0,
						'fatura_salva' => false,
						'sequencia_evento' => 0,
						'empresa_id' => $this->empresa_id
					];
				}
			}

			$ultNSU = max($ultNSU, $respUlt);
			$maxNSU = max($maxNSU, $respMax);
			if ($ultNSU >= $maxNSU) { // pegou tudo que havia
				$esperar = true;
				break;
			}
			sleep(2);
		}

		$this->salvarControle($ultNSU, $esperar ? time() + 3660 : null);
		return $arrayDocs;
	}

	/** Controle da consulta (último NSU e horário liberado) por empresa/filial, em storage/app/dfe. */
	private function arquivoControle(): string
	{
		$dir = storage_path('app/dfe');
		if (!is_dir($dir)) {
			@mkdir($dir, 0775, true);
		}
		$filial = (int) ($this->config['is_filial'] ?? 0);
		return $dir . '/controle_' . (int) $this->empresa_id . '_' . $filial . '.json';
	}

	private function lerControle(): array
	{
		$f = $this->arquivoControle();
		return is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: []) : [];
	}

	private function salvarControle(int $ultNSU, ?int $proxima): void
	{
		@file_put_contents($this->arquivoControle(), json_encode([
			'ult_nsu' => $ultNSU,
			'proxima' => $proxima,
			'atualizado_em' => date('Y-m-d H:i:s'),
		]));
	}

	public function consulta($data_inicial, $data_final){
		$ultNSU = 0;
		$maxNSU = $ultNSU;
		$loopLimit = 10;
		$iCount = 0;
		//executa a busca de DFe em loop
		$last = "";
		$imprime = false;
		$arrayDocs = [];
		while ($ultNSU <= $maxNSU) {
			$iCount++;
			if ($iCount >= $loopLimit) {
				break;
			}
			try {

				$resp = $this->tools->sefazDistDFe($ultNSU);
				$dom = new \DOMDocument();
				$dom->loadXML($resp);

				$node = $dom->getElementsByTagName('retDistDFeInt')->item(0);
				$tpAmb = $node->getElementsByTagName('tpAmb')->item(0)->nodeValue;
				$verAplic = $node->getElementsByTagName('verAplic')->item(0)->nodeValue;
				$cStat = $node->getElementsByTagName('cStat')->item(0)->nodeValue;
				$xMotivo = $node->getElementsByTagName('xMotivo')->item(0)->nodeValue;
				$dhResp = $node->getElementsByTagName('dhResp')->item(0)->nodeValue;
				$ultNSU = $node->getElementsByTagName('ultNSU')->item(0)->nodeValue;
				$maxNSU = $node->getElementsByTagName('maxNSU')->item(0)->nodeValue;
				$lote = $node->getElementsByTagName('loteDistDFeInt')->item(0);
				if (empty($lote)) {
        //lote vazio
					continue;
				}
				if($last != $ultNSU){
					
					$last = $ultNSU;
					if (empty($lote)) {
        			//lote vazio
						continue;
					}
    				//essas tags irão conter os documentos zipados
					$docs = $lote->getElementsByTagName('docZip');

					

					foreach ($docs as $doc) {

						$numnsu = $doc->getAttribute('NSU');
						$schema = $doc->getAttribute('schema');

						$content = gzdecode(base64_decode($doc->nodeValue));
						$xml = simplexml_load_string($content);
						// print_r($xml);
						// print_r($xml->chNFe);
						$temp = [
							'documento' => $xml->CNPJ,
							'nome' => $xml->xNome,
							'data_emissao' => $xml->dhEmi,
							'valor' => $xml->vNF,
							'num_prot' => $xml->nProt,
							'chave' => $xml->chNFe
						];
						$data_dfe = \Carbon\Carbon::parse($xml->dhEmi)->format('Y-m-d');
						if(strtotime($data_dfe) >= strtotime($data_inicial) && strtotime($data_dfe) <= strtotime($data_final)){
							array_push($arrayDocs, $temp);
						}

						$tipo = substr($schema, 0, 6);

					}
					sleep(2);
				}
			} catch (\Exception $e) {
				return $e->getMessage();
			}

		}
		return $arrayDocs;

	}

	public function manifesta($chave, $nSeqEvento){
		try {

			$chNFe = $chave;
			$tpEvento = '210210'; 
			$xJust = ''; 
			$nSeqEvento = $nSeqEvento;

			$response = $this->tools->sefazManifesta($chNFe, $tpEvento, $xJust = '', $nSeqEvento);

			$st = new Standardize($response);

			$arr = $st->toArray();

			return $arr;

		} catch (\Exception $e) {
			echo $e->getMessage();
		}
	}

	public function download($chave){
		try {

			$this->tools->setEnvironment(1);
			
			$response = $this->tools->sefazDownload($chave);
			return $response;

		} catch (\Exception $e) {
			echo str_replace("\n", "<br/>", $e->getMessage());
		}
	}

	public function confirmacao($chave, $nSeqEvento){
		try {

			$chNFe = $chave;
			$tpEvento = '210200'; 
			$xJust = ''; 
			$nSeqEvento = $nSeqEvento;

			$response = $this->tools->sefazManifesta($chNFe, $tpEvento, $xJust = '', $nSeqEvento);

			$st = new Standardize($response);

			$arr = $st->toArray();

			return $arr;

		} catch (\Exception $e) {
			echo $e->getMessage();
		}
	}

	public function desconhecimento($chave, $nSeqEvento, $justificativa){
		try {

			$chNFe = $chave;
			$tpEvento = '210240'; 
			$xJust = $justificativa; 
			$nSeqEvento = $nSeqEvento;

			$response = $this->tools->sefazManifesta($chNFe, $tpEvento, $xJust, $nSeqEvento);

			$st = new Standardize($response);

			$arr = $st->toArray();

			return $arr;

		} catch (\Exception $e) {
			echo $e->getMessage();
		}
	}

	public function operacaoNaoRealizada($chave, $nSeqEvento, $justificativa){
		try {

			$chNFe = $chave;
			$tpEvento = '210220'; 
			$xJust = $justificativa; 
			$nSeqEvento = $nSeqEvento;

			$response = $this->tools->sefazManifesta($chNFe, $tpEvento, $xJust, $nSeqEvento);

			$st = new Standardize($response);

			$arr = $st->toArray();

			return $arr;

		} catch (\Exception $e) {
			echo $e->getMessage();
		}
	}

	
}