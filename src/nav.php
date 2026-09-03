<?php
// Header met woordmerk en navigatie voor ingelogde pagina's
// (vereist dat auth.php al is ingeladen).
$navGebruiker = ingelogdeGebruiker();
if ($navGebruiker === null) {
    return;
}
?>
<header class="sitekop">
    <a class="merk" href="index.php">Circuleather<span class="tagline">leeropslag</span></a>
    <nav class="topnav">
        <a href="index.php">Voorraad</a>
        <a href="galerij.php">Galerij</a>
        <a href="scan.php">📷 Scan</a>
        <?php if (isAdmin()): ?>
            <a href="users.php">Medewerkers</a>
            <a href="labels.php">QR-labels</a>
        <?php endif; ?>
        <a class="gebruiker" href="profile.php" title="Mijn account"><?= htmlspecialchars($navGebruiker['naam']) ?> ⚙</a>
        <a class="uitloggen" href="logout.php">Uitloggen</a>
    </nav>
</header>
