<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/helpers.php';
require base_path('app/database/connection.php');
require base_path('app/repositories/customers.php');
require base_path('app/repositories/dashboard.php');
require base_path('app/repositories/jobs.php');
require base_path('app/repositories/locations.php');
require base_path('app/repositories/users.php');

function not_found(string $resourceName): never
{
    abort(404, 'Page not found', $resourceName . ' could not be found.');
}

function location_form_values(array $source, ?int $defaultCustomerId = null): array
{
    $customerId = positive_int_or_null($source['customer_id'] ?? null);
    $isActive = $source['is_active'] ?? '1';

    return [
        'customer_id' => $customerId ?? $defaultCustomerId,
        'name' => trim((string) ($source['name'] ?? '')),
        'address' => trim((string) ($source['address'] ?? '')),
        'contact_name' => trim((string) ($source['contact_name'] ?? '')),
        'contact_phone' => trim((string) ($source['contact_phone'] ?? '')),
        'access_notes' => trim((string) ($source['access_notes'] ?? '')),
        'is_active' => $isActive === '0' ? '0' : '1',
    ];
}

function validate_location_form(array $values): array
{
    $errors = [];

    if (($values['customer_id'] ?? null) === null) {
        $errors['customer_id'] = 'Select a valid customer.';
    } elseif (!customer_exists((int) $values['customer_id'])) {
        $errors['customer_id'] = 'The selected customer was not found.';
    }

    if (($values['name'] ?? '') === '') {
        $errors['name'] = 'Location name is required.';
    }

    if (($values['address'] ?? '') === '') {
        $errors['address'] = 'Address is required.';
    }

    return $errors;
}

function save_location_payload(array $values): array
{
    return [
        'customer_id' => (int) $values['customer_id'],
        'name' => $values['name'],
        'address_line' => $values['address'],
        'contact_name' => $values['contact_name'] !== '' ? $values['contact_name'] : null,
        'contact_phone' => $values['contact_phone'] !== '' ? $values['contact_phone'] : null,
        'notes' => $values['access_notes'] !== '' ? $values['access_notes'] : null,
        'is_active' => $values['is_active'] === '1' ? 1 : 0,
    ];
}

function job_form_values(array $source, array $defaults = []): array
{
    $typeOptions = job_type_options();
    $priorityOptions = job_priority_options();
    $assignedUserId = array_key_exists('assigned_user_id', $source)
        ? positive_int_or_null($source['assigned_user_id'])
        : ($defaults['assigned_user_id'] ?? null);

    return [
        'customer_id' => array_key_exists('customer_id', $source)
            ? positive_int_or_null($source['customer_id'])
            : ($defaults['customer_id'] ?? null),
        'location_id' => array_key_exists('location_id', $source)
            ? positive_int_or_null($source['location_id'])
            : ($defaults['location_id'] ?? null),
        'title' => trim((string) ($source['title'] ?? ($defaults['title'] ?? ''))),
        'description' => trim((string) ($source['description'] ?? ($defaults['description'] ?? ''))),
        'job_type' => in_array(($source['job_type'] ?? $defaults['job_type'] ?? 'installation'), array_keys($typeOptions), true)
            ? (string) ($source['job_type'] ?? $defaults['job_type'] ?? 'installation')
            : 'installation',
        'priority' => in_array(($source['priority'] ?? $defaults['priority'] ?? 'normal'), array_keys($priorityOptions), true)
            ? (string) ($source['priority'] ?? $defaults['priority'] ?? 'normal')
            : 'normal',
        'assigned_user_id' => $assignedUserId,
        'planned_date' => trim((string) ($source['planned_date'] ?? ($defaults['planned_date'] ?? ''))),
        'planned_start_time' => trim((string) ($source['planned_start_time'] ?? ($defaults['planned_start_time'] ?? ''))),
        'estimated_duration_minutes' => trim((string) ($source['estimated_duration_minutes'] ?? ($defaults['estimated_duration_minutes'] ?? ''))),
        'internal_notes' => trim((string) ($source['internal_notes'] ?? ($defaults['internal_notes'] ?? ''))),
    ];
}

function valid_date_value(string $value): bool
{
    $date = DateTime::createFromFormat('Y-m-d', $value);

    return $date !== false && $date->format('Y-m-d') === $value;
}

function valid_time_value(string $value): bool
{
    $time = DateTime::createFromFormat('H:i', $value);

    return $time !== false && $time->format('H:i') === $value;
}

function derive_job_status(array $values, ?string $currentStatus = null): string
{
    if (in_array($currentStatus, ['cancelled', 'in_progress', 'completed'], true)) {
        return (string) $currentStatus;
    }

    return $values['assigned_user_id'] !== null && $values['planned_date'] !== ''
        ? 'planned'
        : 'draft';
}

function worker_job_note_value(array $source): string
{
    return trim((string) ($source['note'] ?? ''));
}

function validate_worker_job_note(string $note): ?string
{
    if ($note === '') {
        return 'Enter a note before saving.';
    }

    if (strlen($note) > 2000) {
        return 'Notes must be 2000 characters or fewer.';
    }

    return null;
}

