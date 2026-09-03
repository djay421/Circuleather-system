<?php
require 'auth.php';
vereisLogin();
require 'functies.php';

$errors = [];
$melding = '';

// Verkoop registreren: status → 'verkocht' + regel in `verkopen`.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verkoop'])) {
    $id = (int)$_POST['verkoop'];
    $stmt = $pdo->prepare("SELECT id, status FROM voorraad WHERE id = ? AND categorie = 'leersample'");
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    if (!$item) {
        $errors[] = 'Sample niet gevonden.';
    } elseif (!in_array($item['status'], ['beschikbaar', 'gereserveerd'], true)) {
        $errors[] = 'Deze sample is al verkocht (of in bewerking).';
    } else {
        $upd = $pdo->prepare("UPDATE voorraad SET status = 'verkocht' WHERE id = ?");
        $upd->execute([$id]);
        $ins = $pdo->prepare('INSERT INTO verkopen (voorraad_id, gebruiker_id) VALUES (?, ?)');
        $ins->execute([$id, (int)ingelogdeGebruiker()['id']]);
        $melding = 'Sample verkocht en uit de voorraad gehaald.';
    }
}

// Verkoop ongedaan maken: status terug naar 'beschikbaar' + regel verwijderen.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ongedaan'])) {
    $id = (int)$_POST['ongedaan'];
    $stmt = $pdo->prepare("SELECT id, status FROM voorraad WHERE id = ? AND categorie = 'leersample'");
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    if ($item && $item['status'] === 'verkocht') {
        $upd = $pdo->prepare("UPDATE voorraad SET status = 'beschikbaar' WHERE id = ?");
        $upd->execute([$id]);
        $del = $pdo->prepare('DELETE FROM verkopen WHERE voorraad_id = ? ORDER BY id DESC LIMIT 1');
        $del->execute([$id]);
        $melding = 'Verkoop ongedaan gemaakt; de sample is weer beschikbaar.';
    } else {
        $errors[] = 'Alleen verkochte samples kunnen worden teruggezet.';
    }
}

// Filters
$toon = (string)($_GET['toon'] ?? 'tekoop');
if (!in_array($toon, ['tekoop', 'alles', 'verkocht'], true)) {
    $toon = 'tekoop';
}
$kleur = trim((string)($_GET['kleur'] ?? ''));
$zoek = trim((string)($_GET['zoek'] ?? ''));

$kleuren = [];
$stmt = $pdo->query(
    "SELECT co.waarde FROM criteria c
     JOIN criteria_opties co ON co.criterium_id = c.id
     WHERE c.toepassing = 'leersample' AND c.label = 'Kleurcategorie' AND c.actief = 1 AND co.actief = 1
     ORDER BY co.volgorde, co.id"
);
foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $waarde) {
    $kleuren[] = $waarde;
}

$where = ["v.categorie = 'leersample'"];
$params = [];
if ($toon === 'tekoop') {
    $where[] = "v.status IN ('beschikbaar', 'gereserveerd')";
} elseif ($toon === 'verkocht') {
    $where[] = "v.status = 'verkocht'";
}
if ($kleur !== '') {
    $where[] = "EXISTS (SELECT 1 FROM voorraad_criteria vc2
                JOIN criteria_opties co2 ON co2.id = vc2.optie_id
                WHERE vc2.voorraad_id = v.id AND co2.waarde = ?)";
    $params[] = $kleur;
}
if ($zoek !== '') {
    $where[] = "(v.code LIKE ? OR v.opmerking LIKE ?)";
    $params[] = '%' . $zoek . '%';
    $params[] = '%' . $zoek . '%';
}
$in = implode(' AND ', $where);

$stmt = $pdo->prepare(
    "SELECT v.id, v.code, v.categorie, v.status, v.opmerking, b.code AS bigbag_code,
            vk.verkocht_op, vk.gebruiker_id, g.naam AS verkoper
     FROM voorraad v
     LEFT JOIN voorraad b ON b.id = v.bigbag_id
     LEFT JOIN (SELECT v1.* FROM verkopen v1
                JOIN (SELECT voorraad_id, MAX(id) AS maxid FROM verkopen GROUP BY voorraad_id) v2
                  ON v2.maxid = v1.id) vk ON vk.voorraad_id = v.id
     LEFT JOIN gebruikers g ON g.id = vk.gebruiker_id
     WHERE $in
     ORDER BY v.status = 'verkocht', v.aangemaakt_op DESC, v.id DESC"
);
$stmt->execute($params);
$items = $stmt->fetchAll();
$kenmerken = haalKenmerken($pdo, array_map(fn ($i) => (int)$i['id'], $items));

$aantalTeKoop = (int)$pdo->query("SELECT COUNT(*) FROM voorraad WHERE categorie = 'leersample' AND status IN ('beschikbaar','gereserveerd')")->fetchColumn();
$aantalVerkocht = (int)$pdo->query("SELECT COUNT(*) FROM voorraad WHERE categorie = 'leersample' AND status = 'verkocht'")->fetchColumn();

