{{-- Modais: nova/editar conta, lançamento avulso e transferência --}}
<div class="modal fade" id="modal-conta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" method="post" action="{{ route('fin.contas.salvar') }}">
        @csrf
        <input type="hidden" name="id" id="fc-id">
        <div class="modal-header"><h5 class="modal-title" id="fc-titulo">Nova conta</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
            <div class="col-12"><label class="form-label">Nome</label><input name="nome" id="fc-nome" class="form-control" maxlength="80" required placeholder="Ex.: Itaú (fiscal), Conta 2, Caixa da loja"></div>
            <div class="col-6"><label class="form-label">Tipo</label>
                <select name="tipo" id="fc-tipo" class="form-select">@foreach(App\Models\FinConta::TIPOS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            <div class="col-6"><label class="form-label">Saldo inicial</label><div class="input-group"><span class="input-group-text">R$</span><input name="saldo_inicial" id="fc-saldo" class="form-control lux-dinheiro" placeholder="0,00"></div></div>
            <div class="col-6"><label class="form-label">Saldo inicial em</label><input type="date" name="data_saldo_inicial" id="fc-data" class="form-control" value="{{ date('Y-m-d') }}"></div>
            <div class="col-6 d-flex flex-column justify-content-end gap-1">
                <label class="form-check-label"><input type="checkbox" class="form-check-input" name="padrao" id="fc-padrao" value="1"> Conta padrão nos pagamentos</label>
                <label class="form-check-label" id="fc-ativo-wrap" style="display:none"><input type="checkbox" class="form-check-input" name="ativo" id="fc-ativo" value="1" checked> Ativa</label>
            </div>
            <div class="col-12 form-text">Dica: use como saldo inicial o saldo real da conta no dia em que começar a usar o extrato.</div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Salvar</button></div>
    </form></div>
</div>

<div class="modal fade" id="modal-lancar" tabindex="-1" aria-hidden="true" aria-labelledby="fl-titulo">
    <div class="modal-dialog"><form class="modal-content fl" method="post" action="{{ route('fin.lancar') }}" enctype="multipart/form-data" novalidate>
        @csrf
        <div class="modal-header"><h5 class="modal-title" id="fl-titulo">Novo lançamento</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
        <div class="modal-body">
            <div class="fl-tipo" role="radiogroup" aria-label="Tipo de lançamento">
                <input type="radio" name="tipo" id="fl-saida" value="saida" checked>
                <label for="fl-saida" class="fl-op saida"><i class="bi bi-arrow-up-right"></i><span><b>Saída</b><small>dinheiro saiu da conta</small></span></label>
                <input type="radio" name="tipo" id="fl-entrada" value="entrada">
                <label for="fl-entrada" class="fl-op entrada"><i class="bi bi-arrow-down-left"></i><span><b>Entrada</b><small>dinheiro entrou na conta</small></span></label>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-sm-6">
                    <label class="form-label required" for="fl-conta">Conta</label>
                    <select name="conta_id" id="fl-conta" class="form-select" required>@foreach($contas->where('ativo', true) as $c)<option value="{{ $c->id }}" @selected($c->padrao)>{{ $c->nome }}</option>@endforeach</select>
                </div>
                <div class="col-sm-6">
                    <label class="form-label required" for="fl-valor">Valor</label>
                    <div class="input-group fl-valor"><span class="input-group-text">R$</span><input name="valor" id="fl-valor" class="form-control lux-dinheiro" required placeholder="0,00"></div>
                </div>
                <div class="col-sm-6">
                    <label class="form-label required" for="fl-data">Data</label>
                    <input type="date" name="data" id="fl-data" class="form-control" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d', strtotime('+1 year')) }}" required>
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="fl-cat">Categoria</label>
                    <select name="categoria_id" id="fl-cat" class="form-select">
                        <option value="">Sem categoria</option>
                        @foreach($categorias as $cat)<option value="{{ $cat->id }}" data-tipo="{{ $cat->tipo }}">{{ $cat->nome }}</option>@endforeach
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label required" for="fl-desc">Descrição</label>
                    <input name="descricao" id="fl-desc" class="form-control" maxlength="200" required placeholder="Ex.: Retirada do sócio, combustível, tarifa bancária…">
                </div>
                <div class="col-12">
                    <label class="form-label">Comprovante <span class="text-muted fw-normal">(opcional)</span></label>
                    <label class="fl-drop">
                        <input type="file" name="comprovante" accept="image/jpeg,image/png,image/webp,application/pdf">
                        <i class="bi bi-paperclip"></i>
                        <span class="fl-drop-txt">Arraste, cole (<kbd>Ctrl</kbd>+<kbd>V</kbd>) ou clique para escolher</span>
                    </label>
                </div>
                <div class="col-12">
                    <label class="fl-ajuste mb-0">
                        <input type="checkbox" class="form-check-input" name="ajuste" value="1">
                        <span>Acerto de saldo <small class="text-muted d-block">Use só para bater o saldo com o extrato do banco. Não conta como receita nem despesa.</small></span>
                    </label>
                </div>
            </div>
            <div class="fl-resumo" aria-live="polite"></div>
            <div class="fl-erro text-danger small mt-2 d-none" role="alert"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn fl-btn saida">Lançar saída</button>
        </div>
    </form></div>
</div>

<style>
    .fl-tipo { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; }
    .fl-tipo input { position: absolute; opacity: 0; pointer-events: none; }
    .fl-op { display: flex; align-items: center; gap: .6rem; padding: .65rem .8rem; border: 2px solid var(--lx-border); border-radius: 10px; cursor: pointer; margin: 0; color: var(--lx-text-2); transition: border-color .12s, background .12s; }
    .fl-op i { font-size: 1.3rem; }
    .fl-op span { display: flex; flex-direction: column; line-height: 1.2; }
    .fl-op b { font-size: .95rem; }
    .fl-op small { font-size: .72rem; color: var(--lx-muted); }
    .fl-op:hover { border-color: var(--lx-border-strong); }
    #fl-saida:checked + .fl-op { border-color: var(--lx-danger); background: var(--lx-danger-soft); color: var(--lx-danger); }
    #fl-entrada:checked + .fl-op { border-color: var(--lx-success); background: var(--lx-success-soft); color: var(--lx-success); }
    .fl-tipo input:focus-visible + .fl-op { box-shadow: var(--lx-focus); }
    .fl-valor input { font-weight: 700; font-size: 1.05rem; text-align: right; }
    .fl-drop { display: flex; align-items: center; gap: .6rem; width: 100%; margin: 0; padding: .6rem .8rem; border: 2px dashed var(--lx-border-strong); border-radius: 10px; cursor: pointer; font-size: .85rem; color: var(--lx-text-2); }
    .fl-drop:hover, .fl-drop.has-file, .fl-drop.arrastando { border-color: var(--lx-gold); background: var(--lx-gold-soft); }
    .fl-drop input { position: absolute; width: 1px; height: 1px; opacity: 0; }
    .fl-drop i { font-size: 1.15rem; color: var(--lx-gold); }
    .fl-drop kbd { font-size: .68rem; padding: 0 .3rem; background: var(--lx-surface-3); color: inherit; border: 1px solid var(--lx-border); box-shadow: none; }
    .fl-ajuste { display: flex; gap: .6rem; align-items: flex-start; cursor: pointer; font-size: .9rem; }
    .fl-ajuste .form-check-input { margin: .2rem 0 0 !important; float: none !important; position: static !important; flex: 0 0 auto; align-self: flex-start !important; width: 1rem; height: 1rem; }
    .fl-resumo { margin-top: 1rem; padding: .65rem .8rem; border-radius: 8px; font-size: .88rem; background: var(--lx-surface-2); border: 1px solid var(--lx-border); }
    .fl-resumo:empty { display: none; }
    .fl-resumo b.saida { color: var(--lx-danger); }
    .fl-resumo b.entrada { color: var(--lx-success); }
    .fl-btn { color: #fff; border: 0; min-width: 150px; font-weight: 600; }
    .fl-btn.saida { background: var(--lx-danger); }
    .fl-btn.entrada { background: var(--lx-success); }
    .fl-btn:hover { color: #fff; filter: brightness(1.08); }
    html.dark-theme .fl-btn, html.dark-theme .fl-btn:hover { color: #0b0b0c; }
</style>

<script>
(function () {
    var m = document.getElementById('modal-lancar');
    if (!m) return;
    var f = m.querySelector('form');
    var conta = f.querySelector('[name=conta_id]'), valor = f.querySelector('[name=valor]'), data = f.querySelector('[name=data]');
    var cat = f.querySelector('[name=categoria_id]'), desc = f.querySelector('[name=descricao]');
    var resumo = m.querySelector('.fl-resumo'), btn = m.querySelector('.fl-btn'), erro = m.querySelector('.fl-erro');
    var arq = f.querySelector('[name=comprovante]'), drop = m.querySelector('.fl-drop'), dropTxt = m.querySelector('.fl-drop-txt'), dropTxtOrig = dropTxt.innerHTML;
    var ajuste = f.querySelector('[name=ajuste]');

    function tipo() { return f.querySelector('[name=tipo]:checked').value; }
    function num(v) { return window.luxDinheiro ? luxDinheiro.parse(v) : parseFloat(String(v).replace(/\./g, '').replace(',', '.')) || 0; }
    function moeda(n) { return 'R$ ' + Number(n).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function dataBr(d) { var p = (d || '').split('-'); return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : ''; }
    function esc(t) { var d = document.createElement('span'); d.textContent = t; return d.innerHTML; }

    function filtrarCategorias() {
        var quer = tipo() === 'entrada' ? 'receber' : 'pagar';
        Array.prototype.forEach.call(cat.options, function (o) {
            if (!o.dataset.tipo) return;
            var ok = o.dataset.tipo === quer;
            o.hidden = !ok; o.disabled = !ok;
        });
        if (cat.selectedOptions[0] && cat.selectedOptions[0].disabled) cat.value = '';
    }
    function atualizar() {
        var t = tipo(), v = num(valor.value), nomeConta = conta.selectedOptions[0] ? conta.selectedOptions[0].text : '';
        btn.classList.toggle('saida', t === 'saida');
        btn.classList.toggle('entrada', t === 'entrada');
        btn.textContent = (t === 'saida' ? 'Lançar saída' : 'Lançar entrada') + (v > 0 ? ' de ' + moeda(v) : '');
        resumo.innerHTML = v > 0
            ? (t === 'saida' ? 'Vai <b class="saida">sair ' + moeda(v) + '</b> de ' : 'Vai <b class="entrada">entrar ' + moeda(v) + '</b> em ')
              + '<b>' + esc(nomeConta) + '</b> no dia ' + dataBr(data.value) + (ajuste.checked ? ' (acerto de saldo)' : '') + '.'
            : '';
    }
    function mostrarErro(t) { erro.textContent = t || ''; erro.classList.toggle('d-none', !t); }

    f.querySelectorAll('[name=tipo]').forEach(function (r) { r.addEventListener('change', function () { filtrarCategorias(); atualizar(); }); });
    [conta, data, ajuste].forEach(function (el) { el.addEventListener('change', atualizar); });
    valor.addEventListener('input', atualizar);
    valor.addEventListener('change', atualizar);

    // abre já na conta do extrato e limpa o formulário
    m.addEventListener('show.bs.modal', function (e) {
        f.reset();
        mostrarErro('');
        drop.classList.remove('has-file'); dropTxt.innerHTML = dropTxtOrig;
        var b = e.relatedTarget;
        if (b && b.dataset.conta) conta.value = b.dataset.conta;
        filtrarCategorias(); atualizar();
    });
    m.addEventListener('shown.bs.modal', function () { valor.focus(); });

    f.addEventListener('submit', function (e) {
        var v = num(valor.value);
        if (!(v > 0)) { e.preventDefault(); mostrarErro('Informe um valor maior que zero.'); valor.focus(); return; }
        if (!desc.value.trim()) { e.preventDefault(); mostrarErro('Escreva uma descrição para lembrar do que se trata.'); desc.focus(); return; }
        if (!data.value) { e.preventDefault(); mostrarErro('Informe a data.'); data.focus(); return; }
        valor.value = luxDinheiro ? luxDinheiro.format(v) : valor.value;
        btn.disabled = true;
    });

    // comprovante: escolher, arrastar ou colar
    function usar(file) {
        if (!file) return;
        if (!/^(image\/(jpeg|png|webp)|application\/pdf)$/.test(file.type)) { mostrarErro('Comprovante: envie imagem (JPG, PNG, WEBP) ou PDF.'); return; }
        try { var dt = new DataTransfer(); dt.items.add(file); arq.files = dt.files; } catch (x) { return; }
        arq.dispatchEvent(new Event('change'));
    }
    arq.addEventListener('change', function () {
        var fl = arq.files && arq.files[0];
        drop.classList.toggle('has-file', !!fl);
        dropTxt.innerHTML = fl ? '<b>' + esc(fl.name) + '</b> · clique para trocar' : dropTxtOrig;
        mostrarErro('');
    });
    drop.addEventListener('dragover', function (e) { e.preventDefault(); drop.classList.add('arrastando'); });
    drop.addEventListener('dragleave', function () { drop.classList.remove('arrastando'); });
    drop.addEventListener('drop', function (e) { e.preventDefault(); drop.classList.remove('arrastando'); usar(e.dataTransfer.files[0]); });
    document.addEventListener('paste', function (e) {
        if (!m.classList.contains('show') || !e.clipboardData) return;
        var fl = null;
        Array.prototype.some.call(e.clipboardData.items || [], function (it) { if (it.kind === 'file') { fl = it.getAsFile(); return !!fl; } return false; });
        if (!fl) return;
        e.preventDefault();
        if (/^image\.\w+$/i.test(fl.name || 'image.png')) fl = new File([fl], 'comprovante-colado.' + (fl.type.split('/')[1] || 'png').replace('jpeg', 'jpg'), { type: fl.type });
        usar(fl);
    });
})();
</script>

<div class="modal fade" id="modal-transferir" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" method="post" action="{{ route('fin.transferir') }}">
        @csrf
        <div class="modal-header"><h5 class="modal-title">Transferir entre contas</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
            <div class="col-6"><label class="form-label">De</label><select name="de" class="form-select" required>@foreach($contas->where('ativo', true) as $c)<option value="{{ $c->id }}">{{ $c->nome }}</option>@endforeach</select></div>
            <div class="col-6"><label class="form-label">Para</label><select name="para" class="form-select" required>@foreach($contas->where('ativo', true) as $i => $c)<option value="{{ $c->id }}" @selected($loop->index === 1)>{{ $c->nome }}</option>@endforeach</select></div>
            <div class="col-6"><label class="form-label required">Valor</label><div class="input-group"><span class="input-group-text">R$</span><input name="valor" class="form-control lux-dinheiro" required></div></div>
            <div class="col-6"><label class="form-label">Data</label><input type="date" name="data" class="form-control" value="{{ date('Y-m-d') }}" required></div>
            <div class="col-12"><label class="form-label">Observação</label><input name="obs" class="form-control" maxlength="120" placeholder="Ex.: depósito do dinheiro do caixa"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Transferir</button></div>
    </form></div>
</div>
