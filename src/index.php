<?php
require 'auth.php';
vereisLogin();
require 'functies.php';

$berichten = [
    'added' => 'Voorraad toegevoegd.',
    'updated' => 'Voorraad bijgewerkt.',
    'deleted' => 'Voorraad verwijderd.',
    'foto' => 'Foto opgeslagen.',
];
$msg = $_GET['msg'] ?? '';

// --- Tab (soort) en filters -----------------------------------------------
$soort = (string)($_GET['soort'] ?? '');
if (!in_array($soort, ['', 'bigbag', 'leersample'], true)) {
    $soort = '';
}
$status = trim((string)($_GET['status'] ?? ''));
if ($status !== '' && !in_array($status, STATUSSEX, true)) {
    $status = '';
}
$stadId = (int)($_GET['stad'] ?? 0);
$zoek = trim((string)($_GET['zoek'] ?? ''));

$criteria = haalCriteria($pdo);
$steden = haalSteden($pdo);

// Dynamische filters: per tab de 'keuze'-criteria van die categorie
// (Inhoud bigbag bij bigbags; kleur, formaat, geur ... bij samples).
$filterCriteria = $soort !== ''
    ? array_filter(criteriaVoor($criteria, $soort), fn ($c) => $c['soort'] === 'keuze')
    : [];
$filterKeuze = [];
foreach ($filterCriteria as $c) {
    $w = trim((string)($_GET['f' . $c['id']] ?? ''));
    $geldig = array_map(fn ($o) => $o['waarde'], $c['opties']);
    $filterKeuze[$c['id']] = in_array($w, $geldig, true) ? $w : '';
}

$where = ['1=1'];
$params = [];
if ($soort !== '') {
    $where[] = 'v.categorie = ?';
    $params[] = $soort;
}
if ($status !== '') {
    $where[] = 'v.status = ?';
    $params[] = $status;
}
if ($stadId > 0) {
    $where[] = '(v.stad_id = ? OR EXISTS (SELECT 1 FROM voorraad bb
                WHERE bb.id = v.bigbag_id AND bb.stad_id = ?))';
    $params[] = $stadId;
    $params[] = $stadId;
}
if ($zoek !== '') {
    $where[] = '(v.code LIKE ? OR v.locatie LIKE ? OR v.opmerking LIKE ?)';
    $params[] = '%' . $zoek . '%';
    $params[] = '%' . $zoek . '%';
    $params[] = '%' . $zoek . '%';
}
foreach ($filterKeuze as $cid => $waarde) {
    if ($waarde === '') {
        continue;
    }
    $where[] = 'EXISTS (SELECT 1 FROM voorraad_criteria vf
                JOIN criteria_opties of ON of.id = vf.optie_id
                WHERE vf.voorraad_id = v.id AND vf.criterium_id = ? AND of.waarde = ?)';
    $params[] = $cid;
    $params[] = $waarde;
}

