<?php
require 'auth.php';
vereisLogin();
require 'functies.php';

$berichten = [
    'added' => 'Voorraad toegevoegd.',
    'updated' => 'Voorraad bijgewerkt.',
    'deleted' => 'Voorraad verwijderd.',
];
$msg = $_GET['msg'] ?? '';

$items = $pdo->query(
    'SELECT v.*, s.naam AS stad_naam, b.code AS bigbag_code
     FROM voorraad v
     LEFT JOIN steden s ON s.id = v.stad_id
     LEFT JOIN voorraad b ON b.id = v.bigbag_id
     ORDER BY v.aangemaakt_op DESC, v.id DESC'
)->fetchAll();
$kenmerken = haalKenmerken($pdo, array_map(fn ($i) => (int)$i['id'], $items));
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Circuleather — Voorraad</title>
    <link rel="stylesheet" href="style.css?v=3">
</head>
<body>
    <?php include 'nav.php'; ?>
    <h1>Voorraad <small><?= count($items) ?> items</small></h1>

    <?php if (isset($berichten[$msg])): ?>
        <div class="msg"><?= htmlspecialchars($berichten[$msg]) ?></div>
    <?php endif; ?>

    <div class="knoppen">
        <a href="add.php?categorie=bigbag">+ Bigbag toevoegen</a>
        <a href="add.php?categorie=leersample" class="secondary">+ Leersample toevoegen</a>
        <a href="scan.php" class="secondary">📷 Scan met camera</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>Code</th>
                <th>Categorie</th>
                <th>Herkomst</th>
                <th>Locatie</th>
                <th>Kenmerken</th>
                <th>Status</th>
                <th>Acties</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($items)): ?>
            <tr><td colspan="7" class="lege-tabel">Nog geen voorraad toegevoegd.</td></tr>
        <?php else: foreach ($items as $v): $itemKenmerken = $kenmerken[(int)$v['id']] ?? []; ?>
            <tr>
                <td data-label="Code"><?= htmlspecialchars(itemLabel($v)) ?></td>
                <td data-label="Categorie"><?= htmlspecialchars($v['categorie']) ?></td>
                <td data-label="Herkomst"><?php
                    if ($v['categorie'] === 'bigbag') {
                        echo $v['stad_naam'] ? htmlspecialchars($v['stad_naam']) : '—';
                    } else {
                        echo $v['bigbag_code'] ? 'Uit: ' . htmlspecialchars($v['bigbag_code']) : '—';
                    }
                ?></td>
                <td data-label="Locatie"><?= $v['locatie'] ? htmlspecialchars($v['locatie']) : '—' ?></td>
                <td class="kenmerken" data-label="Kenmerken">
                    <?php if (empty($itemKenmerken)): ?>
                        <em>geen criteria ingevuld</em>
                    <?php else: foreach ($itemKenmerken as $label => $teksten): ?>
                        <div><strong><?= htmlspecialchars($label) ?>:</strong>
                            <?= htmlspecialchars(implode(', ', $teksten)) ?></div>
                    <?php endforeach; endif; ?>
                </td>
                <td data-label="Status"><span class="badge <?= htmlspecialchars($v['status']) ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $v['status']))) ?></span></td>
                <td class="acties" data-label="Acties">
                    <a href="edit.php?id=<?= (int)$v['id'] ?>">Bewerken</a>
                    <a class="wissen" href="delete.php?id=<?= (int)$v['id'] ?>"
                       onclick="return confirm('Weet je zeker dat je deze voorraad wilt verwijderen?');">Verwijderen</a>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</body>
</html>
