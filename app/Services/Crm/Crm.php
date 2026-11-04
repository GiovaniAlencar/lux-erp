<?php

namespace App\Services\Crm;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CRM LUX — configuração, campanhas, telefones, textos e bloqueios (SQL 37).
 *
 * Regras gerais (valem para todas as campanhas):
 *  - um ciclo ativo por telefone (um cliente nunca está em duas campanhas ao mesmo tempo);
 *  - entre o último contato proativo e um novo ciclo, no mínimo "intervalo_campanhas_dias";
 *  - telefone bloqueado nunca entra;
 *  - nesta fase nada é enviado: a fila é montada e conferida na tela.
 */
class Crm
{
    public const CAMPANHAS = [
        'reposicao' => [
            'nome' => 'Reposição', 'ativa' => 1, 'dias_min' => 15, 'dias_max' => 44, 'ordem' => 1,
            'texto_1' => "Olá, {nome}! Tudo bem? Passando para saber como estão as vendas por aí. Está precisando de alguma reposição? Temos novidades disponíveis na LUX!\n---\nOi, {nome}! Tudo certo? Como foram as vendas de {produtos}? Se quiser repor ou conhecer as novidades da LUX, é só me chamar por aqui.",
            'texto_2' => "Oi, {nome}! Passando de novo só para lembrar: se precisar de reposição ou quiser ver as novidades da LUX, estou por aqui.",
        ],
        'esfriando' => [
            'nome' => 'Cliente esfriando', 'ativa' => 1, 'dias_min' => 45, 'dias_max' => 74, 'ordem' => 2,
            'texto_1' => "Olá, {nome}! Tudo bem por aí? Faz um tempinho que não fazemos sua reposição aqui na LUX. Estamos com o catálogo atualizado e novidades disponíveis. Posso te apresentar?",
            'texto_2' => "Oi, {nome}! Quer que eu te mande o catálogo atualizado da LUX? É só responder por aqui.",
        ],
        'inativo' => [
            'nome' => 'Cliente inativo', 'ativa' => 1, 'dias_min' => 75, 'dias_max' => 365, 'ordem' => 3,
            'texto_1' => "Olá, {nome}! Tudo bem? Sentimos sua falta aqui na LUX. Chegaram muitas novidades desde a sua última compra. Posso te mandar o catálogo atualizado?",
            'texto_2' => "Oi, {nome}! Se quiser, te mando o catálogo com as novidades da LUX. É só responder por aqui.",
        ],
        'interessado' => [
            'nome' => 'Interessado sem compra', 'ativa' => 0, 'dias_min' => 48, 'dias_max' => null, 'ordem' => 4,
            'texto_1' => "Olá, {nome}! Tudo bem? Passando para saber se ficou alguma dúvida sobre os produtos que conversamos. Posso te ajudar a fechar o pedido?",
            'texto_2' => "Oi, {nome}! Ainda tem interesse? Se quiser, separo o pedido para você.",
        ],
    ];

    public const CONFIG_PADRAO = [
        'geracao_automatica' => false,   // monta a fila sozinho todo dia (ligar depois de conferir)
        'envio_ligado' => false,         // fase 4: envia pela central
        'aprovar_antes' => true,         // a fila do dia só sai depois de aprovada
        'limite_diario' => 20,
        'hora_inicio' => 9,
        'hora_fim' => 18,
        'dias_semana' => [1, 2, 3, 4, 5, 6], // 1 = segunda … 7 = domingo
        'intervalo_min_seg' => 120,
        'intervalo_max_seg' => 240,
        'segunda_tentativa_dias' => 8,
        'tentativas' => 2,
        'intervalo_campanhas_dias' => 15,
        'janela_atribuicao_dias' => 7,
    ];

    public const STATUS_CICLO = [
        'ativo' => 'Em andamento', 'respondido' => 'Respondeu', 'convertido' => 'Comprou (CRM)',
        'encerrado' => 'Encerrado', 'bloqueado' => 'Não quer receber', 'cancelado' => 'Cancelado',
    ];

    public const STATUS_ENVIO = [
        'aguardando_aprovacao' => 'Aguardando aprovação', 'pendente' => 'Aprovado (na fila)', 'processando' => 'Enviando',
        'enviado' => 'Enviado', 'entregue' => 'Entregue', 'respondido' => 'Respondido', 'cancelado' => 'Cancelado', 'erro' => 'Erro',
    ];

    public static function ativo(): bool
    {
        static $ok = null;
        return $ok ??= Schema::hasTable('crm_ciclos');
    }

    public static function config(int $empresaId): array
    {
        $j = DB::table('crm_config')->where('empresa_id', $empresaId)->value('dados');
        $d = $j ? (json_decode($j, true) ?: []) : [];
        return array_merge(self::CONFIG_PADRAO, array_intersect_key($d, self::CONFIG_PADRAO + ['ultima_geracao' => 1]));
    }

    public static function salvarConfig(int $empresaId, array $dados): void
    {
        $atual = self::config($empresaId);
        $novo = array_merge($atual, $dados);
        DB::table('crm_config')->updateOrInsert(['empresa_id' => $empresaId], ['dados' => json_encode($novo), 'updated_at' => now()]);
    }

