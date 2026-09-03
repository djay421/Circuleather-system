<?php
require 'auth.php';
require 'functies.php';
logActie($pdo, 'uitloggen', 'Uitgelogd');
meldAf();
header('Location: login.php?msg=uitgelogd');
exit;