<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\EcommerceUser;
use App\Models\Cliente;
use App\Models\Cidade;

class EcommerceUsuarioController extends Controller
{
    public function index(Request $request)
    {
        $data = EcommerceUser::with('cliente')
            ->when($request->status, function ($q) use ($request) {
                return $q->where('status', $request->status);
            })
            ->when($request->q, function ($q) use ($request) {
                $term = $request->q;
                return $q->where(function ($w) use ($term) {
                    $w->where('name', 'LIKE', "%{$term}%")
                        ->orWhere('email', 'LIKE', "%{$term}%")
                        ->orWhere('phone', 'LIKE', "%{$term}%");
                });
            })
            ->orderByRaw("FIELD(status, 'pending', 'active', 'rejected', 'blocked', 'inactive')")
            ->orderByDesc('created_at')
            ->paginate(env('PAGINACAO', 30));

        $statusLabels = EcommerceUser::statusLabels();
        return view('ecommerce_usuarios.index', compact('data', 'statusLabels'));
    }

    public function show($id)
    {
        $item = EcommerceUser::with('cliente')->findOrFail($id);
        $sugestoes = $this->buscarClientesSugestao($item);
        $statusLabels = EcommerceUser::statusLabels();
        return view('ecommerce_usuarios.show', compact('item', 'sugestoes', 'statusLabels'));
    }

    public function aprovar(Request $request, $id)
    {
        $item = EcommerceUser::findOrFail($id);
        $request->validate([
            'erp_cliente_id' => 'required|integer|exists:clientes,id',
        ], [
            'erp_cliente_id.required' => 'Selecione ou crie um cliente ERP para vincular.',
        ]);

        $cliente = Cliente::findOrFail($request->erp_cliente_id);
        if (!__valida_objeto($cliente)) {
            abort(403);
        }

        try {
            $item->erp_cliente_id = $cliente->id;
            $item->status = 'active';
            $item->approved_at = now();
            $item->approved_by = get_id_user();
            $item->rejected_at = null;
            $item->rejection_reason = null;
            $item->admin_notes = $request->admin_notes ?: $item->admin_notes;
            $item->save();
            session()->flash('flash_sucesso', 'Usuário do site aprovado e vinculado ao cliente #' . $cliente->id);
        } catch (\Exception $e) {
            session()->flash('flash_erro', 'Erro ao aprovar: ' . $e->getMessage());
            __saveLogError($e, request()->empresa_id);
        }

        return redirect()->route('ecommerce-usuarios.show', $item->id);
    }

    public function reprovar(Request $request, $id)
    {
        $item = EcommerceUser::findOrFail($id);
        $request->validate([
            'rejection_reason' => 'nullable|max:255',
        ]);

        try {
            $item->status = 'rejected';
            $item->rejected_at = now();
            $item->rejection_reason = $request->rejection_reason;
            $item->approved_at = null;
            $item->save();
            session()->flash('flash_sucesso', 'Usuário reprovado.');
        } catch (\Exception $e) {
            session()->flash('flash_erro', 'Erro ao reprovar: ' . $e->getMessage());
        }

        return redirect()->route('ecommerce-usuarios.show', $item->id);
    }

    public function bloquear($id)
    {
        $item = EcommerceUser::findOrFail($id);
        $item->status = 'blocked';
        $item->save();
        session()->flash('flash_sucesso', 'Usuário bloqueado.');
        return redirect()->route('ecommerce-usuarios.show', $item->id);
    }

    public function reativar($id)
    {
        $item = EcommerceUser::findOrFail($id);
        if (!$item->erp_cliente_id) {
            session()->flash('flash_erro', 'Vincule um cliente ERP antes de reativar.');
            return redirect()->route('ecommerce-usuarios.show', $item->id);
        }
        $item->status = 'active';
        $item->save();
        session()->flash('flash_sucesso', 'Usuário reativado.');
        return redirect()->route('ecommerce-usuarios.show', $item->id);
    }

