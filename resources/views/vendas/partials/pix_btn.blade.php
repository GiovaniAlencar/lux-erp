{{-- Botão "PIX" que copia o copia e cola com o valor. Params: $conta ('fiscal'|'nao_fiscal'), $valor, $txid (opcional). --}}
@php $__pixCode = \App\Helpers\PixCopiaCola::paraConta($conta, (float) $valor, $txid ?? null); @endphp
@if($__pixCode)
@include('vendas.partials.pix_js')
<button type="button" class="lux-pix-btn" data-pix="{{ $__pixCode }}" title="Copiar PIX copia e cola de R$ {{ __moeda($valor) }} ({{ $conta === 'fiscal' ? config('lux.conta_fiscal') : config('lux.conta_nao_fiscal') }})">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
    <span class="lux-pix-label">PIX</span>
</button>
@endif
