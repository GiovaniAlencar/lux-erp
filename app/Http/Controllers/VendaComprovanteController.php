<?php

namespace App\Http\Controllers;

use App\Models\FinConta;
use App\Models\FinMovimento;
use App\Models\Venda;
use App\Models\VendaAuditoria;
use App\Models\VendaComprovante;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Comprovantes de pagamento da venda.
 * - Qualquer usuário da empresa vê e anexa; excluir: administrador, ou quem anexou no mesmo dia
 *   (e só enquanto a venda não foi travada no caixa).
 * - Com valor + conta do Financeiro, o comprovante vira uma ENTRADA no extrato dessa conta (SQL 28),
 *   e o status de pagamento da venda acompanha: parcial / pago.
 * - Os arquivos ficam no disco "local" (storage/app), fora da pasta pública.
 */
class VendaComprovanteController extends Controller
{
    private const EXTENSOES = 'jpg,jpeg,png,webp,pdf';
    private const MAX_KB = 8192;

    private function venda(Request $request, $id): Venda
    {
        return Venda::with('cliente:id,razao_social')->where('empresa_id', $request->empresa_id)->findOrFail((int) $id);
    }

    private function usuarioId(): int
    {
        return (int) (session('user_logged')['id'] ?? 0);
    }

    private function ehAdm(): bool
    {
        $u = session('user_logged');
        return (bool) ($u['adm'] ?? false) || !empty($u['super']);
    }

    /** O SQL 28 (ligação com o Financeiro) já foi aplicado? */
    private function integraFinanceiro(): bool
    {
        static $ok = null;
        return $ok ??= Schema::hasColumn('venda_comprovantes', 'fin_conta_id') && Schema::hasTable('fin_contas');
    }

    private function podeExcluir(VendaComprovante $c, Venda $v): bool
    {
        if ($this->ehAdm()) {
            return true;
        }
        return !$v->fechada_caixa
            && (int) $c->usuario_id === $this->usuarioId()
            && $c->created_at && $c->created_at->isToday();
    }

    public static function totalVenda(Venda $v): float
    {
        return round((float) $v->valor_total - (float) $v->desconto + (float) $v->acrescimo + (float) ($v->getAttributes()['frete'] ?? 0), 2);
    }

    private function contasFinanceiro(int $empresaId)
    {
        return $this->integraFinanceiro() ? FinConta::daEmpresa($empresaId) : collect();
    }

    private function payload(Venda $v): array
    {
        $lista = VendaComprovante::with('usuario:id,nome')
            ->when($this->integraFinanceiro(), fn ($q) => $q->with('finConta:id,nome'))
            ->where('empresa_id', $v->empresa_id)->where('venda_id', $v->id)
            ->orderBy('id')->get();
        $total = self::totalVenda($v);
        $comprovado = round((float) $lista->sum('valor'), 2);

        return [
            'venda' => $v->id,
            'total' => $total,
            'comprovado' => $comprovado,
            'falta' => round(max(0, $total - $comprovado), 2),
            'status_pagamento' => $v->status_pagamento ?? 'pendente',
            'contas' => $this->contasFinanceiro((int) $v->empresa_id)->map(fn ($c) => ['id' => $c->id, 'nome' => $c->nome, 'padrao' => (bool) $c->padrao])->values(),
            'itens' => $lista->map(fn (VendaComprovante $c) => [
                'id' => $c->id,
                'url' => route('vendas-comprovantes.abrir', $c->id),
                'nome' => $c->nome_original ?: ('comprovante-' . $c->id),
                'pdf' => str_ends_with(strtolower($c->arquivo), '.pdf'),
                'conta' => ($this->integraFinanceiro() && $c->finConta) ? $c->finConta->nome : VendaComprovante::nomeConta($c->conta),
                'no_financeiro' => $this->integraFinanceiro() && !empty($c->fin_movimento_id),
                'valor' => $c->valor,
                'data' => $this->integraFinanceiro() && $c->data_pagamento ? $c->data_pagamento->format('d/m/Y') : null,
                'observacao' => $c->observacao,
                'por' => optional($c->usuario)->nome,
                'em' => optional($c->created_at)->format('d/m/Y H:i'),
                'pode_excluir' => $this->podeExcluir($c, $v),
            ])->values(),
        ];
    }

