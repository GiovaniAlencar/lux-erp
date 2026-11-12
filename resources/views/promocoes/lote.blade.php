@extends('default.layout', ['title' => 'Promoções em lote'])
@section('content')
@php
    $v = fn ($k, $padrao = '') => old($k, $cfg[$k] ?? $padrao);
    $bases = \App\Services\PromocaoLote::BASES;
    $ajustes = \App\Services\PromocaoLote::AJUSTES;
@endphp
<div class="page-content">
    <div class="card">
        <div class="card-body p-4">
            <div class="d-flex align-items-center mb-3">
                <h5 class="mb-0">Promoções em lote</h5>
                <a href="{{ route('promocoes.index') }}" class="btn btn-light ms-auto"><i class="bx bx-arrow-back"></i> Voltar</a>
            </div>
            <p class="text-muted small mb-4">
                Configure a campanha uma vez e aplique em vários produtos. Cada produto ganha uma promoção de <b>preço fixo</b>
                que vale em <b>qualquer quantidade</b> — no site aparece "de R$ (preço normal) por R$ (promoção)".
                Nada é gravado até você conferir a prévia e confirmar.
            </p>

            @if($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <form method="post" action="{{ route('promocoes.lote.previa') }}" id="form-lote">
                @csrf
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Nome da campanha</label>
                        <input type="text" name="campanha" class="form-control" maxlength="120" required placeholder="ex.: BLACK MINI" value="{{ $v('campanha') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Começa em</label>
                        <input type="date" name="data_inicio" class="form-control" required value="{{ $v('data_inicio', date('Y-m-d')) }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Termina em</label>
                        <input type="date" name="data_fim" class="form-control" required value="{{ $v('data_fim') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Limite por produto <small class="text-muted">(opcional)</small></label>
                        <input type="number" name="quantidade_limite" min="1" class="form-control" placeholder="sem limite" value="{{ $v('quantidade_limite') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Se já tiver promoção</label>
                        <select name="se_existir" class="form-select">
                            <option value="substituir" @selected($v('se_existir', 'substituir') === 'substituir')>Substituir</option>
                            <option value="pular" @selected($v('se_existir') === 'pular')>Manter a atual</option>
                        </select>
                    </div>
                </div>

                <hr>
                <div class="mb-2"><b>Produtos</b></div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="origem" id="origem-lista" value="lista" @checked($v('origem', 'lista') === 'lista')>
                    <label class="form-check-label" for="origem-lista">Lista de IDs (com ou sem preço)</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="origem" id="origem-categoria" value="categoria" @checked($v('origem') === 'categoria')>
                    <label class="form-check-label" for="origem-categoria">Categoria inteira (só com estoque)</label>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-md-5" id="bloco-lista">
                        <textarea name="lista" rows="9" class="form-control font-monospace" placeholder="Uma linha por produto. Pode colar do Excel.&#10;753;89,90&#10;546;120&#10;888      (sem preço = usa a regra ao lado)">{{ $v('lista') }}</textarea>
                        <small class="text-muted">Formato: <code>ID;preço</code>, <code>ID preço</code> ou só <code>ID</code>.</small>
                    </div>
                    <div class="col-md-5" id="bloco-categoria">
                        <select name="categoria_id" class="form-select">
                            <option value="">Escolha a categoria…</option>
                            @foreach($categorias as $id => $nome)
                                <option value="{{ $id }}" @selected((string) $v('categoria_id') === (string) $id)>{{ $nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-7">
                        <div class="border rounded p-3 bg-light">
                            <div class="small fw-semibold mb-2">Regra do preço (para quem veio sem preço)</div>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <select name="base" class="form-select form-select-sm">
                                        @foreach($bases as $k => $rotulo)
                                            <option value="{{ $k }}" @selected($v('base', 'preco_3') === $k)>{{ $rotulo }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select name="ajuste" class="form-select form-select-sm">
                                        @foreach($ajustes as $k => $rotulo)
                                            <option value="{{ $k }}" @selected($v('ajuste', 'nenhum') === $k)>{{ $rotulo }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <input type="text" name="valor_ajuste" class="form-control form-control-sm" placeholder="ex.: 3,00" value="{{ old('valor_ajuste', isset($cfg['valor_ajuste']) ? __moeda($cfg['valor_ajuste']) : '') }}">
                                </div>
                            </div>
                            <div class="small text-muted mt-2">
                                Ex.: "preço de 20 em qualquer quantidade" = <i>faixa 3</i> + <i>sem ajuste</i>.
                                Miniaturas a R$ 3 a menos que o preço de 12 = <i>faixa 3</i> + <i>menos R$</i> 3,00.
                                Produto sem a faixa escolhida usa a próxima que tiver.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bx bx-search-alt"></i> Ver prévia</button>
                </div>

                @if($previa)
                    @php
                        $ok = array_filter($previa['linhas'], fn ($l) => $l['ok']);
                        $abaixo = array_filter($ok, fn ($l) => in_array('ABAIXO DO CUSTO', $l['avisos'], true));
                    @endphp
                    <hr>
                    <h6>Prévia — {{ count($ok) }} promoção(ões) serão criadas{{ count($previa['linhas']) - count($ok) ? ', ' . (count($previa['linhas']) - count($ok)) . ' ficam de fora' : '' }}</h6>
                    @if($previa['invalidas'])
                        <div class="alert alert-warning small mb-2">Ignorado: {{ implode(' · ', array_slice($previa['invalidas'], 0, 20)) }}{{ count($previa['invalidas']) > 20 ? ' …' : '' }}</div>
                    @endif
                    @if($abaixo)
                        <div class="alert alert-danger small mb-2"><b>{{ count($abaixo) }} produto(s) abaixo do custo.</b> Confira antes de confirmar.</div>
                    @endif
                    <div class="table-responsive" style="max-height: 520px">
                        <table class="table table-sm table-striped align-middle small">
                            <thead class="table-light" style="position: sticky; top: 0">
                                <tr>
                                    <th>ID</th><th>Produto</th><th>Categoria</th><th class="text-end">Estoque</th>
                                    <th class="text-end">Custo</th><th class="text-end">De (normal)</th><th class="text-end">Faixa 2</th><th class="text-end">Faixa 3</th>
                                    <th class="text-end">Por (promo)</th><th class="text-end">Desc.</th><th>Observação</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($previa['linhas'] as $l)
                                    <tr class="{{ $l['ok'] ? '' : 'table-secondary text-muted' }}">
                                        <td>{{ $l['id'] }}</td>
                                        <td>{{ $l['nome'] }}</td>
                                        <td>{{ $l['categoria'] }}</td>
                                        <td class="text-end">{{ (int) $l['estoque'] }}</td>
                                        <td class="text-end">{{ $l['custo'] > 0 ? __moeda($l['custo']) : '—' }}</td>
                                        <td class="text-end">{{ __moeda($l['preco']) }}</td>
                                        <td class="text-end">{{ $l['preco_2'] > 0 ? __moeda($l['preco_2']) : '—' }}</td>
                                        <td class="text-end">{{ $l['preco_3'] > 0 ? __moeda($l['preco_3']) : '—' }}</td>
                                        <td class="text-end fw-bold">{{ $l['promo'] !== null ? __moeda($l['promo']) : '—' }}</td>
                                        <td class="text-end">{{ $l['promo'] && $l['preco'] > 0 ? round((1 - $l['promo'] / $l['preco']) * 100) . '%' : '' }}</td>
                                        <td>
                                            @if($l['origem_preco'])<span class="text-muted">{{ $l['origem_preco'] }}</span>@endif
                                            @foreach($l['avisos'] as $a)
                                                <span class="badge {{ $a === 'ABAIXO DO CUSTO' ? 'bg-danger' : ($l['ok'] ? 'bg-warning text-dark' : 'bg-secondary') }}">{{ $a }}</span>
                                            @endforeach
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($ok)
                        <button type="submit" formaction="{{ route('promocoes.lote.salvar') }}" class="btn btn-success mt-2"
                            onclick="return confirm('Criar {{ count($ok) }} promoção(ões) na campanha {{ addslashes($cfg['campanha'] ?? '') }}?');">
                            <i class="bx bx-check"></i> Confirmar e criar {{ count($ok) }} promoção(ões)
                        </button>
                        <span class="small text-muted ms-2">Mudou alguma coisa acima? Clique em "Ver prévia" de novo antes de confirmar.</span>
                    @endif
                @endif
            </form>
        </div>
    </div>
</div>
@endsection
@section('js')
<script>
    (function () {
        const lista = document.getElementById('bloco-lista');
        const cat = document.getElementById('bloco-categoria');
        function troca() {
            const porCategoria = document.getElementById('origem-categoria').checked;
            lista.style.display = porCategoria ? 'none' : '';
            cat.style.display = porCategoria ? '' : 'none';
        }
        document.querySelectorAll('input[name=origem]').forEach((r) => r.addEventListener('change', troca));
        troca();
    })();
</script>
@endsection
