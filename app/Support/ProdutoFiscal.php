<?php

namespace App\Support;

/**
 * Regras dos dados fiscais do produto (NF-e, Simples Nacional).
 * Usado no cadastro do produto (quando "Fiscal" = Sim) e na conferência antes de emitir a NF-e.
 */
class ProdutoFiscal
{
    /** CSOSN com substituição tributária: exigem CEST. */
    public const CSOSN_COM_ST = ['201', '202', '203', '500'];

    public const CSOSN_SIMPLES = ['101', '102', '103', '201', '202', '203', '300', '400', '500', '900'];

    /**
     * @param array|object $p dados do produto (request ou model) com as chaves:
     *   NCM, CEST, origem, CST_CSOSN, CFOP_saida_estadual, CFOP_saida_inter_estadual, CST_PIS, CST_COFINS, unidade_venda
     * @return array<string,string> campo => mensagem
     */
    public static function erros($p): array
    {
        $g = function ($k) use ($p) {
            $v = is_array($p) ? ($p[$k] ?? null) : ($p->{$k} ?? null);
            return trim((string) $v);
        };
        $erros = [];

        $ncm = preg_replace('/\D/', '', $g('NCM'));
        if (strlen($ncm) !== 8) {
            $erros['NCM'] = 'NCM deve ter 8 dígitos';
        }

        $csosn = $g('CST_CSOSN');
        if (!in_array($csosn, self::CSOSN_SIMPLES, true)) {
            $erros['CST_CSOSN'] = 'Informe o CSOSN (Simples Nacional)';
        }

        $cest = preg_replace('/\D/', '', $g('CEST'));
        if (in_array($csosn, self::CSOSN_COM_ST, true) && strlen($cest) !== 7) {
            $erros['CEST'] = 'CEST (7 dígitos) é obrigatório para CSOSN com substituição tributária';
        } elseif ($cest !== '' && strlen($cest) !== 7) {
            $erros['CEST'] = 'CEST deve ter 7 dígitos';
        }

        $origem = $g('origem');
        if ($origem === '' || !preg_match('/^[0-8]$/', $origem)) {
            $erros['origem'] = 'Informe a origem da mercadoria';
        }

        $cfopE = preg_replace('/\D/', '', $g('CFOP_saida_estadual'));
        if (!preg_match('/^5\d{3}$/', $cfopE)) {
            $erros['CFOP_saida_estadual'] = 'CFOP dentro do estado deve começar com 5 (ex.: 5102, 5405)';
        }
        $cfopI = preg_replace('/\D/', '', $g('CFOP_saida_inter_estadual'));
        if (!preg_match('/^6\d{3}$/', $cfopI)) {
            $erros['CFOP_saida_inter_estadual'] = 'CFOP fora do estado deve começar com 6 (ex.: 6102, 6404)';
        }

        if (!preg_match('/^\d{2}$/', $g('CST_PIS'))) {
            $erros['CST_PIS'] = 'Informe o CST do PIS';
        }
        if (!preg_match('/^\d{2}$/', $g('CST_COFINS'))) {
            $erros['CST_COFINS'] = 'Informe o CST do COFINS';
        }
        if ($g('unidade_venda') === '') {
            $erros['unidade_venda'] = 'Informe a unidade (ex.: UN)';
        }

        return $erros;
    }

    /** Normaliza os campos (tira pontuação de NCM/CEST/CFOP). */
    public static function normalizar(array $d): array
    {
        foreach (['NCM', 'CEST', 'CFOP_saida_estadual', 'CFOP_saida_inter_estadual'] as $k) {
            if (array_key_exists($k, $d) && $d[$k] !== null) {
                $d[$k] = preg_replace('/\D/', '', (string) $d[$k]);
            }
        }
        return $d;
    }
}