function validate_job_form(array $values): array
{
    $errors = [];
    $customerId = $values['customer_id'] ?? null;
    $locationId = $values['location_id'] ?? null;
    $assignedUserId = $values['assigned_user_id'] ?? null;
    $plannedDate = (string) ($values['planned_date'] ?? '');
    $plannedStartTime = (string) ($values['planned_start_time'] ?? '');
    $estimatedDuration = (string) ($values['estimated_duration_minutes'] ?? '');

    if ($customerId === null) {
        $errors['customer_id'] = 'Select a valid customer.';
    } elseif (find_customer_by_id((int) $customerId) === null) {
        $errors['customer_id'] = 'The selected customer was not found.';
    }

    if ($locationId === null) {
        $errors['location_id'] = 'Select a valid location.';
    } else {
        $location = find_location_by_id((int) $locationId);

        if ($location === null) {
            $errors['location_id'] = 'The selected location was not found.';
        } elseif ($customerId !== null && (int) $location['customer_id'] !== (int) $customerId) {
            $errors['location_id'] = 'The selected location does not belong to the selected customer.';
        }
    }

    if (($values['title'] ?? '') === '') {
        $errors['title'] = 'Job title is required.';
    }

    if (!array_key_exists((string) ($values['job_type'] ?? ''), job_type_options())) {
        $errors['job_type'] = 'Select a valid job type.';
    }

    if (!array_key_exists((string) ($values['priority'] ?? ''), job_priority_options())) {
        $errors['priority'] = 'Select a valid priority.';
    }

    if ($assignedUserId !== null && !active_worker_exists((int) $assignedUserId)) {
        $errors['assigned_user_id'] = 'Select a valid worker.';
    }

    if ($plannedDate !== '' && !valid_date_value($plannedDate)) {
        $errors['planned_date'] = 'Enter a valid planned date.';
    }

    if ($plannedStartTime !== '' && !valid_time_value($plannedStartTime)) {
        $errors['planned_start_time'] = 'Enter a valid start time.';
    } elseif ($plannedStartTime !== '' && $plannedDate === '') {
        $errors['planned_start_time'] = 'Add a planned date before setting a start time.';
    }

    if ($estimatedDuration !== '') {
        if (!preg_match('/^[1-9][0-9]*$/', $estimatedDuration)) {
            $errors['estimated_duration_minutes'] = 'Estimated duration must be a positive whole number.';
        } elseif ((int) $estimatedDuration > 1440) {
            $errors['estimated_duration_minutes'] = 'Estimated duration must be 1440 minutes or less.';
        }
    }

    return $errors;
}

function save_job_payload(array $values, int $createdByUserId, ?string $currentStatus = null): array
{
    return [
        'customer_id' => (int) $values['customer_id'],
        'location_id' => (int) $values['location_id'],
        'title' => $values['title'],
        'description' => $values['description'] !== '' ? $values['description'] : null,
        'job_type' => $values['job_type'],
        'status' => derive_job_status($values, $currentStatus),
        'priority' => $values['priority'],
        'assigned_user_id' => $values['assigned_user_id'] !== null ? (int) $values['assigned_user_id'] : null,
        'planned_date' => $values['planned_date'] !== '' ? $values['planned_date'] : null,
        'planned_start_time' => $values['planned_start_time'] !== '' ? $values['planned_start_time'] : null,
        'estimated_duration_minutes' => $values['estimated_duration_minutes'] !== '' ? (int) $values['estimated_duration_minutes'] : null,
        'internal_notes' => $values['internal_notes'] !== '' ? $values['internal_notes'] : null,
        'created_by_user_id' => $createdByUserId,
    ];
}

function job_filter_values(array $source, array $viewer): array
{
    $status = trim((string) ($source['status'] ?? ''));
    $plannedDate = trim((string) ($source['planned_date'] ?? ''));

    return [
        'search' => trim((string) ($source['search'] ?? '')),
        'status' => array_key_exists($status, job_status_options()) ? $status : '',
        'worker_id' => ($viewer['role'] ?? '') === 'worker' ? (int) $viewer['id'] : positive_int_or_null($source['worker_id'] ?? null),
        'customer_id' => positive_int_or_null($source['customer_id'] ?? null),
        'planned_date' => $plannedDate !== '' && valid_date_value($plannedDate) ? $plannedDate : '',
    ];
}

function user_filter_values(array $source): array
{
    $role = trim((string) ($source['role'] ?? ''));
    $isActive = (string) ($source['is_active'] ?? '');

    return [
        'search' => trim((string) ($source['search'] ?? '')),
        'role' => array_key_exists($role, user_role_options()) ? $role : '',
        'is_active' => in_array($isActive, ['0', '1'], true) ? $isActive : '',
    ];
}

