<?php
require 'auth.php';
meldAf();
header('Location: login.php?msg=uitgelogd');
exit;
