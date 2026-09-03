<?php
require 'auth.php';
vereisAdmin();
require 'functies.php';

// Filters
$van = trim((string)($_GET['van'] ?? ''));
$tot = trim((string)($_GET['tot'] ?? ''));
$gebruikerId = (int)($_GET['gebruiker'] ?? 0);
$actieFilter = trim((string)($_GET['actie'] ?? ''));
$zoek = trim((string)($_GET['zoek'] ?? ''));

$where = ['1=1'];
$params = [];
if ($van !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $van)) {
    $where[] = 'l.aangemaakt_op >= ?';
    $params[] = $van . ' 00:00:00';
}
if ($tot !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tot)) {
    $where[] = 'l.aangemaakt_op < ?';
    $params[] = date('Y-m-d', strtotime($tot . ' +1 day')) . ' 00:00:00';
}
if ($gebruikerId > 0) {
    $where[] = 'l.gebruiker_id = ?';
    $params[] = $gebruikerId;
}
if ($actieFilter !== '') {
    $where[] = 'l.actie = ?';
    $params[] = $actieFilter;
}
if ($zoek !== '') {
    $where[] = '(l.beschrijving LIKE ? OR l.apparaat LIKE ? OR l.ip LIKE ?)';
    $params[] = '%' . $zoek . '%';
    $params[] = '%' . $zoek . '%';
    $params[] = '%' . $zoek . '%';
}
$in = implode(' AND ', $where);