    /** Campanhas da empresa (cria as padrão na primeira vez). */
    public static function campanhas(int $empresaId)
    {
        foreach (self::CAMPANHAS as $chave => $p) {
            $existe = DB::table('crm_campanhas')->where('empresa_id', $empresaId)->where('chave', $chave)->exists();
            if (!$existe) {
                DB::table('crm_campanhas')->insertOrIgnore(['empresa_id' => $empresaId, 'chave' => $chave] + $p + ['created_at' => now(), 'updated_at' => now()]);
            }
        }
        return DB::table('crm_campanhas')->where('empresa_id', $empresaId)->orderBy('ordem')->get()->keyBy('chave');
    }

    /** Só dígitos, com 55 + DDD. Retorna null quando não dá para saber o número completo. */
    public static function telefone(?string $t): ?string
    {
        $d = ltrim(preg_replace('/\D+/', '', (string) $t), '0');
        if (str_starts_with($d, '55') && in_array(strlen($d), [12, 13], true)) {
            return $d;
        }
        if (in_array(strlen($d), [10, 11], true) && (int) substr($d, 0, 2) >= 11) {
            return '55' . $d;
        }
        return null;
    }

    /** Celular primeiro; telefone fixo só se for o único número com cara de celular (9 dígitos depois do DDD). */
    public static function telefoneCliente(object $c): ?string
    {
        foreach ([$c->celular ?? null, $c->telefone ?? null] as $t) {
            $n = self::telefone($t);
            if ($n && strlen($n) === 13) {
                return $n;
            }
        }
        return self::telefone($c->celular ?? null);
    }

    public static function formatarTelefone(?string $n): string
    {
        $n = (string) $n;
        if (strlen($n) === 13) {
            return '(' . substr($n, 2, 2) . ') ' . substr($n, 4, 5) . '-' . substr($n, 9);
        }
        if (strlen($n) === 12) {
            return '(' . substr($n, 2, 2) . ') ' . substr($n, 4, 4) . '-' . substr($n, 8);
        }
        return $n;
    }

    public static function bloqueado(int $empresaId, string $tel): bool
    {
        return DB::table('crm_bloqueios')->where('empresa_id', $empresaId)->where('telefone', $tel)->exists();
    }

    /** Escolhe uma variação do texto (separadas por uma linha "---") e troca as variáveis. */
    public static function montarTexto(?string $modelo, array $ctx, int $semente): string
    {
        $variacoes = array_values(array_filter(array_map('trim', preg_split('/^\s*-{3,}\s*$/m', (string) $modelo))));
        if (!$variacoes) {
            return '';
        }
        $t = $variacoes[$semente % count($variacoes)];
        $produtos = array_values(array_filter((array) ($ctx['produtos'] ?? [])));
        $listaProd = count($produtos) > 1 ? implode(', ', array_slice($produtos, 0, -1)) . ' e ' . end($produtos) : ($produtos[0] ?? 'os perfumes');
        $t = strtr($t, [
            '{nome}' => (string) ($ctx['primeiro_nome'] ?? ''),
            '{produtos}' => $listaProd,
            '{dias}' => (string) ($ctx['dias'] ?? ''),
        ]);
        // "Olá, !" quando não tem nome
        return trim(preg_replace(['/,\s*([!?.])/u', '/\s{2,}/u'], ['$1', ' '], $t));
    }

    /** Nome para a mensagem: pessoa física → primeiro nome; empresa → nome fantasia. */
    public static function primeiroNome(object $c): string
    {
        $doc = preg_replace('/\D+/', '', (string) ($c->cpf_cnpj ?? ''));
        $fantasia = trim((string) ($c->nome_fantasia ?? ''));
        $razao = trim((string) ($c->razao_social ?? ''));
        if (strlen($doc) === 14 && $fantasia !== '') {
            return self::capitalizar($fantasia);
        }
        $base = $razao !== '' ? $razao : $fantasia;
        $primeiro = preg_split('/\s+/', $base)[0] ?? '';
        // "FJ Perfumaria", "SS Store": sigla não é nome de pessoa — usa o nome todo
        if (mb_strlen($primeiro) <= 3) {
            return self::capitalizar($fantasia !== '' ? $fantasia : $base);
        }
        return self::capitalizar($primeiro);
    }

    public static function capitalizar(string $s): string
    {
        $orig = trim($s);
        $tudoMaiusculo = $orig === mb_strtoupper($orig);
        // nome misto ("FJ Perfumaria"): siglas curtas em maiúsculas ficam como estão
        $s = implode(' ', array_map(function ($p) use ($tudoMaiusculo) {
            if (!$tudoMaiusculo && mb_strlen($p) <= 3 && $p === mb_strtoupper($p) && preg_match('/\pL/u', $p)) {
                return $p;
            }
            $min = mb_strtolower($p);
            return in_array($min, ['de', 'da', 'do', 'dos', 'das', 'e'], true) ? $min : mb_convert_case($min, MB_CASE_TITLE);
        }, preg_split('/\s+/u', $orig)));
        $s = mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
        return preg_replace_callback('/\b(\d+\s?ml|edp|edt|edc|ltda|me|eireli)\b/iu', fn ($m) => mb_strtoupper($m[0]), $s);
    }
}
