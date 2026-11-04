<?php

namespace App\Http\Controllers;

use App\Http\Controllers\VendaComprovanteController;
use App\Models\FinConta;
use App\Models\RotaEntrega;
use App\Models\RotaEntregaItem;
use App\Models\Venda;
use App\Models\VendaAuditoria;
use App\Models\VendaComprovante;
use App\Services\RegistroRecebimento;
use App\Services\SaldoCliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * App do motoboy: um link por rota (sem login), com todas as entregas agrupadas por bairro
 * e ordenadas pela distância, botão do Waze, recebimento com foto do comprovante (cartão/PIX),
 * calculadora da maquineta e registro de ocorrência.
 *
 * Segurança: o link tem um token aleatório de 40 caracteres; só funciona enquanto a rota
 * está "em rota" (depois de finalizada mostra só o resumo, sem permitir alterar nada).
 */
class MotoboyController extends Controller
{
    private const FORMAS = [
        'pix' => 'PIX',
        'dinheiro' => 'Dinheiro',
        'credito' => 'Cartão de crédito',
        'debito' => 'Cartão de débito',
    ];

    public static function ativo(): bool
    {
        static $ok = null;
        return $ok ??= Schema::hasColumn('rotas_entrega', 'token_motoboy') && Schema::hasColumn('rota_entrega_itens', 'recebido_forma');
    }

    /** Gera (uma vez) o link do motoboy da rota. */
    public static function tokenDaRota(RotaEntrega $rota): ?string
    {
        if (!self::ativo()) {
            return null;
        }
        if (!$rota->token_motoboy) {
            do {
                $t = Str::random(40);
            } while (RotaEntrega::where('token_motoboy', $t)->exists());
            RotaEntrega::whereKey($rota->id)->update(['token_motoboy' => $t]);
            $rota->token_motoboy = $t;
        }
        return $rota->token_motoboy;
    }

    private function rota(string $token): RotaEntrega
    {
        abort_unless(self::ativo() && strlen($token) === 40, 404);
        return RotaEntrega::with(['itens.venda.cliente.cidade'])->where('token_motoboy', $token)->firstOrFail();
    }

    /** Quanto o motoboy cobra: o que falta do pedido, limitado ao que foi marcado "cobrar na entrega". */
    private function falta(?Venda $v): float
    {
        if (!$v) {
            return 0.0;
        }
        $total = VendaComprovanteController::totalVenda($v);
        $pago = (float) VendaComprovante::where('venda_id', $v->id)->sum('valor');
        $falta = round(max(0, $total - $pago), 2);
        $linhas = $this->linhasPagamento($v);
        if ($linhas !== null) {
            // o "já pago" fica para o vendedor anexar o comprovante; o motoboy só cobra o "na entrega"
            $falta = min($falta, round($linhas->where('entrega', true)->sum('valor'), 2));
        }
        return $falta;
    }

    /**
     * Pagamentos da venda com a marcação "na entrega" / "já pago" (SQL 35).
     * null = venda sem essa informação (antiga): o motoboy cobra o que falta, como antes.
     */
    private function linhasPagamento(Venda $v): ?\Illuminate\Support\Collection
    {
        static $temColuna = null;
        $temColuna ??= Schema::hasColumn('conta_recebers', 'na_entrega');
        if (!$temColuna) {
            return null;
        }
        $contas = \App\Models\ContaReceber::where('venda_id', $v->id)->orderBy('data_vencimento')->orderBy('id')->get();
        if ($contas->whereNotNull('na_entrega')->isEmpty()) {
            return null;
        }
        $marca = \App\Services\RecebimentoVenda::MARCA_RESTANTE;
        return $contas->map(fn ($c) => [
            'tipo' => $c->getTipoPagamento() ?: 'Pagamento',
            'valor' => (float) $c->valor_integral,
            // o "restante automático" (parte que não foi lançada na venda) é cobrado na entrega
            'entrega' => $c->na_entrega === null ? str_contains((string) $c->observacao, $marca) : (bool) (int) $c->na_entrega,
        ]);
    }

