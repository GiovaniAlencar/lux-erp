<?php

namespace App\Services\Crm;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Quem entra em cada campanha e a montagem da fila (o envio é feito por Envio, pela central, ou à mão).
 *
 * Compra válida = pedido do cliente que não foi cancelado (mesma regra do fechamento do mês).
 * Dias desde a última compra decidem a campanha: reposição (15–44), esfriando (45–74), inativo (75–365),
 * conforme a configuração de cada uma. "Interessado sem compra" vem das conversas classificadas na central.
 */
class Fila
{
    /** SQL da data de cada venda e do filtro de venda válida. */
    private static function vendasValidas(int $empresaId)
    {
        return DB::table('vendas as v')
            ->where('v.empresa_id', $empresaId)
            ->whereNotNull('v.cliente_id')
            ->where(fn ($q) => $q->whereNull('v.estado_emissao')->orWhere('v.estado_emissao', '!=', 'cancelado'))
            ->where(fn ($q) => $q->whereNull('v.status_pedido')->orWhere('v.status_pedido', '!=', 'cancelada'));
    }

    /**
     * Candidatos de todas as campanhas por última compra, já filtrados pelas regras gerais.
     * Retorna lista ordenada por campanha (ordem) e, dentro dela, pela última compra mais antiga.
     */
    public static function elegiveis(int $empresaId, ?string $somenteCampanha = null): array
    {
        $interessados = (!$somenteCampanha || $somenteCampanha === 'interessado') ? self::elegiveisInteressados($empresaId) : [];
        return array_merge(self::elegiveisPorCompra($empresaId, $somenteCampanha), $interessados);
    }

    /** Candidatos por última compra (reposição, esfriando, inativo). */
    private static function elegiveisPorCompra(int $empresaId, ?string $somenteCampanha = null): array
    {
        $cfg = Crm::config($empresaId);
        $camps = Crm::campanhas($empresaId)->filter(fn ($c) => $c->chave !== 'interessado' && $c->ativa);
        if ($somenteCampanha) {
            $camps = $camps->where('chave', $somenteCampanha);
        }
        if ($camps->isEmpty()) {
            return [];
        }
        $hoje = Carbon::today();

        $ultimas = self::vendasValidas($empresaId)
            ->selectRaw('v.cliente_id, MAX(COALESCE(v.data_registro, v.created_at)) as ultima, MAX(v.id) as ultima_id')
            ->groupBy('v.cliente_id')->get();

        // já existe ciclo desta campanha para esta compra? (não repete pela mesma compra)
        $usados = DB::table('crm_ciclos')->where('empresa_id', $empresaId)->get(['campanha_id', 'telefone', 'referencia'])
            ->map(fn ($c) => $c->campanha_id . '|' . $c->telefone . '|' . $c->referencia)->flip();
        $ativos = DB::table('crm_ciclos')->where('empresa_id', $empresaId)->whereIn('status', ['ativo', 'respondido'])->pluck('telefone')->flip();
        $bloq = DB::table('crm_bloqueios')->where('empresa_id', $empresaId)->pluck('telefone')->flip();
        $limiteContato = now()->subDays((int) $cfg['intervalo_campanhas_dias']);
        $recentes = DB::table('crm_envios')->where('empresa_id', $empresaId)->whereNotNull('enviado_em')
            ->where('enviado_em', '>', $limiteContato)->pluck('telefone')->flip();

        $clientes = DB::table('clientes')->where('empresa_id', $empresaId)
            ->whereIn('id', $ultimas->pluck('cliente_id'))
            ->get(['id', 'razao_social', 'nome_fantasia', 'cpf_cnpj', 'celular', 'telefone', 'inativo', 'funcionario_id'])->keyBy('id');

        $out = [];
        foreach ($ultimas as $u) {
            $c = $clientes[$u->cliente_id] ?? null;
            if (!$c || (int) ($c->inativo ?? 0) === 1) {
                continue;
            }
            $ult = Carbon::parse($u->ultima);
            $dias = $ult->copy()->startOfDay()->diffInDays($hoje);
            $camp = $camps->first(fn ($k) => $dias >= (int) $k->dias_min && ($k->dias_max === null || $dias <= (int) $k->dias_max));
            if (!$camp) {
                continue;
            }
            $tel = Crm::telefoneCliente($c);
            $motivoFora = null;
            if (!$tel) {
                $motivoFora = 'sem celular válido';
            } elseif (isset($bloq[$tel])) {
                $motivoFora = 'pediu para não receber';
            } elseif (isset($ativos[$tel])) {
                $motivoFora = 'já está em outra campanha';
            } elseif (isset($recentes[$tel])) {
                $motivoFora = 'recebeu contato há menos de ' . $cfg['intervalo_campanhas_dias'] . ' dias';
            } elseif (isset($usados[$camp->id . '|' . $tel . '|v' . $u->ultima_id])) {
                $motivoFora = 'já foi contatado por esta compra';
            }
            $out[] = [
                'campanha' => $camp->chave,
                'campanha_id' => $camp->id,
                'ordem' => (int) $camp->ordem,
                'cliente_id' => (int) $c->id,
                'nome' => Crm::capitalizar($c->nome_fantasia ?: $c->razao_social),
                'primeiro_nome' => Crm::primeiroNome($c),
                'telefone' => $tel,
                'ultima' => $ult->format('Y-m-d'),
                'venda_id' => (int) $u->ultima_id,
                'dias' => $dias,
                'fora' => $motivoFora,
            ];
        }
        usort($out, fn ($a, $b) => [$a['ordem'], $a['ultima']] <=> [$b['ordem'], $b['ultima']]);
        return $out;
    }

