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
        'SELECT id, full_name, email, phone, role, created_at
         FROM users
         ORDER BY id DESC'
    );
    $result = $statement->execute();
    $users = [];

    while ($result !== false && ($row = $result->fetchArray(SQLITE3_ASSOC)) !== false) {
        $users[] = [
            'id' => (int) $row['id'],
            'full_name' => (string) $row['full_name'],
            'email' => (string) $row['email'],
            'phone' => $row['phone'] !== null ? (string) $row['phone'] : '',
            'role' => (string) $row['role'],
            'created_at' => (string) $row['created_at'],
        ];
    }

    respond([
        'ok' => true,
        'users' => $users,
    ]);
} catch (Throwable $exception) {
    respond([
        'ok' => false,
        'error' => 'Failed to load users',
    ], 500);
}
