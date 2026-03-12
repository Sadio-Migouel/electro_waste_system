<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

require_method('POST');

$user = require_login();

if (($user['role'] ?? '') !== 'user') {
    respond([
        'ok' => false,
        'error' => 'Forbidden',
    ], 403);
}

$input = json_input();
$address = trim((string) ($input['address'] ?? ''));
$itemsInput = $input['items'] ?? null;

if ($address === '') {
    respond([
        'ok' => false,
        'error' => 'Address is required',
    ], 422);
}

if (!is_array($itemsInput)) {
    respond([
        'ok' => false,
        'error' => 'At least one valid item is required',
    ], 422);
}

$cleanItems = [];

foreach ($itemsInput as $item) {
    if (!is_array($item)) {
        continue;
    }

    $name = trim((string) ($item['name'] ?? ''));
    $qtyValue = $item['qty'] ?? null;
    $qty = filter_var($qtyValue, FILTER_VALIDATE_INT);

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
        'ok' => false,
        'error' => 'At least one valid item is required',
    ], 422);
}

try {
    $database = db();
    $statement = $database->prepare(
        'INSERT INTO pickup_requests (user_id, address, items, status) VALUES (:user_id, :address, :items, :status)'
    );
    $statement->bindValue(':user_id', (int) $user['id'], SQLITE3_INTEGER);
    $statement->bindValue(':address', $address, SQLITE3_TEXT);
    $statement->bindValue(':items', json_encode($cleanItems, JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
    $statement->bindValue(':status', 'pending', SQLITE3_TEXT);
    $statement->execute();

    respond([
        'ok' => true,
        'message' => 'Request created',
    ]);
} catch (Throwable $exception) {
    respond([
        'ok' => false,
        'error' => 'Failed to create request',
    ], 500);
}
