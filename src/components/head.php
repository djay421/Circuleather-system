<?php
// Gedeelde <head>-inhoud voor alle pagina's (meta, PWA, stijl).
$titel = $titel ?? 'Circuleather — Leeropslag';
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#ee5a48">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Leeropslag">
<link rel="manifest" href="manifest.webmanifest">
<link rel="icon" href="icon-192.png" type="image/png">
<link rel="apple-touch-icon" href="apple-touch-icon.png">
<title><?= htmlspecialchars($titel) ?></title>
<link rel="stylesheet" href="style.css?v=9">
<script src="live.js?v=9"></script>
<script>
/* PWA: service worker registreren — maakt de app installeerbaar en toont
   bij verbindingsverlies een offline-pagina. Registratie is eenmalig en
   werkt op elke https-omgeving (localhost, InfinityFree, tunnel). */
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('sw.js').catch(function () {});
    });
}
</script>