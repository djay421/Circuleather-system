<?php
require 'auth.php';
vereisAdmin();

$jaar = (string)date('Y');
$errors = [];
$melding = '';
$toonCodes = [];

/** Haalt de gebruikte volgnummers op voor BB-<jaar>-... (voorraad + eerder gedrukte labels). */
function gebruikteLabelNummers(PDO $pdo, string $jaar): array
{
    $nummers = [];
    $prefix = 'BB-' . $jaar . '-';
    foreach (['voorraad', 'qr_labels'] as $tabel) {
        $stmt = $pdo->prepare("SELECT code FROM {$tabel} WHERE code LIKE ?");
        $stmt->execute([$prefix . '%']);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $code) {
            if (preg_match('/^BB-\d{4}-(\d+)$/', (string)$code, $m)) {
                $nummers[] = (int)$m[1];
            }
        }
    }
    return $nummers;
}

/** Geeft de volgende vrije labelcodes: BB-<jaar>-<volgnummer>. */
function volgendeLabelCodes(PDO $pdo, string $jaar, int $aantal): array
{
    $max = 0;
    foreach (gebruikteLabelNummers($pdo, $jaar) as $n) {
        if ($n > $max) {
            $max = $n;
        }
    }
    $codes = [];
    for ($i = 1; $i <= $aantal; $i++) {
        $codes[] = 'BB-' . $jaar . '-' . str_pad((string)($max + $i), 3, '0', STR_PAD_LEFT);
    }
    return $codes;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aantal = (int)($_POST['aantal'] ?? 0);
    if ($aantal < 1 || $aantal > 50) {
        $errors[] = 'Kies een aantal tussen 1 en 50.';
    } else {
        $codes = volgendeLabelCodes($pdo, $jaar, $aantal);
        $ins = $pdo->prepare('INSERT INTO qr_labels (code, gebruiker_id) VALUES (?, ?)');
        $gebruikerId = (int)ingelogdeGebruiker()['id'];
        $pdo->beginTransaction();
        try {
            foreach ($codes as $code) {
                $ins->execute([$code, $gebruikerId]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'Genereren mislukt — de nummers zijn waarschijnlijk net gebruikt. Probeer opnieuw.';
            $codes = [];
        }
        if (empty($errors)) {
            $toonCodes = $codes;
            $melding = count($codes) . ' label(s) gegenereerd: ' . implode(', ', $codes)
                . '. Print ze, plak een label op een lege bigbag en scan de code bij inname.';
        }
    }
}

// Een eerder gegenereerd label opnieuw tonen om te printen.
$herprint = trim((string)($_GET['herprint'] ?? ''));
if ($herprint !== '' && empty($toonCodes)) {
    $stmt = $pdo->prepare('SELECT code FROM qr_labels WHERE code = ?');
    $stmt->execute([$herprint]);
    if ($stmt->fetchColumn()) {
        $toonCodes = [$herprint];
        $melding = 'Label ' . $herprint . ' opnieuw weergegeven om te printen.';
    } else {
        header('Location: labels.php');
        exit;
    }
}

$geschiedenis = $pdo->query(
    'SELECT q.code, q.aangemaakt_op, u.naam AS gebruiker
     FROM qr_labels q
     LEFT JOIN gebruikers u ON u.id = q.gebruiker_id
     ORDER BY q.aangemaakt_op DESC, q.id DESC
     LIMIT 60'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QR-labels — Circuleather</title>
    <link rel="stylesheet" href="style.css?v=4">
    <style>
        .qr-aantal { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 10px; }
        .qr-aantal label { margin: 0; }
        .qr-aantal input { width: 110px; margin-top: 0; }
        .qr-aantal button { margin-top: 0; }

        .qr-sheet {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
            margin: 10px 0 26px;
        }
        .qr-label {
            background: var(--wit);
            border: 1px solid #b9a98c;
            border-radius: 14px;
            padding: 12px 14px 10px;
            break-inside: avoid;
        }
        .qr-label .merk {
            font-size: 9.5px;
            letter-spacing: .18em;
            text-transform: uppercase;
            color: var(--leer);
            font-weight: 700;
        }
        .qr-label .code {
            font-family: Georgia, serif;
            font-size: 21px;
            font-weight: 700;
            color: var(--ink);
            margin: 2px 0 6px;
        }
        .qr-label .qr-vak {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            max-width: 200px;
            margin: 0 auto 6px;
            background: #fff;
            border-radius: 8px;
            padding: 4px;
        }
        .qr-label .qr-vak svg { width: 100%; height: auto; display: block; }
        .qr-label .invul {
            border: 1px dashed #c9b896;
            border-radius: 8px;
            padding: 2px 8px 6px;
        }
        .qr-label .invul div {
            font-size: 10.5px;
            color: var(--mild);
            border-bottom: 1px dotted #ddd0ba;
            padding: 3px 0 2px;
            min-height: 15px;
        }
        .qr-label .invul div:last-child { border-bottom: 0; }

        @media print {
            body { max-width: none; padding: 0; background: #fff; }
            .sitekop, .niet-printen { display: none !important; }
            .qr-sheet { margin: 0; gap: 5mm; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); }
            .qr-label { border-radius: 3mm; }
            @page { size: A4 portrait; margin: 8mm; }
        }
    </style>
</head>
<body>
    <?php include 'nav.php'; ?>

    <div class="niet-printen">
        <a class="back-link" href="index.php">&larr; Terug naar voorraad</a>
        <h1>QR-labels voor bigbags</h1>
        <p class="meta">Maak hier voorbedrukte zaklabels: de app kiest de eerstvolgende vrije nummers
        (<code>BB-2026-&hellip;</code>), zodat een nummer nooit twee keer wordt gedrukt. Print de labels,
        plak er één op een lege bigbag en scan de code bij inname (📷 Scannen) — dan wordt de zak
        als nieuwe bigbag geregistreerd. Een gegenereerd label staat nog <strong>niet</strong> in de voorraad.</p>

        <?php if (!empty($errors)): ?>
            <div class="errors"><ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul></div>
        <?php endif; ?>

        <?php if ($melding): ?>
            <div class="msg"><?= htmlspecialchars($melding) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="qr-aantal">
                <label for="aantal">Aantal nieuwe labels (1–50)</label>
                <input type="number" id="aantal" name="aantal" min="1" max="50" value="10" required>
                <button type="submit">Labels genereren</button>
            </div>
        </form>
    </div>

    <?php if (!empty($toonCodes)): ?>
        <div class="knoppen niet-printen">
            <button type="button" onclick="window.print()">🖨 Print deze labels</button>
            <a class="secondary" href="labels.php">Opnieuw beginnen</a>
        </div>

        <section class="qr-sheet">
            <?php foreach ($toonCodes as $code): ?>
                <div class="qr-label">
                    <div class="merk">Circuleather · Leeropslag</div>
                    <div class="code"><?= htmlspecialchars($code) ?></div>
                    <div class="qr-vak" data-code="<?= htmlspecialchars($code) ?>"></div>
                    <div class="invul">
                        <div>Herkomststad:</div>
                        <div>Binnenkomst datum:</div>
                        <div>Gewicht (kg):</div>
                        <div>Inhoud: leersample / restleer</div>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <?php if (!empty($geschiedenis)): ?>
        <div class="niet-printen">
            <h2>Eerder gegenereerde labels</h2>
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Gegenereerd op</th>
                        <th>Door</th>
                        <th>Actie</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($geschiedenis as $r): ?>
                        <tr>
                            <td data-label="Code"><code><?= htmlspecialchars($r['code']) ?></code></td>
                            <td data-label="Gegenereerd op"><?= htmlspecialchars(date('d-m-Y H:i', strtotime($r['aangemaakt_op']))) ?></td>
                            <td data-label="Door"><?= htmlspecialchars($r['gebruiker'] ?? '—') ?></td>
                            <td data-label="Actie" class="acties"><a href="labels.php?herprint=<?= urlencode($r['code']) ?>">Herprint</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <script src="qrcode.min.js"></script>
    <script>
        (function () {
            var vakken = document.querySelectorAll('.qr-vak');
            if (!vakken.length) { return; }
            if (typeof qrcode !== 'function') {
                for (var i = 0; i < vakken.length; i++) {
                    vakken[i].textContent = '(QR-bibliotheek ontbreekt)';
                }
                return;
            }
            for (var j = 0; j < vakken.length; j++) {
                var vak = vakken[j];
                try {
                    var qr = qrcode(0, 'M');
                    qr.addData(vak.getAttribute('data-code'));
                    qr.make();
                    vak.innerHTML = qr.createSvgTag(3, 0);
                } catch (e) {
                    vak.textContent = '(QR maken mislukt)';
                }
            }
        })();
    </script>
</body>
</html>
