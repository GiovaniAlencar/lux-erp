@php
$menu = new App\Helpers\Menu();
$menu = $menu->preparaMenu();
// item atual: a URL do menu que é igual (ou o começo) da página aberta — ex.: /produtos/edit/5 → Produtos
$urlAtual = rtrim(url()->current(), '/');
$melhor = '';
foreach ($menu as $m) {
	foreach ($m['subs'] as $i) {
		$r = rtrim((string) ($i['rota'] ?? ''), '/');
		if ($r !== '' && ($urlAtual === $r || str_starts_with($urlAtual, $r . '/')) && strlen($r) > strlen($melhor)) {
			$melhor = $r;
		}
	}
}
@endphp

@foreach($menu as $m)
@if(!isset($m['ativo']) || $m['ativo'])
@php
	$grupoAtivo = $melhor !== ''
		? collect($m['subs'])->contains(fn ($i) => rtrim((string) ($i['rota'] ?? ''), '/') === $melhor)
		: $m['titulo'] == $rotaAtiva;
@endphp
@if(!empty($m['secao']))
<li class="lx-menu-label" role="presentation">{{ $m['secao'] }}</li>
@endif
<li @if($grupoAtivo) class="mm-active" @endif role="none">
	<a href="javascript:;" class="has-arrow" role="menuitem" aria-expanded="{{ $grupoAtivo ? 'true' : 'false' }}" title="{{$m['titulo']}}" aria-label="{{$m['titulo']}}">
		<div class="parent-icon"><i class='{{$m['icone']}}'></i>
		</div>
		<div class="menu-title">{{$m['titulo']}}</div>
	</a>
	<ul>
		@foreach($m['subs'] as $i)
		@if(!isset($i['rota_ativa']) && $i['rota'] != '')
		<li role="none"><a role="menuitem" @isset($i['target']) target="_blank" @endisset href="{{$i['rota']}}" title="{{$i['nome']}}" @if(rtrim($i['rota'], '/') === $melhor) class="lx-current" aria-current="page" @endif>
			<i class="bx bx-circle" style="font-size: 10px;"></i>{{$i['nome']}}</a>
		</li>
		@endif
		@endforeach
	</ul>
</li>
@endif
@endforeach
