<?php
// Databaseverbinding via PDO.
//
// Standaard worden de omgevingsvariabelen uit docker-compose.yml gebruikt.
// Voor gratis online hosting (zonder Docker) kun je een bestand
// `db.local.php` naast dit bestand zetten met de databasegegevens van je
// hoster — zie `db.local.example.php`. Lokaal met Docker is dat niet nodig.

$dbHost = getenv('DB_HOST') ?: 'db';
$dbName = getenv('DB_NAME') ?: 'circuleather_crm';
$dbUser = getenv('DB_USER') ?: 'crm_user';
$dbPassword = getenv('DB_PASSWORD') ?: 'crm_password';

$localConfig = __DIR__ . '/db.local.php';
if (is_file($localConfig)) {
    $cfg = require $localConfig;
    if (is_array($cfg)) {
        $dbHost = $cfg['host'] ?? $dbHost;
        $dbName = $cfg['dbname'] ?? $dbName;
        $dbUser = $cfg['user'] ?? $dbUser;
        $dbPassword = $cfg['password'] ?? $dbPassword;
    }
}

try {
    $pdo = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPassword,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    // Bij een verbindingsfout direct stoppen. De ruwe foutmelding van MySQL
    // staat erbij (handig om te leren), daaronder een korte checklist met de
    // meest voorkomende oorzaken bij gratis hosting zoals InfinityFree.
    // Error 1045 = "Access denied": de server is bereikbaar, maar de combinatie
    // gebruiker/wachtwoord/databasenaam klopt niet (of de DB bestaat nog niet).
    $fout = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    $isToegangGeweigerd = str_contains($e->getMessage(), '1045');

    $checklist = '';
    if ($isToegangGeweigerd) {
        $checklist = <<<HTML
<h2>Meest waarschijnlijke oorzaken (error 1045)</h2>
<ol>
<li><strong>Verkeerd wachtwoord.</strong> Bij InfinityFree is het database-wachtwoord
    hetzelfde als je <em>hosting-account-wachtwoord</em> (niet je client-area-wachtwoord).
    Te vinden in de client area: account &rarr; Manage &rarr; MySQL Details &rarr; Show bij Password.</li>
<li><strong>Verkeerde databasenaam.</strong> Die moet het volledige voorvoegsel hebben:
    <code>if0_42826696_jouwnaam</code> (zie MySQL Databases in het controlepaneel).
    Alleen <code>jouwnaam</code> invullen geeft exact deze 1045-fout.</li>
<li><strong>Verkeerde host.</strong> Gebruik de host uit het controlepaneel
    (zoals <code>sqlXXX.infinityfree.com</code>), nooit <code>localhost</code>.</li>
<li><strong>Database nog niet aangemaakt.</strong> Maak hem eerst aan via
    MySQL Databases en importeer daarna <code>init.sql</code> via phpMyAdmin.</li>
</ol>
HTML;
    }

    die(<<<HTML
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Databaseverbinding mislukt</title>
<style>
    body { font-family: system-ui, sans-serif; max-width: 46rem; margin: 2rem auto; padding: 0 1rem; line-height: 1.6; color: #1c1c1c; }
    h1 { font-size: 1.4rem; } h2 { font-size: 1.05rem; margin-top: 1.5rem; }
    code { background: #f3f3f3; padding: .1rem .3rem; border-radius: .25rem; font-size: .9em; }
    pre { background: #f3f3f3; padding: .75rem; border-radius: .5rem; overflow-x: auto; font-size: .85rem; }
    li { margin-bottom: .5rem; }
</style>
</head>
<body>
<h1>Databaseverbinding mislukt</h1>
<p><strong>Technische fout:</strong></p>
<pre>$fout</pre>
$checklist
</body>
</html>
HTML);
}
