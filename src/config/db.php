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
    die("Databaseverbinding mislukt: " . $e->getMessage());
}
