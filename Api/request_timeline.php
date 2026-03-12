<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

$requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if (!in_array($requestMethod, ['GET', 'POST'], true)) {
    respond([
        'ok' => false,
        'error' => 'Method not allowed',
    ], 405);
}

$user = require_login();
$input = $requestMethod === 'POST' ? json_input() : $_GET;
$requestId = filter_var($input['request_id'] ?? null, FILTER_VALIDATE_INT);

if ($requestId === false || $requestId <= 0) {
    respond([
        'ok' => false,
        'error' => 'A valid request_id is required',
    ], 422);
}

try {
    $database = db();
    $existsStatement = $database->prepare(
        'SELECT id FROM pickup_requests WHERE id = :request_id LIMIT 1'
    );
    $existsStatement->bindValue(':request_id', $requestId, SQLITE3_INTEGER);
    $existsResult = $existsStatement->execute();
    $existsRow = $existsResult !== false ? $existsResult->fetchArray(SQLITE3_ASSOC) : false;

    if (!is_array($existsRow)) {
        respond([
            'ok' => false,
            'error' => 'Request not found',
        ], 404);
    }

    if (($user['role'] ?? '') === 'admin') {
        $accessStatement = $database->prepare(
            'SELECT id FROM pickup_requests WHERE id = :request_id LIMIT 1'
        );
        $accessStatement->bindValue(':request_id', $requestId, SQLITE3_INTEGER);
    } elseif (($user['role'] ?? '') === 'user') {
        $accessStatement = $database->prepare(
            'SELECT id
             FROM pickup_requests
             WHERE id = :request_id AND user_id = :user_id
             LIMIT 1'
        );
        $accessStatement->bindValue(':request_id', $requestId, SQLITE3_INTEGER);
        $accessStatement->bindValue(':user_id', (int) $user['id'], SQLITE3_INTEGER);
    } elseif (($user['role'] ?? '') === 'collector') {
        $accessStatement = $database->prepare(
            'SELECT pr.id
             FROM pickup_requests pr
             INNER JOIN assignments a ON a.request_id = pr.id
             WHERE pr.id = :request_id AND a.collector_id = :collector_id
             LIMIT 1'
        );
        $accessStatement->bindValue(':request_id', $requestId, SQLITE3_INTEGER);
        $accessStatement->bindValue(':collector_id', (int) $user['id'], SQLITE3_INTEGER);
    } else {
        respond([
            'ok' => false,
            'error' => 'Forbidden',
        ], 403);
    }

    $accessResult = $accessStatement->execute();
    $accessRow = $accessResult !== false ? $accessResult->fetchArray(SQLITE3_ASSOC) : false;

    if (!is_array($accessRow)) {
        respond([
            'ok' => false,
            'error' => 'Forbidden',
        ], 403);
    }

    $historyStatement = $database->prepare(
        'SELECT status, note, created_at
         FROM request_status_history
         WHERE request_id = :request_id
         ORDER BY id ASC'
    );
    $historyStatement->bindValue(':request_id', $requestId, SQLITE3_INTEGER);
    $historyResult = $historyStatement->execute();
    $history = [];

    while ($historyResult !== false && ($row = $historyResult->fetchArray(SQLITE3_ASSOC)) !== false) {
        $history[] = [
            'status' => (string) $row['status'],
            'note' => $row['note'] !== null ? (string) $row['note'] : null,
            'created_at' => (string) $row['created_at'],
        ];
    }

    respond([
        'ok' => true,
        'history' => $history,
    ]);
} catch (Throwable $exception) {
    respond([
        'ok' => false,
        'error' => 'Failed to load request timeline',
    ], 500);
}
