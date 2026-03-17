<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

$user = require_login();

if (($user['role'] ?? '') !== 'admin') {
    respond([
        'error' => 'Forbidden',
    ], 403);
}

$db = db();
$status = trim((string) ($_GET['status'] ?? ''));
$from = trim((string) ($_GET['from'] ?? ''));
$to = trim((string) ($_GET['to'] ?? ''));

$sql = 'SELECT id, user_id, address, items, status, created_at FROM pickup_requests';
$conditions = [];
$params = [];

if ($status !== '') {
    $conditions[] = 'status = :status';
    $params[] = [':status', $status, SQLITE3_TEXT];
}
if ($from !== '') {
    $conditions[] = 'date(created_at) >= :from';
    $params[] = [':from', $from, SQLITE3_TEXT];
}
if ($to !== '') {
    $conditions[] = 'date(created_at) <= :to';
    $params[] = [':to', $to, SQLITE3_TEXT];
}
if ($conditions !== []) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}
$sql .= ' ORDER BY id DESC';

$stmt = $db->prepare($sql);
if (!$stmt) {
    respond(['error' => 'Prepare failed: ' . $db->lastErrorMsg()], 500);
}
foreach ($params as [$name, $value, $type]) {
    $stmt->bindValue($name, $value, $type);
}
$result = $stmt->execute();
if (!$result) {
    respond(['error' => 'Execute failed: ' . $db->lastErrorMsg()], 500);
}

if (!headers_sent()) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="pickup_requests_export.csv"');
}

$output = fopen('php://output', 'w');
fputcsv($output, ['id', 'status', 'address', 'items_summary', 'created_at', 'user_id']);

while (($row = $result->fetchArray(SQLITE3_ASSOC)) !== false) {
    $decodedItems = json_decode((string) ($row['items'] ?? '[]'), true);
    $itemParts = [];
    if (is_array($decodedItems)) {
        foreach ($decodedItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            $name = trim((string) ($item['name'] ?? ''));
            $qty = (int) ($item['qty'] ?? 0);
            if ($name === '') {
                continue;
            }
            $itemParts[] = sprintf('%s(%d)', $name, $qty);
        }
    }

    fputcsv($output, [
        (int) $row['id'],
        (string) $row['status'],
        (string) $row['address'],
        implode('; ', $itemParts),
        (string) $row['created_at'],
        (int) $row['user_id'],
    ]);
}

fclose($output);
exit;