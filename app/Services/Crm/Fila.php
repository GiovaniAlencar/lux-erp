<?php

namespace App\Services\Crm;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Quem entra em cada campanha e a montagem da fila (fase 2: só monta; o envio vem na fase 4).
 *
 * Compra válida = pedido do cliente que não foi cancelado (mesma regra do fechamento do mês).
 * Dias desde a última compra decidem a campanha: reposição (15–44), esfriando (45–74), inativo (75–365),
 * conforme a configuração de cada uma.
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
            $ctx = self::contextoVenda($e['venda_id']) + ['dias' => $e['dias'], 'primeiro_nome' => $e['primeiro_nome']];
            try {
                DB::transaction(function () use ($empresaId, $camp, $e, $ctx, $cfg, $usuarioId) {
                    $cicloId = DB::table('crm_ciclos')->insertGetId([
                        'empresa_id' => $empresaId,
                        'campanha_id' => $camp->id,
                        'cliente_id' => $e['cliente_id'],
                        'telefone' => $e['telefone'],
                        'nome' => mb_substr($e['nome'], 0, 150),
                        'referencia' => 'v' . $e['venda_id'],
                        'motivo' => 'Última compra em ' . Carbon::parse($e['ultima'])->format('d/m/Y') . ' (' . $e['dias'] . ' dias)',
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
