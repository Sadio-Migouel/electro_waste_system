<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

$requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if (!in_array($requestMethod, ['GET', 'POST'], true)) {
    respond([
        'error' => 'Method not allowed',
    ], 405);
}

$user = require_login();
$input = $requestMethod === 'POST' ? json_input() : $_GET;
$requestId = (int) ($input['request_id'] ?? 0);

if ($requestId <= 0) {
    respond([
        'error' => 'A valid request_id is required',
    ], 422);
}

$db = db();
$existsStatement = $db->prepare('SELECT id FROM pickup_requests WHERE id = :request_id LIMIT 1');

if (!$existsStatement) {
    respond([
        'error' => 'Prepare failed: ' . $db->lastErrorMsg(),
    ], 500);
}

$existsStatement->bindValue(':request_id', $requestId, SQLITE3_INTEGER);
$existsResult = $existsStatement->execute();

if (!$existsResult) {
    respond([
        'error' => 'Execute failed: ' . $db->lastErrorMsg(),
    ], 500);
}

if (!is_array($existsResult->fetchArray(SQLITE3_ASSOC))) {
    respond([
        'error' => 'Request not found',
    ], 404);
}

if (($user['role'] ?? '') === 'admin') {
    $accessStatement = $db->prepare('SELECT id FROM pickup_requests WHERE id = :request_id LIMIT 1');
    if (!$accessStatement) {
        respond(['error' => 'Prepare failed: ' . $db->lastErrorMsg()], 500);
    }
    $accessStatement->bindValue(':request_id', $requestId, SQLITE3_INTEGER);
} elseif (($user['role'] ?? '') === 'user') {
    $accessStatement = $db->prepare(
        'SELECT id FROM pickup_requests WHERE id = :request_id AND user_id = :user_id LIMIT 1'
    );
    if (!$accessStatement) {
        respond(['error' => 'Prepare failed: ' . $db->lastErrorMsg()], 500);
    }
    $accessStatement->bindValue(':request_id', $requestId, SQLITE3_INTEGER);
    $accessStatement->bindValue(':user_id', (int) $user['id'], SQLITE3_INTEGER);
} elseif (($user['role'] ?? '') === 'collector') {
    $accessStatement = $db->prepare(
        'SELECT pr.id
         FROM pickup_requests pr
         INNER JOIN assignments a ON a.request_id = pr.id
         WHERE pr.id = :request_id AND a.collector_id = :collector_id
         LIMIT 1'
    );
    if (!$accessStatement) {
        respond(['error' => 'Prepare failed: ' . $db->lastErrorMsg()], 500);
    }
    $accessStatement->bindValue(':request_id', $requestId, SQLITE3_INTEGER);
    $accessStatement->bindValue(':collector_id', (int) $user['id'], SQLITE3_INTEGER);
} else {
    respond([
        'error' => 'Forbidden',
    ], 403);
}

$accessResult = $accessStatement->execute();

if (!$accessResult) {
    respond([
        'error' => 'Execute failed: ' . $db->lastErrorMsg(),
    ], 500);
}

if (!is_array($accessResult->fetchArray(SQLITE3_ASSOC))) {
    respond([
        'error' => 'Forbidden',
    ], 403);
}

$historyStatement = $db->prepare(
    'SELECT status, note, created_at
     FROM request_status_history
     WHERE request_id = :request_id
     ORDER BY id ASC'
);

if (!$historyStatement) {
    respond([
        'error' => 'Prepare failed: ' . $db->lastErrorMsg(),
    ], 500);
}

$historyStatement->bindValue(':request_id', $requestId, SQLITE3_INTEGER);
$historyResult = $historyStatement->execute();

if (!$historyResult) {
    respond([
        'error' => 'Execute failed: ' . $db->lastErrorMsg(),
    ], 500);
}

$history = [];

while (($row = $historyResult->fetchArray(SQLITE3_ASSOC)) !== false) {
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