    private function textoLinhas(\Illuminate\Support\Collection $linhas): string
    {
        $fmt = function ($grupo) {
            return $grupo->groupBy('tipo')->map(function ($g, $tipo) {
                $n = $g->count();
                $iguais = $g->pluck('valor')->unique()->count() === 1;
                return $tipo . ' ' . ($n > 1 && $iguais
                    ? $n . 'x R$ ' . number_format($g->first()['valor'], 2, ',', '.')
                    : 'R$ ' . number_format($g->sum('valor'), 2, ',', '.'));
            })->implode(' + ');
        };
        $txt = [];
        if (($e = $linhas->where('entrega', true))->isNotEmpty()) {
            $txt[] = 'Cobrar na entrega: ' . $fmt($e);
        }
        if (($p = $linhas->where('entrega', false))->isNotEmpty()) {
            $txt[] = 'Já pago antes: ' . $fmt($p) . ' (não cobrar)';
        }
        return implode(' · ', $txt);
    }

    private function itemJson(RotaEntregaItem $i, ?RotaEntrega $rota = null): array
    {
        $rota = $rota ?? $i->rota;
        $v = $i->venda;
        $cli = optional($v)->cliente;
        $falta = $v && ($v->status_pagamento ?? '') !== 'pago' ? $this->falta($v) : 0.0;
        $tel = preg_replace('/\D+/', '', (string) ($i->telefone ?: optional($cli)->celular));
        if ($tel !== '' && strlen($tel) <= 11) {
            $tel = '55' . ltrim($tel, '0');
        }
        $cid = optional(optional($cli)->cidade);
        return [
            'id' => $i->id,
            'venda' => $i->venda_id,
            'cliente' => $i->cliente_nome ?: optional($cli)->nome_exibicao,
            'telefone' => $tel ?: null,
            'endereco' => $i->enderecoFormatado(),
            'complemento' => $i->complemento,
            'bairro' => trim((string) $i->bairro) ?: 'Sem bairro',
            'cidade' => $cid->nome ? $cid->nome . '/' . $cid->uf : null,
            'lat' => $i->lat !== null ? (float) $i->lat : null,
            'lng' => $i->lng !== null ? (float) $i->lng : null,
            'prioridade' => (bool) $i->prioridade,
            'obs_prioridade' => $i->observacao_prioridade,
            'aviso' => $i->aviso_entrega,
            'embalagem' => $i->embalagem_tipo ? trim(($i->embalagem_qtd ? $i->embalagem_qtd . ' ' : '') . $i->embalagem_tipo) : null,
            'pagamento_texto' => ($v && ($linhas = $this->linhasPagamento($v)) !== null) ? $this->textoLinhas($linhas) : $i->textoPagamentoMotoboy(),
            'forma_prevista' => $i->tipo_pagamento_nome,
            'falta' => $falta,
            'status' => $i->status_entrega ?: 'pendente',
            'ocorrencia' => $i->ocorrencia_tipo ? (RotaEntregaItem::tiposOcorrencia()[$i->ocorrencia_tipo] ?? $i->ocorrencia_tipo) : null,
            'recebimentos' => array_map(fn ($r) => [
                'id' => $r['comprovante_id'] ?? null,
                'forma' => self::FORMAS[$r['forma']] ?? $r['forma'],
                'valor' => (float) $r['valor'],
                'liquido' => (float) $r['liquido'],
                'bandeira' => !empty($r['bandeira']) ? (config('lux.maquineta.bandeiras.' . $r['bandeira'] . '.rotulo') ?? $r['bandeira']) : null,
                'parcelas' => $r['parcelas'] ?? null,
            ], $this->recebimentos($i)),
            'editavel' => $rota ? $this->podeEditar($rota, $i) : false,
            'recebido' => $i->recebido_forma ? [
                'forma' => $i->recebido_forma === 'misto' ? 'Mais de uma forma' : (self::FORMAS[$i->recebido_forma] ?? $i->recebido_forma),
                'valor' => (float) $i->recebido_valor,
                'liquido' => (float) $i->recebido_liquido,
                'bandeira' => $i->recebido_bandeira,
                'parcelas' => $i->recebido_parcelas,
            ] : null,
            'concluido_em' => optional($i->confirmado_em)->format('H:i'),
        ];
    }

