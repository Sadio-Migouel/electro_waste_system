<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

require_method('GET');

$user = require_login();

if (($user['role'] ?? '') !== 'admin') {
    respond([
        'error' => 'Forbidden',
    ], 403);
}

$db = db();

$statusStatement = $db->prepare('SELECT status, COUNT(*) AS count FROM pickup_requests GROUP BY status');
if (!$statusStatement) {
    respond(['error' => 'Prepare failed: ' . $db->lastErrorMsg()], 500);
}
$statusResult = $statusStatement->execute();
if (!$statusResult) {
    respond(['error' => 'Execute failed: ' . $db->lastErrorMsg()], 500);
}
$statusCounts = [];
while (($row = $statusResult->fetchArray(SQLITE3_ASSOC)) !== false) {
    $statusCounts[(string) $row['status']] = (int) $row['count'];
}

$totals = [
    'total_requests' => 0,
    'total_users' => 0,
    'total_collectors' => 0,
];

$totalRequestsStatement = $db->prepare('SELECT COUNT(*) AS count FROM pickup_requests');
if (!$totalRequestsStatement) {
    respond(['error' => 'Prepare failed: ' . $db->lastErrorMsg()], 500);
}
$totalRequestsResult = $totalRequestsStatement->execute();
if (!$totalRequestsResult) {
    respond(['error' => 'Execute failed: ' . $db->lastErrorMsg()], 500);
}
$totalRequestsRow = $totalRequestsResult->fetchArray(SQLITE3_ASSOC);
$totals['total_requests'] = is_array($totalRequestsRow) ? (int) $totalRequestsRow['count'] : 0;

foreach (['user' => 'total_users', 'collector' => 'total_collectors'] as $role => $key) {
    $roleStatement = $db->prepare('SELECT COUNT(*) AS count FROM users WHERE role = :role');
    if (!$roleStatement) {
        respond(['error' => 'Prepare failed: ' . $db->lastErrorMsg()], 500);
    }
    $roleStatement->bindValue(':role', $role, SQLITE3_TEXT);
    $roleResult = $roleStatement->execute();
    if (!$roleResult) {
        respond(['error' => 'Execute failed: ' . $db->lastErrorMsg()], 500);
    }
    $roleRow = $roleResult->fetchArray(SQLITE3_ASSOC);
    $totals[$key] = is_array($roleRow) ? (int) $roleRow['count'] : 0;
}

$requestsPerDayStatement = $db->prepare(
    "SELECT date(created_at) AS day, COUNT(*) AS count
     FROM pickup_requests
     WHERE created_at >= datetime('now', '-14 days')
     GROUP BY date(created_at)
     ORDER BY day ASC"
);
if (!$requestsPerDayStatement) {
    respond(['error' => 'Prepare failed: ' . $db->lastErrorMsg()], 500);
}
$requestsPerDayResult = $requestsPerDayStatement->execute();
if (!$requestsPerDayResult) {
    respond(['error' => 'Execute failed: ' . $db->lastErrorMsg()], 500);
}
$requestsPerDay = [];
while (($row = $requestsPerDayResult->fetchArray(SQLITE3_ASSOC)) !== false) {
    $requestsPerDay[] = [
        'day' => (string) $row['day'],
        'count' => (int) $row['count'],
    ];
}

$itemsStatement = $db->prepare('SELECT items FROM pickup_requests ORDER BY id DESC LIMIT 500');
if (!$itemsStatement) {
    respond(['error' => 'Prepare failed: ' . $db->lastErrorMsg()], 500);
}
$itemsResult = $itemsStatement->execute();
if (!$itemsResult) {
    respond(['error' => 'Execute failed: ' . $db->lastErrorMsg()], 500);
}
$itemCounts = [];
$itemLabels = [];
while (($row = $itemsResult->fetchArray(SQLITE3_ASSOC)) !== false) {
    $items = json_decode((string) ($row['items'] ?? '[]'), true);
    if (!is_array($items)) {
        continue;
    }
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $name = trim((string) ($item['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $normalized = mb_strtolower($name, 'UTF-8');
        $itemCounts[$normalized] = ($itemCounts[$normalized] ?? 0) + 1;
        $itemLabels[$normalized] = $itemLabels[$normalized] ?? $name;
    }
}
arsort($itemCounts);
$topItems = [];
foreach (array_slice($itemCounts, 0, 8, true) as $normalized => $count) {
    $topItems[] = [
        'name' => $itemLabels[$normalized] ?? $normalized,
        'count' => (int) $count,
    ];
}

respond([
    'ok' => true,
    'totals' => $totals,
    'status_counts' => $statusCounts,
    'requests_per_day' => $requestsPerDay,
    'top_items' => $topItems,
]);

