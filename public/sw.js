/*
 * Service worker do LUX ERP (app instalável).
 * Segurança: NÃO guarda nenhuma página nem dado do sistema em cache.
 * Tudo vai direto para o servidor; só a tela "Sem conexão" e o ícone ficam
 * guardados, para aparecer quando a internet cair.
 */
const CACHE = 'lux-erp-offline-v1';
const OFFLINE = ['/offline.html', '/icons/icon-192.png'];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(OFFLINE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((ks) => Promise.all(ks.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;            // POST/PUT etc.: o navegador cuida normalmente
  if (req.mode === 'navigate') {               // abrir uma tela: sempre da rede
    e.respondWith(fetch(req).catch(() => caches.match('/offline.html')));
    return;
  }
  if (OFFLINE.includes(new URL(req.url).pathname)) {
    e.respondWith(caches.match(req).then((r) => r || fetch(req)));
  }
});