function user_form_values(array $source, array $defaults = [], bool $includePassword = true): array
{
    $role = array_key_exists('role', $source)
        ? trim((string) $source['role'])
        : (string) ($defaults['role'] ?? 'worker');
    $isActive = array_key_exists('is_active', $source)
        ? trim((string) $source['is_active'])
        : (string) ($defaults['is_active'] ?? '1');

    $values = [
        'name' => trim((string) ($source['name'] ?? ($defaults['name'] ?? ''))),
        'email' => trim((string) ($source['email'] ?? ($defaults['email'] ?? ''))),
        'role' => $role,
        'is_active' => $isActive,
    ];

    if ($includePassword) {
        $values['password'] = (string) ($source['password'] ?? '');
        $values['password_confirmation'] = (string) ($source['password_confirmation'] ?? '');
    }

    return $values;
}

function validate_user_form(array $values, ?array $existingUser = null, ?array $actor = null): array
{
    $errors = [];
    $name = trim((string) ($values['name'] ?? ''));
    $email = trim((string) ($values['email'] ?? ''));
    $role = (string) ($values['role'] ?? '');
    $isActive = (string) ($values['is_active'] ?? '1');

    if ($name === '') {
        $errors['name'] = 'Name is required.';
    }

    if ($email === '') {
        $errors['email'] = 'Email is required.';
    } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors['email'] = 'Enter a valid email address.';
    } elseif (user_email_exists($email, $existingUser !== null ? (int) $existingUser['id'] : null)) {
        $errors['email'] = 'This email address is already in use.';
    }

    if (!array_key_exists($role, user_role_options())) {
        $errors['role'] = 'Select a valid role.';
    }

    if (!in_array($isActive, ['0', '1'], true)) {
        $errors['is_active'] = 'Select a valid account status.';
    }

    if ($existingUser === null) {
        $password = (string) ($values['password'] ?? '');
        $passwordConfirmation = (string) ($values['password_confirmation'] ?? '');
        $passwordError = validate_password_strength($password);

        if ($password === '') {
            $errors['password'] = 'Password is required.';
        } elseif ($passwordError !== null) {
            $errors['password'] = $passwordError;
        }

        if ($passwordConfirmation === '') {
            $errors['password_confirmation'] = 'Password confirmation is required.';
        } elseif ($password !== $passwordConfirmation) {
            $errors['password_confirmation'] = 'Password confirmation must match.';
        }
    }

    if ($existingUser !== null) {
        $actorId = (int) ($actor['id'] ?? 0);
        $existingUserId = (int) $existingUser['id'];
        $existingRole = (string) $existingUser['role'];
        $existingActive = (int) ($existingUser['is_active'] ?? 0) === 1;
        $targetActive = $isActive === '1';
        $isSelf = $actorId !== 0 && $actorId === $existingUserId;
        $removingActiveAdmin = $existingRole === 'admin'
            && $existingActive
            && (!$targetActive || $role !== 'admin');

        if ($isSelf && !$targetActive) {
            $errors['is_active'] = 'You cannot deactivate your own account.';
        }

        if ($isSelf && $role !== 'admin') {
            $errors['role'] = 'You cannot remove your own administrator role.';
        }

        if ($removingActiveAdmin && count_active_admin_users() <= 1) {
            if (!$targetActive) {
                $errors['is_active'] ??= 'The last active administrator cannot be deactivated.';
            }

            if ($role !== 'admin') {
                $errors['role'] ??= 'The last active administrator cannot be changed to another role.';
            }
        }
    }

    return $errors;
}

function validate_password_reset_form(array $source): array
{
    $values = [
        'password' => (string) ($source['password'] ?? ''),
        'password_confirmation' => (string) ($source['password_confirmation'] ?? ''),
    ];
    $errors = [];

    if ($values['password'] === '') {
        $errors['password'] = 'New password is required.';
    } else {
        $passwordError = validate_password_strength($values['password']);

        if ($passwordError !== null) {
            $errors['password'] = $passwordError;
        }
    }

    if ($values['password_confirmation'] === '') {
        $errors['password_confirmation'] = 'Password confirmation is required.';
    } elseif ($values['password'] !== $values['password_confirmation']) {
        $errors['password_confirmation'] = 'Password confirmation must match.';
    }

    return [$values, $errors];
}

function save_user_payload(array $values, bool $includePassword = false): array
{
    $payload = [
        'name' => $values['name'],
        'email' => $values['email'],
        'role' => $values['role'],
        'is_active' => $values['is_active'] === '1' ? 1 : 0,
    ];

    if ($includePassword) {
        $payload['password_hash'] = password_hash((string) $values['password'], PASSWORD_DEFAULT);
    }

    return $payload;
}

$path = request_path();
$method = request_method();

