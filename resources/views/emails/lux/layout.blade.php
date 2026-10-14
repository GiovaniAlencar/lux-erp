@php
	$site = config('lux.site_url');
	$ouro = '#C9A24D';
	$preto = '#0B0B0C';
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light only">
<title>{{ $m['assunto'] }}</title>
</head>
<body style="margin:0;padding:0;background:#F4F1EA;font-family:Arial,Helvetica,sans-serif;color:#222;">
@if(!empty($m['preheader']))
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:#F4F1EA;">{{ $m['preheader'] }}&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;</div>
@endif
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#F4F1EA;">
<tr><td align="center" style="padding:24px 12px;">
	<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;">
		{{-- topo --}}
		<tr><td align="center" style="background:{{ $preto }};border-radius:14px 14px 0 0;padding:22px 24px;">
			<a href="{{ $site }}" style="text-decoration:none;">
				<img src="{{ $site }}/images/logomarca.png" width="150" alt="LUX PERFUMES" style="display:block;border:0;width:150px;max-width:150px;height:auto;color:{{ $ouro }};font-size:22px;font-weight:bold;letter-spacing:4px;">
			</a>
		</td></tr>
		<tr><td style="height:3px;line-height:3px;font-size:0;background:{{ $ouro }};">&nbsp;</td></tr>

		{{-- conteúdo --}}
		<tr><td style="background:#ffffff;padding:32px 32px 28px;">
			<p style="margin:0 0 6px;font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#9C7A2E;font-weight:bold;">LUX Perfumes</p>
			<h1 style="margin:0 0 20px;font-size:24px;line-height:30px;color:{{ $preto }};">{{ $m['titulo'] }}</h1>

			@foreach($m['paragrafos'] ?? [] as $p)
				<p style="margin:0 0 14px;font-size:15px;line-height:23px;color:#333;">@if(!empty($m['html'])){!! $p !!}@else{{ $p }}@endif</p>
			@endforeach

			@if(!empty($m['detalhes']))
			<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:10px 0 18px;background:#FAF8F3;border:1px solid #ECE7DC;border-radius:10px;">
				@foreach($m['detalhes'] as $rotulo => $valor)
				<tr>
					<td style="padding:10px 16px;font-size:13px;color:#777;{{ !$loop->last ? 'border-bottom:1px solid #ECE7DC;' : '' }}">{{ $rotulo }}</td>
					<td align="right" style="padding:10px 16px;font-size:14px;font-weight:bold;color:{{ $preto }};{{ !$loop->last ? 'border-bottom:1px solid #ECE7DC;' : '' }}">{{ $valor }}</td>
				</tr>
				@endforeach
			</table>
			@endif

			@if(!empty($m['botao']))
			<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:22px 0 6px;">
				<tr><td align="center" bgcolor="{{ $ouro }}" style="border-radius:8px;">
					<a href="{{ $m['botao'][1] }}" target="_blank" style="display:inline-block;padding:14px 28px;font-size:14px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;color:{{ $preto }};text-decoration:none;border-radius:8px;">{{ $m['botao'][0] }}</a>
				</td></tr>
			</table>
			<p style="margin:10px 0 0;font-size:12px;line-height:18px;color:#999;">Se o botão não funcionar, copie e cole este endereço no navegador:<br><a href="{{ $m['botao'][1] }}" style="color:#9C7A2E;word-break:break-all;">{{ $m['botao'][1] }}</a></p>
			@endif

			@if(!empty($m['nota']))
			<p style="margin:22px 0 0;padding-top:16px;border-top:1px solid #ECE7DC;font-size:13px;line-height:20px;color:#777;">{{ $m['nota'] }}</p>
			@endif
		</td></tr>

		{{-- rodapé --}}
		<tr><td align="center" style="background:{{ $preto }};border-radius:0 0 14px 14px;padding:22px 24px;">
			<p style="margin:0 0 8px;font-size:13px;color:#E7C878;font-weight:bold;letter-spacing:1px;">LUX PERFUMES</p>
			<p style="margin:0;font-size:12px;line-height:19px;color:#bbb;">
				<a href="{{ $site }}" style="color:#bbb;text-decoration:none;">{{ preg_replace('#^https?://#', '', $site) }}</a>
				&nbsp;·&nbsp;
				<a href="https://wa.me/{{ config('lux.whatsapp') }}" style="color:#bbb;text-decoration:none;">WhatsApp {{ config('lux.whatsapp_exibir') }}</a>
				&nbsp;·&nbsp;
				<a href="https://instagram.com/{{ config('lux.instagram') }}" style="color:#bbb;text-decoration:none;">&#64;{{ config('lux.instagram') }}</a>
			</p>
		</td></tr>
		<tr><td align="center" style="padding:14px 10px 0;font-size:11px;color:#999;">Este é um e-mail automático. Para falar com a gente, use o WhatsApp.</td></tr>
	</table>
</td></tr>
</table>
</body>
</html>
