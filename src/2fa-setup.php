<?php
require 'auth.php';
require 'totp.php';

// Twee situaties:
// 1) Verplicht: beheerder is net ingelogd met wachtwoord, 2FA staat nog uit (wachtende stap).
// 2) Vrijwillig: iemand die al is ingelogd stelt 2FA in (of opnieuw in) via Mijn account.
$viaWacht = tweeStapWacht();
// Net geactiveerd: de herstelcodes staan klaar om één keer te tonen. Zolang die
// in de sessie staan, mag de gebruiker hier blijven om ze op te slaan.
$netGeactiveerd = !empty($_SESSION['2fa_codes']);

if ($viaWacht !== null) {
    $stmt = $pdo->prepare('SELECT id, naam, email, rol, totp_secret FROM gebruikers WHERE id = ?');
    $stmt->execute([(int)$viaWacht['id']]);
    $gebruiker = $stmt->fetch();
    $doel = $viaWacht['doel'] ?? 'index.php';
    if (!$gebruiker) {
        wisTweeStapWacht();
        header('Location: login.php');
        exit;
    }
    if (!empty($gebruiker['totp_secret']) && !$netGeactiveerd) {
        // 2FA staat al aan en er is niets om te bevestigen: de code vragen.
        header('Location: tweestap.php');
        exit;
    }
} else {
    vereisLogin();
    $gebruiker = ingelogdeGebruiker();
    $doel = 'profile.php?msg=2fa-aan';
}

if (!empty($gebruiker['totp_secret']) && $viaWacht === null) {
    header('Location: profile.php?msg=2fa-al-aan');
    exit;
}

$errors = [];
$fase = 'qr'; // 'qr' = code invoeren, 'codes' = herstelcodes tonen
$codes = [];

if (!isset($_SESSION['2fa_setup_geheim'])) {
    $_SESSION['2fa_setup_geheim'] = genereerTotpGeheim();
}
$geheim = $_SESSION['2fa_setup_geheim'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stap = (string)($_POST['stap'] ?? '');

    if ($stap === 'code') {
        $code = trim((string)($_POST['code'] ?? ''));
        if (!valideerTotp($geheim, $code)) {
            $errors[] = 'Die code klopt niet. Open Google Authenticator en scan de QR-code opnieuw, '
                . 'of controleer of de tijd van je telefoon klopt.';
        } else {
            // Geheim activeren + nieuwe herstelcodes klaarzetten.
            $upd = $pdo->prepare('UPDATE gebruikers SET totp_secret = ? WHERE id = ?');
            $upd->execute([$geheim, (int)$gebruiker['id']]);
            wisHerstelcodes($pdo, (int)$gebruiker['id']);
            $codes = genereerHerstelcodes(10);
            bewaarHerstelcodes($pdo, (int)$gebruiker['id'], $codes);
            unset($_SESSION['2fa_setup_geheim']);
            $_SESSION['2fa_codes'] = $codes;
            $fase = 'codes';
        }
    } elseif ($stap === 'klaar') {
        // Herstelcodes zijn opgeslagen; inloggen afronden.
        $codes = $_SESSION['2fa_codes'] ?? [];
        unset($_SESSION['2fa_codes']);
        if ($viaWacht !== null) {
            meldAan($gebruiker);
            wisTweeStapWacht();
        }
        header('Location: ' . $doel);
        exit;
    }
}

$uri = otpauthUri($geheim, $gebruiker['email']);
$codes = $fase === 'codes' && empty($codes) ? ($_SESSION['2fa_codes'] ?? []) : $codes;
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>2FA instellen — Circuleather</title>
    <link rel="stylesheet" href="style.css?v=4">
    <style>
        .qr-vak { display: flex; justify-content: center; margin: 14px 0 4px; }
        .qr-vak svg { width: 220px; height: 220px; }
        .geheim { text-align: center; font-family: ui-monospace, monospace; letter-spacing: .08em; }
        .rc-rij { display: flex; flex-wrap: wrap; gap: 6px; justify-content: center; }
        .rc-rij span { font-family: ui-monospace, monospace; background: #f3f0ea; border: 1px solid #ddd6c8; padding: 4px 10px; border-radius: 8px; font-size: 14px; }
    </style>
</head>
<body class="login-pagina">
    <div class="login-box">
        <?php if ($fase === 'codes'): ?>
            <h1>Herstelcodes bewaard?</h1>
            <p class="meta"><strong>2FA is nu aan.</strong> Deze tien eenmalige codes zijn je redding als je
            telefoon kwijtraakt of gereset wordt. Bewaar ze goed (print of foto) — elke code kan één keer
            worden gebruikt in plaats van de authenticator-code.</p>
            <div class="rc-rij">
                <?php foreach ($codes as $code): ?>
                    <span><?= htmlspecialchars($code) ?></span>
                <?php endforeach; ?>
            </div>
            <form method="post">
                <input type="hidden" name="stap" value="klaar">
                <button type="submit">Ik heb mijn herstelcodes opgeslagen</button>
            </form>
        <?php else: ?>
            <h1>Tweestapsverificatie</h1>
            <?php if ($viaWacht !== null): ?>
                <p class="meta">Welkom <?= htmlspecialchars($gebruiker['naam']) ?>. Voor beheerders is
                tweestapsverificatie <strong>verplicht</strong>. Installeer de app
                <strong>Google Authenticator</strong> (of Authy/Microsoft Authenticator) op je telefoon
                en scan deze QR-code.</p>
            <?php else: ?>
                <p class="meta">Scan deze QR-code met <strong>Google Authenticator</strong> (of een
                vergelijkbare app) om tweestapsverificatie aan te zetten voor
                <?= htmlspecialchars($gebruiker['naam']) ?>.</p>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="errors"><ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul></div>
            <?php endif; ?>

            <div class="qr-vak" data-uri="<?= htmlspecialchars($uri) ?>"></div>
            <p class="meta" style="text-align:center">Kun je niet scannen? Typ dit geheim handmatig:</p>
            <p class="geheim"><?= htmlspecialchars($geheim) ?></p>

            <form method="post">
                <input type="hidden" name="stap" value="code">
                <label for="code">6-cijferige code uit de app</label>
                <input type="text" id="code" name="code" inputmode="numeric" placeholder="000000"
                       required autofocus autocomplete="one-time-code">
                <button type="submit">Activeren</button>
            </form>
            <p class="voetnoot"><a href="<?= $viaWacht !== null ? 'logout.php' : 'profile.php' ?>">Annuleren</a></p>
        <?php endif; ?>
    </div>

    <?php if ($fase === 'qr'): ?>
    <script src="qrcode.min.js"></script>
    <script>
        (function () {
            var vak = document.querySelector('.qr-vak');
            if (!vak) { return; }
            var uri = vak.getAttribute('data-uri');
            try {
                var qr = qrcode(0, 'M');
                qr.addData(uri);
                qr.make();
                vak.innerHTML = qr.createSvgTag(4, 1);
            } catch (e) {
                vak.textContent = '(QR niet beschikbaar)';
            }
        })();
    </script>
    <?php endif; ?>
</body>
</html>