/** Kleurvlak voor een leerstaal op basis van de kleurcategorie. */
function leerStaal(string $naam): string
{
    $kaart = [
        'zwart' => '#26231f', 'wit' => '#f4f1e8', 'grijs' => '#8f8b84',
        'bruin' => '#7c5232', 'beige' => '#d8c5a2', 'crème' => '#ece0c6',
        'blauw' => '#41566e', 'groen' => '#59633e', 'rood' => '#8f3f2c',
        'bordeaux' => '#5f2430', 'geel' => '#c9a23e', 'mosterd' => '#b08a2c',
        'oranje' => '#b86a2f', 'roze' => '#c08a7d', 'paars' => '#5c4a6b',
        'antraciet' => '#4a4a4c', 'naturel' => '#cbb088',
    ];
    foreach ($kaart as $sleutel => $hex) {
        if (mb_stripos($naam, $sleutel) !== false) {
            return $hex;
        }
    }
    return '#b3a38c';
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Galerij — Circuleather</title>
    <link rel="stylesheet" href="style.css?v=3">
    <style>
        .g-tel { color: var(--mild); font-size: 14px; }
        .g-filters { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; background: var(--wit); border: 1px solid var(--lijn); border-radius: var(--radius); padding: 14px; margin: 6px 0 16px; }
        .g-filters form { display: contents; }
        .g-filters form input[type="search"] { flex: 1 1 200px; max-width: 340px; }
        .g-filters select, .g-filters input { width: auto; margin-top: 0; min-width: 160px; }
        .g-filters form button { margin-top: 0; width: auto; }
        .g-voet form { background: transparent; box-shadow: none; border: 0; padding: 0; margin: 0; max-width: none; }
        .g-voet button { margin-top: 0; width: 100%; }
        .g-filters .segm { display: flex; border: 1.5px solid var(--lijn); border-radius: 999px; overflow: hidden; }
        .g-filters .segm a { padding: 9px 16px; font-size: 14px; font-weight: 600; color: var(--mild); text-decoration: none; white-space: nowrap; }
        .g-filters .segm a.actief { background: var(--ink); color: #fff; }
        .g-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(215px, 1fr)); gap: 16px; }
        .g-kaart { background: var(--wit); border: 1px solid var(--lijn); border-radius: var(--radius); overflow: hidden; display: flex; flex-direction: column; box-shadow: var(--schaduw); }
        .g-staal { height: 96px; position: relative; }
        .g-staal::after { content: ""; position: absolute; inset: 0; background: linear-gradient(135deg, rgba(255,255,255,.18), rgba(0,0,0,.12)); mix-blend-mode: multiply; }
        .g-lichaam { padding: 12px 14px 14px; display: flex; flex-direction: column; gap: 4px; flex: 1; }
        .g-code { font-family: "Poppins", system-ui, sans-serif; font-weight: 700; font-size: 15px; letter-spacing: .02em; color: var(--ink); text-transform: uppercase; }
        .g-regel { font-size: 13px; color: var(--ink); }
        .g-regel .lb { color: var(--mild); }
        .g-voet { margin-top: auto; padding-top: 10px; display: flex; flex-direction: column; gap: 6px; }
        .g-voet .badge { align-self: flex-start; }
        .g-knop { background: var(--accent); }
        .g-knop:hover { background: var(--accent-donker); }
        details.g-meer { margin-top: 4px; }
        details.g-meer summary { cursor: pointer; font-size: 12.5px; color: var(--accent-donker); font-weight: 600; }
        details.g-meer dl { margin: 6px 0 2px; }
        details.g-meer dt { font-size: 10.5px; letter-spacing: .06em; text-transform: uppercase; color: var(--mild); margin-top: 6px; }
        details.g-meer dd { margin: 0 0 0 2px; font-size: 13px; }
        .g-leeg { text-align: center; color: var(--mild); padding: 40px 10px; border: 1px dashed var(--lijn); border-radius: var(--radius); }
        .g-verkocht-donker { color: var(--mild); }
    </style>
