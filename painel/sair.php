<?php
declare(strict_types=1);

require __DIR__ . '/../app/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    logout();
}
header('Location: login.php');
exit;
