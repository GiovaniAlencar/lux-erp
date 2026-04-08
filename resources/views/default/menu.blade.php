@php
$menu = new App\Helpers\Menu();
$menu = $menu->preparaMenu();
@endphp


@foreach($menu as $m)
@if(!isset($m['ativo']) || $m['ativo'])
<li @if($m['titulo'] == $rotaAtiva) class="mm-active" @endif role="none">
	<a href="javascript:;" class="has-arrow" role="menuitem" aria-expanded="false" title="{{$m['titulo']}}" aria-label="{{$m['titulo']}}">
		<div class="parent-icon"><i class='{{$m['icone']}}'></i>
		</div>
		<div class="menu-title">{{$m['titulo']}}</div>
	</a>
	<ul>	
		@foreach($m['subs'] as $i)
		@if(!isset($i['rota_ativa']) && $i['rota'] != '')
		<li role="none"><a role="menuitem" @isset($i['target']) target="_blank" @endisset href="{{$i['rota']}}" title="{{$i['nome']}}">
			<i class="bx bx-circle" style="font-size: 10px;"></i>{{$i['nome']}}</a>
		</li>
		@endif
		@endforeach
	</ul>
</li>
@endif
@endforeach