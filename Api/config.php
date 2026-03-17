<?php

const DEV_MODE = true;

$sessionPath = __DIR__ . '/../Data/sessions';

if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0777, true);
}

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $exception): void {
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }

    http_response_code(500);

    $payload = [
        'ok' => false,
        'error' => DEV_MODE ? $exception->getMessage() : 'Internal Server Error',
    ];

    if (DEV_MODE) {
        $payload['type'] = get_class($exception);
        $payload['file'] = $exception->getFile();
        $payload['line'] = $exception->getLine();
    }

    echo json_encode($payload, JSON_PRETTY_PRINT);
    exit;
});

register_shutdown_function(static function (): void {
    $error = error_get_last();

    if (!is_array($error)) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

    if (!in_array($error['type'] ?? null, $fatalTypes, true)) {
        return;
    }

    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }

    http_response_code(500);

    $payload = [
        'ok' => false,
        'error' => DEV_MODE ? (string) ($error['message'] ?? 'Internal Server Error') : 'Internal Server Error',
    ];

    if (DEV_MODE) {
        $payload['file'] = (string) ($error['file'] ?? '');
        $payload['line'] = (int) ($error['line'] ?? 0);
    }

    echo json_encode($payload, JSON_PRETTY_PRINT);
});

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_save_path($sessionPath);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (PHP_SAPI !== 'cli') {
    header('Content-Type: application/json; charset=utf-8');

    if (isset($_SERVER['HTTP_ORIGIN'])) {
        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');
    }

    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
}

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'CLI';

if ($requestMethod === 'OPTIONS') {
    http_response_code(204);
    exit;
}
