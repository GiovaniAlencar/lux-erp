{{-- Divisão fiscal / não fiscal no resumo lateral da venda (uso interno do vendedor). --}}
@include('vendas.partials.pix_js')
@php
    $__luxSaldos = \App\Models\Produto::where('empresa_id', request()->empresa_id)
        ->where('fiscal', 1)
        ->pluck('estoque_fiscal', 'id')
        ->map(fn ($v) => (float) $v)
        ->all();
    if (isset($item) && $item instanceof \App\Models\Venda && $item->exists) {
        foreach ($item->itens as $__it) {
            if (isset($__luxSaldos[$__it->produto_id])) {
                $__luxSaldos[$__it->produto_id] += (float) $__it->qtd_fiscal;
            }
        }
    }
@endphp
<div class="lux-contas-resumo mt-3 p-3 rounded border d-none">
    <div class="small text-muted text-uppercase fw-semibold mb-2">Cobrar em duas contas</div>
    <div class="d-flex justify-content-between align-items-baseline mb-1">
        <span><span class="badge bg-primary me-1">F</span>{{ config('lux.conta_fiscal') }}</span>
        <span class="d-inline-flex align-items-center gap-2"><button type="button" class="lux-pix-btn lux-pix-live d-none" data-conta="fiscal" title="Copiar PIX copia e cola"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg><span class="lux-pix-label">PIX</span></button><span class="h5 mb-0 fw-bold lux-valor-fiscal">R$ 0,00</span></span>
    </div>
    <div class="d-flex justify-content-between align-items-baseline">
        <span><span class="badge bg-secondary me-1">2</span>{{ config('lux.conta_nao_fiscal') }}</span>
        <span class="d-inline-flex align-items-center gap-2"><button type="button" class="lux-pix-btn lux-pix-live d-none" data-conta="nao_fiscal" title="Copiar PIX copia e cola"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg><span class="lux-pix-label">PIX</span></button><span class="h5 mb-0 fw-bold lux-valor-nao-fiscal">R$ 0,00</span></span>
    </div>
    <div class="small text-muted mt-2">Frete, desconto e acréscimo ficam na {{ config('lux.conta_nao_fiscal') }}.</div>
    <div class="lux-fiscal-itens mt-2"></div>
