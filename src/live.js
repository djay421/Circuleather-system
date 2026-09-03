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
