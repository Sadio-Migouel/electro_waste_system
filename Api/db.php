<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db_path(): string
{
    $path = __DIR__ . '/../Data/electro_waste.sqlite';
    $resolvedPath = realpath($path);

    return $resolvedPath !== false ? $resolvedPath : $path;
}

function db(): SQLite3
{
    static $connection = null;

    if ($connection instanceof SQLite3) {
        return $connection;
    }

    $databasePath = db_path();

    if (!is_dir(dirname($databasePath))) {
        mkdir(dirname($databasePath), 0777, true);
    }

    $connection = new SQLite3($databasePath);
    $connection->enableExceptions(true);
    $connection->exec('PRAGMA foreign_keys = ON');

    return $connection;
}
