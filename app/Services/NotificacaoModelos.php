<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Textos dos e-mails de cada evento da fila. Todos usam o mesmo visual
 * (resources/views/emails/lux/layout.blade.php).
 *
 * Retorno: assunto, preheader, titulo, paragrafos[], detalhes[rotulo => valor],
 *          botao[texto, url], nota — ou null quando o evento não tem e-mail.
 */
class NotificacaoModelos
{
    /** Eventos que não podem ser reenviados (o conteúdo é apagado após o envio). */
    public const SEM_REENVIO = ['senha_recuperar'];

    public static function montar(string $evento, array $d): ?array
    {
        $site = config('lux.site_url');
        $nome = self::primeiroNome($d['nome'] ?? '');
        $ola = $nome !== '' ? "Olá, {$nome}!" : 'Olá!';

        switch ($evento) {
            case 'cadastro_recebido':
                return [
                    'assunto' => 'Recebemos seu cadastro na LUX Perfumes',
                    'preheader' => 'Nossa equipe vai analisar e liberar seu acesso aos preços de revenda.',
                    'titulo' => 'Cadastro recebido',
                    'paragrafos' => [
                        $ola,
                        'Recebemos seu cadastro na LUX Perfumes. Nossa equipe vai analisar os dados e liberar seu acesso aos preços de revenda.',
                        'Assim que for aprovado, você recebe outro e-mail avisando. Enquanto isso, pode ir conhecendo o catálogo.',
                    ],
                    'botao' => ['Conhecer o catálogo', $site . '/'],
                    'nota' => 'Precisa de agilidade? Fale com a gente pelo WhatsApp ' . config('lux.whatsapp_exibir') . '.',
                ];

            case 'cadastro_aprovado':
                return [
                    'assunto' => 'Seu cadastro foi aprovado! Os preços já estão liberados',
                    'preheader' => 'Entre no site e veja os preços de revenda.',
                    'titulo' => 'Cadastro aprovado',
                    'paragrafos' => [
                        $ola,
                        'Seu cadastro na LUX Perfumes foi aprovado. Agora você já pode entrar no site, ver os preços de revenda e fazer seus pedidos.',
                        'Quanto mais peças no pedido, melhor o preço: a tabela muda a partir de 10 e de 20 unidades.',
                    ],
                    'botao' => ['Entrar e ver os preços', $site . '/auth/signin'],
                    'nota' => 'Entre com o e-mail e a senha que você usou no cadastro.',
                ];

            case 'pedido_recebido':
                $detalhes = ['Pedido' => (string) ($d['pedido'] ?? '')];
                if (isset($d['total'])) {
                    $detalhes['Total'] = 'R$ ' . number_format((float) $d['total'], 2, ',', '.');
                }
                if (!empty($d['expira_em'])) {
                    try {
                        $detalhes['Reservado até'] = Carbon::parse($d['expira_em'])->format('d/m/Y \à\s H:i');
                    } catch (\Throwable $e) {
                    }
                }
                return [
                    'assunto' => 'Recebemos seu pedido ' . ($d['pedido'] ?? ''),
                    'preheader' => 'Seus produtos estão reservados. Nossa equipe vai confirmar o pedido.',
                    'titulo' => 'Pedido recebido',
                    'paragrafos' => [
                        $ola,
                        'Recebemos seu pedido e os produtos já estão reservados para você.',
                        'Nossa equipe vai conferir e entrar em contato pelo WhatsApp para confirmar o pagamento e o envio.',
                    ],
                    'detalhes' => $detalhes,
                    'botao' => ['Acompanhar meu pedido', $site . '/dashboard'],
                    'nota' => 'Se o pedido não for confirmado dentro do prazo da reserva, ele é cancelado automaticamente.',
                ];

            case 'produto_disponivel':
                $produto = trim((string) ($d['produto'] ?? 'O produto que você queria'));
                $url = !empty($d['produto_id']) ? $site . '/produto/' . (int) $d['produto_id'] : $site . '/';
                return [
                    'assunto' => "Chegou! {$produto} está disponível",
                    'preheader' => 'O produto que você pediu para avisar voltou ao estoque.',
                    'titulo' => 'Voltou ao estoque',
                    'paragrafos' => [
                        e($ola),
                        "Boa notícia: <b>" . e($produto) . "</b> voltou ao estoque na LUX Perfumes.",
                        e('Como você pediu para ser avisado, estamos te contando primeiro. O estoque é limitado, então garanta o seu.'),
                    ],
                    'html' => true, // parágrafos já escapados acima
                    'botao' => ['Ver o produto', $url],
                    'nota' => 'Você recebeu este e-mail porque clicou em "Avise-me quando chegar" no site.',
                ];

            case 'senha_recuperar':
                $token = (string) ($d['token'] ?? '');
                if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
                    return null;
                }
                $min = (int) ($d['validade_minutos'] ?? 60);
                return [
                    'assunto' => 'Crie uma nova senha na LUX Perfumes',
                    'preheader' => 'Use o botão abaixo para criar uma nova senha.',
                    'titulo' => 'Nova senha',
                    'paragrafos' => [
                        $ola,
                        'Recebemos um pedido para criar uma nova senha na sua conta da LUX Perfumes. Clique no botão abaixo para escolher a nova senha.',
                    ],
                    'botao' => ['Criar nova senha', $site . '/auth/redefinir-senha?token=' . $token],
                    'nota' => "O link vale por {$min} minutos e só pode ser usado uma vez. Se não foi você que pediu, ignore este e-mail: sua senha continua a mesma.",
                ];

            case 'teste':
                return [
                    'assunto' => 'Teste de e-mail da LUX Perfumes',
                    'preheader' => 'Se você recebeu, o envio de e-mails do ERP está funcionando.',
                    'titulo' => 'Teste de envio',
                    'paragrafos' => [
                        'Olá!',
                        'Este é um e-mail de teste enviado pelo ERP. Se ele chegou na caixa de entrada, está tudo certo com o envio.',
                    ],
                    'detalhes' => ['Enviado em' => now()->format('d/m/Y H:i:s')],
                    'botao' => ['Abrir o site', $site . '/'],
                ];
        }

        return null;
    }

    public static function rotulo(string $evento): string
    {
        return [
            'cadastro_recebido' => 'Cadastro recebido',
            'cadastro_aprovado' => 'Cadastro aprovado',
            'pedido_recebido' => 'Pedido recebido',
            'produto_disponivel' => 'Avise-me: produto chegou',
            'senha_recuperar' => 'Recuperar senha',
            'teste' => 'Teste',
        ][$evento] ?? $evento;
    }

    private static function primeiroNome(string $nome): string
    {
        $p = preg_split('/\s+/', trim($nome));
        return mb_convert_case(mb_strtolower($p[0] ?? ''), MB_CASE_TITLE, 'UTF-8');
    }
}
