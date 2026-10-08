<?php

namespace App\Helpers;

use App\Models\RotaEntrega;
use App\Models\RotaEntregaItem;
use App\Models\Venda;
use Illuminate\Support\Collection;

class RotaEntregaHelper
{
    public static function ordenarVendas(Collection $vendas): Collection
    {
        return $vendas->sort(function ($a, $b) {
            $bairroA = self::normalizeRouteText(optional($a->cliente)->bairro);
            $bairroB = self::normalizeRouteText(optional($b->cliente)->bairro);

            $prioridadeA = self::bairroRoutePriority($bairroA);
            $prioridadeB = self::bairroRoutePriority($bairroB);

            if ($prioridadeA !== $prioridadeB) {
                return $prioridadeA <=> $prioridadeB;
            }

            if ($bairroA !== $bairroB) {
                return $bairroA <=> $bairroB;
            }

            $ruaA = self::normalizeRouteText(optional($a->cliente)->rua);
            $ruaB = self::normalizeRouteText(optional($b->cliente)->rua);

            if ($ruaA !== $ruaB) {
                return $ruaA <=> $ruaB;
            }

            $nomeA = self::normalizeRouteText(optional($a->cliente)->razao_social);
            $nomeB = self::normalizeRouteText(optional($b->cliente)->razao_social);

            return $nomeA <=> $nomeB;
        })->values();
    }

    public static function dadosItemFromVenda(Venda $v): array
    {
        $c = $v->cliente;
        $valorTotal = floatval($v->valor_total) - floatval($v->desconto) + floatval($v->acrescimo) + floatval($v->frete);

        $tipoPagamento = $v->tipo_pagamento;
        $tipoPagamentoNome = Venda::getTipo($tipoPagamento);

        $qtdParcelas = $v->duplicatas ? $v->duplicatas->count() : 0;
        $mostrarParcelas = false;
        $valorParcela = null;

        if ($v->duplicatas && $v->duplicatas->count() > 0) {
            $qtdParcelas = $v->duplicatas->count();
            $primeiraDup = $v->duplicatas->first();
            $valorParcela = (float) $primeiraDup->valor_integral;
            foreach ($v->duplicatas as $dup) {
                if ($dup->tipo_pagamento === '03') {
                    $mostrarParcelas = true;
                    $tipoPagamento = '03';
                    $tipoPagamentoNome = Venda::getTipo('03');
                    break;
                }
            }
            if ($tipoPagamento === '03') {
                $mostrarParcelas = true;
            }
        } else {
            $qtdParcelas = 1;
            if ($tipoPagamento === '03') {
                $mostrarParcelas = true;
                $valorParcela = $valorTotal;
            }
        }

        $situacaoPagamento = 'pagamento_entrega';
        $valorRestante = null;

        if ($v->status_pagamento === 'pago') {
            $situacaoPagamento = 'pago';
        } elseif ($v->status_pagamento === 'parcial') {
            $situacaoPagamento = 'pago_parcial';
            $valorRestante = 0.0;
            if ($v->duplicatas) {
                foreach ($v->duplicatas as $dup) {
                    $valorRestante += max(0, (float) $dup->valor_integral - (float) $dup->valor_recebido);
                }
            }
            if ($valorRestante <= 0) {
                $valorRestante = $valorTotal;
            }
        }

        $telefone = trim($c->celular ?? ($c->telefone ?? ''));
        if ($telefone === '') {
            $telefone = self::telefoneDoSite($v);
        }

        return [
            'venda_id' => $v->id,
            'cliente_nome' => trim($c->razao_social ?? ''),
            'telefone' => $telefone,
            'rua' => trim($c->rua ?? ''),
            'numero' => trim($c->numero ?? ''),
            'bairro' => trim($c->bairro ?? ''),
            'complemento' => trim($c->complemento ?? ''),
            'valor_total' => $valorTotal,
            'frete' => (float) $v->frete,
            'qtd_parcelas' => max(1, $qtdParcelas),
            'mostrar_parcelas' => $mostrarParcelas,
            'mostrar_valor_pedido' => false,
            'situacao_pagamento' => $situacaoPagamento,
            'valor_restante' => $valorRestante,
            'valor_parcela' => $valorParcela,
            'tipo_pagamento' => $tipoPagamento,
            'tipo_pagamento_nome' => $tipoPagamentoNome,
            'aviso_entrega' => trim($v->aviso_entrega ?? '') ?: null,
            'confirmado_saida' => false,
        ];
    }

