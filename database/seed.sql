INSERT INTO companies (name, registration_number, email, phone, address, is_active)
VALUES
    ('Northwind Services', 'REG-2026-001', 'ops@northwind.example.test', '+371-555-0100', '12 Harbor Street, Riga, Latvia', 1),
    ('Bluebird Manufacturing', 'REG-2026-002', 'hello@bluebird.example.test', '+371-555-0200', '8 Foundry Road, Jelgava, Latvia', 1),
    ('Dormant Demo Company', 'REG-2026-003', 'inactive@example.test', '+371-555-0300', '5 Quiet Lane, Liepaja, Latvia', 0);

INSERT INTO users (name, email, password_hash, role, is_active)
VALUES
    ('Super Admin', 'superadmin@example.test', '$2y$12$z/YuT0KPVQtVqXu/6DH/Fut8sthjnPZbakLl6VQAwoneW1aLpmojK', 'super_admin', 1),
    ('Northwind Admin', 'admin@example.test', '$2y$12$z/YuT0KPVQtVqXu/6DH/Fut8sthjnPZbakLl6VQAwoneW1aLpmojK', 'worker', 1),
    ('Dispatcher User', 'dispatcher@example.test', '$2y$12$z/YuT0KPVQtVqXu/6DH/Fut8sthjnPZbakLl6VQAwoneW1aLpmojK', 'worker', 1),
    ('Worker User', 'worker@example.test', '$2y$12$z/YuT0KPVQtVqXu/6DH/Fut8sthjnPZbakLl6VQAwoneW1aLpmojK', 'worker', 1),
    ('Cross Company Worker', 'worker.two@example.test', '$2y$12$z/YuT0KPVQtVqXu/6DH/Fut8sthjnPZbakLl6VQAwoneW1aLpmojK', 'worker', 1),
    ('Inactive Worker', 'worker.inactive@example.test', '$2y$12$z/YuT0KPVQtVqXu/6DH/Fut8sthjnPZbakLl6VQAwoneW1aLpmojK', 'worker', 0);

INSERT INTO company_users (company_id, user_id, role, is_active)
VALUES
    (1, 2, 'admin', 1),
    (1, 3, 'dispatcher', 1),
    (1, 4, 'worker', 1),
    (1, 5, 'worker', 1),
    (2, 3, 'dispatcher', 1),
    (2, 5, 'worker', 1),
    (2, 6, 'worker', 0);

INSERT INTO customers (
    company_id,
    name,
    registration_number,
    contact_name,
    contact_email,
    contact_phone,
    notes,
    is_active
)
VALUES
    (1, 'Northwind Facilities', 'CUST-001', 'Nina North', 'nina.north@example.test', '+371-555-0101', 'Sample customer for Northwind.', 1),
    (2, 'Bluebird Production', 'CUST-002', 'Ben Bluebird', 'ben.bluebird@example.test', '+371-555-0201', 'Sample customer for Bluebird.', 1);

INSERT INTO locations (
    company_id,
    customer_id,
    name,
    address_line,
    city,
    postal_code,
    country,
    contact_name,
    contact_phone,
    notes,
    is_active
)
VALUES
    (1, 1, 'Northwind Warehouse', '12 Harbor Street', 'Riga', 'LV-1010', 'Latvia', 'Site Supervisor', '+371-555-0102', 'Primary demo location.', 1),
    (1, 1, 'Northwind Office', '45 Central Avenue', 'Riga', 'LV-1011', 'Latvia', 'Office Manager', '+371-555-0104', 'Secondary office.', 1),
    (2, 2, 'Bluebird Plant', '8 Foundry Road', 'Jelgava', 'LV-3001', 'Latvia', 'Plant Foreman', '+371-555-0202', 'Manufacturing plant.', 1);

INSERT INTO tasks (
    company_id,
    task_number,
    customer_id,
    location_id,
    title,
    description,
    status,
    priority,
    requested_date,
    due_date,
    created_by_user_id
)
VALUES
    (1, 'TASK-000001', 1, 1, 'Prepare warehouse loading dock', 'Inspection and service visit for the loading dock.', 'planned', 'high', '2026-07-20', '2026-07-28', 2),
    (2, 'TASK-000002', 2, 3, 'Inspect conveyor controls', 'Planned inspection and deferred repair visit.', 'planned', 'normal', '2026-07-22', '2026-07-30', 3);

INSERT INTO jobs (
    company_id,
    job_number,
    task_id,
    customer_id,
    location_id,
    title,
    description,
    job_type,
    status,
    priority,
    assigned_user_id,
    planned_date,
    planned_start_time,
    estimated_duration_minutes,
    actual_start_at,
    actual_completed_at,
    internal_notes,
    created_by_user_id
)
VALUES
    (1, 'JOB-000001', 1, 1, 1, 'Inspect dock leveler', 'Check safety interlocks and wear.', 'inspection', 'planned', 'high', 4, CURRENT_DATE(), '09:00:00', 60, NULL, NULL, 'Bring inspection checklist.', 3),
    (1, 'JOB-000002', 1, 1, 2, 'Replace office keypad', 'Install the new keypad and test access.', 'installation', 'in_progress', 'urgent', 4, CURRENT_DATE(), '11:00:00', 90, CURRENT_TIMESTAMP(), NULL, 'Customer approved same-day replacement.', 3),
    (2, 'JOB-000003', 2, 2, 3, 'Complete conveyor inspection report', 'Finish report and confirm repaired conveyor.', 'inspection', 'completed', 'normal', 5, CURRENT_DATE(), '10:00:00', 45, TIMESTAMP(CURRENT_DATE(), '10:02:00'), TIMESTAMP(CURRENT_DATE(), '10:41:00'), 'Upload signed inspection sheet.', 3);

INSERT INTO materials (
    company_id,
    name,
    sku,
    unit,
    description,
    is_active
)
VALUES
    (1, 'Access Control Keypad', 'MAT-001', 'pcs', 'Replacement keypad unit.', 1),
    (1, 'Mounting Kit', 'MAT-002', 'kit', 'Keypad mounting hardware.', 1),
    (2, 'Conveyor Sensor', 'MAT-003', 'pcs', 'Replacement sensor for conveyor line.', 1);

INSERT INTO job_materials (
    company_id,
    job_id,
    material_id,
    quantity,
    recorded_by_user_id
)
VALUES
    (1, 2, 1, 1.000, 4),
    (1, 2, 2, 1.000, 4),
    (2, 3, 3, 2.000, 5);

INSERT INTO job_notes (job_id, user_id, note)
VALUES
    (2, 4, 'Old keypad removed and wiring checked.'),
    (3, 5, 'Inspection completed and report is ready to send.');
