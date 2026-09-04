/* Circuleather Leeropslag — service worker (PWA).
   - Maakt de app installeerbaar als app op telefoon/desktop (vereiste van
     Chrome/Android).
   - Elke pagina en elk fragment wordt altijd vers van de server opgehaald
     (network-first): live-updates en verse data blijven werken.
   - Stijl/scripts/icoontjes worden slim gecached voor snelheid en offline.
   - Zonder verbinding toont een navigatie de offline-pagina.
   Let op: na wijzigingen in dit bestand de versie hieronder verhogen,
   zodat oude caches worden opgeruimd. */

const CACHE = 'circuleather-v1';
const PRECACHE = ['offline.html'];
const ASSET = /\.(css|js|png|jpe?g|webp|gif|svg|woff2?|ttf|otf|webmanifest)(\?|$)/i;

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE).then(function (cache) {
            return cache.addAll(PRECACHE);
        }).then(function () {
            return self.skipWaiting();
        })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (namen) {
            return Promise.all(
                namen.filter(function (n) { return n !== CACHE; })
                     .map(function (n) { return caches.delete(n); })
            );
        }).then(function () {
            return self.clients.claim();
        })
    );
});

self.addEventListener('fetch', function (event) {
    var verzoek = event.request;
    if (verzoek.method !== 'GET') { return; }
    var url;
    try { url = new URL(verzoek.url); } catch (e) { return; }
    if (url.origin !== self.location.origin) { return; }

    // Pagina's en live-fragmenten: altijd eerst het netwerk (fragmenten
    // eindigen niet op een asset-extensie en worden dus nooit gecached).
    if (verzoek.mode === 'navigate') {
        event.respondWith(
            fetch(verzoek).catch(function () {
                return caches.match('offline.html');
            })
        );
        return;
    }

    // Stijl, scripts en icoontjes: eerst cache (snel), daarna op de
    // achtergrond de verse versie ophalen.
    if (ASSET.test(url.pathname)) {
        event.respondWith(versVanCacheOfNet(verzoek));
    }
});

function versVanCacheOfNet(verzoek) {
    return caches.open(CACHE).then(function (cache) {
        return cache.match(verzoek).then(function (hit) {
            var netwerk = fetch(verzoek).then(function (antwoord) {
                if (antwoord && antwoord.ok && antwoord.type === 'basic') {
                    cache.put(verzoek, antwoord.clone());
                }
                return antwoord;
            });
            return hit || netwerk;
        });
    });
}