</head>
<body>
    <?php include 'nav.php'; ?>
    <h1>Galerij <small>leersamples · <?= $aantalTeKoop ?> te koop · <?= $aantalVerkocht ?> verkocht</small></h1>
    <p class="meta">De samples die beschikbaar zijn als verkoop. Toon een klant het staal op je
    telefoon of tablet; met “Verkoop” haal je de sample direct uit de voorraad (registratie: wie en wanneer).</p>

    <?php if ($melding): ?>
        <div class="msg"><?= htmlspecialchars($melding) ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="errors"><ul>
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul></div>
    <?php endif; ?>

    <div class="g-filters">
        <div class="segm">
            <a class="<?= $toon === 'tekoop' ? 'actief' : '' ?>" href="galerij.php?toon=tekoop<?= $kleur ? '&kleur=' . urlencode($kleur) : '' ?>">Te koop (<?= $aantalTeKoop ?>)</a>
            <a class="<?= $toon === 'alles' ? 'actief' : '' ?>" href="galerij.php?toon=alles<?= $kleur ? '&kleur=' . urlencode($kleur) : '' ?>">Alles</a>
            <a class="<?= $toon === 'verkocht' ? 'actief' : '' ?>" href="galerij.php?toon=verkocht<?= $kleur ? '&kleur=' . urlencode($kleur) : '' ?>">Verkocht (<?= $aantalVerkocht ?>)</a>
        </div>
        <form method="get" action="galerij.php" style="display:flex;gap:8px;align-items:flex-end;background:none;box-shadow:none;padding:0;margin:0;border:0">
            <input type="hidden" name="toon" value="<?= htmlspecialchars($toon) ?>">
            <select name="kleur" onchange="this.form.submit()">
                <option value="">Alle kleuren</option>
                <?php foreach ($kleuren as $k): ?>
                    <option value="<?= htmlspecialchars($k) ?>" <?= $kleur === $k ? 'selected' : '' ?>><?= htmlspecialchars($k) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="search" name="zoek" value="<?= htmlspecialchars($zoek) ?>" placeholder="Zoek op code of opmerking…">
            <button type="submit" class="secondary">Filter</button>
        </form>
    </div>

    <?php if (empty($items)): ?>
        <div class="g-leeg">Geen samples gevonden<?= $toon === 'tekoop' ? ' die te koop zijn' : '' ?>. Pas de filters aan.</div>
    <?php else: ?>
        <div class="g-grid">
            <?php foreach ($items as $v): $k = $kenmerken[(int)$v['id']] ?? []; ?>
                <?php
                    $staalKleur = '';
                    if (!empty($k['Kleurcategorie'])) {
                        $staalKleur = leerStaal($k['Kleurcategorie'][0]);
                    }
                    $samenvatting = [];
                    foreach (['Formaat', 'Gewicht', 'Dikte', 'Soepelheid', 'Schade', 'Geur', 'Optisch', 'PANTONE code'] as $voorkeur) {
                        if (!empty($k[$voorkeur])) {
                            $samenvatting[] = $voorkeur . ': ' . implode(', ', $k[$voorkeur]);
                        }
                    }
                ?>
                <div class="g-kaart">
                    <div class="g-staal" style="<?= $staalKleur ? 'background:' . $staalKleur : 'background:var(--beige)' ?>"></div>
                    <div class="g-lichaam">
                        <div class="g-code"><?= htmlspecialchars(itemLabel($v)) ?></div>
                        <?php if (!empty($k['Kleurcategorie'])): ?>
                            <div class="g-regel"><span class="lb">Kleur:</span> <?= htmlspecialchars(implode(', ', $k['Kleurcategorie'])) ?></div>
                        <?php endif; ?>
                        <?php foreach (array_slice($samenvatting, 0, 3) as $regel): ?>
                            <div class="g-regel"><span class="lb"><?= htmlspecialchars(explode(':', $regel, 2)[0]) ?>:</span><?= htmlspecialchars(explode(':', $regel, 2)[1]) ?></div>
                        <?php endforeach; ?>
                        <details class="g-meer">
                            <summary>Alle kenmerken</summary>
                            <dl>
                                <?php foreach ($k as $label => $teksten): ?>
                                    <dt><?= htmlspecialchars($label) ?></dt>
                                    <dd><?= htmlspecialchars(implode(', ', $teksten)) ?></dd>
                                <?php endforeach; ?>
                            </dl>
                        </details>
                        <?php if ($v['bigbag_code']): ?>
                            <div class="g-regel"><span class="lb">Uit bigbag:</span> <?= htmlspecialchars($v['bigbag_code']) ?></div>
                        <?php endif; ?>
                        <div class="g-voet">
                            <span class="badge <?= htmlspecialchars($v['status']) ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $v['status']))) ?></span>
                            <?php if ($v['status'] === 'verkocht'): ?>
                                <span class="g-regel g-verkocht-donker">Verkocht <?= $v['verkocht_op'] ? 'op ' . htmlspecialchars(date('d-m-Y H:i', strtotime($v['verkocht_op']))) : '' ?><?= $v['verkoper'] ? ' door ' . htmlspecialchars($v['verkoper']) : '' ?></span>
                                <form method="post">
                                    <input type="hidden" name="ongedaan" value="<?= (int)$v['id'] ?>">
                                    <button type="submit" class="secondary" onclick="return confirm('Verkoop van deze sample ongedaan maken?')">Ongedaan maken</button>
                                </form>
                            <?php else: ?>
                                <form method="post">
                                    <input type="hidden" name="verkoop" value="<?= (int)$v['id'] ?>">
                                    <button type="submit" class="g-knop" onclick="return confirm('Deze sample als verkocht registreren en uit de voorraad halen?')">Verkoop</button>
                                </form>
                            <?php endif; ?>
                            <a class="g-regel" href="edit.php?id=<?= (int)$v['id'] ?>">Bewerken</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</body>
</html>
