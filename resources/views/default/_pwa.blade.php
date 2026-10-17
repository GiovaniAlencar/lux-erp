{{-- App instalável (PWA): ícone, cor da janela e registro do service worker --}}
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#0b0b0c">
<meta name="application-name" content="LUX ERP">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="LUX ERP">
<meta name="apple-mobile-web-app-status-bar-style" content="black">
<link rel="icon" type="image/png" sizes="64x64" href="/icons/favicon-64.png">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function () {});
    });
}
</script>
