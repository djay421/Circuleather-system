<?php require 'db.php'; ?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Circuleather CRM</title>
    <style>
        body { font-family: sans-serif; margin: 40px; background: #f7f7f5; color: #222; }
        h1 { color: #3a2e26; }
        table { border-collapse: collapse; width: 100%; background: #fff; }
        th, td { border: 1px solid #ddd; padding: 8px 12px; text-align: left; }
        th { background: #3a2e26; color: #fff; }
    </style>
</head>
<body>
    <h1>Circuleather CRM</h1>
    <p>Docker-omgeving werkt. Verbonden met database: <strong><?= htmlspecialchars($dbname) ?></strong></p>

    <h2>Klanten</h2>
    <?php
    $stmt = $pdo->query("SELECT * FROM klanten ORDER BY aangemaakt_op DESC");
    $klanten = $stmt->fetchAll();
    ?>
    <table>
        <tr><th>ID</th><th>Bedrijfsnaam</th><th>Email</th><th>Status</th></tr>
        <?php if (empty($klanten)): ?>
            <tr><td colspan="4">Nog geen klanten toegevoegd.</td></tr>
        <?php else: foreach ($klanten as $k): ?>
            <tr>
                <td><?= $k['id'] ?></td>
                <td><?= htmlspecialchars($k['bedrijfsnaam']) ?></td>
                <td><?= htmlspecialchars($k['email']) ?></td>
                <td><?= htmlspecialchars($k['status']) ?></td>
            </tr>
        <?php endforeach; endif; ?>
    </table>
</body>
</html>
