<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/audit.php';

require_method('POST');

$u = require_login();

if (($u['role'] ?? '') !== 'collector') {
    respond([
        'error' => 'Forbidden',
    ], 403);
}

$body = json_input();
$rid = (int) ($body['request_id'] ?? 0);

if ($rid <= 0) {
    respond([
        'error' => 'Invalid request_id',
    ], 422);
}

$db = db();
$stmt = $db->prepare('SELECT 1 FROM assignments WHERE request_id = :rid AND collector_id = :cid');

if (!$stmt) {
    respond([
        'error' => 'Prepare failed: ' . $db->lastErrorMsg(),
    ], 500);
}

$stmt->bindValue(':rid', $rid, SQLITE3_INTEGER);
$stmt->bindValue(':cid', (int) $u['id'], SQLITE3_INTEGER);
$res = $stmt->execute();

if (!$res) {
    respond([
        'error' => 'Execute failed: ' . $db->lastErrorMsg(),
    ], 500);
}

if (!is_array($res->fetchArray(SQLITE3_ASSOC))) {
    respond([
        'error' => 'Not assigned to you',
    ], 403);
}

$statusStmt = $db->prepare('SELECT status, user_id FROM pickup_requests WHERE id = :rid');

if (!$statusStmt) {
    respond([
        'error' => 'Prepare failed: ' . $db->lastErrorMsg(),
    ], 500);
}

$statusStmt->bindValue(':rid', $rid, SQLITE3_INTEGER);
$statusRes = $statusStmt->execute();

if (!$statusRes) {
    respond([
        'error' => 'Execute failed: ' . $db->lastErrorMsg(),
    ], 500);
}

$request = $statusRes->fetchArray(SQLITE3_ASSOC);

if (!is_array($request)) {
    respond([
        'error' => 'Request not found',
    ], 404);
}

if (($request['status'] ?? '') !== 'assigned') {
    respond([
        'error' => 'Request not in assigned state',
    ], 409);
}

$stmtU = $db->prepare("UPDATE pickup_requests SET status = 'collected' WHERE id = :rid");

if (!$stmtU) {
    respond([
        'error' => 'Prepare failed: ' . $db->lastErrorMsg(),
    ], 500);
}

$stmtU->bindValue(':rid', $rid, SQLITE3_INTEGER);
$resU = $stmtU->execute();

if (!$resU) {
    respond([
        'error' => 'Execute failed: ' . $db->lastErrorMsg(),
    ], 500);
}

if ($db->changes() === 0) {
    respond([
        'error' => 'Request not found',
    ], 404);
}

add_status_history($rid, 'collected', 'Marked collected by collector', $db);
add_notification((int) $request['user_id'], 'Pickup Collected', "Your pickup request #{$rid} has been marked as collected.", $db);

respond([
    'ok' => true,
    'message' => 'Marked as collected',
]);