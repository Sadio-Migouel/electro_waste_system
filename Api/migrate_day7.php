<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $database = db();

    $statements = [
        <<<'SQL'
CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    message TEXT NOT NULL,
    is_read INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
)
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS request_status_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    request_id INTEGER NOT NULL,
    status TEXT NOT NULL,
    note TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY(request_id) REFERENCES pickup_requests(id) ON DELETE CASCADE
)
SQL,
    ];

    foreach ($statements as $sql) {
        if ($database->exec($sql) !== true) {
            http_response_code(500);
            echo 'Migration failed: ' . $database->lastErrorMsg();
            exit;
        }
    }

    echo 'Day7 migration complete';
} catch (Throwable $exception) {
    http_response_code(500);
    echo 'Migration error: ' . $exception->getMessage();
}