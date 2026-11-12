<?php

namespace App\Http\Controllers;

use App\Models\Produto;
use App\Models\Promocao;
use App\Services\PromocaoLote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PromocaoController extends Controller
{
    public function index(Request $request)
    {
        $data = Promocao::with('produto')
            ->where('empresa_id', $request->empresa_id)
            ->when($request->filled('produto'), function ($q) use ($request) {
                return $q->whereHas('produto', function ($qq) use ($request) {
                    $qq->where('nome', 'LIKE', "%{$request->produto}%");
                });
            })
            ->when($request->filled('campanha'), fn ($q) => $q->where('observacao', $request->campanha))
            ->orderByDesc('id')
            ->paginate(env('PAGINACAO', 30));

        $campanhas = PromocaoLote::campanhasAtivas((int) $request->empresa_id);
        return view('promocoes.index', compact('data', 'campanhas'));
    }

    // ---- promoções em lote (lista de IDs e preços, ou categoria com estoque) ----

    private function configLote(Request $request): array
    {
        $request->validate([
            'campanha' => 'required|string|max:120',
            'data_inicio' => 'required|date',
            'data_fim' => 'required|date|after_or_equal:data_inicio',
            'origem' => 'required|in:lista,categoria',
            'lista' => 'required_if:origem,lista|nullable|string|max:60000',
            'categoria_id' => 'required_if:origem,categoria|nullable|integer',
            'base' => 'required|in:' . implode(',', array_keys(PromocaoLote::BASES)),
            'ajuste' => 'required|in:' . implode(',', array_keys(PromocaoLote::AJUSTES)),
            'se_existir' => 'required|in:substituir,pular',
            'quantidade_limite' => 'nullable|integer|min:1',
        ], [
            'campanha.required' => 'Dê um nome para a campanha (ex.: BLACK MINI).',
            'data_fim.after_or_equal' => 'A data final não pode ser anterior à inicial.',
            'lista.required_if' => 'Cole a lista de IDs (e preços).',
            'categoria_id.required_if' => 'Escolha a categoria.',
        ]);
        $cfg = $request->only(['campanha', 'data_inicio', 'data_fim', 'origem', 'lista', 'categoria_id', 'base', 'ajuste', 'se_existir', 'quantidade_limite']);
        $cfg['campanha'] = trim($cfg['campanha']);
        $cfg['valor_ajuste'] = max(0, (float) (PromocaoLote::numero($request->input('valor_ajuste')) ?? 0));
        if ($cfg['ajuste'] === 'percentual' && $cfg['valor_ajuste'] >= 100) {
            abort(back()->withInput()->with('flash_erro', 'O desconto em % tem que ser menor que 100.'));
        }
        return $cfg;
    }

    private function categorias(Request $request)
    {
        return DB::table('categorias')->where('empresa_id', $request->empresa_id)->orderBy('nome')->pluck('nome', 'id');
    }

    public function lote(Request $request)
    {
        return view('promocoes.lote', ['categorias' => $this->categorias($request), 'previa' => null, 'cfg' => []]);
    }

    public function lotePrevia(Request $request)
    {
        $cfg = $this->configLote($request);
        $previa = PromocaoLote::previa((int) $request->empresa_id, $cfg);
        return view('promocoes.lote', ['categorias' => $this->categorias($request), 'previa' => $previa, 'cfg' => $cfg]);
    }

    public function loteSalvar(Request $request)
    {
        $cfg = $this->configLote($request);
        // recalcula tudo no servidor (não confia em nada da tela de prévia)
        $previa = PromocaoLote::previa((int) $request->empresa_id, $cfg);
        $ok = array_filter($previa['linhas'], fn ($l) => $l['ok']);
        if (!$ok) {
            return back()->withInput()->with('flash_erro', 'Nenhum produto válido para criar promoção.');
        }
        try {
            [$criadas, $substituidas] = PromocaoLote::salvar((int) $request->empresa_id, $previa['linhas'], $cfg);
        } catch (\Exception $e) {
            __saveLogError($e, $request->empresa_id);
            return back()->withInput()->with('flash_erro', 'Algo deu errado: ' . $e->getMessage());
        }
        return redirect()->route('promocoes.index', ['campanha' => $cfg['campanha']])
            ->with('flash_sucesso', "Campanha \"{$cfg['campanha']}\": {$criadas} promoção(ões) criada(s)" . ($substituidas ? ", {$substituidas} antiga(s) desativada(s)." : '.'));
    }

    /** Desativa todas as promoções ativas de uma campanha (pelo nome). */
    public function encerrarCampanha(Request $request)
    {
        $request->validate(['campanha' => 'required|string|max:255']);
        $n = Promocao::where('empresa_id', $request->empresa_id)->where('observacao', $request->campanha)
            ->where('ativo', 1)->update(['ativo' => 0]);
        return redirect()->route('promocoes.index')->with('flash_sucesso', "Campanha \"{$request->campanha}\" encerrada: {$n} promoção(ões) desativada(s).");
    }

    public function create()
    {
        return view('promocoes.create');
    }

    public function store(Request $request)
    {
        $data = $this->validado($request);

        try {
            $data['empresa_id'] = $request->empresa_id;
            $data['quantidade_utilizada'] = 0;
            Promocao::create($data);
            session()->flash('flash_sucesso', 'Promoção cadastrada com sucesso!');
        } catch (\Exception $e) {
            session()->flash('flash_erro', 'Algo deu errado: ' . $e->getMessage());
            __saveLogError($e, $request->empresa_id);
        }

        return redirect()->route('promocoes.index');
    }

    public function edit($id)
    {
        $item = Promocao::findOrFail($id);
        if (!__valida_objeto($item)) {
            abort(403);
        }

        return view('promocoes.edit', compact('item'));
    }

    public function update(Request $request, $id)
    {
        $item = Promocao::findOrFail($id);
        if (!__valida_objeto($item)) {
            abort(403);
        }

        $data = $this->validado($request);

        try {
            $item->fill($data)->save();
            session()->flash('flash_sucesso', 'Promoção atualizada com sucesso!');
        } catch (\Exception $e) {
            session()->flash('flash_erro', 'Algo deu errado: ' . $e->getMessage());
            __saveLogError($e, $request->empresa_id);
        }

        return redirect()->route('promocoes.index');
    }

    public function destroy($id)
    {
        $item = Promocao::findOrFail($id);
        if (!__valida_objeto($item)) {
            abort(403);
        }

        try {
            $item->delete();
            session()->flash('flash_sucesso', 'Promoção removida com sucesso!');
        } catch (\Exception $e) {
            session()->flash('flash_erro', 'Algo deu errado: ' . $e->getMessage());
            __saveLogError($e, request()->empresa_id);
        }

        return redirect()->route('promocoes.index');
    }

    private function validado(Request $request): array
    {
        $validated = $request->validate([
            'produto_id' => 'required|integer|exists:produtos,id',
            'tipo' => 'required|in:percentual,preco_fixo',
            'valor' => 'required',
            'data_inicio' => 'required|date',
            'data_fim' => 'required|date|after_or_equal:data_inicio',
            'quantidade_limite' => 'nullable',
            'ativo' => 'sometimes|boolean',
            'observacao' => 'nullable|string|max:255',
        ], [
            'produto_id.required' => 'Selecione o produto da promoção.',
            'data_fim.after_or_equal' => 'A data final não pode ser anterior à data inicial.',
        ]);

        $qtdLimite = trim((string) ($validated['quantidade_limite'] ?? ''));

        return [
            'produto_id' => (int) $validated['produto_id'],
            'tipo' => $validated['tipo'],
            'valor' => (float) __convert_value_bd((string) $validated['valor']),
            'data_inicio' => $validated['data_inicio'],
            'data_fim' => $validated['data_fim'],
            'quantidade_limite' => $qtdLimite !== '' ? (int) $qtdLimite : null,
            'ativo' => $request->boolean('ativo', true),
            'observacao' => trim($validated['observacao'] ?? '') ?: null,
        ];
    }
}
