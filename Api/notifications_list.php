<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

require_method('GET');

$user = require_login();

try {
    $database = db();
    $statement = $database->prepare(
        'SELECT id, title, message, is_read, created_at
         FROM notifications
         WHERE user_id = :user_id
         ORDER BY id DESC'
    );
    $statement->bindValue(':user_id', (int) $user['id'], SQLITE3_INTEGER);
    $result = $statement->execute();
    $notifications = [];

    while ($result !== false && ($row = $result->fetchArray(SQLITE3_ASSOC)) !== false) {
        $notifications[] = [
            'id' => (int) $row['id'],
            'title' => (string) $row['title'],
            'message' => (string) $row['message'],
            'is_read' => (int) $row['is_read'],
            'created_at' => (string) $row['created_at'],
        ];
    }

    respond([
        'ok' => true,
        'notifications' => $notifications,
    ]);
} catch (Throwable $exception) {
    respond([
        'ok' => false,
        'error' => 'Failed to load notifications',
    ], 500);
}
