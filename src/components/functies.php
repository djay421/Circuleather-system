<?php
// Gedeelde helpers voor het dynamische criteria-systeem (Circuleather).
// Criteria en hun keuzes worden uit de database gelezen, zodat ze in de
// backend kunnen worden uitgebreid of aangepast zonder code aan te passen.

require_once __DIR__ . '/../config/db.php';

const CATEGORIEEN = ['bigbag', 'leersample'];
const STATUSSEX = ['beschikbaar', 'gereserveerd', 'in_bewerking', 'verkocht'];

function appIcoon(string $naam): string
{
    $paden = [
        'box' => '<path d="M4 7.5 12 3l8 4.5v9L12 21l-8-4.5z"/><path d="m4 7.5 8 4.5 8-4.5M12 12v9"/>',
        'thread' => '<path d="M6 4v16M6 6h8a3 3 0 0 1 0 6H6m0 0h8a3 3 0 0 1 0 6H6"/>',
        'tag' => '<path d="M4 5.5A1.5 1.5 0 0 1 5.5 4H13l7 7-9 9-7-7z"/><circle cx="8" cy="8" r="1"/>',
        'chart' => '<path d="M4 19V5M4 19h16M8 16v-5M12 16V7M16 16v-3"/>',
        'camera' => '<path d="M4 8h3l1.5-2h7L17 8h3v10H4z"/><circle cx="12" cy="13" r="3"/>',
        'clipboard' => '<path d="M8 5h8v15H5V5h3M9 3h6v4H9z"/><path d="m8 13 2 2 5-5"/>',
        'print' => '<path d="M7 9V4h10v5M7 17H4V9h16v8h-3M7 14h10v6H7z"/>',
        'eye' => '<path d="M2.5 12s3.5-5 9.5-5 9.5 5 9.5 5-3.5 5-9.5 5-9.5-5-9.5-5z"/><circle cx="12" cy="12" r="2.5"/>',
        'eye-off' => '<path d="m3 3 18 18M10.6 6.2A10.7 10.7 0 0 1 12 6c6 0 9.5 6 9.5 6a16 16 0 0 1-3.1 3.5M6.2 6.8C3.8 8.4 2.5 12 2.5 12s3.5 6 9.5 6c1 0 2-.2 2.8-.5"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
        'hart' => '<path fill="currentColor" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>',
        'download' => '<path d="M12 3v9M12 12l2.5 4M12 12l-2.5 4M6 16h12v4H6z"/>',
        'check' => '<path d="M20 6L9 17 4 12"/>',
        'gebruiker' => '<circle cx="12" cy="8" r="4"/><path d="M4.5 20c1-4 4-6 7.5-6s6.5 2 7.5 6"/>',
        'prullenbak' => '<path d="M4 5h16M6 8h12v10H6z"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
    ];
    return '<svg class="app-icoon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paden[$naam] ?? '') . '</svg>';
}

/** Alle actieve criteria met hun keuzemogelijkheden, gesorteerd. */
function haalCriteria(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT id, label, toepassing, soort, eenheid, meerdere_waarden
         FROM criteria WHERE actief = 1
         ORDER BY toepassing, volgorde, id'
    );
    $criteria = [];
    foreach ($stmt->fetchAll() as $c) {
        $criteria[(int)$c['id']] = [
            'id'         => (int)$c['id'],
            'label'      => $c['label'],
            'toepassing' => $c['toepassing'],
            'soort'      => $c['soort'],
            'eenheid'    => $c['eenheid'],
            'meerdere'   => (bool)$c['meerdere_waarden'],
            'opties'     => [],
        ];
    }
    if ($criteria) {
        $in = implode(',', array_keys($criteria));
        $stmt = $pdo->query(
            "SELECT id, criterium_id, waarde FROM criteria_opties
             WHERE actief = 1 AND criterium_id IN ($in)
             ORDER BY volgorde, id"
        );
        foreach ($stmt->fetchAll() as $o) {
            $criteria[(int)$o['criterium_id']]['opties'][] = [
                'id' => (int)$o['id'],
                'waarde' => $o['waarde'],
            ];
        }
    }
    return $criteria;
}

/** Alleen de criteria die gelden voor de opgegeven categorie. */
function criteriaVoor(array $criteria, string $toepassing): array
{
    return array_filter($criteria, fn ($c) => $c['toepassing'] === $toepassing);
}

