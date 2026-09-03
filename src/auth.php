<?php
// Sessie- en rechtenhulp voor Circuleather.

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'path' => '/']);
    session_start();
}

/** De ingelogde gebruiker (id, naam, email, rol) of null. */
function ingelogdeGebruiker(): ?array
{
    return $_SESSION['gebruiker'] ?? null;
}

function isAdmin(): bool
{
    $g = ingelogdeGebruiker();
    return $g !== null && ($g['rol'] ?? '') === 'admin';
}

/** Alleen eigen .php-pagina's zijn een veilige 'redirect'-waarde (geen open redirect). */
function isVeiligePaginaUrl(?string $url): bool
{
    return $url !== null && $url !== ''
        && preg_match('#^/?[a-z0-9_]+\.php(\?.*)?$#i', $url);
}

/** Redirect naar de login, met de bedoelde pagina als 'redirect'-parameter. */
function loginRedirectUrl(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (isVeiligePaginaUrl($uri)) {
        return 'login.php?redirect=' . urlencode($uri);
    }
    return 'login.php';
}

/** Beveiligde pagina: zonder login terug naar het inlogscherm. */
function vereisLogin(): void
{
    if (ingelogdeGebruiker() === null) {
        header('Location: ' . loginRedirectUrl());
        exit;
    }
}

/** Alleen beheerders: anders 403. */
function vereisAdmin(): void
{
    vereisLogin();
    if (!isAdmin()) {
        http_response_code(403);
        die('<!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8"><title>Geen toegang</title></head>'
            . '<body style="margin:0;font-family:system-ui,sans-serif;background:#f6f1e6;color:#2b2115">'
            . '<div style="max-width:560px;margin:0 auto;padding:24px">'
            . '<h1 style="color:#4a3322">Geen toegang</h1><p>Alleen beheerders kunnen dit deel gebruiken.</p>'
            . '<p><a href="index.php" style="color:#6e4526">&larr; Terug naar de voorraad</a></p></div></body></html>');
    }
}

function meldAan(array $gebruiker): void
{
    session_regenerate_id(true);
    $_SESSION['gebruiker'] = [
        'id' => (int)$gebruiker['id'],
        'naam' => $gebruiker['naam'],
        'email' => $gebruiker['email'],
        'rol' => $gebruiker['rol'],
    ];
}

function meldAf(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Rolnaam voor weergave. */
function rolLabel(string $rol): string
{
    return $rol === 'admin' ? 'Beheerder' : 'Medewerker';
}

/* --- Twee-staps-inloggen (2FA) --- */

/** Zet de tussenstap klaar: wachtwoord klopt, code van de telefoon nog nodig. */
function setTweeStapWacht(array $gebruiker, string $doel = 'index.php'): void
{
    $_SESSION['2fa_wacht'] = [
        'id' => (int)$gebruiker['id'],
        'naam' => $gebruiker['naam'],
        'email' => $gebruiker['email'],
        'rol' => $gebruiker['rol'],
        'doel' => isVeiligePaginaUrl($doel) ? $doel : 'index.php',
        'verval' => time() + 300, // 5 minuten om de code in te voeren
    ];
}

/** De wachtende 2FA-stap, of null. */
function tweeStapWacht(): ?array
{
    $wacht = $_SESSION['2fa_wacht'] ?? null;
    if ($wacht === null) {
        return null;
    }
    if ((int)($wacht['verval'] ?? 0) < time()) {
        unset($_SESSION['2fa_wacht']);
        return null;
    }
    return $wacht;
}

/** Maakt de wachtende 2FA-stap leeg. */
function wisTweeStapWacht(): void
{
    unset($_SESSION['2fa_wacht']);
}

/** Is dit account een actieve beheerder? */
function isActieveAdmin(PDO $pdo, int $id): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM gebruikers WHERE id = ? AND rol = 'admin' AND actief = 1");
    $stmt->execute([$id]);
    return (bool)$stmt->fetchColumn();
}

/** Is dit de laatste actieve beheerder (mag niet worden opgeheven/verwijderd)? */
function isLaatsteActieveAdmin(PDO $pdo, int $behalveId = 0): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM gebruikers WHERE rol = 'admin' AND actief = 1 AND id <> ?");
    $stmt->execute([$behalveId]);
    return (int)$stmt->fetchColumn() === 0;
}