</div>
<style>
    .lux-fiscal-itens .lux-fi { border-top: 1px dashed #d1d5db; padding: 6px 0; font-size: .8rem; }
    .lux-fiscal-itens .lux-fi-pend { background: #fff7ed; border-radius: 6px; padding: 6px; border: 1px solid #fdba74; }
    .lux-fiscal-itens .btn { font-size: .72rem; padding: 2px 6px; }
</style>

<script>
(function () {
    var SALDOS = @json((object) $__luxSaldos); // produto_id -> saldo fiscal disponível
    var EPS = 0.0005;

    function num(v) {
        var s = (v == null ? '' : String(v)).replace(/[^\d,.-]/g, '');
        if (s.indexOf(',') >= 0) s = s.replace(/\./g, '').replace(',', '.');
        var n = parseFloat(s);
        return isNaN(n) ? 0 : n;
    }
    function moeda(n) {
        return 'R$ ' + n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function campo(sel) {
        var el = document.querySelector(sel);
        return el ? num(el.value) : 0;
    }
    window.__luxFiscalPendentes = 0;
    window.__luxRidSeq = window.__luxRidSeq || 0;

    function fmtQ(n) { return (Math.round(n * 1000) / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 3 }); }
    function esc(t) { return String(t || '').replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

    /** garante o input hidden qtd_fiscal[] na linha (um por linha, na mesma ordem dos demais arrays) */
    function inputQf(tr) {
        var inp = tr.querySelector('input[name="qtd_fiscal[]"]');
        if (!inp) {
            inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'qtd_fiscal[]';
            inp.value = '';
            var td = tr.querySelector('td');
            (td || tr).appendChild(inp);
        }
        return inp;
    }

    /** decisão inicial vinda do servidor (edição / volta com erro) */
    function decisaoInicial(tr, qty, inp) {
        if (tr.dataset.luxDecisao !== undefined) return;
        if (inp.value === '') { tr.dataset.luxDecisao = ''; return; }
        var qf = num(inp.value);
        tr.dataset.luxDecisao = qf <= EPS ? 'conta2' : (qf < qty - EPS ? 'dividir' : '');
    }

    // Mesma regra do servidor (Venda::divisaoFiscal), com a parte fiscal limitada ao saldo com nota.
    function calcular() {
        var box = document.querySelector('.lux-contas-resumo');
        if (!box) return;
        var fiscal = 0, naoFiscal = 0, pendentes = 0;
        var resto = {};
        Object.keys(SALDOS).forEach(function (k) { resto[k] = Math.max(0, SALDOS[k]); });
        var linhasHtml = [];

        document.querySelectorAll('.table-itens tbody tr').forEach(function (tr) {
            var pid = tr.querySelector('input[name="produto_id[]"]');
            var sub = tr.querySelector('input[name="subtotal_item[]"]');
            if (!pid || !sub) return;
            var inp = inputQf(tr);
            if (!tr.dataset.luxRid) tr.dataset.luxRid = String(++window.__luxRidSeq);
            var idx = tr.dataset.luxRid;
            var id = String(parseInt(pid.value, 10));
            var subtotal = num(sub.value);
            var qtyEl = tr.querySelector('input[name="quantidade[]"]');
            var qty = qtyEl ? num(qtyEl.value) : 0;
            var unit = qty > 0 ? subtotal / qty : 0;

            if (!(id in SALDOS)) { inp.value = '0'; naoFiscal += subtotal; return; }
            decisaoInicial(tr, qty, inp);

            var dec = tr.dataset.luxDecisao || '';
            var disp = resto[id] || 0;
            var qf = 0, pend = false, falta = Math.max(0, qty - disp);
            if (dec === 'conta2') qf = 0;
            else if (qty <= disp + EPS) qf = qty;
            else if (dec === 'dividir') qf = Math.max(0, disp);
            else { qf = 0; pend = true; }
            resto[id] = Math.max(0, disp - qf);

            inp.value = qf.toFixed(3);
            var vfLinha = Math.round(unit * qf * 100) / 100;
            fiscal += vfLinha;
            naoFiscal += subtotal - vfLinha;
            if (pend) pendentes++;

            var nome = (tr.querySelector('input[name="produto_nome[]"]') || {}).value || ('#' + id);
            var h = '<div class="lux-fi' + (pend ? ' lux-fi-pend' : '') + '" data-linha="' + idx + '">' +
                '<div class="fw-semibold">' + esc(nome) + '</div>';
            if (pend) {
                h += '<div class="text-danger">Saldo com nota: <b>' + fmtQ(disp) + '</b> de ' + fmtQ(qty) + ' un. Escolha:</div>' +
                    '<div class="d-flex flex-wrap gap-1 mt-1">' +
                    (disp > EPS ? '<button type="button" class="btn btn-outline-primary" data-acao="dividir">Dividir: ' + fmtQ(disp) + ' fiscal + ' + fmtQ(falta) + ' conta 2</button>' : '') +
                    '<button type="button" class="btn btn-outline-secondary" data-acao="conta2">Tudo na conta 2</button></div>';
            } else if (dec === 'conta2') {
                h += '<div class="text-muted">' + fmtQ(qty) + ' un na conta 2 <a href="#" data-acao="reset">↺ voltar para fiscal</a></div>';
            } else {
                h += '<div><span class="badge bg-primary">F</span> ' + fmtQ(qf) + ' un' +
                    (qty - qf > EPS ? ' · <span class="badge bg-secondary">2</span> ' + fmtQ(qty - qf) + ' un' : '') +
                    ' <span class="text-muted">(saldo com nota ' + fmtQ(disp) + ')</span> · <a href="#" data-acao="' + (dec === 'dividir' ? 'reset' : 'conta2') + '">' +
                    (dec === 'dividir' ? '↺ desfazer' : 'mandar p/ conta 2') + '</a></div>';
            }
            linhasHtml.push(h + '</div>');
        });

        window.__luxFiscalPendentes = pendentes;
        var painel = box.querySelector('.lux-fiscal-itens');
        if (painel) {
            painel.innerHTML = linhasHtml.length
                ? '<div class="small text-muted text-uppercase fw-semibold">Produtos com estoque fiscal</div>' + linhasHtml.join('')
                : '';
        }
        fiscal = Math.round(fiscal * 100) / 100;
        naoFiscal = Math.round(naoFiscal * 100) / 100;

        var desconto = campo('input[name="desconto"]');
        var acrescimo = campo('input[name="acrescimo"]');
        var frete = campo('input[name="frete"]');
        var base = fiscal + naoFiscal;
        var total = Math.round((base - desconto + acrescimo + frete) * 100) / 100;
        var vf = 0;
        if (fiscal > 0) {
            if (naoFiscal <= 0) {
                vf = total;
            } else {
                var excedente = Math.max(0, desconto - (naoFiscal + acrescimo + frete));
                vf = fiscal - excedente;
            }
            vf = Math.max(0, Math.min(Math.round(vf * 100) / 100, total));
        }
        var vnf = Math.round((total - vf) * 100) / 100;

        box.classList.toggle('d-none', base <= 0);
        window.__luxTemFiscal = fiscal > 0;
        atualizarDoc();
        box.querySelector('.lux-valor-fiscal').textContent = moeda(vf);
        box.querySelector('.lux-valor-nao-fiscal').textContent = moeda(vnf);

        var txBase = 'LUX' + ({{ isset($item) && $item->id ? (int) $item->id : 0 }} || '');
        [['fiscal', vf, 'F'], ['nao_fiscal', vnf, 'N']].forEach(function (x) {
            var b = box.querySelector('.lux-pix-live[data-conta="' + x[0] + '"]');
            if (!b || !window.luxPix) return;
            var code = {{ (isset($item) && ($item->status_pagamento ?? '') === 'pago') ? 'true' : 'false' }} ? null : window.luxPix.gerar(x[0], x[1], txBase !== 'LUX' ? txBase + x[2] : '');
            b.classList.toggle('d-none', !code);
            if (code) b.setAttribute('data-pix', code);
        });
    }

    // ---------- CPF/CNPJ obrigatório quando há produto fiscal ----------
    function docDigitos(v) { return String(v || '').replace(/\D/g, ''); }
    function cpfOk(c) {
        if (c.length !== 11 || /^(\d)\1{10}$/.test(c)) return false;
        for (var t = 9; t < 11; t++) {
            var s = 0;
            for (var i = 0; i < t; i++) s += +c[i] * ((t + 1) - i);
            if (+c[t] !== ((10 * s) % 11) % 10) return false;
        }
        return true;
    }
    function cnpjOk(c) {
        if (c.length !== 14 || /^(\d)\1{13}$/.test(c)) return false;
        var pesos = [[5,4,3,2,9,8,7,6,5,4,3,2], [6,5,4,3,2,9,8,7,6,5,4,3,2]];
        for (var k = 0; k < 2; k++) {
            var len = 12 + k, s = 0;
            for (var i = 0; i < len; i++) s += +c[i] * pesos[k][i];
            var r = s % 11, dv = r < 2 ? 0 : 11 - r;
            if (+c[len] !== dv) return false;
        }
        return true;
    }
    function docValido(v) { var d = docDigitos(v); return d.length === 11 ? cpfOk(d) : (d.length === 14 ? cnpjOk(d) : false); }

    function atualizarDoc() {
        var boxDoc = document.querySelector('.lux-doc-fiscal-box');
        var inp = document.getElementById('inp-cliente_cpf_cnpj');
        if (!boxDoc || !inp) return;
        var precisa = !!window.__luxTemFiscal;
        boxDoc.classList.toggle('d-none', !precisa);
        var ok = docValido(inp.value);
        inp.classList.toggle('is-invalid', precisa && inp.value.trim() !== '' && !ok);
        inp.classList.toggle('is-valid', precisa && ok);
    }

    function carregarDocCliente() {
        var sel = document.getElementById('inp-cliente_id');
        var inp = document.getElementById('inp-cliente_cpf_cnpj');
        if (!sel || !inp || !sel.value) return;
        var base = (typeof path_url !== 'undefined' && path_url) ? path_url : '/';
        fetch(base + 'api/cliente/find/' + encodeURIComponent(sel.value), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (c) {
                if (!c) return;
                inp.value = docValido(c.cpf_cnpj) ? c.cpf_cnpj : '';
                atualizarDoc();
            }).catch(function () {});
    }

    // decisões do vendedor (dividir / conta 2 / voltar)
    document.addEventListener('click', function (e) {
        var a = e.target.closest('.lux-fiscal-itens [data-acao]');
        if (!a) return;
        e.preventDefault();
        var linha = a.closest('[data-linha]');
        var tr = document.querySelector('.table-itens tbody tr[data-lux-rid="' + linha.getAttribute('data-linha') + '"]');
        if (!tr) return;
        var acao = a.getAttribute('data-acao');
        tr.dataset.luxDecisao = acao === 'reset' ? '' : acao;
        calcular();
    });

    // Trava o "Finalizar/Salvar" se houver item fiscal sem decisão (saldo insuficiente)
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.btn-venda');
        if (!btn || !(window.__luxFiscalPendentes > 0)) return;
        e.preventDefault();
        e.stopPropagation();
        var msg = 'Há produto com saldo fiscal insuficiente. No resumo, escolha "Dividir" ou "Tudo na conta 2".';
        var p = document.querySelector('.lux-fi-pend');
        if (p) p.scrollIntoView({ behavior: 'smooth', block: 'center' });
        if (typeof swal === 'function') swal('Atenção', msg, 'warning'); else alert(msg);
    }, true);

    // Trava o "Finalizar/Salvar" se tiver produto fiscal sem documento válido
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.btn-venda');
        if (!btn || !window.__luxTemFiscal) return;
        var inp = document.getElementById('inp-cliente_cpf_cnpj');
        if (inp && docValido(inp.value)) return;
        e.preventDefault();
        e.stopPropagation();
        if (inp) { inp.classList.add('is-invalid'); inp.focus(); }
        var msg = 'Pedido com produto fiscal: informe um CPF/CNPJ válido do cliente.';
        if (typeof swal === 'function') swal('Atenção', msg, 'warning'); else alert(msg);
    }, true);

    document.addEventListener('DOMContentLoaded', function () {
        if (window.jQuery) {
            jQuery(document).on('change', '#inp-cliente_id', carregarDocCliente);
        }
        document.addEventListener('input', function (e) {
            if (e.target && e.target.id === 'inp-cliente_cpf_cnpj') atualizarDoc();
        });
        var inpDoc = document.getElementById('inp-cliente_cpf_cnpj');
        if (inpDoc && !inpDoc.value) carregarDocCliente();

        var tbody = document.querySelector('.table-itens tbody');
        if (tbody) new MutationObserver(calcular).observe(tbody, { childList: true, subtree: true, attributes: true, attributeFilter: ['value'] });
        document.addEventListener('input', function (e) {
            if (e.target.matches('.desconto, .acrescimo, .frete, .qtd_row, .value_unit_row, .subtotal-item')) calcular();
        });
        document.addEventListener('change', function (e) {
            if (e.target.matches('.desconto, .acrescimo, .frete')) calcular();
        });
        // valores alterados por JS (ex.: tabela de preço) não disparam "input"
        setInterval(calcular, 1500);
        calcular();
    });
})();
</script>
