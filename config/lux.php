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
        // instância do número do atendimento (central): avisos aos clientes. Vazio = sem WhatsApp aos clientes.
        'instancia_clientes' => env('EVOLUTION_INSTANCIA_CLIENTES', ''),
    ],

    // Avisos automáticos aos clientes: pedido confirmado, saiu para entrega e NF-e emitida.
    // E-mail segue NOTIFICACOES_EMAIL; WhatsApp precisa de EVOLUTION_INSTANCIA_CLIENTES.
    // WhatsApp só sai dentro do horário (fora dele espera até a abertura).
    'avisos_clientes' => [
        'ativo' => (bool) env('AVISOS_CLIENTES', true),
        'hora_inicio' => (int) env('AVISOS_HORA_INICIO', 7),
        'hora_fim' => (int) env('AVISOS_HORA_FIM', 21),
    ],

    // Taxas da maquineta (%) repassadas ao cliente — maquininha da Conta 2.
    // Usadas no "Calcular taxas da maquineta" das vendas. Mesma tabela do simulador do site.
    'maquineta' => [
        'bandeiras' => [
            'visa_master' => ['rotulo' => 'Visa / Mastercard', 'taxas' => ['debito' => 2.00, 1 => 4.00, 2 => 5.04, 3 => 5.74, 4 => 6.43, 5 => 7.12, 6 => 7.82, 7 => 8.25, 8 => 8.94, 9 => 9.64, 10 => 10.33, 11 => 11.03, 12 => 11.73, 13 => 12.42, 14 => 13.12, 15 => 13.81, 16 => 14.51, 17 => 15.21, 18 => 15.90]],
            'elo' => ['rotulo' => 'Elo', 'taxas' => ['debito' => 2.60, 1 => 4.98, 2 => 5.45, 3 => 6.14, 4 => 6.83, 5 => 7.52, 6 => 8.22, 7 => 8.88, 8 => 9.57, 9 => 10.26, 10 => 10.95, 11 => 11.64, 12 => 12.33, 13 => 13.02, 14 => 14.08, 15 => 14.41, 16 => 15.10, 17 => 15.79, 18 => 16.48]],
            'demais' => ['rotulo' => 'Demais bandeiras', 'taxas' => ['debito' => 2.60, 1 => 4.20, 2 => 6.06, 3 => 6.75, 4 => 7.43, 5 => 8.12, 6 => 8.80, 7 => 11.28, 8 => 11.96, 9 => 12.63, 10 => 13.30, 11 => 13.97, 12 => 14.65, 13 => 15.32, 14 => 16.63, 15 => 16.67, 16 => 17.34, 17 => 18.01, 18 => 18.68]],
        ],
    ],
];
