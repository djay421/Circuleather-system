<?php
require 'auth.php';

if (ingelogdeGebruiker() !== null) {
    header('Location: index.php');
    exit;
}

$errors = [];
$email = '';
$redirect = trim((string)($_GET['redirect'] ?? ''));
$redirectVeilig = isVeiligePaginaUrl($redirect);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $wachtwoord = (string)($_POST['wachtwoord'] ?? '');

    if ($email === '' || $wachtwoord === '') {
        $errors[] = 'Vul je e-mailadres en wachtwoord in.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, naam, email, wachtwoord_hash, rol, actief, totp_secret FROM gebruikers WHERE email = ?'
        );
        $stmt->execute([$email]);
        $gebruiker = $stmt->fetch();

        if (!$gebruiker || !(bool)$gebruiker['actief'] || !password_verify($wachtwoord, $gebruiker['wachtwoord_hash'])) {
            $errors[] = 'Onjuist e-mailadres of wachtwoord.';
        } else {
            $redirect = trim((string)($_POST['redirect'] ?? ''));
            $doel = isVeiligePaginaUrl($redirect) ? $redirect : 'index.php';

            // Tweede stap: Google Authenticator. Beheerders móéten 2FA hebben;
            // medewerkers die het (nog) niet aan hebben gezet kunnen direct door.
            if (!empty($gebruiker['totp_secret'])) {
                setTweeStapWacht($gebruiker, $doel);
                header('Location: tweestap.php');
                exit;
            }
            if ($gebruiker['rol'] === 'admin') {
                setTweeStapWacht($gebruiker, $doel);
                header('Location: 2fa-setup.php');
                exit;
            }
            meldAan($gebruiker);
            header('Location: ' . $doel);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inloggen — Circuleather</title>
    <link rel="stylesheet" href="style.css?v=3">
</head>
<body class="login-pagina">
    <div class="login-box">
        <div class="login-merk">
            <div class="circu">Circuleather</div>
            <div class="tagline">re-<span class="hart">❤</span> leather · leeropslag</div>
        </div>
        <h1>Inloggen</h1>
        <p class="meta">Log in om de voorraad te bekijken of te beheren.</p>

        <?php if (($_GET['msg'] ?? '') === 'uitgelogd'): ?>
            <div class="msg">Je bent uitgelogd.</div>
        <?php elseif (($_GET['msg'] ?? '') === 'verlopen'): ?>
            <div class="fout">De tweede stap is verlopen. Log opnieuw in.</div>
        <?php endif; ?>

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
            <?php if ($redirectVeilig): ?>
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
            <?php endif; ?>

            <label for="email">E-mailadres</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required autofocus>

            <label for="wachtwoord">Wachtwoord</label>
            <input type="password" id="wachtwoord" name="wachtwoord" required>

            <button type="submit">Inloggen</button>
        </form>

        <p class="voetnoot">Wachtwoord vergeten of nog geen toegang?<br>Neem contact op met de beheerder.</p>
    </div>
</body>
</html>
