<?php
// Live-fragment (?deel=…): machine-leesbaar antwoord → nooit PHP-notices
// in de JSON en nergens cachen (belangrijk op gedeelde hosting).
if (isset($_GET['deel'])) {
    error_reporting(E_ERROR | E_PARSE);
    ini_set('display_errors', '0');
    header('Cache-Control: no-store');
}
require __DIR__ . '/../core/auth.php';
vereisLogin();
require __DIR__ . '/../components/functies.php';

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
                JOIN criteria_opties fo ON fo.id = vf.optie_id
                WHERE vf.voorraad_id = v.id AND vf.criterium_id = ? AND fo.waarde = ?)';
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
$totaalBeschikbaar = (int)$pdo->query("SELECT COUNT(*) FROM voorraad WHERE categorie = 'leersample' AND status IN ('beschikbaar','gereserveerd')")->fetchColumn();
$totaalAlle = $totaalBigbags + $totaalSamples;

$aantalFilters = ($status !== '' ? 1 : 0) + ($stadId > 0 ? 1 : 0) + ($zoek !== '' ? 1 : 0) + count(array_filter($filterKeuze));
$heeftFilters = $aantalFilters > 0;
$legeCta = $soort === 'bigbag' ? 'add.php?categorie=bigbag' : 'add.php?categorie=leersample';
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
    <?php $titel = 'Circuleather — Voorraad'; ?>
    <?php include __DIR__ . '/../components/head.php';; ?>
</head>
<body>
    <?php include 'nav.php'; ?>
    <h1>Voorraad <small><span class="live-stip" title="Automatisch bijgewerkt"></span><span id="live-tel"><?= count($items) ?> items</span></small></h1>

    <?php if (isset($berichten[$msg])): ?>
        <div class="msg"><?= htmlspecialchars($berichten[$msg]) ?></div>
    <?php endif; ?>

    <div class="stat-rij">
        <div class="stat"><span class="stat-icoon">📦</span><span class="stat-label">Bigbags</span><span class="stat-waarde" id="stat-bigbags"><?= $totaalBigbags ?></span></div>
        <div class="stat"><span class="stat-icoon">🧵</span><span class="stat-label">Leersamples</span><span class="stat-waarde" id="stat-samples"><?= $totaalSamples ?></span></div>
        <div class="stat"><span class="stat-icoon">🏷</span><span class="stat-label">Te koop</span><span class="stat-waarde" id="stat-beschikbaar"><?= $totaalBeschikbaar ?></span></div>
        <div class="stat"><span class="stat-icoon">📊</span><span class="stat-label">Totaal items</span><span class="stat-waarde" id="stat-totaal"><?= $totaalAlle ?></span></div>
    </div>

    <div class="knoppen toevoegen-rij">
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
        <div class="filterblok">
            <button type="button" class="filters-knop">Filters<?php if ($aantalFilters > 0): ?><span class="filters-tel"><?= $aantalFilters ?> actief</span><?php endif; ?></button>
            <div class="filters-inhoud">
        <form method="get" action="index.php" class="v-filterform">
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
        </div>
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
            <tr><td colspan="7" class="lege-tabel"><div class="lege-inner"><span class="lege-icoon">📦</span><p><?= $heeftFilters
                ? 'Geen voorraad gevonden met deze filters.'
                : ($soort === 'bigbag' ? 'Nog geen bigbags geregistreerd.' : ($soort === 'leersample' ? 'Nog geen leersamples geregistreerd.' : 'Nog geen voorraad toegevoegd.')) ?></p><a href="<?= $legeCta ?>">+ Toevoegen</a></div></td></tr>
        <?php else: foreach ($items as $v): $itemKenmerken = $kenmerken[(int)$v['id']] ?? [];
            $kleurwaarde = $v['categorie'] === 'leersample' ? ($itemKenmerken['Kleurcategorie'][0] ?? '') : ''; ?>
            <tr class="rij-<?= $v['categorie'] ?>">
                <td data-label="Code"><?php if ($kleurwaarde !== ''): ?><span class="kleurbol" style="background:<?= leerStaal($kleurwaarde) ?>" title="Kleurcategorie: <?= htmlspecialchars($kleurwaarde) ?>"></span><?php endif; ?><?= htmlspecialchars(itemLabel($v)) ?><a class="rij-chevron" href="edit.php?id=<?= (int)$v['id'] ?>" aria-label="Bewerken">›</a></td>
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

    <script>
        (function () {
            var tbody = document.getElementById('live-rijen');
            var tel = document.getElementById('live-tel');
            var tb = document.getElementById('tab-bigbags');
            var ts = document.getElementById('tab-leersamples');
            var sb = document.getElementById('stat-bigbags');
            var ss = document.getElementById('stat-samples');
            var sbes = document.getElementById('stat-beschikbaar');
            var stot = document.getElementById('stat-totaal');
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
                if (sb && typeof d.bigbags === 'number') { sb.textContent = d.bigbags; }
                if (ss && typeof d.samples === 'number') { ss.textContent = d.samples; }
                if (sbes && typeof d.beschikbaar === 'number') { sbes.textContent = d.beschikbaar; }
                if (stot && typeof d.alles === 'number') { stot.textContent = d.alles; }
            });
        })();
    </script>
    <div class="fab" id="fab">
        <div class="fab-items">
            <a href="add.php?categorie=bigbag">📦 Bigbag toevoegen</a>
            <a href="add.php?categorie=leersample">🧵 Leersample toevoegen</a>
            <a href="scan.php">📷 Scannen</a>
        </div>
        <button type="button" class="fab-knop" aria-label="Toevoegen">+</button>
    </div>
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
        'beschikbaar' => $totaalBeschikbaar,
        'alles' => $totaalAlle,
        'html' => $rijen,
    ]);
    exit;
}
?>
