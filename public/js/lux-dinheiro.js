/*
 * Campo de dinheiro "natural" (class="lux-dinheiro"): a pessoa digita como fala.
 *   150 → R$ 150,00 · 150,5 → R$ 150,50 · 1.500 → R$ 1.500,00
 * (diferente da máscara antiga .moeda, em que digitar 150 virava 1,50).
 * Ao sair do campo, formata em 1.234,56. O servidor lê com Financeiro::valorBr().
 */
(function () {
    function parse(v) {
        var s = String(v == null ? '' : v).replace(/[^\d,.\-]/g, '');
        if (!s || s === '-') return 0;
        if (s.indexOf(',') >= 0) s = s.replace(/\./g, '').replace(',', '.');
        else if (/^-?\d{1,3}(\.\d{3})+$/.test(s)) s = s.replace(/\./g, '');
        var n = parseFloat(s);
        return isNaN(n) ? 0 : Math.round(n * 100) / 100;
    }
    function format(n) {
        return Number(n || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    window.luxDinheiro = { parse: parse, format: format };

    function preparar(el) {
        if (el.dataset.luxDin) return;
        el.dataset.luxDin = '1';
        el.setAttribute('inputmode', 'decimal');
        el.setAttribute('autocomplete', 'off');
        if (!el.placeholder) el.placeholder = '0,00';
    }
    document.addEventListener('focusin', function (e) {
        var el = e.target;
        if (!el.classList || !el.classList.contains('lux-dinheiro')) return;
        preparar(el);
        // remove a máscara antiga se alguém deixou as duas classes
        if (window.jQuery && jQuery.fn.unmask) { try { jQuery(el).unmask(); } catch (x) {} }
        // seleciona tudo ao entrar (para digitar por cima), mas só se a pessoa ainda não começou a digitar
        var antes = el.value;
        setTimeout(function () { if (el.value === antes && document.activeElement === el) { try { el.select(); } catch (x) {} } }, 0);
    });
    document.addEventListener('input', function (e) {
        var el = e.target;
        if (!el.classList || !el.classList.contains('lux-dinheiro')) return;
        var limpo = el.value.replace(/[^\d,.]/g, '');
        if (limpo !== el.value) el.value = limpo;
    });
    document.addEventListener('focusout', function (e) {
        var el = e.target;
        if (!el.classList || !el.classList.contains('lux-dinheiro') || el.value.trim() === '') return;
        el.value = format(parse(el.value));
        el.dispatchEvent(new Event('change', { bubbles: true }));
    });
    document.querySelectorAll('input.lux-dinheiro').forEach(preparar);
})();
