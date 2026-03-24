<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $database = db();
    $columns = [];
    $result = $database->query("PRAGMA table_info(pickup_requests)");

    while ($result instanceof SQLite3Result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
        $columns[] = (string) ($row['name'] ?? '');
    }

    if (in_array('media_path', $columns, true)) {
        echo "Column media_path already exists.\n";
        exit;
    }

    $database->exec('ALTER TABLE pickup_requests ADD COLUMN media_path TEXT');
    echo "Added media_path column to pickup_requests.\n";
} catch (Throwable $exception) {
    if (str_contains(strtolower($exception->getMessage()), 'duplicate column name')) {
        echo "Column media_path already exists.\n";
        exit;
    }

    http_response_code(500);
    echo 'Migration failed: ' . $exception->getMessage();
}
