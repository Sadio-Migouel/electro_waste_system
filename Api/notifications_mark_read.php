<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

require_method('POST');

$user = require_login();
$input = json_input();
$markAll = (bool) ($input['mark_all'] ?? false);
$notificationId = (int) ($input['notification_id'] ?? 0);

if (!$markAll && $notificationId <= 0) {
    respond([
        'error' => 'notification_id or mark_all is required',
    ], 422);
}

$db = db();

if ($markAll) {
    $statement = $db->prepare(
        'UPDATE notifications
         SET is_read = 1
         WHERE user_id = :user_id'
    );

    if (!$statement) {
        respond([
            'error' => 'Prepare failed: ' . $db->lastErrorMsg(),
        ], 500);
    }

    $statement->bindValue(':user_id', (int) $user['id'], SQLITE3_INTEGER);
} else {
    $statement = $db->prepare(
        'UPDATE notifications
         SET is_read = 1
         WHERE id = :id AND user_id = :user_id'
    );

    if (!$statement) {
        respond([
            'error' => 'Prepare failed: ' . $db->lastErrorMsg(),
        ], 500);
    }

    $statement->bindValue(':id', $notificationId, SQLITE3_INTEGER);
    $statement->bindValue(':user_id', (int) $user['id'], SQLITE3_INTEGER);
}

if ($statement->execute() === false) {
    respond([
        'error' => 'Execute failed: ' . $db->lastErrorMsg(),
    ], 500);
}

respond([
    'ok' => true,
]);