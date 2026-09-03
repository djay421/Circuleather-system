<?php
require 'auth.php';
require 'functies.php';

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
            logActie($pdo, 'inloggen_mislukt', 'Mislukte inlogpoging voor ' . $email);
        } else {
            $redirect = trim((string)($_POST['redirect'] ?? ''));
            $doel = isVeiligePaginaUrl($redirect) ? $redirect : 'index.php';

            // Tweede stap: Google Authenticator. Beheerders móéten 2FA hebben;
            // medewerkers die het (nog) niet aan hebben gezet kunnen direct door.
            if (!empty($gebruiker['totp_secret'])) {
                // Dit apparaat staat in de onthoud-cookie: 2FA overslaan.
                $onthouden = onthoudenApparaatGeldig($pdo);
                if ($onthouden !== null && (int)$onthouden['gebruiker_id'] === (int)$gebruiker['id']) {
                    meldAan($gebruiker);
                    logActie($pdo, 'inloggen', 'Ingelogd vanaf onthouden apparaat (' . apparaatLabel() . ')');
                    header('Location: ' . $doel);
                    exit;
                }
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
            logActie($pdo, 'inloggen', 'Ingelogd als ' . $gebruiker['naam']);
            header('Location: ' . $doel);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <?php $titel = 'Inloggen — Circuleather'; ?>
    <?php include 'head.php'; ?>
</head>
<body class="login-pagina">
    <div class="login-box">
        <div class="login-merk">
            <div class="app-icoon">❤</div>
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
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required autofocus autocomplete="email">

            <label for="wachtwoord">Wachtwoord</label>
            <div class="wachtwoord-rij">
                <input type="password" id="wachtwoord" name="wachtwoord" required autocomplete="current-password">
                <button type="button" class="toon-wachtwoord"
                        onclick="var i = document.getElementById('wachtwoord'); i.type = i.type === 'password' ? 'text' : 'password'; this.textContent = i.type === 'password' ? 'Toon' : 'Verberg';">Toon</button>
            </div>

            <button type="submit">Inloggen</button>
        </form>

        <p class="voetnoot">Wachtwoord vergeten of nog geen toegang?<br>Neem contact op met de beheerder.</p>
    </div>
</body>
</html>
