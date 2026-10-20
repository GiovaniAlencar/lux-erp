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

    // E-mails e notificações do site enviados pelo ERP
    'site_url' => rtrim(env('LUX_SITE_URL', 'https://luxatacado.com.br'), '/'),
    'whatsapp' => env('LUX_WHATSAPP', '5583999459629'),
    'whatsapp_exibir' => env('LUX_WHATSAPP_EXIBIR', '(83) 99945-9629'),
    'instagram' => env('LUX_INSTAGRAM', 'luxperfumesdistribuidora'),
    // desliga o envio de e-mails da fila sem parar o resto (NOTIFICACOES_EMAIL=false)
    'notificacoes_email' => (bool) env('NOTIFICACOES_EMAIL', true),

    // WhatsApp (Evolution API v2) — envia os comprovantes de venda para um grupo.
    // Tudo vazio = desligado. O número da instância precisa estar dentro do grupo.
    // Para achar o ID do grupo: php artisan lux:whatsapp-grupos "LUX COMPROVANTES"
    'evolution' => [
        'url' => rtrim((string) env('EVOLUTION_URL', ''), '/'),
        'key' => env('EVOLUTION_KEY', ''),
        'instancia' => env('EVOLUTION_INSTANCIA', ''),
        'grupo_comprovantes' => env('WHATSAPP_GRUPO_COMPROVANTES', ''), // ex.: 120363000000000000@g.us
        // só manda comprovantes pagos nesta conta (parte do nome, ex.: "Orion"). Vazio = todas.
        'conta_comprovantes' => env('WHATSAPP_COMPROVANTES_CONTA', 'Orion'),
    ],
];
