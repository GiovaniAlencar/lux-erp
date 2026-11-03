<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#111111">
<title>Rota #{{ $rota->id }} · LUX</title>
<style>
  :root { --bg:#f3f4f6; --card:#fff; --tx:#111827; --mut:#6b7280; --ln:#e5e7eb; --ouro:#b08a3a; --ok:#059669; --er:#dc2626; --am:#d97706; --az:#2563eb; }
  * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
  body { margin:0; font-family: system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; background:var(--bg); color:var(--tx); font-size:15px; }
  header { position:sticky; top:0; z-index:5; background:#111; color:#fff; padding:12px 14px calc(10px); }
  header h1 { margin:0; font-size:17px; } header small { color:#d1d5db; }
  .barra { height:6px; background:#374151; border-radius:9px; margin-top:8px; overflow:hidden; } .barra i { display:block; height:100%; background:var(--ouro); }
  .resumo { display:flex; gap:6px; margin-top:8px; flex-wrap:wrap; font-size:12px; } .resumo span { background:#1f2937; padding:3px 8px; border-radius:99px; }
  main { padding:10px 10px 90px; max-width:640px; margin:0 auto; }
  .aviso-geo { background:#fff7ed; border:1px solid #fed7aa; color:#9a3412; border-radius:10px; padding:10px; font-size:13px; margin-bottom:10px; display:flex; gap:8px; align-items:center; justify-content:space-between; }
  .bairro { margin:16px 4px 6px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--mut); display:flex; justify-content:space-between; }
  .card { background:var(--card); border-radius:14px; padding:12px; margin-bottom:10px; box-shadow:0 1px 2px rgba(0,0,0,.06); border:1px solid var(--ln); }
  .card.prio { border-color:#f59e0b; box-shadow:0 0 0 2px #fde68a; }
  .card.feito { opacity:.72; }
  .top { display:flex; justify-content:space-between; gap:8px; align-items:flex-start; }
  .nome { font-weight:700; font-size:16px; } .ped { color:var(--mut); font-size:12px; white-space:nowrap; }
  .end { margin-top:4px; color:#374151; } .comp { color:var(--mut); font-size:13px; }
  .tags { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; }
  .tag { font-size:12px; padding:3px 8px; border-radius:99px; background:#f3f4f6; color:#374151; }
  .tag.ok { background:#d1fae5; color:#065f46; } .tag.co { background:#fee2e2; color:#991b1b; font-weight:700; font-size:13px; }
  .tag.pr { background:#fef3c7; color:#92400e; font-weight:700; } .tag.oc { background:#fee2e2; color:#991b1b; }
  .aviso { margin-top:8px; background:#fef3c7; color:#78350f; padding:7px 9px; border-radius:8px; font-size:13px; }
  .acoes { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:10px; }
  .btn { display:flex; align-items:center; justify-content:center; gap:6px; border:0; border-radius:10px; padding:12px 10px; font-size:15px; font-weight:600; text-decoration:none; cursor:pointer; }
  .btn-waze { background:#33ccff; color:#002b3a; } .btn-tel { background:#f3f4f6; color:#111; }
  .btn-ok { background:var(--ok); color:#fff; grid-column:1 / -1; } .btn-oc { background:#fff; color:var(--er); border:1px solid #fecaca; }
  .btn-sec { background:#f3f4f6; color:#111; } .btn:disabled { opacity:.5; }
  .feitos-tit { margin:22px 4px 6px; font-size:12px; font-weight:700; color:var(--mut); text-transform:uppercase; }
  .vazio { text-align:center; color:var(--mut); padding:30px 10px; }
  /* janela de baixo */
  .fundo { position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:20; display:none; }
  .folha { position:fixed; left:0; right:0; bottom:0; z-index:21; background:#fff; border-radius:18px 18px 0 0; max-height:92vh; overflow:auto; padding:16px 14px calc(16px + env(safe-area-inset-bottom)); display:none; max-width:640px; margin:0 auto; }
  .folha h2 { margin:0 0 4px; font-size:18px; } .folha .sub { color:var(--mut); font-size:13px; margin-bottom:12px; }
  .grid2 { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
  .opc { border:2px solid var(--ln); border-radius:10px; padding:11px 8px; text-align:center; font-weight:600; background:#fff; font-size:14px; }
  .opc.sel { border-color:var(--tx); background:#111; color:#fff; }
  label.l { display:block; font-size:12px; font-weight:700; color:var(--mut); text-transform:uppercase; margin:12px 0 5px; }
  input.in, select.in, textarea.in { width:100%; font-size:17px; padding:11px; border:1px solid #d1d5db; border-radius:10px; background:#fff; }
  .valor-grande { font-size:26px; font-weight:800; text-align:center; }
  .calc { background:#f9fafb; border:1px solid var(--ln); border-radius:12px; padding:10px; margin-top:10px; font-size:14px; }
  .calc b { font-size:18px; } .calc .linha { display:flex; justify-content:space-between; padding:3px 0; }
  .foto { border:2px dashed #d1d5db; border-radius:12px; padding:14px; text-align:center; color:var(--mut); margin-top:6px; }
  .foto img { max-width:100%; max-height:220px; border-radius:8px; display:block; margin:0 auto 8px; }
  .erro { color:var(--er); font-size:14px; margin-top:10px; min-height:1em; }
  .check { display:flex; gap:10px; align-items:center; margin-top:12px; font-size:15px; }
  .check input { width:22px; height:22px; }
  .tabela { width:100%; border-collapse:collapse; font-size:14px; margin-top:6px; }
  .tabela td { padding:6px 4px; border-top:1px solid var(--ln); } .tabela td:last-child { text-align:right; font-weight:700; }
  .toast { position:fixed; left:50%; bottom:24px; transform:translateX(-50%); background:#111; color:#fff; padding:10px 16px; border-radius:99px; font-size:14px; z-index:40; display:none; max-width:90vw; text-align:center; }
  .flutua { position:fixed; right:14px; bottom:18px; z-index:10; background:#111; color:#fff; border:0; border-radius:99px; padding:13px 16px; font-weight:700; box-shadow:0 6px 18px rgba(0,0,0,.25); }
</style>
</head>
<body>
<header>
  <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
    <h1>Rota #{{ $rota->id }} @if($rota->motoboy_nome)· {{ $rota->motoboy_nome }}@endif</h1>
    <button onclick="abrirCalc()" style="background:#b08a3a;color:#111;border:0;border-radius:99px;padding:8px 12px;font-weight:700;font-size:14px">🧮 Calculadora</button>
  </div>
  <small id="progresso"></small>
  <div class="barra"><i id="barra" style="width:0"></i></div>
  <div class="resumo" id="resumo"></div>
</header>
<main>
  @if($rota->status !== 'em_rota')
    <div class="aviso-geo" style="background:#eef2ff;border-color:#c7d2fe;color:#3730a3">
      {{ $rota->status === 'finalizada' ? 'Rota finalizada. Obrigado!' : 'Esta rota ainda não saiu (está em rascunho). Aguarde a loja liberar.' }}
    </div>
  @endif
  <div id="avisoGeo" class="aviso-geo" style="display:none">
    <span>Libere a localização para ordenar pelas entregas mais perto de você.</span>
    <button class="btn btn-sec" style="padding:8px 10px" onclick="pedirLocal()">Usar localização</button>
  </div>
  <div id="lista"></div>
</main>

<div class="fundo" id="fundo" onclick="fechar()"></div>
<div class="folha" id="folha"></div>
<div class="toast" id="toast"></div>

<script>
(function () {
  const TOKEN = @json($token);
  const BASE = @json(url('/motoboy/' . $token));
  const CSRF = document.querySelector('meta[name=csrf-token]').content;
  const TAXAS = @json($taxas);
  const OCORRENCIAS = @json(\App\Models\RotaEntregaItem::tiposOcorrencia());
  const EM_ROTA = @json($rota->status === 'em_rota');
  let LOJA = @json($loja);
  let itens = @json($itens);
  let origem = null; // posição do motoboy

  const $ = (s) => document.querySelector(s);
  const moeda = (v) => (Number(v) || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  function lerValor(t) {
    let s = String(t || '').replace(/[^\d.,]/g, '');
    if (!s) return 0;
    if (s.includes(',')) s = s.replace(/\./g, '').replace(',', '.');
    else if (/^\d{1,3}(\.\d{3})+$/.test(s)) s = s.replace(/\./g, '');
    return Math.round((parseFloat(s) || 0) * 100) / 100;
  }
  function toast(msg) { const t = $('#toast'); t.textContent = msg; t.style.display = 'block'; clearTimeout(t._t); t._t = setTimeout(() => (t.style.display = 'none'), 3200); }

  // ---------- distância e ordem ----------
  function dist(a, b) {
    const R = 6371, r = Math.PI / 180;
    const dLat = (b.lat - a.lat) * r, dLng = (b.lng - a.lng) * r;
    const x = Math.sin(dLat / 2) ** 2 + Math.cos(a.lat * r) * Math.cos(b.lat * r) * Math.sin(dLng / 2) ** 2;
    return 2 * R * Math.asin(Math.sqrt(x));
  }
  // vizinho mais próximo a partir do motoboy (ou da loja): sequência de visita
  function sequencia(lista) {
    const com = lista.filter((i) => i.lat != null && i.lng != null);
    const sem = lista.filter((i) => i.lat == null || i.lng == null);
    let atual = origem || LOJA || (com[0] ? { lat: com[0].lat, lng: com[0].lng } : null);
    const ordem = [], resto = com.slice();
    while (resto.length) {
      let k = 0, best = Infinity;
      resto.forEach((i, idx) => { const d = atual ? dist(atual, i) : 0; if (d < best) { best = d; k = idx; } });
      const [p] = resto.splice(k, 1); p._km = atual ? best : null; ordem.push(p); atual = p;
    }
    return ordem.concat(sem);
  }
  // agrupa por bairro na ordem em que o bairro aparece na sequência; prioridade sempre primeiro
  function agrupar(pendentes) {
    const seq = sequencia(pendentes);
    const grupos = new Map();
    seq.forEach((i) => { if (!grupos.has(i.bairro)) grupos.set(i.bairro, []); grupos.get(i.bairro).push(i); });
    const arr = Array.from(grupos.entries());
    arr.sort((a, b) => (b[1].some((i) => i.prioridade) ? 1 : 0) - (a[1].some((i) => i.prioridade) ? 1 : 0));
    arr.forEach((g) => g[1].sort((a, b) => (b.prioridade ? 1 : 0) - (a.prioridade ? 1 : 0)));
    return arr;
  }

  function linkWaze(i) {
    if (i.lat != null) return 'https://waze.com/ul?ll=' + i.lat + ',' + i.lng + '&navigate=yes';
    return 'https://waze.com/ul?q=' + encodeURIComponent(i.endereco + (i.cidade ? ', ' + i.cidade : '')) + '&navigate=yes';
  }
  function linkMaps(i) {
    const dest = i.lat != null ? i.lat + ',' + i.lng : i.endereco + (i.cidade ? ', ' + i.cidade : '');
    return 'https://www.google.com/maps/dir/?api=1&destination=' + encodeURIComponent(dest);
  }

  function cardHtml(i) {
    const feito = i.status !== 'pendente';
    let tags = '';
    if (i.prioridade) tags += '<span class="tag pr">⚡ Prioridade' + (i.obs_prioridade ? ': ' + esc(i.obs_prioridade) : '') + '</span>';
    if (!feito) tags += i.falta > 0 ? '<span class="tag co">Cobrar ' + moeda(i.falta) + '</span>' : '<span class="tag ok">Pago</span>';
    if (i.embalagem) tags += '<span class="tag">📦 ' + esc(i.embalagem) + '</span>';
    if (!feito && i._km != null) tags += '<span class="tag">' + (i._km < 1 ? Math.round(i._km * 1000) + ' m' : i._km.toFixed(1) + ' km') + '</span>';
    if (i.status === 'entregue') tags += '<span class="tag ok">✔ Entregue ' + esc(i.concluido_em || '') + '</span>';
    if (i.status === 'ocorrencia') tags += '<span class="tag oc">⚠ ' + esc(i.ocorrencia || 'Ocorrência') + '</span>';
    if (i.recebido) tags += '<span class="tag">' + esc(i.recebido.forma) + ' ' + moeda(i.recebido.valor) + (i.recebido.parcelas > 1 ? ' ' + i.recebido.parcelas + 'x' : '') + '</span>';

    let acoes = '<div class="acoes">' +
      '<a class="btn btn-waze" href="' + linkWaze(i) + '" target="_blank" rel="noopener">🚗 Waze</a>' +
      (i.telefone ? '<a class="btn btn-tel" href="https://wa.me/' + esc(i.telefone) + '" target="_blank" rel="noopener">💬 WhatsApp</a>'
                  : '<a class="btn btn-tel" href="' + linkMaps(i) + '" target="_blank" rel="noopener">🗺️ Maps</a>');
    if (!feito && EM_ROTA) {
      acoes += '<button class="btn btn-ok" data-entregar="' + i.id + '">✔ Entregue' + (i.falta > 0 ? ' / receber' : '') + '</button>' +
        '<button class="btn btn-oc" data-ocorrencia="' + i.id + '">⚠ Ocorrência</button>' +
        (i.telefone ? '<a class="btn btn-sec" href="tel:+' + esc(i.telefone) + '">📞 Ligar</a>' : '<span></span>');
    }
    acoes += '</div>';

    return '<div class="card' + (i.prioridade && !feito ? ' prio' : '') + (feito ? ' feito' : '') + '">' +
      '<div class="top"><div class="nome">' + esc(i.cliente || 'Cliente') + '</div><div class="ped">Pedido #' + i.venda + '</div></div>' +
      '<div class="end">' + esc(i.endereco) + '</div>' +
      (i.complemento ? '<div class="comp">' + esc(i.complemento) + '</div>' : '') +
      (!feito && i.pagamento_texto ? '<div class="comp" style="margin-top:4px">' + esc(i.pagamento_texto) + '</div>' : '') +
      (i.aviso ? '<div class="aviso">📝 ' + esc(i.aviso) + '</div>' : '') +
      '<div class="tags">' + tags + '</div>' + acoes + '</div>';
  }

  function render() {
    const pend = itens.filter((i) => i.status === 'pendente');
    const feitos = itens.filter((i) => i.status !== 'pendente').sort((a, b) => String(b.concluido_em || '').localeCompare(String(a.concluido_em || '')));
    const total = itens.length, ok = total - pend.length;
    $('#progresso').textContent = ok + ' de ' + total + ' entregas concluídas';
    $('#barra').style.width = (total ? Math.round((ok / total) * 100) : 0) + '%';
    const receber = pend.reduce((s, i) => s + (i.falta || 0), 0);
    const somar = (f) => itens.filter((i) => i.recebido && f(i.recebido.forma)).reduce((s, i) => s + i.recebido.valor, 0);
    $('#resumo').innerHTML = '<span>A receber: ' + moeda(receber) + '</span>' +
      '<span>💵 Dinheiro: ' + moeda(somar((f) => f === 'Dinheiro')) + '</span>' +
      '<span>PIX: ' + moeda(somar((f) => f === 'PIX')) + '</span>' +
      '<span>💳 Cartão: ' + moeda(somar((f) => f.startsWith('Cartão'))) + '</span>';

    let html = '';
    if (!pend.length && total) html += '<div class="vazio">🎉 Todas as entregas foram concluídas.</div>';
    agrupar(pend).forEach(([bairro, lista]) => {
      html += '<div class="bairro"><span>📍 ' + esc(bairro) + '</span><span>' + lista.length + '</span></div>';
      lista.forEach((i) => (html += cardHtml(i)));
    });
    if (feitos.length) {
      html += '<div class="feitos-tit">Concluídas (' + feitos.length + ')</div>';
      feitos.forEach((i) => (html += cardHtml(i)));
    }
    $('#lista').innerHTML = html;
    $('#avisoGeo').style.display = !origem && EM_ROTA && pend.some((i) => i.lat != null) ? 'flex' : 'none';
  }

  window.pedirLocal = function () {
    if (!navigator.geolocation) return toast('Este celular não informa a localização.');
    navigator.geolocation.getCurrentPosition(
      (p) => { origem = { lat: p.coords.latitude, lng: p.coords.longitude }; render(); },
      () => toast('Localização não liberada. A ordem fica a partir da loja.'),
      { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
    );
  };

  // ---------- janela de baixo ----------
  function abrir(html) { $('#folha').innerHTML = html; $('#folha').style.display = 'block'; $('#fundo').style.display = 'block'; }
  window.fechar = function () { $('#folha').style.display = 'none'; $('#fundo').style.display = 'none'; };

  function taxa(bandeira, op) { const t = TAXAS[bandeira]; return t ? parseFloat(t.taxas[op]) || 0 : 0; }
  function brutoPara(valor, bandeira, op) { const t = taxa(bandeira, op); return t ? Math.ceil(valor / (1 - t / 100)) : valor; }

  function opcoesBandeira(sel) {
    return Object.keys(TAXAS).map((k) => '<option value="' + k + '"' + (k === sel ? ' selected' : '') + '>' + esc(TAXAS[k].nome) + '</option>').join('');
  }

  // Calculadora solta (botão flutuante): quanto passar na maquineta para receber X líquido
  window.abrirCalc = function (valorInicial) {
    abrir('<h2>🧮 Calculadora da maquineta</h2><div class="sub">Quanto passar no cartão para a loja receber o valor do pedido.</div>' +
      '<label class="l">Valor do pedido (líquido)</label><input class="in" id="cv" inputmode="decimal" value="' + (valorInicial ? String(valorInicial).replace('.', ',') : '') + '" placeholder="0,00">' +
      '<label class="l">Bandeira</label><select class="in" id="cb">' + opcoesBandeira('visa_master') + '</select>' +
      '<table class="tabela" id="ct"></table><div style="margin-top:14px"><button class="btn btn-sec" style="width:100%" onclick="fechar()">Fechar</button></div>');
    const calc = () => {
      const v = lerValor($('#cv').value), b = $('#cb').value;
      let h = '';
      ['debito'].concat(Array.from({ length: 18 }, (_, i) => String(i + 1))).forEach((op) => {
        const bruto = v > 0 ? brutoPara(v, b, op) : 0;
        const n = op === 'debito' ? 1 : +op;
        h += '<tr><td>' + (op === 'debito' ? 'Débito' : op + 'x') + ' <small style="color:#6b7280">' + taxa(b, op).toFixed(2).replace('.', ',') + '%</small></td>' +
             '<td>' + (n > 1 && bruto ? n + 'x ' + moeda(bruto / n) : '') + '</td><td>' + (bruto ? moeda(bruto) : '—') + '</td></tr>';
      });
      $('#ct').innerHTML = h;
    };
    $('#cv').addEventListener('input', calc); $('#cb').addEventListener('change', calc); calc();
  };

  // Recebimento na entrega
  function abrirEntregar(i) {
    const st = { forma: i.falta > 0 ? '' : 'nenhum', bandeira: 'visa_master', parcelas: '1', foto: null };
    abrir('<h2>' + esc(i.cliente) + '</h2><div class="sub">Pedido #' + i.venda + ' · ' + esc(i.endereco) + '</div>' +
      (i.falta > 0
        ? '<div class="calc"><div class="linha"><span>Falta receber do pedido</span><b>' + moeda(i.falta) + '</b></div>' + (i.forma_prevista ? '<div class="linha"><span>Combinado</span><span>' + esc(i.forma_prevista) + '</span></div>' : '') + '</div>' +
          '<label class="l">Como o cliente pagou?</label><div class="grid2" id="formas">' +
          '<button class="opc" data-f="pix">PIX</button><button class="opc" data-f="dinheiro">💵 Dinheiro</button>' +
          '<button class="opc" data-f="credito">💳 Crédito</button><button class="opc" data-f="debito">💳 Débito</button></div>' +
          '<div id="cartao" style="display:none"><label class="l">Bandeira</label><select class="in" id="eb">' + opcoesBandeira('visa_master') + '</select>' +
          '<div id="boxParc"><label class="l">Parcelas</label><select class="in" id="ep">' + Array.from({ length: 18 }, (_, k) => '<option value="' + (k + 1) + '">' + (k + 1) + 'x</option>').join('') + '</select></div>' +
          '<div class="calc" id="ecalc"></div></div>' +
          '<label class="l" id="lv">Valor recebido</label><input class="in valor-grande" id="ev" inputmode="decimal" value="' + String(i.falta.toFixed(2)).replace('.', ',') + '">' +
          '<div class="comp" id="eobs" style="margin-top:6px"></div>' +
          '<div id="boxFoto" style="display:none"><label class="l">Foto do comprovante</label><label class="foto" id="fotoLbl"><input type="file" accept="image/*" capture="environment" id="ef" style="display:none"><span>📷 Toque para tirar a foto</span></label></div>' +
          '<label class="check"><input type="checkbox" id="esem"> Não recebi — cliente vai pagar depois</label>'
        : '<div class="calc"><div class="linha"><span>Pagamento</span><b style="color:#059669">Já está pago</b></div></div>') +
      '<div class="erro" id="eerro"></div>' +
      '<div class="acoes"><button class="btn btn-sec" onclick="fechar()">Cancelar</button><button class="btn btn-ok" style="grid-column:auto" id="econf">Confirmar entrega</button></div>');

    if (i.falta > 0) {
      const atual = () => {
        const cartao = st.forma === 'credito' || st.forma === 'debito';
        $('#cartao').style.display = cartao ? 'block' : 'none';
        $('#boxParc').style.display = st.forma === 'credito' ? 'block' : 'none';
        $('#boxFoto').style.display = cartao || st.forma === 'pix' ? 'block' : 'none';
        $('#lv').textContent = cartao ? 'Valor passado na maquineta (com a taxa)' : 'Valor recebido';
        const v = lerValor($('#ev').value);
        if (cartao) {
          const op = st.forma === 'debito' ? 'debito' : $('#ep').value;
          const sugerido = brutoPara(i.falta, $('#eb').value, op), t = taxa($('#eb').value, op);
          const liq = Math.round(v * (1 - t / 100) * 100) / 100;
          const n = st.forma === 'debito' ? 1 : +op;
          $('#ecalc').innerHTML = '<div class="linha"><span>Passar na maquineta</span><b>' + moeda(sugerido) + '</b></div>' +
            (n > 1 ? '<div class="linha"><span>' + n + 'x de</span><span>' + moeda(sugerido / n) + '</span></div>' : '') +
            '<div class="linha"><span>Taxa ' + t.toFixed(2).replace('.', ',') + '% · loja recebe</span><span>' + moeda(liq) + '</span></div>' +
            '<button class="btn btn-sec" style="width:100%;margin-top:6px;padding:8px" id="usarSug">Usar ' + moeda(sugerido) + '</button>';
          $('#usarSug').onclick = () => { $('#ev').value = String(sugerido).replace('.', ','); atual(); };
          $('#eobs').textContent = liq > i.falta + 1 ? 'Sobra ' + moeda(liq - i.falta) + ' → fica de saldo para o cliente.' : (liq + 0.01 < i.falta - 1 ? 'Falta ' + moeda(i.falta - liq) + ' → fica em aberto no pedido.' : '');
        } else if (st.forma) {
          $('#eobs').textContent = v > i.falta + 0.99 ? 'Sobra ' + moeda(v - i.falta) + ' → fica de saldo para o cliente.' : (v + 0.009 < i.falta ? 'Falta ' + moeda(i.falta - v) + ' → fica em aberto no pedido.' : '');
        }
      };
      document.querySelectorAll('#formas .opc').forEach((b) => b.onclick = () => {
        st.forma = b.dataset.f; document.querySelectorAll('#formas .opc').forEach((x) => x.classList.toggle('sel', x === b));
        if (st.forma === 'credito' || st.forma === 'debito') {
          const op = st.forma === 'debito' ? 'debito' : $('#ep').value;
          $('#ev').value = String(brutoPara(i.falta, $('#eb').value, op)).replace('.', ',');
        } else { $('#ev').value = String(i.falta.toFixed(2)).replace('.', ','); }
        atual();
      });
      ['#eb', '#ep'].forEach((s) => $(s).addEventListener('change', () => {
        const op = st.forma === 'debito' ? 'debito' : $('#ep').value;
        $('#ev').value = String(brutoPara(i.falta, $('#eb').value, op)).replace('.', ','); atual();
      }));
      $('#ev').addEventListener('input', atual);
      $('#ef').addEventListener('change', (e) => {
        const f = e.target.files[0]; st.foto = f || null;
        if (f) { const u = URL.createObjectURL(f); $('#fotoLbl').querySelector('span').innerHTML = '<img src="' + u + '">Toque para trocar a foto'; }
      });
    }

    $('#econf').onclick = async () => {
      const fd = new FormData();
      const sem = i.falta > 0 && $('#esem').checked;
      if (i.falta > 0 && !sem) {
        if (!st.forma) return ($('#eerro').textContent = 'Escolha como o cliente pagou.');
        const v = lerValor($('#ev').value);
        if (!(v > 0)) return ($('#eerro').textContent = 'Informe o valor recebido.');
        if ((st.forma !== 'dinheiro') && !st.foto) return ($('#eerro').textContent = 'Tire a foto do comprovante.');
        fd.append('forma', st.forma); fd.append('valor', String(v));
        if (st.forma === 'credito' || st.forma === 'debito') { fd.append('bandeira', $('#eb').value); fd.append('parcelas', st.forma === 'debito' ? '1' : $('#ep').value); }
        if (st.foto) fd.append('foto', await reduzir(st.foto), 'comprovante.jpg');
      }
      if (sem) fd.append('sem_pagamento', '1');
      await enviar('/itens/' + i.id + '/entregar', fd, $('#econf'), (j) => {
        toast(j.saldo > 0 ? 'Entrega confirmada. ' + moeda(j.saldo) + ' ficou de saldo do cliente.' : (j.falta > 0 && !sem ? 'Entrega confirmada. Ficou faltando ' + moeda(j.falta) + '.' : 'Entrega confirmada!'));
      });
    };
  }

  function abrirOcorrencia(i) {
    abrir('<h2>Ocorrência</h2><div class="sub">' + esc(i.cliente) + ' · Pedido #' + i.venda + '</div>' +
      '<label class="l">O que aconteceu?</label><select class="in" id="ot">' + Object.entries(OCORRENCIAS).map(([k, v]) => '<option value="' + k + '">' + esc(v) + '</option>').join('') + '</select>' +
      '<label class="l">Observação</label><textarea class="in" id="oo" rows="3" placeholder="Opcional"></textarea>' +
      '<div class="erro" id="eerro"></div><div class="acoes"><button class="btn btn-sec" onclick="fechar()">Cancelar</button><button class="btn btn-ok" style="grid-column:auto;background:#dc2626" id="oconf">Registrar</button></div>');
    $('#oconf').onclick = () => {
      const fd = new FormData(); fd.append('tipo', $('#ot').value); fd.append('observacao', $('#oo').value);
      enviar('/itens/' + i.id + '/ocorrencia', fd, $('#oconf'), () => toast('Ocorrência registrada.'));
    };
  }

  async function enviar(caminho, fd, botao, ok) {
    botao.disabled = true; const txt = botao.textContent; botao.textContent = 'Enviando…'; $('#eerro').textContent = '';
    try {
      const r = await fetch(BASE + caminho, { method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }, credentials: 'same-origin' });
      const j = await r.json().catch(() => ({}));
      if (!r.ok) {
        let m = j.message || 'Não foi possível salvar. Confira a internet e tente de novo.';
        if (j.errors) { const k = Object.keys(j.errors)[0]; if (k) m = j.errors[k][0]; }
        if (r.status === 419) m = 'A página ficou muito tempo aberta. Recarregue e tente de novo.';
        throw new Error(m);
      }
      if (j.item) itens = itens.map((x) => (x.id === j.item.id ? j.item : x));
      fechar(); render(); ok(j);
    } catch (e) { $('#eerro').textContent = e.message; }
    finally { botao.disabled = false; botao.textContent = txt; }
  }

  // reduz a foto (celular tira fotos de 4-8 MB) para ~1600 px
  function reduzir(f) {
    return new Promise((res) => {
      const img = new Image(), u = URL.createObjectURL(f);
      img.onload = () => {
        const esc = Math.min(1, 1600 / Math.max(img.width, img.height));
        const c = document.createElement('canvas'); c.width = Math.round(img.width * esc); c.height = Math.round(img.height * esc);
        const ctx = c.getContext('2d'); ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, c.width, c.height); ctx.drawImage(img, 0, 0, c.width, c.height);
        c.toBlob((b) => { URL.revokeObjectURL(u); res(b && b.size < f.size ? b : f); }, 'image/jpeg', 0.82);
      };
      img.onerror = () => res(f);
      img.src = u;
    });
  }

  document.addEventListener('click', (e) => {
    const a = e.target.closest('[data-entregar]'), o = e.target.closest('[data-ocorrencia]');
    if (a) abrirEntregar(itens.find((i) => i.id === +a.dataset.entregar));
    if (o) abrirOcorrencia(itens.find((i) => i.id === +o.dataset.ocorrencia));
  });

  // atualiza a cada 60 s (coordenadas que chegaram, entrega concluída por outro aparelho)
  setInterval(async () => {
    if ($('#folha').style.display === 'block') return;
    try { const r = await fetch(BASE + '/dados', { headers: { Accept: 'application/json' } }); if (r.ok) { const j = await r.json(); itens = j.itens; render(); } } catch (e) {}
  }, 60000);

  render();
  if (EM_ROTA && navigator.permissions && navigator.permissions.query) {
    navigator.permissions.query({ name: 'geolocation' }).then((p) => { if (p.state === 'granted') window.pedirLocal(); }).catch(() => {});
  }
})();
</script>
</body>
</html>