    public function alterarSenha(Request $request, $id)
    {
        $item = EcommerceUser::findOrFail($id);
        $request->validate([
            'password' => 'required|min:6|confirmed',
        ], [
            'password.required' => 'Informe a nova senha.',
            'password.min' => 'Mínimo de 6 caracteres.',
            'password.confirmed' => 'A confirmação da senha não confere.',
        ]);

        $item->password = password_hash($request->password, PASSWORD_DEFAULT);
        $item->save();
        session()->flash('flash_sucesso', 'Senha do cliente do site alterada.');
        return redirect()->route('ecommerce-usuarios.show', $item->id);
    }

    public function criarCliente(Request $request, $id)
    {
        $item = EcommerceUser::findOrFail($id);
        $empresaId = request()->empresa_id;

        try {
            $cliente = DB::transaction(function () use ($item, $empresaId, $request) {
                $cidadeId = (int)($request->cidade_id ?: 1338); // João Pessoa padrão
                if (!Cidade::find($cidadeId)) {
                    $cidadeId = Cidade::query()->value('id') ?: 1;
                }

                $phone = preg_replace('/\D/', '', $item->phone);
                $cliente = Cliente::create([
                    'empresa_id' => $empresaId,
                    'razao_social' => $item->name,
                    'nome_fantasia' => $item->name,
                    'cpf_cnpj' => $request->cpf_cnpj ?: '000.000.000-00',
                    'rua' => $request->rua ?: 'A definir',
                    'numero' => $request->numero ?: 'S/N',
                    'bairro' => $request->bairro ?: 'A definir',
                    'telefone' => $phone,
                    'celular' => $phone,
                    'email' => $item->email,
                    'cep' => $request->cep ?: '58000000',
                    'ie_rg' => 'ISENTO',
                    'consumidor_final' => 1,
                    'contribuinte' => 0,
                    'cidade_id' => $cidadeId,
                    'limite_venda' => 0,
                    'cod_pais' => 1058,
                    'grupo_id' => 0,
                    'acessor_id' => 0,
                    'funcionario_id' => 0,
                    'inativo' => 0,
                    'observacao' => 'Criado via Usuários Site #' . $item->id,
                ]);

                $item->erp_cliente_id = $cliente->id;
                $item->status = 'active';
                $item->approved_at = now();
                $item->approved_by = get_id_user();
                $item->save();

                return $cliente;
            });

            session()->flash('flash_sucesso', 'Cliente #' . $cliente->id . ' criado e usuário aprovado.');
        } catch (\Exception $e) {
            session()->flash('flash_erro', 'Erro ao criar cliente: ' . $e->getMessage());
            __saveLogError($e, $empresaId);
        }

        return redirect()->route('ecommerce-usuarios.show', $item->id);
    }

    public function buscarClientes(Request $request)
    {
        $q = trim($request->q ?? '');
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $clientes = Cliente::where('empresa_id', request()->empresa_id)
            ->where(function ($w) use ($q) {
                $w->where('razao_social', 'LIKE', "%{$q}%")
                    ->orWhere('nome_fantasia', 'LIKE', "%{$q}%")
                    ->orWhere('email', 'LIKE', "%{$q}%")
                    ->orWhere('telefone', 'LIKE', "%{$q}%")
                    ->orWhere('celular', 'LIKE', "%{$q}%")
                    ->orWhere('cpf_cnpj', 'LIKE', "%{$q}%");
            })
            ->limit(20)
            ->get(['id', 'razao_social', 'nome_fantasia', 'telefone', 'celular', 'email']);

        return response()->json($clientes);
    }

    private function buscarClientesSugestao(EcommerceUser $user)
    {
        $phone = preg_replace('/\D/', '', $user->phone);
        $email = $user->email;

        return Cliente::where('empresa_id', request()->empresa_id)
            ->where(function ($w) use ($phone, $email, $user) {
                if ($phone) {
                    $w->orWhere('telefone', 'LIKE', "%{$phone}%")
                        ->orWhere('celular', 'LIKE', "%{$phone}%");
                }
                if ($email) {
                    $w->orWhere('email', $email);
                }
                $w->orWhere('razao_social', 'LIKE', '%' . $user->name . '%');
            })
            ->limit(10)
            ->get();
    }
}