    /** pago quando os comprovantes cobrem o total; parcial quando cobrem parte. */
    private function recalcularStatus(Venda $v): void
    {
        if (($v->status_pagamento ?? '') === 'estornado') {
            return;
        }
        $comprovado = (float) VendaComprovante::where('empresa_id', $v->empresa_id)->where('venda_id', $v->id)->sum('valor');
        $total = self::totalVenda($v);
        $novo = $comprovado > 0 && $comprovado + 0.009 >= $total ? 'pago' : ($comprovado > 0 ? 'parcial' : 'pendente');
        if ($novo !== ($v->status_pagamento ?? 'pendente')) {
            Venda::whereKey($v->id)->update(['status_pagamento' => $novo]);
            $v->status_pagamento = $novo;
        }
    }

    private function auditar(Venda $v, string $acao, string $texto): void
    {
        try {
            VendaAuditoria::create([
                'empresa_id' => $v->empresa_id,
                'venda_id' => $v->id,
                'usuario_id' => $this->usuarioId() ?: null,
                'acao' => $acao,
                'descricao' => mb_substr($texto, 0, 512),
            ]);
        } catch (\Throwable $e) {
            // auditoria nunca impede o anexo
        }
    }

    public function listar(Request $request, $id)
    {
        return response()->json($this->payload($this->venda($request, $id)));
    }

