<?php
require __DIR__ . '\/..\/core\/auth.php';
require __DIR__ . '\/..\/components\/functies.php';
vereisAdmin();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$bestaand = null;
if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM gebruikers WHERE id = ?');
    $stmt->execute([$id]);
    $bestaand = $stmt->fetch();
    if (!$bestaand) {
        header('Location: users.php');
        exit;
    }
}

$errors = [];
$input = [
    'naam' => $bestaand['naam'] ?? '',
    'email' => $bestaand['email'] ?? '',
    'rol' => $bestaand['rol'] ?? 'medewerker',
    'actief' => $bestaand ? (bool)$bestaand['actief'] : true,
    'wachtwoord' => '',
];

// 2FA uitzetten (bijv. verloren telefoon): alleen voor een bestaand account.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['actie'] ?? '') === 'reset_2fa') {
    require_once 'totp.php';
    if ($bestaand !== null && !empty($bestaand['totp_secret'])) {
        $upd = $pdo->prepare('UPDATE gebruikers SET totp_secret = NULL WHERE id = ?');
        $upd->execute([(int)$bestaand['id']]);
        wisHerstelcodes($pdo, (int)$bestaand['id']);
        wisApparatenVanGebruiker($pdo, (int)$bestaand['id']);
        logActie($pdo, '2fa_gereset', '2FA gereset voor ' . $bestaand['email']);
        header('Location: users.php?msg=2fa-gereset');
        exit;
    }
    header('Location: users.php');
    exit;
}