    public const CLASSIFICACAO = [
        'interessado' => 'Interessado', 'orcamento_enviado' => 'Orçamento enviado',
        'aguardando_decisao' => 'Aguardando decisão', 'nao_comprou' => 'Não comprou',
    ];

    /**
     * Quem conversou na central e não comprou (oportunidades classificadas pelo atendente).
     * Entra na campanha "interessado" depois de X horas da classificação (dias_min = horas), se ainda não comprou.
     */
    public static function elegiveisInteressados(int $empresaId): array
    {
        $camp = Crm::campanhas($empresaId)->get('interessado');
        if (!$camp || !$camp->ativa) {
            return [];
        }
        $cfg = Crm::config($empresaId);
        $limite = now()->subHours(max(0, (int) $camp->dias_min));
        $opos = DB::table('crm_oportunidades')->where('empresa_id', $empresaId)->where('status', 'aberta')
            ->whereIn('classificacao', array_keys(self::CLASSIFICACAO))
            ->where('data_conversa', '<=', $limite)
            ->where('data_conversa', '>=', now()->subDays(60))
            ->orderBy('data_conversa')->get();
        if ($opos->isEmpty()) {
            return [];
        }
        $bloq = DB::table('crm_bloqueios')->where('empresa_id', $empresaId)->pluck('telefone')->flip();
        $ativos = DB::table('crm_ciclos')->where('empresa_id', $empresaId)->whereIn('status', ['ativo', 'respondido'])->pluck('telefone')->flip();
        $recentes = DB::table('crm_envios')->where('empresa_id', $empresaId)->whereNotNull('enviado_em')
            ->where('enviado_em', '>', now()->subDays((int) $cfg['intervalo_campanhas_dias']))->pluck('telefone')->flip();
        $out = [];
        foreach ($opos as $o) {
            // comprou depois da conversa? não precisa de lembrete
            $comprou = $o->cliente_id && self::vendasValidas($empresaId)->where('v.cliente_id', $o->cliente_id)
                ->whereRaw('COALESCE(v.data_registro, v.created_at) >= ?', [$o->data_conversa])->exists();
            $fora = null;
            if ($comprou) {
                $fora = 'já comprou depois da conversa';
            } elseif (isset($bloq[$o->telefone])) {
                $fora = 'pediu para não receber';
            } elseif (isset($ativos[$o->telefone])) {
                $fora = 'já está em outra campanha';
            } elseif (isset($recentes[$o->telefone])) {
                $fora = 'recebeu contato há menos de ' . $cfg['intervalo_campanhas_dias'] . ' dias';
            }
            $nome = Crm::capitalizar((string) ($o->nome ?: ''));
            $primeiro = preg_split('/\s+/u', $nome)[0] ?? '';
            $out[] = [
                'campanha' => 'interessado',
                'campanha_id' => $camp->id,
                'ordem' => (int) $camp->ordem,
                'cliente_id' => $o->cliente_id ? (int) $o->cliente_id : null,
                'oportunidade_id' => (int) $o->id,
                'classificacao' => $o->classificacao,
                'atendente' => $o->atendente,
                'nome' => $nome !== '' ? $nome : Crm::formatarTelefone($o->telefone),
                'primeiro_nome' => mb_strlen($primeiro) > 2 ? $primeiro : '',
                'telefone' => $o->telefone,
                'ultima' => Carbon::parse($o->data_conversa)->format('Y-m-d'),
                'venda_id' => null,
                'dias' => Carbon::parse($o->data_conversa)->startOfDay()->diffInDays(Carbon::today()),
                'fora' => $fora,
            ];
        }
        return $out;
    }