    public static function telefoneDoSite(Venda $v): string
    {
        try {
            if (!empty($v->id)) {
                $order = \App\Models\EcommerceOrder::where('venda_id', $v->id)->first();
                if ($order && trim($order->customer_phone ?? '') !== '') {
                    return trim($order->customer_phone);
                }
            }
            if (!empty($v->pedido_ecommerce_id)) {
                $order = \App\Models\EcommerceOrder::find($v->pedido_ecommerce_id);
                if ($order && trim($order->customer_phone ?? '') !== '') {
                    return trim($order->customer_phone);
                }
            }
        } catch (\Throwable $e) {
            // tabela pode não existir em ambientes antigos
        }

        return '';
    }

    public static function telefoneCliente(Venda $v): string
    {
        $c = $v->cliente;
        $tel = trim($c->celular ?? ($c->telefone ?? ''));

        return $tel !== '' ? $tel : self::telefoneDoSite($v);
    }

    public static function gerarTxtRota($rota): string
    {
        $lines = [];
        $itens = $rota->itens;
        $bairroAtual = null;

        $lines[] = 'ROTA #' . $rota->id . ' — ' . now()->format('d/m/Y H:i');
        if (!empty($rota->motoboy_nome)) {
            $lines[] = 'Motoboy: ' . $rota->motoboy_nome;
        }
        $lines[] = '';

        foreach ($itens as $item) {
            $bairro = trim($item->bairro ?? '');
            $bairroFormatado = strlen($bairro) ? mb_strtoupper($bairro, 'UTF-8') : 'SEM BAIRRO';

            if ($bairroAtual !== $bairroFormatado) {
                if (count($lines) > 0) {
                    $lines[] = '';
                }
                $lines[] = '=== ' . $bairroFormatado . ' ===';
                $lines[] = '';
                $bairroAtual = $bairroFormatado;
            }

            if ($item->prioridade) {
                $lines[] = '*** PRIORIDADE ***';
                if (!empty($item->observacao_prioridade)) {
                    $lines[] = $item->observacao_prioridade;
                }
            }

            $lines[] = $item->cliente_nome . ' (' . $item->venda_id . ')';
            $lines[] = $item->telefone;
            $lines[] = $item->enderecoFormatado();
            if (strlen(trim($item->complemento ?? '')) > 0) {
                $lines[] = $item->complemento;
            }
            $lines[] = 'Pagamento: ' . $item->textoPagamentoMotoboy();
            if ($item->mostrar_valor_pedido && $item->situacao_pagamento !== 'pago') {
                $valorFmt = number_format((float) $item->valor_total, 2, ',', '.');
                $lines[] = '*R$ ' . $valorFmt . '*';
            }
            $lines[] = 'Confirmar: ' . $item->urlConfirmacao();
            $lines[] = '-----------------------';
            $lines[] = '';
        }

        return implode("\r\n", $lines);
    }

    public static function bairroRoutePriority($bairro): int
    {
        $ordem = self::bairroRouteOrder();

        return $ordem[$bairro] ?? 999;
    }

    public static function bairroRouteOrder(): array
    {
        $bairros = [
            'geisel',
            'ernesto geisel',
            'grotao',
            'joao paulo ii',
            'cuia',
            'jose americo',
            'funcionarios',
            'cristo redentor',
            'jaguaribe',
            'bancarios',
            'agua fria',
            'mangabeira',
            'valentina',
            'gramame',
            'paratibe',
            'colinas do sul',
            'planalto boa esperanca',
            'mucumagro',
        ];

        $ordem = [];
        foreach ($bairros as $index => $bairro) {
            $ordem[self::normalizeRouteText($bairro)] = $index;
        }

        return $ordem;
    }

    public static function normalizeRouteText($value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($ascii !== false) {
            $value = $ascii;
        }

        $value = mb_strtolower($value, 'UTF-8');
        $value = preg_replace('/[^a-z0-9]+/i', ' ', $value);

        return trim($value);
    }

    public static function reordenarItensRota(RotaEntrega $rota): void
    {
        $itens = $rota->itens()->with('venda.cliente')->get();
        if ($itens->isEmpty()) {
            return;
        }

        $vendas = $itens->map(fn ($item) => $item->venda)->filter();
        $ordenadas = self::ordenarVendas($vendas);

        $ordem = 0;
        foreach ($ordenadas as $venda) {
            RotaEntregaItem::where('rota_entrega_id', $rota->id)
                ->where('venda_id', $venda->id)
                ->update(['ordem' => $ordem++]);
        }
    }
}
