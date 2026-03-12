<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

require_method('GET');

$user = require_login();
$database = db();

try {
    if (($user['role'] ?? '') === 'user') {
        $statement = $database->prepare(
            'SELECT id, user_id, address, items, status, created_at
             FROM pickup_requests
             WHERE user_id = :user_id
             ORDER BY id DESC'
        );
        $statement->bindValue(':user_id', (int) $user['id'], SQLITE3_INTEGER);
    } elseif (($user['role'] ?? '') === 'admin') {
        $statement = $database->prepare(
            'SELECT pr.id, pr.user_id, pr.address, pr.items, pr.status, pr.created_at,
                    a.collector_id AS collector_id, c.full_name AS collector_name, c.email AS collector_email
             FROM pickup_requests pr
             LEFT JOIN assignments a ON a.request_id = pr.id
             LEFT JOIN users c ON c.id = a.collector_id
             ORDER BY pr.id DESC'
        );
    } elseif (($user['role'] ?? '') === 'collector') {
        $statement = $database->prepare(
            'SELECT pr.id, pr.user_id, pr.address, pr.items, pr.status, pr.created_at,
                    NULL AS collector_id, NULL AS collector_name, NULL AS collector_email
             FROM pickup_requests pr
             INNER JOIN assignments a ON a.request_id = pr.id
             WHERE a.collector_id = :collector_id
             ORDER BY pr.id DESC'
        );
        $statement->bindValue(':collector_id', (int) $user['id'], SQLITE3_INTEGER);
    } else {
        respond([
            'ok' => false,
            'error' => 'Forbidden',
        ], 403);
    }

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
            'collector_id' => isset($row['collector_id']) && $row['collector_id'] !== null ? (int) $row['collector_id'] : null,
            'collector_name' => isset($row['collector_name']) ? ($row['collector_name'] !== null ? (string) $row['collector_name'] : null) : null,
            'collector_email' => isset($row['collector_email']) ? ($row['collector_email'] !== null ? (string) $row['collector_email'] : null) : null,
        ];
    }

    respond([
        'ok' => true,
        'requests' => $requests,
    ]);
} catch (Throwable $exception) {
    respond([
        'ok' => false,
        'error' => 'Failed to load requests',
    ], 500);
}
