CREATE TABLE companies (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'admin', 'dispatcher', 'worker') NOT NULL DEFAULT 'worker',
    signature_path VARCHAR(1000) DEFAULT NULL,
    signature_mime_type VARCHAR(100) DEFAULT NULL,
    signature_file_size BIGINT UNSIGNED DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role),
    KEY idx_users_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE company_users (
    company_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role ENUM('admin', 'dispatcher', 'worker') NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (company_id, user_id),
    KEY idx_company_users_user_id (user_id),
    KEY idx_company_users_role (role),
    KEY idx_company_users_is_active (is_active),
    CONSTRAINT fk_company_users_company
        FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_company_users_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    registration_number VARCHAR(100) DEFAULT NULL,
    contact_name VARCHAR(255) DEFAULT NULL,
    contact_email VARCHAR(255) DEFAULT NULL,
    contact_phone VARCHAR(50) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_customers_id_company_id (id, company_id),
    KEY idx_customers_company_id (company_id),
    KEY idx_customers_name (name),
    CONSTRAINT fk_customers_company
        FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE locations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    address_line VARCHAR(255) DEFAULT NULL,
    city VARCHAR(100) DEFAULT NULL,
    postal_code VARCHAR(30) DEFAULT NULL,
    country VARCHAR(100) DEFAULT NULL,
    contact_name VARCHAR(255) DEFAULT NULL,
    contact_phone VARCHAR(50) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_locations_id_company_id (id, company_id),
    KEY idx_locations_company_id (company_id),
    KEY idx_locations_customer_id (customer_id),
    CONSTRAINT fk_locations_company
        FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_locations_customer
        FOREIGN KEY (customer_id) REFERENCES customers (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_locations_customer_company
        FOREIGN KEY (customer_id, company_id) REFERENCES customers (id, company_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tasks (
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
    UNIQUE KEY uq_tasks_id_company_id (id, company_id),
    KEY idx_tasks_company_id (company_id),
    KEY idx_tasks_customer_id (customer_id),
    KEY idx_tasks_location_id (location_id),
    KEY idx_tasks_created_by_user_id (created_by_user_id),
    KEY idx_tasks_status (status),
    KEY idx_tasks_priority (priority),
    KEY idx_tasks_due_date (due_date),
    CONSTRAINT fk_tasks_company
        FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_tasks_customer
        FOREIGN KEY (customer_id) REFERENCES customers (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_tasks_customer_company
        FOREIGN KEY (customer_id, company_id) REFERENCES customers (id, company_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_tasks_location
        FOREIGN KEY (location_id) REFERENCES locations (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_tasks_location_company
        FOREIGN KEY (location_id, company_id) REFERENCES locations (id, company_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_tasks_created_by_user
        FOREIGN KEY (created_by_user_id) REFERENCES users (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NOT NULL,
    job_number VARCHAR(50) NOT NULL,
    task_id BIGINT UNSIGNED DEFAULT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    location_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    job_type ENUM('installation', 'maintenance', 'repair', 'inspection', 'delivery', 'other') NOT NULL,
    status ENUM('draft', 'planned', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'draft',
    priority ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal',
    assigned_user_id BIGINT UNSIGNED DEFAULT NULL,
    planned_date DATE DEFAULT NULL,
    planned_start_time TIME DEFAULT NULL,
    estimated_duration_minutes INT UNSIGNED DEFAULT NULL,
    actual_start_at TIMESTAMP NULL DEFAULT NULL,
    actual_completed_at TIMESTAMP NULL DEFAULT NULL,
    internal_notes TEXT DEFAULT NULL,
    created_by_user_id BIGINT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_jobs_job_number (job_number),
    UNIQUE KEY uq_jobs_id_company_id (id, company_id),
    KEY idx_jobs_company_id (company_id),
    KEY idx_jobs_task_id (task_id),
    KEY idx_jobs_customer_id (customer_id),
    KEY idx_jobs_location_id (location_id),
    KEY idx_jobs_status (status),
    KEY idx_jobs_priority (priority),
    KEY idx_jobs_assigned_user_id (assigned_user_id),
    KEY idx_jobs_planned_date (planned_date),
    KEY idx_jobs_created_by_user_id (created_by_user_id),
    CONSTRAINT fk_jobs_company
        FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_jobs_task
        FOREIGN KEY (task_id) REFERENCES tasks (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_jobs_task_company
        FOREIGN KEY (task_id, company_id) REFERENCES tasks (id, company_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_jobs_customer
        FOREIGN KEY (customer_id) REFERENCES customers (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_jobs_customer_company
        FOREIGN KEY (customer_id, company_id) REFERENCES customers (id, company_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_jobs_location
        FOREIGN KEY (location_id) REFERENCES locations (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_jobs_location_company
        FOREIGN KEY (location_id, company_id) REFERENCES locations (id, company_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_jobs_assigned_user
        FOREIGN KEY (assigned_user_id) REFERENCES users (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    CONSTRAINT fk_jobs_created_by_user
        FOREIGN KEY (created_by_user_id) REFERENCES users (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE job_notes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NOT NULL,
    job_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED DEFAULT NULL,
    note TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_job_notes_id_company_id (id, company_id),
    KEY idx_job_notes_company_id (company_id),
    KEY idx_job_notes_job_id (job_id),
    KEY idx_job_notes_user_id (user_id),
    CONSTRAINT fk_job_notes_company
        FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_job_notes_job
        FOREIGN KEY (job_id) REFERENCES jobs (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_job_notes_job_company
        FOREIGN KEY (job_id, company_id) REFERENCES jobs (id, company_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_job_notes_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE job_attachments (
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
    UNIQUE KEY uq_job_attachments_id_company_id (id, company_id),
    KEY idx_job_attachments_company_id (company_id),
    KEY idx_job_attachments_job_id (job_id),
    KEY idx_job_attachments_uploaded_by_user_id (uploaded_by_user_id),
    CONSTRAINT fk_job_attachments_company
        FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_job_attachments_job
        FOREIGN KEY (job_id) REFERENCES jobs (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_job_attachments_job_company
        FOREIGN KEY (job_id, company_id) REFERENCES jobs (id, company_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_job_attachments_uploaded_by_user
        FOREIGN KEY (uploaded_by_user_id) REFERENCES users (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE job_photos (
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
    UNIQUE KEY uq_job_photos_id_company_id (id, company_id),
    KEY idx_job_photos_company_id (company_id),
    KEY idx_job_photos_job_id (job_id),
    KEY idx_job_photos_uploaded_by_user_id (uploaded_by_user_id),
    CONSTRAINT fk_job_photos_company
        FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_job_photos_job
        FOREIGN KEY (job_id) REFERENCES jobs (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_job_photos_job_company
        FOREIGN KEY (job_id, company_id) REFERENCES jobs (id, company_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_job_photos_uploaded_by_user
        FOREIGN KEY (uploaded_by_user_id) REFERENCES users (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE job_customer_confirmations (
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
    UNIQUE KEY uq_job_customer_confirmations_id_company_id (id, company_id),
    KEY idx_job_customer_confirmations_company_id (company_id),
    KEY idx_job_customer_confirmations_confirmed_by_user_id (confirmed_by_user_id),
    KEY idx_job_customer_confirmations_confirmed_at (confirmed_at),
    CONSTRAINT fk_job_customer_confirmations_company
        FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_job_customer_confirmations_job
        FOREIGN KEY (job_id) REFERENCES jobs (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_job_customer_confirmations_job_company
        FOREIGN KEY (job_id, company_id) REFERENCES jobs (id, company_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_job_customer_confirmations_confirmed_by_user
        FOREIGN KEY (confirmed_by_user_id) REFERENCES users (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE materials (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    sku VARCHAR(100) DEFAULT NULL,
    unit VARCHAR(50) NOT NULL,
    description TEXT DEFAULT NULL,
    is_device TINYINT(1) NOT NULL DEFAULT 0,
    is_device_accessory TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_materials_id_company_id (id, company_id),
    KEY idx_materials_company_id (company_id),
    KEY idx_materials_name (name),
    KEY idx_materials_sku (sku),
    KEY idx_materials_is_device (is_device),
    KEY idx_materials_is_device_accessory (is_device_accessory),
    KEY idx_materials_is_active (is_active),
    CONSTRAINT fk_materials_company
        FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE material_movements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NOT NULL,
    material_id BIGINT UNSIGNED NOT NULL,
    movement_type ENUM('in', 'out') NOT NULL,
    quantity DECIMAL(14,3) NOT NULL,
    job_id BIGINT UNSIGNED DEFAULT NULL,
    job_material_id BIGINT UNSIGNED DEFAULT NULL,
    created_by_user_id BIGINT UNSIGNED DEFAULT NULL,
    note TEXT DEFAULT NULL,
    occurred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_material_movements_job_material_id (job_material_id),
    UNIQUE KEY uq_material_movements_id_company_id (id, company_id),
    KEY idx_material_movements_company_material_occurred_at (company_id, material_id, occurred_at),
    KEY idx_material_movements_company_job_id (company_id, job_id),
    KEY idx_material_movements_company_occurred_at (company_id, occurred_at),
    KEY idx_material_movements_created_by_user_id (created_by_user_id),
    CONSTRAINT fk_material_movements_company
        FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_material_movements_material
        FOREIGN KEY (material_id) REFERENCES materials (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_material_movements_material_company
        FOREIGN KEY (material_id, company_id) REFERENCES materials (id, company_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_material_movements_job
        FOREIGN KEY (job_id) REFERENCES jobs (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_material_movements_job_company
        FOREIGN KEY (job_id, company_id) REFERENCES jobs (id, company_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_material_movements_created_by_user
        FOREIGN KEY (created_by_user_id) REFERENCES users (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE material_inventories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NOT NULL,
    status ENUM('draft', 'pending_approval', 'approved', 'cancelled') NOT NULL DEFAULT 'draft',
    started_by_user_id BIGINT UNSIGNED NOT NULL,
    submitted_by_user_id BIGINT UNSIGNED DEFAULT NULL,
    approved_by_user_id BIGINT UNSIGNED DEFAULT NULL,
    started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    submitted_at TIMESTAMP NULL DEFAULT NULL,
    effective_at TIMESTAMP NULL DEFAULT NULL,
    approved_at TIMESTAMP NULL DEFAULT NULL,
    note TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_material_inventories_id_company_id (id, company_id),
    KEY idx_material_inventories_company_status (company_id, status),
    KEY idx_material_inventories_started_by_user_id (started_by_user_id),
    KEY idx_material_inventories_submitted_by_user_id (submitted_by_user_id),
    KEY idx_material_inventories_approved_by_user_id (approved_by_user_id),
    CONSTRAINT fk_material_inventories_company
        FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_material_inventories_started_by_user
        FOREIGN KEY (started_by_user_id) REFERENCES users (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_material_inventories_submitted_by_user
        FOREIGN KEY (submitted_by_user_id) REFERENCES users (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    CONSTRAINT fk_material_inventories_approved_by_user
        FOREIGN KEY (approved_by_user_id) REFERENCES users (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE material_inventory_lines (
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
    UNIQUE KEY uq_material_inventory_lines_id_company_id (id, company_id),
    KEY idx_material_inventory_lines_company_inventory (company_id, inventory_id),
    KEY idx_material_inventory_lines_company_material (company_id, material_id),
    CONSTRAINT fk_material_inventory_lines_inventory
        FOREIGN KEY (inventory_id) REFERENCES material_inventories (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_material_inventory_lines_inventory_company
        FOREIGN KEY (inventory_id, company_id) REFERENCES material_inventories (id, company_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_material_inventory_lines_company
        FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_material_inventory_lines_material
        FOREIGN KEY (material_id) REFERENCES materials (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_material_inventory_lines_material_company
        FOREIGN KEY (material_id, company_id) REFERENCES materials (id, company_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE job_materials (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NOT NULL,
    job_id BIGINT UNSIGNED NOT NULL,
    material_id BIGINT UNSIGNED NOT NULL,
    movement_id BIGINT UNSIGNED DEFAULT NULL,
    entry_type ENUM('used', 'returned') NOT NULL DEFAULT 'used',
    quantity DECIMAL(14,3) NOT NULL,
    device_identifier VARCHAR(255) DEFAULT NULL,
    recorded_by_user_id BIGINT UNSIGNED DEFAULT NULL,
    occurred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_job_materials_id_company_id (id, company_id),
    KEY idx_job_materials_company_id (company_id),
    KEY idx_job_materials_job_id (job_id),
    KEY idx_job_materials_material_id (material_id),
    KEY idx_job_materials_movement_id (movement_id),
    KEY idx_job_materials_recorded_by_user_id (recorded_by_user_id),
    CONSTRAINT fk_job_materials_company
        FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_job_materials_job
        FOREIGN KEY (job_id) REFERENCES jobs (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_job_materials_job_company
        FOREIGN KEY (job_id, company_id) REFERENCES jobs (id, company_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_job_materials_material
        FOREIGN KEY (material_id) REFERENCES materials (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_job_materials_material_company
        FOREIGN KEY (material_id, company_id) REFERENCES materials (id, company_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_job_materials_movement
        FOREIGN KEY (movement_id) REFERENCES material_movements (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    CONSTRAINT fk_job_materials_recorded_by_user
        FOREIGN KEY (recorded_by_user_id) REFERENCES users (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE job_report_snapshots (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NOT NULL,
    job_id BIGINT UNSIGNED NOT NULL,
    snapshot_json LONGTEXT NOT NULL,
    report_version INT UNSIGNED NOT NULL DEFAULT 1,
    created_by_user_id BIGINT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_job_report_snapshots_job_id (job_id),
    UNIQUE KEY uq_job_report_snapshots_id_company_id (id, company_id),
    KEY idx_job_report_snapshots_company_id (company_id),
    KEY idx_job_report_snapshots_created_by_user_id (created_by_user_id),
    KEY idx_job_report_snapshots_job_company_id (job_id, company_id),
    CONSTRAINT fk_job_report_snapshots_company
        FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_job_report_snapshots_job
        FOREIGN KEY (job_id) REFERENCES jobs (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_job_report_snapshots_job_company
        FOREIGN KEY (job_id, company_id) REFERENCES jobs (id, company_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_job_report_snapshots_created_by_user
        FOREIGN KEY (created_by_user_id) REFERENCES users (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE device_installations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NOT NULL,
    job_id BIGINT UNSIGNED NOT NULL,
    device_material_usage_id BIGINT UNSIGNED NOT NULL,
    device_material_id BIGINT UNSIGNED NOT NULL,
    device_identifier VARCHAR(255) NOT NULL,
    object_name VARCHAR(255) NOT NULL,
    created_by BIGINT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_device_installations_usage_id (device_material_usage_id),
    UNIQUE KEY uq_device_installations_id_company_id (id, company_id),
    KEY idx_device_installations_company_job (company_id, job_id),
    KEY idx_device_installations_company_material (company_id, device_material_id),
    CONSTRAINT fk_device_installations_company
        FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_device_installations_job
        FOREIGN KEY (job_id) REFERENCES jobs (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_device_installations_job_company
        FOREIGN KEY (job_id, company_id) REFERENCES jobs (id, company_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_device_installations_usage
        FOREIGN KEY (device_material_usage_id) REFERENCES job_materials (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_device_installations_usage_company
        FOREIGN KEY (device_material_usage_id, company_id) REFERENCES job_materials (id, company_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_device_installations_material
        FOREIGN KEY (device_material_id) REFERENCES materials (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_device_installations_material_company
        FOREIGN KEY (device_material_id, company_id) REFERENCES materials (id, company_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_device_installations_created_by
        FOREIGN KEY (created_by) REFERENCES users (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE device_installation_accessories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NOT NULL,
    device_installation_id BIGINT UNSIGNED NOT NULL,
    accessory_material_id BIGINT UNSIGNED NOT NULL,
    accessory_material_usage_id BIGINT UNSIGNED NOT NULL,
    quantity DECIMAL(14,3) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_device_installation_accessories_installation_material (device_installation_id, accessory_material_id),
    UNIQUE KEY uq_device_installation_accessories_usage_id (accessory_material_usage_id),
    UNIQUE KEY uq_device_installation_accessories_id_company_id (id, company_id),
    KEY idx_device_installation_accessories_company_installation (company_id, device_installation_id),
    KEY idx_device_installation_accessories_company_material (company_id, accessory_material_id),
    CONSTRAINT fk_device_installation_accessories_company
        FOREIGN KEY (company_id) REFERENCES companies (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_device_installation_accessories_installation
        FOREIGN KEY (device_installation_id) REFERENCES device_installations (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_device_installation_accessories_installation_company
        FOREIGN KEY (device_installation_id, company_id) REFERENCES device_installations (id, company_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_device_installation_accessories_material
        FOREIGN KEY (accessory_material_id) REFERENCES materials (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_device_installation_accessories_material_company
        FOREIGN KEY (accessory_material_id, company_id) REFERENCES materials (id, company_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_device_installation_accessories_usage
        FOREIGN KEY (accessory_material_usage_id) REFERENCES job_materials (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_device_installation_accessories_usage_company
        FOREIGN KEY (accessory_material_usage_id, company_id) REFERENCES job_materials (id, company_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
