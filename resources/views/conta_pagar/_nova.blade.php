{{--
  Nova conta a pagar (mesmo jeito do "Lançar entrada/saída").
  Usado na janela da lista (index) e na página /conta-pagar/create.
  Precisa de: $categorias (pagar), $fornecedores, $contasFin.
--}}
@php
    $icoCat = [
        'perfumes quebrados/faltando' => 'heartbreak',
        'entregas' => 'truck',
        'compras' => 'bag',
        'mídias' => 'megaphone',
        'diferenças/juros' => 'percent',
        'despesas' => 'receipt',
        'impostos (difal)' => 'bank',
        'taxas de pagamento' => 'credit-card',
    ];
    $temDespesa = \App\Http\Controllers\ContaPagarController::temDespesa();
    $catOld = old('categoria_id');
    $formas = ['Pix' => 'PIX', 'Boleto' => 'Boleto', 'Cartão de Crédito' => 'Cartão', 'Dinheiro' => 'Dinheiro', 'Depósito Bancário' => 'Transferência'];
@endphp
<form method="post" action="{{ route('conta-pagar.store') }}" enctype="multipart/form-data" class="ncp" novalidate>
    @csrf
    <div class="ncp-body">
        <div class="ncp-label required">Categoria</div>
        <div class="ncp-cats" role="radiogroup" aria-label="Categoria">
            @foreach($categorias as $cat)
            @php $ico = $icoCat[mb_strtolower($cat->nome)] ?? 'tag'; @endphp
            <input type="radio" name="categoria_id" id="ncp-cat-{{ $cat->id }}" value="{{ $cat->id }}" data-despesa="{{ ($cat->despesa ?? true) ? 1 : 0 }}" @checked($catOld == $cat->id)>
            <label for="ncp-cat-{{ $cat->id }}" class="ncp-cat"><i class="bi bi-{{ $ico }}"></i>{{ $cat->nome }}</label>
            @endforeach
            <input type="radio" name="categoria_id" id="ncp-cat-nova" value="" data-despesa="1">
            <label for="ncp-cat-nova" class="ncp-cat ncp-cat-mais"><i class="bi bi-plus-lg"></i>Outra</label>
        </div>
        <input type="text" name="categoria_nova" class="form-control mt-2 d-none ncp-cat-nova-inp" maxlength="60" placeholder="Nome da nova categoria" value="{{ old('categoria_nova') }}">

        <div class="row g-3 mt-1">
            <div class="col-sm-7">
                <label class="form-label required" for="ncp-ref">Descrição</label>
                <input name="referencia" id="ncp-ref" class="form-control" maxlength="255" required value="{{ old('referencia') }}" placeholder="Ex.: Frete Loggi semana 42, perfume quebrado pedido #120">
            </div>
            <div class="col-sm-5">
                <label class="form-label" for="ncp-forn">Fornecedor <span class="text-muted fw-normal">(opcional)</span></label>
                <select name="fornecedor_id" id="ncp-forn" class="form-select">
                    <option value="">—</option>
                    @foreach($fornecedores as $f)<option value="{{ $f->id }}" @selected(old('fornecedor_id') == $f->id)>{{ $f->razao_social }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-sm-4">
                <label class="form-label required" for="ncp-valor">Valor</label>
                <div class="input-group ncp-valor"><span class="input-group-text">R$</span><input name="valor_integral" id="ncp-valor" class="form-control lux-dinheiro" required value="{{ old('valor_integral') }}" placeholder="0,00"></div>
            </div>
            <div class="col-6 col-sm-4">
                <label class="form-label required" for="ncp-venc">Vencimento</label>
                <input type="date" name="data_vencimento" id="ncp-venc" class="form-control" required value="{{ old('data_vencimento', date('Y-m-d')) }}">
            </div>
            <div class="col-6 col-sm-4">
                <label class="form-label" for="ncp-forma">Forma</label>
                <select name="tipo_pagamento" id="ncp-forma" class="form-select">
                    @foreach($formas as $k => $lbl)<option value="{{ $k }}" @selected(old('tipo_pagamento', 'Pix') === $k)>{{ $lbl }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-sm-12">
                <label class="form-label" for="ncp-rep">Repetir todo mês</label>
                <select name="repetir" id="ncp-rep" class="form-select">
                    <option value="0">Não repetir</option>
                    @foreach([1, 2, 3, 5, 11] as $n)<option value="{{ $n }}" @selected(old('repetir') == $n)>Mais {{ $n }} {{ $n === 1 ? 'mês' : 'meses' }} ({{ $n + 1 }} no total)</option>@endforeach
                </select>
            </div>
        </div>

        @if($temDespesa)
        {{-- entra no fechamento do mês (lucro − despesas)? vem da categoria, mas dá para trocar --}}
        <div class="ncp-desp mt-3">
            <input type="hidden" name="despesa" value="0">
            <label class="ncp-desp-sw form-switch">
                <input type="checkbox" name="despesa" value="1" class="form-check-input" role="switch" @checked(old('despesa', '1') == '1')>
                <span><b class="ncp-desp-tit">Entra como despesa no fechamento do mês</b><small class="ncp-desp-dica">Diminui o lucro do mês (ex.: taxas, perfume quebrado, mídias).</small></span>
            </label>
        </div>
        @endif

        {{-- já paga? escolhe de qual conta saiu --}}
        <label class="ncp-paga mt-3">
            <input type="checkbox" name="paga" value="1" class="form-check-input" @checked(old('paga'))>
            <span><b>Já está paga</b><small>lança a saída no extrato da conta escolhida</small></span>
        </label>
        <div class="ncp-pag {{ old('paga') ? '' : 'd-none' }}">
            <div class="row g-3">
                <div class="col-sm-6">
                    <label class="form-label required" for="ncp-conta">Saiu da conta</label>
                    <select name="fin_conta_id" id="ncp-conta" class="form-select">
                        @forelse($contasFin as $c)<option value="{{ $c->id }}" @selected(old('fin_conta_id') ? old('fin_conta_id') == $c->id : $c->padrao)>{{ $c->nome }}</option>
                        @empty<option value="">Cadastre uma conta em Financeiro › Contas e saldos</option>@endforelse
                    </select>
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="ncp-dpag">Pago em</label>
                    <input type="date" name="data_pagamento" id="ncp-dpag" class="form-control" value="{{ old('data_pagamento', date('Y-m-d')) }}" max="{{ date('Y-m-d') }}">
                </div>
                <div class="col-12">
                    <label class="ncp-drop">
                        <input type="file" name="comprovante" accept="image/jpeg,image/png,image/webp,application/pdf">
                        <i class="bi bi-paperclip"></i>
                        <span class="ncp-drop-txt">Comprovante: arraste, cole (<kbd>Ctrl</kbd>+<kbd>V</kbd>) ou clique para escolher <small>(opcional)</small></span>
                    </label>
                </div>
            </div>
        </div>

        <div class="ncp-resumo" aria-live="polite"></div>
        <div class="ncp-erro text-danger small mt-2 {{ $errors->any() ? '' : 'd-none' }}" role="alert">{{ $errors->first() }}</div>
    </div>
    <div class="ncp-foot">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal" onclick="if(!this.closest('.modal')) history.back()">Cancelar</button>
        <button type="submit" class="btn ncp-btn">Salvar conta</button>
    </div>
</form>

@once
<style>
    .ncp-label { font-size: .875rem; font-weight: 500; margin-bottom: .4rem; }
    .ncp-cats { display: flex; flex-wrap: wrap; gap: .45rem; }
    .ncp-cats input { position: absolute; opacity: 0; pointer-events: none; }
    .ncp-cat { display: inline-flex; align-items: center; gap: .4rem; margin: 0; padding: .45rem .75rem; border-radius: 999px; border: 1.5px solid var(--lx-border); background: var(--lx-surface); color: var(--lx-text-2); font-size: .84rem; font-weight: 500; cursor: pointer; transition: border-color .12s, background .12s; user-select: none; }
    .ncp-cat i { font-size: 1rem; color: var(--lx-muted); }
    .ncp-cat:hover { border-color: var(--lx-border-strong); }
    .ncp-cats input:checked + .ncp-cat { border-color: var(--lx-gold); background: var(--lx-gold-soft); color: var(--lx-text); }
    .ncp-cats input:checked + .ncp-cat i { color: var(--lx-gold-strong); }
    .ncp-cats input:focus-visible + .ncp-cat { box-shadow: var(--lx-focus); }
    .ncp-cat-mais { border-style: dashed; }
    .ncp-valor input { font-weight: 700; font-size: 1.05rem; text-align: right; }
    .ncp-paga { display: flex; align-items: flex-start; gap: .6rem; padding: .7rem .8rem; border: 1.5px solid var(--lx-border); border-radius: 10px; cursor: pointer; width: 100%; margin-bottom: 0; }
    .ncp-paga .form-check-input { margin: .15rem 0 0 !important; float: none !important; position: static !important; flex: 0 0 auto; width: 1.05rem; height: 1.05rem; }
    .ncp-paga span { display: flex; flex-direction: column; line-height: 1.25; }
    .ncp-paga small { color: var(--lx-muted); font-size: .76rem; }
    .ncp-paga:has(input:checked) { border-color: var(--lx-danger); background: var(--lx-danger-soft); }
    .ncp-desp-sw.form-switch { padding-left: .8rem; }
    .ncp-desp-sw { display: flex; align-items: flex-start; gap: .6rem; padding: .7rem .8rem; border: 1.5px solid var(--lx-border); border-radius: 10px; cursor: pointer; width: 100%; margin: 0; }
    .ncp-desp-sw .form-check-input { margin: .15rem 0 0 !important; float: none !important; position: static !important; flex: 0 0 auto; width: 2rem; height: 1.05rem; border-radius: 1rem; }
    .ncp-desp-sw span { display: flex; flex-direction: column; line-height: 1.25; }
    .ncp-desp-sw small { color: var(--lx-muted); font-size: .76rem; }
    .ncp-desp-sw:has(input:checked) { border-color: var(--lx-warning); background: var(--lx-warning-soft); }
    .ncp-pag { margin-top: .75rem; padding: .85rem; border-radius: 10px; background: var(--lx-surface-2); border: 1px solid var(--lx-border); }
    .ncp-drop { display: flex; align-items: center; gap: .6rem; width: 100%; margin: 0; padding: .6rem .8rem; border: 2px dashed var(--lx-border-strong); border-radius: 10px; cursor: pointer; font-size: .84rem; color: var(--lx-text-2); background: var(--lx-surface); }
    .ncp-drop:hover, .ncp-drop.has-file, .ncp-drop.arrastando { border-color: var(--lx-gold); background: var(--lx-gold-soft); }
    .ncp-drop input { position: absolute; width: 1px; height: 1px; opacity: 0; }
    .ncp-drop i { font-size: 1.15rem; color: var(--lx-gold); }
    .ncp-drop kbd { font-size: .68rem; padding: 0 .3rem; background: var(--lx-surface-3); color: inherit; border: 1px solid var(--lx-border); box-shadow: none; }
    .ncp-resumo { margin-top: 1rem; padding: .65rem .8rem; border-radius: 8px; font-size: .88rem; background: var(--lx-surface-2); border: 1px solid var(--lx-border); }
    .ncp-resumo:empty { display: none; }
    .ncp-resumo b.sai { color: var(--lx-danger); }
    .ncp-foot { display: flex; justify-content: flex-end; gap: .5rem; padding: .85rem 1rem; border-top: 1px solid var(--lx-border); }
    .ncp-body { padding: 1rem; }
    .ncp-btn { min-width: 160px; font-weight: 600; background: var(--lx-primary); color: var(--lx-on-primary); border: 0; }
    .ncp-btn:hover { background: var(--lx-primary-hover); color: var(--lx-on-primary); }
    .ncp-btn.pagar { background: var(--lx-danger); color: #fff; }
    html.dark-theme .ncp-btn.pagar { color: #0b0b0c; }
</style>
<script>
document.querySelectorAll('form.ncp').forEach(function (f) {
    if (f.dataset.pronto) return; f.dataset.pronto = '1';
    var $ = function (s) { return f.querySelector(s); };
    var valor = $('[name=valor_integral]'), venc = $('[name=data_vencimento]'), rep = $('[name=repetir]'), ref = $('[name=referencia]');
    var paga = $('[name=paga]'), pag = $('.ncp-pag'), conta = $('[name=fin_conta_id]'), dpag = $('[name=data_pagamento]');
    var resumo = $('.ncp-resumo'), btn = $('.ncp-btn'), erro = $('.ncp-erro'), novaInp = $('.ncp-cat-nova-inp');
    var arq = $('[name=comprovante]'), drop = $('.ncp-drop'), dropTxt = $('.ncp-drop-txt'), dropOrig = dropTxt.innerHTML;
    var modal = f.closest('.modal');
    var desp = f.querySelector('input[type=checkbox][name=despesa]'), despTit = $('.ncp-desp-tit'), despDica = $('.ncp-desp-dica');
    function textoDespesa() {
        if (!desp) return;
        despTit.textContent = desp.checked ? 'Entra como despesa no fechamento do mês' : 'Não entra como despesa';
        despDica.textContent = desp.checked
            ? 'Diminui o lucro do mês (ex.: taxas, perfume quebrado, mídias).'
            : 'Só sai do caixa: é repasse (frete cobrado do cliente) ou vira custo do produto (compras, DIFAL).';
    }
    // escolher a categoria já marca o padrão dela
    f.querySelectorAll('[name=categoria_id]').forEach(function (r) {
        r.addEventListener('change', function () { if (desp && r.checked) { desp.checked = r.dataset.despesa === '1'; textoDespesa(); } });
    });
    if (desp) desp.addEventListener('change', textoDespesa);

    function num(v) { return window.luxDinheiro ? luxDinheiro.parse(v) : (parseFloat(String(v).replace(/\./g, '').replace(',', '.')) || 0); }
    function moeda(n) { return 'R$ ' + Number(n).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function dataBr(d) { var p = (d || '').split('-'); return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : ''; }
    function esc(t) { var s = document.createElement('span'); s.textContent = t; return s.innerHTML; }
    function catNome() {
        var r = f.querySelector('[name=categoria_id]:checked');
        if (!r) return '';
        if (r.id === 'ncp-cat-nova') return novaInp.value.trim();
        return f.querySelector('label[for="' + r.id + '"]').textContent.trim();
    }
    function mostrarErro(t) { erro.textContent = t || ''; erro.classList.toggle('d-none', !t); }

    function atualizar() {
        var v = num(valor.value), n = parseInt(rep.value || '0', 10), c = catNome(), pg = paga.checked;
        pag.classList.toggle('d-none', !pg);
        novaInp.classList.toggle('d-none', !(f.querySelector('#ncp-cat-nova') || {}).checked);
        btn.classList.toggle('pagar', pg);
        btn.textContent = pg ? 'Salvar e pagar' + (v > 0 ? ' ' + moeda(v) : '') : 'Salvar conta' + (v > 0 ? ' de ' + moeda(v) : '');
        if (!(v > 0)) { resumo.innerHTML = ''; return; }
        var t = 'Conta de <b>' + moeda(v) + '</b>' + (c ? ' em <b>' + esc(c) + '</b>' : '') + ', vence <b>' + dataBr(venc.value) + '</b>';
        if (n > 0) t += ', repete por mais ' + n + (n === 1 ? ' mês' : ' meses') + ' (total ' + moeda(v * (n + 1)) + ')';
        t += '.';
        if (desp) t += desp.checked ? ' Conta como <b>despesa</b> no fechamento.' : ' <b>Não</b> conta como despesa no fechamento.';
        if (pg && conta && conta.selectedOptions[0]) t += ' Vai <b class="sai">sair ' + moeda(v) + '</b> de <b>' + esc(conta.selectedOptions[0].text) + '</b> em ' + dataBr(dpag.value) + (n > 0 ? ' (só a primeira; as outras ficam em aberto)' : '') + '.';
        resumo.innerHTML = t;
    }
    f.addEventListener('change', atualizar);
    f.addEventListener('input', function (e) { if (e.target === valor || e.target === novaInp) atualizar(); });

    f.addEventListener('submit', function (e) {
        var r = f.querySelector('[name=categoria_id]:checked');
        if (!r || (r.id === 'ncp-cat-nova' && !novaInp.value.trim())) { e.preventDefault(); mostrarErro('Escolha a categoria.'); return; }
        if (!ref.value.trim()) { e.preventDefault(); mostrarErro('Escreva a descrição da conta.'); ref.focus(); return; }
        if (!(num(valor.value) > 0)) { e.preventDefault(); mostrarErro('Informe um valor maior que zero.'); valor.focus(); return; }
        if (!venc.value) { e.preventDefault(); mostrarErro('Informe o vencimento.'); venc.focus(); return; }
        if (paga.checked && (!conta || !conta.value)) { e.preventDefault(); mostrarErro('Escolha de qual conta saiu o dinheiro.'); return; }
        if (window.luxDinheiro) valor.value = luxDinheiro.format(num(valor.value));
        btn.disabled = true;
    });

    // comprovante: escolher, arrastar ou colar
    function usar(fl) {
        if (!fl) return;
        if (!/^(image\/(jpeg|png|webp)|application\/pdf)$/.test(fl.type)) { mostrarErro('Comprovante: envie imagem (JPG, PNG, WEBP) ou PDF.'); return; }
        try { var dt = new DataTransfer(); dt.items.add(fl); arq.files = dt.files; } catch (x) { return; }
        arq.dispatchEvent(new Event('change', { bubbles: true }));
    }
    arq.addEventListener('change', function () {
        var fl = arq.files && arq.files[0];
        drop.classList.toggle('has-file', !!fl);
        dropTxt.innerHTML = fl ? '<b>' + esc(fl.name) + '</b> · clique para trocar' : dropOrig;
    });
    drop.addEventListener('dragover', function (e) { e.preventDefault(); drop.classList.add('arrastando'); });
    drop.addEventListener('dragleave', function () { drop.classList.remove('arrastando'); });
    drop.addEventListener('drop', function (e) { e.preventDefault(); drop.classList.remove('arrastando'); usar(e.dataTransfer.files[0]); });
    document.addEventListener('paste', function (e) {
        var visivel = modal ? modal.classList.contains('show') : true;
        if (!visivel || !paga.checked || !e.clipboardData) return;
        var fl = null;
        Array.prototype.some.call(e.clipboardData.items || [], function (it) { if (it.kind === 'file') { fl = it.getAsFile(); return !!fl; } return false; });
        if (!fl) return;
        e.preventDefault();
        if (/^image\.\w+$/i.test(fl.name || 'image.png')) fl = new File([fl], 'comprovante-colado.' + (fl.type.split('/')[1] || 'png').replace('jpeg', 'jpg'), { type: fl.type });
        usar(fl);
    });

    if (modal) {
        modal.addEventListener('show.bs.modal', function () {
            if (f.dataset.manter) { delete f.dataset.manter; return; }
            f.reset(); mostrarErro(''); btn.disabled = false;
            drop.classList.remove('has-file'); dropTxt.innerHTML = dropOrig;
            textoDespesa();
            atualizar();
        });
        modal.addEventListener('shown.bs.modal', function () { var r = f.querySelector('[name=categoria_id]:checked'); (r ? valor : f.querySelector('.ncp-cat')).focus(); });
    }
    textoDespesa();
    atualizar();
});
</script>
@endonce
