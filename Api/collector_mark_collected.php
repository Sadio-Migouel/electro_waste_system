<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/notify.php';

require_method('POST');

$user = require_login();

if (($user['role'] ?? '') !== 'collector') {
    respond([
        'ok' => false,
        'error' => 'Forbidden',
    ], 403);
}

$input = json_input();
$requestId = filter_var($input['request_id'] ?? null, FILTER_VALIDATE_INT);

if ($requestId === false || $requestId <= 0) {
    respond([
        'ok' => false,
        'error' => 'A valid request_id is required',
    ], 422);
}

try {
    $database = db();

    $assignmentCheck = $database->prepare(
        'SELECT 1
         FROM assignments
         WHERE request_id = :rid AND collector_id = :cid
         LIMIT 1'
    );
    $assignmentCheck->bindValue(':rid', $requestId, SQLITE3_INTEGER);
    $assignmentCheck->bindValue(':cid', (int) $user['id'], SQLITE3_INTEGER);
    $assignmentResult = $assignmentCheck->execute();
    $assignmentRow = $assignmentResult !== false ? $assignmentResult->fetchArray(SQLITE3_ASSOC) : false;

    if (!is_array($assignmentRow)) {
        respond([
            'ok' => false,
            'error' => 'Not your assignment',
        ], 403);
    }

    $ownerStatement = $database->prepare(
        'SELECT user_id FROM pickup_requests WHERE id = :rid LIMIT 1'
    );
    $ownerStatement->bindValue(':rid', $requestId, SQLITE3_INTEGER);
    $ownerResult = $ownerStatement->execute();
    $ownerRow = $ownerResult !== false ? $ownerResult->fetchArray(SQLITE3_ASSOC) : false;

    if (!is_array($ownerRow)) {
        respond([
            'ok' => false,
            'error' => 'Request not found',
        ], 404);
    }

    $database->exec('BEGIN');

    $updateStatement = $database->prepare(
        "UPDATE pickup_requests
         SET status = 'collected'
         WHERE id = :rid AND status = 'assigned'"
    );
    $updateStatement->bindValue(':rid', $requestId, SQLITE3_INTEGER);
    $updateResult = $updateStatement->execute();

    if ($updateResult === false) {
        throw new RuntimeException('Failed to update request');
    }

    if ($database->changes() < 1) {
        $database->exec('ROLLBACK');
        respond([
            'ok' => false,
            'error' => 'Only assigned requests can be marked as collected',
        ], 409);
    }

    add_status_history($requestId, 'collected', 'Marked collected by collector', $database);
    add_notification(
        (int) $ownerRow['user_id'],
        'Pickup Collected',
        "Your pickup request #{$requestId} has been marked as collected.",
        $database
    );
    $database->exec('COMMIT');

    respond([
        'ok' => true,
        'message' => 'Marked as collected',
    ]);
} catch (Throwable $exception) {
    if (isset($database) && $database instanceof SQLite3) {
        @$database->exec('ROLLBACK');
    }

    respond([
        'ok' => false,
        'error' => 'Failed to mark request as collected',
    ], 500);
}
