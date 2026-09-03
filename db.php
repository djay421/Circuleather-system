<?php
// Databaseverbinding via PDO, met credentials uit de omgevingsvariabelen
// die in docker-compose.yml zijn gezet.

$host = getenv('DB_HOST') ?: 'db';
$dbname = getenv('DB_NAME') ?: 'circuleather_crm';
$user = getenv('DB_USER') ?: 'crm_user';
$password = getenv('DB_PASSWORD') ?: 'crm_password';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Databaseverbinding mislukt: " . $e->getMessage());
}
