<?php

$accessToken = "EAAXYgsY4HLMBRgoVXXnyR58vCwC7LWxFys0gwvX10aamox0x8YNvd6KiKKoZArJ8Oa1gfY2UTAhGXeJhLFzF3tHd304sP2wPPQPwHvXz2XJbNwQSXMQgZAtq6lpBs5lrZBKcbBWfTmP7YZCsBZBS1FW8M2J3XucKRZALPMEt9ZBwZCiSoGb74JZBZBCGOd97JPNZCOSXJSJvFGvEkIzDEXmE8VW5cMgJndvQ9GKigEOJHPG";
$phoneNumberId = "1078437278680975";

/*
|--------------------------------------------------------------------------
| VALIDACAO META
|--------------------------------------------------------------------------
*/

$verify_token = "lux2026";

$mode = $_GET['hub_mode'] ?? $_GET['hub.mode'] ?? '';
$token = $_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '';
$challenge = $_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '';

if ($mode === 'subscribe' && $token === $verify_token) {
    echo $challenge;
    exit;
}

/*
|--------------------------------------------------------------------------
| LOG
|--------------------------------------------------------------------------
*/

$raw = file_get_contents('php://input');

file_put_contents(
    __DIR__ . '/webhook.log',
    date('Y-m-d H:i:s') . PHP_EOL .
    $raw . PHP_EOL . PHP_EOL,
    FILE_APPEND
);

$payload = json_decode($raw, true);

$message =
    $payload['entry'][0]['changes'][0]['value']['messages'][0]
    ?? null;

if (!$message) {
    http_response_code(200);
    exit;
}

$telefone = $message['from'] ?? null;

if (!$telefone) {
    http_response_code(200);
    exit;
}

/*
|--------------------------------------------------------------------------
| CAPTURA BOTAO
|--------------------------------------------------------------------------
*/

$textoBotao = null;

// formato button
if (isset($message['button']['text'])) {
    $textoBotao = trim($message['button']['text']);
}

// formato interactive
if (
    isset($message['interactive']['button_reply']['title'])
) {
    $textoBotao =
        trim($message['interactive']['button_reply']['title']);
}

if (!$textoBotao) {
    http_response_code(200);
    exit;
}

/*
|--------------------------------------------------------------------------
| RESPOSTAS
|--------------------------------------------------------------------------
*/

if (strcasecmp($textoBotao, 'Confirmar') === 0) {

    enviarMensagem(
        $telefone,
        "✅ Confirmado com sucesso!",
        $accessToken,
        $phoneNumberId
    );
    enviarMensagem(
        $telefone,
        "https://licurgopremiacoes.me/",
        $accessToken,
        $phoneNumberId
    );
}

if (strcasecmp($textoBotao, 'Cancelar') === 0) {

    enviarMensagem(
        $telefone,
        "❌ Cancelado com sucesso!",
        $accessToken,
        $phoneNumberId
    );
}

http_response_code(200);
exit;

/*
|--------------------------------------------------------------------------
| FUNCAO ENVIO
|--------------------------------------------------------------------------
*/

function enviarMensagem(
    $telefone,
    $texto,
    $token,
    $phoneId
) {

    $url = "https://graph.facebook.com/v23.0/$phoneId/messages";

    $payload = [
        "messaging_product" => "whatsapp",
        "to" => $telefone,
        "type" => "text",
        "text" => [
            "body" => $texto
        ]
    ];

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$token}",
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode($payload)
    ]);

    curl_exec($ch);
    curl_close($ch);
}