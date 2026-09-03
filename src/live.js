/* Live-updates zonder herladen: pollt elke paar seconden een 'fragment'-eindpunt
   en geeft het resultaat (JSON) aan de meegegeven verwerk-functie. Stopt
   automatisch met pollen als de tab op de achtergrond staat. */
function livePoll(maakUrl, verwerk, intervalMs) {
    var interval = intervalMs || 3000;
    var bezig = false;

    function tik() {
        if (document.hidden || bezig) { return; }
        bezig = true;
        var url = maakUrl();
        url += (url.indexOf('?') === -1 ? '?' : '&') + '_=' + Date.now();
        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && typeof verwerk === 'function') { verwerk(data); }
            })
            .catch(function () {
                // Niet ingelogd/geen verbinding: stil negeren; volgende poll probeert opnieuw.
            })
            .then(function () { bezig = false; });
    }

    setInterval(tik, interval);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) { tik(); }
    });
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