/**
 * Opgeslagen criteria-waarden van één item.
 * Retourneert: criterium_id => ['opties' => [optie_id, ...], 'vrij' => 'waarde'].
 */
function haalWaardenVoorItem(PDO $pdo, int $itemId): array
{
    $stmt = $pdo->prepare(
        'SELECT criterium_id, optie_id, waarde_vrij FROM voorraad_criteria WHERE voorraad_id = ?'
    );
    $stmt->execute([$itemId]);
    $uitkomst = [];
    foreach ($stmt->fetchAll() as $r) {
        $id = (int)$r['criterium_id'];
        if (!isset($uitkomst[$id])) {
            $uitkomst[$id] = ['opties' => [], 'vrij' => ''];
        }
        if ($r['optie_id'] !== null) {
            $uitkomst[$id]['opties'][] = (int)$r['optie_id'];
        }
        if ($r['waarde_vrij'] !== null && $r['waarde_vrij'] !== '') {
            $uitkomst[$id]['vrij'] = $r['waarde_vrij'];
        }
    }
    return $uitkomst;
}

/** Veldwaarden uit POST, geordend naar criterium: id => ['opties'=>[], 'vrij'=>string]. */
function leesPostWaarden(array $criteria, string $toepassing, array $post): array
{
    $waarden = [];
    foreach (criteriaVoor($criteria, $toepassing) as $c) {
        $cid = $c['id'];
        $opties = [];
        if ($c['soort'] === 'keuze') {
            $geldig = array_map(fn ($o) => $o['id'], $c['opties']);
            if ($c['meerdere']) {
                foreach (($post["crit_{$cid}_opties"] ?? []) as $o) {
                    $o = (int)$o;
                    if (in_array($o, $geldig, true)) {
                        $opties[] = $o;
                    }
                }
            } else {
                $o = (int)($post["crit_{$cid}_optie"] ?? 0);
                if (in_array($o, $geldig, true)) {
                    $opties[] = $o;
                }
            }
        }
        $vrij = trim((string)($post["crit_{$cid}_vrij"] ?? ''));
        if ($c['soort'] === 'getal') {
            $vrij = str_replace(',', '.', $vrij); // Nederlandse decimale komma
        }
        $waarden[$cid] = ['opties' => array_values(array_unique($opties)), 'vrij' => $vrij];
    }
    return $waarden;
}

/**
 * Overschrijft alle criteria-waarden van een item met de meegegeven waarden
 * (oudere waarden worden eerst verwijderd).
 */
function bewaarCriteriaWaarden(PDO $pdo, int $itemId, array $waarden): void
{
    $del = $pdo->prepare('DELETE FROM voorraad_criteria WHERE voorraad_id = ?');
    $del->execute([$itemId]);

    $ins = $pdo->prepare(
        'INSERT INTO voorraad_criteria (voorraad_id, criterium_id, optie_id, waarde_vrij)
         VALUES (?, ?, ?, ?)'
    );
    foreach ($waarden as $criteriumId => $w) {
        foreach ($w['opties'] as $optieId) {
            $ins->execute([$itemId, $criteriumId, $optieId, null]);
        }
        if ($w['vrij'] !== '') {
            $ins->execute([$itemId, $criteriumId, null, mb_substr($w['vrij'], 0, 255)]);
        }
    }
}

/** Geeft de foutmeldingen terug voor een ingevulde criteria-set. */
function valideerCriteriaWaarden(array $waarden, array $criteria): array
{
    $errors = [];
    foreach ($waarden as $criteriumId => $w) {
        if (!isset($criteria[$criteriumId])) {
            continue;
        }
        $c = $criteria[$criteriumId];
        if ($c['soort'] === 'getal' && $w['vrij'] !== '' && !is_numeric($w['vrij'])) {
            $errors[] = 'Veld "' . $c['label'] . '" moet een getal zijn.';
        }
    }
    return $errors;
}

