<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

require_method('POST');

$user = require_login();
$input = json_input();
$markAll = (bool) ($input['mark_all'] ?? false);
$notificationId = filter_var($input['notification_id'] ?? null, FILTER_VALIDATE_INT);

if (!$markAll && ($notificationId === false || $notificationId <= 0)) {
    respond([
        'ok' => false,
        'error' => 'notification_id or mark_all is required',
    ], 422);
}

try {
    $database = db();

    if ($markAll) {
        $statement = $database->prepare(
            'UPDATE notifications
             SET is_read = 1
             WHERE user_id = :user_id'
        );
        $statement->bindValue(':user_id', (int) $user['id'], SQLITE3_INTEGER);
    } else {
        $statement = $database->prepare(
            'UPDATE notifications
             SET is_read = 1
             WHERE id = :id AND user_id = :user_id'
        );
        $statement->bindValue(':id', $notificationId, SQLITE3_INTEGER);
        $statement->bindValue(':user_id', (int) $user['id'], SQLITE3_INTEGER);
    }

    if ($statement->execute() === false) {
        throw new RuntimeException('Failed to update notifications');
    }

    respond([
        'ok' => true,
    ]);
} catch (Throwable $exception) {
    respond([
        'ok' => false,
        'error' => 'Failed to update notifications',
    ], 500);
}
