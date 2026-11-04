<?php

namespace App\Http\Controllers;

use App\Models\FinConta;
use App\Models\Entregador;
use App\Models\EntregadorAcerto;
use App\Models\RotaEntrega;
use App\Models\RotaEntregaItem;
use App\Services\AcertoMotoboy;
use App\Services\Financeiro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Vendas › Motoboys: cadastro e acertos do frete (repasse semanal ou no dia + extras). SQL 39. */
class MotoboyCadastroController extends Controller
{
    private function exigir(): void
    {
        if (!AcertoMotoboy::ativo()) {
            abort(redirect('/')->with('flash_erro', 'Rode o SQL 39 (motoboys e acertos) para usar esta tela.'));
        }
    }

    private function exigeAdm(): void
    {
        $u = session('user_logged');
        if (!($u['adm'] ?? false) && empty($u['super'])) {
            abort(403, 'Somente administradores pagam o acerto do motoboy.');
        }
    }

    public function index(Request $request)
    {
        $this->exigir();
        $emp = (int) $request->empresa_id;
        $motoboys = Entregador::where('empresa_id', $emp)->orderByDesc('ativo')->orderBy('nome')->get();
        // pagos direto em Contas a pagar também fecham o acerto
        EntregadorAcerto::where('empresa_id', $emp)->where('status', 'aberto')->get()->each(fn ($a) => AcertoMotoboy::verificarPago($a));
        $abertos = EntregadorAcerto::with('motoboy')->where('empresa_id', $emp)->where('status', 'aberto')->orderBy('vencimento')->get();
        $semAcerto = RotaEntrega::where('empresa_id', $emp)->where('status', 'finalizada')->whereNotNull('entregador_id')
            ->whereNull('acerto_id')->where(fn ($q) => $q->whereNull('motoboy_pago')->orWhere('motoboy_pago', 0))->count();
        $pagos = EntregadorAcerto::with('motoboy')->where('empresa_id', $emp)->where('status', 'pago')->orderByDesc('pago_em')->limit(30)->get();
        return view('entregadores.index', compact('motoboys', 'abertos', 'pagos', 'semAcerto'));
    }

    public function salvar(Request $request)
    {
        $this->exigir();
        $request->validate([
            'id' => 'nullable|integer',
            'nome' => 'required|string|max:120',
            'telefone' => 'nullable|string|max:30',
            'acerto' => 'required|in:semanal,diario',
        ]);
        $m = $request->id ? Entregador::where('empresa_id', $request->empresa_id)->findOrFail((int) $request->id) : new Entregador(['empresa_id' => $request->empresa_id]);
        $m->fill(['nome' => trim($request->nome), 'telefone' => $request->telefone, 'acerto' => $request->acerto, 'ativo' => $request->has('ativo') ? $request->boolean('ativo') : true]);
        $m->save();
        return back()->with('flash_sucesso', 'Motoboy salvo.');
    }

    /** Coloca no acerto as rotas finalizadas antes desta tela existir (ou que ficaram de fora). */
    public function gerarPendentes(Request $request)
    {
        $this->exigir();
        $rotas = RotaEntrega::where('empresa_id', $request->empresa_id)->where('status', 'finalizada')->whereNotNull('entregador_id')
            ->whereNull('acerto_id')->where(fn ($q) => $q->whereNull('motoboy_pago')->orWhere('motoboy_pago', 0))->get();
        foreach ($rotas as $r) {
            AcertoMotoboy::sincronizarRota($r);
        }
        return back()->with('flash_sucesso', $rotas->count() . ' rota(s) colocadas no acerto.');
    }

    public function acerto(Request $request, $id)
    {
        $this->exigir();
        $a = EntregadorAcerto::with(['motoboy', 'rotas.itens'])->where('empresa_id', $request->empresa_id)->findOrFail((int) $id);
        if ($a->status === 'aberto') {
            $a = AcertoMotoboy::recalcular($a);
            if (!$a->exists) {
                return redirect()->route('entregadores.index')->with('flash_erro', 'Este acerto ficou vazio e foi removido.');
            }
            $a->load(['motoboy', 'rotas.itens']);
        }
        $contas = FinConta::daEmpresa($request->empresa_id);
        $titulos = \App\Models\ContaPagar::whereIn('id', array_filter([$a->conta_fretes_id, $a->conta_extras_id]))->get()->keyBy('id');
        return view('entregadores.acerto', compact('a', 'contas', 'titulos'));
    }

    /** Extras do motoboy por entrega (2ª caixa, entrega que deu errado…). */
    public function extras(Request $request, $id)
    {
        $this->exigir();
        $a = EntregadorAcerto::where('empresa_id', $request->empresa_id)->findOrFail((int) $id);
        if ($a->status !== 'aberto') {
            return back()->with('flash_erro', 'Acerto já pago: não dá para mudar os extras.');
        }
        $rotaIds = RotaEntrega::where('acerto_id', $a->id)->pluck('id');
        DB::transaction(function () use ($request, $rotaIds) {
            foreach ((array) $request->input('extra', []) as $itemId => $e) {
                $item = RotaEntregaItem::whereIn('rota_entrega_id', $rotaIds)->find((int) $itemId);
                if (!$item) {
                    continue;
                }
                $v = Financeiro::valorBr($e['valor'] ?? '');
                $item->motoboy_extra = $v > 0 ? round($v, 2) : null;
                $item->motoboy_extra_motivo = $v > 0 ? (mb_substr(trim((string) ($e['motivo'] ?? '')), 0, 120) ?: null) : null;
                $item->save();
            }
        });
        AcertoMotoboy::recalcular($a);
        return back()->with('flash_sucesso', 'Extras salvos. As contas a pagar foram atualizadas.');
    }

    public function pagar(Request $request, $id)
    {
        $this->exigir();
        $this->exigeAdm();
        $request->validate(['fin_conta_id' => 'required|integer', 'data' => 'required|date', 'forma' => 'nullable|string|max:30']);
        $a = EntregadorAcerto::where('empresa_id', $request->empresa_id)->findOrFail((int) $id);
        $conta = FinConta::where('empresa_id', $request->empresa_id)->findOrFail((int) $request->fin_conta_id);
        try {
            $a = AcertoMotoboy::pagar($a, $conta, $request->data, $request->forma ?: null);
        } catch (\Throwable $e) {
            return back()->with('flash_erro', 'Não foi possível pagar: ' . $e->getMessage());
        }
        return back()->with('flash_sucesso', $a->status === 'pago'
            ? 'Acerto pago: R$ ' . __moeda($a->total()) . ' saiu de ' . $conta->nome . '. As rotas ficaram marcadas como pagas ao motoboy.'
            : 'Pagamento lançado.');
    }
}