/** Tekent de invoervelden voor één categorie (bigbag of leersample). */
function toonCriteriaVelden(array $criteria, string $toepassing, array $waarden = []): void
{
    foreach (criteriaVoor($criteria, $toepassing) as $c) {
        $cid = $c['id'];
        $w = $waarden[$cid] ?? ['opties' => [], 'vrij' => ''];
        $eenheid = $c['eenheid'] ? ' (' . htmlspecialchars($c['eenheid']) . ')' : '';

        echo '<div class="veld">';
        echo '<label for="crit_' . $cid . '">' . htmlspecialchars($c['label']) . $eenheid;
        if ($c['meerdere']) {
            echo ' <span class="hint">(meerdere mogelijk)</span>';
        }
        echo '</label>';

        if ($c['soort'] === 'keuze') {
            if ($c['meerdere']) {
                foreach ($c['opties'] as $o) {
                    $checked = in_array($o['id'], $w['opties'], true) ? ' checked' : '';
                    echo '<label class="optie"><input type="checkbox" name="crit_' . $cid
                        . '_opties[]" value="' . $o['id'] . '"' . $checked . '> '
                        . htmlspecialchars($o['waarde']) . '</label>';
                }
            } else {
                echo '<select id="crit_' . $cid . '" name="crit_' . $cid . '_optie">';
                echo '<option value="">— kies —</option>';
                foreach ($c['opties'] as $o) {
                    $selected = in_array($o['id'], $w['opties'], true) ? ' selected' : '';
                    echo '<option value="' . $o['id'] . '"' . $selected . '>'
                        . htmlspecialchars($o['waarde']) . '</option>';
                }
                echo '</select>';
            }
        } elseif ($c['soort'] === 'getal') {
            echo '<input type="number" step="any" min="0" id="crit_' . $cid
                . '" name="crit_' . $cid . '_vrij" value="' . htmlspecialchars($w['vrij']) . '">';
        } else {
            echo '<input type="text" id="crit_' . $cid . '" name="crit_' . $cid
                . '_vrij" value="' . htmlspecialchars($w['vrij']) . '">';
        }
        echo '</div>';
    }
}

/** Alle steden als [id => naam]. */
function haalSteden(PDO $pdo): array
{
    $steden = [];
    foreach ($pdo->query('SELECT id, naam FROM steden WHERE actief = 1 ORDER BY naam') as $r) {
        $steden[(int)$r['id']] = $r['naam'];
    }
    return $steden;
}

/**
 * Criteria-kenmerken van één of meerdere items, klaar voor weergave:
 * [itemId => [label => [waarde-tekst, ...]]].
 */
function haalKenmerken(PDO $pdo, array $itemIds): array
{
    $itemIds = array_values(array_unique(array_filter(array_map('intval', $itemIds))));
    $resultaat = [];
    if (!$itemIds) {
        return $resultaat;
    }

    $in = implode(',', $itemIds);
    $rows = $pdo->query(
        "SELECT vc.voorraad_id, vc.waarde_vrij, c.label, c.eenheid, co.waarde AS optie_waarde
         FROM voorraad_criteria vc
         JOIN criteria c ON c.id = vc.criterium_id
         LEFT JOIN criteria_opties co ON co.id = vc.optie_id
         WHERE vc.voorraad_id IN ($in)
         ORDER BY c.volgorde, vc.id"
    )->fetchAll();

    foreach ($rows as $r) {
        $tekst = $r['optie_waarde'] !== null ? $r['optie_waarde'] : trim((string)$r['waarde_vrij']);
        if ($tekst === '') {
            continue;
        }
        if ($r['eenheid']) {
            $tekst .= ' ' . $r['eenheid'];
        }
        $resultaat[(int)$r['voorraad_id']][$r['label']][] = $tekst;
    }
    return $resultaat;
}

/** Bigbags voor het koppelen van leersamples: [id => label]. */
function haalBigbags(PDO $pdo): array
{
    $bigbags = [];
    $rows = $pdo->query(
        "SELECT v.id, v.code, v.binnenkomst_datum, s.naam AS stad_naam
         FROM voorraad v
         LEFT JOIN steden s ON s.id = v.stad_id
         WHERE v.categorie = 'bigbag'
         ORDER BY v.aangemaakt_op DESC, v.id DESC"
    )->fetchAll();
    foreach ($rows as $r) {
        $delen = array_filter([
            $r['code'],
            $r['stad_naam'],
            $r['binnenkomst_datum'],
        ], fn ($d) => $d !== null && $d !== '');
        $bigbags[(int)$r['id']] = implode(' — ', $delen);
    }
    return $bigbags;
}

