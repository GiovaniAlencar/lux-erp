<?php

namespace App\Services;

use App\Console\Commands\EnviarWhatsappComprovantes;
use App\Http\Controllers\VendaComprovanteController;
use App\Models\FinConta;
use App\Models\FinMovimento;
use App\Models\Venda;
use App\Models\VendaAuditoria;
use App\Models\VendaComprovante;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registra um recebimento de venda (usado pelo app do motoboy e pelo "usar saldo do cliente"):
 *
 *   - comprovante na venda com o valor que ABATE do pedido (nunca mais que o que falta);
 *   - entrada no extrato da conta escolhida com o dinheiro que entra de verdade
 *     (no cartão é o LÍQUIDO, já sem a taxa da maquineta);
 *   - o que o cliente pagou a mais vira SALDO do cliente;
 *   - recalcula o status de pagamento da venda e as contas a receber;
 *   - comprovante da conta configurada vai para o grupo do WhatsApp.
 */
class RegistroRecebimento
{
    /**
     * @param float  $valorPago    o que o cliente pagou (no cartão, o bruto passado na maquineta)
     * @param float  $valorConta   o que entra na conta (no cartão, o líquido); null = igual ao pago
     * @param string $arquivo      caminho do comprovante no disco local ('' = sem arquivo, ex.: dinheiro)
     * @param bool   $sobraSaldo   o que passar do que falta vira saldo do cliente
     * @return array{comprovante: VendaComprovante, abatido: float, saldo: float, falta: float}
     */
    public static function registrar(
        Venda $v,
        ?FinConta $conta,
        float $valorPago,
        ?float $valorConta,
        string $data,
        string $arquivo,
        ?string $nomeOriginal,
        ?string $observacao,
        ?int $usuarioId,
        string $formaTexto,
        bool $sobraSaldo = true,
        ?string $contaTexto = null,
        bool $movimentarConta = true
    ): array {
        $valorPago = round($valorPago, 2);
        $valorConta = round($valorConta ?? $valorPago, 2);
        if ($valorPago <= 0) {
            throw new \InvalidArgumentException('Informe o valor recebido.');
        }

        return DB::transaction(function () use ($v, $conta, $valorPago, $valorConta, $data, $arquivo, $nomeOriginal, $observacao, $usuarioId, $formaTexto, $sobraSaldo, $contaTexto, $movimentarConta) {
            Venda::whereKey($v->id)->lockForUpdate()->first();
            $total = VendaComprovanteController::totalVenda($v);
            $ja = (float) VendaComprovante::where('empresa_id', $v->empresa_id)->where('venda_id', $v->id)->sum('valor');
            $falta = round(max(0, $total - $ja), 2);

            // o que abate do pedido: nunca mais que o que falta
            $abatido = round(min($valorPago, $falta), 2);
            // sobra em dinheiro de verdade: no cartão compara o líquido (o acréscimo da taxa não é crédito)
            $sobra = round($valorConta - $abatido, 2);
            $saldo = ($sobraSaldo && $sobra >= 1.0 && $v->cliente_id) ? $sobra : 0.0;

            $temFin = Schema::hasColumn('venda_comprovantes', 'fin_conta_id');
            $dados = [
                'empresa_id' => $v->empresa_id,
                'venda_id' => $v->id,
                'usuario_id' => $usuarioId ?: null,
                'arquivo' => $arquivo,
                'nome_original' => $nomeOriginal ? mb_substr($nomeOriginal, 0, 150) : null,
                'conta' => $conta ? null : $contaTexto,
                'valor' => $abatido > 0 ? $abatido : null,
                'observacao' => $observacao ? mb_substr(strip_tags($observacao), 0, 255) : null,
            ];
            if ($temFin) {
                $dados['fin_conta_id'] = $conta?->id;
                $dados['data_pagamento'] = $data;
            }
            $c = VendaComprovante::create($dados);

            if ($conta && $movimentarConta && $valorConta > 0) {
                $cliente = optional($v->cliente)->nome_exibicao;
                $mov = FinMovimento::create([
                    'empresa_id' => $v->empresa_id,
                    'conta_id' => $conta->id,
                    'tipo' => 'entrada',
                    'valor' => $valorConta,
                    'data' => $data,
                    'descricao' => mb_substr('Recebimento venda #' . $v->id . ($cliente ? ' · ' . $cliente : '') . ' · ' . $formaTexto, 0, 255),
                    'origem' => 'recebimento',
                    'forma_pagamento' => mb_substr($formaTexto, 0, 30),
                    'venda_id' => $v->id,
                    'comprovante' => $arquivo !== '' ? $arquivo : null,
                    'usuario_id' => $usuarioId ?: null,
                ]);
                $c->fin_movimento_id = $mov->id;
                $c->save();
            }

            if ($saldo > 0) {
                SaldoCliente::creditar((int) $v->empresa_id, (int) $v->cliente_id, $saldo, $v->id,
                    'Pagou a mais no pedido #' . $v->id . ' (' . $formaTexto . ')', $usuarioId, $c->id);
            }

            VendaComprovanteController::recalcularStatusVenda($v);
            RecebimentoVenda::sincronizar($v);

            try {
                VendaAuditoria::create([
                    'empresa_id' => $v->empresa_id,
                    'venda_id' => $v->id,
                    'usuario_id' => $usuarioId ?: null,
                    'acao' => 'comprovante_anexado',
                    'descricao' => mb_substr('Recebido R$ ' . __moeda($valorPago) . ' (' . $formaTexto . ')'
                        . ($conta ? ' em ' . $conta->nome : '')
                        . ($valorConta !== $valorPago ? ' · líquido R$ ' . __moeda($valorConta) : '')
                        . ($saldo > 0 ? ' · R$ ' . __moeda($saldo) . ' para o saldo do cliente' : '') . '.', 0, 512),
                ]);
            } catch (\Throwable $e) {
                // auditoria nunca impede o recebimento
            }

            return ['comprovante' => $c, 'abatido' => $abatido, 'saldo' => $saldo, 'falta' => round(max(0, $falta - $abatido), 2)];
        });
    }

    /** Manda o comprovante para o grupo do WhatsApp quando é da conta configurada (fora da transação). */
    public static function enviarGrupo(VendaComprovante $c, ?FinConta $conta): void
    {
        try {
            if ($c->arquivo !== '' && EnviarWhatsappComprovantes::contaVaiProGrupo($conta, $c->conta)) {
                EnviarWhatsappComprovantes::enfileirar($c, 'Pedido #' . $c->venda_id);
            }
        } catch (\Throwable $e) {
            \Log::warning('Comprovante para o WhatsApp não entrou na fila: ' . $e->getMessage());
        }
    }
}
