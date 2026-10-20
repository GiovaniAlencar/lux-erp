{{--
  Comprovantes de pagamento da venda.
  Abra com qualquer botão que tenha data-lux-comprovantes="ID_DA_VENDA".
  Contadores com data-lux-comp-count="ID_DA_VENDA" são atualizados sozinhos.
--}}
<div class="modal fade" id="modal-lux-comprovantes" tabindex="-1" aria-labelledby="lux-comp-titulo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content lux-comp">
            <div class="lux-comp-soltar" aria-hidden="true"><i class="bi bi-cloud-arrow-down"></i>Solte para anexar o comprovante</div>
            <div class="modal-header">
                <h5 class="modal-title" id="lux-comp-titulo">Comprovantes · Venda <span class="lux-comp-num"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="lux-comp-resumo">
                    <div><span>Total da venda</span><strong class="lux-comp-total">—</strong></div>
                    <div><span>Comprovado</span><strong class="lux-comp-comprovado">—</strong></div>
                    <div class="lux-comp-falta-box"><span>Falta</span><strong class="lux-comp-falta">—</strong></div>
                </div>

                <div class="lux-comp-lista" aria-live="polite"></div>

                <form class="lux-comp-form" enctype="multipart/form-data" novalidate>
                    <h6 class="lux-comp-form-tit">Anexar comprovante</h6>
                    <label class="lux-comp-drop">
                        <input type="file" name="arquivo" accept="image/jpeg,image/png,image/webp,application/pdf" required>
                        <i class="bi bi-cloud-arrow-up" aria-hidden="true"></i>
                        <img class="lux-comp-prev d-none" alt="">
                        <span class="lux-comp-drop-txt"><span class="lux-comp-so-pc">Arraste o arquivo aqui, cole com <kbd>Ctrl</kbd>+<kbd>V</kbd> ou clique para escolher</span><span class="lux-comp-so-toque">Toque para escolher a foto ou o PDF</span><br><small>JPG, PNG, WEBP ou PDF · até 8 MB</small></span>
                    </label>
                    <div class="row g-2 mt-1">
                        <div class="col-sm-4">
                            <label class="form-label" for="lux-comp-conta">Pago na conta</label>
                            <select class="form-select" name="conta" id="lux-comp-conta">
                                <option value="fiscal">{{ config('lux.conta_fiscal') }}</option>
                                <option value="nao_fiscal">{{ config('lux.conta_nao_fiscal') }}</option>
                                <option value="outra">Outra (dinheiro, cartão…)</option>
                            </select>
                        </div>
                        <div class="col-6 col-sm-2">
                            <label class="form-label" for="lux-comp-valor">Valor</label>
                            <input type="text" inputmode="decimal" class="form-control lux-dinheiro" name="valor" id="lux-comp-valor" placeholder="0,00" autocomplete="off">
                        </div>
                        <div class="col-6 col-sm-3">
                            <label class="form-label" for="lux-comp-data">Pago em</label>
                            <input type="date" class="form-control" name="data_pagamento" id="lux-comp-data" max="{{ date('Y-m-d') }}" value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-sm-3">
                            <label class="form-label" for="lux-comp-obs">Observação</label>
                            <input type="text" class="form-control" name="observacao" id="lux-comp-obs" maxlength="255" placeholder="Opcional">
                        </div>
                    </div>
                    <div class="lux-comp-dica small text-muted mt-2 d-none"><i class="bi bi-bank"></i> Com valor, entra como <b>recebimento</b> no extrato da conta escolhida (Financeiro).</div>
                    <div class="lux-comp-erro text-danger small mt-2 d-none" role="alert"></div>
                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-primary lux-comp-enviar"><i class="bi bi-paperclip"></i> Anexar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .lux-comp-resumo { display: grid; grid-template-columns: repeat(3, 1fr); gap: .5rem; margin-bottom: 1rem; }
    .lux-comp-resumo > div { border: 1px solid var(--lx-border, #e5e7eb); border-radius: 10px; padding: .55rem .75rem; display: flex; flex-direction: column; }
    .lux-comp-resumo span { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; opacity: .7; }
    .lux-comp-resumo strong { font-size: 1.05rem; }
    .lux-comp-falta-box.is-falta { border-color: #f5c26b; background: rgba(245, 166, 35, .1); }
    .lux-comp-falta-box.is-falta strong { color: #b26a00; }
    .lux-comp-falta-box.is-ok { border-color: #9fd5ab; background: rgba(34, 197, 94, .1); }
    .lux-comp-falta-box.is-ok strong { color: #1d7a3a; }
    html.dark-theme .lux-comp-falta-box.is-falta strong { color: #f6c56f; }
    html.dark-theme .lux-comp-falta-box.is-ok strong { color: #86e0a5; }
    .lux-comp-lista { display: flex; flex-direction: column; gap: .5rem; margin-bottom: 1rem; }
    .lux-comp-vazio { text-align: center; padding: 1rem; font-size: .875rem; opacity: .7; border: 1px dashed var(--lx-border, #d1d5db); border-radius: 10px; }
    .lux-comp-item { display: flex; gap: .75rem; align-items: center; border: 1px solid var(--lx-border, #e5e7eb); border-radius: 10px; padding: .5rem; }
    .lux-comp-thumb { width: 56px; height: 56px; flex: 0 0 56px; border-radius: 8px; overflow: hidden; display: flex; align-items: center; justify-content: center; background: rgba(127, 127, 127, .12); font-size: 1.5rem; color: inherit; text-decoration: none; }
    .lux-comp-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .lux-comp-info { flex: 1; min-width: 0; font-size: .85rem; line-height: 1.35; }
    .lux-comp-info .lux-comp-l1 { font-weight: 600; }
    .lux-comp-fin { display: inline-flex; align-items: center; gap: .2rem; font-size: .68rem; font-weight: 600; padding: 0 .4rem; border-radius: 999px; background: rgba(34, 197, 94, .12); color: #15803d; margin-left: .25rem; vertical-align: 1px; }
    html.dark-theme .lux-comp-fin { color: #86e0a5; }
    .lux-comp-info .lux-comp-l2 { opacity: .7; font-size: .78rem; }
    .lux-comp-acoes { display: flex; gap: .25rem; }
    .lux-comp-form { border-top: 1px solid var(--lx-border, #e5e7eb); padding-top: 1rem; }
    .lux-comp-form-tit { font-size: .8rem; text-transform: uppercase; letter-spacing: .05em; margin-bottom: .5rem; }
    .lux-comp-drop { display: flex; align-items: center; gap: .75rem; border: 2px dashed var(--lx-border, #d1d5db); border-radius: 10px; padding: .85rem 1rem; cursor: pointer; margin: 0; width: 100%; }
    .lux-comp-drop:hover, .lux-comp-drop.has-file { border-color: #c9a227; }
    .lux-comp-prev { width: 48px; height: 48px; object-fit: cover; border-radius: 6px; flex: 0 0 48px; border: 1px solid var(--lx-border, #e5e7eb); }
    .lux-comp-drop kbd { font-size: .7rem; padding: .05rem .3rem; border-radius: 4px; background: var(--lx-surface-3, #eee); color: inherit; border: 1px solid var(--lx-border, #ddd); box-shadow: none; }
    .lux-comp-so-toque { display: none; }
    @media (hover: none) and (pointer: coarse) { .lux-comp-so-pc { display: none; } .lux-comp-so-toque { display: inline; } }
    #modal-lux-comprovantes .modal-content { position: relative; }
    .lux-comp-soltar { position: absolute; inset: 0; z-index: 5; display: none; align-items: center; justify-content: center; flex-direction: column; gap: .5rem;
        border: 3px dashed #c9a227; border-radius: inherit; background: rgba(201, 162, 77, .14); backdrop-filter: blur(2px); font-weight: 600; font-size: 1.05rem; pointer-events: none; }
    .lux-comp-soltar i { font-size: 2.2rem; color: #c9a227; }
    #modal-lux-comprovantes.arrastando .lux-comp-soltar { display: flex; }
    .lux-comp-drop input { position: absolute; width: 1px; height: 1px; opacity: 0; }
    .lux-comp-drop i { font-size: 1.6rem; color: #c9a227; }
    .lux-comp-drop-txt { font-size: .85rem; line-height: 1.3; }
    .lux-comp-drop-txt small { opacity: .65; }
    @media (max-width: 575.98px) {
        .lux-comp-resumo { grid-template-columns: 1fr 1fr; }
        .lux-comp-resumo > div:first-child { grid-column: 1 / -1; }
    }
</style>

<script>
(function () {
    var CSRF = @json(csrf_token());
    var URL_LISTAR = @json(url('/vendas-comprovantes/venda')) + '/';
    var URL_ITEM = @json(url('/vendas-comprovantes')) + '/';
    var modalEl = document.getElementById('modal-lux-comprovantes');
    if (!modalEl || modalEl.dataset.pronto) return;
    modalEl.dataset.pronto = '1';
    var vendaAtual = null;
    var form = modalEl.querySelector('.lux-comp-form');
    var drop = modalEl.querySelector('.lux-comp-drop');
    var dropTxt = modalEl.querySelector('.lux-comp-drop-txt');
    var dropTxtOriginal = dropTxt.innerHTML;
    var erro = modalEl.querySelector('.lux-comp-erro');
    var btnEnviar = modalEl.querySelector('.lux-comp-enviar');

    function esc(t) { return String(t == null ? '' : t).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function moeda(n) { return 'R$ ' + Number(n || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function num(n) { return Number(n || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

    function mostrarErro(msg) { erro.textContent = msg || ''; erro.classList.toggle('d-none', !msg); }

    function abrirModal() {
        if (window.bootstrap && bootstrap.Modal) { bootstrap.Modal.getOrCreateInstance(modalEl).show(); }
        else if (window.jQuery) { jQuery(modalEl).modal('show'); }
    }

    function render(d) {
        modalEl.querySelector('.lux-comp-num').textContent = '#' + d.venda;
        modalEl.querySelector('.lux-comp-total').textContent = moeda(d.total);
        modalEl.querySelector('.lux-comp-comprovado').textContent = moeda(d.comprovado);
        var fbox = modalEl.querySelector('.lux-comp-falta-box');
        modalEl.querySelector('.lux-comp-falta').textContent = d.falta > 0.009 ? moeda(d.falta) : 'Nada';
        fbox.classList.toggle('is-falta', d.falta > 0.009 && d.itens.length > 0);
        fbox.classList.toggle('is-ok', d.falta <= 0.009);

        var lista = modalEl.querySelector('.lux-comp-lista');
        if (!d.itens.length) {
            lista.innerHTML = '<div class="lux-comp-vazio">Nenhum comprovante anexado ainda.</div>';
        } else {
            lista.innerHTML = d.itens.map(function (c) {
                var thumb = c.pdf
                    ? '<i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>'
                    : '<img src="' + esc(c.url) + '" alt="" loading="lazy">';
                return '<div class="lux-comp-item">' +
                    '<a class="lux-comp-thumb" href="' + esc(c.url) + '" target="_blank" rel="noopener" title="Abrir comprovante">' + thumb + '</a>' +
                    '<div class="lux-comp-info">' +
                        '<div class="lux-comp-l1">' + (c.valor ? moeda(c.valor) + ' · ' : '') + esc(c.conta) + (c.no_financeiro ? '<span class="lux-comp-fin" title="Lançado como recebimento no extrato"><i class="bi bi-check2"></i> no financeiro</span>' : '') + '</div>' +
                        (c.data ? '<div class="lux-comp-l2">pago em ' + esc(c.data) + '</div>' : '') +
                        (c.observacao ? '<div>' + esc(c.observacao) + '</div>' : '') +
                        '<div class="lux-comp-l2">' + esc(c.por || '—') + ' · ' + esc(c.em || '') + '</div>' +
                    '</div>' +
                    '<div class="lux-comp-acoes">' +
                        '<a class="btn btn-sm btn-outline-secondary" href="' + esc(c.url) + '" target="_blank" rel="noopener" title="Abrir"><i class="bi bi-box-arrow-up-right"></i></a>' +
                        (c.pode_excluir ? '<button type="button" class="btn btn-sm btn-outline-danger lux-comp-del" data-id="' + c.id + '" title="Excluir"><i class="bi bi-trash"></i></button>' : '') +
                    '</div>' +
                '</div>';
            }).join('');
        }
        // valor sugerido = o que falta
        // contas do Financeiro (quando existem) no lugar de Fiscal / Conta 2 / Outra
        var selConta = form.querySelector('[name=conta]');
        if (d.contas && d.contas.length) {
            var atual = selConta.value;
            if (!selConta.dataset.fin) {
                selConta.innerHTML = d.contas.map(function (c) {
                    return '<option value="' + c.id + '"' + (c.padrao ? ' data-padrao="1"' : '') + '>' + esc(c.nome) + '</option>';
                }).join('');
                selConta.dataset.fin = '1';
                var pad = selConta.querySelector('[data-padrao]');
                if (pad) selConta.value = pad.value;
            } else if (atual) {
                selConta.value = atual;
            }
            modalEl.querySelector('.lux-comp-dica').classList.remove('d-none');
        }
        var inpValor = form.querySelector('[name=valor]');
        // só sugere o "falta" se a pessoa ainda não digitou um valor
        if (!inpValor.dataset.editado) inpValor.value = d.falta > 0.009 ? num(d.falta) : '';

        document.querySelectorAll('[data-lux-comp-pago="' + d.venda + '"]').forEach(function (el) {
            el.classList.toggle('d-none', d.status_pagamento !== 'pago');
        });
        document.querySelectorAll('[data-lux-comp-count="' + d.venda + '"]').forEach(function (el) {
            el.textContent = d.itens.length ? String(d.itens.length) : '';
            el.classList.toggle('d-none', !d.itens.length);
        });
    }

    function carregar(id) {
        vendaAtual = id;
        modalEl.querySelector('.lux-comp-lista').innerHTML = '<div class="lux-comp-vazio">Carregando…</div>';
        return fetch(URL_LISTAR + encodeURIComponent(id), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { if (!r.ok) throw new Error('Não foi possível carregar os comprovantes.'); return r.json(); })
            .then(render)
            .catch(function (e) { modalEl.querySelector('.lux-comp-lista').innerHTML = '<div class="lux-comp-vazio text-danger">' + esc(e.message) + '</div>'; });
    }

    function limparForm() {
        var contaEscolhida = form.querySelector('[name=conta]').value;
        form.reset();
        if (contaEscolhida) form.querySelector('[name=conta]').value = contaEscolhida; // mantém a conta
        delete form.querySelector('[name=valor]').dataset.editado;
        if (typeof mostrarPrevia === 'function') mostrarPrevia(null);
        drop.classList.remove('has-file');
        dropTxt.innerHTML = dropTxtOriginal;
        mostrarErro('');
    }

    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-lux-comprovantes]');
        if (!b) return;
        e.preventDefault();
        limparForm();
        carregar(b.getAttribute('data-lux-comprovantes'));
        abrirModal();
    });

    form.querySelector('[name=valor]').addEventListener('input', function () { this.dataset.editado = '1'; });
    var inpArq = form.querySelector('[name=arquivo]');
    var prev = modalEl.querySelector('.lux-comp-prev');
    var prevUrl = null;
    function mostrarPrevia(f) {
        if (prevUrl) { URL.revokeObjectURL(prevUrl); prevUrl = null; }
        if (f && /^image\//.test(f.type)) {
            prevUrl = URL.createObjectURL(f);
            prev.src = prevUrl; prev.classList.remove('d-none');
        } else {
            prev.removeAttribute('src'); prev.classList.add('d-none');
        }
    }
    inpArq.addEventListener('change', function () {
        var f = this.files && this.files[0];
        drop.classList.toggle('has-file', !!f);
        dropTxt.innerHTML = f ? '<strong>' + esc(f.name) + '</strong><br><small>' + (f.size / 1024 / 1024).toFixed(2).replace('.', ',') + ' MB · clique ou cole outro para trocar</small>' : dropTxtOriginal;
        mostrarPrevia(f);
        if (f && f.type === 'application/pdf' && f.size > 8 * 1024 * 1024) mostrarErro('O PDF pode ter no máximo 8 MB.'); else mostrarErro('');
    });

    // arrastar e soltar / colar (Ctrl+V): coloca o arquivo no campo como se tivesse escolhido
    var TIPOS_OK = /^(image\/(jpeg|png|webp)|application\/pdf)$/;
    function usarArquivo(f) {
        if (!f) return false;
        if (!TIPOS_OK.test(f.type)) { mostrarErro('Envie uma imagem (JPG, PNG, WEBP) ou PDF.'); return false; }
        if (!f.name || /^image\.\w+$/i.test(f.name)) {
            var ext = f.type === 'application/pdf' ? 'pdf' : f.type.split('/')[1].replace('jpeg', 'jpg');
            var d = new Date(), pad = function (n) { return String(n).padStart(2, '0'); };
            f = new File([f], 'comprovante-colado-' + d.getFullYear() + pad(d.getMonth() + 1) + pad(d.getDate()) + '-' + pad(d.getHours()) + pad(d.getMinutes()) + pad(d.getSeconds()) + '.' + ext, { type: f.type });
        }
        try {
            var dt = new DataTransfer();
            dt.items.add(f);
            inpArq.files = dt.files;
        } catch (e) { mostrarErro('Seu navegador não deixa colar arquivos aqui. Use o botão para escolher.'); return false; }
        inpArq.dispatchEvent(new Event('change', { bubbles: true }));
        var v = form.querySelector('[name=valor]');
        if (v) setTimeout(function () { v.focus(); v.select(); }, 50);
        return true;
    }
    function aberto() { return modalEl.classList.contains('show'); }
    function temArquivo(e) { return e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types || [], 'Files') >= 0; }
    var contaArraste = 0;
    modalEl.addEventListener('dragenter', function (e) { if (!temArquivo(e)) return; e.preventDefault(); contaArraste++; modalEl.classList.add('arrastando'); });
    modalEl.addEventListener('dragover', function (e) { if (!temArquivo(e)) return; e.preventDefault(); e.dataTransfer.dropEffect = 'copy'; });
    modalEl.addEventListener('dragleave', function (e) { if (!temArquivo(e)) return; contaArraste = Math.max(0, contaArraste - 1); if (!contaArraste) modalEl.classList.remove('arrastando'); });
    modalEl.addEventListener('drop', function (e) {
        if (!temArquivo(e)) return;
        e.preventDefault();
        contaArraste = 0; modalEl.classList.remove('arrastando');
        usarArquivo(e.dataTransfer.files[0]);
    });
    // com a janela aberta, soltar fora dela não abre o arquivo no navegador
    window.addEventListener('dragover', function (e) { if (aberto() && temArquivo(e)) e.preventDefault(); });
    window.addEventListener('drop', function (e) { if (aberto() && temArquivo(e)) e.preventDefault(); });
    document.addEventListener('paste', function (e) {
        if (!aberto() || !e.clipboardData) return;
        var arq = null;
        Array.prototype.some.call(e.clipboardData.items || [], function (it) {
            if (it.kind === 'file') { arq = it.getAsFile(); return !!arq; }
            return false;
        });
        if (!arq && e.clipboardData.files && e.clipboardData.files.length) arq = e.clipboardData.files[0];
        if (!arq) return; // texto: cola normal no campo
        e.preventDefault();
        if (usarArquivo(arq) && window.toastr) toastr.info('Comprovante colado. Confira o valor e clique em Anexar.');
    });

    // foto de celular: reduz para no máximo 1600px (JPEG) antes de enviar — fica leve e legível
    function prepararArquivo(f) {
        if (!/^image\/(jpeg|png|webp)$/.test(f.type) || f.size < 700 * 1024 || !window.createImageBitmap) return Promise.resolve(f);
        return createImageBitmap(f).then(function (img) {
            var max = 1600, esc = Math.min(1, max / Math.max(img.width, img.height));
            var c = document.createElement('canvas');
            c.width = Math.round(img.width * esc); c.height = Math.round(img.height * esc);
            var ctx = c.getContext('2d');
            ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, c.width, c.height);
            ctx.drawImage(img, 0, 0, c.width, c.height);
            return new Promise(function (ok) { c.toBlob(ok, 'image/jpeg', 0.82); });
        }).then(function (blob) {
            if (!blob || blob.size >= f.size) return f;
            return new File([blob], f.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' });
        }).catch(function () { return f; });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var f = form.querySelector('[name=arquivo]').files[0];
        if (!f) { mostrarErro('Escolha a foto ou o PDF do comprovante.'); return; }
        mostrarErro('');
        btnEnviar.disabled = true;
        var htmlBtn = btnEnviar.innerHTML;
        btnEnviar.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enviando…';
        prepararArquivo(f).then(function (arq) {
            if (arq.size > 8 * 1024 * 1024) throw new Error('O arquivo pode ter no máximo 8 MB.');
            var fd = new FormData(form);
            fd.set('arquivo', arq, arq.name);
            return fetch(URL_LISTAR + encodeURIComponent(vendaAtual), {
            method: 'POST', body: fd, credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' }
            });
        })
            .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { return { ok: r.ok, status: r.status, j: j }; }); })
            .then(function (x) {
                if (!x.ok) {
                    var msg = x.j.message || 'Não foi possível anexar.';
                    if (x.j.errors) { var k = Object.keys(x.j.errors)[0]; if (k) msg = x.j.errors[k][0]; }
                    if (x.status === 413) msg = 'Arquivo grande demais para o servidor.';
                    throw new Error(msg);
                }
                limparForm();
                modalEl.dataset.mudou = '1';
                render(x.j);
                if (window.toastr) toastr.success('Comprovante anexado.');
            })
            .catch(function (err) { mostrarErro(err.message); })
            .finally(function () { btnEnviar.disabled = false; btnEnviar.innerHTML = htmlBtn; });
    });

    modalEl.addEventListener('click', function (e) {
        var del = e.target.closest('.lux-comp-del');
        if (!del) return;
        var id = del.getAttribute('data-id');
        var perguntar = (typeof swal === 'function')
            ? swal({ title: 'Excluir comprovante?', text: 'Se ele lançou dinheiro no extrato, a entrada continua lá com um aviso para o financeiro estornar ou vincular a outra venda.', icon: 'warning', buttons: ['Cancelar', 'Excluir'], dangerMode: true })
            : Promise.resolve(confirm('Excluir este comprovante?'));
        perguntar.then(function (ok) { if (ok) excluir(id); });
    });

    function excluir(id) {
        fetch(URL_ITEM + encodeURIComponent(id), {
            method: 'DELETE', credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { if (!r.ok) throw new Error(j.message || 'Não foi possível excluir.'); return j; }); })
            .then(function (j) { modalEl.dataset.mudou = '1'; render(j); })
            .catch(function (err) { mostrarErro(err.message); });
    }
})();
</script>