    /** Total do pedido (mesma conta do recebimento: itens − desconto + acréscimo + frete, quando existe). */
    public static function totalVenda(object $v): float
    {
        return round((float) $v->valor_total - (float) ($v->desconto ?? 0) + (float) ($v->acrescimo ?? 0) + (float) ($v->frete ?? 0), 2);
    }

    /** Detalhes da última compra para a mensagem e para a tela. */
    public static function contextoVenda(int $vendaId): array
    {
        $v = DB::table('vendas')->where('id', $vendaId)->first();
        if (!$v) {
            return [];
        }
        $itens = DB::table('item_vendas as i')->join('produtos as p', 'p.id', '=', 'i.produto_id')
            ->where('i.venda_id', $vendaId)->groupBy('p.id', 'p.nome')
            ->selectRaw('p.nome, SUM(i.quantidade) q')->orderByDesc('q')->get();
        return [
            'venda_id' => (int) $v->id,
            'data' => Carbon::parse($v->data_registro ?: $v->created_at)->format('Y-m-d'),
            'valor' => self::totalVenda($v),
            'itens' => $itens->count(),
            'unidades' => (float) $itens->sum('q'),
            'produtos' => $itens->take(3)->map(fn ($i) => self::nomeCurto($i->nome))->values()->all(),
            'produtos_todos' => $itens->take(12)->map(fn ($i) => rtrim(rtrim(number_format((float) $i->q, 2, ',', ''), '0'), ',') . '× ' . Crm::capitalizar($i->nome))->values()->all(),
        ];
    }

    /** "LATTAFA KHAMRAH EDP 100ML" → "Khamrah" (tira marca comum e volume para caber na frase). */
    public static function nomeCurto(string $nome): string
    {
        $n = preg_replace('/\b\d+\s?ML\b|\b(EDP|EDT|EDC|EAU DE PARFUM|EAU DE TOILETTE|PERFUME|MASCULINO|FEMININO|UNISSEX)\b/iu', '', $nome);
        $n = trim(preg_replace('/\s{2,}/', ' ', $n));
        $p = preg_split('/\s+/', $n);
        // primeira palavra costuma ser a marca (Lattafa, Armaf…): tira quando sobra nome depois
        if (count($p) >= 3) {
            array_shift($p);
        }
        return Crm::capitalizar(implode(' ', array_slice($p, 0, 3)));
    }

