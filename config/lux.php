<?php

/*
 * Configurações específicas da LUX (uso interno: venda, listagem, fechamento e rota).
 * Defina no .env (local e servidor). Exemplo:
 *
 *   LUX_CONTA_FISCAL="Conta Fiscal - Itaú"
 *   LUX_CONTA_NAO_FISCAL="Conta 2 - Nubank"
 *
 *   LUX_PIX_FISCAL_CHAVE=12345678000199
 *   LUX_PIX_FISCAL_NOME="LUX PERFUMES LTDA"
 *   LUX_PIX_FISCAL_CIDADE="SAO PAULO"
 *
 *   LUX_PIX_NAO_FISCAL_CHAVE=email@dominio.com
 *   LUX_PIX_NAO_FISCAL_NOME="NOME DO TITULAR"
 *   LUX_PIX_NAO_FISCAL_CIDADE="SAO PAULO"
 *
 * Chave: CPF/CNPJ (pode ter pontuação), e-mail, celular no formato +5511999999999 ou chave aleatória.
 * Nome do titular até 25 letras e cidade até 15 (sem acento; o sistema corta/limpa sozinho).
 * Sem chave configurada, o botão de PIX não aparece.
 */
return [
    'conta_fiscal' => env('LUX_CONTA_FISCAL', 'Conta Fiscal'),
    'conta_nao_fiscal' => env('LUX_CONTA_NAO_FISCAL', 'Conta 2'),

    'pix' => [
        'fiscal' => [
            'chave' => env('LUX_PIX_FISCAL_CHAVE', ''),
            'nome' => env('LUX_PIX_FISCAL_NOME', ''),
            'cidade' => env('LUX_PIX_FISCAL_CIDADE', ''),
        ],
        'nao_fiscal' => [
            'chave' => env('LUX_PIX_NAO_FISCAL_CHAVE', ''),
            'nome' => env('LUX_PIX_NAO_FISCAL_NOME', ''),
            'cidade' => env('LUX_PIX_NAO_FISCAL_CIDADE', ''),
        ],
    ],
];
