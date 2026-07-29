<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/helpers.php';
require dirname(__DIR__) . '/app/database/connection.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

if (!database_configured()) {
    fwrite(STDERR, "Database credentials are missing. Update .env before running setup.\n");
    exit(1);
}

$includeSeed = !in_array('--no-seed', $argv, true);
$connection = database_connection();

if ($connection === null) {
    fwrite(STDERR, "Unable to create a PDO connection.\n");
    exit(1);
}

try {
    reset_database($connection);
    run_sql_file($connection, dirname(__DIR__) . '/database/schema.sql');
    fwrite(STDOUT, "Schema loaded successfully.\n");

    if ($includeSeed) {
        $connection->beginTransaction();

        try {
            run_sql_file($connection, dirname(__DIR__) . '/database/seed.sql');
            $connection->commit();
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }

        fwrite(STDOUT, "Seed data loaded successfully.\n");
    }
} catch (Throwable $exception) {
    fwrite(STDERR, sprintf("Database setup failed: %s\n", $exception->getMessage()));
    exit(1);
} finally {
    restore_foreign_key_checks($connection);
}

function run_sql_file(PDO $connection, string $path): void
{
    if (!is_file($path)) {
        throw new RuntimeException(sprintf('SQL file not found: %s', $path));
    }

    $sql = file_get_contents($path);

    if ($sql === false) {
        throw new RuntimeException(sprintf('Unable to read SQL file: %s', $path));
    }

    $connection->exec($sql);
}

function reset_database(PDO $connection): void
{
    $connection->exec('SET FOREIGN_KEY_CHECKS = 0');

    try {
        $connection->exec(
            'DROP TABLE IF EXISTS
                job_materials,
                materials,
                job_customer_confirmations,
                job_photos,
                job_attachments,
                job_notes,
                jobs,
                tasks,
                locations,
                customers,
                company_users,
                users,
                companies'
        );
    } finally {
        restore_foreign_key_checks($connection);
    }
}

function restore_foreign_key_checks(PDO $connection): void
{
    try {
        $connection->exec('SET FOREIGN_KEY_CHECKS = 1');
    } catch (Throwable) {
    }
}
