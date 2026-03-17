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
$collector_id = (int) ($body['collector_id'] ?? 0);

if ($request_id <= 0 || $collector_id <= 0) {
    respond([
        'error' => 'Valid request_id and collector_id are required',
    ], 422);
}

$db = db();
$requestStmt = $db->prepare('SELECT user_id FROM pickup_requests WHERE id = :rid LIMIT 1');

if (!$requestStmt) {
    respond([
        'error' => 'Prepare failed: ' . $db->lastErrorMsg(),
    ], 500);
}

$requestStmt->bindValue(':rid', $request_id, SQLITE3_INTEGER);
$requestRes = $requestStmt->execute();

if (!$requestRes) {
    respond([
        'error' => 'Execute failed: ' . $db->lastErrorMsg(),
    ], 500);
}

$request = $requestRes->fetchArray(SQLITE3_ASSOC);

if (!is_array($request)) {
    respond([
        'error' => 'Request not found',
    ], 404);
}

$collectorStmt = $db->prepare("SELECT id FROM users WHERE id = :id AND role = 'collector' LIMIT 1");

if (!$collectorStmt) {
    respond([
        'error' => 'Prepare failed: ' . $db->lastErrorMsg(),
    ], 500);
}

$collectorStmt->bindValue(':id', $collector_id, SQLITE3_INTEGER);
$collectorRes = $collectorStmt->execute();

if (!$collectorRes) {
    respond([
        'error' => 'Execute failed: ' . $db->lastErrorMsg(),
    ], 500);
}

$collector = $collectorRes->fetchArray(SQLITE3_ASSOC);

if (!is_array($collector)) {
    respond([
        'error' => 'Collector not found',
    ], 404);
}

$updateAssignment = $db->prepare(
    "UPDATE assignments SET collector_id = :cid, assigned_at = datetime('now') WHERE request_id = :rid"
);

if (!$updateAssignment) {
    respond([
        'error' => 'Prepare failed: ' . $db->lastErrorMsg(),
    ], 500);
}

$updateAssignment->bindValue(':cid', $collector_id, SQLITE3_INTEGER);
$updateAssignment->bindValue(':rid', $request_id, SQLITE3_INTEGER);
$updateAssignmentRes = $updateAssignment->execute();

if (!$updateAssignmentRes) {
    respond([
        'error' => 'Execute failed: ' . $db->lastErrorMsg(),
    ], 500);
}

if ($db->changes() === 0) {
    $insertAssignment = $db->prepare('INSERT INTO assignments (request_id, collector_id) VALUES (:rid, :cid)');

    if (!$insertAssignment) {
        respond([
            'error' => 'Prepare failed: ' . $db->lastErrorMsg(),
        ], 500);
    }

    $insertAssignment->bindValue(':rid', $request_id, SQLITE3_INTEGER);
    $insertAssignment->bindValue(':cid', $collector_id, SQLITE3_INTEGER);
    $insertAssignmentRes = $insertAssignment->execute();

    if (!$insertAssignmentRes) {
        respond([
            'error' => 'Execute failed: ' . $db->lastErrorMsg(),
        ], 500);
    }
}

$updateRequest = $db->prepare("UPDATE pickup_requests SET status = 'assigned' WHERE id = :rid");

if (!$updateRequest) {
    respond([
        'error' => 'Prepare failed: ' . $db->lastErrorMsg(),
    ], 500);
}

$updateRequest->bindValue(':rid', $request_id, SQLITE3_INTEGER);
$updateRequestRes = $updateRequest->execute();

if (!$updateRequestRes) {
    respond([
        'error' => 'Execute failed: ' . $db->lastErrorMsg(),
    ], 500);
}

add_status_history($request_id, 'assigned', 'Assigned to collector', $db);
add_notification((int) $request['user_id'], 'Collector Assigned', "A collector has been assigned to your request #{$request_id}.", $db);
add_notification($collector_id, 'New Pickup Assigned', "You have been assigned pickup request #{$request_id}.", $db);

respond([
    'ok' => true,
    'message' => 'Collector assigned',
]);