    /**
     * Monta a fila: cria o ciclo e a 1ª tentativa de quem é elegível, até a capacidade do dia
     * (limite diário − o que já está aguardando/pendente). Retorna quantos entraram.
     */
    public static function gerar(int $empresaId, ?int $usuarioId = null, ?int $maximo = null): int
    {
        $cfg = Crm::config($empresaId);
        $camps = Crm::campanhas($empresaId);
        $naFila = DB::table('crm_envios')->where('empresa_id', $empresaId)->whereIn('status', ['aguardando_aprovacao', 'pendente', 'processando'])->count();
        $capacidade = $maximo ?? max(0, (int) $cfg['limite_diario'] - $naFila);
        if ($capacidade <= 0) {
            return 0;
        }
        $n = 0;
        foreach (self::elegiveis($empresaId) as $e) {
            if ($n >= $capacidade) {
                break;
            }
            if ($e['fora']) {
                continue;
            }
            $camp = $camps[$e['campanha']];
            $opo = $e['oportunidade_id'] ?? null;
            $ctx = $opo
                ? ['dias' => $e['dias'], 'primeiro_nome' => $e['primeiro_nome'], 'classificacao' => $e['classificacao'], 'atendente' => $e['atendente']]
                : self::contextoVenda($e['venda_id']) + ['dias' => $e['dias'], 'primeiro_nome' => $e['primeiro_nome']];
            $motivo = $opo
                ? (self::CLASSIFICACAO[$e['classificacao']] ?? 'Interessado') . ' na central em ' . Carbon::parse($e['ultima'])->format('d/m/Y') . ($e['atendente'] ? ' (' . $e['atendente'] . ')' : '')
                : 'Última compra em ' . Carbon::parse($e['ultima'])->format('d/m/Y') . ' (' . $e['dias'] . ' dias)';
            try {
                DB::transaction(function () use ($empresaId, $camp, $e, $ctx, $cfg, $usuarioId, $opo, $motivo) {
                    $cicloId = DB::table('crm_ciclos')->insertGetId([
                        'empresa_id' => $empresaId,
                        'campanha_id' => $camp->id,
                        'cliente_id' => $e['cliente_id'],
                        'oportunidade_id' => $opo,
                        'telefone' => $e['telefone'],
                        'nome' => mb_substr($e['nome'], 0, 150),
                        'referencia' => $opo ? 'o' . $opo : 'v' . $e['venda_id'],
                        'motivo' => mb_substr($motivo, 0, 255),
                        'contexto' => json_encode($ctx, JSON_UNESCAPED_UNICODE),
                        'status' => 'ativo',
                        'usuario_id' => $usuarioId,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    DB::table('crm_envios')->insert([
                        'empresa_id' => $empresaId,
                        'ciclo_id' => $cicloId,
                        'tentativa' => 1,
                        'uuid' => (string) Str::uuid(),
                        'telefone' => $e['telefone'],
                        'texto' => Crm::montarTexto($camp->texto_1, $ctx, $cicloId),
                        'agendado_para' => now(),
                        'status' => $cfg['aprovar_antes'] ? 'aguardando_aprovacao' : 'pendente',
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    if ($opo) {
                        DB::table('crm_oportunidades')->where('id', $opo)->update(['status' => 'em_campanha', 'updated_at' => now()]);
                    }
                });
                $n++;
            } catch (\Illuminate\Database\QueryException $ex) {
                // corrida com outra geração (índice único de ciclo ativo / referência): só pula
                if ((int) ($ex->errorInfo[1] ?? 0) !== 1062) {
                    throw $ex;
                }
            }
        }
        return $n;
    }

    /**
     * Compra nova encerra o ciclo e cancela o que falta enviar.
     * Conta como venda do CRM quando o cliente respondeu e comprou dentro da janela de atribuição.
     */
    public static function verificarCompras(int $empresaId): int
    {
        $cfg = Crm::config($empresaId);
        $janela = (int) $cfg['janela_atribuicao_dias'];
        $ciclos = DB::table('crm_ciclos')->where('empresa_id', $empresaId)->whereIn('status', ['ativo', 'respondido'])
            ->whereNotNull('cliente_id')->get();
        $n = 0;
        foreach ($ciclos as $c) {
            $venda = self::vendasValidas($empresaId)->where('v.cliente_id', $c->cliente_id)
                ->whereRaw('COALESCE(v.data_registro, v.created_at) > ?', [$c->created_at])
                ->orderByRaw('COALESCE(v.data_registro, v.created_at)')
                ->first(['v.*', DB::raw('COALESCE(v.data_registro, v.created_at) as data_crm')]);
            if (!$venda) {
                continue;
            }
            $valor = self::totalVenda($venda);
            $converteu = $c->status === 'respondido' && $c->respondido_em
                && Carbon::parse($venda->data_crm)->lte(Carbon::parse($c->respondido_em)->addDays($janela));
            self::encerrar($c->id, $converteu ? 'convertido' : 'encerrado', $converteu ? 'comprou depois de responder' : 'comprou (sem resposta ao CRM)', [
                'venda_id' => $venda->id,
                'venda_valor' => $valor,
            ]);
            $n++;
        }
        return $n;
    }

    /** Fecha o ciclo e cancela os envios que ainda não saíram. */
    public static function encerrar(int $cicloId, string $status, string $motivo, array $extra = []): void
    {
        DB::transaction(function () use ($cicloId, $status, $motivo, $extra) {
            DB::table('crm_ciclos')->where('id', $cicloId)->update([
                'status' => $status, 'motivo_fim' => mb_substr($motivo, 0, 120), 'encerrado_em' => now(), 'updated_at' => now(),
            ] + $extra);
            DB::table('crm_envios')->where('ciclo_id', $cicloId)->whereIn('status', ['aguardando_aprovacao', 'pendente'])
                ->update(['status' => 'cancelado', 'erro' => mb_substr($motivo, 0, 500), 'updated_at' => now()]);
        });
    }

    /** Envio feito à mão (WhatsApp aberto pelo botão): marca como enviado e começa a contar a 2ª tentativa. */
    public static function marcarEnviado(int $empresaId, int $envioId, ?int $usuarioId): bool
    {
        return DB::table('crm_envios')->where('empresa_id', $empresaId)->where('id', $envioId)
            ->whereIn('status', ['aguardando_aprovacao', 'pendente'])
            ->update([
                'status' => 'enviado', 'enviado_em' => now(), 'updated_at' => now(),
                'aprovado_por' => DB::raw('COALESCE(aprovado_por, ' . (int) $usuarioId . ')'),
                'aprovado_em' => DB::raw('COALESCE(aprovado_em, NOW())'),
            ]) > 0;
    }

    /** O cliente respondeu: para a automação e conta a venda se ele comprar dentro da janela. */
    public static function marcarRespondido(int $empresaId, int $cicloId, ?string $atendente = null): bool
    {
        return DB::transaction(function () use ($empresaId, $cicloId, $atendente) {
            $n = DB::table('crm_ciclos')->where('empresa_id', $empresaId)->where('id', $cicloId)->where('status', 'ativo')
                ->update(['status' => 'respondido', 'respondido_em' => now(), 'atendente' => $atendente, 'updated_at' => now()]);
            if ($n) {
                DB::table('crm_envios')->where('ciclo_id', $cicloId)->whereIn('status', ['aguardando_aprovacao', 'pendente'])
                    ->update(['status' => 'cancelado', 'erro' => 'cliente respondeu', 'updated_at' => now()]);
                DB::table('crm_envios')->where('ciclo_id', $cicloId)->whereIn('status', ['enviado', 'entregue'])
                    ->update(['status' => 'respondido', 'updated_at' => now()]);
            }
            return $n > 0;
        });
    }

    /**
     * Régua: 2ª tentativa depois de X dias sem resposta; depois da última tentativa + X dias, encerra o ciclo
     * (o cliente volta a poder entrar em outra campanha respeitando o intervalo entre campanhas).
     */
    public static function avancarCiclos(int $empresaId): array
    {
        $cfg = Crm::config($empresaId);
        $dias = (int) $cfg['segunda_tentativa_dias'];
        $maxTent = (int) $cfg['tentativas'];
        $camps = DB::table('crm_campanhas')->where('empresa_id', $empresaId)->get()->keyBy('id');
        $segundas = 0;
        $encerrados = 0;
        $ciclos = DB::table('crm_ciclos')->where('empresa_id', $empresaId)->where('status', 'ativo')->get();
        foreach ($ciclos as $c) {
            $envios = DB::table('crm_envios')->where('ciclo_id', $c->id)->orderBy('tentativa')->get();
            $ult = $envios->last();
            if (!$ult || !$ult->enviado_em || in_array($ult->status, ['aguardando_aprovacao', 'pendente', 'processando'], true)) {
                continue; // ainda não saiu a última mensagem
            }
            if (\Carbon\Carbon::parse($ult->enviado_em)->gt(now()->subDays($dias))) {
                continue; // ainda dentro do prazo de espera
            }
            $camp = $camps[$c->campanha_id] ?? null;
            if ((int) $ult->tentativa < $maxTent && $camp && trim((string) $camp->texto_2) !== '') {
                $ctx = json_decode((string) $c->contexto, true) ?: [];
                try {
                    DB::table('crm_envios')->insert([
                        'empresa_id' => $empresaId, 'ciclo_id' => $c->id, 'tentativa' => (int) $ult->tentativa + 1,
                        'uuid' => (string) Str::uuid(), 'telefone' => $c->telefone,
                        'texto' => Crm::montarTexto($camp->texto_2, $ctx, (int) $c->id),
                        'agendado_para' => now(),
                        'status' => $cfg['aprovar_antes'] ? 'aguardando_aprovacao' : 'pendente',
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $segundas++;
                } catch (\Illuminate\Database\QueryException $e) {
                    if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
                        throw $e;
                    }
                }
            } else {
                self::encerrar($c->id, 'encerrado', 'sem resposta depois de ' . $ult->tentativa . ' tentativa(s)');
                $encerrados++;
            }
        }
        return ['segundas' => $segundas, 'encerrados' => $encerrados];
    }

    /** Não contatar mais: vale para todas as campanhas. */
    public static function bloquear(int $empresaId, string $tel, string $origem, ?string $obs, ?int $usuarioId): void
    {
        DB::table('crm_bloqueios')->insertOrIgnore([
            'empresa_id' => $empresaId, 'telefone' => $tel, 'origem' => $origem,
            'observacao' => $obs ? mb_substr($obs, 0, 255) : null, 'usuario_id' => $usuarioId, 'created_at' => now(),
        ]);
        foreach (DB::table('crm_ciclos')->where('empresa_id', $empresaId)->where('telefone', $tel)->whereIn('status', ['ativo', 'respondido'])->pluck('id') as $id) {
            self::encerrar($id, 'bloqueado', 'pediu para não receber mensagens');
        }
    }
}
