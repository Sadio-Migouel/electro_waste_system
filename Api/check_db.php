<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

try {
    $database = db();
    $query = "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name";
    $result = $database->query($query);

    $tables = [];

    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $tables[] = $row['name'];
    }

    echo json_encode([
        'ok' => true,
        'db_path' => db_path(),
        'tables' => $tables,
    ], JSON_PRETTY_PRINT);
} catch (Throwable $exception) {
    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'db_path' => db_path(),
        'tables' => [],
        'error' => $exception->getMessage(),
    ], JSON_PRETTY_PRINT);
}
