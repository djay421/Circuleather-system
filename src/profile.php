<?php
require 'auth.php';
require 'totp.php';
vereisLogin();

$gebruiker = ingelogdeGebruiker();
$id = (int)$gebruiker['id'];

$stmt = $pdo->prepare('SELECT id, naam, email, rol, actief, totp_secret FROM gebruikers WHERE id = ?');
$stmt->execute([$id]);
$rij = $stmt->fetch();
if (!$rij) {
    header('Location: logout.php');
    exit;
}

$errors = [];
$melding = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actie = (string)($_POST['actie'] ?? '');
    $code = trim((string)($_POST['code'] ?? ''));

    // Uitzetten of opnieuw instellen mag alleen met een geldige code
    // (authenticator of ongebruikte herstelcode), zodat een gestolen
    // sessie 2FA niet stilletjes kan uitzetten.
    if (in_array($actie, ['uit', 'vervang'], true)) {
        if (!empty($rij['totp_secret'])
            && (valideerTotp($rij['totp_secret'], $code) || gebruikHerstelcode($pdo, $id, $code))) {
            $upd = $pdo->prepare('UPDATE gebruikers SET totp_secret = NULL WHERE id = ?');
            $upd->execute([$id]);
            wisHerstelcodes($pdo, $id);
            if ($actie === 'uit') {
                $melding = 'Tweestapsverificatie staat uit.';
            } else {
                header('Location: 2fa-setup.php');
                exit;
            }
        } else {
            $errors[] = 'Vul een geldige code in (Google Authenticator of een ongebruikte herstelcode).';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mijn account — Circuleather</title>
    <link rel="stylesheet" href="style.css?v=3">
</head>
<body>
    <?php include 'nav.php'; ?>
    <a class="back-link" href="index.php">&larr; Terug naar voorraad</a>
    <h1>Mijn account</h1>

    <?php if ($melding): ?>
        <div class="msg"><?= htmlspecialchars($melding) ?></div>
    <?php endif; ?>
    <?php if (($_GET['msg'] ?? '') === '2fa-aan'): ?>
        <div class="msg">Tweestapsverificatie is ingeschakeld voor je account.</div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="errors"><ul>
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul></div>
    <?php endif; ?>

    <div class="kaart">
        <h2>Account</h2>
        <dl>
            <dt>Naam</dt><dd><?= htmlspecialchars($rij['naam']) ?></dd>
            <dt>E-mailadres</dt><dd><?= htmlspecialchars($rij['email']) ?></dd>
            <dt>Rol</dt><dd><span class="badge <?= htmlspecialchars($rij['rol']) ?>"><?= htmlspecialchars(rolLabel($rij['rol'])) ?></span></dd>
        </dl>
        <p class="meta">Wachtwoord wijzigen kan de beheerder doen via Medewerkers → Bewerken.</p>
    </div>

    <div class="kaart">
        <h2>Tweestapsverificatie (Google Authenticator)</h2>
        <?php if (!empty($rij['totp_secret'])): ?>
            <p class="meta"><span class="badge beschikbaar">Aan</span> Bij elke inlogbeurt wordt ná je
            wachtwoord een 6-cijferige code uit de app gevraagd.</p>
            <p class="meta">De eenmalige herstelcodes zijn bij het instellen getoond. Ben je ze kwijt?
            Kies “Opnieuw instellen” en bewaar de nieuwe codes goed.</p>
            <form method="post">
                <input type="hidden" name="actie" value="uit">
                <label for="code-uit">Code om 2FA uit te zetten</label>
                <input type="text" id="code-uit" name="code" inputmode="numeric" placeholder="000000" required>
                <button type="submit">2FA uitzetten</button>
            </form>
            <form method="post">
                <input type="hidden" name="actie" value="vervang">
                <label for="code-vervang">Code om 2FA opnieuw in te stellen (nieuwe QR + herstelcodes)</label>
                <input type="text" id="code-vervang" name="code" inputmode="numeric" placeholder="000000" required>
                <button type="submit">Opnieuw instellen</button>
            </form>
        <?php else: ?>
            <p class="meta">Nog niet ingeschakeld.
            <?php if ($rij['rol'] === 'admin'): ?>
                Voor beheerders is dit <strong>verplicht</strong> — de volgende keer dat je inlogt word je
                er doorheen geleid. Je kunt het ook nu alvast doen:
            <?php else: ?>
                Vrijwillig, maar sterk aangeraden (zeker als je buiten het magazijn inlogt):
            <?php endif; ?>
            </p>
            <div class="knoppen">
                <a href="2fa-setup.php">2FA instellen</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
