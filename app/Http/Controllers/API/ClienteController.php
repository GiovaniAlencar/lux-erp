<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\EcommerceShippingNeighborhood;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    public function pesquisa(Request $request){
        $data = Cliente::
        orderBy('razao_social', 'desc')
        ->where('empresa_id', $request->empresa_id)
        ->where('razao_social', 'like', "%$request->pesquisa%")
        ->get();
        return response()->json($data, 200);

    }

    public function store(Request $request){
        try{
            $request->merge([
                'ie_rg' => $request->ie_rg ?? '',
                'limite_venda' => $request->limite_venda ? __convert_value_bd($request->limite_venda) : 0,
                'grupo_id' => $request->grupo_id ?? 0,
                'acessor_id' => $request->acessor_id ?? 0,
                'telefone' => $request->telefone ?? '',
                'celular' => $request->celular ?? '',
                'email' => $request->email ?? '',
                'observacao' => $request->observacao ?? '',
                'nome_fantasia' => $request->nome_fantasia ?? ''
            ]);
            $item = Cliente::create($request->all());
            return response()->json($item, 200);

        }catch(\Exception $e){
            return response()->json($e->getMessage(), 400);
        }
    }

    public function find($id){
        $item = Cliente::with('cidade')->findOrFail($id);
        return response()->json($item, 200);

    }

    public function updateEndereco(Request $request, $id){
        try{
            $cliente = Cliente::findOrFail($id);
            $cliente->rua = $request->rua ?? $cliente->rua;
            $cliente->numero = $request->numero ?? $cliente->numero;
            $cliente->bairro = $request->bairro ?? $cliente->bairro;
            $cliente->complemento = $request->complemento ?? $cliente->complemento;
            $cliente->cep = $request->cep ?? $cliente->cep;
            if($request->cidade_id){
                $cliente->cidade_id = $request->cidade_id;
            }
            $cliente->save();
            return response()->json($cliente, 200);
        }catch(\Exception $e){
            return response()->json($e->getMessage(), 400);
        }
    }

    public function updatePerfil(Request $request, $id){
        try{
            $cliente = Cliente::findOrFail($id);
            $cliente->razao_social = $request->razao_social ?? $cliente->razao_social;
            $cliente->celular = $request->celular ?? $cliente->celular;
            $cliente->email = $request->email ?? $cliente->email;
            $doc = trim((string) $request->input('cpf_cnpj', ''));
            if ($doc !== '') {
                if (!\App\Helpers\Documento::valido($doc)) {
                    return response()->json('CPF/CNPJ inválido.', 422);
                }
                $cliente->cpf_cnpj = \App\Helpers\Documento::formatar($doc);
            }
            $cliente->save();
            return response()->json($cliente, 200);
        }catch(\Exception $e){
            return response()->json($e->getMessage(), 400);
        }
    }

    /**
     * Consulta o valor de frete cadastrado para um bairro (mesma tabela usada pelo site).
     * Usado para sugerir automaticamente o frete ao criar uma venda pelo ERP.
     */
    public function freteBairro(Request $request){
        $bairro = trim((string) $request->query('bairro', ''));

        if ($bairro === '') {
            return response()->json(['encontrado' => false], 200);
        }

        $item = EcommerceShippingNeighborhood::where('active', true)
            ->whereRaw('LOWER(neighborhood) = ?', [mb_strtolower($bairro)])
            ->first();

        if (!$item) {
            $item = EcommerceShippingNeighborhood::where('active', true)
                ->where('neighborhood', 'LIKE', "%{$bairro}%")
                ->first();
        }

        if (!$item) {
            return response()->json(['encontrado' => false], 200);
        }

        return response()->json([
            'encontrado' => true,
            'bairro' => $item->neighborhood,
            'valor' => $item->shipping_price,
        ], 200);
    }
}


