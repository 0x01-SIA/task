<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => (string) env_value('APP_NAME', 'Task App'),
        'env' => (string) env_value('APP_ENV', 'development'),
        'debug' => (bool) env_value('APP_DEBUG', false),
        'url' => (string) env_value('APP_URL', ''),
    ],
    'database' => [
        'host' => (string) env_value('DB_HOST', ''),
        'port' => (string) env_value('DB_PORT', '3306'),
        'name' => (string) env_value('DB_NAME', ''),
        'username' => (string) env_value('DB_USERNAME', ''),
        'password' => (string) env_value('DB_PASSWORD', ''),
        'charset' => 'utf8mb4',
    ],
];
