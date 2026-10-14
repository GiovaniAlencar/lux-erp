{{ $m['titulo'] }} - LUX Perfumes

@foreach($m['paragrafos'] ?? [] as $p)
{!! html_entity_decode(strip_tags($p), ENT_QUOTES, 'UTF-8') !!}

@endforeach
@foreach($m['detalhes'] ?? [] as $rotulo => $valor)
{!! $rotulo !!}: {!! $valor !!}
@endforeach
@if(!empty($m['botao']))

{!! $m['botao'][0] !!}: {!! $m['botao'][1] !!}
@endif
@if(!empty($m['nota']))

{!! $m['nota'] !!}
@endif

--
LUX Perfumes · {{ preg_replace('#^https?://#', '', config('lux.site_url')) }} · WhatsApp {{ config('lux.whatsapp_exibir') }}