$stmt = $pdo->prepare(
    'SELECT v.*, s.naam AS stad_naam, b.code AS bigbag_code
     FROM voorraad v
     LEFT JOIN steden s ON s.id = v.stad_id
     LEFT JOIN voorraad b ON b.id = v.bigbag_id
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY (v.categorie = \'bigbag\') DESC, v.aangemaakt_op DESC, v.id DESC'
);
$stmt->execute($params);
$items = $stmt->fetchAll();
$kenmerken = haalKenmerken($pdo, array_map(fn ($i) => (int)$i['id'], $items));

$totaalBigbags = (int)$pdo->query("SELECT COUNT(*) FROM voorraad WHERE categorie = 'bigbag'")->fetchColumn();
$totaalSamples = (int)$pdo->query("SELECT COUNT(*) FROM voorraad WHERE categorie = 'leersample'")->fetchColumn();

$heeftFilters = $status !== '' || $stadId > 0 || $zoek !== '' || count(array_filter($filterKeuze)) > 0;
$tabZoek = $zoek !== '' ? '&zoek=' . rawurlencode($zoek) : '';

// Live-update-fragment: buffert de pagina en stuurt alleen de tabelrijen als JSON.
$liveFragment = ($_GET['deel'] ?? '') === 'tbody';
if ($liveFragment) {
    ob_start();
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Circuleather — Voorraad</title>
    <link rel="stylesheet" href="style.css?v=4">
    <style>
        .kleurbol {
            display: inline-block;
            width: 13px;
            height: 13px;
            border-radius: 50%;
            margin-right: 8px;
            vertical-align: -1px;
            border: 2px solid #fff;
            box-shadow: 0 0 0 1px rgba(36, 28, 18, .22), 0 1px 3px rgba(36, 28, 18, .25);
        }
        .v-filterbalk { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; background: var(--wit); border: 1px solid var(--lijn); border-radius: var(--radius); padding: 14px; margin: 4px 0 18px; box-shadow: var(--schaduw); }
        .v-filterbalk .segm { display: flex; border: 1.5px solid var(--lijn); border-radius: 999px; overflow: hidden; background: #fbf6ea; }
        .v-filterbalk .segm a { padding: 9px 15px; font-size: 13px; font-weight: 600; color: var(--mild); text-decoration: none; white-space: nowrap; }
        .v-filterbalk .segm a.actief { background: var(--ink); color: #fff; }
        .v-filterbalk .segm a span { font-weight: 400; opacity: .8; }
        .v-rij { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; flex: 1 1 auto; }
        .v-veld { display: flex; flex-direction: column; gap: 4px; }
        .v-veld > label { margin: 0; font-size: 10.5px; font-weight: 700; letter-spacing: .09em; text-transform: uppercase; color: var(--mild); }
        .v-veld select, .v-veld input { width: auto; margin-top: 0; min-width: 150px; }
        .v-veld input[type="search"] { min-width: 190px; }
        .v-filterbalk form { display: contents; }
        .v-filterbalk button { margin-top: 0; width: auto; padding: 11px 20px; font-size: 13.5px; }
        .wis-filters { font-size: 12.5px; font-weight: 600; text-decoration: none; padding: 8px 2px; white-space: nowrap; }
        @media (max-width: 760px) {
            .v-filterbalk .segm { flex: 1 1 100%; justify-content: center; }
            .v-filterbalk .segm a { flex: 1; text-align: center; }
            .v-veld { flex: 1 1 100%; }
            .v-veld select, .v-veld input, .v-veld input[type="search"] { width: 100%; min-width: 0; }
            .v-filterbalk button { width: 100%; }
            .wis-filters { text-align: center; }
        }
    </style>
</head>
<body>
    <?php include 'nav.php'; ?>
    <h1>Voorraad <small id="live-tel"><?= count($items) ?> items</small></h1>

    <?php if (isset($berichten[$msg])): ?>
        <div class="msg"><?= htmlspecialchars($berichten[$msg]) ?></div>
    <?php endif; ?>

    <div class="knoppen">
        <a href="add.php?categorie=bigbag">+ Bigbag toevoegen</a>
        <a href="add.php?categorie=leersample" class="secondary">+ Leersample toevoegen</a>
        <a href="scan.php" class="secondary">📷 Scan met camera</a>
    </div>

    <div class="v-filterbalk">
        <div class="segm">
            <a class="<?= $soort === '' ? 'actief' : '' ?>" href="index.php?soort=<?= $tabZoek ?>">Alles</a>
            <a class="<?= $soort === 'bigbag' ? 'actief' : '' ?>" href="index.php?soort=bigbag<?= $tabZoek ?>">Bigbags <span id="tab-bigbags"><?= $totaalBigbags ?></span></a>
            <a class="<?= $soort === 'leersample' ? 'actief' : '' ?>" href="index.php?soort=leersample<?= $tabZoek ?>">Leersamples <span id="tab-leersamples"><?= $totaalSamples ?></span></a>
        </div>
        <form method="get" action="index.php" style="display:contents">
            <input type="hidden" name="soort" value="<?= htmlspecialchars($soort) ?>">
            <div class="v-rij">
                <span class="v-veld">
                    <label for="v-status">Status</label>
                    <select id="v-status" name="status">
                        <option value="">Alle statussen</option>
                        <?php foreach (STATUSSEX as $st): ?>
                            <option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $st)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </span>
                <?php if ($soort !== 'leersample'): ?>
                    <span class="v-veld">
                        <label for="v-stad">Herkomststad</label>
                        <select id="v-stad" name="stad">
                            <option value="">Alle steden</option>
                            <?php foreach ($steden as $sid => $naam): ?>
                                <option value="<?= $sid ?>" <?= $stadId === $sid ? 'selected' : '' ?>><?= htmlspecialchars($naam) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </span>
                <?php endif; ?>
                <?php foreach ($filterCriteria as $c): ?>
                    <span class="v-veld">
                        <label for="v-f<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['label']) ?></label>
                        <select id="v-f<?= (int)$c['id'] ?>" name="f<?= (int)$c['id'] ?>">
                            <option value="">Alle</option>
                            <?php foreach ($c['opties'] as $o): ?>
                                <option value="<?= htmlspecialchars($o['waarde']) ?>" <?= $filterKeuze[$c['id']] === $o['waarde'] ? 'selected' : '' ?>><?= htmlspecialchars($o['waarde']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </span>
                <?php endforeach; ?>
                <span class="v-veld">
                    <label for="v-zoek">Zoek</label>
                    <input type="search" id="v-zoek" name="zoek" value="<?= htmlspecialchars($zoek) ?>" placeholder="Code, locatie, opmerking…">
                </span>
                <button type="submit">Filter</button>
                <?php if ($heeftFilters): ?>
                    <a class="wis-filters" href="index.php?soort=<?= htmlspecialchars($soort) ?>">Wis filters</a>
                <?php endif; ?>
            </div>
        </form>
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
        <tbody id="live-rijen">
        <?php if (empty($items)): ?>
            <tr><td colspan="7" class="lege-tabel"><?= $heeftFilters
                ? 'Geen voorraad gevonden met deze filters.'
                : ($soort === 'bigbag' ? 'Nog geen bigbags geregistreerd.' : ($soort === 'leersample' ? 'Nog geen leersamples geregistreerd.' : 'Nog geen voorraad toegevoegd.')) ?></td></tr>
        <?php else: foreach ($items as $v): $itemKenmerken = $kenmerken[(int)$v['id']] ?? [];
            $kleurwaarde = $v['categorie'] === 'leersample' ? ($itemKenmerken['Kleurcategorie'][0] ?? '') : ''; ?>
            <tr>
                <td data-label="Code"><?php if ($kleurwaarde !== ''): ?><span class="kleurbol" style="background:<?= leerStaal($kleurwaarde) ?>" title="Kleurcategorie: <?= htmlspecialchars($kleurwaarde) ?>"></span><?php endif; ?><?= htmlspecialchars(itemLabel($v)) ?></td>
                <td data-label="Categorie"><?= $v['categorie'] === 'bigbag' ? 'Bigbag' : 'Leersample' ?></td>
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

    <script src="live.js"></script>
    <script>
        (function () {
            var tbody = document.getElementById('live-rijen');
            var tel = document.getElementById('live-tel');
            var tb = document.getElementById('tab-bigbags');
            var ts = document.getElementById('tab-leersamples');
            if (!tbody) { return; }
            var vorige = tbody.innerHTML;
            livePoll(function () {
                var q = new URLSearchParams(location.search);
                q.delete('msg');
                q.set('deel', 'tbody');
                return 'index.php?' + q.toString();
            }, function (d) {
                if (!d || typeof d.html !== 'string') { return; }
                if (d.html !== vorige) {
                    vorige = d.html;
                    tbody.innerHTML = d.html;
                }
                if (tel && typeof d.totaal === 'number') {
                    tel.textContent = d.totaal + ' items';
                }
                if (tb && typeof d.bigbags === 'number') { tb.textContent = d.bigbags; }
                if (ts && typeof d.samples === 'number') { ts.textContent = d.samples; }
            });
        })();
    </script>
</body>
</html>
<?php
if ($liveFragment) {
    $helePagina = ob_get_clean();
    $rijen = '';
    if (preg_match('#<tbody id="live-rijen">(.*?)</tbody>#s', $helePagina, $m)) {
        $rijen = $m[1];
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'totaal' => count($items),
        'bigbags' => $totaalBigbags,
        'samples' => $totaalSamples,
        'html' => $rijen,
    ]);
    exit;
}
?>
