<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

require_method('GET');

$user = require_login();

if (($user['role'] ?? '') !== 'admin') {
    respond([
        'ok' => false,
        'error' => 'Forbidden',
    ], 403);
}

try {
    $database = db();
    $statement = $database->prepare(
        "SELECT id, full_name, email, phone
         FROM users
         WHERE role = 'collector'
         ORDER BY full_name ASC"
    );
    $result = $statement->execute();
    $collectors = [];

    while ($result !== false && ($row = $result->fetchArray(SQLITE3_ASSOC)) !== false) {
        $collectors[] = [
            'id' => (int) $row['id'],
            'full_name' => (string) $row['full_name'],
            'email' => (string) $row['email'],
            'phone' => (string) ($row['phone'] ?? ''),
        ];
    }

    respond([
        'ok' => true,
        'collectors' => $collectors,
    ]);
} catch (Throwable $exception) {
    respond([
        'ok' => false,
        'error' => 'Failed to load collectors',
    ], 500);
}
