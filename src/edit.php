<?php
require 'auth.php';
vereisLogin();
require 'functies.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM voorraad WHERE id = ?');
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    header('Location: index.php');
    exit;
}

$criteria = haalCriteria($pdo);
$steden = haalSteden($pdo);
$bigbags = haalBigbags($pdo);
$categorie = $item['categorie'];

$errors = [];
$input = [
    'code' => $item['code'] ?? '',
    'locatie' => $item['locatie'] ?? '',
    'status' => $item['status'],
    'stad_id' => $item['stad_id'] !== null ? (string)$item['stad_id'] : '',
    'binnenkomst_datum' => $item['binnenkomst_datum'] ?? '',
    'bigbag_id' => $item['bigbag_id'] !== null ? (string)$item['bigbag_id'] : '',
];
$waarden = haalWaardenVoorItem($pdo, $id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($input as $key => $default) {
        $input[$key] = trim($_POST[$key] ?? $default);
    }

    // Zelfde regels als bij toevoegen: code verplicht voor een bigbag,
    // optioneel voor een leersample; een sample hoort bij hooguit één bigbag.
    if ($categorie === 'bigbag') {
        if ($input['code'] === '') {
            $errors[] = 'QR-code / partij-nummer is verplicht voor een bigbag.';
        } elseif (mb_strlen($input['code']) > 50) {
            $errors[] = 'Code mag maximaal 50 tekens bevatten.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM voorraad WHERE code = ? AND id <> ?');
            $stmt->execute([$input['code'], $id]);
            if ($stmt->fetch()) {
                $errors[] = 'Deze code bestaat al.';
            }
        }
        $input['bigbag_id'] = '';
    } elseif ($input['code'] !== '') {
        if (mb_strlen($input['code']) > 50) {
            $errors[] = 'Code mag maximaal 50 tekens bevatten.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM voorraad WHERE code = ? AND id <> ?');
            $stmt->execute([$input['code'], $id]);
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
    if ($input['stad_id'] !== '' && !isset($steden[(int)$input['stad_id']])) {
        $errors[] = 'Ongeldige herkomststad.';
    }
    if ($categorie === 'leersample' && $input['bigbag_id'] !== ''
        && !isset($bigbags[(int)$input['bigbag_id']])) {
        $errors[] = 'Ongeldige bigbag.';
    }

    $waarden = leesPostWaarden($criteria, $categorie, $_POST);
    $errors = array_merge($errors, valideerCriteriaWaarden($waarden, $criteria));

    // Foto: vervangen met een nieuw bestand, of verwijderen.
    $fotoWaarde = $item['foto'] ?? null;
    if (empty($errors) && $categorie === 'leersample') {
        if (!empty($_FILES['foto']['name'] ?? '')) {
            [$nieuwPad, $fout] = verwerkFotoUpload($_FILES['foto'] ?? []);
            if ($fout) {
                $errors[] = $fout;
            } else {
                $fotoWaarde = $nieuwPad;
            }
        } elseif (!empty($_POST['verwijder_foto']) && $fotoWaarde) {
            verwijderFotoBestand($fotoWaarde);
            $fotoWaarde = null;
        }
    }

    if (empty($errors)) {
        if ($fotoWaarde !== ($item['foto'] ?? null) && $fotoWaarde !== null) {
            verwijderFotoBestand($item['foto'] ?? null); // oude foto opruimen bij vervangen
        }
        $stmt = $pdo->prepare(
            'UPDATE voorraad
             SET code = :code, stad_id = :stad_id, bigbag_id = :bigbag_id,
                 locatie = :locatie, status = :status, foto = :foto,
                 binnenkomst_datum = :binnenkomst_datum
             WHERE id = :id'
        );
        $stmt->execute([
            'code' => $input['code'] !== '' ? $input['code'] : null,
            'stad_id' => $input['stad_id'] !== '' ? (int)$input['stad_id'] : null,
            'bigbag_id' => $categorie === 'leersample' && $input['bigbag_id'] !== ''
                ? (int)$input['bigbag_id']
                : null,
            'locatie' => $input['locatie'],
            'status' => $input['status'],
            'foto' => $fotoWaarde,
            'binnenkomst_datum' => $input['binnenkomst_datum'] !== '' ? $input['binnenkomst_datum'] : null,
            'id' => $id,
        ]);
        bewaarCriteriaWaarden($pdo, $id, $waarden);
        logActie($pdo, 'bewerkt', ($categorie === 'bigbag' ? 'Bigbag ' : 'Leersample ') . itemLabel($item) . ' bewerkt');

        header('Location: index.php?msg=updated');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <?php $titel = 'Circuleather — Voorraad bewerken'; ?>
    <?php include 'head.php'; ?>
</head>
<body>
    <?php include 'nav.php'; ?>
    <a class="back-link" href="index.php">&larr; Terug naar overzicht</a>
    <h1>Voorraad bewerken — <?= htmlspecialchars(itemLabel($item)) ?> (<?= $categorie ?>)</h1>

    <?php if (!empty($errors)): ?>
        <div class="errors">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="categorie" value="<?= htmlspecialchars($categorie) ?>">

        <label for="code">Code <?= $categorie === 'bigbag' ? '(QR / partij-nummer) *' : '(optioneel eigen nummer)' ?></label>
        <input type="text" id="code" name="code" value="<?= htmlspecialchars($input['code']) ?>"
               <?= $categorie === 'bigbag' ? 'required' : '' ?>
               placeholder="<?= $categorie === 'bigbag' ? 'Bijv. BB-2026-001' : 'Samples hebben geen QR-code' ?>">

        <label for="locatie">Locatie</label>
        <input type="text" id="locatie" name="locatie" value="<?= htmlspecialchars($input['locatie']) ?>">

        <label for="status">Status</label>
        <select id="status" name="status">
            <?php foreach (STATUSSEX as $status): ?>
                <option value="<?= $status ?>" <?= $input['status'] === $status ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $status)) ?></option>
            <?php endforeach; ?>
        </select>

        <?php if ($categorie === 'bigbag'): ?>
            <fieldset>
                <legend>Bigbag</legend>
                <label for="stad_id">Herkomststad</label>
                <select id="stad_id" name="stad_id">
                    <option value="">— kies —</option>
                    <?php foreach ($steden as $sid => $naam): ?>
                        <option value="<?= $sid ?>" <?= $input['stad_id'] !== '' && (int)$input['stad_id'] === $sid ? 'selected' : '' ?>><?= htmlspecialchars($naam) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="binnenkomst_datum">Binnenkomst datum</label>
                <input type="date" id="binnenkomst_datum" name="binnenkomst_datum" value="<?= htmlspecialchars($input['binnenkomst_datum']) ?>">

                <?php toonCriteriaVelden($criteria, 'bigbag', $waarden); ?>
            </fieldset>
        <?php else: ?>
            <fieldset>
                <legend>Leersample</legend>            <p class="meta">Koppel dit sample aan de bigbag waar het uit komt (optioneel),
            zodat herkomststad en datum herleidbaar blijven.</p>

            <?php if (!empty($item['foto'])): ?>
                <p class="meta"><img src="<?= htmlspecialchars($item['foto']) ?>" alt="Huidige foto" style="max-width:100%;max-height:190px;border-radius:12px;border:1px solid var(--lijn);display:block;margin-top:8px"></p>
            <?php endif; ?>
            <label for="foto">Foto <?= empty($item['foto']) ? '(optioneel)' : '(vervang huidige foto)' ?></label>
            <input type="file" id="foto" name="foto" accept="image/*">
            <?php if (!empty($item['foto'])): ?>
                <label class="optie" style="margin-top:10px"><input type="checkbox" name="verwijder_foto" value="1"> Foto verwijderen</label>
            <?php endif; ?>

                <label for="bigbag_id">Afkomstig uit bigbag (optioneel)</label>
                <select id="bigbag_id" name="bigbag_id">
                    <option value="">— geen / losse sample —</option>
                    <?php foreach ($bigbags as $bid => $label): ?>
                        <option value="<?= $bid ?>" <?= $input['bigbag_id'] !== '' && (int)$input['bigbag_id'] === $bid ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>

                <?php toonCriteriaVelden($criteria, 'leersample', $waarden); ?>
            </fieldset>
        <?php endif; ?>

        <div class="form-acties">
            <a href="index.php">Annuleren</a>
            <button type="submit">Opslaan</button>
        </div>
    </form>
</body>
</html>
