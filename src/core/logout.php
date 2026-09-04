<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../components/functies.php';
logActie($pdo, 'uitloggen', 'Uitgelogd');
meldAf();
header('Location: login.php?msg=uitgelogd');
exit;