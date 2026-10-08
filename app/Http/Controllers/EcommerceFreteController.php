<?php

namespace App\Http\Controllers;

use App\Models\EcommerceShippingNeighborhood;
use Illuminate\Http\Request;

class EcommerceFreteController extends Controller
{
    public function index(Request $request)
    {
        $data = EcommerceShippingNeighborhood::query()
            ->when($request->q, function ($q) use ($request) {
                $term = $request->q;
                return $q->where(function ($w) use ($term) {
                    $w->where('neighborhood', 'LIKE', "%{$term}%")
                        ->orWhere('city', 'LIKE', "%{$term}%")
                        ->orWhere('notes', 'LIKE', "%{$term}%");
                });
            })
            ->when($request->filled('active'), function ($q) use ($request) {
                return $q->where('active', (int) $request->active);
            })
            ->orderBy('city')
            ->orderBy('neighborhood')
            ->paginate(env('PAGINACAO', 40));

        return view('ecommerce_frete.index', compact('data'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        try {
            EcommerceShippingNeighborhood::create($data);
            session()->flash('flash_sucesso', 'Frete do bairro cadastrado.');
        } catch (\Exception $e) {
            session()->flash('flash_erro', 'Erro ao salvar: ' . $e->getMessage());
        }

        return redirect()->route('ecommerce-frete.index');
    }

    public function update(Request $request, $id)
    {
        $item = EcommerceShippingNeighborhood::findOrFail($id);
        $data = $this->validated($request);

        try {
            $item->fill($data)->save();
            session()->flash('flash_sucesso', 'Frete atualizado.');
        } catch (\Exception $e) {
            session()->flash('flash_erro', 'Erro ao atualizar: ' . $e->getMessage());
        }

        return redirect()->route('ecommerce-frete.index');
    }

    public function destroy($id)
    {
        $item = EcommerceShippingNeighborhood::findOrFail($id);
        try {
            $item->delete();
            session()->flash('flash_sucesso', 'Registro removido.');
        } catch (\Exception $e) {
            session()->flash('flash_erro', 'Erro ao remover: ' . $e->getMessage());
        }

        return redirect()->route('ecommerce-frete.index');
    }

    public function toggle($id)
    {
        $item = EcommerceShippingNeighborhood::findOrFail($id);
        $item->active = !$item->active;
        $item->save();

        session()->flash('flash_sucesso', $item->active ? 'Bairro ativado.' : 'Bairro desativado.');

        return redirect()->route('ecommerce-frete.index');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'neighborhood' => 'required|string|max:120',
            'city' => 'nullable|string|max:120',
            'state' => 'nullable|string|max:2',
            'shipping_price' => 'required',
            'notes' => 'nullable|string|max:255',
            'active' => 'sometimes|boolean',
        ], [
            'neighborhood.required' => 'Informe o bairro.',
            'shipping_price.required' => 'Informe o valor do frete.',
        ]);

        return [
            'neighborhood' => trim($validated['neighborhood']),
            'city' => trim($validated['city'] ?? 'João Pessoa') ?: 'João Pessoa',
            'state' => strtoupper(trim($validated['state'] ?? 'PB') ?: 'PB'),
            'shipping_price' => (float) __convert_value_bd((string) $validated['shipping_price']),
            'notes' => trim($validated['notes'] ?? '') ?: null,
            'active' => $request->boolean('active', true),
        ];
    }
}
