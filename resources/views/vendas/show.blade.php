@extends('default.layout', ['title' => 'Vendas'])

@section('css')
<script src="https://cdn.tailwindcss.com"></script>
<script>
    tailwind.config = {
        darkMode: 'class',
        important: '#lux-venda-show',
        corePlugins: { preflight: false },
        theme: {
            extend: {
                fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
            },
        },
    };
</script>
<script src="https://unpkg.com/lucide@0.460.0/dist/umd/lucide.min.js"></script>
@endsection

@section('content')
@php
    $stPed = $item->status_pedido ?? 'aguardando_confirmacao';
    $stPag = $item->status_pagamento ?? 'pendente';
    $lblStatusPedido = [
        'aguardando_confirmacao' => 'Aguardando confirmação',
        'confirmado' => 'Confirmado',
        'em_separacao' => 'Em separação',
        'separado' => 'Separado',
        'alteracao_pendente' => 'Alteração pendente',
        'em_rota_entrega' => 'Em rota de entrega',
        'ocorrencia_entrega' => 'Ocorrência na entrega',
        'entregue' => 'Entregue',
        'cancelada' => 'Cancelada',
    ];
    $lblStatusPagamento = [
        'pendente' => 'Pagamento pendente',
        'pago' => 'Pago',
        'parcial' => 'Parcial',
        'estornado' => 'Estornado',
    ];
    $twPedido = [
        'aguardando_confirmacao' => 'bg-amber-500/15 text-amber-300 border-amber-500/30',
        'confirmado' => 'bg-sky-500/15 text-sky-300 border-sky-500/30',
        'em_separacao' => 'bg-indigo-500/15 text-indigo-300 border-indigo-500/30',
        'separado' => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
        'alteracao_pendente' => 'bg-rose-500/15 text-rose-300 border-rose-500/30',
        'em_rota_entrega' => 'bg-violet-500/15 text-violet-300 border-violet-500/30',
        'ocorrencia_entrega' => 'bg-rose-500/15 text-rose-300 border-rose-500/30',
        'entregue' => 'bg-teal-500/15 text-teal-300 border-teal-500/30',
        'cancelada' => 'bg-gray-500/20 text-gray-400 border-gray-600',
    ];
    $twPagamento = [
        'pendente' => 'bg-amber-500/15 text-amber-300 border-amber-500/30',
        'pago' => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
        'parcial' => 'bg-sky-500/15 text-sky-300 border-sky-500/30',
        'estornado' => 'bg-gray-500/20 text-gray-400 border-gray-600',
    ];
    $twNfe = [
        'novo' => 'bg-gray-500/20 text-gray-300 border-gray-600',
        'aprovado' => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
        'rejeitado' => 'bg-rose-500/15 text-rose-300 border-rose-500/30',
        'cancelado' => 'bg-gray-500/20 text-gray-400 border-gray-600',
    ];
    $estNfe = $item->estado_emissao ?? 'novo';
    $somaItensCalc = $item->itens->sum(fn ($p) => (float) $p->quantidade * (float) $p->valor);
    $totalComFrete = (float) $item->valor_total + (float) $item->frete;
    $rotuloAcaoAuditoria = [
        'venda_criada' => 'Venda criada',
        'venda_alterada' => 'Venda alterada',
        'workflow_confirmar_pedido' => 'Pedido confirmado',
        'workflow_marcar_separado' => 'Marcado como separado',
        'workflow_confirmar_alteracao' => 'Alteração conferida',
        'workflow_marcar_pago' => 'Pagamento registrado',
        'workflow_marcar_entregue' => 'Marcado como entregue',
        'fluxo_impressao_em_separacao' => 'Foi para separação (impressão)',
        'ficha_separacao_impressa' => 'Ficha de separação impressa',
        'pedido_pdf_baixado' => 'PDF do pedido baixado',
        'entrega_confirmada_motoboy' => 'Entrega confirmada (motoboy)',
        'entrega_ocorrencia_motoboy' => 'Ocorrência na entrega (motoboy)',
        'caixa_fechada' => 'Caixa fechado (ADM)',
        'nf_externa_emitida' => 'NF-e fiscal emitida',
        'nf_externa_pendente' => 'NF-e fiscal pendente',
    ];
@endphp

