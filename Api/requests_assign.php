<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

require_method('POST');

$user = require_login();

if (($user['role'] ?? '') !== 'admin') {
    respond([
        'ok' => false,
        'error' => 'Forbidden',
    ], 403);
}

$input = json_input();
$requestId = filter_var($input['request_id'] ?? null, FILTER_VALIDATE_INT);
$collectorId = filter_var($input['collector_id'] ?? null, FILTER_VALIDATE_INT);

if ($requestId === false || $requestId <= 0 || $collectorId === false || $collectorId <= 0) {
    respond([
        'ok' => false,
        'error' => 'Valid request_id and collector_id are required',
    ], 422);
}

try {
    $database = db();

    $requestCheck = $database->prepare('SELECT id FROM pickup_requests WHERE id = :id LIMIT 1');
    $requestCheck->bindValue(':id', $requestId, SQLITE3_INTEGER);
    $requestResult = $requestCheck->execute();
    $requestRow = $requestResult !== false ? $requestResult->fetchArray(SQLITE3_ASSOC) : false;

    if (!is_array($requestRow)) {
        respond([
            'ok' => false,
            'error' => 'Request not found',
        ], 404);
    }

    $collectorCheck = $database->prepare(
        "SELECT id FROM users WHERE id = :id AND role = 'collector' LIMIT 1"
    );
    $collectorCheck->bindValue(':id', $collectorId, SQLITE3_INTEGER);
    $collectorResult = $collectorCheck->execute();
    $collectorRow = $collectorResult !== false ? $collectorResult->fetchArray(SQLITE3_ASSOC) : false;

    if (!is_array($collectorRow)) {
        respond([
            'ok' => false,
            'error' => 'Collector not found',
        ], 404);
    }

    $database->exec('BEGIN');

    $updateAssignment = $database->prepare(
        "UPDATE assignments
         SET collector_id = :collector_id, assigned_at = datetime('now')
         WHERE request_id = :request_id"
    );
    $updateAssignment->bindValue(':collector_id', $collectorId, SQLITE3_INTEGER);
    $updateAssignment->bindValue(':request_id', $requestId, SQLITE3_INTEGER);
    $updateResult = $updateAssignment->execute();

    if ($updateResult === false) {
        throw new RuntimeException('Failed to update assignment');
    }

    if ($database->changes() < 1) {
        $insertAssignment = $database->prepare(
            "INSERT INTO assignments (request_id, collector_id, assigned_at)
             VALUES (:request_id, :collector_id, datetime('now'))"
        );
        $insertAssignment->bindValue(':request_id', $requestId, SQLITE3_INTEGER);
        $insertAssignment->bindValue(':collector_id', $collectorId, SQLITE3_INTEGER);
        $insertResult = $insertAssignment->execute();

        if ($insertResult === false) {
            throw new RuntimeException('Failed to insert assignment');
        }
    }

    $updateRequest = $database->prepare(
        "UPDATE pickup_requests
         SET status = 'assigned'
         WHERE id = :id"
    );
    $updateRequest->bindValue(':id', $requestId, SQLITE3_INTEGER);
    $requestUpdateResult = $updateRequest->execute();

    if ($requestUpdateResult === false) {
        throw new RuntimeException('Failed to update request status');
    }

    $database->exec('COMMIT');

    respond([
        'ok' => true,
        'message' => 'Collector assigned',
    ]);
} catch (Throwable $exception) {
    if (isset($database) && $database instanceof SQLite3) {
        @$database->exec('ROLLBACK');
    }

    respond([
        'ok' => false,
        'error' => 'Failed to assign collector',
    ], 500);
}
