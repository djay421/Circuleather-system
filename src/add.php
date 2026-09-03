<?php
require 'auth.php';
vereisLogin();
require 'functies.php';

$criteria = haalCriteria($pdo);
$steden = haalSteden($pdo);
$bigbags = haalBigbags($pdo);

$errors = [];
$input = [
    'code' => trim((string)($_GET['code'] ?? '')),
    'categorie' => $_GET['categorie'] ?? 'leersample',
    'locatie' => '',
    'status' => 'beschikbaar',
    'stad_id' => '',
    'binnenkomst_datum' => '',
    'bigbag_id' => trim((string)($_GET['bigbag_id'] ?? '')),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($input as $key => $default) {
        $input[$key] = trim($_POST[$key] ?? $default);
    }

    if (!in_array($input['categorie'], CATEGORIEEN, true)) {
        $errors[] = 'Ongeldige categorie.';
    }

    // Een code is verplicht en uniek voor bigbags (de QR); een leersample
    // heeft geen QR, een eigen partij-nummer is alleen optioneel.
    if ($input['categorie'] === 'bigbag') {
        if ($input['code'] === '') {
            $errors[] = 'QR-code / partij-nummer is verplicht voor een bigbag.';
        } elseif (mb_strlen($input['code']) > 50) {
            $errors[] = 'Code mag maximaal 50 tekens bevatten.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM voorraad WHERE code = ?');
            $stmt->execute([$input['code']]);
            if ($stmt->fetch()) {
                $errors[] = 'Deze code bestaat al.';
            }
        }
        $input['bigbag_id'] = ''; // een bigbag hoort niet bij een andere bigbag
    } elseif ($input['code'] !== '') {
        if (mb_strlen($input['code']) > 50) {
            $errors[] = 'Code mag maximaal 50 tekens bevatten.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM voorraad WHERE code = ?');
            $stmt->execute([$input['code']]);
            if ($stmt->fetch()) {
                $errors[] = 'Deze code bestaat al.';
            }
        }
    }

    if (!in_array($input['status'], STATUSSEX, true)) {
        $errors[] = 'Ongeldige status.';
    }
    if ($input['binnenkomst_datum'] !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $input['binnenkomst_datum']);
        if (!$d || $d->format('Y-m-d') !== $input['binnenkomst_datum']) {
            $errors[] = 'Ongeldige binnenkomst-datum.';
        }
    }
    if ($input['stad_id'] !== '') {
        $input['stad_id'] = (string)(int)$input['stad_id'];
        if ((int)$input['stad_id'] > 0 && !isset($steden[(int)$input['stad_id']])) {
            $errors[] = 'Ongeldige herkomststad.';
        }
    } else {
        $input['stad_id'] = '';
    }
    if ($input['bigbag_id'] !== '') {
        $input['bigbag_id'] = (string)(int)$input['bigbag_id'];
        if (!isset($bigbags[(int)$input['bigbag_id']])) {
            $errors[] = 'Ongeldige bigbag.';
        }
    } else {
        $input['bigbag_id'] = '';
    }

    // Criteria-waarden uit POST lezen en valideren.
    $categorie = in_array($input['categorie'], CATEGORIEEN, true) ? $input['categorie'] : 'leersample';
    $waarden = leesPostWaarden($criteria, $categorie, $_POST);
    $errors = array_merge($errors, valideerCriteriaWaarden($waarden, $criteria));

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO voorraad (code, categorie, stad_id, bigbag_id, locatie, status, binnenkomst_datum)
             VALUES (:code, :categorie, :stad_id, :bigbag_id, :locatie, :status, :binnenkomst_datum)'
        );
        $stmt->execute([
            'code' => $input['code'] !== '' ? $input['code'] : null,
            'categorie' => $categorie,
            'stad_id' => $input['stad_id'] !== '' ? (int)$input['stad_id'] : null,
            'bigbag_id' => $categorie === 'leersample' && $input['bigbag_id'] !== ''
                ? (int)$input['bigbag_id']
                : null,
            'locatie' => $input['locatie'],
            'status' => $input['status'],
            'binnenkomst_datum' => $input['binnenkomst_datum'] !== '' ? $input['binnenkomst_datum'] : null,
        ]);
        bewaarCriteriaWaarden($pdo, (int)$pdo->lastInsertId(), $waarden);

        // Na het registreren terug naar het item (scan-aanzicht) zodat je
        // de gescande code direct bevestigd ziet.
        $doel = 'index.php?msg=added';
        if ($categorie === 'leersample' && $input['bigbag_id'] !== '') {
            $stmt = $pdo->prepare('SELECT code FROM voorraad WHERE id = ?');
            $stmt->execute([(int)$input['bigbag_id']]);
            $bagCode = $stmt->fetchColumn();
            if ($bagCode) {
                $doel = 'scan.php?code=' . rawurlencode($bagCode);
            }
        } elseif ($input['code'] !== '') {
            $doel = 'scan.php?code=' . rawurlencode($input['code']);
        }
        header('Location: ' . $doel);
        exit;
    }
}

