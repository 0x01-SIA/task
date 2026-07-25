<?php

declare(strict_types=1);

function database_configured(): bool
{
    $database = config('database', []);

    return $database['host'] !== ''
        && $database['name'] !== ''
        && $database['username'] !== '';
}

function database_connection(): ?PDO
{
    static $connection = null;
    static $attempted = false;

    if ($attempted) {
        return $connection;
    }

    $attempted = true;

    if (!database_configured()) {
        return null;
    }

    $database = config('database');
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $database['host'],
        $database['port'],
        $database['name'],
        $database['charset']
    );

    $connection = new PDO(
        $dsn,
        $database['username'],
        $database['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    return $connection;
}

function database_status(): array
{
    if (!database_configured()) {
        return [
            'label' => 'Not configured',
            'variant' => 'warning',
            'message' => 'Add your database settings to .env before connecting.',
        ];
    }

    try {
        database_connection();

        return [
            'label' => 'Connected',
            'variant' => 'success',
            'message' => 'PDO connection is available.',
        ];
    } catch (Throwable $exception) {
        return [
            'label' => 'Connection failed',
            'variant' => 'danger',
            'message' => is_debug()
                ? $exception->getMessage()
                : 'Database connection is unavailable. Check your configuration and server status.',
        ];
    }
}
