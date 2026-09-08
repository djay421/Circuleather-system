<?php
// Diagnose-pagina voor de databaseverbinding (handig bij gratis hosting
// zoals InfinityFree, waar je geen ssh of echte error-logs hebt).
//
// Upload dit bestand samen met de rest van src/ naar htdocs en open het in
// de browser: https://jouw-subdomein/test-db.php
//
// BELANGRIJK: verwijder dit bestand van de server als je klaar bent — het
// toont foutdetails die je niet publiek wilt hebben.
//
// Deze pagina probeert NIET de echte app-verbinding te openen (db.php stopt
// de pagina bij een fout); hij leest alleen de instellingen die db.php zou
// gebruiken en test ze daarna in twee losse stappen, zodat je precies ziet
// waar het misgaat. Wil je tijdelijk andere waarden proberen? Vul ze
// hieronder in bij $overschrijf en upload opnieuw.

$overschrijf = [
    // 'host'     => 'sqlXXX.infinityfree.com',
    // 'dbname'   => 'if0_42826696_circuleather',
    // 'user'     => 'if0_42826696',
    // 'password' => 'jouw-wachtwoord',
];

// --- Dezelfde configuratielogica als config/db.php, maar zonder verbinding ---
$dbHost = getenv('DB_HOST') ?: 'db';
$dbName = getenv('DB_NAME') ?: 'circuleather_crm';
$dbUser = getenv('DB_USER') ?: 'crm_user';
$dbPassword = getenv('DB_PASSWORD') ?: 'crm_password';

$localConfig = __DIR__ . '/config/db.local.php';
$gebruiktLocal = is_file($localConfig);
if ($gebruiktLocal) {
    $cfg = require $localConfig;
    if (is_array($cfg)) {
        $dbHost = $cfg['host'] ?? $dbHost;
        $dbName = $cfg['dbname'] ?? $dbName;
        $dbUser = $cfg['user'] ?? $dbUser;
        $dbPassword = $cfg['password'] ?? $dbPassword;
    }
}

