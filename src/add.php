<?php
require 'db.php';

$errors = [];
$input = [
    'partij_code' => '',
    'locatie' => '',
    'gewicht_kg' => '',
    'kleur' => '',
    'breedte_cm' => '',
    'lengte_cm' => '',
    'status' => 'beschikbaar',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($input as $key => $default) {
        $input[$key] = trim($_POST[$key] ?? '');
    }

    if ($input['partij_code'] === '') {
        $errors[] = 'Partij-code is verplicht.';
    }
    if ($input['gewicht_kg'] !== '' && !is_numeric($input['gewicht_kg'])) {
        $errors[] = 'Gewicht moet een getal zijn.';
    }
    if ($input['breedte_cm'] !== '' && !is_numeric($input['breedte_cm'])) {
        $errors[] = 'Breedte moet een getal zijn.';
    }
    if ($input['lengte_cm'] !== '' && !is_numeric($input['lengte_cm'])) {
        $errors[] = 'Lengte moet een getal zijn.';
    }
    $geldigeStatussen = ['beschikbaar', 'gereserveerd', 'in_bewerking', 'verkocht'];
    if (!in_array($input['status'], $geldigeStatussen, true)) {
        $errors[] = 'Ongeldige status.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            "INSERT INTO voorraad (partij_code, locatie, gewicht_kg, kleur, breedte_cm, lengte_cm, status)
             VALUES (:partij_code, :locatie, :gewicht_kg, :kleur, :breedte_cm, :lengte_cm, :status)"
        );
        $stmt->execute([
            'partij_code' => $input['partij_code'],
            'locatie' => $input['locatie'],
            'gewicht_kg' => $input['gewicht_kg'] !== '' ? $input['gewicht_kg'] : 0,
            'kleur' => $input['kleur'],
            'breedte_cm' => $input['breedte_cm'] !== '' ? $input['breedte_cm'] : 0,
            'lengte_cm' => $input['lengte_cm'] !== '' ? $input['lengte_cm'] : 0,
            'status' => $input['status'],
        ]);

        header('Location: index.php?msg=added');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Circuleather — Voorraad toevoegen</title>
    <style>
        body { font-family: sans-serif; margin: 40px; background: #f7f7f5; color: #222; }
        h1 { color: #3a2e26; }
        form { background: #fff; padding: 20px; border-radius: 6px; max-width: 500px; }
        label { display: block; margin-top: 12px; font-weight: bold; font-size: 14px; }
        input, select { width: 100%; padding: 8px; margin-top: 4px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        button { margin-top: 20px; padding: 10px 20px; background: #3a2e26; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #57453a; }
        .back-link { display: inline-block; margin-bottom: 15px; color: #3a7ab8; text-decoration: none; }
        .errors { background: #fbe5e5; border: 1px solid #d8a8a8; color: #7a2020; padding: 10px 15px; border-radius: 4px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <a class="back-link" href="index.php">&larr; Terug naar overzicht</a>
    <h1>Nieuwe voorraad toevoegen</h1>

    <?php if (!empty($errors)): ?>
        <div class="errors">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post">
        <label for="partij_code">Partij-code *</label>
        <input type="text" id="partij_code" name="partij_code" value="<?= htmlspecialchars($input['partij_code']) ?>" required>

        <label for="locatie">Locatie</label>
        <input type="text" id="locatie" name="locatie" value="<?= htmlspecialchars($input['locatie']) ?>">

        <label for="gewicht_kg">Gewicht (kg)</label>
        <input type="number" step="0.01" id="gewicht_kg" name="gewicht_kg" value="<?= htmlspecialchars($input['gewicht_kg']) ?>">

        <label for="kleur">Kleur</label>
        <input type="text" id="kleur" name="kleur" value="<?= htmlspecialchars($input['kleur']) ?>">

        <label for="breedte_cm">Breedte (cm)</label>
        <input type="number" step="0.01" id="breedte_cm" name="breedte_cm" value="<?= htmlspecialchars($input['breedte_cm']) ?>">

        <label for="lengte_cm">Lengte (cm)</label>
        <input type="number" step="0.01" id="lengte_cm" name="lengte_cm" value="<?= htmlspecialchars($input['lengte_cm']) ?>">

        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="beschikbaar" <?= $input['status'] === 'beschikbaar' ? 'selected' : '' ?>>Beschikbaar</option>
            <option value="gereserveerd" <?= $input['status'] === 'gereserveerd' ? 'selected' : '' ?>>Gereserveerd</option>
            <option value="in_bewerking" <?= $input['status'] === 'in_bewerking' ? 'selected' : '' ?>>In bewerking</option>
            <option value="verkocht" <?= $input['status'] === 'verkocht' ? 'selected' : '' ?>>Verkocht</option>
        </select>

        <button type="submit">Toevoegen</button>
    </form>
</body>
</html>