    public function salvar(Request $request, $id)
    {
        $v = $this->venda($request, $id);
        $request->validate([
            'arquivo' => 'required|file|mimes:' . self::EXTENSOES . '|max:' . self::MAX_KB,
            'conta' => 'nullable|string|max:20',
            'valor' => 'nullable|string|max:20',
            'data_pagamento' => 'nullable|date|before_or_equal:today',
            'observacao' => 'nullable|string|max:255',
        ], [
            'arquivo.required' => 'Escolha a foto ou o PDF do comprovante.',
            'arquivo.mimes' => 'Envie uma imagem (JPG, PNG, WEBP) ou PDF.',
            'arquivo.max' => 'O arquivo pode ter no máximo 8 MB.',
            'data_pagamento.before_or_equal' => 'A data do pagamento não pode ser no futuro.',
        ]);

        // conta: id de uma conta do Financeiro, ou (sem Financeiro) fiscal / nao_fiscal / outra
        $contaReq = (string) $request->input('conta', '');
        $finConta = null;
        $contaTexto = null;
        if (ctype_digit($contaReq) && $this->integraFinanceiro()) {
            $finConta = FinConta::where('empresa_id', $v->empresa_id)->where('ativo', 1)->find((int) $contaReq);
            if (!$finConta) {
                return response()->json(['message' => 'Conta inválida.'], 422);
            }
        } elseif (in_array($contaReq, VendaComprovante::CONTAS, true)) {
            $contaTexto = $contaReq;
        }

        $valor = trim((string) $request->input('valor', ''));
        $valor = $valor === '' ? null : \App\Services\Financeiro::valorBr($valor);
        if ($valor !== null && $valor <= 0) {
            $valor = null;
        }
        $total = self::totalVenda($v);
        $jaComprovado = (float) VendaComprovante::where('empresa_id', $v->empresa_id)->where('venda_id', $v->id)->sum('valor');
        if ($valor !== null && $jaComprovado + $valor > $total + 0.009) {
            return response()->json(['message' => 'O valor passa do total da venda: já comprovado R$ ' . __moeda($jaComprovado)
                . ' de R$ ' . __moeda($total) . ' (falta R$ ' . __moeda(max(0, $total - $jaComprovado)) . ').'], 422);
        }
        $data = $request->input('data_pagamento') ?: date('Y-m-d');

        $arq = $request->file('arquivo');
        $ext = strtolower($arq->getClientOriginalExtension() ?: $arq->extension());
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        if (!in_array($ext, explode(',', self::EXTENSOES), true)) {
            return response()->json(['message' => 'Envie uma imagem (JPG, PNG, WEBP) ou PDF.'], 422);
        }
        $caminho = $arq->storeAs(
            'comprovantes-vendas/' . (int) $v->empresa_id . '/' . date('Y-m'),
            Str::uuid() . '.' . $ext,
            'local'
        );

        try {
            DB::transaction(function () use ($v, $request, $arq, $caminho, $finConta, $contaTexto, $valor, $data) {
                $dados = [
                    'empresa_id' => $v->empresa_id,
                    'venda_id' => $v->id,
                    'usuario_id' => $this->usuarioId() ?: null,
                    'arquivo' => $caminho,
                    'nome_original' => mb_substr(preg_replace('/[^\pL\pN ._()-]+/u', '', $arq->getClientOriginalName()) ?: '', 0, 150) ?: null,
                    'conta' => $finConta ? null : $contaTexto,
                    'valor' => $valor,
                    'observacao' => $request->input('observacao') ? mb_substr(strip_tags($request->input('observacao')), 0, 255) : null,
                ];
                if ($this->integraFinanceiro()) {
                    $dados['fin_conta_id'] = $finConta?->id;
                    $dados['data_pagamento'] = $data;
                }
                $c = VendaComprovante::create($dados);

                // valor + conta do Financeiro → entrada no extrato
                if ($finConta && $valor) {
                    $cliente = optional($v->cliente)->razao_social;
                    $mov = FinMovimento::create([
                        'empresa_id' => $v->empresa_id,
                        'conta_id' => $finConta->id,
                        'tipo' => 'entrada',
                        'valor' => $valor,
                        'data' => $data,
                        'descricao' => mb_substr('Recebimento venda #' . $v->id . ($cliente ? ' · ' . $cliente : ''), 0, 255),
                        'origem' => 'recebimento',
                        'forma_pagamento' => $v->tipo_pagamento ? mb_substr((string) Venda::getTipo($v->tipo_pagamento), 0, 30) : null,
                        'venda_id' => $v->id,
                        'comprovante' => $caminho,
                        'usuario_id' => $this->usuarioId() ?: null,
                    ]);
                    $c->fin_movimento_id = $mov->id;
                    $c->save();
                }

                $this->recalcularStatus($v);
                $this->auditar($v, 'comprovante_anexado', 'Comprovante anexado'
                    . ($valor ? ': R$ ' . __moeda($valor) : '')
                    . ($finConta ? ' em ' . $finConta->nome . ' (lançado no financeiro)' : ($contaTexto ? ' · ' . VendaComprovante::nomeConta($contaTexto) : ''))
                    . '.');
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($caminho);
            throw $e;
        }

        return response()->json($this->payload($v->fresh(['cliente:id,razao_social'])));
    }

    /** Abre o arquivo (privado, sem cache, só para usuários da mesma empresa). */
    public function abrir(Request $request, $cid)
    {
        $c = VendaComprovante::where('empresa_id', $request->empresa_id)->findOrFail((int) $cid);
        if (!Storage::disk('local')->exists($c->arquivo)) {
            abort(404);
        }
        $ext = pathinfo($c->arquivo, PATHINFO_EXTENSION);
        return Storage::disk('local')->response($c->arquivo, 'comprovante-venda-' . $c->venda_id . '-' . $c->id . '.' . $ext, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; object-src 'self'",
        ]);
    }

    public function excluir(Request $request, $cid)
    {
        $c = VendaComprovante::where('empresa_id', $request->empresa_id)->findOrFail((int) $cid);
        $v = $this->venda($request, $c->venda_id);
        if (!$this->podeExcluir($c, $v)) {
            return response()->json(['message' => 'Só o administrador (ou quem anexou, no mesmo dia) pode excluir este comprovante.'], 403);
        }
        $arquivo = $c->arquivo;
        DB::transaction(function () use ($c, $v) {
            // tira também a entrada do extrato que esse comprovante gerou
            if ($this->integraFinanceiro() && $c->fin_movimento_id) {
                FinMovimento::where('empresa_id', $c->empresa_id)->whereKey($c->fin_movimento_id)->delete();
            }
            $valor = $c->valor;
            $c->delete();
            $this->recalcularStatus($v);
            $this->auditar($v, 'comprovante_excluido', 'Comprovante excluído' . ($valor ? ' (R$ ' . __moeda($valor) . ')' : '') . '.');
        });
        Storage::disk('local')->delete($arquivo);

        return response()->json($this->payload($v));
    }
}
