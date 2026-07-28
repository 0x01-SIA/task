<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/helpers.php';
require dirname(__DIR__) . '/app/database/connection.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

if (!database_configured()) {
    fwrite(STDERR, "Database credentials are missing. Update .env before running upgrade.\n");
    exit(1);
}

$connection = database_connection();

if ($connection === null) {
    fwrite(STDERR, "Unable to create a PDO connection.\n");
    exit(1);
}

try {
    ensureTasksTable($connection);
    ensureJobsTaskLink($connection);
    ensureJobAttachmentsTable($connection);
    ensureJobPhotosTable($connection);
    fwrite(STDOUT, "Database upgrade completed successfully.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, sprintf("Database upgrade failed: %s\n", $exception->getMessage()));
    exit(1);
}

function ensureTasksTable(PDO $connection): void
{
    $connection->exec(
        "CREATE TABLE IF NOT EXISTS tasks (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            task_number VARCHAR(50) NOT NULL,
            customer_id BIGINT UNSIGNED NOT NULL,
            location_id BIGINT UNSIGNED DEFAULT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            status ENUM('new', 'planned', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'new',
            priority ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal',
            requested_date DATE DEFAULT NULL,
            due_date DATE DEFAULT NULL,
            created_by_user_id BIGINT UNSIGNED DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_tasks_task_number (task_number),
            KEY idx_tasks_customer_id (customer_id),
            KEY idx_tasks_location_id (location_id),
            KEY idx_tasks_created_by_user_id (created_by_user_id),
            KEY idx_tasks_status (status),
            KEY idx_tasks_priority (priority),
            KEY idx_tasks_due_date (due_date),
            CONSTRAINT fk_tasks_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_tasks_location FOREIGN KEY (location_id) REFERENCES locations (id) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_tasks_created_by_user FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    ensureColumn($connection, 'tasks', 'location_id', 'ALTER TABLE tasks ADD COLUMN location_id BIGINT UNSIGNED DEFAULT NULL AFTER customer_id');
    ensureColumn($connection, 'tasks', 'title', 'ALTER TABLE tasks ADD COLUMN title VARCHAR(255) NOT NULL AFTER location_id');
    ensureColumn($connection, 'tasks', 'description', 'ALTER TABLE tasks ADD COLUMN description TEXT DEFAULT NULL AFTER title');
    ensureColumn($connection, 'tasks', 'status', "ALTER TABLE tasks ADD COLUMN status ENUM('new', 'planned', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'new' AFTER description");
    ensureColumn($connection, 'tasks', 'priority', "ALTER TABLE tasks ADD COLUMN priority ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal' AFTER status");
    ensureColumn($connection, 'tasks', 'requested_date', 'ALTER TABLE tasks ADD COLUMN requested_date DATE DEFAULT NULL AFTER priority');
    ensureColumn($connection, 'tasks', 'due_date', 'ALTER TABLE tasks ADD COLUMN due_date DATE DEFAULT NULL AFTER requested_date');
    ensureColumn($connection, 'tasks', 'created_by_user_id', 'ALTER TABLE tasks ADD COLUMN created_by_user_id BIGINT UNSIGNED DEFAULT NULL AFTER due_date');

    ensureIndex($connection, 'tasks', 'uq_tasks_task_number', 'ALTER TABLE tasks ADD UNIQUE KEY uq_tasks_task_number (task_number)');
    ensureIndex($connection, 'tasks', 'idx_tasks_customer_id', 'ALTER TABLE tasks ADD KEY idx_tasks_customer_id (customer_id)');
    ensureIndex($connection, 'tasks', 'idx_tasks_location_id', 'ALTER TABLE tasks ADD KEY idx_tasks_location_id (location_id)');
    ensureIndex($connection, 'tasks', 'idx_tasks_created_by_user_id', 'ALTER TABLE tasks ADD KEY idx_tasks_created_by_user_id (created_by_user_id)');
    ensureIndex($connection, 'tasks', 'idx_tasks_status', 'ALTER TABLE tasks ADD KEY idx_tasks_status (status)');
    ensureIndex($connection, 'tasks', 'idx_tasks_priority', 'ALTER TABLE tasks ADD KEY idx_tasks_priority (priority)');
    ensureIndex($connection, 'tasks', 'idx_tasks_due_date', 'ALTER TABLE tasks ADD KEY idx_tasks_due_date (due_date)');

    ensureForeignKey($connection, 'tasks', 'fk_tasks_customer', 'ALTER TABLE tasks ADD CONSTRAINT fk_tasks_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT ON UPDATE CASCADE');
    ensureForeignKey($connection, 'tasks', 'fk_tasks_location', 'ALTER TABLE tasks ADD CONSTRAINT fk_tasks_location FOREIGN KEY (location_id) REFERENCES locations (id) ON DELETE RESTRICT ON UPDATE CASCADE');
    ensureForeignKey($connection, 'tasks', 'fk_tasks_created_by_user', 'ALTER TABLE tasks ADD CONSTRAINT fk_tasks_created_by_user FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE');
}

function ensureJobsTaskLink(PDO $connection): void
{
    ensureColumn($connection, 'jobs', 'task_id', 'ALTER TABLE jobs ADD COLUMN task_id BIGINT UNSIGNED DEFAULT NULL AFTER job_number');
    ensureIndex($connection, 'jobs', 'idx_jobs_task_id', 'ALTER TABLE jobs ADD KEY idx_jobs_task_id (task_id)');
    ensureForeignKey($connection, 'jobs', 'fk_jobs_task', 'ALTER TABLE jobs ADD CONSTRAINT fk_jobs_task FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE RESTRICT ON UPDATE CASCADE');
}

function ensureJobAttachmentsTable(PDO $connection): void
{
    $connection->exec(
        "CREATE TABLE IF NOT EXISTS job_attachments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            job_id BIGINT UNSIGNED NOT NULL,
            original_filename VARCHAR(255) NOT NULL,
            stored_filename VARCHAR(255) NOT NULL,
            storage_path VARCHAR(1000) NOT NULL,
            mime_type VARCHAR(255) NOT NULL,
            file_size BIGINT UNSIGNED NOT NULL,
            uploaded_by_user_id BIGINT UNSIGNED DEFAULT NULL,
            uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_job_attachments_job_id (job_id),
            KEY idx_job_attachments_uploaded_by_user_id (uploaded_by_user_id),
            CONSTRAINT fk_job_attachments_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_job_attachments_uploaded_by_user FOREIGN KEY (uploaded_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    ensureColumn($connection, 'job_attachments', 'stored_filename', 'ALTER TABLE job_attachments ADD COLUMN stored_filename VARCHAR(255) NOT NULL AFTER original_filename');
    ensureColumn($connection, 'job_attachments', 'storage_path', 'ALTER TABLE job_attachments ADD COLUMN storage_path VARCHAR(1000) NOT NULL AFTER stored_filename');
    ensureColumn($connection, 'job_attachments', 'mime_type', 'ALTER TABLE job_attachments ADD COLUMN mime_type VARCHAR(255) NOT NULL AFTER storage_path');
    ensureColumn($connection, 'job_attachments', 'file_size', 'ALTER TABLE job_attachments ADD COLUMN file_size BIGINT UNSIGNED NOT NULL AFTER mime_type');
    ensureColumn($connection, 'job_attachments', 'uploaded_by_user_id', 'ALTER TABLE job_attachments ADD COLUMN uploaded_by_user_id BIGINT UNSIGNED DEFAULT NULL AFTER file_size');
    ensureColumn($connection, 'job_attachments', 'uploaded_at', 'ALTER TABLE job_attachments ADD COLUMN uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER uploaded_by_user_id');

    ensureIndex($connection, 'job_attachments', 'idx_job_attachments_job_id', 'ALTER TABLE job_attachments ADD KEY idx_job_attachments_job_id (job_id)');
    ensureIndex($connection, 'job_attachments', 'idx_job_attachments_uploaded_by_user_id', 'ALTER TABLE job_attachments ADD KEY idx_job_attachments_uploaded_by_user_id (uploaded_by_user_id)');

    ensureForeignKey($connection, 'job_attachments', 'fk_job_attachments_job', 'ALTER TABLE job_attachments ADD CONSTRAINT fk_job_attachments_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE RESTRICT ON UPDATE CASCADE');
    ensureForeignKey($connection, 'job_attachments', 'fk_job_attachments_uploaded_by_user', 'ALTER TABLE job_attachments ADD CONSTRAINT fk_job_attachments_uploaded_by_user FOREIGN KEY (uploaded_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE');
}

function ensureJobPhotosTable(PDO $connection): void
{
    $connection->exec(
        "CREATE TABLE IF NOT EXISTS job_photos (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            job_id BIGINT UNSIGNED NOT NULL,
            original_filename VARCHAR(255) NOT NULL,
            stored_filename VARCHAR(255) NOT NULL,
            storage_path VARCHAR(1000) NOT NULL,
            mime_type VARCHAR(255) NOT NULL,
            file_size BIGINT UNSIGNED NOT NULL,
            caption VARCHAR(255) DEFAULT NULL,
            uploaded_by_user_id BIGINT UNSIGNED DEFAULT NULL,
            uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_job_photos_job_id (job_id),
            KEY idx_job_photos_uploaded_by_user_id (uploaded_by_user_id),
            CONSTRAINT fk_job_photos_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_job_photos_uploaded_by_user FOREIGN KEY (uploaded_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    ensureColumn($connection, 'job_photos', 'stored_filename', 'ALTER TABLE job_photos ADD COLUMN stored_filename VARCHAR(255) NOT NULL AFTER original_filename');
    ensureColumn($connection, 'job_photos', 'storage_path', 'ALTER TABLE job_photos ADD COLUMN storage_path VARCHAR(1000) NOT NULL AFTER stored_filename');
    ensureColumn($connection, 'job_photos', 'mime_type', 'ALTER TABLE job_photos ADD COLUMN mime_type VARCHAR(255) NOT NULL AFTER storage_path');
    ensureColumn($connection, 'job_photos', 'file_size', 'ALTER TABLE job_photos ADD COLUMN file_size BIGINT UNSIGNED NOT NULL AFTER mime_type');
    ensureColumn($connection, 'job_photos', 'caption', 'ALTER TABLE job_photos ADD COLUMN caption VARCHAR(255) DEFAULT NULL AFTER file_size');
    ensureColumn($connection, 'job_photos', 'uploaded_by_user_id', 'ALTER TABLE job_photos ADD COLUMN uploaded_by_user_id BIGINT UNSIGNED DEFAULT NULL AFTER caption');
    ensureColumn($connection, 'job_photos', 'uploaded_at', 'ALTER TABLE job_photos ADD COLUMN uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER uploaded_by_user_id');

    ensureIndex($connection, 'job_photos', 'idx_job_photos_job_id', 'ALTER TABLE job_photos ADD KEY idx_job_photos_job_id (job_id)');
    ensureIndex($connection, 'job_photos', 'idx_job_photos_uploaded_by_user_id', 'ALTER TABLE job_photos ADD KEY idx_job_photos_uploaded_by_user_id (uploaded_by_user_id)');

    ensureForeignKey($connection, 'job_photos', 'fk_job_photos_job', 'ALTER TABLE job_photos ADD CONSTRAINT fk_job_photos_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE RESTRICT ON UPDATE CASCADE');
    ensureForeignKey($connection, 'job_photos', 'fk_job_photos_uploaded_by_user', 'ALTER TABLE job_photos ADD CONSTRAINT fk_job_photos_uploaded_by_user FOREIGN KEY (uploaded_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE');
}

function ensureColumn(PDO $connection, string $table, string $column, string $sql): void
{
    $statement = $connection->prepare(
        'SELECT 1
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND COLUMN_NAME = :column_name
         LIMIT 1'
    );
    $statement->execute([
        'table_name' => $table,
        'column_name' => $column,
    ]);

    if ($statement->fetchColumn() === false) {
        $connection->exec($sql);
    }
}

function ensureIndex(PDO $connection, string $table, string $index, string $sql): void
{
    $statement = $connection->prepare(
        'SELECT 1
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND INDEX_NAME = :index_name
         LIMIT 1'
    );
    $statement->execute([
        'table_name' => $table,
        'index_name' => $index,
    ]);

    if ($statement->fetchColumn() === false) {
        $connection->exec($sql);
    }
}

function ensureForeignKey(PDO $connection, string $table, string $constraint, string $sql): void
{
    $statement = $connection->prepare(
        'SELECT 1
         FROM information_schema.TABLE_CONSTRAINTS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND CONSTRAINT_NAME = :constraint_name
           AND CONSTRAINT_TYPE = \'FOREIGN KEY\'
         LIMIT 1'
    );
    $statement->execute([
        'table_name' => $table,
        'constraint_name' => $constraint,
    ]);

    if ($statement->fetchColumn() === false) {
        $connection->exec($sql);
    }
}
