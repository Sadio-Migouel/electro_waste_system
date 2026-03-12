<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

require_method('POST');

$input = json_input();
$fullName = trim((string) ($input['full_name'] ?? ''));
$email = strtolower(trim((string) ($input['email'] ?? '')));
$phone = trim((string) ($input['phone'] ?? ''));
$password = (string) ($input['password'] ?? '');

if ($fullName === '') {
    respond([
        'ok' => false,
        'error' => 'Full name is required',
    ], 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond([
        'ok' => false,
        'error' => 'A valid email is required',
    ], 422);
}

if (strlen($password) < 6) {
    respond([
        'ok' => false,
        'error' => 'Password must be at least 6 characters',
    ], 422);
}

try {
    $database = db();

    $checkStatement = $database->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $checkStatement->bindValue(':email', $email, SQLITE3_TEXT);
    $existingUser = $checkStatement->execute();
    $existingRow = $existingUser !== false ? $existingUser->fetchArray(SQLITE3_ASSOC) : false;

    if (is_array($existingRow)) {
        respond([
            'ok' => false,
            'error' => 'Email already exists',
        ], 409);
    }

    $insertStatement = $database->prepare(
        'INSERT INTO users (full_name, email, phone, password_hash) VALUES (:full_name, :email, :phone, :password_hash)'
    );
    $insertStatement->bindValue(':full_name', $fullName, SQLITE3_TEXT);
    $insertStatement->bindValue(':email', $email, SQLITE3_TEXT);
    $insertStatement->bindValue(':phone', $phone, SQLITE3_TEXT);
    $insertStatement->bindValue(':password_hash', password_hash($password, PASSWORD_DEFAULT), SQLITE3_TEXT);
    $result = $insertStatement->execute();

    if ($result === false) {
        respond([
            'ok' => false,
            'error' => 'Registration failed',
        ], 500);
    }

    respond([
        'ok' => true,
        'message' => 'Registered',
    ]);
} catch (Throwable $exception) {
    respond([
        'ok' => false,
        'error' => 'Registration failed',
    ], 500);
}

