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
    $needsDisposableReset = schema_requires_disposable_reset($connection);

    if ($needsDisposableReset) {
        clear_disposable_application_data($connection);
    }

    ensure_users_super_admin_role($connection);
    ensure_companies_table($connection);
    ensure_company_users_table($connection);
    ensure_customers_company_scope($connection);
    ensure_locations_company_scope($connection);
    ensure_tasks_company_scope($connection);
    ensure_jobs_company_scope($connection);
    ensure_job_notes_company_scope($connection);
    ensure_job_attachments_company_scope($connection);
    ensure_job_photos_company_scope($connection);
    ensure_job_customer_confirmations_company_scope($connection);
    ensure_materials_company_scope($connection);
    ensure_material_stock_tables($connection);
    ensure_job_materials_company_scope($connection);
    migrate_existing_job_material_movements($connection);
    ensure_default_company_exists($connection);

    fwrite(STDOUT, "Database upgrade completed successfully.\n");
    fwrite(STDOUT, "If no super admin exists yet, run: php bin/create-super-admin.php --email=you@example.com --name=\"Your Name\" --password='change-me'\n");
} catch (Throwable $exception) {
    fwrite(STDERR, sprintf("Database upgrade failed: %s\n", $exception->getMessage()));
    exit(1);
}

function schema_requires_disposable_reset(PDO $connection): bool
{
    if (!table_exists($connection, 'companies')) {
        return true;
    }

    if (!column_exists($connection, 'companies', 'slug')) {
        return true;
    }

    if (!table_exists($connection, 'company_users')) {
        return true;
    }

    if (!users_role_supports_super_admin($connection)) {
        return true;
    }

    foreach ([
        ['customers', 'company_id'],
        ['locations', 'company_id'],
        ['tasks', 'company_id'],
        ['jobs', 'company_id'],
        ['job_notes', 'company_id'],
        ['job_attachments', 'company_id'],
        ['job_photos', 'company_id'],
        ['job_customer_confirmations', 'company_id'],
        ['materials', 'company_id'],
        ['job_materials', 'company_id'],
    ] as [$table, $column]) {
        if (table_exists($connection, $table) && !column_exists($connection, $table, $column)) {
            return true;
        }
    }

    return false;
}