$categorie = in_array($input['categorie'], CATEGORIEEN, true) ? $input['categorie'] : 'leersample';
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Circuleather — Voorraad toevoegen</title>
    <link rel="stylesheet" href="style.css?v=3">
</head>
<body>
    <?php include 'nav.php'; ?>
    <a class="back-link" href="index.php">&larr; Terug naar overzicht</a>
    <h1>Nieuwe voorraad toevoegen</h1>

    <?php if (!empty($errors)): ?>
        <div class="errors">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post">
        <label for="categorie">Categorie</label>
        <div class="radio-row">
            <label><input type="radio" name="categorie" value="bigbag" <?= $categorie === 'bigbag' ? 'checked' : '' ?> onchange="toonCategorie()"> Bigbag (heeft QR-code)</label>
            <label><input type="radio" name="categorie" value="leersample" <?= $categorie === 'leersample' ? 'checked' : '' ?> onchange="toonCategorie()"> Leersample (handmatig geregistreerd)</label>
        </div>

        <label for="code">Code <span id="code-vereist-label">(QR / partij-nummer van de bigbag) *</span></label>
        <input type="text" id="code" name="code" value="<?= htmlspecialchars($input['code']) ?>"
               placeholder="<?= $categorie === 'bigbag' ? 'Bijv. BB-2026-001' : 'Optioneel eigen nummer — samples hebben geen QR' ?>">

        <label for="locatie">Locatie</label>
        <input type="text" id="locatie" name="locatie" value="<?= htmlspecialchars($input['locatie']) ?>">

        <label for="status">Status</label>
        <select id="status" name="status">
            <?php foreach (STATUSSEX as $status): ?>
                <option value="<?= $status ?>" <?= $input['status'] === $status ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $status)) ?></option>
            <?php endforeach; ?>
        </select>

        <fieldset id="velden-bigbag">
            <legend>Bigbag</legend>
            <p class="meta">Een bigbag heeft een unieke QR/streepjescode en wordt bij binnenkomst
            geregistreerd: herkomststad, datum, gewicht en inhoud.</p>
            <label for="stad_id">Herkomststad</label>
            <select id="stad_id" name="stad_id">
                <option value="">— kies —</option>
                <?php foreach ($steden as $id => $naam): ?>
                    <option value="<?= $id ?>" <?= (string)$id === $input['stad_id'] ? 'selected' : '' ?>><?= htmlspecialchars($naam) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="binnenkomst_datum">Binnenkomst datum</label>
            <input type="date" id="binnenkomst_datum" name="binnenkomst_datum" value="<?= htmlspecialchars($input['binnenkomst_datum']) ?>">

            <?php toonCriteriaVelden($criteria, 'bigbag'); ?>
        </fieldset>

        <fieldset id="velden-leersample">
            <legend>Leersample</legend>
            <p class="meta">Leersamples worden handmatig geregistreerd met de selectiecriteria.
            Koppel ze aan de bigbag waar ze uit komen, zodat de herkomst bekend blijft.</p>

            <label for="bigbag_id">Afkomstig uit bigbag (optioneel)</label>
            <select id="bigbag_id" name="bigbag_id">
                <option value="">— geen / losse sample —</option>
                <?php foreach ($bigbags as $bid => $label): ?>
                    <option value="<?= $bid ?>" <?= $input['bigbag_id'] !== '' && (int)$input['bigbag_id'] === $bid ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>

            <?php toonCriteriaVelden($criteria, 'leersample'); ?>
        </fieldset>

        <button type="submit">Toevoegen</button>
    </form>

    <script>
        function toonCategorie() {
            var waarde = document.querySelector('input[name="categorie"]:checked').value;
            document.getElementById('velden-bigbag').style.display = waarde === 'bigbag' ? '' : 'none';
            document.getElementById('velden-leersample').style.display = waarde === 'leersample' ? '' : 'none';
            var codeVeld = document.getElementById('code');
            var label = document.getElementById('code-vereist-label');
            if (waarde === 'bigbag') {
                codeVeld.required = true;
                codeVeld.placeholder = 'Bijv. BB-2026-001';
                label.textContent = '(QR / partij-nummer van de bigbag) *';
            } else {
                codeVeld.required = false;
                codeVeld.placeholder = 'Optioneel eigen nummer — samples hebben geen QR';
                label.textContent = '(optioneel eigen nummer)';
            }
        }
        toonCategorie();
    </script>
</body>
</html>
