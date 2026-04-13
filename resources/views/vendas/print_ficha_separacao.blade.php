{{--
    Ficha de separação: itens agrupados por categoria do produto (cadastro).
    Checkboxes são quadrados para marcação manual na impressão (quantidade conferida / item OK).
--}}
@php
    $qtdDec = 2;
    $itens = $item->itens;
    $porCategoria = $itens->groupBy(function ($linha) {
        $p = $linha->produto;
        if (!$p) {
            return 'Sem categoria';
        }

        return optional($p->categoria)->nome ?: 'Sem categoria';
    });
    $categoriasOrdenadas = $porCategoria->keys()->sort(function ($a, $b) {
        if ($a === 'Sem categoria') {
            return 1;
        }
        if ($b === 'Sem categoria') {
            return -1;
        }

        return strcasecmp($a, $b);
    })->values();
    $totalPecas = $itens->sum(function ($linha) {
        $q = (float) $linha->quantidade;
        $d = (float) ($linha->quantidade_dimensao ?? 1);

        return $q * $d;
    });
@endphp

<div class="ficha-separacao">
    <h1 class="ficha-titulo">Ficha de separação</h1>
    <p class="ficha-subtitulo">Pedido #{{ $item->id }} — {{ optional($item->cliente)->razao_social ?? 'Cliente' }}</p>

    <table class="ficha-meta">
        <tr>
            <td><strong>Impresso em:</strong> {{ now()->format('d/m/Y H:i') }}</td>
        </tr>
    </table>

    <p class="ficha-instrucao">
        <strong>Como usar:</strong> percorra por <em>categoria</em> (mesma prateleira/linha de produção).
        Marque <strong>Qtd</strong> quando a quantidade estiver conferida e <strong>OK</strong> quando o item estiver completo (sem falta / sem divergência).
    </p>

    @if($itens->isEmpty())
        <p class="ficha-instrucao">Nenhum item neste pedido.</p>
    @endif

    @foreach($categoriasOrdenadas as $nomeCategoria)
        @php $grupo = $porCategoria[$nomeCategoria]; @endphp
        <h2 class="ficha-categoria">{{ $nomeCategoria }}</h2>
        <table class="ficha-tabela">
            <thead>
                <tr>
                    <th class="col-chk" title="Quantidade conferida">Qtd</th>
                    <th class="col-chk" title="Item completo / tudo certo">OK</th>
                    <th class="col-qtd">Quant.</th>
                    <th class="col-prod">Produto</th>
                    <th class="col-cod">Cód. / Ref.</th>
                </tr>
            </thead>
            <tbody>
                @foreach($grupo as $linha)
                    @php
                        $p = $linha->produto;
                        $ref = $p && $p->referencia ? $p->referencia : '—';
                        $cod = $p ? $p->id : '—';
                        $nomeProd = $p ? $p->nome : '(produto removido)';
                        if ($p && $p->grade && $p->str_grade) {
                            $nomeProd .= ' (' . $p->str_grade . ')';
                        }
                        $qtdTxt = number_format((float) $linha->quantidade, $qtdDec, ',', '.');
                        $dim = (float) ($linha->quantidade_dimensao ?? 1);
                        if ($dim != 1.0) {
                            $qtdTxt .= ' × ' . number_format($dim, $qtdDec, ',', '.');
                        }
                    @endphp
                    <tr>
                        <td class="td-chk"><span class="chk-box" aria-hidden="true"></span></td>
                        <td class="td-chk"><span class="chk-box" aria-hidden="true"></span></td>
                        <td class="td-num">{{ $qtdTxt }}</td>
                        <td>{{ $nomeProd }}</td>
                        <td>{{ $cod }}@if($ref !== '—')<br><span class="muted">{{ $ref }}</span>@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    <table class="ficha-rodape">
        <tr>
            <td colspan="2"><strong>Total quantidade:</strong> {{ number_format($totalPecas, $qtdDec, ',', '.') }}</td>
        </tr>
        <tr>
            <td colspan="2" class="ficha-assina">
                <span class="assina-linha">Conferido por: ________________________________</span>
                <span class="assina-linha">Data: ____/____/________</span>
            </td>
        </tr>
    </table>
</div>
