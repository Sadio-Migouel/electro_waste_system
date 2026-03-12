<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

require_method('POST');

if (!DEV_MODE) {
    respond([
        'ok' => false,
        'error' => 'Not available',
    ], 403);
}

$input = json_input();
$email = strtolower(trim((string) ($input['email'] ?? '')));
$role = trim((string) ($input['role'] ?? ''));
$allowedRoles = ['user', 'collector', 'admin'];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond([
        'ok' => false,
        'error' => 'A valid email is required',
    ], 422);
}

if (!in_array($role, $allowedRoles, true)) {
    respond([
        'ok' => false,
        'error' => 'Invalid role',
    ], 422);
}

try {
    $database = db();
    $statement = $database->prepare('UPDATE users SET role = :role WHERE email = :email');
    $statement->bindValue(':role', $role, SQLITE3_TEXT);
    $statement->bindValue(':email', $email, SQLITE3_TEXT);
    $result = $statement->execute();

    if ($result === false || $database->changes() < 1) {
        respond([
            'ok' => false,
            'error' => 'User not found',
        ], 404);
    }

    respond([
        'ok' => true,
        'message' => 'Role updated',
        'email' => $email,
        'role' => $role,
    ]);
} catch (Throwable $exception) {
    respond([
        'ok' => false,
        'error' => 'Role update failed',
    ], 500);
}

