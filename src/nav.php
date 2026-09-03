<?php
// Header met woordmerk en navigatie voor ingelogde pagina's.
// (vereist dat auth.php al is ingeladen.)
$navGebruiker = ingelogdeGebruiker();
if ($navGebruiker === null) {
    return;
}
$navPagina = basename($_SERVER['PHP_SELF'] ?? 'index.php');

/** SVG-pictogram voor de onderste navigatiebalk. */
function navIcoon(string $naam): string
{
    $svg = [
        'voorraad' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18"/><path d="M12 9v11"/>',
        'galerij' => '<rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="8" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/><rect x="13" y="13" width="8" height="8" rx="1.5"/>',
        'scan' => '<path d="M4 8V6a2 2 0 0 1 2-2h2"/><path d="M16 4h2a2 2 0 0 1 2 2v2"/><path d="M20 16v2a2 2 0 0 1-2 2h-2"/><path d="M8 20H6a2 2 0 0 1-2-2v-2"/><rect x="7" y="9" width="10" height="6" rx="1.5"/><circle cx="12" cy="12" r="1.6"/>',
        'beheer' => '<circle cx="9" cy="8" r="3"/><path d="M3.5 19c.7-3.2 2.8-5 5.5-5s4.8 1.8 5.5 5"/><path d="M15.5 5.5a3 3 0 0 1 0 5.4"/><path d="M17.5 14.4c2 .7 3 2.2 3.3 4.6"/>',
        'labels' => '<rect x="3.5" y="3.5" width="7" height="7" rx="1"/><rect x="13.5" y="3.5" width="7" height="7" rx="1"/><rect x="3.5" y="13.5" width="7" height="7" rx="1"/><path d="M13.5 13.5h3v3h-3z"/><path d="M18 13.5h2.5v2.5H18z"/><path d="M13.5 18h3v2.5h-3z"/><path d="M18 18h2.5v2.5H18z"/>',
        'account' => '<circle cx="12" cy="8" r="3.5"/><path d="M4.5 20c1-4 4-6 7.5-6s6.5 2 7.5 6"/>',
    ][$naam] ?? '';
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $svg . '</svg>';
}
?>
<header class="sitekop">
    <div class="sitekop-boven">
        <a class="merk" href="index.php">Circuleather<span class="tagline">leeropslag <span class="hart">&#10084;</span></span></a>
        <div class="gebruiker-zone">
            <a class="gebruiker" href="profile.php" title="Mijn account"><?= htmlspecialchars($navGebruiker['naam']) ?></a>
            <a class="uitloggen" href="logout.php" title="Uitloggen">Uitloggen</a>
        </div>
    </div>
    <nav class="topnav">
        <a href="index.php" class="<?= in_array($navPagina, ['index.php', 'add.php', 'edit.php', 'delete.php'], true) ? 'actief' : '' ?>">Voorraad</a>
        <a href="galerij.php" class="<?= $navPagina === 'galerij.php' ? 'actief' : '' ?>">Galerij</a>
        <a href="scan.php" class="<?= $navPagina === 'scan.php' ? 'actief' : '' ?>">Scan</a>
        <?php if (isAdmin()): ?>
            <a href="users.php" class="<?= in_array($navPagina, ['users.php', 'user_edit.php'], true) ? 'actief' : '' ?>">Medewerkers</a>
            <a href="logboek.php" class="<?= $navPagina === 'logboek.php' ? 'actief' : '' ?>">Logboek</a>
            <a href="labels.php" class="<?= $navPagina === 'labels.php' ? 'actief' : '' ?>">QR-labels</a>
        <?php endif; ?>
    </nav>
</header>

<nav class="bodem-nav" aria-label="Hoofdnavigatie">
    <a href="index.php" class="<?= in_array($navPagina, ['index.php', 'add.php', 'edit.php', 'delete.php'], true) ? 'actief' : '' ?>">
        <?= navIcoon('voorraad') ?><span>Voorraad</span>
    </a>
    <a href="galerij.php" class="<?= $navPagina === 'galerij.php' ? 'actief' : '' ?>">
        <?= navIcoon('galerij') ?><span>Galerij</span>
    </a>
    <a href="scan.php" class="<?= $navPagina === 'scan.php' ? 'actief' : '' ?>">
        <?= navIcoon('scan') ?><span>Scan</span>
    </a>
    <?php if (isAdmin()): ?>
        <a href="users.php" class="<?= in_array($navPagina, ['users.php', 'user_edit.php'], true) ? 'actief' : '' ?>">
            <?= navIcoon('beheer') ?><span>Beheer</span>
        </a>
        <a href="labels.php" class="<?= $navPagina === 'labels.php' ? 'actief' : '' ?>">
            <?= navIcoon('labels') ?><span>Labels</span>
        </a>
    <?php else: ?>
        <a href="profile.php" class="<?= $navPagina === 'profile.php' ? 'actief' : '' ?>">
            <?= navIcoon('account') ?><span>Account</span>
        </a>
    <?php endif; ?>
</nav>