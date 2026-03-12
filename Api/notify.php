<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function add_notification(int $userId, string $title, string $message, ?SQLite3 $database = null): void
{
    $connection = $database ?? db();
    $statement = $connection->prepare(
        'INSERT INTO notifications (user_id, title, message) VALUES (:user_id, :title, :message)'
    );
    $statement->bindValue(':user_id', $userId, SQLITE3_INTEGER);
    $statement->bindValue(':title', $title, SQLITE3_TEXT);
    $statement->bindValue(':message', $message, SQLITE3_TEXT);

    if ($statement->execute() === false) {
        throw new RuntimeException('Failed to add notification');
    }
}

function add_status_history(int $requestId, string $status, ?string $note = null, ?SQLite3 $database = null): void
{
    $connection = $database ?? db();
    $statement = $connection->prepare(
        'INSERT INTO request_status_history (request_id, status, note) VALUES (:request_id, :status, :note)'
    );
    $statement->bindValue(':request_id', $requestId, SQLITE3_INTEGER);
    $statement->bindValue(':status', $status, SQLITE3_TEXT);
    $statement->bindValue(':note', $note, $note === null ? SQLITE3_NULL : SQLITE3_TEXT);

    if ($statement->execute() === false) {
        throw new RuntimeException('Failed to add status history');
    }
}