    public function show(string $token)
    {
        $rota = $this->rota($token);
        $itens = $rota->itens->map(fn ($i) => $this->itemJson($i, $rota))->values();
        $taxas = collect(config('lux.maquineta.bandeiras', []))->map(fn ($b) => ['nome' => $b['rotulo'], 'taxas' => $b['taxas']]);
        $loja = config('lux.geo.loja_lat') && config('lux.geo.loja_lng')
            ? ['lat' => (float) config('lux.geo.loja_lat'), 'lng' => (float) config('lux.geo.loja_lng')] : null;

        return response()
            ->view('motoboy.rota', compact('rota', 'itens', 'taxas', 'loja', 'token'))
            ->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer');
    }

    /** Lista atualizada (coordenadas vão chegando; outro aparelho pode ter concluído uma entrega). */
    public function dados(string $token)
    {
        $rota = $this->rota($token);
        return response()->json([
            'status' => $rota->status,
            'itens' => $rota->itens->map(fn ($i) => $this->itemJson($i, $rota))->values(),
        ])->header('Cache-Control', 'no-store');
    }

    /** Correção depois de concluída: até 12 h e enquanto o motoboy não foi pago pela rota. */
    private const HORAS_EDICAO = 12;

    private function podeEditar(RotaEntrega $rota, RotaEntregaItem $item): bool
    {
        if ($rota->status === 'em_rota') {
            return true;
        }
        return $rota->status === 'finalizada' && !$rota->motoboy_pago && $item->status_entrega === 'entregue'
            && $item->confirmado_em && $item->confirmado_em->gt(now()->subHours(self::HORAS_EDICAO));
    }

    /** Lista do que o motoboy recebeu nesta entrega (mais de um pagamento é permitido). */
    private function recebimentos(RotaEntregaItem $item): array
    {
        $lista = is_array($item->recebimentos) ? $item->recebimentos : [];
        if (!$lista && $item->recebido_forma && $item->recebido_forma !== 'misto') {
            // entrega concluída antes do SQL 38: um pagamento só, sem o id do comprovante
            $lista = [['comprovante_id' => null, 'forma' => $item->recebido_forma, 'valor' => (float) $item->recebido_valor,
                'liquido' => (float) $item->recebido_liquido, 'bandeira' => $item->recebido_bandeira, 'parcelas' => $item->recebido_parcelas, 'em' => null]];
        }
        return $lista;
    }

    /** Guarda a lista e o resumo nos campos antigos (recebido_*), usados pelas telas da rota. */
    private function salvarRecebimentos(RotaEntregaItem $item, array $lista): void
    {
        if (Schema::hasColumn('rota_entrega_itens', 'recebimentos')) {
            $item->recebimentos = array_values($lista);
        }
        $item->recebido_forma = !$lista ? null : (count(array_unique(array_column($lista, 'forma'))) > 1 ? 'misto' : $lista[0]['forma']);
        $item->recebido_valor = $lista ? round(array_sum(array_column($lista, 'valor')), 2) : null;
        $item->recebido_liquido = $lista ? round(array_sum(array_column($lista, 'liquido')), 2) : null;
        $ult = $lista ? end($lista) : null;
        $item->recebido_bandeira = $ult['bandeira'] ?? null;
        $item->recebido_parcelas = $ult['parcelas'] ?? null;
        if (!$lista) {
            $item->recebido_comprovante = null;
        }
    }

