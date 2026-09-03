<?php
require 'auth.php';
vereisLogin();
require 'functies.php';

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $pdo->prepare('SELECT foto FROM voorraad WHERE id = ?');
    $stmt->execute([$id]);
    $foto = $stmt->fetchColumn();
    if ($foto !== false && $foto !== null) {
        verwijderFotoBestand($foto);
    }
    $stmt = $pdo->prepare('DELETE FROM voorraad WHERE id = ?');
    $stmt->execute([$id]);
}

header('Location: index.php?msg=deleted');
exit;