foreach ($overschrijf as $k => $v) {
    if ($k === 'host') { $dbHost = $v; }
    if ($k === 'dbname') { $dbName = $v; }
    if ($k === 'user') { $dbUser = $v; }
    if ($k === 'password') { $dbPassword = $v; }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>DB-diagnose</title>
<style>
    body { font-family: system-ui, sans-serif; max-width: 46rem; margin: 2rem auto; padding: 0 1rem; line-height: 1.6; color: #1c1c1c; }
    h1 { font-size: 1.4rem; } h2 { font-size: 1.05rem; margin-top: 1.5rem; }
    code { background: #f3f3f3; padding: .1rem .3rem; border-radius: .25rem; font-size: .9em; }
    table { border-collapse: collapse; width: 100%; }
    td, th { border: 1px solid #ddd; padding: .4rem .6rem; text-align: left; font-size: .9rem; }
    .ok { color: #0a7d33; font-weight: 600; } .fout { color: #c62828; font-weight: 600; }
    li { margin-bottom: .4rem; }
</style>
</head>
<body>
<h1>Database-diagnose</h1>

<h2>1. Welke instellingen gebruikt de app?</h2>
<p>Bron: <strong><?= $gebruiktLocal ? 'config/db.local.php (bestaat)' : 'omgevingsvariabelen (config/db.local.php ontbreekt!)' ?></strong></p>
<table>
<tr><th>Instelling</th><th>Waarde</th></tr>
<tr><td>host</td><td><code><?= htmlspecialchars($dbHost, ENT_QUOTES, 'UTF-8') ?></code></td></tr>
<tr><td>dbname</td><td><code><?= htmlspecialchars($dbName, ENT_QUOTES, 'UTF-8') ?></code></td></tr>
<tr><td>user</td><td><code><?= htmlspecialchars($dbUser, ENT_QUOTES, 'UTF-8') ?></code></td></tr>
<tr><td>password</td><td><?= $dbPassword === '' ? '<span class="fout">LEEG</span>' : str_repeat('&bull;', max(6, strlen($dbPassword))) . ' (' . strlen($dbPassword) . ' tekens)' ?></td></tr>
</table>

<?php if (!$gebruiktLocal): ?>
<p><strong>Let op:</strong> db.local.php ontbreekt, dus de app gebruikt nu de
Docker-standaardwaarden (gebruiker <code>crm_user</code>, database
<code>circuleather_crm</code>) — die bestaan niet op InfinityFree. Maak eerst
<code>config/db.local.php</code> met je InfinityFree-gegevens.</p>
<?php endif; ?>

<h2>2. Verbindingstest</h2>
<?php
$serverOk = false;
$dbOk = false;
$serverFout = '';
$dbFout = '';

// Stap 1: alleen verbinden met de server (zonder databasenaam).
try {
    new PDO("mysql:host={$dbHost};charset=utf8mb4", $dbUser, $dbPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 10,
    ]);
    $serverOk = true;
} catch (PDOException $e) {
    $serverFout = $e->getMessage();
}

// Stap 2: de specifieke database selecteren.
if ($serverOk) {
    try {
        new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPassword, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 10,
        ]);
        $dbOk = true;
    } catch (PDOException $e) {
        $dbFout = $e->getMessage();
    }
}
?>
<table>
<tr><th>Test</th><th>Resultaat</th></tr>
<tr>
    <td>Server bereiken (gebruiker + wachtwoord)</td>
    <td><?= $serverOk ? '<span class="ok">gelukt</span>' : '<span class="fout">mislukt</span>' ?></td>
</tr>
<tr>
    <td>Database selecteren (<?= htmlspecialchars($dbName, ENT_QUOTES, 'UTF-8') ?>)</td>
    <td><?= $dbOk ? '<span class="ok">gelukt</span>' : ($serverOk ? '<span class="fout">mislukt</span>' : 'overgeslagen') ?></td>
</tr>
</table>

<?php if ($serverFout !== ''): ?>
<p><strong>Serverfout:</strong></p>
<pre><?= htmlspecialchars($serverFout, ENT_QUOTES, 'UTF-8') ?></pre>
<?php endif; ?>
<?php if ($dbFout !== ''): ?>
<p><strong>Databasefout:</strong></p>
<pre><?= htmlspecialchars($dbFout, ENT_QUOTES, 'UTF-8') ?></pre>
<?php endif; ?>

<h2>3. Wat betekent dit?</h2>
<ul>
<li><strong>Stap 1 mislukt met 1045 "Access denied"</strong>: gebruiker of
    wachtwoord klopt niet. Bij InfinityFree is het wachtwoord dat van je
    <em>hosting-account</em> (client area &rarr; Manage &rarr; MySQL Details &rarr;
    Show bij Password) — niet je client-area-wachtwoord.</li>
<li><strong>Stap 1 lukt, stap 2 mislukt met 1044</strong> (of ook 1045): de
    databasenaam klopt niet. De naam moet het volledige voorvoegsel hebben:
    <code>if0_42826696_jouwnaam</code> (zie MySQL Databases in het
    controlepaneel). Alleen <code>jouwnaam</code> invullen geeft exact deze fout.</li>
<li><strong>Stap 1 mislukt met 2002 / "No such file or directory"</strong>: je
    gebruikt <code>localhost</code> als host. Gebruik de host uit het
    controlepaneel (zoals <code>sqlXXX.infinityfree.com</code>).</li>
<li><strong>Beide stappen gelukt</strong>: de verbinding is goed en het probleem
    zit elders — ververs dan de echte pagina (Ctrl+F5) en kijk of de fout weg is.</li>
</ul>

<p style="margin-top:2rem"><em>Vergeet dit bestand niet te verwijderen van de
server als je klaar bent.</em></p>
</body>
</html>
