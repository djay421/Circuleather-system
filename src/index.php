<?php require 'db.php'; ?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Circuleather</title>
    <style>
        body { font-family: sans-serif; margin: 40px; background: #f7f7f5; color: #222; }
        h1 { color: #3a2e26; }
        table { border-collapse: collapse; width: 100%; background: #fff; }
        th, td { border: 1px solid #ddd; padding: 8px 12px; text-align: left; }
        th { background: #3a2e26; color: #fff; }
    </style>
</head>
<body>
    <h1>Circuleather</h1>
    <p>Verbonden met database: <strong><?= htmlspecialchars(getenv('DB_NAME') ?: 'circuleather_crm') ?></strong></p>

    <button onclick="window.location.href='add.php'">Nieuwe voorraad toevoegen</button>

    <button onclick="window.location.href=''">Voorraad verwijderen</button>

    <button onclick="window.location.href=''">Voorraad aanpassen</button>

    <h2>Voorraad</h2>
    <?php
    $stmt = $pdo->query("SELECT * FROM voorraad ORDER BY aangemaakt_op DESC");
    $voorraad = $stmt->fetchAll();
    ?>
    <table>
        <tr><th>ID</th><th>Partij-code</th><th>Locatie</th><th>Gewicht (kg)</th><th>Kleur</th><th>Breedte (cm)</th><th>Lengte (cm)</th><th>Status</th></tr>
        <?php if (empty($voorraad)): ?>
            <tr><td colspan="8">Nog geen voorraad toegevoegd.</td></tr>
        <?php else: foreach ($voorraad as $v): ?>
            <tr>
                <td><?= $v['id'] ?></td>
                <td><?= htmlspecialchars($v['partij-code']) ?></td>
                <td><?= htmlspecialchars($v['locatie']) ?></td>
                <td><?= htmlspecialchars($v['gewicht (kg)']) ?></td>
                <td><?= htmlspecialchars($v['kleur']) ?></td>
                <td><?= htmlspecialchars($v['breedte (cm)']) ?></td>
                <td><?= htmlspecialchars($v['lengte (cm)']) ?></td>
                <td><?= htmlspecialchars($v['status']) ?></td>
            </tr>
        <?php endforeach; endif; ?>
    </table>
</body>
</html>
