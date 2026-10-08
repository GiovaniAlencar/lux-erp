{{-- Divisão fiscal / não fiscal no resumo lateral da venda (uso interno do vendedor). --}}
@include('vendas.partials.pix_js')
@php
    $__luxFiscalIds = \App\Models\Produto::where('empresa_id', request()->empresa_id)
        ->where('fiscal', 1)
        ->pluck('id')
        ->map(fn ($id) => (int) $id)
        ->values();
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
    <div class="small text-muted mt-2">Desconto/acréscimo divididos proporcionalmente. Frete na {{ config('lux.conta_nao_fiscal') }}.</div>
</div>

<script>
(function () {
    var FISCAIS = new Set(@json($__luxFiscalIds));

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

    // Mesma regra do servidor (Venda::divisaoFiscal).
    function calcular() {
        var box = document.querySelector('.lux-contas-resumo');
        if (!box) return;
        var fiscal = 0, naoFiscal = 0;
        document.querySelectorAll('.table-itens tbody tr').forEach(function (tr) {
            var pid = tr.querySelector('input[name="produto_id[]"]');
            var sub = tr.querySelector('input[name="subtotal_item[]"]');
            if (!pid || !sub) return;
            var v = num(sub.value);
            if (FISCAIS.has(parseInt(pid.value, 10))) fiscal += v; else naoFiscal += v;
        });

        var desconto = campo('input[name="desconto"]');
        var acrescimo = campo('input[name="acrescimo"]');
        var frete = campo('input[name="frete"]');
        var base = fiscal + naoFiscal;
        var total = Math.round((base - desconto + acrescimo + frete) * 100) / 100;
        var prop = base > 0 ? fiscal / base : 0;

        var vf = 0;
        if (fiscal > 0) {
            vf = fiscal - desconto * prop + acrescimo * prop;
            if (naoFiscal <= 0) vf += frete;
            vf = Math.max(0, Math.round(vf * 100) / 100);
            if (vf > total) vf = Math.max(0, total);
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
