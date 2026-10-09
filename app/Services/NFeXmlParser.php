<?php

namespace App\Services;

/**
 * Lê um XML de NF-e (nfeProc ou NFe) e devolve cabeçalho + itens com os dados fiscais.
 */
class NFeXmlParser
{
    public function parse(string $xml): array
    {
        $xml = trim($xml);
        if ($xml === '') {
            throw new \RuntimeException('Arquivo vazio.');
        }
        $dom = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$ok) {
            throw new \RuntimeException('Arquivo não é um XML válido.');
        }

        $infNFe = $dom->getElementsByTagName('infNFe')->item(0);
        if (!$infNFe) {
            throw new \RuntimeException('XML não é de uma NF-e (sem infNFe).');
        }

        $emit = $this->first($infNFe, 'emit');
        $ide = $this->first($infNFe, 'ide');

        $cab = [
            'chave' => preg_replace('/\D/', '', (string) $infNFe->getAttribute('Id')),
            'numero' => $this->txt($ide, 'nNF'),
            'serie' => $this->txt($ide, 'serie'),
            'data' => substr($this->txt($ide, 'dhEmi') ?: $this->txt($ide, 'dEmi'), 0, 10),
            'emit_cnpj' => preg_replace('/\D/', '', $this->txt($emit, 'CNPJ') ?: $this->txt($emit, 'CPF')),
            'emit_nome' => $this->txt($emit, 'xNome'),
            'itens' => [],
        ];

        foreach ($infNFe->getElementsByTagName('det') as $det) {
            $prod = $this->first($det, 'prod');
            $imposto = $this->first($det, 'imposto');
            if (!$prod) {
                continue;
            }

            $orig = '';
            $csosn = '';
            $cst = '';
            $icms = $imposto ? $this->first($imposto, 'ICMS') : null;
            if ($icms) {
                foreach ($icms->childNodes as $grupo) {
                    if ($grupo instanceof \DOMElement) {
                        $orig = $this->txt($grupo, 'orig');
                        $csosn = $this->txt($grupo, 'CSOSN');
                        $cst = $this->txt($grupo, 'CST');
                        break;
                    }
                }
            }

            $cstPis = '';
            $pis = $imposto ? $this->first($imposto, 'PIS') : null;
            if ($pis) {
                $cstPis = $this->txt($pis, 'CST');
            }
            $cstCofins = '';
            $cofins = $imposto ? $this->first($imposto, 'COFINS') : null;
            if ($cofins) {
                $cstCofins = $this->txt($cofins, 'CST');
            }

            $ean = $this->txt($prod, 'cEAN');
            if ($ean === '' || stripos($ean, 'SEM') !== false) {
                $ean = $this->txt($prod, 'cEANTrib');
            }
            if (stripos($ean, 'SEM') !== false) {
                $ean = '';
            }

            // custo de aquisição: produto + frete + seguro + outras + IPI + ICMS-ST + FCP-ST − desconto
            $n = fn ($node, $tag) => (float) str_replace(',', '.', $this->txt($node, $tag));
            $vProd = $n($prod, 'vProd');
            $vIpi = 0.0;
            $vSt = 0.0;
            if ($imposto) {
                $ipi = $this->first($imposto, 'IPI');
                $vIpi = $ipi ? $n($ipi, 'vIPI') : 0.0;
                $vSt = $icms ? ($n($icms, 'vICMSST') + $n($icms, 'vFCPST')) : 0.0;
            }
            $custoTotal = $vProd + $n($prod, 'vFrete') + $n($prod, 'vSeg') + $n($prod, 'vOutro') - $n($prod, 'vDesc') + $vIpi + $vSt;

            $cab['itens'][] = [
                'n' => (int) $det->getAttribute('nItem'),
                'cProd' => $this->txt($prod, 'cProd'),
                'ean' => preg_replace('/\D/', '', $ean),
                'nome' => $this->txt($prod, 'xProd'),
                'ncm' => preg_replace('/\D/', '', $this->txt($prod, 'NCM')),
                'cest' => preg_replace('/\D/', '', $this->txt($prod, 'CEST')),
                'cfop' => preg_replace('/\D/', '', $this->txt($prod, 'CFOP')),
                'unidade' => strtoupper($this->txt($prod, 'uCom')),
                'qtd' => (float) $this->txt($prod, 'qCom'),
                'valor_unit' => (float) $this->txt($prod, 'vUnCom'),
                'v_prod' => round($vProd, 2),
                'custo_total' => round($custoTotal, 2),
                'orig' => $orig,
                'csosn' => $csosn,
                'cst' => $cst,
                'cst_pis' => $cstPis,
                'cst_cofins' => $cstCofins,
            ];
        }

        return $cab;
    }

    private function first(?\DOMNode $node, string $tag): ?\DOMElement
    {
        if (!$node instanceof \DOMElement) {
            return null;
        }
        $el = $node->getElementsByTagName($tag)->item(0);
        return $el instanceof \DOMElement ? $el : null;
    }

    private function txt(?\DOMNode $node, string $tag): string
    {
        $el = $this->first($node, $tag);
        return $el ? trim($el->textContent) : '';
    }
}
