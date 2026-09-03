<?php
require 'auth.php';
require 'functies.php';
vereisAdmin();

$fout = '';
$verwijderId = (int)($_GET['verwijder'] ?? 0);
if ($verwijderId > 0) {
    $huidige = ingelogdeGebruiker();
    if ($verwijderId === (int)$huidige['id']) {
        $fout = 'Je kunt je eigen account niet verwijderen.';
    } elseif (isActieveAdmin($pdo, $verwijderId) && isLaatsteActieveAdmin($pdo, $verwijderId)) {
        $fout = 'De laatste actieve beheerder kan niet worden verwijderd.';
    } else {
        $stmt = $pdo->prepare('SELECT naam, email FROM gebruikers WHERE id = ?');
        $stmt->execute([$verwijderId]);
        $weg = $stmt->fetch();
        $stmt = $pdo->prepare('DELETE FROM gebruikers WHERE id = ?');
        $stmt->execute([$verwijderId]);
        logActie($pdo, 'gebruiker_verwijderd', 'Account verwijderd: ' . ($weg['email'] ?? '#' . $verwijderId));
        header('Location: users.php?msg=deleted');
        exit;
    }
}

$berichten = [
    'created' => 'Account aangemaakt.',
    'updated' => 'Account bijgewerkt.',
    'deleted' => 'Medewerker verwijderd.',
    '2fa-gereset' => 'Tweestapsverificatie uitgezet voor dit account.',
];
$msg = $_GET['msg'] ?? '';

$gebruikers = $pdo->query('SELECT id, naam, email, rol, actief, totp_secret, aangemaakt_op FROM gebruikers ORDER BY naam')->fetchAll();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <?php $titel = 'Medewerkers — Circuleather'; ?>
    <?php include 'head.php'; ?>
</head>
<body>
    <?php include 'nav.php'; ?>
    <h1>Medewerkers</h1>

    <?php if ($fout): ?>
        <div class="fout"><?= htmlspecialchars($fout) ?></div>
    <?php elseif (isset($berichten[$msg])): ?>
        <div class="msg"><?= htmlspecialchars($berichten[$msg]) ?></div>
    <?php endif; ?>

    <div class="knoppen">
        <a href="user_edit.php">+ Nieuwe medewerker</a>
        <a class="secondary" href="logboek.php">📋 Logboek bekijken</a>
        <a class="secondary" href="labels.php">🏷 QR-labels genereren</a>
    </div>
    <table>
        <thead>
            <tr>
                <th>Naam</th>
                <th>E-mailadres</th>
                <th>Rol</th>
                <th>2FA</th>
                <th>Actief</th>
                <th>Aangemaakt</th>
                <th>Acties</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($gebruikers as $u): ?>
            <tr>
                <td data-label="Naam"><?= htmlspecialchars($u['naam']) ?></td>
                <td data-label="E-mailadres"><?= htmlspecialchars($u['email']) ?></td>
                <td data-label="Rol"><span class="badge <?= htmlspecialchars($u['rol']) ?>"><?= htmlspecialchars(rolLabel($u['rol'])) ?></span></td>
                <td data-label="2FA"><?= !empty($u['totp_secret'])
                    ? '<span class="badge beschikbaar">Aan</span>'
                    : '<span class="inactief">Uit</span>' ?></td>
                <td class="<?= $u['actief'] ? '' : 'inactief' ?>" data-label="Actief"><?= $u['actief'] ? 'Ja' : 'Nee (geblokkeerd)' ?></td>
                <td data-label="Aangemaakt"><?= htmlspecialchars($u['aangemaakt_op']) ?></td>
                <td class="acties" data-label="Acties">
                    <a href="user_edit.php?id=<?= (int)$u['id'] ?>">Bewerken</a>
                    <a class="wissen" href="users.php?verwijder=<?= (int)$u['id'] ?>"
                       onclick="return confirm('Weet je zeker dat je dit account wilt verwijderen?');">Verwijderen</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