    /**
     * Registra UM pagamento (forma + valor + foto) na venda do item. Não conclui a entrega.
     * Retorna o resultado do registro ou uma resposta de erro.
     */
    private function registrarPagamento(Request $request, RotaEntrega $rota, RotaEntregaItem $item)
    {
        $forma = (string) $request->input('forma', '');
        $valor = \App\Services\Financeiro::valorBr($request->input('valor', ''));
        if ($forma === '') {
            return response()->json(['message' => 'Escolha a forma de pagamento.'], 422);
        }
        if ($valor <= 0) {
            return response()->json(['message' => 'Informe o valor recebido.'], 422);
        }
        $cartao = in_array($forma, ['credito', 'debito'], true);
        if (($cartao || $forma === 'pix') && !$request->hasFile('foto')) {
            return response()->json(['message' => 'Tire a foto do comprovante ' . ($cartao ? 'da maquineta' : 'do PIX') . '.'], 422);
        }
        $bandeira = null;
        $parcelas = null;
        $liquido = $valor;
        if ($cartao) {
            $bandeira = (string) $request->input('bandeira', 'visa_master');
            $tabela = config('lux.maquineta.bandeiras.' . $bandeira . '.taxas');
            if (!is_array($tabela)) {
                return response()->json(['message' => 'Escolha a bandeira do cartão.'], 422);
            }
            $parcelas = $forma === 'debito' ? 1 : (int) $request->input('parcelas', 1);
            $taxa = (float) ($forma === 'debito' ? ($tabela['debito'] ?? 0) : ($tabela[$parcelas] ?? 0));
            $liquido = round($valor * (1 - $taxa / 100), 2);
        }

        $conta = $this->contaDaForma((int) $rota->empresa_id, $forma);
        $arquivo = '';
        $nome = null;
        if ($request->hasFile('foto')) {
            $f = $request->file('foto');
            $ext = in_array(strtolower((string) $f->extension()), ['png', 'webp'], true) ? strtolower((string) $f->extension()) : 'jpg';
            $arquivo = $f->storeAs('comprovantes-vendas/' . (int) $rota->empresa_id . '/' . date('Y-m'), Str::uuid() . '.' . $ext, 'local');
            $nome = 'comprovante-motoboy-' . $item->venda_id . '.' . $ext;
        }
        $formaTexto = self::FORMAS[$forma] . ($cartao ? ' ' . (config('lux.maquineta.bandeiras.' . $bandeira . '.rotulo') ?? '') . ($forma === 'credito' ? ' ' . $parcelas . 'x' : '') : '') . ' · motoboy';

        try {
            $resultado = RegistroRecebimento::registrar(
                $item->venda, $conta, $valor, $liquido, date('Y-m-d'), $arquivo, $nome,
                $request->input('observacao') ?: ('Recebido pelo motoboy' . ($rota->motoboy_nome ? ' ' . $rota->motoboy_nome : '')),
                null, $formaTexto, true, $conta ? null : 'outra'
            );
        } catch (\Throwable $e) {
            if ($arquivo !== '') {
                \Storage::disk('local')->delete($arquivo);
            }
            return response()->json(['message' => $e instanceof \InvalidArgumentException ? $e->getMessage() : 'Não foi possível registrar o pagamento. Tente de novo.'], 422);
        }
        RegistroRecebimento::enviarGrupo($resultado['comprovante'], $conta);

        $lista = $this->recebimentos($item);
        $lista[] = [
            'comprovante_id' => $resultado['comprovante']->id,
            'forma' => $forma, 'valor' => $valor, 'liquido' => $liquido,
            'bandeira' => $bandeira, 'parcelas' => $parcelas, 'em' => now()->format('Y-m-d H:i'),
        ];
        $this->salvarRecebimentos($item, $lista);
        if ($arquivo !== '') {
            $item->recebido_comprovante = $arquivo;
        }
        $item->save();
        return $resultado;
    }

    private function validarPagamento(Request $request): void
    {
        $request->validate([
            'forma' => 'nullable|in:pix,dinheiro,credito,debito',
            'valor' => 'nullable|string|max:20',
            'bandeira' => 'nullable|string|max:20',
            'parcelas' => 'nullable|integer|min:1|max:18',
            'foto' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:8192',
            'observacao' => 'nullable|string|max:255',
            'sem_pagamento' => 'nullable|boolean',
        ], [
            'foto.mimes' => 'A foto precisa ser JPG, PNG ou WEBP.',
            'foto.max' => 'A foto pode ter no máximo 8 MB.',
        ]);
    }

    private function itemDaRota(RotaEntrega $rota, int $itemId): RotaEntregaItem
    {
        $item = $rota->itens->firstWhere('id', $itemId);
        abort_unless($item, 404);
        return $item;
    }

