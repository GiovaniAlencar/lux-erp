<?php

namespace App\Http\Controllers;

use App\Models\CategoriaConta;
use App\Models\FinConta;
use App\Models\FinMovimento;
use App\Services\Financeiro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Contas e saldos, extrato, lançamentos avulsos e transferências.
 * Saldos e extrato: só administrador. Comprovante: qualquer usuário da empresa.
 */
class FinLuxController extends Controller
{
    private function exigeAdm(): void
    {
        $u = session('user_logged');
        if (!($u['adm'] ?? false) && empty($u['super'])) {
            abort(403, 'Somente administradores veem saldos e extratos.');
        }
    }

    private function conta(Request $request, $id): FinConta
    {
        return FinConta::where('empresa_id', $request->empresa_id)->findOrFail((int) $id);
    }

    public function contas(Request $request)
    {
        $this->exigeAdm();
        $contas = FinConta::daEmpresa($request->empresa_id, false);
        $saldos = $contas->mapWithKeys(fn ($c) => [$c->id => $c->saldo()]);
        $total = $contas->where('ativo', true)->sum(fn ($c) => $saldos[$c->id]);

        // a pagar: vencidos, hoje e próximos 7 dias (o que falta pagar)
        $restante = 'GREATEST(valor_integral - valor_pago, 0)';
        $base = \App\Models\ContaPagar::where('empresa_id', $request->empresa_id)->where('status', 0);
        $aPagar = [
            'vencido' => (float) (clone $base)->where('data_vencimento', '<', today())->sum(\DB::raw($restante)),
            'hoje' => (float) (clone $base)->whereDate('data_vencimento', today())->sum(\DB::raw($restante)),
            'semana' => (float) (clone $base)->whereBetween('data_vencimento', [today()->addDay(), today()->addDays(7)])->sum(\DB::raw($restante)),
        ];
        $mes = FinMovimento::where('empresa_id', $request->empresa_id)
            ->whereBetween('data', [today()->startOfMonth(), today()->endOfMonth()])
            ->where('origem', '!=', 'transferencia')
            ->selectRaw("COALESCE(SUM(CASE WHEN tipo='entrada' THEN valor END),0) entradas, COALESCE(SUM(CASE WHEN tipo='saida' THEN valor END),0) saidas")
            ->first();
        $categorias = CategoriaConta::where('empresa_id', $request->empresa_id)->orderBy('nome')->get();

        $pendencias = self::pendencias($request->empresa_id);
        return view('financeiro.contas', compact('pendencias', 'contas', 'saldos', 'total', 'aPagar', 'mes', 'categorias'));
    }

    public function salvarConta(Request $request)
    {
        $this->exigeAdm();
        $dados = $request->validate([
            'id' => 'nullable|integer',
            'nome' => 'required|string|max:80',
            'tipo' => 'required|in:' . implode(',', array_keys(FinConta::TIPOS)),
            'saldo_inicial' => 'nullable|string|max:20',
            'data_saldo_inicial' => 'nullable|date',
            'padrao' => 'nullable',
            'ativo' => 'nullable',
        ]);
        $c = !empty($dados['id']) ? $this->conta($request, $dados['id']) : new FinConta(['empresa_id' => $request->empresa_id]);
        $c->fill([
            'nome' => $dados['nome'],
            'tipo' => $dados['tipo'],
            'saldo_inicial' => Financeiro::valorBr($dados['saldo_inicial'] ?? '0'),
            'data_saldo_inicial' => $dados['data_saldo_inicial'] ?? today()->format('Y-m-d'),
            'padrao' => $request->boolean('padrao'),
            'ativo' => !$c->exists || $request->boolean('ativo'),
        ]);
        $c->empresa_id = $request->empresa_id;
        $c->save();
        if ($c->padrao) {
            FinConta::where('empresa_id', $request->empresa_id)->where('id', '!=', $c->id)->update(['padrao' => 0]);
        }
        return back()->with('flash_sucesso', 'Conta "' . $c->nome . '" salva.');
    }

