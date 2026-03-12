<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $database = db();

    $statements = [
        'users' => <<<SQL
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    phone TEXT,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL CHECK(role IN ('user', 'collector', 'admin')) DEFAULT 'user',
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
)
SQL,
        'pickup_requests' => <<<SQL
CREATE TABLE IF NOT EXISTS pickup_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    address TEXT NOT NULL,
    items TEXT NOT NULL,
    status TEXT NOT NULL CHECK(status IN ('pending', 'approved', 'assigned', 'collected', 'cancelled')) DEFAULT 'pending',
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)
SQL,
        'assignments' => <<<SQL
CREATE TABLE IF NOT EXISTS assignments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    request_id INTEGER NOT NULL UNIQUE,
    collector_id INTEGER NOT NULL,
    assigned_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (request_id) REFERENCES pickup_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (collector_id) REFERENCES users(id) ON DELETE CASCADE
)
SQL,
        'notifications' => <<<SQL
CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    message TEXT NOT NULL,
    is_read INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)
SQL,
    ];

    foreach ($statements as $name => $sql) {
        $result = $database->exec($sql);

        if ($result !== true) {
            http_response_code(500);
            echo "Failed creating {$name}: " . $database->lastErrorMsg();
            exit;
        }
    }

    echo "Database initialized successfully.\n";
    echo 'DB file: ' . db_path();
} catch (Throwable $exception) {
    http_response_code(500);
    echo 'Initialization error: ' . $exception->getMessage();
}
