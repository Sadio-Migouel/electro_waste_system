<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

require_method('GET');

$user = require_login();

if (($user['role'] ?? '') !== 'collector') {
    respond([
        'ok' => false,
        'error' => 'Forbidden',
    ], 403);
}

try {
    $database = db();
    $statement = $database->prepare(
        'SELECT pr.*
         FROM pickup_requests pr
         JOIN assignments a ON a.request_id = pr.id
         WHERE a.collector_id = :cid
         ORDER BY pr.id DESC'
    );
    $statement->bindValue(':cid', (int) $user['id'], SQLITE3_INTEGER);
    $result = $statement->execute();
    $requests = [];

    while ($result !== false && ($row = $result->fetchArray(SQLITE3_ASSOC)) !== false) {
        $decodedItems = json_decode((string) ($row['items'] ?? '[]'), true);

        $requests[] = [
            'id' => (int) $row['id'],
            'user_id' => (int) $row['user_id'],
            'address' => (string) $row['address'],
            'items' => is_array($decodedItems) ? $decodedItems : [],
            'status' => (string) $row['status'],
            'created_at' => (string) $row['created_at'],
        ];
    }

    respond([
        'ok' => true,
        'requests' => $requests,
    ]);
} catch (Throwable $exception) {
    respond([
        'ok' => false,
        'error' => 'Failed to load assigned requests',
    ], 500);
}