    public function extrato(Request $request)
    {
        $this->exigeAdm();
        $contas = FinConta::daEmpresa($request->empresa_id, false);
        $contaId = (int) $request->get('conta_id', optional($contas->first())->id);
        $conta = $contas->firstWhere('id', $contaId);
        $ini = $request->get('inicio') ?: today()->startOfMonth()->format('Y-m-d');
        $fim = $request->get('fim') ?: today()->format('Y-m-d');

        $movs = collect();
        $saldoAnterior = $saldoFinal = 0;
        if ($conta) {
            $saldoAnterior = $conta->saldo(date('Y-m-d', strtotime($ini . ' -1 day')));
            $movs = FinMovimento::with(['categoria', 'usuario', 'contaPagar'])
                ->where('conta_id', $conta->id)
                ->whereBetween('data', [$ini, $fim])
                ->orderBy('data')->orderBy('id')->get();
            $saldo = $saldoAnterior;
            foreach ($movs as $m) {
                $saldo += $m->tipo === 'entrada' ? $m->valor : -$m->valor;
                $m->saldo_apos = round($saldo, 2);
            }
            $saldoFinal = round($saldo, 2);
        }
        $categorias = CategoriaConta::where('empresa_id', $request->empresa_id)->orderBy('nome')->get();
        return view('financeiro.extrato', compact('contas', 'conta', 'movs', 'ini', 'fim', 'saldoAnterior', 'saldoFinal', 'categorias'));
    }

    /** Entrada ou saída avulsa (retirada, despesa rápida, aporte, ajuste…). */
    public function lancar(Request $request)
    {
        $this->exigeAdm();
        $request->validate([
            'conta_id' => 'required|integer',
            'tipo' => 'required|in:entrada,saida',
            'valor' => 'required|string|max:20',
            'data' => 'required|date',
            'descricao' => 'required|string|max:200',
            'categoria_id' => 'nullable|integer',
            'comprovante' => 'nullable|file|mimes:' . Financeiro::COMPROVANTE_MIMES . '|max:' . Financeiro::COMPROVANTE_MAX_KB,
        ]);
        $conta = $this->conta($request, $request->conta_id);
        $valor = Financeiro::valorBr($request->valor);
        if ($valor <= 0) {
            return back()->with('flash_erro', 'Informe um valor maior que zero.');
        }
        $cat = $request->categoria_id
            ? CategoriaConta::where('empresa_id', $request->empresa_id)->find((int) $request->categoria_id)
            : null;
        FinMovimento::create([
            'empresa_id' => $request->empresa_id,
            'conta_id' => $conta->id,
            'tipo' => $request->tipo,
            'valor' => $valor,
            'data' => $request->data,
            'descricao' => $request->descricao,
            'origem' => $request->boolean('ajuste') ? 'ajuste' : 'avulso',
            'categoria_id' => optional($cat)->id,
            'comprovante' => Financeiro::salvarComprovante($request->file('comprovante'), $request->empresa_id),
            'usuario_id' => get_id_user(),
        ]);
        return back()->with('flash_sucesso', ($request->tipo === 'entrada' ? 'Entrada' : 'Saída') . ' de R$ ' . __moeda($valor) . ' lançada em ' . $conta->nome . '.');
    }

    public function transferir(Request $request)
    {
        $this->exigeAdm();
        $request->validate([
            'de' => 'required|integer', 'para' => 'required|integer|different:de',
            'valor' => 'required|string|max:20', 'data' => 'required|date', 'obs' => 'nullable|string|max:120',
        ], ['para.different' => 'Escolha contas diferentes.']);
        $valor = Financeiro::valorBr($request->valor);
        if ($valor <= 0) {
            return back()->with('flash_erro', 'Informe um valor maior que zero.');
        }
        Financeiro::transferir($this->conta($request, $request->de), $this->conta($request, $request->para), $valor, $request->data, (string) $request->obs);
        return back()->with('flash_sucesso', 'Transferência de R$ ' . __moeda($valor) . ' lançada.');
    }

    public function estornar(Request $request, $id)
    {
        $this->exigeAdm();
        $m = FinMovimento::where('empresa_id', $request->empresa_id)->findOrFail((int) $id);
        Financeiro::estornar($m);
        return back()->with('flash_sucesso', 'Lançamento desfeito.' . ($m->conta_pagar_id ? ' O valor voltou a ficar em aberto na conta a pagar.' : ''));
    }

