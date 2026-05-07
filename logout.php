<?php
require_once __DIR__ . '/auth/session.php';
logoutDoctor();
header('Location: login.php?msg=logout');
exit;
