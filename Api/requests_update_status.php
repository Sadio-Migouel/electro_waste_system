<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/audit.php';

require_method('POST');

$admin = require_login();

if (($admin['role'] ?? '') !== 'admin') {
    respond([
        'error' => 'Forbidden',
    ], 403);
}

$body = json_input();
$request_id = (int) ($body['request_id'] ?? 0);
$status = trim((string) ($body['status'] ?? ''));
$allowedStatuses = ['approved', 'cancelled'];

if ($request_id <= 0) {
    respond([
        'error' => 'Valid request_id is required',
    ], 422);
}

if (!in_array($status, $allowedStatuses, true)) {
    respond([
        'error' => 'Invalid status',
    ], 422);
}

$db = db();
$ownerStmt = $db->prepare('SELECT user_id FROM pickup_requests WHERE id = :id LIMIT 1');

if (!$ownerStmt) {
    respond([
        'error' => 'Prepare failed: ' . $db->lastErrorMsg(),
    ], 500);
}

$ownerStmt->bindValue(':id', $request_id, SQLITE3_INTEGER);
$ownerRes = $ownerStmt->execute();

if (!$ownerRes) {
    respond([
        'error' => 'Execute failed: ' . $db->lastErrorMsg(),
    ], 500);
}

$requestRow = $ownerRes->fetchArray(SQLITE3_ASSOC);

if (!is_array($requestRow)) {
    respond([
        'error' => 'Request not found',
    ], 404);
}

$stmt = $db->prepare('UPDATE pickup_requests SET status = :s WHERE id = :id');

if (!$stmt) {
    respond([
        'error' => 'Prepare failed: ' . $db->lastErrorMsg(),
    ], 500);
}

$stmt->bindValue(':s', $status, SQLITE3_TEXT);
$stmt->bindValue(':id', $request_id, SQLITE3_INTEGER);
$res = $stmt->execute();

if (!$res) {
    respond([
        'error' => 'Execute failed: ' . $db->lastErrorMsg(),
    ], 500);
}

if ($db->changes() === 0) {
    respond([
        'error' => 'Request not found',
    ], 404);
}

if ($status === 'approved') {
    add_status_history($request_id, 'approved', 'Approved by admin', $db);
    add_notification((int) $requestRow['user_id'], 'Request Approved', "Your request #{$request_id} was approved.", $db);
} else {
    add_status_history($request_id, 'cancelled', 'Cancelled by admin', $db);
    add_notification((int) $requestRow['user_id'], 'Request Cancelled', "Your request #{$request_id} was cancelled.", $db);
}

respond([
    'ok' => true,
    'message' => 'Status updated',
]);