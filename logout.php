<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
logout_user();
header('Location: ' . url('/login.php'));
exit;