try {
    switch (true) {
        case $path === '/':
            if (is_authenticated()) {
                redirect_to_home();
            }

            redirect('/login');
            break;

        case $path === '/login':
            if ($method === 'GET') {
                require_guest();
                render('login', [
                    'pageTitle' => 'Login',
                    'email' => '',
                    'errorMessage' => null,
                ]);
                break;
            }

            if ($method !== 'POST') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            require_guest();

            $email = trim((string) ($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $csrfToken = $_POST['_token'] ?? null;

            if (!verify_csrf_token(is_string($csrfToken) ? $csrfToken : null)) {
                render('login', [
                    'pageTitle' => 'Login',
                    'email' => $email,
                    'errorMessage' => 'Invalid email or password',
                ], 422);
                break;
            }

            $user = $email !== '' ? find_user_by_email($email) : null;
            $credentialsValid = is_array($user)
                && (int) ($user['is_active'] ?? 0) === 1
                && password_verify($password, (string) $user['password_hash']);

            if (!$credentialsValid) {
                render('login', [
                    'pageTitle' => 'Login',
                    'email' => $email,
                    'errorMessage' => 'Invalid email or password',
                ], 422);
                break;
            }

            login_user((int) $user['id']);
            redirect_to_home($user);
            break;

        case $path === '/logout':
            if ($method !== 'POST') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            require_auth();

            $csrfToken = $_POST['_token'] ?? null;

            if (!verify_csrf_token(is_string($csrfToken) ? $csrfToken : null)) {
                abort(419, 'Session expired', 'The form token is invalid or has expired.');
            }

            logout_user();
            redirect('/login');
            break;

        case $path === '/dashboard':
            require_role(['admin', 'dispatcher']);
            render('admin-dashboard', [
                'pageTitle' => 'Dashboard',
                'user' => current_user(),
                'summaryCounts' => dashboard_summary_counts(),
                'attentionJobs' => dashboard_attention_jobs(),
                'todaysSchedule' => dashboard_todays_schedule(),
                'activeWorkers' => dashboard_active_workers(),
                'recentlyCompletedJobs' => dashboard_recently_completed_jobs(),
            ]);
            break;

        case $path === '/work':
            require_role(['admin', 'dispatcher', 'worker']);
            $user = current_user();
            render('work/index', [
                'pageTitle' => 'My Work',
                'user' => $user,
                'jobSections' => list_worker_jobs_grouped((int) $user['id']),
                'successMessage' => flash('success'),
                'errorMessage' => flash('error'),
            ]);
            break;

        case $path === '/customers':
            require_role(['admin', 'dispatcher']);

            if ($method !== 'GET') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            render('customers/index', [
                'pageTitle' => 'Customers',
                'customers' => all_customers(),
            ]);
            break;

        case preg_match('#^/customers/([1-9][0-9]*)$#', $path, $matches) === 1:
            require_role(['admin', 'dispatcher']);

            if ($method !== 'GET') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            $customer = find_customer_by_id((int) $matches[1]);

            if ($customer === null) {
                not_found('Customer');
            }

            render('customers/show', [
                'pageTitle' => $customer['name'],
                'customer' => $customer,
                'locations' => list_locations_for_customer((int) $customer['id']),
            ]);
            break;

        case $path === '/locations':
            require_role(['admin', 'dispatcher']);

            if ($method === 'GET') {
                $filterCustomerId = null;
                $selectedCustomer = null;

                if (array_key_exists('customer_id', $_GET) && $_GET['customer_id'] !== '') {
                    $filterCustomerId = positive_int_or_null($_GET['customer_id']);

                    if ($filterCustomerId === null) {
                        not_found('Customer');
                    }

                    $selectedCustomer = find_customer_by_id($filterCustomerId);

                    if ($selectedCustomer === null) {
                        not_found('Customer');
                    }
                }

                render('locations/index', [
                    'pageTitle' => 'Locations',
                    'locations' => list_locations($filterCustomerId),
                    'customers' => all_customers(),
                    'selectedCustomer' => $selectedCustomer,
                    'selectedCustomerId' => $filterCustomerId,
                ]);
                break;
            }

            if ($method !== 'POST') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            $csrfToken = $_POST['_token'] ?? null;

            if (!verify_csrf_token(is_string($csrfToken) ? $csrfToken : null)) {
                abort(419, 'Session expired', 'The form token is invalid or has expired.');
            }

            $values = location_form_values($_POST);
            $errors = validate_location_form($values);
            $customers = all_customers();

            if ($errors !== []) {
                render('locations/form', [
                    'pageTitle' => 'Add Location',
                    'formTitle' => 'Add Location',
                    'formAction' => '/locations',
                    'submitLabel' => 'Create Location',
                    'customers' => $customers,
                    'values' => $values,
                    'errors' => $errors,
                    'location' => null,
                ], 422);
                break;
            }

            $locationId = create_location(save_location_payload($values));
            redirect('/locations/' . $locationId);
            break;

        case $path === '/locations/create':
            require_role(['admin', 'dispatcher']);

            if ($method !== 'GET') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            $preselectedCustomerId = null;

            if (array_key_exists('customer_id', $_GET) && $_GET['customer_id'] !== '') {
                $preselectedCustomerId = positive_int_or_null($_GET['customer_id']);

                if ($preselectedCustomerId === null || !customer_exists($preselectedCustomerId)) {
                    not_found('Customer');
                }
            }

            render('locations/form', [
                'pageTitle' => 'Add Location',
                'formTitle' => 'Add Location',
                'formAction' => '/locations',
                'submitLabel' => 'Create Location',
                'customers' => all_customers(),
                'values' => location_form_values([], $preselectedCustomerId),
                'errors' => [],
                'location' => null,
            ]);
            break;

        case preg_match('#^/locations/([1-9][0-9]*)$#', $path, $matches) === 1:
            require_role(['admin', 'dispatcher']);

            if ($method !== 'GET') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            $location = find_location_by_id((int) $matches[1]);

            if ($location === null) {
                not_found('Location');
            }

            render('locations/show', [
                'pageTitle' => $location['name'],
                'location' => $location,
                'recentJobs' => recent_jobs_for_location((int) $location['id']),
            ]);
            break;

        case preg_match('#^/locations/([1-9][0-9]*)/edit$#', $path, $matches) === 1:
            require_role(['admin', 'dispatcher']);

            $location = find_location_by_id((int) $matches[1]);

            if ($location === null) {
                not_found('Location');
            }

            $customers = all_customers();

            if ($method === 'GET') {
                render('locations/form', [
                    'pageTitle' => 'Edit Location',
                    'formTitle' => 'Edit Location',
                    'formAction' => '/locations/' . $location['id'] . '/edit',
                    'submitLabel' => 'Save Changes',
                    'customers' => $customers,
                    'values' => [
                        'customer_id' => (int) $location['customer_id'],
                        'name' => (string) $location['name'],
                        'address' => (string) ($location['address_line'] ?? ''),
                        'contact_name' => (string) ($location['contact_name'] ?? ''),
                        'contact_phone' => (string) ($location['contact_phone'] ?? ''),
                        'access_notes' => (string) ($location['notes'] ?? ''),
                        'is_active' => (int) ($location['is_active'] ?? 0) === 1 ? '1' : '0',
                    ],
                    'errors' => [],
                    'location' => $location,
                ]);
                break;
            }

            if ($method !== 'POST') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            $csrfToken = $_POST['_token'] ?? null;

            if (!verify_csrf_token(is_string($csrfToken) ? $csrfToken : null)) {
                abort(419, 'Session expired', 'The form token is invalid or has expired.');
            }

            $values = location_form_values($_POST);
            $errors = validate_location_form($values);

            if ($errors !== []) {
                render('locations/form', [
                    'pageTitle' => 'Edit Location',
                    'formTitle' => 'Edit Location',
                    'formAction' => '/locations/' . $location['id'] . '/edit',
                    'submitLabel' => 'Save Changes',
                    'customers' => $customers,
                    'values' => $values,
                    'errors' => $errors,
                    'location' => $location,
                ], 422);
                break;
            }

            update_location((int) $location['id'], save_location_payload($values));
            redirect('/locations/' . $location['id']);
            break;

        case $path === '/tasks':
            require_role(['admin', 'dispatcher']);
            render('module-placeholder', [
                'pageTitle' => 'Tasks',
                'heading' => 'Tasks',
                'message' => 'Task management will be implemented in a later task.',
            ]);
            break;

        case $path === '/jobs':
            require_role(['admin', 'dispatcher']);

            if ($method === 'POST') {
                require_role(['admin', 'dispatcher']);

                $csrfToken = $_POST['_token'] ?? null;

                if (!verify_csrf_token(is_string($csrfToken) ? $csrfToken : null)) {
                    abort(419, 'Session expired', 'The form token is invalid or has expired.');
                }

                $values = job_form_values($_POST);
                $errors = validate_job_form($values);

                if ($errors !== []) {
                    render('jobs/form', [
                        'pageTitle' => 'Create Job',
                        'formTitle' => 'Create Job',
                        'formAction' => '/jobs',
                        'submitLabel' => 'Create Job',
                        'customers' => active_customers(),
                        'locations' => list_active_locations(),
                        'workers' => list_active_workers(),
                        'values' => $values,
                        'errors' => $errors,
                        'job' => null,
                    ], 422);
                    break;
                }

                $createdByUser = current_user();

                try {
                    $jobId = create_job(save_job_payload($values, (int) $createdByUser['id']));
                } catch (RuntimeException $exception) {
                    render('jobs/form', [
                        'pageTitle' => 'Create Job',
                        'formTitle' => 'Create Job',
                        'formAction' => '/jobs',
                        'submitLabel' => 'Create Job',
                        'customers' => active_customers(),
                        'locations' => list_active_locations(),
                        'workers' => list_active_workers(),
                        'values' => $values,
                        'errors' => ['job_number' => 'Could not generate a job number. Please try again.'],
                        'job' => null,
                    ], 422);
                    break;
                }

                flash('success', 'Job created successfully.');
                redirect('/jobs/' . $jobId);
            }

            if ($method !== 'GET') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            $user = current_user();
            $filters = job_filter_values($_GET, $user);

            render('jobs/index', [
                'pageTitle' => 'Jobs',
                'jobs' => list_jobs($filters, $user),
                'filters' => $filters,
                'workers' => list_active_workers(),
                'customers' => all_customers(),
                'user' => $user,
                'successMessage' => flash('success'),
            ]);
            break;

        case $path === '/jobs/create':
            require_role(['admin', 'dispatcher']);

            if ($method !== 'GET') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            render('jobs/form', [
                'pageTitle' => 'Create Job',
                'formTitle' => 'Create Job',
                'formAction' => '/jobs',
                'submitLabel' => 'Create Job',
                'customers' => active_customers(),
                'locations' => list_active_locations(),
                'workers' => list_active_workers(),
                'values' => job_form_values($_GET),
                'errors' => [],
                'job' => null,
            ]);
            break;

        case preg_match('#^/jobs/([1-9][0-9]*)$#', $path, $matches) === 1:
            require_role(['admin', 'dispatcher']);

            if ($method !== 'GET') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            $job = find_job_by_id((int) $matches[1], current_user());

            if ($job === null) {
                not_found('Job');
            }

            render('jobs/show', [
                'pageTitle' => $job['job_number'],
                'job' => $job,
                'successMessage' => flash('success'),
            ]);
            break;

        case preg_match('#^/jobs/([1-9][0-9]*)/edit$#', $path, $matches) === 1:
            require_role(['admin', 'dispatcher']);

            $job = find_job_by_id((int) $matches[1], current_user());

            if ($job === null) {
                not_found('Job');
            }

            if ($method === 'GET') {
                render('jobs/form', [
                    'pageTitle' => 'Edit Job',
                    'formTitle' => 'Edit Job',
                    'formAction' => '/jobs/' . $job['id'] . '/edit',
                    'submitLabel' => 'Save Changes',
                    'customers' => active_customers(),
                    'locations' => list_active_locations(),
                    'workers' => list_active_workers(),
                    'values' => job_form_values([], [
                        'customer_id' => (int) $job['customer_id'],
                        'location_id' => $job['location_id'] !== null ? (int) $job['location_id'] : null,
                        'title' => (string) $job['title'],
                        'description' => (string) ($job['description'] ?? ''),
                        'job_type' => (string) $job['job_type'],
                        'priority' => (string) $job['priority'],
                        'assigned_user_id' => $job['assigned_user_id'] !== null ? (int) $job['assigned_user_id'] : null,
                        'planned_date' => (string) ($job['planned_date'] ?? ''),
                        'planned_start_time' => $job['planned_start_time'] !== null ? substr((string) $job['planned_start_time'], 0, 5) : '',
                        'estimated_duration_minutes' => $job['estimated_duration_minutes'] !== null ? (string) $job['estimated_duration_minutes'] : '',
                        'internal_notes' => (string) ($job['internal_notes'] ?? ''),
                    ]),
                    'errors' => [],
                    'job' => $job,
                ]);
                break;
            }

            if ($method !== 'POST') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            $csrfToken = $_POST['_token'] ?? null;

            if (!verify_csrf_token(is_string($csrfToken) ? $csrfToken : null)) {
                abort(419, 'Session expired', 'The form token is invalid or has expired.');
            }

            $values = job_form_values($_POST);
            $errors = validate_job_form($values);

            if ($errors !== []) {
                render('jobs/form', [
                    'pageTitle' => 'Edit Job',
                    'formTitle' => 'Edit Job',
                    'formAction' => '/jobs/' . $job['id'] . '/edit',
                    'submitLabel' => 'Save Changes',
                    'customers' => active_customers(),
                    'locations' => list_active_locations(),
                    'workers' => list_active_workers(),
                    'values' => $values,
                    'errors' => $errors,
                    'job' => $job,
                ], 422);
                break;
            }

            update_job((int) $job['id'], save_job_payload($values, (int) ($job['created_by_user_id'] ?? current_user()['id']), (string) $job['status']));
            flash('success', 'Job updated successfully.');
            redirect('/jobs/' . $job['id']);
            break;

        case preg_match('#^/jobs/([1-9][0-9]*)/cancel$#', $path, $matches) === 1:
            require_role(['admin', 'dispatcher']);

            if ($method !== 'POST') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            $csrfToken = $_POST['_token'] ?? null;

            if (!verify_csrf_token(is_string($csrfToken) ? $csrfToken : null)) {
                abort(419, 'Session expired', 'The form token is invalid or has expired.');
            }

            $job = find_job_by_id((int) $matches[1], current_user());

            if ($job === null) {
                not_found('Job');
            }

            cancel_job((int) $job['id']);
            flash('success', 'Job cancelled successfully.');
            redirect('/jobs/' . $job['id']);
            break;

        case preg_match('#^/jobs/([1-9][0-9]*)/reactivate$#', $path, $matches) === 1:
            require_role(['admin', 'dispatcher']);

            if ($method !== 'POST') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            $csrfToken = $_POST['_token'] ?? null;

            if (!verify_csrf_token(is_string($csrfToken) ? $csrfToken : null)) {
                abort(419, 'Session expired', 'The form token is invalid or has expired.');
            }

            $job = find_job_by_id((int) $matches[1], current_user());

            if ($job === null) {
                not_found('Job');
            }

            reactivate_job((int) $job['id']);
            flash('success', 'Job reactivated successfully.');
            redirect('/jobs/' . $job['id']);
            break;

        case $path === '/users':
            require_role(['admin']);

            if ($method === 'POST') {
                $csrfToken = $_POST['_token'] ?? null;

                if (!verify_csrf_token(is_string($csrfToken) ? $csrfToken : null)) {
                    abort(419, 'Session expired', 'The form token is invalid or has expired.');
                }

                $values = user_form_values($_POST);
                $errors = validate_user_form($values);

                if ($errors !== []) {
                    render('users/form', [
                        'pageTitle' => 'Create User',
                        'formTitle' => 'Create User',
                        'formAction' => '/users',
                        'submitLabel' => 'Create User',
                        'values' => $values,
                        'errors' => $errors,
                        'userRecord' => null,
                    ], 422);
                    break;
                }

                $userId = create_user(save_user_payload($values, true));
                flash('success', 'User created successfully.');
                redirect('/users/' . $userId);
            }

            if ($method !== 'GET') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            $filters = user_filter_values($_GET);

            render('users/index', [
                'pageTitle' => 'Users',
                'users' => list_users($filters),
                'filters' => $filters,
                'successMessage' => flash('success'),
            ]);
            break;

        case $path === '/users/create':
            require_role(['admin']);

            if ($method !== 'GET') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            render('users/form', [
                'pageTitle' => 'Create User',
                'formTitle' => 'Create User',
                'formAction' => '/users',
                'submitLabel' => 'Create User',
                'values' => user_form_values([]),
                'errors' => [],
                'userRecord' => null,
            ]);
            break;

        case preg_match('#^/users/([1-9][0-9]*)$#', $path, $matches) === 1:
            require_role(['admin']);

            if ($method !== 'GET') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            $managedUser = find_managed_user_by_id((int) $matches[1]);

            if ($managedUser === null) {
                not_found('User');
            }

            render('users/show', [
                'pageTitle' => $managedUser['name'],
                'managedUser' => $managedUser,
                'recentJobs' => recent_assigned_jobs_for_user((int) $managedUser['id']),
                'passwordValues' => ['password' => '', 'password_confirmation' => ''],
                'passwordErrors' => [],
                'successMessage' => flash('success'),
            ]);
            break;

        case preg_match('#^/users/([1-9][0-9]*)/edit$#', $path, $matches) === 1:
            require_role(['admin']);

            $managedUser = find_managed_user_by_id((int) $matches[1]);

            if ($managedUser === null) {
                not_found('User');
            }

            if ($method === 'GET') {
                render('users/form', [
                    'pageTitle' => 'Edit User',
                    'formTitle' => 'Edit User',
                    'formAction' => '/users/' . $managedUser['id'] . '/edit',
                    'submitLabel' => 'Save Changes',
                    'values' => user_form_values([], [
                        'name' => (string) $managedUser['name'],
                        'email' => (string) $managedUser['email'],
                        'role' => (string) $managedUser['role'],
                        'is_active' => (int) ($managedUser['is_active'] ?? 0) === 1 ? '1' : '0',
                    ], false),
                    'errors' => [],
                    'userRecord' => $managedUser,
                ]);
                break;
            }

            if ($method !== 'POST') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            $csrfToken = $_POST['_token'] ?? null;

            if (!verify_csrf_token(is_string($csrfToken) ? $csrfToken : null)) {
                abort(419, 'Session expired', 'The form token is invalid or has expired.');
            }

            $values = user_form_values($_POST, [], false);
            $errors = validate_user_form($values, $managedUser, current_user());

            if ($errors !== []) {
                render('users/form', [
                    'pageTitle' => 'Edit User',
                    'formTitle' => 'Edit User',
                    'formAction' => '/users/' . $managedUser['id'] . '/edit',
                    'submitLabel' => 'Save Changes',
                    'values' => $values,
                    'errors' => $errors,
                    'userRecord' => $managedUser,
                ], 422);
                break;
            }

            update_user((int) $managedUser['id'], save_user_payload($values));
            flash('success', 'User updated successfully.');
            redirect('/users/' . $managedUser['id']);
            break;

        case preg_match('#^/users/([1-9][0-9]*)/password$#', $path, $matches) === 1:
            require_role(['admin']);

            if ($method !== 'POST') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            $csrfToken = $_POST['_token'] ?? null;

            if (!verify_csrf_token(is_string($csrfToken) ? $csrfToken : null)) {
                abort(419, 'Session expired', 'The form token is invalid or has expired.');
            }

            $managedUser = find_managed_user_by_id((int) $matches[1]);

            if ($managedUser === null) {
                not_found('User');
            }

            [$passwordValues, $passwordErrors] = validate_password_reset_form($_POST);

            if ($passwordErrors !== []) {
                render('users/show', [
                    'pageTitle' => $managedUser['name'],
                    'managedUser' => $managedUser,
                    'recentJobs' => recent_assigned_jobs_for_user((int) $managedUser['id']),
                    'passwordValues' => $passwordValues,
                    'passwordErrors' => $passwordErrors,
                    'successMessage' => null,
                ], 422);
                break;
            }

            update_user_password(
                (int) $managedUser['id'],
                password_hash((string) $passwordValues['password'], PASSWORD_DEFAULT)
            );
            flash('success', 'Password reset successfully.');
            redirect('/users/' . $managedUser['id']);
            break;

        case $path === '/work/jobs':
            require_role(['admin', 'dispatcher', 'worker']);
            redirect('/work');
            break;

        case preg_match('#^/work/jobs/([1-9][0-9]*)$#', $path, $matches) === 1:
            require_role(['admin', 'dispatcher', 'worker']);

            if ($method !== 'GET') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            $viewer = current_user();
            $job = find_worker_accessible_job_by_id((int) $matches[1], $viewer);

            if ($job === null) {
                not_found('Job');
            }

            render('work/show', [
                'pageTitle' => $job['job_number'],
                'job' => $job,
                'notes' => list_job_notes((int) $job['id']),
                'noteValue' => '',
                'noteError' => null,
                'successMessage' => flash('success'),
                'errorMessage' => flash('error'),
                'viewer' => $viewer,
            ]);
            break;

        case preg_match('#^/work/jobs/([1-9][0-9]*)/start$#', $path, $matches) === 1:
            require_role(['admin', 'dispatcher', 'worker']);

            if ($method !== 'POST') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            $csrfToken = $_POST['_token'] ?? null;

            if (!verify_csrf_token(is_string($csrfToken) ? $csrfToken : null)) {
                abort(419, 'Session expired', 'The form token is invalid or has expired.');
            }

            $viewer = current_user();
            $job = find_worker_accessible_job_by_id((int) $matches[1], $viewer);

            if ($job === null) {
                not_found('Job');
            }

            if (!worker_can_start_job($job)) {
                flash('error', 'This job cannot be started in its current state.');
                redirect('/work/jobs/' . $matches[1]);
            }

            if (!start_worker_job((int) $job['id'], (int) $viewer['id'], (string) $viewer['role'])) {
                flash('error', 'The job could not be started.');
                redirect('/work/jobs/' . $matches[1]);
            }

            flash('success', 'Job started successfully.');
            redirect('/work/jobs/' . $matches[1]);
            break;

        case preg_match('#^/work/jobs/([1-9][0-9]*)/complete$#', $path, $matches) === 1:
            require_role(['admin', 'dispatcher', 'worker']);

            if ($method !== 'POST') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            $csrfToken = $_POST['_token'] ?? null;

            if (!verify_csrf_token(is_string($csrfToken) ? $csrfToken : null)) {
                abort(419, 'Session expired', 'The form token is invalid or has expired.');
            }

            $viewer = current_user();
            $job = find_worker_accessible_job_by_id((int) $matches[1], $viewer);

            if ($job === null) {
                not_found('Job');
            }

            if (!worker_can_complete_job($job)) {
                flash('error', 'Only in-progress jobs can be completed.');
                redirect('/work/jobs/' . $matches[1]);
            }

            if (!complete_worker_job((int) $job['id'], (int) $viewer['id'], (string) $viewer['role'])) {
                flash('error', 'The job could not be completed.');
                redirect('/work/jobs/' . $matches[1]);
            }

            flash('success', 'Job completed successfully.');
            redirect('/work/jobs/' . $matches[1]);
            break;

        case preg_match('#^/work/jobs/([1-9][0-9]*)/notes$#', $path, $matches) === 1:
            require_role(['admin', 'dispatcher', 'worker']);

            if ($method !== 'POST') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            $csrfToken = $_POST['_token'] ?? null;

            if (!verify_csrf_token(is_string($csrfToken) ? $csrfToken : null)) {
                abort(419, 'Session expired', 'The form token is invalid or has expired.');
            }

            $viewer = current_user();
            $job = find_worker_accessible_job_by_id((int) $matches[1], $viewer);

            if ($job === null) {
                not_found('Job');
            }

            $noteValue = worker_job_note_value($_POST);
            $noteError = validate_worker_job_note($noteValue);

            if ($noteError !== null) {
                render('work/show', [
                    'pageTitle' => $job['job_number'],
                    'job' => $job,
                    'notes' => list_job_notes((int) $job['id']),
                    'noteValue' => $noteValue,
                    'noteError' => $noteError,
                    'successMessage' => flash('success'),
                    'errorMessage' => flash('error'),
                    'viewer' => $viewer,
                ], 422);
                break;
            }

            create_job_note((int) $job['id'], isset($viewer['id']) ? (int) $viewer['id'] : null, $noteValue);
            flash('success', 'Job note added successfully.');
            redirect('/work/jobs/' . $matches[1]);
            break;

        case $path === '/error':
            render('error', [
                'pageTitle' => 'Error',
                'heading' => 'Example error page',
                'message' => safe_error_message('This is a sample error page for layout and messaging checks.'),
                'statusCode' => 500,
                'details' => 'Triggered from /error for demonstration purposes.',
            ], 500);
            break;

        default:
            render('error', [
                'pageTitle' => 'Page not found',
                'heading' => 'Page not found',
                'message' => is_debug()
                    ? 'The requested route does not exist.'
                    : 'The page you requested could not be found.',
                'statusCode' => 404,
                'details' => 'No route matched path: ' . $path,
            ], 404);
            break;
    }
} catch (Throwable $exception) {
    $isDatabaseException = $exception instanceof PDOException;

    render('error', [
        'pageTitle' => 'Application error',
        'heading' => 'Application error',
        'message' => $isDatabaseException
            ? 'A service dependency is temporarily unavailable. Please try again later.'
            : safe_error_message($exception->getMessage()),
        'statusCode' => 500,
        'details' => $isDatabaseException ? null : $exception,
    ], 500);
}
