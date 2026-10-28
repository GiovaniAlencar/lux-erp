<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Controllers\VendaComprovanteController;
use App\Models\Cliente;
use App\Models\Venda;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Dados do cliente para o painel da central de atendimento (WhatsApp).
 * Só a central chama, com o cabeçalho X-Central-Token = CENTRAL_ATENDIMENTO_TOKEN do .env.
 * Sem token configurado, as rotas ficam desligadas (404).
 *
 *   GET /api/central/cliente?telefone=5583999999999   → acha pelo celular/telefone
 *   GET /api/central/cliente?id=123                    → cliente vinculado à mão
 *   GET /api/central/clientes?q=nome|cpf|id            → busca para vincular
 */
class CentralAtendimentoController extends Controller
{
    private const STATUS_PEDIDO = [
        'aguardando_confirmacao' => 'Aguardando confirmação',
        'em_elaboracao' => 'Aguardando confirmação',
        'confirmado' => 'Confirmado',
        'em_separacao' => 'Em separação',
        'separado' => 'Separado',
        'alteracao_pendente' => 'Alteração pendente',
        'em_rota_entrega' => 'Em rota de entrega',
        'ocorrencia_entrega' => 'Ocorrência na entrega',
        'entregue' => 'Entregue',
        'cancelada' => 'Cancelada',
    ];

    private const STATUS_PAGAMENTO = [
        'pendente' => 'Não pago',
        'pago' => 'Pago',
        'parcial' => 'Pago em parte',
        'estornado' => 'Estornado',
    ];

    private function autorizado(Request $request): bool
    {
        $token = (string) config('lux.central_atendimento.token');
        $enviado = (string) $request->header('X-Central-Token', '');
        return strlen($token) >= 24 && $enviado !== '' && hash_equals($token, $enviado);
    }

    private function empresaId(): int
    {
        return (int) config('lux.central_atendimento.empresa_id', 1);
    }

    public function cliente(Request $request)
    {
        if (!$this->autorizado($request)) {
            abort(404);
        }

        if ($request->filled('id')) {
            $c = Cliente::where('empresa_id', $this->empresaId())->find((int) $request->id);
            return response()->json(['clientes' => $c ? [$this->dados($c, true)] : []]);
        }

        $tel = preg_replace('/\D+/', '', (string) $request->telefone);
        if (strlen($tel) < 10) {
            return response()->json(['clientes' => []]);
        }
        $achados = $this->porTelefone($tel);
        // um só: vem completo (pedidos, endereço). Mais de um: a central mostra para escolher.
        return response()->json([
            'clientes' => $achados->map(fn ($c) => $this->dados($c, $achados->count() === 1))->values(),
        ]);
    }

    public function buscar(Request $request)
    {
        if (!$this->autorizado($request)) {
            abort(404);
        }
        $q = trim((string) $request->q);
        if (mb_strlen($q) < 2) {
            return response()->json(['clientes' => []]);
        }
        $digitos = preg_replace('/\D+/', '', $q);
        $lista = Cliente::where('empresa_id', $this->empresaId())
            ->where(function ($w) use ($q, $digitos) {
                $w->where('razao_social', 'like', "%$q%")->orWhere('nome_fantasia', 'like', "%$q%");
                if ($digitos !== '' && ctype_digit(ltrim($q, '#'))) {
                    $w->orWhere('id', (int) $digitos);
                }
                if (strlen($digitos) >= 5) {
                    $w->orWhereRaw("REPLACE(REPLACE(REPLACE(cpf_cnpj, '.', ''), '-', ''), '/', '') LIKE ?", ["%$digitos%"]);
                }
            })
            ->orderBy('razao_social')->limit(15)->get();
        return response()->json(['clientes' => $lista->map(fn ($c) => $this->dados($c, false))->values()]);
    }

