@once
{{-- PIX copia e cola: gerador (mesma regra do App\Helpers\PixCopiaCola) + botão de copiar. --}}
<style>
    .lux-pix-btn { display: inline-flex; align-items: center; justify-content: center; gap: 4px; border: 1px solid #10b981; color: #059669; background: #ecfdf5; border-radius: 6px; padding: 1px 6px; font-size: 11px; font-weight: 600; line-height: 18px; cursor: pointer; white-space: nowrap; vertical-align: middle; }
    .lux-pix-btn:hover { background: #10b981; color: #fff; }
    .lux-pix-btn.copiado { background: #059669; color: #fff; }
    .lux-pix-btn svg { width: 12px; height: 12px; }
</style>
<script>
window.luxPix = window.luxPix || (function () {
    var CFG = @json([
        'fiscal' => config('lux.pix.fiscal'),
        'nao_fiscal' => config('lux.pix.nao_fiscal'),
    ]);

    function campo(id, v) { return id + String(v.length).padStart(2, '0') + v; }
    function texto(s, max) {
        s = String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '');
        s = s.replace(/[^A-Za-z0-9 ]/g, '').replace(/\s+/g, ' ').trim().toUpperCase();
        return s.substring(0, max);
    }
    function chave(c) {
        c = String(c || '').trim();
        if (/^[\d.\-\/\s]+$/.test(c)) return c.replace(/\D/g, '');
        if (c.indexOf('@') >= 0) return c.toLowerCase();
        return c.replace(/\s+/g, '');
    }
    function crc16(str) {
        var crc = 0xFFFF;
        for (var i = 0; i < str.length; i++) {
            crc ^= str.charCodeAt(i) << 8;
            for (var b = 0; b < 8; b++) {
                crc = (crc & 0x8000) ? ((crc << 1) ^ 0x1021) : (crc << 1);
                crc &= 0xFFFF;
            }
        }
        return crc.toString(16).toUpperCase().padStart(4, '0');
    }
    function gerar(conta, valor, txid) {
        var c = CFG[conta];
        if (!c || !c.chave || !(valor > 0)) return null;
        var tx = String(txid || '').replace(/[^A-Za-z0-9]/g, '').substring(0, 25) || '***';
        var p = campo('00', '01')
            + campo('26', campo('00', 'br.gov.bcb.pix') + campo('01', chave(c.chave)))
            + campo('52', '0000') + campo('53', '986')
            + campo('54', (Math.round(valor * 100) / 100).toFixed(2))
            + campo('58', 'BR')
            + campo('59', texto(c.nome, 25) || 'RECEBEDOR')
            + campo('60', texto(c.cidade, 15) || 'SAO PAULO')
            + campo('62', campo('05', tx))
            + '6304';
        return p + crc16(p);
    }
    function copiarTexto(txt) {
        // http (lux-erp.test) não libera navigator.clipboard: usa o fallback com textarea
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(txt);
        }
        return new Promise(function (ok, fail) {
            var ta = document.createElement('textarea');
            ta.value = txt;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.top = '-1000px';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy') ? ok() : fail(); } catch (e) { fail(e); }
            document.body.removeChild(ta);
        });
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.lux-pix-btn');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        var code = btn.getAttribute('data-pix');
        if (!code) return;
        var label = btn.querySelector('.lux-pix-label');
        var orig = label ? label.textContent : '';
        copiarTexto(code).then(function () {
            btn.classList.add('copiado');
            if (label) label.textContent = 'Copiado!';
            setTimeout(function () { btn.classList.remove('copiado'); if (label) label.textContent = orig; }, 1600);
        }, function () {
            window.prompt('Copie o PIX copia e cola:', code);
        });
    }, true);

    return { gerar: gerar, configurado: function (conta) { return !!(CFG[conta] && CFG[conta].chave); } };
})();
</script>
@endonce
