<?php
require 'auth.php';
vereisLogin();
require 'functies.php';

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $pdo->prepare('SELECT code, categorie, foto FROM voorraad WHERE id = ?');
    $stmt->execute([$id]);
    $rij = $stmt->fetch();
    if ($rij) {
        if (!empty($rij['foto'])) {
            verwijderFotoBestand($rij['foto']);
        }
        $stmt = $pdo->prepare('DELETE FROM voorraad WHERE id = ?');
        $stmt->execute([$id]);
        logActie($pdo, 'verwijderd', ucfirst($rij['categorie'] ?? 'voorraad') . ' '
            . ($rij['code'] ?: '#' . $id) . ' verwijderd');
    }
}

header('Location: index.php?msg=deleted');
exit;