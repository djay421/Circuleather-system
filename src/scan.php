<?php
require 'auth.php';
vereisLogin();
require 'functies.php';

$errors = [];
$melding = '';
$code = trim((string)($_GET['code'] ?? ''));
$item = null;
$kenmerken = [];
$samples = [];
$labelInfo = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim((string)($_POST['code'] ?? ''));
    $status = (string)($_POST['status'] ?? '');
    if ($code === '') {
        $errors[] = 'Voer een code in.';
    }
    if (!in_array($status, STATUSSEX, true)) {
        $errors[] = 'Ongeldige status.';
    }
    if (empty($errors)) {
        $stmt = $pdo->prepare('UPDATE voorraad SET status = ? WHERE code = ?');
        $stmt->execute([$status, $code]);
        if ($stmt->rowCount() > 0) {
            $melding = 'Status bijgewerkt naar "' . ucfirst(str_replace('_', ' ', $status)) . '".';
        } else {
            $errors[] = 'Geen voorraad gevonden met deze code.';
        }
    }
}

if ($code !== '' && empty($errors)) {
    $stmt = $pdo->prepare(
        'SELECT v.*, s.naam AS stad_naam, b.code AS bigbag_code
         FROM voorraad v
         LEFT JOIN steden s ON s.id = v.stad_id
         LEFT JOIN voorraad b ON b.id = v.bigbag_id
         WHERE v.code = ?'
    );
    $stmt->execute([$code]);
    $item = $stmt->fetch();

    if ($item) {
        $kenmerken = haalKenmerken($pdo, [$item['id']])[(int)$item['id']] ?? [];

        // Een bigbag toont meteen de samples die eruit zijn geregistreerd.
        if ($item['categorie'] === 'bigbag') {
            $stmt = $pdo->prepare(
                'SELECT id, code, categorie, status FROM voorraad WHERE bigbag_id = ? ORDER BY id DESC'
            );
            $stmt->execute([$item['id']]);
            $samples = $stmt->fetchAll();
        }
    } elseif ($code !== '') {
        // Onbekende code: is dit een eerder gegenereerd (voorbedrukt) label?
        $stmt = $pdo->prepare(
            'SELECT q.aangemaakt_op, u.naam AS gebruiker
             FROM qr_labels q
             LEFT JOIN gebruikers u ON u.id = q.gebruiker_id
             WHERE q.code = ?'
        );
        $stmt->execute([$code]);
        $labelInfo = $stmt->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Scannen — Circuleather</title>
    <link rel="stylesheet" href="style.css?v=3">
</head>
<body class="smal">
    <?php include 'nav.php'; ?>
    <a class="back-link" href="index.php">&larr; Terug naar overzicht</a>
    <h1>📷 Scannen</h1>

    <?php if (!empty($errors)): ?>
        <div class="fout"><ul>
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul></div>
    <?php endif; ?>

    <?php if ($melding): ?>
        <div class="msg"><?= htmlspecialchars($melding) ?></div>
    <?php endif; ?>

    <?php if ($item): ?>
        <div class="kaart">
            <h2>Gevonden: <?= htmlspecialchars(itemLabel($item)) ?></h2>
            <p class="meta">Deze code is al geregistreerd — bekijk de gegevens of werk de status bij.</p>
            <dl>
                <dt>Categorie</dt><dd><?= htmlspecialchars($item['categorie']) ?></dd>
                <?php if ($item['categorie'] === 'leersample' && $item['bigbag_code']): ?>
                    <dt>Afkomstig uit bigbag</dt>
                    <dd><a href="scan.php?code=<?= urlencode($item['bigbag_code']) ?>"><?= htmlspecialchars($item['bigbag_code']) ?></a></dd>
                <?php endif; ?>
                <?php if ($item['stad_naam']): ?><dt>Herkomststad</dt><dd><?= htmlspecialchars($item['stad_naam']) ?></dd><?php endif; ?>
                <?php if ($item['locatie']): ?><dt>Locatie</dt><dd><?= htmlspecialchars($item['locatie']) ?></dd><?php endif; ?>
                <?php if ($item['binnenkomst_datum']): ?><dt>Binnenkomst</dt><dd><?= htmlspecialchars($item['binnenkomst_datum']) ?></dd><?php endif; ?>
                <dt>Status</dt><dd><span class="badge <?= htmlspecialchars($item['status']) ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $item['status']))) ?></span></dd>
            </dl>

            <?php if (!empty($kenmerken)): ?>
                <dl>
                    <?php foreach ($kenmerken as $label => $teksten): ?>
                        <dt><?= htmlspecialchars($label) ?></dt>
                        <dd><?= htmlspecialchars(implode(', ', $teksten)) ?></dd>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>

            <?php if ($item['categorie'] === 'bigbag'): ?>
                <h2 style="margin-bottom:2px">Leersamples uit deze bigbag (<?= count($samples) ?>)</h2>
                <?php if (empty($samples)): ?>
                    <p class="meta">Nog geen samples geregistreerd uit deze bigbag.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($samples as $sample): ?>
                            <li>
                                <a href="edit.php?id=<?= (int)$sample['id'] ?>"><?= htmlspecialchars(itemLabel($sample)) ?></a>
                                — <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $sample['status']))) ?>
                                <?php if ($sample['code']): ?>
                                    (<a href="scan.php?code=<?= urlencode($sample['code']) ?>">bekijk</a>)
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <div class="knoppen">
                    <a href="add.php?categorie=leersample&bigbag_id=<?= (int)$item['id'] ?>">+ Leersample toevoegen uit deze bigbag</a>
                </div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="code" value="<?= htmlspecialchars($item['code']) ?>">
                <label for="status">Status bijwerken</label>
                <select id="status" name="status">
                    <?php foreach (STATUSSEX as $status): ?>
                        <option value="<?= $status ?>" <?= $item['status'] === $status ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $status))) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="knoppen">
                    <button type="submit">Status opslaan</button>
                </div>
            </form>

            <div class="knoppen">
                <a href="edit.php?id=<?= (int)$item['id'] ?>">Bewerken</a>
                <a class="secondary" href="scan.php">Opnieuw scannen</a>
            </div>
        </div>
    <?php else: ?>
        <p class="meta">Richt de camera op de QR-code van een bigbag.
        Een <strong>onbekende code</strong> is een nieuwe zak: die registreer je hierna direct
        als nieuwe bigbag. Een <strong>bekende code</strong> opent de geregistreerde bigbag.</p>

        <div class="viewfinder">
            <video id="camera" autoplay muted playsinline></video>
            <div class="scan-status" id="scan-status">Camera starten …</div>
        </div>

        <form class="handmatig" method="get" action="scan.php">
            <input type="text" name="code" value="<?= htmlspecialchars($code) ?>"
                   placeholder="Of voer de code handmatig in" required autocomplete="off">
            <button type="submit">Zoeken</button>
        </form>

        <?php if ($code !== ''): ?>
            <div class="kaart">
                <h2>Nieuwe zak: <?= htmlspecialchars($code) ?></h2>
                <p class="meta">Deze code staat nog <strong>niet</strong> in de voorraad — zoals een
                verse zaklabel die net binnenkomt. Registreer de bigbag bij inname: weeg de zak en
                noteer herkomststad, datum en inhoud.</p>
                <?php if ($labelInfo): ?>
                    <p class="meta">✓ Label is eerder gegenereerd
                    <?= $labelInfo['gebruiker'] ? 'door ' . htmlspecialchars($labelInfo['gebruiker']) . ' ' : '' ?>
                    op <?= htmlspecialchars(date('d-m-Y H:i', strtotime($labelInfo['aangemaakt_op']))) ?>.
                    Je kunt hem dus gerust registreren.</p>
                <?php endif; ?>
                <div class="knoppen">
                    <a href="add.php?categorie=bigbag&code=<?= urlencode($code) ?>">+ Registreer nieuwe bigbag</a>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($item === null): ?>
    <script>
        (function () {
            var video = document.getElementById('camera');
            var status = document.getElementById('scan-status');
            var stream = null;
            var detector = null;
            var bezig = false;

            function tekst(t) { if (status) status.textContent = t; }

            function stop() {
                if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
            }

            function scanLus() {
                if (!video.videoWidth || !detector) { return; }
                detector.detect(video).then(function (codes) {
                    if (!bezig && codes && codes.length) {
                        var raw = String(codes[0].rawValue || '').trim();
                        if (raw) {
                            bezig = true;
                            tekst('Gevonden: ' + raw);
                            stop();
                            window.location.href = 'scan.php?code=' + encodeURIComponent(raw);
                            return;
                        }
                    }
                    requestAnimationFrame(scanLus);
                }).catch(function () {
                    requestAnimationFrame(scanLus);
                });
            }

            async function start() {
                if (!('mediaDevices' in navigator && 'getUserMedia' in navigator.mediaDevices)) {
                    tekst('Camera niet beschikbaar in deze browser. Voer de code hieronder handmatig in.');
                    return;
                }
                try {
                    stream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: 'environment' }
                    });
                } catch (e) {
                    if (e.name === 'NotAllowedError') {
                        tekst('Cameratoestemming geweigerd. Sta de camera toe of voer de code handmatig in.');
                    } else if (e.name === 'SecurityError' || e.name === 'NotReadableError') {
                        tekst('Camera vereist een beveiligde verbinding (https) of is in gebruik. Voer de code handmatig in.');
                    } else {
                        tekst('Camera starten mislukt (' + e.name + '). Voer de code handmatig in.');
                    }
                    return;
                }
                video.srcObject = stream;
                try { await video.play(); } catch (e) { /* stille vangst */ }

                if (!('BarcodeDetector' in window)) {
                    tekst('Deze browser kan niet automatisch scannen. Gebruik het invoerveld hieronder.');
                    return;
                }
                try {
                    var gevraagd = ['qr_code', 'code_128', 'ean_13', 'ean_8', 'code_39',
                                    'upc_a', 'upc_e', 'codabar', 'itf',
                                    'data_matrix', 'aztec', 'pdf417'];
                    var ondersteund = await BarcodeDetector.getSupportedFormats();
                    var formats = gevraagd.filter(function (f) { return ondersteund.indexOf(f) !== -1; });
                    detector = formats.length
                        ? new BarcodeDetector({ formats: formats })
                        : new BarcodeDetector();
                } catch (e) {
                    detector = null;
                }
                if (!detector) {
                    tekst('Automatisch scannen niet ondersteund. Gebruik het invoerveld hieronder.');
                    return;
                }
                tekst('Richt de camera op de QR-code of streepjescode …');
                requestAnimationFrame(scanLus);
            }

            window.addEventListener('beforeunload', stop);
            start();
        })();
    </script>
    <?php endif; ?>
</body>
</html>
