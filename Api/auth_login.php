<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

require_method('POST');

$input = json_input();
$email = strtolower(trim((string) ($input['email'] ?? '')));
$password = (string) ($input['password'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    respond([
        'ok' => false,
        'error' => 'Email and password are required',
    ], 422);
}

try {
    $database = db();
    $statement = $database->prepare(
        'SELECT id, full_name, email, phone, password_hash, role FROM users WHERE email = :email LIMIT 1'
    );
    $statement->bindValue(':email', $email, SQLITE3_TEXT);
    $result = $statement->execute();
    $user = $result !== false ? $result->fetchArray(SQLITE3_ASSOC) : false;

    if (!is_array($user) || !password_verify($password, (string) $user['password_hash'])) {
        respond([
            'ok' => false,
            'error' => 'Invalid email or password',
        ], 401);
    }

    $sessionUser = [
        'id' => (int) $user['id'],
        'full_name' => (string) $user['full_name'],
        'email' => (string) $user['email'],
        'phone' => (string) ($user['phone'] ?? ''),
        'role' => (string) $user['role'],
    ];

    session_regenerate_id(true);
    $_SESSION['user'] = $sessionUser;

    respond([
        'ok' => true,
        'user' => $sessionUser,
    ]);
} catch (Throwable $exception) {
    respond([
        'ok' => false,
        'error' => 'Login failed',
    ], 500);
}

