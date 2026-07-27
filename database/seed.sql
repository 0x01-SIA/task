INSERT INTO users (name, email, password_hash, role, is_active)
VALUES
    (
        'Admin User',
        'admin@example.test',
        '$2y$12$z/YuT0KPVQtVqXu/6DH/Fut8sthjnPZbakLl6VQAwoneW1aLpmojK',
        'admin',
        1
    ),
    (
        'Dispatcher User',
        'dispatcher@example.test',
        '$2y$12$z/YuT0KPVQtVqXu/6DH/Fut8sthjnPZbakLl6VQAwoneW1aLpmojK',
        'dispatcher',
        1
    ),
    (
        'Worker User',
        'worker@example.test',
        '$2y$12$z/YuT0KPVQtVqXu/6DH/Fut8sthjnPZbakLl6VQAwoneW1aLpmojK',
        'worker',
        1
    ),
    (
        'Worker Two',
        'worker.two@example.test',
        '$2y$12$z/YuT0KPVQtVqXu/6DH/Fut8sthjnPZbakLl6VQAwoneW1aLpmojK',
        'worker',
        1
    ),
    (
        'Inactive Worker',
        'worker.inactive@example.test',
        '$2y$12$z/YuT0KPVQtVqXu/6DH/Fut8sthjnPZbakLl6VQAwoneW1aLpmojK',
        'worker',
        0
    );

INSERT INTO customers (
    name,
    registration_number,
    contact_name,
    contact_email,
    contact_phone,
    notes,
    is_active
)
VALUES
    (
        'Northwind Facilities',
        'REG-2026-001',
        'Nina North',
        'nina.north@example.test',
        '+371-555-0101',
        'Sample customer for local development only.',
        1
    ),
    (
        'Bluebird Manufacturing',
        'REG-2026-002',
        'Ben Bluebird',
        'ben.bluebird@example.test',
        '+371-555-0103',
        'Second sample customer for local development only.',
        1
    );

INSERT INTO locations (
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
    (
        1,
        'Northwind Warehouse',
        '12 Harbor Street',
        'Riga',
        'LV-1010',
        'Latvia',
        'Site Supervisor',
        '+371-555-0102',
        'Primary sample service location.',
        1
    ),
    (
        1,
        'Northwind Office',
        '45 Central Avenue',
        'Riga',
        'LV-1011',
        'Latvia',
        'Office Manager',
        '+371-555-0104',
        'Secondary Northwind location.',
        1
    ),
    (
        2,
        'Bluebird Plant',
        '8 Foundry Road',
        'Jelgava',
        'LV-3001',
        'Latvia',
        'Plant Foreman',
        '+371-555-0105',
        'Manufacturing plant service location.',
        1
    );

INSERT INTO tasks (
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
    (
        'TASK-000001',
        1,
        1,
        'Prepare warehouse loading dock',
        'Customer requested an inspection and a follow-up service visit for the loading dock.',
        'planned',
        'high',
        '2026-07-20',
        '2026-07-28',
        1
    ),
    (
        'TASK-000002',
        2,
        3,
        'Inspect conveyor controls',
        'Bluebird requested a planned inspection and one deferred repair visit.',
        'planned',
        'normal',
        '2026-07-22',
        '2026-07-30',
        2
    );

INSERT INTO jobs (
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
    (
        'JOB-000001',
        1,
        1,
        1,
        'Inspect dock leveler',
        'Check safety interlocks and general wear before service work.',
        'inspection',
        'planned',
        'high',
        3,
        CURRENT_DATE(),
        '09:00:00',
        60,
        NULL,
        NULL,
        'Bring inspection checklist.',
        2
    ),
    (
        'JOB-000002',
        1,
        1,
        2,
        'Deliver access control parts',
        'Drop off the replacement access control parts at the office reception desk.',
        'delivery',
        'draft',
        'normal',
        NULL,
        DATE_ADD(CURRENT_DATE(), INTERVAL 1 DAY),
        '13:30:00',
        120,
        NULL,
        NULL,
        'Leave unassigned until the delivery window is confirmed.',
        2
    ),
    (
        'JOB-000003',
        1,
        1,
        2,
        'Replace office keypad',
        'Install the new keypad and test badge access before leaving site.',
        'installation',
        'in_progress',
        'urgent',
        3,
        CURRENT_DATE(),
        '11:00:00',
        90,
        CURRENT_TIMESTAMP(),
        NULL,
        'Customer approved same-day replacement.',
        2
    ),
    (
        'JOB-000004',
        2,
        2,
        3,
        'Complete conveyor inspection report',
        'Finish the post-visit report and confirm the repaired conveyor is back online.',
        'inspection',
        'completed',
        'normal',
        3,
        CURRENT_DATE(),
        '10:00:00',
        45,
        TIMESTAMP(CURRENT_DATE(), '10:02:00'),
        TIMESTAMP(CURRENT_DATE(), '10:41:00'),
        'Upload the signed inspection sheet after the visit.',
        2
    ),
    (
        'JOB-000005',
        2,
        2,
        3,
        'Replace worn conveyor sensor',
        'Repair visit postponed by customer after the initial assessment.',
        'repair',
        'cancelled',
        'urgent',
        3,
        DATE_SUB(CURRENT_DATE(), INTERVAL 1 DAY),
        '14:30:00',
        90,
        NULL,
        NULL,
        'Cancelled after customer requested a reschedule.',
        2
    ),
    (
        'JOB-000006',
        2,
        3,
        'Inspect loading conveyor motor',
        'Assigned to another worker and used to verify access restrictions.',
        'inspection',
        'planned',
        'high',
        4,
        DATE_ADD(CURRENT_DATE(), INTERVAL 1 DAY),
        '15:00:00',
        75,
        NULL,
        NULL,
        'Verify belt tension during the visit.',
        2
    ),
    (
        'JOB-000007',
        2,
        3,
        'Review emergency stop fault',
        'This planned job is intentionally overdue so the operations dashboard has an incomplete past-due item.',
        'repair',
        'planned',
        'urgent',
        NULL,
        DATE_SUB(CURRENT_DATE(), INTERVAL 2 DAY),
        '08:30:00',
        90,
        NULL,
        NULL,
        'Dispatch to the next available worker after triage.',
        2
    );

INSERT INTO job_notes (job_id, user_id, note)
VALUES
    (
        1,
        3,
        'Initial note added for the sample inspection job.'
    ),
    (
        3,
        3,
        'Worker confirmed the old keypad has been removed and wiring is ready.'
    ),
    (
        4,
        3,
        'Completed functional testing and reported the result to dispatch.'
    ),
    (
        5,
        3,
        'Customer asked to move the cancelled visit to next week once new parts arrive.'
    ),
    (
        6,
        4,
        'Secondary worker assignment used for permission testing.'
    ),
    (
        7,
        2,
        'Dispatcher flagged this overdue job for follow-up.'
    );
