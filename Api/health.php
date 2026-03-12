<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

try {
    $database = db();
    $result = $database->querySingle('SELECT 1');

    echo json_encode([
        'ok' => $result === 1,
        'message' => 'E-Waste API is running',
        'time' => date(DATE_ATOM),
        'db' => $result === 1 ? 'connected' : 'unreachable',
    ], JSON_PRETTY_PRINT);
} catch (Throwable $exception) {
    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'message' => 'Health check failed',
        'time' => date(DATE_ATOM),
        'db' => 'error',
        'error' => $exception->getMessage(),
    ], JSON_PRETTY_PRINT);
}