    /** Lançamentos com aviso (ex.: comprovante excluído da venda) — mostrados em Contas e saldos. */
    public static function pendencias($empresaId)
    {
        if (!\App\Http\Controllers\VendaComprovanteController::temPendencia()) {
            return collect();
        }
        return FinMovimento::with('conta:id,nome')->where('empresa_id', $empresaId)->whereNotNull('pendencia_em')
            ->orderBy('pendencia_em')->limit(50)->get();
    }

    /**
     * Entrada que perdeu o comprovante (excluído da venda): liga a outra venda.
     * Cria o comprovante na nova venda com o mesmo arquivo, dá baixa lá e tira o aviso.
     */
    public function vincularVenda(Request $request, $id)
    {
        $this->exigeAdm();
        $request->validate(['venda_id' => 'required|integer|min:1'], ['venda_id.required' => 'Informe o número do pedido.']);
        $m = FinMovimento::where('empresa_id', $request->empresa_id)->findOrFail((int) $id);
        if ($m->tipo !== 'entrada') {
            return back()->with('flash_erro', 'Só entradas podem ser ligadas a uma venda.');
        }
        $v = \App\Models\Venda::with('cliente:id,razao_social')->where('empresa_id', $request->empresa_id)->find((int) $request->venda_id);
        if (!$v) {
            return back()->with('flash_erro', 'Pedido #' . (int) $request->venda_id . ' não encontrado.');
        }
        $total = \App\Http\Controllers\VendaComprovanteController::totalVenda($v);
        $ja = (float) \App\Models\VendaComprovante::where('empresa_id', $v->empresa_id)->where('venda_id', $v->id)->sum('valor');
        if ($ja + (float) $m->valor > $total + 0.009) {
            return back()->with('flash_erro', 'O pedido #' . $v->id . ' já tem R$ ' . __moeda($ja) . ' comprovado de R$ ' . __moeda($total)
                . ': os R$ ' . __moeda($m->valor) . ' passariam do total.');
        }
        $antes = $m->venda_id;
        \Illuminate\Support\Facades\DB::transaction(function () use ($m, $v, $antes) {
            \App\Models\VendaComprovante::create([
                'empresa_id' => $v->empresa_id,
                'venda_id' => $v->id,
                'usuario_id' => (int) (session('user_logged')['id'] ?? 0) ?: null,
                'arquivo' => $m->comprovante ?: '',
                'nome_original' => 'comprovante-extrato-' . $m->id,
                'fin_conta_id' => $m->conta_id,
                'fin_movimento_id' => $m->id,
                'valor' => $m->valor,
                'data_pagamento' => $m->data,
                'observacao' => 'Vinculado pelo extrato' . ($antes ? ' (era do pedido #' . $antes . ')' : ''),
            ]);
            $cliente = optional($v->cliente)->razao_social;
            $m->venda_id = $v->id;
            $m->descricao = mb_substr('Recebimento venda #' . $v->id . ($cliente ? ' · ' . $cliente : ''), 0, 255);
            $m->pendencia = null;
            $m->pendencia_em = null;
            $m->save();
            \App\Http\Controllers\VendaComprovanteController::recalcularStatusVenda($v);
            \App\Services\RecebimentoVenda::sincronizar($v);
        });
        return back()->with('flash_sucesso', 'Entrada de R$ ' . __moeda($m->valor) . ' ligada ao pedido #' . $v->id . '.');
    }

    /** Abre o comprovante (arquivo privado, só para usuários da mesma empresa). */
    public function comprovante(Request $request, $id)
    {
        $m = FinMovimento::where('empresa_id', $request->empresa_id)->findOrFail((int) $id);
        if (!$m->comprovante || !Storage::disk('local')->exists($m->comprovante)) {
            abort(404);
        }
        return Storage::disk('local')->response($m->comprovante, 'comprovante-' . $m->id . '.' . pathinfo($m->comprovante, PATHINFO_EXTENSION), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
