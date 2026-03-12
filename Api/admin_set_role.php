<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

require_method('POST');

$currentUser = require_login();

if (($currentUser['role'] ?? '') !== 'admin') {
    respond([
        'ok' => false,
        'error' => 'Forbidden',
    ], 403);
}

$input = json_input();
$userId = filter_var($input['user_id'] ?? null, FILTER_VALIDATE_INT);
$role = trim((string) ($input['role'] ?? ''));
$allowedRoles = ['user', 'collector', 'admin'];

if ($userId === false || $userId <= 0) {
    respond([
        'ok' => false,
        'error' => 'A valid user_id is required',
    ], 422);
}

if (!in_array($role, $allowedRoles, true)) {
    respond([
        'ok' => false,
        'error' => 'Invalid role',
    ], 422);
}

if ($userId === (int) $currentUser['id'] && $role !== 'admin') {
    respond([
        'ok' => false,
        'error' => 'You cannot remove your own admin role',
    ], 400);
}

try {
    $database = db();
    $statement = $database->prepare('UPDATE users SET role = :role WHERE id = :id');
    $statement->bindValue(':role', $role, SQLITE3_TEXT);
    $statement->bindValue(':id', $userId, SQLITE3_INTEGER);
    $result = $statement->execute();

    if ($result === false) {
        throw new RuntimeException('Failed to update role');
    }

    if ($database->changes() < 1) {
        $checkStatement = $database->prepare('SELECT id, role FROM users WHERE id = :id LIMIT 1');
        $checkStatement->bindValue(':id', $userId, SQLITE3_INTEGER);
        $checkResult = $checkStatement->execute();
        $existingUser = $checkResult !== false ? $checkResult->fetchArray(SQLITE3_ASSOC) : false;

        if (!is_array($existingUser)) {
            respond([
                'ok' => false,
                'error' => 'User not found',
            ], 404);
        }
    }

    respond([
        'ok' => true,
        'message' => 'Role updated',
    ]);
} catch (Throwable $exception) {
    respond([
        'ok' => false,
        'error' => 'Failed to update role',
    ], 500);
}
