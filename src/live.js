/* Live-updates zonder herladen: pollt elke paar seconden een 'fragment'-eindpunt
   en geeft het resultaat (JSON) aan de meegegeven verwerk-functie. Werkt op
   elke gewone PHP-hosting (InfinityFree enz.): het is een licht https-verzoekje
   per 3 seconden, niets bijzonders op de server nodig.

   - Stopt met pollen als de tab op de achtergrond staat; zodra je terugkeert
     (zichtbaar, focus, online) volgt direct een controle.
   - Lukt er drie keer achter elkaar niets (geen verbinding of niet meer
     ingelogd), dan wordt het groene 'live'-stipje grijs als teken dat de
     automatische verversing even stil ligt; daarna wordt er gewoon verder
     geprobeerd. */
function livePoll(maakUrl, verwerk, intervalMs) {
    var interval = intervalMs || 3000;
    var bezig = false;
    var mislukt = 0;

    function stelStatus(ok) {
        var stippen = document.querySelectorAll('.live-stip');
        for (var i = 0; i < stippen.length; i++) {
            if (ok) {
                stippen[i].classList.remove('live-onderbroken');
            } else {
                stippen[i].classList.add('live-onderbroken');
            }
        }
    }

    function tik() {
        if (document.hidden || bezig) { return; }
        bezig = true;
        var url = maakUrl();
        url += (url.indexOf('?') === -1 ? '?' : '&') + '_=' + Date.now();
        fetch(url, { cache: 'no-store' })
            .then(function (r) {
                if (!r.ok) { throw new Error('HTTP ' + r.status); }
                return r.json();
            })
            .then(function (data) {
                mislukt = 0;
                stelStatus(true);
                if (data && typeof verwerk === 'function') { verwerk(data); }
            })
            .catch(function () {
                // Geen verbinding / niet ingelogd / tijdelijk probleem:
                // stil doorgaan; na drie keer achter elkaar het stipje grijs maken.
                mislukt += 1;
                if (mislukt >= 3) { stelStatus(false); }
            })
            .then(function () { bezig = false; });
    }

    setInterval(tik, interval);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) { tik(); }
    });
    window.addEventListener('focus', tik);
    window.addEventListener('pageshow', tik);
    window.addEventListener('online', tik);
    tik();
}

/* Algemene app-hulp: succesmeldingen als toast, FAB en inklapbare filters. */
function initApp() {
    // Succesmelding (.msg) verschijnt als toast en verdwijnt vanzelf.
    var msgs = document.querySelectorAll('.msg');
    for (var i = 0; i < msgs.length; i++) {
        (function (el) {
            setTimeout(function () {
                el.classList.add('verdwijn');
                setTimeout(function () { if (el.parentNode) { el.parentNode.removeChild(el); } }, 380);
            }, 4200);
        })(msgs[i]);
    }

    // Zwevende actieknop (+): openen/sluiten, en sluiten bij klik erbuiten.
    var fab = document.getElementById('fab');
    var fabKnop = fab ? fab.querySelector('.fab-knop') : null;
    if (fab && fabKnop) {
        fabKnop.addEventListener('click', function () { fab.classList.toggle('open'); });
        document.addEventListener('click', function (e) {
            if (fab.classList.contains('open') && !fab.contains(e.target)) {
                fab.classList.remove('open');
            }
        });
    }

    // Inklapbare filterbalk op telefoon.
    var knoppen = document.querySelectorAll('.filters-knop');
    for (var j = 0; j < knoppen.length; j++) {
        knoppen[j].addEventListener('click', function () {
            var blok = this.closest('.filterblok');
            if (blok) { blok.classList.toggle('open'); }
        });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initApp);
} else {
    initApp();
}

/* PWA-installatie: zodra de browser zegt dat de app installeerbaar is
   (beforeinstallprompt, Android/Chrome/Edge) tonen we een eigen banner met
   een Installeren-knop — zo hoef je nooit in het browsermenu te zoeken en
   mis je de 'app installeren'-melding niet meer. Op iPhone/iPad bestaat
   zo'n prompt niet: daar is 'Zet op beginscherm' de enige (en normale) weg,
   en opent de app daarna met eigen icoon in eigen venster. */
(function () {
    var promptGebeurtenis = null;
    var nietVragen = false;

    function alGeinstalleerd() {
        return window.matchMedia('(display-mode: standalone)').matches
            || window.navigator.standalone === true;
    }

    function toonBanner() {
        if (nietVragen || alGeinstalleerd()) { return; }
        var b = document.getElementById('pwa-banner');
        if (b) { b.hidden = false; }
    }

    function verbergBanner() {
        var b = document.getElementById('pwa-banner');
        if (b) { b.hidden = true; }
    }

    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault(); // geen dubbele melding: wij tonen zelf de banner
        promptGebeurtenis = e;
        toonBanner();
    });

    window.addEventListener('appinstalled', function () {
        promptGebeurtenis = null;
        verbergBanner();
    });

    document.addEventListener('click', function (e) {
        var t = e.target;
        if (!t || !t.id) { return; }
        if (t.id === 'pwa-installeer') {
            if (promptGebeurtenis && typeof promptGebeurtenis.prompt === 'function') {
                var ev = promptGebeurtenis;
                promptGebeurtenis = null;
                ev.prompt();
                if (ev.userChoice) {
                    ev.userChoice.then(function (keuze) {
                        if (!keuze || keuze.outcome !== 'accepted') {
                            nietVragen = true; // bewust geweigerd: deze sessie niet opnieuw vragen
                        }
                        verbergBanner();
                    });
                }
            } else {
                nietVragen = true;
                verbergBanner();
            }
        } else if (t.id === 'pwa-later') {
            nietVragen = true; // deze sessie niet meer tonen
            verbergBanner();
        }
    });
})();