    /** Registra uma parte do pagamento sem concluir a entrega (ex.: PIX agora, o resto no cartão). */
    public function pagamento(Request $request, string $token, int $itemId)
    {
        $rota = $this->rota($token);
        $item = $this->itemDaRota($rota, $itemId);
        if (!$this->podeEditar($rota, $item) || $item->status_entrega === 'ocorrencia') {
            return response()->json(['message' => 'Esta entrega não pode mais ser alterada pelo app. Fale com a loja.'], 423);
        }
        $this->validarPagamento($request);
        $r = $this->registrarPagamento($request, $rota, $item);
        if ($r instanceof \Illuminate\Http\JsonResponse) {
            return $r;
        }
        $item->refresh()->load('venda.cliente.cidade');
        return response()->json(['item' => $this->itemJson($item, $rota), 'saldo' => $r['saldo'] ?? 0, 'falta' => $this->falta($item->venda)]);
    }

    /** Desfaz um pagamento lançado errado nesta entrega (estorna do extrato e apaga o comprovante). */
    public function desfazerPagamento(Request $request, string $token, int $itemId, int $comprovanteId)
    {
        $rota = $this->rota($token);
        $item = $this->itemDaRota($rota, $itemId);
        if (!$this->podeEditar($rota, $item)) {
            return response()->json(['message' => 'Esta entrega não pode mais ser alterada pelo app. Fale com a loja.'], 423);
        }
        $lista = $this->recebimentos($item);
        $pos = collect($lista)->search(fn ($r) => (int) ($r['comprovante_id'] ?? 0) === $comprovanteId);
        if ($pos === false) {
            return response()->json(['message' => 'Este pagamento não foi lançado por esta entrega.'], 404);
        }
        $c = VendaComprovante::where('venda_id', $item->venda_id)->find($comprovanteId);
        if ($c) {
            RegistroRecebimento::desfazer($c, 'corrigido pelo motoboy' . ($rota->motoboy_nome ? ' ' . $rota->motoboy_nome : '') . ' (rota #' . $rota->id . ')');
        }
        unset($lista[$pos]);
        $this->salvarRecebimentos($item, array_values($lista));
        $item->save();
        $item->refresh()->load('venda.cliente.cidade');
        return response()->json(['item' => $this->itemJson($item, $rota), 'falta' => $this->falta($item->venda)]);
    }

    public function entregar(Request $request, string $token, int $itemId)
    {
        $rota = $this->rota($token);
        if ($rota->status !== 'em_rota') {
            return response()->json(['message' => 'Esta rota não está mais em andamento.'], 423);
        }
        $item = $this->itemDaRota($rota, $itemId);
        if (in_array($item->status_entrega, ['entregue', 'ocorrencia'], true)) {
            return response()->json(['message' => 'Esta entrega já foi registrada.'], 409);
        }
        $this->validarPagamento($request);

        $semPagamento = $request->boolean('sem_pagamento');
        $valor = \App\Services\Financeiro::valorBr($request->input('valor', ''));
        $resultado = null;
        if ($valor > 0) {
            $resultado = $this->registrarPagamento($request, $rota, $item);
            if ($resultado instanceof \Illuminate\Http\JsonResponse) {
                return $resultado;
            }
        }
        $falta = $this->falta($item->venda()->first());
        if (!$semPagamento && $falta > 0.009) {
            return response()->json([
                'message' => 'Ainda falta R$ ' . number_format($falta, 2, ',', '.') . '. Registre o restante ou marque "não recebi / vai pagar depois".',
                'item' => $this->itemJson($item->fresh()->load('venda.cliente.cidade'), $rota),
            ], 422);
        }

        $item->status_entrega = 'entregue';
        $item->ocorrencia_tipo = null;
        $item->ocorrencia_observacao = $semPagamento && $falta > 0.009 ? 'Entregue sem receber o restante (vai pagar depois)' : null;
        $item->confirmado_em = now();
        $item->save();

        $this->statusVenda($item, 'entregue', 'Pedido entregue (app do motoboy)'
            . ($semPagamento && $falta > 0.009 ? ' — faltou receber R$ ' . number_format($falta, 2, ',', '.') . ', vai pagar depois.' : '.'));
        $this->verificarFim($rota);

        $item->refresh()->load('venda.cliente.cidade');
        return response()->json([
            'item' => $this->itemJson($item, $rota->fresh()),
            'saldo' => is_array($resultado) ? ($resultado['saldo'] ?? 0) : 0,
            'falta' => $this->falta($item->venda),
        ]);
    }

