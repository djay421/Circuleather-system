<?php
require 'db.php';

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $pdo->prepare("DELETE FROM voorraad WHERE id = ?");
    $stmt->execute([$id]);
}

header('Location: index.php?msg=deleted');
exit;