{{-- Escopo isolado: largura total da área útil (col-12 + sem max-width) --}}
<div id="lux-venda-show" class="dark text-gray-100 font-sans antialiased col-12 w-100 px-0">
    <div class="min-h-[calc(100vh-8rem)] w-full max-w-none rounded-xl bg-gray-900 border border-gray-800 shadow-md p-4 sm:p-5 lg:p-6 xl:p-8">

        {{-- 1. Cabeçalho --}}
        <header class="mb-8 pb-6 border-b border-gray-800">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-sm font-medium text-indigo-400 uppercase tracking-wide">Detalhes da venda</p>
                    <div class="mt-1 flex flex-wrap items-center gap-3">
                        <h1 class="text-3xl sm:text-4xl font-semibold text-gray-100 tracking-tight">#{{ $item->id }}</h1>
                        @if($item->fechada_caixa)
                            <span class="inline-flex items-center gap-1 rounded-full border border-gray-600 bg-gray-800 px-3 py-1 text-xs font-medium text-gray-300">
                                <i data-lucide="lock" class="size-3.5"></i> Fechada no caixa
                            </span>
                            @if(!empty($usuarioAdm))
                                <button type="button" id="lux-venda-reabrir-caixa" data-venda="{{ $item->id }}" class="inline-flex items-center gap-1 rounded-full border border-amber-500/50 bg-amber-500/15 px-3 py-1 text-xs font-medium text-amber-200 transition hover:bg-amber-500/25" title="Permitir edição e alterações novamente">
                                    <i data-lucide="lock-open" class="size-3.5"></i> Reabrir caixa (ADM)
                                </button>
                            @endif
                        @endif
                    </div>
                    @if($item->pedido_nuvemshop_id)
                        <p class="mt-2 text-sm text-gray-400">
                            Pedido Nuvemshop —
                            <a href="{{ route('nuvemshop-pedidos.show', [$item->pedidoNuvemShop->pedido_id]) }}" class="text-indigo-400 hover:text-indigo-300 underline-offset-2 hover:underline">ver pedido</a>
                        </p>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium {{ $twPedido[$stPed] ?? 'bg-gray-500/15 text-gray-300 border-gray-600' }}">
                        <i data-lucide="package" class="size-3.5 shrink-0"></i>
                        {{ $lblStatusPedido[$stPed] ?? $stPed }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium {{ $twPagamento[$stPag] ?? 'bg-gray-500/15 text-gray-300 border-gray-600' }}">
                        <i data-lucide="wallet" class="size-3.5 shrink-0"></i>
                        {{ $lblStatusPagamento[$stPag] ?? $stPag }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium {{ $twNfe[$estNfe] ?? $twNfe['novo'] }}">
                        <i data-lucide="file-text" class="size-3.5 shrink-0"></i>
                        NF-e: {{ ucfirst($estNfe) }}
                    </span>
                </div>
            </div>

            {{-- Cliente em duas colunas --}}
            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="rounded-xl border border-gray-700 bg-gray-800/50 p-4 shadow-md">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-3">Cliente</p>
                    <p class="text-lg font-semibold text-gray-100">{{ $item->cliente->razao_social }}</p>
                    <p class="mt-1 text-sm text-gray-400 font-mono">{{ $item->cliente->cpf_cnpj }}</p>
                </div>
                <div class="rounded-xl border border-gray-700 bg-gray-800/50 p-4 shadow-md">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-3">Resumo rápido</p>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-400">Valor total</dt>
                            <dd class="font-semibold text-green-400 tabular-nums">{{ __moeda($item->valor_total) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-400">Registro</dt>
                            <dd class="text-gray-200">{{ $item->data_registro }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </header>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 lg:gap-8">

            <div class="xl:col-span-2 space-y-6">

                {{-- 2. Informações da venda (grid) --}}
                <section class="rounded-xl border border-gray-700 bg-gray-800 shadow-md overflow-hidden">
                    <div class="px-4 sm:px-5 py-4 border-b border-gray-700 flex items-center gap-2">
                        <i data-lucide="clipboard-list" class="size-5 text-indigo-400"></i>
                        <h2 class="text-base font-semibold text-gray-100">Informações da venda</h2>
                    </div>
                    <div class="p-4 sm:p-5 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                        <div>
                            <p class="text-gray-500 text-xs uppercase tracking-wide mb-1">Cliente</p>
                            <p class="text-gray-100">{{ $item->cliente->razao_social }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500 text-xs uppercase tracking-wide mb-1">CPF / CNPJ</p>
                            <p class="text-gray-100 font-mono">{{ $item->cliente->cpf_cnpj }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500 text-xs uppercase tracking-wide mb-1">Data</p>
                            <p class="text-gray-100">{{ $item->data_registro }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500 text-xs uppercase tracking-wide mb-1">Cidade</p>
                            <p class="text-gray-100">{{ optional($item->cliente->cidade)->nome ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500 text-xs uppercase tracking-wide mb-1">Valor total</p>
                            <p class="text-green-400 font-semibold tabular-nums">{{ __moeda($item->valor_total) }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500 text-xs uppercase tracking-wide mb-1">Estado (UF)</p>
                            <p class="text-gray-100">{{ optional($item->cliente->cidade)->uf ?? '—' }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <p class="text-gray-500 text-xs uppercase tracking-wide mb-1">Chave da NF-e</p>
                            <p class="text-gray-300 font-mono text-xs break-all leading-relaxed">{{ $item->chave ? $item->chave : '—' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500 text-xs uppercase tracking-wide mb-1">Data de entrega</p>
                            <p class="text-gray-100">{{ $item->data_entrega ?? '—' }}</p>
                        </div>
                        @if(!empty($item->aviso_entrega))
                        <div class="sm:col-span-2">
                            <p class="text-gray-500 text-xs uppercase tracking-wide mb-1">Aviso de entrega</p>
                            <p class="text-amber-300 font-semibold">⚠ {{ $item->aviso_entrega }}</p>
                        </div>
                        @endif
                    </div>
                </section>

                {{-- 3. Itens --}}
                <section class="rounded-xl border border-gray-700 bg-gray-800 shadow-md overflow-hidden">
                    <div class="px-4 sm:px-5 py-4 border-b border-gray-700 flex items-center gap-2">
                        <i data-lucide="shopping-cart" class="size-5 text-indigo-400"></i>
                        <h2 class="text-base font-semibold text-gray-100">Itens da venda</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead>
                                <tr class="border-b border-gray-700 bg-gray-900/40 text-gray-400 text-xs uppercase tracking-wide">
                                    <th class="px-4 py-3 font-medium">ID</th>
                                    <th class="px-4 py-3 font-medium">Produto</th>
                                    <th class="px-4 py-3 font-medium text-right">Qtd</th>
                                    <th class="px-4 py-3 font-medium text-right">Valor unit.</th>
                                    <th class="px-4 py-3 font-medium text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700/80">
                                @foreach ($item->itens as $p)
                                <tr class="transition-colors hover:bg-gray-700/30">
                                    <td class="px-4 py-3.5 text-gray-400 font-mono">{{ $p->id }}</td>
                                    <td class="px-4 py-3.5 text-gray-100">{{ $p->produto->nome }}@if($p->fiscal) <span class="ml-1 inline-block rounded px-1.5 py-0.5 text-[10px] font-bold bg-indigo-600 text-white" title="Item fiscal: NF-e no outro sistema">F</span>@endif</td>
                                    <td class="px-4 py-3.5 text-right tabular-nums text-gray-200">{{ __moeda($p->quantidade) }}</td>
                                    <td class="px-4 py-3.5 text-right tabular-nums text-gray-300">{{ __moeda($p->valor) }}</td>
                                    <td class="px-4 py-3.5 text-right tabular-nums font-medium text-indigo-300">{{ __moeda($p->quantidade * $p->valor) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                {{-- 5. Pagamento + Fatura --}}
                <section class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="rounded-xl border border-gray-700 bg-gray-800 shadow-md p-4 sm:p-5">
                        <div class="flex items-center gap-2 mb-4">
                            <i data-lucide="credit-card" class="size-5 text-indigo-400"></i>
                            <h2 class="text-base font-semibold text-gray-100">Pagamento</h2>
                        </div>
                        <dl class="space-y-3 text-sm">
                            <div>
                                <dt class="text-gray-500 text-xs uppercase tracking-wide">Forma</dt>
                                <dd class="mt-0.5 text-gray-100">{{ $item->forma_pagamento }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 text-xs uppercase tracking-wide">Tipo</dt>
                                <dd class="mt-0.5 text-gray-100">{{ \App\Models\Venda::getTipo($item->tipo_pagamento) }}</dd>
                            </div>
                        </dl>
                    </div>
                    <div class="rounded-xl border border-gray-700 bg-gray-800 shadow-md p-4 sm:p-5">
                        <div class="flex items-center gap-2 mb-4">
                            <i data-lucide="receipt" class="size-5 text-indigo-400"></i>
                            <h2 class="text-base font-semibold text-gray-100">Fatura / parcelas</h2>
                        </div>
                        <div class="overflow-x-auto rounded-lg border border-gray-700">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="bg-gray-900/50 text-gray-400 text-xs uppercase border-b border-gray-700">
                                        <th class="px-3 py-2 text-left font-medium">Vencimento</th>
                                        <th class="px-3 py-2 text-right font-medium">Valor</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-700/80">
                                    @if(count($item->duplicatas) > 0)
                                        @foreach ($item->duplicatas as $dup)
                                        <tr class="hover:bg-gray-700/25 transition-colors">
                                            <td class="px-3 py-2.5 text-gray-200">
                                                {{ $dup->data_vencimento ? \Carbon\Carbon::parse($dup->data_vencimento)->format('d/m/Y') : '—' }}
                                            </td>
                                            <td class="px-3 py-2.5 text-right tabular-nums font-medium text-gray-100">{{ __moeda($dup->valor_integral) }}</td>
                                        </tr>
                                        @endforeach
                                    @else
                                        <tr class="hover:bg-gray-700/25 transition-colors">
                                            <td class="px-3 py-2.5 text-gray-200">{{ __data_pt($item->created_at, 0) }}</td>
                                            <td class="px-3 py-2.5 text-right tabular-nums font-medium text-gray-100">{{ __moeda($item->valor_total) }}</td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                {{-- Carnê (lógica preservada) --}}
                @if(count($item->duplicatas) > 0)
                <section class="rounded-xl border border-gray-700 bg-gray-800/50 shadow-md p-4 sm:p-5">
                    <h2 class="text-sm font-semibold text-gray-200 mb-3 flex items-center gap-2">
                        <i data-lucide="file-stack" class="size-4 text-indigo-400"></i> Gerar carnê
                    </h2>
                    {!! Form::open()->get()->route('vendas.carne') !!}
                    <input type="hidden" value="{{ $item->id }}" name="id">
                    <div class="flex flex-col sm:flex-row flex-wrap gap-3 items-end">
                        <div class="w-full sm:w-40">
                            {!! Form::text('juros', 'Juros')->attrs(['class' => 'moeda form-control']) !!}
                        </div>
                        <div class="w-full sm:w-40">
                            {!! Form::text('multa', 'Multa')->attrs(['class' => 'moeda form-control']) !!}
                        </div>
                        <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white shadow-md transition hover:bg-indigo-500 hover:shadow-lg">
                            <i data-lucide="plus" class="size-4"></i> Gerar carnê
                        </button>
                    </div>
                    {!! Form::close() !!}
                </section>
                @endif

                {{-- 7. Histórico --}}
                <section class="rounded-xl border border-gray-700 bg-gray-800 shadow-md overflow-hidden">
                    <div class="px-4 sm:px-5 py-4 border-b border-gray-700">
                        <div class="flex items-center gap-2">
                            <i data-lucide="history" class="size-5 text-indigo-400"></i>
                            <h2 class="text-base font-semibold text-gray-100">Histórico da venda</h2>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Atividades desta venda (quem fez o quê), em linguagem simples.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead>
                                <tr class="border-b border-gray-700 bg-gray-900/40 text-gray-400 text-xs uppercase tracking-wide">
                                    <th class="px-4 py-3 font-medium whitespace-nowrap">Quando</th>
                                    <th class="px-4 py-3 font-medium">Atividade</th>
                                    <th class="px-4 py-3 font-medium min-w-[12rem]">O que aconteceu</th>
                                    <th class="px-4 py-3 font-medium">Usuário</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700/80">
                                @forelse(($auditorias ?? collect()) as $a)
                                @php
                                    $acaoAud = (string) ($a->acao ?? '');
                                    $slug = $rotuloAcaoAuditoria[$acaoAud] ?? $acaoAud;
                                    $badgeHist = match (true) {
                                        str_contains($acaoAud, 'workflow') => 'bg-violet-500/15 text-violet-300 border-violet-500/30',
                                        str_contains($acaoAud, 'caixa') => 'bg-amber-500/15 text-amber-300 border-amber-500/30',
                                        $acaoAud === 'venda_criada' => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
                                        $acaoAud === 'venda_alterada' => 'bg-sky-500/15 text-sky-300 border-sky-500/30',
                                        default => 'bg-gray-500/15 text-gray-300 border-gray-600',
                                    };
                                @endphp
                                <tr class="hover:bg-gray-700/30 transition-colors align-top">
                                    <td class="px-4 py-3 text-gray-400 whitespace-nowrap text-xs">{{ \Carbon\Carbon::parse($a->created_at)->format('d/m/Y H:i') }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $badgeHist }}">{{ $slug }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-300 text-xs max-w-xl whitespace-pre-line break-words">{{ $a->descricao }}</td>
                                    <td class="px-4 py-3 text-gray-200">{{ $a->usuario ? $a->usuario->nome : '—' }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-gray-500 text-sm">Nenhum registro de auditoria ainda. Novas ações passam a ser gravadas automaticamente.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            {{-- Coluna lateral: resumo + ações --}}
            <div class="xl:col-span-1 space-y-6">
                {{-- 4. Resumo financeiro (fixo ao rolar: sticky + self-start no grid) --}}
                <aside class="rounded-xl border border-gray-700 bg-gray-800 shadow-md p-5 xl:sticky xl:top-24 xl:z-30 xl:self-start xl:max-h-[calc(100vh-7rem)] xl:overflow-y-auto xl:overscroll-contain">
                    <div class="flex items-center gap-2 mb-4">
                        <i data-lucide="calculator" class="size-5 text-indigo-400"></i>
                        <h2 class="text-base font-semibold text-gray-100">Resumo financeiro</h2>
                    </div>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between gap-3 border-b border-gray-700/80 pb-3">
                            <dt class="text-gray-400">Soma dos itens</dt>
                            <dd class="tabular-nums text-gray-200 font-medium">{{ __moeda($somaItensCalc) }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-400">Desconto</dt>
                            <dd class="tabular-nums text-rose-300/90">− {{ __moeda($item->desconto) }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-400">Acréscimo</dt>
                            <dd class="tabular-nums text-gray-200">+ {{ __moeda($item->acrescimo) }}</dd>
                        </div>
                        <div class="flex justify-between gap-3 border-b border-gray-700/80 pb-3">
                            <dt class="text-gray-400">Frete</dt>
                            <dd class="tabular-nums text-gray-200">+ {{ __moeda($item->frete) }}</dd>
                        </div>
                        <div class="flex justify-between gap-3 items-baseline pt-1">
                            <dt class="text-base font-semibold text-gray-100">Total</dt>
                            <dd class="text-xl font-bold tabular-nums text-green-400">{{ __moeda($totalComFrete) }}</dd>
                        </div>
                    </dl>
                    <p class="mt-4 text-xs text-gray-500 leading-relaxed">O total exibido segue a regra anterior: valor total da venda + frete.</p>
                </aside>

                @php
                    $divFiscal = $item->divisaoFiscal();
                    $sitNf = $item->situacaoNfExterna($divFiscal);
                @endphp
                @if($divFiscal['tem_fiscal'] || $sitNf)
                {{-- Divisão em duas contas (interno: vendedor e fechamento) --}}
                <div id="nf-externa" class="rounded-xl border border-indigo-500/40 bg-gray-800 shadow-md p-5">
                    <div class="flex items-center gap-2 mb-4">
                        <i data-lucide="split" class="size-5 text-indigo-400"></i>
                        <h2 class="text-base font-semibold text-gray-100">Cobrar em duas contas</h2>
                    </div>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between gap-3 items-baseline">
                            <dt class="text-gray-300"><span class="inline-block rounded px-1.5 py-0.5 text-[10px] font-bold bg-indigo-600 text-white mr-1">F</span>{{ config('lux.conta_fiscal') }}</dt>
                            <dd class="text-lg font-bold tabular-nums text-indigo-300 flex items-center gap-2">@include('vendas.partials.pix_btn', ['conta' => 'fiscal', 'valor' => $divFiscal['fiscal'], 'txid' => 'LUX' . $item->id . 'F']){{ __moeda($divFiscal['fiscal']) }}</dd>
                        </div>
                        <div class="flex justify-between gap-3 items-baseline border-b border-gray-700/80 pb-3">
                            <dt class="text-gray-300"><span class="inline-block rounded px-1.5 py-0.5 text-[10px] font-bold bg-gray-500 text-white mr-1">2</span>{{ config('lux.conta_nao_fiscal') }}</dt>
                            <dd class="text-lg font-bold tabular-nums text-gray-100 flex items-center gap-2">@include('vendas.partials.pix_btn', ['conta' => 'nao_fiscal', 'valor' => $divFiscal['nao_fiscal'], 'txid' => 'LUX' . $item->id . 'N']){{ __moeda($divFiscal['nao_fiscal']) }}</dd>
                        </div>
                        <div class="flex justify-between gap-3 text-xs text-gray-500">
                            <dt>Total</dt>
                            <dd class="tabular-nums">{{ __moeda($divFiscal['total']) }}</dd>
                        </div>
                    </dl>

                    <details class="mt-4">
                        <summary class="cursor-pointer text-xs font-medium text-indigo-300">Itens para emitir a NF-e ({{ $item->itensFiscais()->count() }})</summary>
                        <table class="w-full mt-2 text-xs text-gray-300">
                            <thead class="text-gray-500">
                                <tr><th class="text-left py-1">Produto</th><th class="text-right py-1">Qtd</th><th class="text-right py-1">Unit.</th><th class="text-right py-1">Subtotal</th></tr>
                            </thead>
                            <tbody>
                                @foreach($item->itensFiscais() as $fi)
                                <tr class="border-t border-gray-700/60">
                                    <td class="py-1 pr-2">{{ $fi->produto->nome ?? ('#' . $fi->produto_id) }}</td>
                                    <td class="py-1 text-right tabular-nums">{{ rtrim(rtrim(number_format((float) $fi->quantidade, 3, ',', '.'), '0'), ',') }}</td>
                                    <td class="py-1 text-right tabular-nums">{{ __moeda($fi->valor) }}</td>
                                    <td class="py-1 text-right tabular-nums">{{ __moeda($fi->subtotal()) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if((float) $item->desconto > 0 || (float) $item->acrescimo > 0)
                        <p class="mt-2 text-[11px] text-gray-500">Desconto/acréscimo rateados: valor fiscal já considera a parte proporcional. Itens: {{ __moeda($divFiscal['itens_fiscal']) }} → cobrar {{ __moeda($divFiscal['fiscal']) }}.</p>
                        @endif
                    </details>

                    {{-- Controle da NF-e emitida no outro sistema --}}
                    <div class="mt-4 border-t border-gray-700/80 pt-4">
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">NF-e fiscal (outro sistema)</p>
                        @if($sitNf === 'emitida')
                            <div class="rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-3 py-2 text-sm text-emerald-200">
                                <i data-lucide="circle-check" class="size-4 inline -mt-0.5"></i>
                                Emitida{{ $item->nf_externa_numero ? ' · nº ' . $item->nf_externa_numero : '' }}
                                <div class="text-[11px] text-emerald-300/70 mt-0.5">
                                    {{ $item->nf_externa_em ? __data_pt($item->nf_externa_em, 1) : '' }}{{ optional($item->nfExternaUsuario)->nome ? ' · ' . $item->nfExternaUsuario->nome : '' }}
                                </div>
                            </div>
                        @elseif($sitNf === 'divergente')
                            <div class="rounded-lg border border-rose-500/50 bg-rose-500/10 px-3 py-2 text-sm text-rose-200">
                                <i data-lucide="triangle-alert" class="size-4 inline -mt-0.5"></i>
                                Marcada como emitida{{ $item->nf_externa_numero ? ' (nº ' . $item->nf_externa_numero . ')' : '' }} com
                                <strong>R$ {{ __moeda($item->nf_externa_valor) }}</strong>, mas o valor fiscal agora é
                                <strong>R$ {{ __moeda($divFiscal['fiscal']) }}</strong>. Confira a nota.
                            </div>
                        @else
                            <div class="rounded-lg border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-sm text-amber-200">
                                <i data-lucide="clock" class="size-4 inline -mt-0.5"></i> Pendente
                            </div>
                        @endif

                        <div class="mt-3 flex flex-col gap-2">
                            @if($sitNf !== 'emitida' && $divFiscal['tem_fiscal'])
                            <input type="text" id="lux-nf-numero" maxlength="30" placeholder="Nº da NF-e (opcional)"
                                value="{{ $item->nf_externa_numero }}"
                                class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-sm text-gray-100 placeholder-gray-500 focus:border-indigo-500 focus:outline-none">
                            <button type="button" class="lux-nf-acao inline-flex items-center justify-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-500" data-acao="emitida">
                                <i data-lucide="file-check" class="size-4"></i> Marcar NF como emitida
                            </button>
                            @endif
                            @if($sitNf === 'emitida' || $sitNf === 'divergente')
                            <button type="button" class="lux-nf-acao inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-600 px-3 py-2 text-sm font-medium text-gray-300 hover:bg-gray-700" data-acao="pendente">
                                <i data-lucide="undo-2" class="size-4"></i> Voltar para pendente
                            </button>
                            @endif
                        </div>
                    </div>
                </div>
                @endif

                {{-- 6. Ações --}}
                <div class="rounded-xl border border-gray-700 bg-gray-800 shadow-md p-5">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-3">Ações</p>
                    <div class="flex flex-col sm:flex-row xl:flex-col gap-3">
                        @if($item->fechada_caixa)
                            @if(!empty($usuarioAdm))
                                <p class="text-sm text-amber-200/90 leading-relaxed">Venda travada no caixa. Use <strong>Reabrir caixa</strong> acima para permitir alterações.</p>
                            @else
                                <p class="text-sm text-gray-400 leading-relaxed">Esta venda está fechada no caixa e não pode ser editada.</p>
                            @endif
                        @else
                            <a href="{{ route('vendas.edit', $item->id) }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm font-medium text-amber-200 shadow-md transition hover:bg-amber-500/20 hover:border-amber-400/60">
                                <i data-lucide="pencil" class="size-4"></i> Editar
                            </a>
                        @endif
                        <a href="{{ route('vendas.print', ['id' => $item->id, 'download' => 1]) }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-sky-500/40 bg-sky-500/10 px-4 py-3 text-sm font-medium text-sky-200 shadow-md transition hover:bg-sky-500/20 hover:border-sky-400/60">
                            <i data-lucide="download" class="size-4"></i> Baixar pedido (PDF)
                        </a>
                        @php $podeFichaShow = in_array($item->status_pedido, ['confirmado', 'em_separacao', 'separado', 'alteracao_pendente', 'em_rota_entrega', 'entregue'], true); @endphp
                        <a href="{{ $podeFichaShow ? route('vendas.print-ficha', $item->id) : '#' }}"
                            target="{{ $podeFichaShow ? '_blank' : '_self' }}"
                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm font-medium text-amber-200 shadow-md transition hover:bg-amber-500/20 hover:border-amber-400/60 {{ !$podeFichaShow ? 'opacity-40 pointer-events-none' : '' }}">
                            <i data-lucide="printer" class="size-4"></i> Ficha de separação
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof lucide !== 'undefined' && lucide.createIcons) {
            lucide.createIcons();
        }
        var btnReabrir = document.getElementById('lux-venda-reabrir-caixa');
        var csrfToken = '{{ csrf_token() }}';

        document.querySelectorAll('.lux-nf-acao').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var numEl = document.getElementById('lux-nf-numero');
                var body = new URLSearchParams();
                body.append('acao', btn.getAttribute('data-acao'));
                if (numEl) body.append('numero', numEl.value || '');
                btn.disabled = true;
                fetch("{{ route('vendas.nf-externa', $item->id) }}", {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: body
                }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (x) {
                    if (!x.ok) {
                        btn.disabled = false;
                        swal("Erro", (x.j && x.j.message) || "Não foi possível salvar.", "error");
                        return;
                    }
                    window.location.hash = 'nf-externa';
                    window.location.reload();
                }).catch(function () {
                    btn.disabled = false;
                    swal("Erro", "Falha de conexão.", "error");
                });
            });
        });
        if (btnReabrir) {
            btnReabrir.addEventListener('click', function () {
                var vid = btnReabrir.getAttribute('data-venda');
                swal({
                    title: "Reabrir no caixa?",
                    text: "A venda poderá ser editada e ter status alterado novamente.",
                    icon: "warning",
                    buttons: true,
                    dangerMode: true
                }).then(function (ok) {
                    if (!ok) return;
                    fetch("{{ url('vendas/reabrir-caixa') }}/" + encodeURIComponent(vid), {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json'
                        }
                    }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                    .then(function (x) {
                        if (!x.ok) {
                            swal("Erro", (x.j && x.j.message) || "Não foi possível reabrir.", "error");
                            return;
                        }
                        swal({ title: "Caixa reaberto", icon: "success", timer: 1200, buttons: false }).then(function () {
                            window.location.reload();
                        });
                    });
                });
            });
        }
    });
</script>
@endsection