    public function ocorrencia(Request $request, string $token, int $itemId)
    {
        $rota = $this->rota($token);
        if ($rota->status !== 'em_rota') {
            return response()->json(['message' => 'Esta rota não está mais em andamento.'], 423);
        }
        $item = $rota->itens->firstWhere('id', $itemId);
        abort_unless($item, 404);
        if (in_array($item->status_entrega, ['entregue', 'ocorrencia'], true)) {
            return response()->json(['message' => 'Esta entrega já foi registrada.'], 409);
        }
        $request->validate([
            'tipo' => 'required|in:' . implode(',', array_keys(RotaEntregaItem::tiposOcorrencia())),
            'observacao' => 'nullable|string|max:1000',
        ]);
        $item->status_entrega = 'ocorrencia';
        $item->ocorrencia_tipo = $request->tipo;
        $item->ocorrencia_observacao = $request->observacao;
        $item->confirmado_em = now();
        $item->save();
        $label = RotaEntregaItem::tiposOcorrencia()[$item->ocorrencia_tipo];
        $this->statusVenda($item, 'ocorrencia_entrega', 'Ocorrência na entrega: ' . $label . ($item->ocorrencia_observacao ? ' — ' . $item->ocorrencia_observacao : ''));
        $this->verificarFim($rota);

        $item->refresh()->load('venda.cliente.cidade');
        return response()->json(['item' => $this->itemJson($item, $rota)]);
    }

    /** Conta do Financeiro de cada forma (config lux.motoboy). null = sem financeiro configurado. */
    private function contaDaForma(int $empresaId, string $forma): ?FinConta
    {
        if (!Schema::hasTable('fin_contas')) {
            return null;
        }
        $contas = FinConta::daEmpresa($empresaId);
        if ($forma === 'dinheiro') {
            return $contas->first(fn ($c) => $c->ehDinheiro());
        }
        if (in_array($forma, ['credito', 'debito'], true)) {
            $nome = mb_strtolower((string) config('lux.motoboy.conta_cartao'));
            return $contas->first(fn ($c) => $nome !== '' && str_contains(mb_strtolower($c->nome), $nome))
                ?? $contas->first(fn ($c) => $c->tipo === 'cartao');
        }
        $nome = mb_strtolower((string) config('lux.motoboy.conta_pix'));
        return $contas->first(fn ($c) => $nome !== '' && str_contains(mb_strtolower($c->nome), $nome));
    }

    private function statusVenda(RotaEntregaItem $item, string $status, string $descricao): void
    {
        $venda = Venda::find($item->venda_id);
        if (!$venda) {
            return;
        }
        if (!in_array($venda->status_pedido, ['entregue', 'cancelada'], true)) {
            $venda->status_pedido = $status;
            $venda->save();
            try {
                \App\Helpers\EcommerceSync::syncFromVenda($venda, false);
            } catch (\Throwable $e) {
                // o site atualiza na próxima sincronização
            }
        }
        VendaAuditoria::create([
            'empresa_id' => $venda->empresa_id,
            'venda_id' => $venda->id,
            'usuario_id' => null,
            'acao' => $status === 'entregue' ? 'workflow_marcar_entregue' : 'entrega_ocorrencia_motoboy',
            'descricao' => mb_substr($descricao, 0, 512),
            'meta' => ['rota_id' => $item->rota_entrega_id],
        ]);
    }

    private function verificarFim(RotaEntrega $rota): void
    {
        $pendentes = RotaEntregaItem::where('rota_entrega_id', $rota->id)
            ->where(fn ($q) => $q->whereNull('status_entrega')->orWhere('status_entrega', 'pendente'))->count();
        if ($pendentes === 0 && $rota->status === 'em_rota') {
            RotaEntrega::whereKey($rota->id)->update(['status' => 'finalizada']);
            try {
                \App\Services\AcertoMotoboy::sincronizarRota($rota->fresh());
            } catch (\Throwable $e) {
                \Log::warning('Acerto do motoboy (rota #' . $rota->id . '): ' . $e->getMessage());
            }
        }
    }
}