/** Weergavenaam van een voorraad-item (code, of automatisch nummer). */
function itemLabel(array $item): string
{
    if ($item['code'] !== null && $item['code'] !== '') {
        return $item['code'];
    }
    return ($item['categorie'] === 'bigbag' ? 'Bigbag' : 'Sample') . ' #' . $item['id'];
}

/** Kleur-hex voor een kleurcategorie-naam (kleurbolletje + staalvlak in de galerij). */
function leerStaal(string $naam): string
{
    $kaart = [
        'zwart' => '#26231f', 'wit' => '#f4f1e8', 'grijs' => '#8f8b84',
        'bruin' => '#7c5232', 'beige' => '#d8c5a2', 'crème' => '#ece0c6',
        'blauw' => '#41566e', 'groen' => '#59633e', 'rood' => '#8f3f2c',
        'bordeaux' => '#5f2430', 'geel' => '#c9a23e', 'mosterd' => '#b08a2c',
        'oranje' => '#b86a2f', 'roze' => '#c08a7d', 'paars' => '#5c4a6b',
        'antraciet' => '#4a4a4c', 'naturel' => '#cbb088', 'goud' => '#b98d2e',
    ];
    foreach ($kaart as $sleutel => $hex) {
        if (mb_stripos($naam, $sleutel) !== false) {
            return $hex;
        }
    }
    return '#b3a38c';
}

/**
 * Slaat een geüploade afbeelding op in src/uploads/.
 * Retourneert [relatief pad of null, fouttekst of null].
 * Geen bestand meegegeven is geen fout: [null, null].
 */
function verwerkFotoUpload(array $bestand): array
{
    if (!isset($bestand['error'])) {
        return [null, null];
    }
    if ((int)$bestand['error'] === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if ((int)$bestand['error'] !== UPLOAD_ERR_OK) {
        return [null, 'Foto-upload mislukt (foutcode ' . (int)$bestand['error'] . ').'];
    }
    if ((int)($bestand['size'] ?? 0) > 10 * 1024 * 1024) {
        return [null, 'Foto is groter dan 10 MB — kies een kleinere afbeelding.'];
    }

    $info = @getimagesize($bestand['tmp_name']);
    if ($info === false) {
        return [null, 'Het gekozen bestand is geen geldige afbeelding.'];
    }
    $toegestaan = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_GIF => 'gif',
    ];
    if (defined('IMAGETYPE_WEBP')) {
        $toegestaan[IMAGETYPE_WEBP] = 'webp';
    }
    $ext = $toegestaan[$info[2]] ?? null;
    if ($ext === null) {
        return [null, 'Alleen JPG, PNG, WebP of GIF-afbeeldingen zijn toegestaan.'];
    }

    $map = __DIR__ . '/uploads';
    if (!is_dir($map) && !@mkdir($map, 0775, true) && !is_dir($map)) {
        return [null, 'De uploadmap bestaat niet en kon niet worden aangemaakt.'];
    }
    $naam = 'f_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!@move_uploaded_file($bestand['tmp_name'], $map . '/' . $naam)) {
        return [null, 'De foto kon niet worden opgeslagen.'];
    }
    return ['uploads/' . $naam, null];
}

/** Verwijdert een eerder opgeslagen fotobestand (alleen binnen de uploads-map). */
function verwijderFotoBestand(?string $pad): void
{
    if ($pad === null || $pad === '') {
        return;
    }
    $map = realpath(__DIR__ . '/uploads');
    $doel = realpath(__DIR__ . '/' . $pad);
    if ($map !== false && $doel !== false
        && strncmp($doel, $map . DIRECTORY_SEPARATOR, strlen($map) + 1) === 0
        && is_file($doel)) {
        @unlink($doel);
    }
}

/* --- Logboek (audit-log) --- */

/** Het IP-adres van de huidige aanvraag. */
function ipAdres(): string
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? '');
}

