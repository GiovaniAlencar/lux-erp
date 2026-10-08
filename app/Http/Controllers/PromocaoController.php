<?php

namespace App\Http\Controllers;

use App\Models\Produto;
use App\Models\Promocao;
use Illuminate\Http\Request;

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
            ->orderByDesc('id')
            ->paginate(env('PAGINACAO', 30));

        return view('promocoes.index', compact('data'));
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
