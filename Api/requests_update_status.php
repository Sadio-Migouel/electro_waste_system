<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/notify.php';

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
$status = trim((string) ($input['status'] ?? ''));
$allowedStatuses = ['approved', 'cancelled'];

if ($requestId === false || $requestId <= 0) {
    respond([
        'ok' => false,
        'error' => 'A valid request_id is required',
    ], 422);
}

if (!in_array($status, $allowedStatuses, true)) {
    respond([
        'ok' => false,
        'error' => 'Invalid status',
    ], 422);
}

try {
    $database = db();
    $checkStatement = $database->prepare('SELECT id, user_id FROM pickup_requests WHERE id = :id LIMIT 1');
    $checkStatement->bindValue(':id', $requestId, SQLITE3_INTEGER);
    $checkResult = $checkStatement->execute();
    $existingRequest = $checkResult !== false ? $checkResult->fetchArray(SQLITE3_ASSOC) : false;

    if (!is_array($existingRequest)) {
        respond([
            'ok' => false,
            'error' => 'Request not found',
        ], 404);
    }

    $database->exec('BEGIN');

    $statement = $database->prepare('UPDATE pickup_requests SET status = :status WHERE id = :id');
    $statement->bindValue(':status', $status, SQLITE3_TEXT);
    $statement->bindValue(':id', $requestId, SQLITE3_INTEGER);
    $result = $statement->execute();

    if ($result === false) {
        throw new RuntimeException('Failed to update status');
    }

    $note = $status === 'approved' ? 'Approved by admin' : 'Cancelled by admin';
    $title = $status === 'approved' ? 'Request Approved' : 'Request Cancelled';
    $message = $status === 'approved'
        ? "Your pickup request #{$requestId} was approved."
        : "Your pickup request #{$requestId} was cancelled.";

    add_status_history($requestId, $status, $note, $database);
    add_notification((int) $existingRequest['user_id'], $title, $message, $database);
    $database->exec('COMMIT');

    respond([
        'ok' => true,
        'message' => 'Status updated',
    ]);
} catch (Throwable $exception) {
    if (isset($database) && $database instanceof SQLite3) {
        @$database->exec('ROLLBACK');
    }

    respond([
        'ok' => false,
        'error' => 'Failed to update status',
    ], 500);
}