// CSV-download (respecteert dezelfde filters)
if (($_GET['download'] ?? '') === 'csv') {
    $stmt = $pdo->prepare(
        "SELECT l.aangemaakt_op, g.naam, l.actie, l.beschrijving, l.ip, l.apparaat
         FROM logboek l
         LEFT JOIN gebruikers g ON g.id = l.gebruiker_id
         WHERE $in ORDER BY l.id DESC"
    );
    $stmt->execute($params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="logboek-' . date('Ymd-His') . '.csv"');
    $uit = fopen('php://output', 'w');
    fwrite($uit, "\xEF\xBB\xBF"); // BOM zodat Excel de accenten goed toont
    fputcsv($uit, ['Datum/tijd', 'Gebruiker', 'Actie', 'Beschrijving', 'IP-adres', 'Apparaat'], ';');
    foreach ($stmt->fetchAll() as $r) {
        fputcsv($uit, [
            date('d-m-Y H:i:s', strtotime($r['aangemaakt_op'])),
            $r['naam'] ?? '—',
            $r['actie'],
            $r['beschrijving'],
            $r['ip'],
            $r['apparaat'],
        ], ';');
    }
    fclose($uit);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT l.*, g.naam
     FROM logboek l
     LEFT JOIN gebruikers g ON g.id = l.gebruiker_id
     WHERE $in ORDER BY l.id DESC LIMIT 300"
);
$stmt->execute($params);
$regels = $stmt->fetchAll();

$stmtC = $pdo->prepare("SELECT COUNT(*) FROM logboek l WHERE $in");
$stmtC->execute($params);
$totaal = (int)$stmtC->fetchColumn();

$gebruikers = $pdo->query('SELECT id, naam FROM gebruikers ORDER BY naam')->fetchAll();
$acties = $pdo->query('SELECT DISTINCT actie FROM logboek ORDER BY actie')->fetchAll(PDO::FETCH_COLUMN);

$aantalFilters = ($van !== '' ? 1 : 0) + ($tot !== '' ? 1 : 0)
    + ($gebruikerId > 0 ? 1 : 0) + ($actieFilter !== '' ? 1 : 0) + ($zoek !== '' ? 1 : 0);

/** Kleur-badge per actietype. */
function actieBadge(string $actie): string
{
    if (str_contains($actie, 'verkoop')) {
        return 'beschikbaar';
    }
    if (str_contains($actie, 'verwijder') || str_contains($actie, 'mislukt')) {
        return 'in_bewerking';
    }
    if (str_contains($actie, 'inloggen') || str_contains($actie, '2fa')
        || str_contains($actie, 'apparaat') || str_contains($actie, 'uitloggen')) {
        return 'medewerker';
    }
    if (str_contains($actie, 'toegevoegd') || str_contains($actie, 'labels')) {
        return 'gereserveerd';
    }
    return 'verkocht';
}

$csvParams = $_GET;
$csvParams['download'] = 'csv';
unset($csvParams['deel']);
$csvUrl = 'logboek.php?' . http_build_query($csvParams);

// Live-update-fragment: alleen de tabelrijen.
$liveFragment = ($_GET['deel'] ?? '') === 'rijen';
if ($liveFragment) {
    ob_start();
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <?php $titel = 'Logboek — Circuleather'; ?>
    <?php include 'head.php'; ?>
</head>
<body>
    <?php include 'nav.php'; ?>
    <a class="back-link" href="users.php">&larr; Terug naar beheer</a>
    <h1>Logboek <small><span class="live-stip" title="Automatisch bijgewerkt"></span><span id="log-tel"><?= $totaal ?> meldingen</span></small></h1>
    <p class="meta">Alles wat er in de app gebeurt, in één overzicht: wie deed wat en wanneer,
    en vanaf welk apparaat. Wordt automatisch bijgewerkt. Download de (gefilterde) lijst als
    CSV voor in Excel.</p>

    <div class="knoppen">
        <a href="<?= htmlspecialchars($csvUrl) ?>">⬇ Download CSV</a>
        <a class="secondary" href="logboek.php">Alles tonen</a>
    </div>

    <div class="v-filterbalk">
        <div class="filterblok">
            <button type="button" class="filters-knop">Filters<?php if ($aantalFilters > 0): ?><span class="filters-tel"><?= $aantalFilters ?> actief</span><?php endif; ?></button>
            <div class="filters-inhoud">
                <form method="get" action="logboek.php" class="v-filterform">
                    <div class="v-rij">
                        <span class="v-veld">
                            <label for="l-van">Van</label>
                            <input type="date" id="l-van" name="van" value="<?= htmlspecialchars($van) ?>">
                        </span>
                        <span class="v-veld">
                            <label for="l-tot">Tot</label>
                            <input type="date" id="l-tot" name="tot" value="<?= htmlspecialchars($tot) ?>">
                        </span>
                        <span class="v-veld">
                            <label for="l-gebruiker">Persoon</label>
                            <select id="l-gebruiker" name="gebruiker">
                                <option value="">Iedereen</option>
                                <?php foreach ($gebruikers as $g): ?>
                                    <option value="<?= (int)$g['id'] ?>" <?= $gebruikerId === (int)$g['id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['naam']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </span>
                        <span class="v-veld">
                            <label for="l-actie">Actie</label>
                            <select id="l-actie" name="actie">
                                <option value="">Alle acties</option>
                                <?php foreach ($acties as $a): ?>
                                    <option value="<?= htmlspecialchars($a) ?>" <?= $actieFilter === $a ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $a))) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </span>
                        <span class="v-veld">
                            <label for="l-zoek">Zoek</label>
                            <input type="search" id="l-zoek" name="zoek" value="<?= htmlspecialchars($zoek) ?>" placeholder="Tekst, IP, apparaat…">
                        </span>
                        <button type="submit">Filter</button>
                        <?php if ($aantalFilters > 0): ?>
                            <a class="wis-filters" href="logboek.php">Wis filters</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Wanneer</th>
                <th>Wie</th>
                <th>Actie</th>
                <th>Beschrijving</th>
                <th>Apparaat</th>
            </tr>
        </thead>
        <tbody id="log-rijen">
        <?php if (empty($regels)): ?>
            <tr><td colspan="5" class="lege-tabel">Nog geen activiteit<?= $aantalFilters > 0 ? ' met deze filters' : '' ?>.</td></tr>
        <?php else: foreach ($regels as $r): ?>
            <tr>
                <td data-label="Wanneer"><?= htmlspecialchars(date('d-m-Y H:i:s', strtotime($r['aangemaakt_op']))) ?></td>
                <td data-label="Wie"><?= htmlspecialchars($r['naam'] ?? '—') ?></td>
                <td data-label="Actie"><span class="badge <?= actieBadge($r['actie']) ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $r['actie']))) ?></span></td>
                <td data-label="Beschrijving"><?= htmlspecialchars($r['beschrijving']) ?></td>
                <td data-label="Apparaat"><?= htmlspecialchars($r['apparaat']) ?><?= $r['ip'] ? '<br><span class="inactief">' . htmlspecialchars($r['ip']) . '</span>' : '' ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    <?php if ($totaal > 300): ?>
        <p class="meta">De laatste 300 regels worden getoond; de CSV-download bevat alles.</p>
    <?php endif; ?>

    <script>
        (function () {
            var tbody = document.getElementById('log-rijen');
            var tel = document.getElementById('log-tel');
            if (!tbody) { return; }
            var vorige = tbody.innerHTML;
            livePoll(function () {
                var q = new URLSearchParams(location.search);
                q.delete('download');
                q.set('deel', 'rijen');
                return 'logboek.php?' + q.toString();
            }, function (d) {
                if (!d || typeof d.html !== 'string') { return; }
                if (d.html !== vorige) {
                    vorige = d.html;
                    tbody.innerHTML = d.html;
                }
                if (tel && typeof d.totaal === 'number') {
                    tel.textContent = d.totaal + ' meldingen';
                }
            });
        })();
    </script>
</body>
</html>
<?php
if ($liveFragment) {
    $helePagina = ob_get_clean();
    $rijen = '';
    if (preg_match('#<tbody id="log-rijen">(.*?)</tbody>#s', $helePagina, $m)) {
        $rijen = $m[1];
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'totaal' => $totaal,
        'html' => $rijen,
    ]);
    exit;
}
?>