// Alle onthouden apparaten van dit account wissen.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['actie'] ?? '') === 'wis_apparaten') {
    if ($bestaand !== null) {
        wisApparatenVanGebruiker($pdo, (int)$bestaand['id']);
        logActie($pdo, 'apparaten_gewist', 'Alle onthouden apparaten gewist voor ' . $bestaand['email']);
        header('Location: user_edit.php?id=' . (int)$bestaand['id'] . '&msg=apparaten-gewist');
        exit;
    }
    header('Location: users.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = [
        'naam' => trim($_POST['naam'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'rol' => $_POST['rol'] ?? 'medewerker',
        'actief' => isset($_POST['actief']),
        'wachtwoord' => (string)($_POST['wachtwoord'] ?? ''),
    ];

    if ($input['naam'] === '') {
        $errors[] = 'Naam is verplicht.';
    }
    if ($input['email'] === '' || !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Vul een geldig e-mailadres in.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM gebruikers WHERE email = ? AND id <> ?');
        $stmt->execute([$input['email'], $id]);
        if ($stmt->fetch()) {
            $errors[] = 'Dit e-mailadres is al in gebruik.';
        }
    }
    if (!in_array($input['rol'], ['admin', 'medewerker'], true)) {
        $errors[] = 'Ongeldige rol.';
    }
    if ($bestaand === null && $input['wachtwoord'] === '') {
        $errors[] = 'Kies een wachtwoord voor het nieuwe account.';
    } elseif ($input['wachtwoord'] !== '' && mb_strlen($input['wachtwoord']) < 6) {
        $errors[] = 'Wachtwoord moet minimaal 6 tekens bevatten.';
    }

    // De laatste actieve beheerder kan zichzelf niet opheffen of degraderen.
    $huidige = ingelogdeGebruiker();
    $isZelf = $bestaand !== null && (int)$bestaand['id'] === (int)$huidige['id'];
    if ($isZelf && !$input['actief']) {
        $errors[] = 'Je kunt je eigen account niet deactiveren.';
    }
    if ($bestaand !== null
        && isActieveAdmin($pdo, (int)$bestaand['id'])
        && ($input['rol'] !== 'admin' || !$input['actief'])
        && isLaatsteActieveAdmin($pdo, (int)$bestaand['id'])) {
        $errors[] = 'De laatste actieve beheerder kan niet worden gedegradeerd of gedeactiveerd.';
    }

    if (empty($errors)) {
        $wachtwoordHash = $input['wachtwoord'] !== ''
            ? password_hash($input['wachtwoord'], PASSWORD_DEFAULT)
            : null;

        if ($bestaand === null) {
            $stmt = $pdo->prepare(
                'INSERT INTO gebruikers (naam, email, wachtwoord_hash, rol, actief) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $input['naam'],
                $input['email'],
                $wachtwoordHash,
                $input['rol'],
                $input['actief'] ? 1 : 0,
            ]);
            logActie($pdo, 'gebruiker_aangemaakt', 'Account aangemaakt voor ' . $input['email'] . ' (rol: ' . $input['rol'] . ')');
            header('Location: users.php?msg=created');
            exit;
        }

        $stmt = $pdo->prepare('UPDATE gebruikers SET naam = ?, email = ?, rol = ?, actief = ? WHERE id = ?');
        $stmt->execute([
            $input['naam'],
            $input['email'],
            $input['rol'],
            $input['actief'] ? 1 : 0,
            $id,
        ]);
        if ($wachtwoordHash !== null) {
            $stmt = $pdo->prepare('UPDATE gebruikers SET wachtwoord_hash = ? WHERE id = ?');
            $stmt->execute([$wachtwoordHash, $id]);
        }
        logActie($pdo, 'gebruiker_bijgewerkt', 'Account bijgewerkt: ' . $input['email'] . ' (rol: ' . $input['rol'] . ')');
        header('Location: users.php?msg=updated');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <?php $titel = ($bestaand ? 'Medewerker bewerken' : 'Nieuwe medewerker') . ' — Circuleather'; ?>
    <?php include 'head.php'; ?>
</head>
<body>
    <?php include 'nav.php'; ?>
    <a class="back-link" href="users.php">&larr; Terug naar medewerkers</a>
    <h1><?= $bestaand ? 'Medewerker bewerken — ' . htmlspecialchars($bestaand['naam']) : 'Nieuwe medewerker' ?></h1>

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
        <?php if ($bestaand): ?>
            <input type="hidden" name="id" value="<?= (int)$bestaand['id'] ?>">
        <?php endif; ?>

        <label for="naam">Naam *</label>
        <input type="text" id="naam" name="naam" value="<?= htmlspecialchars($input['naam']) ?>" required>

        <label for="email">E-mailadres (inlognaam) *</label>
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($input['email']) ?>" required>

        <label for="rol">Rol</label>
        <select id="rol" name="rol">
            <option value="medewerker" <?= $input['rol'] === 'medewerker' ? 'selected' : '' ?>>Medewerker (voorraad bijhouden en scannen)</option>
            <option value="admin" <?= $input['rol'] === 'admin' ? 'selected' : '' ?>>Beheerder (alles + medewerkers beheren)</option>
        </select>

        <label for="wachtwoord"><?= $bestaand ? 'Nieuw wachtwoord' : 'Wachtwoord *' ?></label>
        <input type="password" id="wachtwoord" name="wachtwoord"
               <?= $bestaand ? '' : 'required' ?>
               placeholder="<?= $bestaand ? 'Leeg laten om het huidige wachtwoord te behouden' : 'Minimaal 6 tekens' ?>">

        <label class="checkbox-rij">
            <input type="checkbox" name="actief" <?= $input['actief'] ? 'checked' : '' ?>> Account is actief (kan inloggen)
        </label>

        <button type="submit"><?= $bestaand ? 'Opslaan' : 'Account aanmaken' ?></button>
    </form>

    <?php if ($bestaand):
        // Aantal onthouden apparaten van dit account.
        $stmtApp = $pdo->prepare('SELECT COUNT(*) FROM apparaten WHERE gebruiker_id = ?');
        $stmtApp->execute([(int)$bestaand['id']]);
        $aantalApparaten = (int)$stmtApp->fetchColumn();
        ?>
        <div class="kaart">
            <h2>Tweestapsverificatie</h2>
            <?php if (!empty($bestaand['totp_secret'])): ?>
                <p class="meta">2FA staat <span class="badge beschikbaar">Aan</span> voor dit account.
                Zet hem uit als iemand zijn telefoon kwijt is — de gebruiker moet daarna bij de volgende
                login (of via Mijn account) 2FA opnieuw instellen.</p>
                <form method="post">
                    <input type="hidden" name="actie" value="reset_2fa">
                    <button type="submit"
                            onclick="return confirm('2FA voor dit account uitzetten?')">2FA uitzetten voor dit account</button>
                </form>
            <?php else: ?>
                <p class="meta">2FA staat uit voor dit account.
                <?= $bestaand['rol'] === 'admin'
                    ? 'Beheerders moeten 2FA instellen bij hun volgende login.'
                    : 'De medewerker kan het zelf aanzetten via Mijn account.' ?></p>
            <?php endif; ?>

            <h2 style="margin-top:22px">Onthouden apparaten</h2>
            <p class="meta"><?= $aantalApparaten ?> apparaat<?= $aantalApparaten === 1 ? '' : 'en' ?> onthouden
            (2FA wordt daar 30 dagen overgeslagen). Wis ze als er een verloren of onbekend apparaat tussen zit:</p>
            <form method="post">
                <input type="hidden" name="actie" value="wis_apparaten">
                <button type="submit" class="secondary"
                        onclick="return confirm('Alle onthouden apparaten van dit account vergeten?')">Alle apparaten wissen</button>
            </form>
        </div>
    <?php endif; ?>
</body>
</html>
