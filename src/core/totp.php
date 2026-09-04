<?php
// TOTP (RFC 6238) voor Google Authenticator + eenmalige herstelcodes.
// Geen externe bibliotheek nodig: alleen PHP-functies.

/** Decodeert een base32-string naar bytes. */
function totpBase32Decode(string $base32): string
{
    $alfabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split(strtoupper(rtrim($base32, '='))) as $teken) {
        $waarde = strpos($alfabet, $teken);
        if ($waarde === false) {
            return '';
        }
        $bits .= str_pad(decbin($waarde), 5, '0', STR_PAD_LEFT);
    }
    $bytes = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $bytes .= chr(bindec($byte));
        }
    }
    return $bytes;
}

/** Encodeert bytes naar base32 (zonder padding). */
function totpBase32Encode(string $data): string
{
    $alfabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($data) as $byte) {
        $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
    }
    $uit = '';
    foreach (str_split($bits, 5) as $vijf) {
        $uit .= $alfabet[bindec(str_pad($vijf, 5, '0', STR_PAD_RIGHT))];
    }
    return $uit;
}

/** Een nieuw willekeurig TOTP-geheim (160 bit, base32, leesbaar). */
function genereerTotpGeheim(): string
{
    return totpBase32Encode(random_bytes(20));
}

/** Berekent de 6-cijferige TOTP-code voor een geheim op een tijdstip. */
function totpCode(string $geheim, ?int $tijd = null): string
{
    $tijd = $tijd ?? time();
    $sleutel = totpBase32Decode($geheim);
    $counter = (int)floor($tijd / 30);
    $boodschap = pack('J', $counter); // 8 bytes big-endian
    $hash = hash_hmac('sha1', $boodschap, $sleutel, true);
    $offset = ord($hash[strlen($hash) - 1]) & 0x0f;
    $deel = ((ord($hash[$offset]) & 0x7f) << 24)
        | ((ord($hash[$offset + 1]) & 0xff) << 16)
        | ((ord($hash[$offset + 2]) & 0xff) << 8)
        | (ord($hash[$offset + 3]) & 0xff);
    return str_pad((string)($deel % 1000000), 6, '0', STR_PAD_LEFT);
}

/** Controleert een ingevulde code tegen het geheim (met ±1 tijdsinterval). */
function valideerTotp(string $geheim, string $code): bool
{
    if ($code === '' || $geheim === '') {
        return false;
    }
    $code = preg_replace('/\s+/', '', $code);
    if (!preg_match('/^\d{6}$/', $code)) {
        return false;
    }
    $nu = time();
    for ($afwijking = -1; $afwijking <= 1; $afwijking++) {
        if (hash_equals(totpCode($geheim, $nu + $afwijking * 30), $code)) {
            return true;
        }
    }
    return false;
}

/** otpauth-URI voor de QR-code (Google Authenticator e.d.). */
function otpauthUri(string $geheim, string $account): string
{
    $account = preg_replace('/[^a-z0-9@._+-]/i', '', $account);
    return 'otpauth://totp/' . rawurlencode('Circuleather:' . $account)
        . '?secret=' . rawurlencode($geheim)
        . '&issuer=' . rawurlencode('Circuleather')
        . '&algorithm=SHA1&digits=6&period=30';
}

/** Genereert een set eenmalige herstelcodes (XXXX-XXXX). */
function genereerHerstelcodes(int $aantal = 10): array
{
    $codes = [];
    for ($i = 0; $i < $aantal; $i++) {
        $deel1 = strtoupper(bin2hex(random_bytes(2)));
        $deel2 = strtoupper(bin2hex(random_bytes(2)));
        $codes[] = $deel1 . '-' . $deel2;
    }
    return $codes;
}

/** Bewaart de herstelcodes (gehasht) voor een gebruiker. */
function bewaarHerstelcodes(PDO $pdo, int $gebruikerId, array $codes): void
{
    $stmt = $pdo->prepare('INSERT INTO recovery_codes (gebruiker_id, code_hash) VALUES (?, ?)');
    foreach ($codes as $code) {
        $stmt->execute([$gebruikerId, hash('sha256', $code)]);
    }
}

/**
 * Controleert en verbruikt een herstelcode. Retourneert true als de code
 * klopte en nog niet eerder gebruikt is.
 */
function gebruikHerstelcode(PDO $pdo, int $gebruikerId, string $code): bool
{
    $code = strtoupper(trim($code));
    if (!preg_match('/^[0-9A-F]{4}-[0-9A-F]{4}$/', $code)) {
        return false;
    }
    $stmt = $pdo->prepare(
        'SELECT id FROM recovery_codes WHERE gebruiker_id = ? AND code_hash = ? AND gebruikt = 0 LIMIT 1'
    );
    $stmt->execute([$gebruikerId, hash('sha256', $code)]);
    $id = $stmt->fetchColumn();
    if (!$id) {
        return false;
    }
    $upd = $pdo->prepare('UPDATE recovery_codes SET gebruikt = 1 WHERE id = ?');
    $upd->execute([(int)$id]);
    return true;
}

/** Verwijdert de herstelcodes van een gebruiker (bij uitzetten 2FA). */
function wisHerstelcodes(PDO $pdo, int $gebruikerId): void
{
    $stmt = $pdo->prepare('DELETE FROM recovery_codes WHERE gebruiker_id = ?');
    $stmt->execute([$gebruikerId]);
}
