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
    'uploads' => [
        'base_dir' => (string) env_value('UPLOAD_BASE_DIR', base_path('storage/uploads')),
        'attachments' => [
            'max_bytes' => (int) env_value('JOB_ATTACHMENT_MAX_BYTES', 10 * 1024 * 1024),
        ],
        'photos' => [
            'max_bytes' => (int) env_value('JOB_PHOTO_MAX_BYTES', 25 * 1024 * 1024),
            'max_files' => (int) env_value('JOB_PHOTO_MAX_FILES', 10),
        ],
    ],
];