function clear_disposable_application_data(PDO $connection): void
{
    $connection->exec('SET FOREIGN_KEY_CHECKS = 0');

    try {
        foreach ([
            'job_materials',
            'job_customer_confirmations',
            'job_photos',
            'job_attachments',
            'job_notes',
            'jobs',
            'tasks',
            'locations',
            'customers',
            'materials',
            'company_users',
            'companies',
        ] as $table) {
            if (table_exists($connection, $table)) {
                $connection->exec('DELETE FROM ' . $table);
            }
        }
    } finally {
        $connection->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}

function ensure_users_super_admin_role(PDO $connection): void
{
    if (!table_exists($connection, 'users')) {
        throw new RuntimeException('The users table is required before running the upgrade.');
    }

    $connection->exec(
        "ALTER TABLE users
         MODIFY role ENUM('super_admin','admin','dispatcher','worker') NOT NULL DEFAULT 'worker'"
    );
    ensure_index($connection, 'users', 'idx_users_role', 'ALTER TABLE users ADD KEY idx_users_role (role)');
    ensure_index($connection, 'users', 'idx_users_is_active', 'ALTER TABLE users ADD KEY idx_users_is_active (is_active)');
}

function ensure_companies_table(PDO $connection): void
{
    $connection->exec(
        "CREATE TABLE IF NOT EXISTS companies (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(120) NOT NULL,
            registration_number VARCHAR(100) DEFAULT NULL,
            email VARCHAR(255) DEFAULT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            address TEXT DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_companies_slug (slug),
            KEY idx_companies_name (name),
            KEY idx_companies_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    ensure_column($connection, 'companies', 'slug', "ALTER TABLE companies ADD COLUMN slug VARCHAR(120) NOT NULL AFTER name");
    $connection->exec("ALTER TABLE companies MODIFY name VARCHAR(255) NOT NULL");
    $connection->exec("ALTER TABLE companies MODIFY slug VARCHAR(120) NOT NULL");
    $connection->exec("ALTER TABLE companies MODIFY is_active TINYINT(1) NOT NULL DEFAULT 1");

    ensure_index($connection, 'companies', 'uq_companies_slug', 'ALTER TABLE companies ADD UNIQUE KEY uq_companies_slug (slug)');
    ensure_index($connection, 'companies', 'idx_companies_name', 'ALTER TABLE companies ADD KEY idx_companies_name (name)');
    ensure_index($connection, 'companies', 'idx_companies_is_active', 'ALTER TABLE companies ADD KEY idx_companies_is_active (is_active)');
}

function ensure_company_users_table(PDO $connection): void
{
    $connection->exec(
        "CREATE TABLE IF NOT EXISTS company_users (
            company_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            role ENUM('admin','dispatcher','worker') NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (company_id, user_id),
            KEY idx_company_users_user_id (user_id),
            KEY idx_company_users_role (role),
            KEY idx_company_users_is_active (is_active),
            CONSTRAINT fk_company_users_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_company_users_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    ensure_index($connection, 'company_users', 'idx_company_users_user_id', 'ALTER TABLE company_users ADD KEY idx_company_users_user_id (user_id)');
    ensure_index($connection, 'company_users', 'idx_company_users_role', 'ALTER TABLE company_users ADD KEY idx_company_users_role (role)');
    ensure_index($connection, 'company_users', 'idx_company_users_is_active', 'ALTER TABLE company_users ADD KEY idx_company_users_is_active (is_active)');
    ensure_foreign_key($connection, 'company_users', 'fk_company_users_company', 'ALTER TABLE company_users ADD CONSTRAINT fk_company_users_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE RESTRICT ON UPDATE CASCADE');
    ensure_foreign_key($connection, 'company_users', 'fk_company_users_user', 'ALTER TABLE company_users ADD CONSTRAINT fk_company_users_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE');
}

function ensure_customers_company_scope(PDO $connection): void
{
    ensure_column($connection, 'customers', 'company_id', 'ALTER TABLE customers ADD COLUMN company_id BIGINT UNSIGNED NOT NULL FIRST');
    ensure_index($connection, 'customers', 'idx_customers_company_id', 'ALTER TABLE customers ADD KEY idx_customers_company_id (company_id)');
    ensure_foreign_key($connection, 'customers', 'fk_customers_company', 'ALTER TABLE customers ADD CONSTRAINT fk_customers_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE RESTRICT ON UPDATE CASCADE');
}

function ensure_locations_company_scope(PDO $connection): void
{
    ensure_column($connection, 'locations', 'company_id', 'ALTER TABLE locations ADD COLUMN company_id BIGINT UNSIGNED NOT NULL AFTER id');
    ensure_index($connection, 'locations', 'idx_locations_company_id', 'ALTER TABLE locations ADD KEY idx_locations_company_id (company_id)');
    ensure_foreign_key($connection, 'locations', 'fk_locations_company', 'ALTER TABLE locations ADD CONSTRAINT fk_locations_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE RESTRICT ON UPDATE CASCADE');
}

function ensure_tasks_company_scope(PDO $connection): void
{
    ensure_tasks_table($connection);
    ensure_column($connection, 'tasks', 'company_id', 'ALTER TABLE tasks ADD COLUMN company_id BIGINT UNSIGNED NOT NULL AFTER id');
    ensure_index($connection, 'tasks', 'idx_tasks_company_id', 'ALTER TABLE tasks ADD KEY idx_tasks_company_id (company_id)');
    ensure_foreign_key($connection, 'tasks', 'fk_tasks_company', 'ALTER TABLE tasks ADD CONSTRAINT fk_tasks_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE RESTRICT ON UPDATE CASCADE');
}

function ensure_jobs_company_scope(PDO $connection): void
{
    ensure_jobs_task_link($connection);
    ensure_column($connection, 'jobs', 'company_id', 'ALTER TABLE jobs ADD COLUMN company_id BIGINT UNSIGNED NOT NULL AFTER id');
    ensure_index($connection, 'jobs', 'idx_jobs_company_id', 'ALTER TABLE jobs ADD KEY idx_jobs_company_id (company_id)');
    ensure_foreign_key($connection, 'jobs', 'fk_jobs_company', 'ALTER TABLE jobs ADD CONSTRAINT fk_jobs_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE RESTRICT ON UPDATE CASCADE');
}

function ensure_job_notes_company_scope(PDO $connection): void
{
    if (!table_exists($connection, 'job_notes')) {
        $connection->exec(
            "CREATE TABLE job_notes (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                company_id BIGINT UNSIGNED NOT NULL,
                job_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED DEFAULT NULL,
                note TEXT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_job_notes_company_id (company_id),
                KEY idx_job_notes_job_id (job_id),
                KEY idx_job_notes_user_id (user_id),
                CONSTRAINT fk_job_notes_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT fk_job_notes_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT fk_job_notes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        return;
    }

    ensure_column($connection, 'job_notes', 'company_id', 'ALTER TABLE job_notes ADD COLUMN company_id BIGINT UNSIGNED NOT NULL AFTER id');
    ensure_index($connection, 'job_notes', 'idx_job_notes_company_id', 'ALTER TABLE job_notes ADD KEY idx_job_notes_company_id (company_id)');
    ensure_foreign_key($connection, 'job_notes', 'fk_job_notes_company', 'ALTER TABLE job_notes ADD CONSTRAINT fk_job_notes_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE RESTRICT ON UPDATE CASCADE');
}

function ensure_job_attachments_company_scope(PDO $connection): void
{
    ensure_job_attachments_table($connection);
    ensure_column($connection, 'job_attachments', 'company_id', 'ALTER TABLE job_attachments ADD COLUMN company_id BIGINT UNSIGNED NOT NULL AFTER id');
    ensure_index($connection, 'job_attachments', 'idx_job_attachments_company_id', 'ALTER TABLE job_attachments ADD KEY idx_job_attachments_company_id (company_id)');
    ensure_foreign_key($connection, 'job_attachments', 'fk_job_attachments_company', 'ALTER TABLE job_attachments ADD CONSTRAINT fk_job_attachments_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE RESTRICT ON UPDATE CASCADE');
}

function ensure_job_photos_company_scope(PDO $connection): void
{
    ensure_job_photos_table($connection);
    ensure_column($connection, 'job_photos', 'company_id', 'ALTER TABLE job_photos ADD COLUMN company_id BIGINT UNSIGNED NOT NULL AFTER id');
    ensure_index($connection, 'job_photos', 'idx_job_photos_company_id', 'ALTER TABLE job_photos ADD KEY idx_job_photos_company_id (company_id)');
    ensure_foreign_key($connection, 'job_photos', 'fk_job_photos_company', 'ALTER TABLE job_photos ADD CONSTRAINT fk_job_photos_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE RESTRICT ON UPDATE CASCADE');
}

function ensure_job_customer_confirmations_company_scope(PDO $connection): void
{
    ensure_job_customer_confirmations_table($connection);
    ensure_column($connection, 'job_customer_confirmations', 'company_id', 'ALTER TABLE job_customer_confirmations ADD COLUMN company_id BIGINT UNSIGNED NOT NULL AFTER id');
    ensure_index($connection, 'job_customer_confirmations', 'idx_job_customer_confirmations_company_id', 'ALTER TABLE job_customer_confirmations ADD KEY idx_job_customer_confirmations_company_id (company_id)');
    ensure_foreign_key($connection, 'job_customer_confirmations', 'fk_job_customer_confirmations_company', 'ALTER TABLE job_customer_confirmations ADD CONSTRAINT fk_job_customer_confirmations_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE RESTRICT ON UPDATE CASCADE');
}

function ensure_materials_company_scope(PDO $connection): void
{
    ensure_materials_table($connection);
    ensure_column($connection, 'materials', 'company_id', 'ALTER TABLE materials ADD COLUMN company_id BIGINT UNSIGNED NOT NULL AFTER id');
    ensure_index($connection, 'materials', 'idx_materials_company_id', 'ALTER TABLE materials ADD KEY idx_materials_company_id (company_id)');
    ensure_foreign_key($connection, 'materials', 'fk_materials_company', 'ALTER TABLE materials ADD CONSTRAINT fk_materials_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE RESTRICT ON UPDATE CASCADE');
}

function ensure_material_stock_tables(PDO $connection): void
{
    ensure_material_movements_table($connection);
    ensure_material_inventories_table($connection);
    ensure_material_inventory_lines_table($connection);
}

function ensure_job_materials_company_scope(PDO $connection): void
{
    ensure_job_materials_table($connection);
    ensure_column($connection, 'job_materials', 'company_id', 'ALTER TABLE job_materials ADD COLUMN company_id BIGINT UNSIGNED NOT NULL AFTER id');
    ensure_column($connection, 'job_materials', 'movement_id', 'ALTER TABLE job_materials ADD COLUMN movement_id BIGINT UNSIGNED DEFAULT NULL AFTER material_id');
    ensure_column($connection, 'job_materials', 'entry_type', "ALTER TABLE job_materials ADD COLUMN entry_type ENUM('used','returned') NOT NULL DEFAULT 'used' AFTER movement_id");
    ensure_column($connection, 'job_materials', 'occurred_at', 'ALTER TABLE job_materials ADD COLUMN occurred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER recorded_by_user_id');
    $connection->exec("ALTER TABLE job_materials MODIFY entry_type ENUM('used','returned') NOT NULL DEFAULT 'used'");
    $connection->exec('ALTER TABLE job_materials MODIFY quantity DECIMAL(14,3) NOT NULL');
    ensure_index_absent($connection, 'job_materials', 'uq_job_materials_job_id_material_id', 'ALTER TABLE job_materials DROP INDEX uq_job_materials_job_id_material_id');
    ensure_index($connection, 'job_materials', 'idx_job_materials_company_id', 'ALTER TABLE job_materials ADD KEY idx_job_materials_company_id (company_id)');
    ensure_index($connection, 'job_materials', 'idx_job_materials_job_id', 'ALTER TABLE job_materials ADD KEY idx_job_materials_job_id (job_id)');
    ensure_index($connection, 'job_materials', 'idx_job_materials_movement_id', 'ALTER TABLE job_materials ADD KEY idx_job_materials_movement_id (movement_id)');
    ensure_foreign_key($connection, 'job_materials', 'fk_job_materials_company', 'ALTER TABLE job_materials ADD CONSTRAINT fk_job_materials_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE RESTRICT ON UPDATE CASCADE');
    ensure_foreign_key($connection, 'job_materials', 'fk_job_materials_movement', 'ALTER TABLE job_materials ADD CONSTRAINT fk_job_materials_movement FOREIGN KEY (movement_id) REFERENCES material_movements (id) ON DELETE SET NULL ON UPDATE CASCADE');
}

function ensure_default_company_exists(PDO $connection): void
{
    $statement = $connection->query('SELECT COUNT(*) FROM companies');

    if ((int) $statement->fetchColumn() > 0) {
        return;
    }

    $statement = $connection->prepare(
        "INSERT INTO companies (name, slug, registration_number, email, phone, address, is_active)
         VALUES ('Default Company', 'default-company', NULL, NULL, NULL, NULL, 1)"
    );
    $statement->execute();
}

function ensure_tasks_table(PDO $connection): void
{
    $connection->exec(
        "CREATE TABLE IF NOT EXISTS tasks (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            company_id BIGINT UNSIGNED NOT NULL,
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
            KEY idx_tasks_company_id (company_id),
            KEY idx_tasks_customer_id (customer_id),
            KEY idx_tasks_location_id (location_id),
            KEY idx_tasks_created_by_user_id (created_by_user_id),
            KEY idx_tasks_status (status),
            KEY idx_tasks_priority (priority),
            KEY idx_tasks_due_date (due_date),
            CONSTRAINT fk_tasks_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_tasks_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_tasks_location FOREIGN KEY (location_id) REFERENCES locations (id) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_tasks_created_by_user FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    ensure_column($connection, 'tasks', 'location_id', 'ALTER TABLE tasks ADD COLUMN location_id BIGINT UNSIGNED DEFAULT NULL AFTER customer_id');
    ensure_column($connection, 'tasks', 'title', 'ALTER TABLE tasks ADD COLUMN title VARCHAR(255) NOT NULL AFTER location_id');
    ensure_column($connection, 'tasks', 'description', 'ALTER TABLE tasks ADD COLUMN description TEXT DEFAULT NULL AFTER title');
    ensure_column($connection, 'tasks', 'status', "ALTER TABLE tasks ADD COLUMN status ENUM('new','planned','in_progress','completed','cancelled') NOT NULL DEFAULT 'new' AFTER description");
    ensure_column($connection, 'tasks', 'priority', "ALTER TABLE tasks ADD COLUMN priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal' AFTER status");
    ensure_column($connection, 'tasks', 'requested_date', 'ALTER TABLE tasks ADD COLUMN requested_date DATE DEFAULT NULL AFTER priority');
    ensure_column($connection, 'tasks', 'due_date', 'ALTER TABLE tasks ADD COLUMN due_date DATE DEFAULT NULL AFTER requested_date');
    ensure_column($connection, 'tasks', 'created_by_user_id', 'ALTER TABLE tasks ADD COLUMN created_by_user_id BIGINT UNSIGNED DEFAULT NULL AFTER due_date');

    ensure_index($connection, 'tasks', 'uq_tasks_task_number', 'ALTER TABLE tasks ADD UNIQUE KEY uq_tasks_task_number (task_number)');
    ensure_index($connection, 'tasks', 'idx_tasks_customer_id', 'ALTER TABLE tasks ADD KEY idx_tasks_customer_id (customer_id)');
    ensure_index($connection, 'tasks', 'idx_tasks_location_id', 'ALTER TABLE tasks ADD KEY idx_tasks_location_id (location_id)');
    ensure_index($connection, 'tasks', 'idx_tasks_created_by_user_id', 'ALTER TABLE tasks ADD KEY idx_tasks_created_by_user_id (created_by_user_id)');
    ensure_index($connection, 'tasks', 'idx_tasks_status', 'ALTER TABLE tasks ADD KEY idx_tasks_status (status)');
    ensure_index($connection, 'tasks', 'idx_tasks_priority', 'ALTER TABLE tasks ADD KEY idx_tasks_priority (priority)');
    ensure_index($connection, 'tasks', 'idx_tasks_due_date', 'ALTER TABLE tasks ADD KEY idx_tasks_due_date (due_date)');
    ensure_foreign_key($connection, 'tasks', 'fk_tasks_customer', 'ALTER TABLE tasks ADD CONSTRAINT fk_tasks_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT ON UPDATE CASCADE');
    ensure_foreign_key($connection, 'tasks', 'fk_tasks_location', 'ALTER TABLE tasks ADD CONSTRAINT fk_tasks_location FOREIGN KEY (location_id) REFERENCES locations (id) ON DELETE RESTRICT ON UPDATE CASCADE');
    ensure_foreign_key($connection, 'tasks', 'fk_tasks_created_by_user', 'ALTER TABLE tasks ADD CONSTRAINT fk_tasks_created_by_user FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE');
}

function ensure_jobs_task_link(PDO $connection): void
{
    ensure_column($connection, 'jobs', 'task_id', 'ALTER TABLE jobs ADD COLUMN task_id BIGINT UNSIGNED DEFAULT NULL AFTER job_number');
    ensure_index($connection, 'jobs', 'idx_jobs_task_id', 'ALTER TABLE jobs ADD KEY idx_jobs_task_id (task_id)');
    ensure_foreign_key($connection, 'jobs', 'fk_jobs_task', 'ALTER TABLE jobs ADD CONSTRAINT fk_jobs_task FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE RESTRICT ON UPDATE CASCADE');
}

function ensure_job_attachments_table(PDO $connection): void
{
    $connection->exec(
        "CREATE TABLE IF NOT EXISTS job_attachments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            company_id BIGINT UNSIGNED NOT NULL,
            job_id BIGINT UNSIGNED NOT NULL,
            original_filename VARCHAR(255) NOT NULL,
            stored_filename VARCHAR(255) NOT NULL,
            storage_path VARCHAR(1000) NOT NULL,
            mime_type VARCHAR(255) NOT NULL,
            file_size BIGINT UNSIGNED NOT NULL,
            uploaded_by_user_id BIGINT UNSIGNED DEFAULT NULL,
            uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_job_attachments_company_id (company_id),
            KEY idx_job_attachments_job_id (job_id),
            KEY idx_job_attachments_uploaded_by_user_id (uploaded_by_user_id),
            CONSTRAINT fk_job_attachments_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_job_attachments_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_job_attachments_uploaded_by_user FOREIGN KEY (uploaded_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    ensure_column($connection, 'job_attachments', 'stored_filename', 'ALTER TABLE job_attachments ADD COLUMN stored_filename VARCHAR(255) NOT NULL AFTER original_filename');
    ensure_column($connection, 'job_attachments', 'storage_path', 'ALTER TABLE job_attachments ADD COLUMN storage_path VARCHAR(1000) NOT NULL AFTER stored_filename');
    ensure_column($connection, 'job_attachments', 'mime_type', 'ALTER TABLE job_attachments ADD COLUMN mime_type VARCHAR(255) NOT NULL AFTER storage_path');
    ensure_column($connection, 'job_attachments', 'file_size', 'ALTER TABLE job_attachments ADD COLUMN file_size BIGINT UNSIGNED NOT NULL AFTER mime_type');
    ensure_column($connection, 'job_attachments', 'uploaded_by_user_id', 'ALTER TABLE job_attachments ADD COLUMN uploaded_by_user_id BIGINT UNSIGNED DEFAULT NULL AFTER file_size');
    ensure_column($connection, 'job_attachments', 'uploaded_at', 'ALTER TABLE job_attachments ADD COLUMN uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER uploaded_by_user_id');

    ensure_index($connection, 'job_attachments', 'idx_job_attachments_job_id', 'ALTER TABLE job_attachments ADD KEY idx_job_attachments_job_id (job_id)');
    ensure_index($connection, 'job_attachments', 'idx_job_attachments_uploaded_by_user_id', 'ALTER TABLE job_attachments ADD KEY idx_job_attachments_uploaded_by_user_id (uploaded_by_user_id)');
    ensure_foreign_key($connection, 'job_attachments', 'fk_job_attachments_job', 'ALTER TABLE job_attachments ADD CONSTRAINT fk_job_attachments_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE RESTRICT ON UPDATE CASCADE');
    ensure_foreign_key($connection, 'job_attachments', 'fk_job_attachments_uploaded_by_user', 'ALTER TABLE job_attachments ADD CONSTRAINT fk_job_attachments_uploaded_by_user FOREIGN KEY (uploaded_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE');
}

function ensure_job_photos_table(PDO $connection): void
{
    $connection->exec(
        "CREATE TABLE IF NOT EXISTS job_photos (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            company_id BIGINT UNSIGNED NOT NULL,
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
            KEY idx_job_photos_company_id (company_id),
            KEY idx_job_photos_job_id (job_id),
            KEY idx_job_photos_uploaded_by_user_id (uploaded_by_user_id),
            CONSTRAINT fk_job_photos_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_job_photos_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_job_photos_uploaded_by_user FOREIGN KEY (uploaded_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    ensure_column($connection, 'job_photos', 'stored_filename', 'ALTER TABLE job_photos ADD COLUMN stored_filename VARCHAR(255) NOT NULL AFTER original_filename');
    ensure_column($connection, 'job_photos', 'storage_path', 'ALTER TABLE job_photos ADD COLUMN storage_path VARCHAR(1000) NOT NULL AFTER stored_filename');
    ensure_column($connection, 'job_photos', 'mime_type', 'ALTER TABLE job_photos ADD COLUMN mime_type VARCHAR(255) NOT NULL AFTER storage_path');
    ensure_column($connection, 'job_photos', 'file_size', 'ALTER TABLE job_photos ADD COLUMN file_size BIGINT UNSIGNED NOT NULL AFTER mime_type');
    ensure_column($connection, 'job_photos', 'caption', 'ALTER TABLE job_photos ADD COLUMN caption VARCHAR(255) DEFAULT NULL AFTER file_size');
    ensure_column($connection, 'job_photos', 'uploaded_by_user_id', 'ALTER TABLE job_photos ADD COLUMN uploaded_by_user_id BIGINT UNSIGNED DEFAULT NULL AFTER caption');
    ensure_column($connection, 'job_photos', 'uploaded_at', 'ALTER TABLE job_photos ADD COLUMN uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER uploaded_by_user_id');

    ensure_index($connection, 'job_photos', 'idx_job_photos_job_id', 'ALTER TABLE job_photos ADD KEY idx_job_photos_job_id (job_id)');
    ensure_index($connection, 'job_photos', 'idx_job_photos_uploaded_by_user_id', 'ALTER TABLE job_photos ADD KEY idx_job_photos_uploaded_by_user_id (uploaded_by_user_id)');
    ensure_foreign_key($connection, 'job_photos', 'fk_job_photos_job', 'ALTER TABLE job_photos ADD CONSTRAINT fk_job_photos_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE RESTRICT ON UPDATE CASCADE');
    ensure_foreign_key($connection, 'job_photos', 'fk_job_photos_uploaded_by_user', 'ALTER TABLE job_photos ADD CONSTRAINT fk_job_photos_uploaded_by_user FOREIGN KEY (uploaded_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE');
}

function ensure_job_customer_confirmations_table(PDO $connection): void
{
    $connection->exec(
        "CREATE TABLE IF NOT EXISTS job_customer_confirmations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            company_id BIGINT UNSIGNED NOT NULL,
            job_id BIGINT UNSIGNED NOT NULL,
            customer_name VARCHAR(255) NOT NULL,
            customer_email VARCHAR(255) DEFAULT NULL,
            signature_path VARCHAR(1000) NOT NULL,
            signature_mime_type VARCHAR(100) NOT NULL DEFAULT 'image/png',
            signature_file_size BIGINT UNSIGNED NOT NULL,
            confirmed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            confirmed_by_user_id BIGINT UNSIGNED DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_job_customer_confirmations_job_id (job_id),
            KEY idx_job_customer_confirmations_company_id (company_id),
            KEY idx_job_customer_confirmations_confirmed_by_user_id (confirmed_by_user_id),
            KEY idx_job_customer_confirmations_confirmed_at (confirmed_at),
            CONSTRAINT fk_job_customer_confirmations_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_job_customer_confirmations_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_job_customer_confirmations_confirmed_by_user FOREIGN KEY (confirmed_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    ensure_column($connection, 'job_customer_confirmations', 'job_id', 'ALTER TABLE job_customer_confirmations ADD COLUMN job_id BIGINT UNSIGNED NOT NULL AFTER id');
    ensure_column($connection, 'job_customer_confirmations', 'customer_name', 'ALTER TABLE job_customer_confirmations ADD COLUMN customer_name VARCHAR(255) NOT NULL AFTER job_id');
    ensure_column($connection, 'job_customer_confirmations', 'customer_email', 'ALTER TABLE job_customer_confirmations ADD COLUMN customer_email VARCHAR(255) DEFAULT NULL AFTER customer_name');
    ensure_column($connection, 'job_customer_confirmations', 'signature_path', 'ALTER TABLE job_customer_confirmations ADD COLUMN signature_path VARCHAR(1000) NOT NULL AFTER customer_email');
    ensure_column($connection, 'job_customer_confirmations', 'signature_mime_type', "ALTER TABLE job_customer_confirmations ADD COLUMN signature_mime_type VARCHAR(100) NOT NULL DEFAULT 'image/png' AFTER signature_path");
    ensure_column($connection, 'job_customer_confirmations', 'signature_file_size', 'ALTER TABLE job_customer_confirmations ADD COLUMN signature_file_size BIGINT UNSIGNED NOT NULL AFTER signature_mime_type');
    ensure_column($connection, 'job_customer_confirmations', 'confirmed_at', 'ALTER TABLE job_customer_confirmations ADD COLUMN confirmed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER signature_file_size');
    ensure_column($connection, 'job_customer_confirmations', 'confirmed_by_user_id', 'ALTER TABLE job_customer_confirmations ADD COLUMN confirmed_by_user_id BIGINT UNSIGNED DEFAULT NULL AFTER confirmed_at');
    ensure_column($connection, 'job_customer_confirmations', 'created_at', 'ALTER TABLE job_customer_confirmations ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER confirmed_by_user_id');
    ensure_column($connection, 'job_customer_confirmations', 'updated_at', 'ALTER TABLE job_customer_confirmations ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at');

    ensure_index($connection, 'job_customer_confirmations', 'uq_job_customer_confirmations_job_id', 'ALTER TABLE job_customer_confirmations ADD UNIQUE KEY uq_job_customer_confirmations_job_id (job_id)');
    ensure_index($connection, 'job_customer_confirmations', 'idx_job_customer_confirmations_confirmed_by_user_id', 'ALTER TABLE job_customer_confirmations ADD KEY idx_job_customer_confirmations_confirmed_by_user_id (confirmed_by_user_id)');
    ensure_index($connection, 'job_customer_confirmations', 'idx_job_customer_confirmations_confirmed_at', 'ALTER TABLE job_customer_confirmations ADD KEY idx_job_customer_confirmations_confirmed_at (confirmed_at)');
    ensure_foreign_key($connection, 'job_customer_confirmations', 'fk_job_customer_confirmations_job', 'ALTER TABLE job_customer_confirmations ADD CONSTRAINT fk_job_customer_confirmations_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE RESTRICT ON UPDATE CASCADE');
    ensure_foreign_key($connection, 'job_customer_confirmations', 'fk_job_customer_confirmations_confirmed_by_user', 'ALTER TABLE job_customer_confirmations ADD CONSTRAINT fk_job_customer_confirmations_confirmed_by_user FOREIGN KEY (confirmed_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE');
}

function ensure_materials_table(PDO $connection): void
{
    $connection->exec(
        "CREATE TABLE IF NOT EXISTS materials (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            company_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            sku VARCHAR(100) DEFAULT NULL,
            unit VARCHAR(50) NOT NULL,
            description TEXT DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_materials_company_id (company_id),
            KEY idx_materials_name (name),
            KEY idx_materials_sku (sku),
            KEY idx_materials_is_active (is_active),
            CONSTRAINT fk_materials_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    ensure_column($connection, 'materials', 'name', 'ALTER TABLE materials ADD COLUMN name VARCHAR(255) NOT NULL AFTER id');
    ensure_column($connection, 'materials', 'sku', 'ALTER TABLE materials ADD COLUMN sku VARCHAR(100) DEFAULT NULL AFTER name');
    ensure_column($connection, 'materials', 'unit', 'ALTER TABLE materials ADD COLUMN unit VARCHAR(50) NOT NULL AFTER sku');
    ensure_column($connection, 'materials', 'description', 'ALTER TABLE materials ADD COLUMN description TEXT DEFAULT NULL AFTER unit');
    ensure_column($connection, 'materials', 'is_active', 'ALTER TABLE materials ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER description');
    ensure_column($connection, 'materials', 'created_at', 'ALTER TABLE materials ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER is_active');
    ensure_column($connection, 'materials', 'updated_at', 'ALTER TABLE materials ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at');

    ensure_index($connection, 'materials', 'idx_materials_name', 'ALTER TABLE materials ADD KEY idx_materials_name (name)');
    ensure_index($connection, 'materials', 'idx_materials_sku', 'ALTER TABLE materials ADD KEY idx_materials_sku (sku)');
    ensure_index($connection, 'materials', 'idx_materials_is_active', 'ALTER TABLE materials ADD KEY idx_materials_is_active (is_active)');
}

function ensure_material_movements_table(PDO $connection): void
{
    $connection->exec(
        "CREATE TABLE IF NOT EXISTS material_movements (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            company_id BIGINT UNSIGNED NOT NULL,
            material_id BIGINT UNSIGNED NOT NULL,
            movement_type ENUM('in','out') NOT NULL,
            quantity DECIMAL(14,3) NOT NULL,
            job_id BIGINT UNSIGNED DEFAULT NULL,
            job_material_id BIGINT UNSIGNED DEFAULT NULL,
            created_by_user_id BIGINT UNSIGNED DEFAULT NULL,
            note TEXT DEFAULT NULL,
            occurred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_material_movements_job_material_id (job_material_id),
            KEY idx_material_movements_company_material_occurred_at (company_id, material_id, occurred_at),
            KEY idx_material_movements_company_job_id (company_id, job_id),
            KEY idx_material_movements_company_occurred_at (company_id, occurred_at),
            KEY idx_material_movements_created_by_user_id (created_by_user_id),
            CONSTRAINT fk_material_movements_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_material_movements_material FOREIGN KEY (material_id) REFERENCES materials (id) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_material_movements_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_material_movements_created_by_user FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    ensure_index($connection, 'material_movements', 'uq_material_movements_job_material_id', 'ALTER TABLE material_movements ADD UNIQUE KEY uq_material_movements_job_material_id (job_material_id)');
    ensure_index($connection, 'material_movements', 'idx_material_movements_company_material_occurred_at', 'ALTER TABLE material_movements ADD KEY idx_material_movements_company_material_occurred_at (company_id, material_id, occurred_at)');
    ensure_index($connection, 'material_movements', 'idx_material_movements_company_job_id', 'ALTER TABLE material_movements ADD KEY idx_material_movements_company_job_id (company_id, job_id)');
    ensure_index($connection, 'material_movements', 'idx_material_movements_company_occurred_at', 'ALTER TABLE material_movements ADD KEY idx_material_movements_company_occurred_at (company_id, occurred_at)');
    ensure_index($connection, 'material_movements', 'idx_material_movements_created_by_user_id', 'ALTER TABLE material_movements ADD KEY idx_material_movements_created_by_user_id (created_by_user_id)');
    ensure_foreign_key($connection, 'material_movements', 'fk_material_movements_company', 'ALTER TABLE material_movements ADD CONSTRAINT fk_material_movements_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE RESTRICT ON UPDATE CASCADE');
    ensure_foreign_key($connection, 'material_movements', 'fk_material_movements_material', 'ALTER TABLE material_movements ADD CONSTRAINT fk_material_movements_material FOREIGN KEY (material_id) REFERENCES materials (id) ON DELETE RESTRICT ON UPDATE CASCADE');
    ensure_foreign_key($connection, 'material_movements', 'fk_material_movements_job', 'ALTER TABLE material_movements ADD CONSTRAINT fk_material_movements_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE RESTRICT ON UPDATE CASCADE');
    ensure_foreign_key($connection, 'material_movements', 'fk_material_movements_created_by_user', 'ALTER TABLE material_movements ADD CONSTRAINT fk_material_movements_created_by_user FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE');
}

function ensure_material_inventories_table(PDO $connection): void
{
    $connection->exec(
        "CREATE TABLE IF NOT EXISTS material_inventories (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            company_id BIGINT UNSIGNED NOT NULL,
            status ENUM('draft','pending_approval','approved','cancelled') NOT NULL DEFAULT 'draft',
            started_by_user_id BIGINT UNSIGNED NOT NULL,
            submitted_by_user_id BIGINT UNSIGNED DEFAULT NULL,
            approved_by_user_id BIGINT UNSIGNED DEFAULT NULL,
            started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            submitted_at TIMESTAMP NULL DEFAULT NULL,
            approved_at TIMESTAMP NULL DEFAULT NULL,
            note TEXT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_material_inventories_company_status (company_id, status),
            KEY idx_material_inventories_started_by_user_id (started_by_user_id),
            KEY idx_material_inventories_submitted_by_user_id (submitted_by_user_id),
            KEY idx_material_inventories_approved_by_user_id (approved_by_user_id),
            CONSTRAINT fk_material_inventories_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_material_inventories_started_by_user FOREIGN KEY (started_by_user_id) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_material_inventories_submitted_by_user FOREIGN KEY (submitted_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT fk_material_inventories_approved_by_user FOREIGN KEY (approved_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    ensure_index($connection, 'material_inventories', 'idx_material_inventories_company_status', 'ALTER TABLE material_inventories ADD KEY idx_material_inventories_company_status (company_id, status)');
    ensure_index($connection, 'material_inventories', 'idx_material_inventories_started_by_user_id', 'ALTER TABLE material_inventories ADD KEY idx_material_inventories_started_by_user_id (started_by_user_id)');
    ensure_index($connection, 'material_inventories', 'idx_material_inventories_submitted_by_user_id', 'ALTER TABLE material_inventories ADD KEY idx_material_inventories_submitted_by_user_id (submitted_by_user_id)');
    ensure_index($connection, 'material_inventories', 'idx_material_inventories_approved_by_user_id', 'ALTER TABLE material_inventories ADD KEY idx_material_inventories_approved_by_user_id (approved_by_user_id)');
    ensure_foreign_key($connection, 'material_inventories', 'fk_material_inventories_company', 'ALTER TABLE material_inventories ADD CONSTRAINT fk_material_inventories_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE RESTRICT ON UPDATE CASCADE');
    ensure_foreign_key($connection, 'material_inventories', 'fk_material_inventories_started_by_user', 'ALTER TABLE material_inventories ADD CONSTRAINT fk_material_inventories_started_by_user FOREIGN KEY (started_by_user_id) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE');
    ensure_foreign_key($connection, 'material_inventories', 'fk_material_inventories_submitted_by_user', 'ALTER TABLE material_inventories ADD CONSTRAINT fk_material_inventories_submitted_by_user FOREIGN KEY (submitted_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE');
    ensure_foreign_key($connection, 'material_inventories', 'fk_material_inventories_approved_by_user', 'ALTER TABLE material_inventories ADD CONSTRAINT fk_material_inventories_approved_by_user FOREIGN KEY (approved_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE');
}

function ensure_material_inventory_lines_table(PDO $connection): void
{
    $connection->exec(
        "CREATE TABLE IF NOT EXISTS material_inventory_lines (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            inventory_id BIGINT UNSIGNED NOT NULL,
            company_id BIGINT UNSIGNED NOT NULL,
            material_id BIGINT UNSIGNED NOT NULL,
            counted_quantity DECIMAL(14,3) DEFAULT NULL,
            system_quantity_at_start DECIMAL(14,3) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_material_inventory_lines_inventory_material (inventory_id, material_id),
            KEY idx_material_inventory_lines_company_inventory (company_id, inventory_id),
            KEY idx_material_inventory_lines_company_material (company_id, material_id),
            CONSTRAINT fk_material_inventory_lines_inventory FOREIGN KEY (inventory_id) REFERENCES material_inventories (id) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_material_inventory_lines_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_material_inventory_lines_material FOREIGN KEY (material_id) REFERENCES materials (id) ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    ensure_index($connection, 'material_inventory_lines', 'uq_material_inventory_lines_inventory_material', 'ALTER TABLE material_inventory_lines ADD UNIQUE KEY uq_material_inventory_lines_inventory_material (inventory_id, material_id)');
    ensure_index($connection, 'material_inventory_lines', 'idx_material_inventory_lines_company_inventory', 'ALTER TABLE material_inventory_lines ADD KEY idx_material_inventory_lines_company_inventory (company_id, inventory_id)');
    ensure_index($connection, 'material_inventory_lines', 'idx_material_inventory_lines_company_material', 'ALTER TABLE material_inventory_lines ADD KEY idx_material_inventory_lines_company_material (company_id, material_id)');
    ensure_foreign_key($connection, 'material_inventory_lines', 'fk_material_inventory_lines_inventory', 'ALTER TABLE material_inventory_lines ADD CONSTRAINT fk_material_inventory_lines_inventory FOREIGN KEY (inventory_id) REFERENCES material_inventories (id) ON DELETE RESTRICT ON UPDATE CASCADE');
    ensure_foreign_key($connection, 'material_inventory_lines', 'fk_material_inventory_lines_company', 'ALTER TABLE material_inventory_lines ADD CONSTRAINT fk_material_inventory_lines_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE RESTRICT ON UPDATE CASCADE');
    ensure_foreign_key($connection, 'material_inventory_lines', 'fk_material_inventory_lines_material', 'ALTER TABLE material_inventory_lines ADD CONSTRAINT fk_material_inventory_lines_material FOREIGN KEY (material_id) REFERENCES materials (id) ON DELETE RESTRICT ON UPDATE CASCADE');
}

function ensure_job_materials_table(PDO $connection): void
{
    $connection->exec(
        "CREATE TABLE IF NOT EXISTS job_materials (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            company_id BIGINT UNSIGNED NOT NULL,
            job_id BIGINT UNSIGNED NOT NULL,
            material_id BIGINT UNSIGNED NOT NULL,
            movement_id BIGINT UNSIGNED DEFAULT NULL,
            entry_type ENUM('used','returned') NOT NULL DEFAULT 'used',
            quantity DECIMAL(14,3) NOT NULL,
            recorded_by_user_id BIGINT UNSIGNED DEFAULT NULL,
            occurred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_job_materials_company_id (company_id),
            KEY idx_job_materials_job_id (job_id),
            KEY idx_job_materials_material_id (material_id),
            KEY idx_job_materials_movement_id (movement_id),
            KEY idx_job_materials_recorded_by_user_id (recorded_by_user_id),
            CONSTRAINT fk_job_materials_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_job_materials_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_job_materials_material FOREIGN KEY (material_id) REFERENCES materials (id) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_job_materials_movement FOREIGN KEY (movement_id) REFERENCES material_movements (id) ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT fk_job_materials_recorded_by_user FOREIGN KEY (recorded_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    ensure_column($connection, 'job_materials', 'job_id', 'ALTER TABLE job_materials ADD COLUMN job_id BIGINT UNSIGNED NOT NULL AFTER id');
    ensure_column($connection, 'job_materials', 'material_id', 'ALTER TABLE job_materials ADD COLUMN material_id BIGINT UNSIGNED NOT NULL AFTER job_id');
    ensure_column($connection, 'job_materials', 'movement_id', 'ALTER TABLE job_materials ADD COLUMN movement_id BIGINT UNSIGNED DEFAULT NULL AFTER material_id');
    ensure_column($connection, 'job_materials', 'entry_type', "ALTER TABLE job_materials ADD COLUMN entry_type ENUM('used','returned') NOT NULL DEFAULT 'used' AFTER movement_id");
    ensure_column($connection, 'job_materials', 'quantity', 'ALTER TABLE job_materials ADD COLUMN quantity DECIMAL(14,3) NOT NULL AFTER entry_type');
    ensure_column($connection, 'job_materials', 'recorded_by_user_id', 'ALTER TABLE job_materials ADD COLUMN recorded_by_user_id BIGINT UNSIGNED DEFAULT NULL AFTER quantity');
    ensure_column($connection, 'job_materials', 'occurred_at', 'ALTER TABLE job_materials ADD COLUMN occurred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER recorded_by_user_id');
    ensure_column($connection, 'job_materials', 'created_at', 'ALTER TABLE job_materials ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER occurred_at');
    ensure_column($connection, 'job_materials', 'updated_at', 'ALTER TABLE job_materials ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at');

    ensure_index($connection, 'job_materials', 'idx_job_materials_job_id', 'ALTER TABLE job_materials ADD KEY idx_job_materials_job_id (job_id)');
    ensure_index($connection, 'job_materials', 'idx_job_materials_material_id', 'ALTER TABLE job_materials ADD KEY idx_job_materials_material_id (material_id)');
    ensure_index($connection, 'job_materials', 'idx_job_materials_movement_id', 'ALTER TABLE job_materials ADD KEY idx_job_materials_movement_id (movement_id)');
    ensure_index($connection, 'job_materials', 'idx_job_materials_recorded_by_user_id', 'ALTER TABLE job_materials ADD KEY idx_job_materials_recorded_by_user_id (recorded_by_user_id)');
    ensure_foreign_key($connection, 'job_materials', 'fk_job_materials_job', 'ALTER TABLE job_materials ADD CONSTRAINT fk_job_materials_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE RESTRICT ON UPDATE CASCADE');
    ensure_foreign_key($connection, 'job_materials', 'fk_job_materials_material', 'ALTER TABLE job_materials ADD CONSTRAINT fk_job_materials_material FOREIGN KEY (material_id) REFERENCES materials (id) ON DELETE RESTRICT ON UPDATE CASCADE');
    ensure_foreign_key($connection, 'job_materials', 'fk_job_materials_movement', 'ALTER TABLE job_materials ADD CONSTRAINT fk_job_materials_movement FOREIGN KEY (movement_id) REFERENCES material_movements (id) ON DELETE SET NULL ON UPDATE CASCADE');
    ensure_foreign_key($connection, 'job_materials', 'fk_job_materials_recorded_by_user', 'ALTER TABLE job_materials ADD CONSTRAINT fk_job_materials_recorded_by_user FOREIGN KEY (recorded_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE');
}

function migrate_existing_job_material_movements(PDO $connection): void
{
    if (!table_exists($connection, 'job_materials') || !table_exists($connection, 'material_movements')) {
        return;
    }

    $statement = $connection->query(
        "SELECT id, company_id, job_id, material_id, movement_id, entry_type, quantity, recorded_by_user_id, COALESCE(occurred_at, updated_at, created_at, CURRENT_TIMESTAMP) AS occurred_at
         FROM job_materials
         WHERE movement_id IS NULL
         ORDER BY id ASC"
    );
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

    if (!is_array($rows) || $rows === []) {
        return;
    }

    $insertMovement = $connection->prepare(
        "INSERT INTO material_movements (
            company_id,
            material_id,
            movement_type,
            quantity,
            job_id,
            job_material_id,
            created_by_user_id,
            note,
            occurred_at,
            created_at
        ) VALUES (
            :company_id,
            :material_id,
            'out',
            :quantity,
            :job_id,
            :job_material_id,
            :created_by_user_id,
            NULL,
            :occurred_at,
            :created_at
        )"
    );
    $updateJobMaterial = $connection->prepare(
        'UPDATE job_materials
         SET movement_id = :movement_id,
             entry_type = :entry_type,
             occurred_at = :occurred_at
         WHERE id = :id'
    );

    foreach ($rows as $row) {
        $existingMovement = $connection->prepare(
            'SELECT id
             FROM material_movements
             WHERE job_material_id = :job_material_id
             LIMIT 1'
        );
        $existingMovement->execute(['job_material_id' => (int) $row['id']]);
        $movementId = $existingMovement->fetchColumn();

        if ($movementId === false) {
            $insertMovement->execute([
                'company_id' => (int) $row['company_id'],
                'material_id' => (int) $row['material_id'],
                'quantity' => (string) $row['quantity'],
                'job_id' => (int) $row['job_id'],
                'job_material_id' => (int) $row['id'],
                'created_by_user_id' => $row['recorded_by_user_id'] !== null ? (int) $row['recorded_by_user_id'] : null,
                'occurred_at' => (string) $row['occurred_at'],
                'created_at' => (string) $row['occurred_at'],
            ]);
            $movementId = (int) $connection->lastInsertId();
        }

        $movementTypeStatement = $connection->prepare(
            'SELECT movement_type
             FROM material_movements
             WHERE id = :id
             LIMIT 1'
        );
        $movementTypeStatement->execute(['id' => (int) $movementId]);
        $movementType = (string) ($movementTypeStatement->fetchColumn() ?: 'out');

        $updateJobMaterial->execute([
            'movement_id' => (int) $movementId,
            'entry_type' => $movementType === 'in' ? 'returned' : 'used',
            'occurred_at' => (string) $row['occurred_at'],
            'id' => (int) $row['id'],
        ]);
    }
}

function users_role_supports_super_admin(PDO $connection): bool
{
    $statement = $connection->query("SHOW COLUMNS FROM users LIKE 'role'");
    $column = $statement->fetch(PDO::FETCH_ASSOC);

    return is_array($column) && str_contains((string) ($column['Type'] ?? ''), "'super_admin'");
}

function table_exists(PDO $connection, string $table): bool
{
    $statement = $connection->prepare(
        'SELECT 1
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
         LIMIT 1'
    );
    $statement->execute(['table_name' => $table]);

    return $statement->fetchColumn() !== false;
}

function column_exists(PDO $connection, string $table, string $column): bool
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

    return $statement->fetchColumn() !== false;
}

function ensure_column(PDO $connection, string $table, string $column, string $sql): void
{
    if (!column_exists($connection, $table, $column)) {
        $connection->exec($sql);
    }
}

function ensure_index(PDO $connection, string $table, string $index, string $sql): void
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

function ensure_index_absent(PDO $connection, string $table, string $index, string $sql): void
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

    if ($statement->fetchColumn() !== false) {
        $connection->exec($sql);
    }
}

function ensure_foreign_key(PDO $connection, string $table, string $constraint, string $sql): void
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
