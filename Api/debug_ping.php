<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

$user = $_SESSION['user'] ?? null;

respond([
    'ok' => true,
    'api_folder' => __DIR__,
    'session_user' => is_array($user) ? $user : null,
]);
