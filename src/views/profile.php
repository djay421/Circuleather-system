<?php
require __DIR__ . '\/..\/core\/auth.php';
require __DIR__ . '\/..\/core\/totp.php';
require __DIR__ . '\/..\/components\/functies.php';
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
            wisApparatenVanGebruiker($pdo, $id); // oude onthouden apparaten zijn niet meer geldig
            if ($actie === 'uit') {
                logActie($pdo, '2fa_uit', 'Tweestapsverificatie uitgezet');
                $melding = 'Tweestapsverificatie staat uit.';
            } else {
                header('Location: 2fa-setup.php');
                exit;
            }
        } else {
            $errors[] = 'Vul een geldige code in (Google Authenticator of een ongebruikte herstelcode).';
        }
    }

    // Een onthouden apparaat verwijderen (daar wordt dan weer om een code gevraagd).
    if ($actie === 'apparaat_weg') {
        $apparaatId = (int)($_POST['apparaat_id'] ?? 0);
        if ($apparaatId > 0 && wisApparaat($pdo, $apparaatId, $id)) {
            logActie($pdo, 'apparaat_verwijderd', 'Onthouden apparaat verwijderd');
            $melding = 'Apparaat verwijderd — daar wordt weer om een code gevraagd.';
        } else {
            $errors[] = 'Apparaat niet gevonden.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <?php $titel = 'Mijn account — Circuleather'; ?>
    <?php include 'head.php'; ?>
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

    <?php
    // Onthouden apparaten van deze gebruiker.
    $stmtA = $pdo->prepare(
        'SELECT id, label, ip, laatst_gebruikt, aangemaakt_op
         FROM apparaten WHERE gebruiker_id = ?
         ORDER BY COALESCE(laatst_gebruikt, aangemaakt_op) DESC'
    );
    $stmtA->execute([$id]);
    $apparaten = $stmtA->fetchAll();
    ?>

    <div class="kaart">
        <h2>Onthouden apparaten</h2>
        <?php if (empty($apparaten)): ?>
            <p class="meta">Geen onthouden apparaten. Vink bij de volgende 2FA-login
            “Onthoud dit apparaat” aan om 30 dagen lang geen code meer te hoeven invoeren
            op deze telefoon of computer.</p>
        <?php else: ?>
            <p class="meta">Op deze apparaten wordt de 2FA-code de komende 30 dagen overgeslagen.
            Verwijder een apparaat als je het kwijt bent of niet meer vertrouwt — dan wordt daar
            weer om een code gevraagd.</p>
            <?php foreach ($apparaten as $app): ?>
                <div class="apparaat-rij">
                    <div>
                        <strong><?= htmlspecialchars($app['label']) ?></strong>
                        <span class="meta" style="display:block;margin:0">
                            Laatst gebruikt: <?= htmlspecialchars(date('d-m-Y H:i', strtotime($app['laatst_gebruikt'] ?? $app['aangemaakt_op']))) ?>
                            <?= $app['ip'] ? ' · ' . htmlspecialchars($app['ip']) : '' ?>
                        </span>
                    </div>
                    <form method="post" style="margin:0">
                        <input type="hidden" name="actie" value="apparaat_weg">
                        <input type="hidden" name="apparaat_id" value="<?= (int)$app['id'] ?>">
                        <button type="submit" class="secondary" style="margin:0;min-height:40px"
                                onclick="return confirm('Dit apparaat vergeten? Daar wordt weer om een code gevraagd.')">Verwijderen</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
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