    /**
     * Celular/telefone do cadastro que bate com o número do WhatsApp.
     * Compara DDD + últimos 8 dígitos (o WhatsApp às vezes vem sem o 9 da frente).
     */
    private function porTelefone(string $tel)
    {
        if (str_starts_with($tel, '55') && strlen($tel) >= 12) {
            $tel = substr($tel, 2);
        }
        $ddd = substr($tel, 0, 2);
        $fim = substr($tel, -8);

        $candidatos = Cliente::where('empresa_id', $this->empresaId())
            ->where(function ($w) use ($fim) {
                $w->where('celular', 'like', '%' . substr($fim, 0, 4) . '%' . substr($fim, 4) . '%')
                    ->orWhere('telefone', 'like', '%' . substr($fim, 0, 4) . '%' . substr($fim, 4) . '%');
            })
            ->limit(50)->get();

        return $candidatos->filter(function ($c) use ($ddd, $fim) {
            foreach ([$c->celular, $c->telefone] as $t) {
                $d = preg_replace('/\D+/', '', (string) $t);
                if (str_starts_with($d, '55') && strlen($d) >= 12) {
                    $d = substr($d, 2);
                }
                $d = ltrim($d, '0');
                if (strlen($d) >= 8 && substr($d, -8) === $fim && (strlen($d) < 10 || substr($d, 0, 2) === $ddd)) {
                    return true;
                }
            }
            return false;
        })->values()->take(5);
    }

    private function dados(Cliente $c, bool $completo): array
    {
        $base = rtrim((string) config('lux.central_atendimento.erp_url') ?: (string) config('app.url'), '/');
        $cidade = $c->cidade_id ? optional($c->cidade) : null;
        $r = [
            'id' => $c->id,
            'nome' => $c->nome_exibicao,
            'razao_social' => $c->razao_secundaria,
            'cpf_cnpj' => $c->cpf_cnpj ?: null,
            'celular' => $c->celular ?: ($c->telefone ?: null),
            'cidade' => $cidade && $cidade->nome ? $cidade->nome . '/' . $cidade->uf : null,
            'url' => $base . '/clientes/' . $c->id . '/edit',
        ];
        if (!$completo) {
            return $r;
        }

        $r['email'] = $c->email ?: null;
        $r['observacao'] = $c->observacao ?: null;
        $r['enderecos'] = array_values(array_filter([
            $this->endereco('Entrega', $c->rua, $c->numero, $c->complemento, $c->bairro, $cidade && $cidade->nome ? $cidade->nome . '/' . $cidade->uf : null, $c->cep),
            $this->endereco('Cobrança', $c->rua_cobranca, $c->numero_cobranca, null, $c->bairro_cobranca, null, $c->cep_cobranca),
        ]));
        // não repete o de cobrança quando é igual ao de entrega
        if (count($r['enderecos']) === 2 && $r['enderecos'][0]['linha'] === $r['enderecos'][1]['linha']) {
            array_pop($r['enderecos']);
        }

        $vendas = Venda::where('empresa_id', $c->empresa_id)->where('cliente_id', $c->id)
            ->orderByDesc('id')->limit(6)->get();
        $temComp = Schema::hasTable('venda_comprovantes');
        $r['total_pedidos'] = Venda::where('empresa_id', $c->empresa_id)->where('cliente_id', $c->id)->count();
        $r['pedidos'] = $vendas->map(function (Venda $v) use ($base, $temComp) {
            $total = VendaComprovanteController::totalVenda($v);
            $pago = $temComp ? (float) \App\Models\VendaComprovante::where('venda_id', $v->id)->sum('valor') : 0;
            $st = $v->status_pagamento ?: 'pendente';
            return [
                'id' => $v->id,
                'data' => optional($v->created_at)->format('d/m/Y'),
                'total' => $total,
                'falta' => $st === 'pago' ? 0 : round(max(0, $total - $pago), 2),
                'status' => self::STATUS_PEDIDO[$v->status_pedido ?? ''] ?? ($v->status_pedido ?: '—'),
                'cancelado' => ($v->status_pedido ?? '') === 'cancelada' || ($v->estado_emissao ?? '') === 'cancelado',
                'pagamento' => $st,
                'pagamento_rotulo' => self::STATUS_PAGAMENTO[$st] ?? $st,
                'url' => $base . '/vendas/' . $v->id,
            ];
        })->values();
        return $r;
    }

    private function endereco(string $tipo, $rua, $numero, $compl, $bairro, $cidade, $cep): ?array
    {
        $rua = trim((string) $rua);
        if ($rua === '') {
            return null;
        }
        $linha = $rua . ($numero ? ', ' . trim((string) $numero) : '')
            . ($compl ? ' - ' . trim((string) $compl) : '')
            . ($bairro ? ' · ' . trim((string) $bairro) : '')
            . ($cidade ? ' · ' . $cidade : '')
            . ($cep ? ' · CEP ' . trim((string) $cep) : '');
        return ['tipo' => $tipo, 'linha' => $linha];
    }
}
