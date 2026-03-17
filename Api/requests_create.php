<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/audit.php';

require_method('POST');

$user = require_login();

if (($user['role'] ?? '') !== 'user') {
    respond([
        'error' => 'Forbidden',
    ], 403);
}

$body = json_input();

if (!is_array($body)) {
    $body = [];
}

$address = trim((string) ($body['address'] ?? ''));
$items = $body['items'] ?? [];

if ($address === '') {
    respond([
        'error' => 'Address is required',
    ], 422);
}

if (!is_array($items)) {
    respond([
        'error' => 'Items must be an array',
    ], 422);
}

$cleanItems = [];

foreach ($items as $item) {
    if (!is_array($item)) {
        continue;
    }

    $name = trim((string) ($item['name'] ?? ''));
    $qty = filter_var($item['qty'] ?? null, FILTER_VALIDATE_INT);

    if ($name === '' || $qty === false || $qty <= 0) {
        continue;
    }

    $cleanItems[] = [
        'name' => $name,
        'qty' => $qty,
    ];
}

if ($cleanItems === []) {
    respond([
        'error' => 'At least one valid item is required',
    ], 422);
}

$db = db();
$sql = "INSERT INTO pickup_requests (user_id, address, items, status)
VALUES (:uid, :addr, :items, 'pending')";
$stmt = $db->prepare($sql);

if (!$stmt) {
    respond([
        'error' => 'Prepare failed: ' . $db->lastErrorMsg(),
    ], 500);
}

$itemsJson = json_encode($cleanItems);

if ($itemsJson === false) {
    respond([
        'error' => 'Failed to encode items',
    ], 500);
}

$stmt->bindValue(':uid', (int) $user['id'], SQLITE3_INTEGER);
$stmt->bindValue(':addr', $address, SQLITE3_TEXT);
$stmt->bindValue(':items', $itemsJson, SQLITE3_TEXT);

$res = $stmt->execute();

if (!$res) {
    respond([
        'error' => 'Execute failed: ' . $db->lastErrorMsg(),
    ], 500);
}

$id = $db->lastInsertRowID();
add_status_history($id, 'pending', 'Request created', $db);

respond([
    'ok' => true,
    'message' => 'Request created',
    'request_id' => $id,
]);