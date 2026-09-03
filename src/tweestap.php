<?php
require 'auth.php';
require 'totp.php';
require 'functies.php';

// Al ingelogd? Dan is deze stap niet nodig.
if (ingelogdeGebruiker() !== null) {
    header('Location: index.php');
    exit;
}

$wacht = tweeStapWacht();
if ($wacht === null) {
    header('Location: login.php?msg=verlopen');
    exit;
}

$stmt = $pdo->prepare('SELECT id, naam, email, rol, actief, totp_secret FROM gebruikers WHERE id = ?');
$stmt->execute([(int)$wacht['id']]);
$gebruiker = $stmt->fetch();
if (!$gebruiker || !(bool)$gebruiker['actief'] || empty($gebruiker['totp_secret'])) {
    // Account gewijzigd of 2FA net uitgezet: opnieuw inloggen.
    wisTweeStapWacht();
    header('Location: login.php');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim((string)($_POST['code'] ?? ''));
    if ($code === '') {
        $errors[] = 'Vul de code in.';
    } elseif (valideerTotp($gebruiker['totp_secret'], $code)
        || gebruikHerstelcode($pdo, (int)$gebruiker['id'], $code)) {
        meldAan($gebruiker);
        if (!empty($_POST['onthouden'])) {
            onthoudApparaat($pdo, (int)$gebruiker['id']);
            logActie($pdo, 'apparaat_onthouden', 'Dit apparaat wordt 30 dagen onthouden (' . apparaatLabel() . ')');
        }
        logActie($pdo, 'inloggen', 'Ingelogd met 2FA als ' . $gebruiker['naam']);
        $doel = $wacht['doel'] ?? 'index.php';
        wisTweeStapWacht();
        header('Location: ' . (isVeiligePaginaUrl($doel) ? $doel : 'index.php'));
        exit;
    } else {
        $errors[] = 'Onjuiste code. Controleer Google Authenticator of gebruik een ongebruikte herstelcode.';
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <?php $titel = 'Tweede stap — Circuleather'; ?>
    <?php include 'head.php'; ?>
</head>
<body class="login-pagina">
    <div class="login-box">
        <h1>Tweede stap</h1>
        <p class="meta">Hoi <?= htmlspecialchars($gebruiker['naam']) ?>, vul de 6-cijferige code uit
        <strong>Google Authenticator</strong> in (of een ongebruikte herstelcode, bijv. <code>AB12-CD34</code>).</p>

        <?php if (!empty($errors)): ?>
            <div class="errors"><ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul></div>
        <?php endif; ?>

        <form method="post">
            <label for="code">Authenticator-code of herstelcode</label>
            <input type="text" id="code" name="code" inputmode="numeric" autocomplete="one-time-code"
                   placeholder="000000" required autofocus>
            <label class="checkbox-rij" style="margin-top:14px">
                <input type="checkbox" name="onthouden" value="1" checked>
                Onthoud dit apparaat 30 dagen (geen code meer vragen op deze telefoon)
            </label>
            <button type="submit">Verifiëren</button>
        </form>

        <p class="voetnoot"><a href="logout.php">Annuleren</a></p>
    </div>
</body>
</html>
