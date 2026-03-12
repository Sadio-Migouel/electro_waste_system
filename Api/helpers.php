<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function json_input(): array
{
    $rawInput = file_get_contents('php://input');

    if ($rawInput === false || trim($rawInput) === '') {
        return [];
    }

    $data = json_decode($rawInput, true);

    if (!is_array($data)) {
        respond([
            'ok' => false,
            'error' => 'Invalid JSON body',
        ], 400);
    }

    return $data;
}

function respond(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

function require_method(string $method): void
{
    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if (strtoupper($requestMethod) !== strtoupper($method)) {
        respond([
            'ok' => false,
            'error' => 'Method not allowed',
        ], 405);
    }
}

function require_login(): array
{
    $user = $_SESSION['user'] ?? null;

    if (!is_array($user)) {
        respond([
            'ok' => false,
            'error' => 'Unauthorized',
        ], 401);
    }

    return $user;
}

