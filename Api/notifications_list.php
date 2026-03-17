<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

require_method('GET');

$user = require_login();
$db = db();
$stmt = $db->prepare(
    'SELECT id, title, message, is_read, created_at
     FROM notifications
     WHERE user_id = :user_id
     ORDER BY id DESC'
);

if (!$stmt) {
    respond([
        'error' => 'Prepare failed: ' . $db->lastErrorMsg(),
    ], 500);
}

$stmt->bindValue(':user_id', (int) $user['id'], SQLITE3_INTEGER);
$res = $stmt->execute();

if (!$res) {
    respond([
        'error' => 'Execute failed: ' . $db->lastErrorMsg(),
    ], 500);
}

$notifications = [];

while (($row = $res->fetchArray(SQLITE3_ASSOC)) !== false) {
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