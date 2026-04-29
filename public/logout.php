<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';

auth_logout();
header('Location: /WhiteGlove/public/login.php');
exit;

