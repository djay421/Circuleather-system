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
        logActie($pdo, 'verkocht', 'Sample #' . $id . ' verkocht');
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
        logActie($pdo, 'verkoop_ongedaan', 'Verkoop van sample #' . $id . ' ongedaan gemaakt');
        $melding = 'Verkoop ongedaan gemaakt; de sample is weer beschikbaar.';
    } else {
        $errors[] = 'Alleen verkochte samples kunnen worden teruggezet.';
    }
}

// Foto toevoegen of vervangen (knopje op de verkoopkaart).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['actie'] ?? '') === 'foto') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT id, foto, status FROM voorraad WHERE id = ? AND categorie = 'leersample'");
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    if (!$item) {
        $errors[] = 'Sample niet gevonden.';
    } else {
        [$pad, $fout] = verwerkFotoUpload($_FILES['foto'] ?? []);
        if ($fout) {
            $errors[] = $fout;
        } elseif ($pad !== null) {
            $upd = $pdo->prepare('UPDATE voorraad SET foto = ? WHERE id = ?');
            $upd->execute([$pad, $id]);
            verwijderFotoBestand($item['foto']); // oude foto opruimen bij vervangen
            $melding = 'Foto opgeslagen.';
        } else {
            $errors[] = 'Kies eerst een afbeelding.';
        }
    }
}

// Filters
$toon = (string)($_GET['toon'] ?? 'tekoop');
if (!in_array($toon, ['tekoop', 'alles', 'verkocht'], true)) {
    $toon = 'tekoop';
}
$kleur = trim((string)($_GET['kleur'] ?? ''));
$zoek = trim((string)($_GET['zoek'] ?? ''));
$aantalFilters = ($kleur !== '' ? 1 : 0) + ($zoek !== '' ? 1 : 0);

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
    "SELECT v.id, v.code, v.categorie, v.status, v.opmerking, v.foto, b.code AS bigbag_code,
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

// Live-update-fragment: buffert de pagina en stuurt alleen de grid als JSON.
$liveFragment = ($_GET['deel'] ?? '') === 'grid';
if ($liveFragment) {
    ob_start();
}

?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <?php $titel = 'Galerij — Circuleather'; ?>
    <?php include 'head.php'; ?>
</head>
<body>
    <?php include 'nav.php'; ?>
    <h1>Galerij <small><span class="live-stip" title="Automatisch bijgewerkt"></span><span id="gal-tel">leersamples · <?= $aantalTeKoop ?> te koop · <?= $aantalVerkocht ?> verkocht</span></small></h1>
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
            <a class="<?= $toon === 'tekoop' ? 'actief' : '' ?>" href="galerij.php?toon=tekoop<?= $kleur ? '&kleur=' . urlencode($kleur) : '' ?>">Te koop (<span id="cnt-tekoop"><?= $aantalTeKoop ?></span>)</a>
            <a class="<?= $toon === 'alles' ? 'actief' : '' ?>" href="galerij.php?toon=alles<?= $kleur ? '&kleur=' . urlencode($kleur) : '' ?>">Alles</a>
            <a class="<?= $toon === 'verkocht' ? 'actief' : '' ?>" href="galerij.php?toon=verkocht<?= $kleur ? '&kleur=' . urlencode($kleur) : '' ?>">Verkocht (<span id="cnt-verkocht"><?= $aantalVerkocht ?></span>)</a>
        </div>
        <div class="filterblok">
            <button type="button" class="filters-knop">Filters<?php if ($aantalFilters > 0): ?><span class="filters-tel"><?= $aantalFilters ?> actief</span><?php endif; ?></button>
            <div class="filters-inhoud">
        <form method="get" action="galerij.php" class="g-filterform">
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
        </div>
    </div>

    <div id="live-wissel">
        <!--LIVE-GRID-BEGIN-->
        <?php if (empty($items)): ?>
            <div class="g-leeg">Geen samples gevonden<?= $toon === 'tekoop' ? ' die te koop zijn' : '' ?>. Pas de filters aan.</div>
        <?php else: ?>
            <section class="g-grid">
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
                    <?php if (!empty($v['foto'])): ?>
                        <div class="g-foto"><img src="<?= htmlspecialchars($v['foto']) ?>" alt="Foto van <?= htmlspecialchars(itemLabel($v)) ?>">
                    <?php else: ?>
                        <div class="g-staal" style="<?= $staalKleur ? 'background:' . $staalKleur : 'background:var(--beige)' ?>">
                    <?php endif; ?>
                        <form class="g-fotovorm" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="actie" value="foto">
                            <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
                            <label class="g-fotoknop" title="<?= empty($v['foto']) ? 'Foto toevoegen' : 'Foto vervangen' ?>"><?= empty($v['foto']) ? '📷 Foto' : '📷 Vervang' ?>
                                <input type="file" name="foto" accept="image/*" onchange="this.form.submit()">
                            </label>
                        </form>
                    </div>
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
            </section>
        <?php endif; ?>
        <!--LIVE-GRID-EIND-->
    </div>

    <script>
        (function () {
            var wissel = document.getElementById('live-wissel');
            var tel = document.getElementById('gal-tel');
            var cntTk = document.getElementById('cnt-tekoop');
            var cntVk = document.getElementById('cnt-verkocht');
            if (!wissel) { return; }
            var vorige = wissel.innerHTML;
            var vorigeT = -1;
            var vorigeV = -1;
            livePoll(function () {
                var q = new URLSearchParams(location.search);
                q.set('deel', 'grid');
                return 'galerij.php?' + q.toString();
            }, function (d) {
                if (!d || typeof d.html !== 'string') { return; }
                if (d.html !== vorige) {
                    vorige = d.html;
                    wissel.innerHTML = d.html;
                }
                var t = typeof d.teKoop === 'number' ? d.teKoop : null;
                var v = typeof d.verkocht === 'number' ? d.verkocht : null;
                if (t !== null && t !== vorigeT) { vorigeT = t; if (cntTk) { cntTk.textContent = t; } }
                if (v !== null && v !== vorigeV) { vorigeV = v; if (cntVk) { cntVk.textContent = v; } }
                if (tel) {
                    var nw = 'leersamples · ' + (t !== null ? t : vorigeT) + ' te koop · '
                        + (v !== null ? v : vorigeV) + ' verkocht';
                    if (tel.textContent !== nw) { tel.textContent = nw; }
                }
            });
        })();
    </script>
</body>
</html>
<?php
if ($liveFragment) {
    $helePagina = ob_get_clean();
    $html = '';
    if (preg_match('#(<!--LIVE-GRID-BEGIN-->.*?<!--LIVE-GRID-EIND-->)#s', $helePagina, $m)) {
        $html = $m[1];
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'html' => $html,
        'teKoop' => $aantalTeKoop,
        'verkocht' => $aantalVerkocht,
    ]);
    exit;
}
?>
