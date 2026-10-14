<?php

namespace App\Http\Controllers;

use App\Models\Produto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Vitrine "Destaques" da home do site: escolhe quais produtos aparecem e em que ordem.
 * (A vitrine "Promoções" é automática: mostra o que estiver no módulo de Promoções.)
 */
class VitrineSiteController extends Controller
{
    public function index()
    {
        $destaques = Produto::with('estoque')
            ->where('empresa_id', request()->empresa_id)
            ->where('destaque_site', 1)
            ->orderBy('destaque_ordem')
            ->orderBy('nome')
            ->get();

        $promocoes = collect();
        try {
            $promocoes = DB::table('promocoes')
                ->join('produtos', 'produtos.id', '=', 'promocoes.produto_id')
                ->where('produtos.empresa_id', request()->empresa_id)
                ->where('promocoes.ativo', 1)
                ->whereDate('promocoes.data_inicio', '<=', now())
                ->whereDate('promocoes.data_fim', '>=', now())
                ->select('promocoes.*', 'produtos.nome')
                ->orderBy('promocoes.data_fim')
                ->get();
        } catch (\Throwable $e) {
            // módulo de promoções não instalado
        }

        // clientes esperando produto esgotado ("Avise-me quando chegar")
        $aguardando = collect();
        try {
            $aguardando = DB::table('ecommerce_avisos_reposicao as a')
                ->join('produtos as p', 'p.id', '=', 'a.produto_id')
                ->leftJoin('estoques as e', 'e.produto_id', '=', 'p.id')
                ->where('p.empresa_id', request()->empresa_id)
                ->whereNull('a.avisado_em')
                ->groupBy('p.id', 'p.nome')
                ->selectRaw('p.id, p.nome, COUNT(DISTINCT a.id) as clientes, MAX(a.created_at) as ultimo, COALESCE(MAX(e.quantidade), 0) as estoque')
                ->orderByDesc('clientes')
                ->limit(100)
                ->get();
        } catch (\Throwable $e) {
            // tabela ainda não criada (SQL 23)
        }

        return view('vitrine_site.index', compact('destaques', 'promocoes', 'aguardando'));
    }

    public function adicionar(Request $request)
    {
        $termo = trim((string) $request->produto);
        $produto = null;
        if ($termo !== '') {
            $q = Produto::where('empresa_id', request()->empresa_id);
            $produto = ctype_digit($termo)
                ? (clone $q)->where('id', (int) $termo)->first()
                : (clone $q)->where('nome', 'LIKE', '%' . $termo . '%')->orderBy('nome')->first();
        }
        if (!$produto) {
            session()->flash('flash_erro', 'Produto não encontrado. Use o código ou parte do nome.');
            return redirect()->route('vitrine-site.index');
        }

        $produto->destaque_site = 1;
        $produto->destaque_ordem = (int) Produto::where('empresa_id', request()->empresa_id)->where('destaque_site', 1)->max('destaque_ordem') + 1;
        $produto->destaque_ate = $request->destaque_ate ?: null;
        $produto->save();

        session()->flash('flash_sucesso', 'Produto "' . $produto->nome . '" adicionado aos destaques.');
        return redirect()->route('vitrine-site.index');
    }

    public function atualizar(Request $request)
    {
        foreach ((array) $request->input('itens', []) as $id => $dados) {
            $produto = Produto::where('empresa_id', request()->empresa_id)->find((int) $id);
            if (!$produto) {
                continue;
            }
            $produto->destaque_ordem = max(0, (int) ($dados['ordem'] ?? 0));
            $produto->destaque_ate = !empty($dados['ate']) ? $dados['ate'] : null;
            $produto->save();
        }
        session()->flash('flash_sucesso', 'Vitrine atualizada.');
        return redirect()->route('vitrine-site.index');
    }

    public function remover($id)
    {
        $produto = Produto::where('empresa_id', request()->empresa_id)->findOrFail((int) $id);
        $produto->destaque_site = 0;
        $produto->save();
        session()->flash('flash_sucesso', 'Produto retirado dos destaques.');
        return redirect()->route('vitrine-site.index');
    }
}