/** Een korte, leesbare omschrijving van het huidige apparaat (bijv. "iPhone · Safari"). */
function apparaatLabel(): string
{
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $apparaat = 'Onbekend apparaat';
    if (stripos($ua, 'iPhone') !== false) {
        $apparaat = 'iPhone';
    } elseif (stripos($ua, 'iPad') !== false) {
        $apparaat = 'iPad';
    } elseif (stripos($ua, 'Android') !== false) {
        $apparaat = stripos($ua, 'Mobile') !== false ? 'Android-telefoon' : 'Android-tablet';
    } elseif (stripos($ua, 'Windows') !== false) {
        $apparaat = 'Windows';
    } elseif (stripos($ua, 'Macintosh') !== false) {
        $apparaat = 'Mac';
    } elseif (stripos($ua, 'Linux') !== false) {
        $apparaat = 'Linux';
    }
    $browser = 'Browser';
    if (stripos($ua, 'Edg/') !== false) {
        $browser = 'Edge';
    } elseif (stripos($ua, 'Chrome') !== false) {
        $browser = 'Chrome';
    } elseif (stripos($ua, 'Firefox') !== false) {
        $browser = 'Firefox';
    } elseif (stripos($ua, 'Safari') !== false) {
        $browser = 'Safari';
    }
    return $apparaat . ' · ' . $browser;
}

/**
 * Schrijft een regel naar het logboek (wie, wat, wanneer, vanaf welk apparaat).
 * Werkt ook zonder ingelogde gebruiker (bijv. mislukte inlogpogingen).
 */
function logActie(PDO $pdo, string $actie, string $beschrijving = ''): void
{
    $gebruikerId = null;
    if (function_exists('ingelogdeGebruiker') && ingelogdeGebruiker() !== null) {
        $gebruikerId = (int)ingelogdeGebruiker()['id'];
    }
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO logboek (gebruiker_id, actie, beschrijving, ip, apparaat)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $gebruikerId,
            mb_substr($actie, 0, 60),
            mb_substr($beschrijving, 0, 500),
            ipAdres(),
            mb_substr(apparaatLabel(), 0, 120),
        ]);
    } catch (Throwable $e) {
        // Logboek mag nooit een actie laten mislukken.
    }
}

/* --- Onthouden apparaten (2FA 30 dagen overslaan op dit apparaat) --- */

const ONDHOUD_COOKIE = 'cl_onthoud';

/** Maakt een apparaat-token aan en zet de onthoud-cookie (30 dagen). */
function onthoudApparaat(PDO $pdo, int $gebruikerId): void
{
    $token = bin2hex(random_bytes(32));
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie(ONDHOUD_COOKIE, $gebruikerId . '.' . $token, [
        'expires' => time() + 60 * 60 * 24 * 30,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $secure,
    ]);
    $stmt = $pdo->prepare(
        'INSERT INTO apparaten (gebruiker_id, token_hash, label, ip, laatst_gebruikt)
         VALUES (?, ?, ?, ?, NOW())'
    );
    $stmt->execute([$gebruikerId, hash('sha256', $token), apparaatLabel(), ipAdres()]);
}

/**
 * Is er een geldige onthoud-cookie voor een ingelogd-waardige gebruiker?
 * Retourneert de apparaatrij (incl. gebruiker) of null.
 */
function onthoudenApparaatGeldig(PDO $pdo): ?array
{
    $raw = (string)($_COOKIE[ONDHOUD_COOKIE] ?? '');
    if (!preg_match('/^(\d+)\.([a-f0-9]{64})$/', $raw, $m)) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT a.*, g.naam, g.rol, g.actief
         FROM apparaten a
         JOIN gebruikers g ON g.id = a.gebruiker_id
         WHERE a.gebruiker_id = ? AND a.token_hash = ?'
    );
    $stmt->execute([(int)$m[1], hash('sha256', $m[2])]);
    $rij = $stmt->fetch();
    if (!$rij || !(bool)$rij['actief']) {
        return null;
    }
    $upd = $pdo->prepare('UPDATE apparaten SET laatst_gebruikt = NOW(), ip = ? WHERE id = ?');
    $upd->execute([ipAdres(), (int)$rij['id']]);
    return $rij;
}

/** Verwijdert één onthouden apparaat van de ingelogde gebruiker. */
function wisApparaat(PDO $pdo, int $id, int $gebruikerId): bool
{
    $stmt = $pdo->prepare('DELETE FROM apparaten WHERE id = ? AND gebruiker_id = ?');
    $stmt->execute([$id, $gebruikerId]);
    return $stmt->rowCount() > 0;
}

/** Verwijdert alle onthouden apparaten van een gebruiker (bijv. bij 2FA-reset). */
function wisApparatenVanGebruiker(PDO $pdo, int $gebruikerId): void
{
    $stmt = $pdo->prepare('DELETE FROM apparaten WHERE gebruiker_id = ?');
    $stmt->execute([$gebruikerId]);
}
