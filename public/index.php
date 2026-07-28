<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/helpers.php';
require base_path('app/database/connection.php');
require base_path('app/repositories/customers.php');
require base_path('app/repositories/dashboard.php');
require base_path('app/repositories/job_assets.php');
require base_path('app/repositories/job_customer_confirmations.php');
require base_path('app/repositories/jobs.php');
require base_path('app/repositories/locations.php');
require base_path('app/repositories/materials.php');
require base_path('app/repositories/tasks.php');
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

function material_filter_values(array $source): array
{
    $status = trim((string) ($source['status'] ?? ''));

    return [
        'search' => trim((string) ($source['search'] ?? '')),
        'status' => in_array($status, ['active', 'inactive'], true) ? $status : '',
    ];
}

function material_form_values(array $source, array $defaults = []): array
{
    $isActive = array_key_exists('is_active', $source)
        ? (string) $source['is_active']
        : (string) ($defaults['is_active'] ?? '1');

    return [
        'name' => trim((string) ($source['name'] ?? ($defaults['name'] ?? ''))),
        'sku' => trim((string) ($source['sku'] ?? ($defaults['sku'] ?? ''))),
        'unit' => trim((string) ($source['unit'] ?? ($defaults['unit'] ?? ''))),
        'description' => trim((string) ($source['description'] ?? ($defaults['description'] ?? ''))),
        'is_active' => $isActive === '0' ? '0' : '1',
    ];
}

function validate_material_form(array $values): array
{
    $errors = [];

    if (($values['name'] ?? '') === '') {
        $errors['name'] = 'Material name is required.';
    }

    if (($values['unit'] ?? '') === '') {
        $errors['unit'] = 'Unit is required.';
    }

    return $errors;
}

function save_material_payload(array $values): array
{
    return [
        'name' => $values['name'],
        'sku' => $values['sku'] !== '' ? $values['sku'] : null,
        'unit' => $values['unit'],
        'description' => $values['description'] !== '' ? $values['description'] : null,
        'is_active' => $values['is_active'] === '1' ? 1 : 0,
    ];
}

function job_material_form_values(array $source): array
{
    return [
        'material_id' => positive_int_or_null($source['material_id'] ?? null),
        'quantity' => trim((string) ($source['quantity'] ?? '')),
    ];
}

function normalize_quantity_value(string $value): ?string
{
    $normalized = trim($value);

    if ($normalized === '' || preg_match('/^\d+(?:\.\d{1,3})?$/', $normalized) !== 1) {
        return null;
    }

    if ((float) $normalized <= 0) {
        return null;
    }

    return number_format((float) $normalized, 3, '.', '');
}

function validate_job_material_create(array $values): array
{
    $errors = [];

    if (($values['material_id'] ?? null) === null) {
        $errors['material_id'] = 'Select a valid material.';
    } else {
        $material = find_material_by_id((int) $values['material_id']);

        if ($material === null || (int) ($material['is_active'] ?? 0) !== 1) {
            $errors['material_id'] = 'The selected material is not available.';
        }
    }

    if (normalize_quantity_value((string) ($values['quantity'] ?? '')) === null) {
        $errors['quantity'] = 'Enter a quantity greater than zero using up to 3 decimal places.';
    }

    return $errors;
}

function validate_job_material_quantity(string $quantity): ?string
{
    return normalize_quantity_value($quantity) === null
        ? 'Enter a quantity greater than zero using up to 3 decimal places.'
        : null;
}

function material_option_label(array $material): string
{
    $label = (string) $material['name'];

    if (($material['sku'] ?? null) !== null && trim((string) $material['sku']) !== '') {
        $label .= ' (' . trim((string) $material['sku']) . ')';
    }

    return $label . ' - ' . (string) $material['unit'];
}

function job_form_values(array $source, array $defaults = []): array
{
    $typeOptions = job_type_options();
    $priorityOptions = job_priority_options();
    $assignedUserId = array_key_exists('assigned_user_id', $source)
        ? positive_int_or_null($source['assigned_user_id'])
        : ($defaults['assigned_user_id'] ?? null);
    $taskId = array_key_exists('task_id', $source)
        ? positive_int_or_null($source['task_id'])
        : ($defaults['task_id'] ?? null);

    return [
        'task_id' => $taskId,
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

function task_form_values(array $source, array $defaults = []): array
{
    $statusOptions = task_status_options();
    $priorityOptions = task_priority_options();

    return [
        'customer_id' => array_key_exists('customer_id', $source)
            ? positive_int_or_null($source['customer_id'])
            : ($defaults['customer_id'] ?? null),
        'location_id' => array_key_exists('location_id', $source)
            ? positive_int_or_null($source['location_id'])
            : ($defaults['location_id'] ?? null),
        'title' => trim((string) ($source['title'] ?? ($defaults['title'] ?? ''))),
        'description' => trim((string) ($source['description'] ?? ($defaults['description'] ?? ''))),
        'status' => in_array(($source['status'] ?? $defaults['status'] ?? 'new'), array_keys($statusOptions), true)
            ? (string) ($source['status'] ?? $defaults['status'] ?? 'new')
            : 'new',
        'priority' => in_array(($source['priority'] ?? $defaults['priority'] ?? 'normal'), array_keys($priorityOptions), true)
            ? (string) ($source['priority'] ?? $defaults['priority'] ?? 'normal')
            : 'normal',
        'requested_date' => trim((string) ($source['requested_date'] ?? ($defaults['requested_date'] ?? ''))),
        'due_date' => trim((string) ($source['due_date'] ?? ($defaults['due_date'] ?? ''))),
    ];
}

function valid_date_value(string $value): bool
{
    $date = DateTime::createFromFormat('Y-m-d', $value);

    return $date !== false && $date->format('Y-m-d') === $value;
}

function valid_month_value(string $value): bool
{
    if (preg_match('/^\d{4}-\d{2}$/', $value) !== 1) {
        return false;
    }

    $month = DateTimeImmutable::createFromFormat('!Y-m', $value);

    return $month !== false && $month->format('Y-m') === $value;
}

function requested_calendar_month(mixed $value): DateTimeImmutable
{
    $monthValue = is_string($value) ? trim($value) : '';

    if ($monthValue !== '' && valid_month_value($monthValue)) {
        return new DateTimeImmutable($monthValue . '-01');
    }

    return new DateTimeImmutable('first day of this month');
}

function requested_calendar_view(mixed $value): string
{
    $view = is_string($value) ? trim($value) : '';

    return in_array($view, ['week', 'month'], true) ? $view : 'week';
}

function requested_calendar_date(mixed $value): DateTimeImmutable
{
    $dateValue = is_string($value) ? trim($value) : '';

    if ($dateValue !== '' && valid_date_value($dateValue)) {
        return new DateTimeImmutable($dateValue);
    }

    return new DateTimeImmutable('today');
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

function job_photo_caption_value(array $source): string
{
    return trim((string) ($source['caption'] ?? ''));
}

function validate_job_photo_caption(string $caption): ?string
{
    if (strlen($caption) > 255) {
        return 'Photo captions must be 255 characters or fewer.';
    }

    return null;
}

function customer_confirmation_form_values(array $source): array
{
    return [
        'customer_name' => trim((string) ($source['customer_name'] ?? '')),
        'customer_email' => trim((string) ($source['customer_email'] ?? '')),
        'signature_data' => trim((string) ($source['signature_data'] ?? '')),
    ];
}

function validate_customer_confirmation_form(array $values, array $job, ?array $viewer): array
{
    $errors = [];
    $rules = job_customer_confirmation_rules();

    if ($job === []) {
        $errors['job'] = 'The selected job was not found.';

        return $errors;
    }

    if ($viewer === null || !user_can_record_job_customer_confirmation($viewer, $job)) {
        $errors['authorization'] = 'You do not have permission to record customer confirmation for this job.';
    }

    if (!job_can_accept_customer_confirmation($job)) {
        $errors['status'] = 'Customer confirmation can only be recorded after the job is completed.';
    }

    if (find_job_customer_confirmation((int) $job['id']) !== null) {
        $errors['duplicate'] = 'Customer confirmation has already been recorded for this job.';
    }

    $customerName = (string) ($values['customer_name'] ?? '');

    if ($customerName === '') {
        $errors['customer_name'] = 'Customer name is required.';
    } elseif (strlen($customerName) > $rules['max_name_length']) {
        $errors['customer_name'] = 'Customer name must be 255 characters or fewer.';
    }

    $customerEmail = (string) ($values['customer_email'] ?? '');

    if ($customerEmail !== '') {
        if (strlen($customerEmail) > $rules['max_email_length']) {
            $errors['customer_email'] = 'Customer email must be 255 characters or fewer.';
        } elseif (filter_var($customerEmail, FILTER_VALIDATE_EMAIL) === false) {
            $errors['customer_email'] = 'Enter a valid customer email address.';
        }
    }

    $signatureError = validate_customer_confirmation_signature_data((string) ($values['signature_data'] ?? ''));

    if ($signatureError !== null) {
        $errors['signature_data'] = $signatureError;
    }

    return $errors;
}

function validate_customer_confirmation_signature_data(string $signatureData): ?string
{
    if ($signatureData === '') {
        return 'Capture the customer signature before submitting.';
    }

    if (preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $signatureData, $matches) !== 1) {
        return 'The signature must be submitted as a valid PNG image.';
    }

    $decoded = base64_decode($matches[1], true);

    if ($decoded === false || $decoded === '') {
        return 'The signature image could not be decoded.';
    }

    if (strlen($decoded) > job_customer_confirmation_rules()['max_signature_bytes']) {
        return 'The signature image exceeds the allowed size limit.';
    }

    $imageInfo = @getimagesizefromstring($decoded);

    if ($imageInfo === false || ($imageInfo['mime'] ?? '') !== 'image/png') {
        return 'The signature image must be a valid PNG.';
    }

    return null;
}

function decoded_customer_confirmation_signature(string $signatureData): string
{
    if (preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $signatureData, $matches) !== 1) {
        throw new InvalidArgumentException('Invalid signature data.');
    }

    $decoded = base64_decode($matches[1], true);

    if ($decoded === false || $decoded === '') {
        throw new InvalidArgumentException('Invalid signature payload.');
    }

    return $decoded;
}

function job_detail_route_for_user(array $viewer, int $jobId): string
{
    return ((string) ($viewer['role'] ?? '')) === 'worker'
        ? '/work/jobs/' . $jobId
        : '/jobs/' . $jobId;
}

function render_job_show_page(
    array $job,
    array $viewer,
    array $overrides = [],
    bool $workerView = false,
    int $statusCode = 200
): void {
    $baseData = [
        'pageTitle' => $job['job_number'],
        'job' => $job,
        'attachments' => list_job_attachments((int) $job['id']),
        'photos' => list_job_photos((int) $job['id']),
        'jobMaterials' => list_job_materials((int) $job['id']),
        'activeMaterials' => list_active_materials(),
        'customerConfirmation' => find_job_customer_confirmation((int) $job['id']),
        'customerConfirmationValues' => customer_confirmation_form_values([]),
        'customerConfirmationErrors' => [],
        'materialUsageValues' => job_material_form_values([]),
        'materialUsageErrors' => [],
        'materialEditValues' => [],
        'materialEditErrors' => [],
        'viewer' => $viewer,
        'successMessage' => flash('success'),
        'errorMessage' => flash('error'),
        'attachmentError' => null,
        'photoError' => null,
        'photoCaption' => '',
        'photoCaptionError' => null,
    ];

    if ($workerView) {
        $baseData['notes'] = list_job_notes((int) $job['id']);
        $baseData['noteValue'] = '';
        $baseData['noteError'] = null;
    }

    render($workerView ? 'work/show' : 'jobs/show', array_merge($baseData, $overrides), $statusCode);
}

function send_stored_job_asset(array $asset, string $disposition = 'attachment'): never
{
    $storagePath = (string) ($asset['storage_path'] ?? '');

    if ($storagePath === '' || !is_file($storagePath) || !is_readable($storagePath)) {
        abort(404, 'File not found', 'The requested file is unavailable.');
    }

    $mimeType = (string) ($asset['mime_type'] ?? 'application/octet-stream');
    $fileSize = (int) ($asset['file_size'] ?? filesize($storagePath) ?: 0);
    $downloadName = basename((string) ($asset['original_filename'] ?? 'download'));

    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . $fileSize);
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: ' . $disposition . '; filename="' . addcslashes($downloadName, "\"\\") . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
    readfile($storagePath);
    exit;
}

function validate_job_form(array $values): array
{
    $errors = [];
    $taskId = $values['task_id'] ?? null;
    $customerId = $values['customer_id'] ?? null;
    $locationId = $values['location_id'] ?? null;
    $assignedUserId = $values['assigned_user_id'] ?? null;
    $plannedDate = (string) ($values['planned_date'] ?? '');
    $plannedStartTime = (string) ($values['planned_start_time'] ?? '');
    $estimatedDuration = (string) ($values['estimated_duration_minutes'] ?? '');
    $task = null;

    if ($taskId !== null) {
        $task = find_task_brief_by_id((int) $taskId);

        if ($task === null) {
            $errors['task_id'] = 'The selected task was not found.';
        }
    }

    if ($customerId === null) {
        $errors['customer_id'] = 'Select a valid customer.';
    } elseif (find_customer_by_id((int) $customerId) === null) {
        $errors['customer_id'] = 'The selected customer was not found.';
    } elseif ($task !== null && (int) $task['customer_id'] !== (int) $customerId) {
        $errors['customer_id'] = 'The selected customer must match the linked task.';
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

function validate_task_form(array $values): array
{
    $errors = [];
    $customerId = $values['customer_id'] ?? null;
    $locationId = $values['location_id'] ?? null;
    $requestedDate = (string) ($values['requested_date'] ?? '');
    $dueDate = (string) ($values['due_date'] ?? '');

    if ($customerId === null) {
        $errors['customer_id'] = 'Select a valid customer.';
    } elseif (find_customer_by_id((int) $customerId) === null) {
        $errors['customer_id'] = 'The selected customer was not found.';
    }

    if ($locationId !== null) {
        $location = find_location_by_id((int) $locationId);

        if ($location === null) {
            $errors['location_id'] = 'The selected location was not found.';
        } elseif ($customerId !== null && (int) $location['customer_id'] !== (int) $customerId) {
            $errors['location_id'] = 'The selected location does not belong to the selected customer.';
        }
    }

    if (($values['title'] ?? '') === '') {
        $errors['title'] = 'Task title is required.';
    }

    if (!array_key_exists((string) ($values['status'] ?? ''), task_status_options())) {
        $errors['status'] = 'Select a valid task status.';
    }

    if (!array_key_exists((string) ($values['priority'] ?? ''), task_priority_options())) {
        $errors['priority'] = 'Select a valid task priority.';
    }

    if ($requestedDate !== '' && !valid_date_value($requestedDate)) {
        $errors['requested_date'] = 'Enter a valid requested date.';
    }

    if ($dueDate !== '' && !valid_date_value($dueDate)) {
        $errors['due_date'] = 'Enter a valid due date.';
    }

    if ($requestedDate !== '' && $dueDate !== '' && $dueDate < $requestedDate) {
        $errors['due_date'] = 'Due date cannot be earlier than the requested date.';
    }

    return $errors;
}

function save_job_payload(array $values, int $createdByUserId, ?string $currentStatus = null): array
{
    return [
        'task_id' => $values['task_id'] !== null ? (int) $values['task_id'] : null,
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

function save_task_payload(array $values, int $createdByUserId): array
{
    return [
        'customer_id' => (int) $values['customer_id'],
        'location_id' => $values['location_id'] !== null ? (int) $values['location_id'] : null,
        'title' => $values['title'],
        'description' => $values['description'] !== '' ? $values['description'] : null,
        'status' => $values['status'],
        'priority' => $values['priority'],
        'requested_date' => $values['requested_date'] !== '' ? $values['requested_date'] : null,
        'due_date' => $values['due_date'] !== '' ? $values['due_date'] : null,
        'created_by_user_id' => $createdByUserId,
    ];
}

function job_filter_values(array $source, array $viewer): array
{
    $status = trim((string) ($source['status'] ?? ''));
    $plannedDate = trim((string) ($source['planned_date'] ?? ''));
    $schedule = trim((string) ($source['schedule'] ?? ''));

    return [
        'search' => trim((string) ($source['search'] ?? '')),
        'status' => array_key_exists($status, job_status_options()) ? $status : '',
        'worker_id' => ($viewer['role'] ?? '') === 'worker' ? (int) $viewer['id'] : positive_int_or_null($source['worker_id'] ?? null),
        'customer_id' => positive_int_or_null($source['customer_id'] ?? null),
        'planned_date' => $plannedDate !== '' && valid_date_value($plannedDate) ? $plannedDate : '',
        'schedule' => $schedule === 'unscheduled' ? 'unscheduled' : '',
    ];
}

function task_filter_values(array $source): array
{
    $status = trim((string) ($source['status'] ?? ''));
    $priority = trim((string) ($source['priority'] ?? ''));
    $dueState = trim((string) ($source['due_state'] ?? ''));

    return [
        'search' => trim((string) ($source['search'] ?? '')),
        'status' => array_key_exists($status, task_status_options()) ? $status : '',
        'priority' => array_key_exists($priority, task_priority_options()) ? $priority : '',
        'customer_id' => positive_int_or_null($source['customer_id'] ?? null),
        'due_state' => array_key_exists($dueState, task_due_state_options()) ? $dueState : '',
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
                'attentionTasks' => dashboard_attention_tasks(),
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

        case $path === '/materials':
            require_role(['admin', 'dispatcher']);

            if ($method !== 'GET') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            $filters = material_filter_values($_GET);

            render('materials/index', [
                'pageTitle' => 'Materials',
                'materials' => list_materials($filters),
                'filters' => $filters,
                'successMessage' => flash('success'),
            ]);
            break;

        case $path === '/materials/create':
            require_role(['admin', 'dispatcher']);

            if ($method === 'POST') {
                $csrfToken = $_POST['_token'] ?? null;

                if (!verify_csrf_token(is_string($csrfToken) ? $csrfToken : null)) {
                    abort(419, 'Session expired', 'The form token is invalid or has expired.');
                }

                $values = material_form_values($_POST);
                $errors = validate_material_form($values);

                if ($errors !== []) {
                    render('materials/form', [
                        'pageTitle' => 'Create Material',
                        'formTitle' => 'Create Material',
                        'formAction' => '/materials/create',
                        'submitLabel' => 'Create Material',
                        'values' => $values,
                        'errors' => $errors,
                        'material' => null,
                    ], 422);
                    break;
                }

                $materialId = create_material(save_material_payload($values));
                flash('success', 'Material created successfully.');
                redirect('/materials/' . $materialId);
            }

            if ($method !== 'GET') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            render('materials/form', [
                'pageTitle' => 'Create Material',
                'formTitle' => 'Create Material',
                'formAction' => '/materials/create',
                'submitLabel' => 'Create Material',
                'values' => material_form_values([]),
                'errors' => [],
                'material' => null,
            ]);
            break;

        case preg_match('#^/materials/([1-9][0-9]*)$#', $path, $matches) === 1:
            require_role(['admin', 'dispatcher']);

            if ($method !== 'GET') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            $material = find_material_by_id((int) $matches[1]);

            if ($material === null) {
                not_found('Material');
            }

            render('materials/show', [
                'pageTitle' => $material['name'],
                'material' => $material,
                'successMessage' => flash('success'),
                'errorMessage' => flash('error'),
            ]);
            break;

        case preg_match('#^/materials/([1-9][0-9]*)/edit$#', $path, $matches) === 1:
            require_role(['admin', 'dispatcher']);

            $material = find_material_by_id((int) $matches[1]);

            if ($material === null) {
                not_found('Material');
            }

            if ($method === 'GET') {
                render('materials/form', [
                    'pageTitle' => 'Edit Material',
                    'formTitle' => 'Edit Material',
                    'formAction' => '/materials/' . $material['id'] . '/edit',
                    'submitLabel' => 'Save Changes',
                    'values' => material_form_values([], [
                        'name' => (string) $material['name'],
                        'sku' => (string) ($material['sku'] ?? ''),
                        'unit' => (string) $material['unit'],
                        'description' => (string) ($material['description'] ?? ''),
                        'is_active' => (int) ($material['is_active'] ?? 0) === 1 ? '1' : '0',
                    ]),
                    'errors' => [],
                    'material' => $material,
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

            $values = material_form_values($_POST);
            $errors = validate_material_form($values);

            if ($errors !== []) {
                render('materials/form', [
                    'pageTitle' => 'Edit Material',
                    'formTitle' => 'Edit Material',
                    'formAction' => '/materials/' . $material['id'] . '/edit',
                    'submitLabel' => 'Save Changes',
                    'values' => $values,
                    'errors' => $errors,
                    'material' => $material,
                ], 422);
                break;
            }

            update_material((int) $material['id'], save_material_payload($values));
            flash('success', 'Material updated successfully.');
            redirect('/materials/' . $material['id']);
            break;

        case preg_match('#^/materials/([1-9][0-9]*)/status$#', $path, $matches) === 1:
            require_role(['admin', 'dispatcher']);

            if ($method !== 'POST') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            $csrfToken = $_POST['_token'] ?? null;

            if (!verify_csrf_token(is_string($csrfToken) ? $csrfToken : null)) {
                abort(419, 'Session expired', 'The form token is invalid or has expired.');
            }

            $material = find_material_by_id((int) $matches[1]);

            if ($material === null) {
                not_found('Material');
            }

            $statusValue = (string) ($_POST['is_active'] ?? '');

            if (!in_array($statusValue, ['0', '1'], true)) {
                flash('error', 'Select a valid material status.');
                redirect('/materials/' . $material['id']);
            }

            set_material_active_status((int) $material['id'], $statusValue === '1');
            flash('success', $statusValue === '1' ? 'Material activated successfully.' : 'Material deactivated successfully.');
            redirect('/materials/' . $material['id']);
            break;

        case $path === '/tasks':
            require_role(['admin', 'dispatcher']);

            if ($method === 'POST') {
                $csrfToken = $_POST['_token'] ?? null;

                if (!verify_csrf_token(is_string($csrfToken) ? $csrfToken : null)) {
                    abort(419, 'Session expired', 'The form token is invalid or has expired.');
                }

                $values = task_form_values($_POST);
                $errors = validate_task_form($values);

                if ($errors !== []) {
                    render('tasks/form', [
                        'pageTitle' => 'Create Task',
                        'formTitle' => 'Create Task',
                        'formAction' => '/tasks',
                        'submitLabel' => 'Create Task',
                        'customers' => active_customers(),
                        'locations' => list_active_locations(),
                        'values' => $values,
                        'errors' => $errors,
                        'task' => null,
                    ], 422);
                    break;
                }

                $createdByUser = current_user();

                try {
                    $taskId = create_task(save_task_payload($values, (int) $createdByUser['id']));
                } catch (RuntimeException $exception) {
                    render('tasks/form', [
                        'pageTitle' => 'Create Task',
                        'formTitle' => 'Create Task',
                        'formAction' => '/tasks',
                        'submitLabel' => 'Create Task',
                        'customers' => active_customers(),
                        'locations' => list_active_locations(),
                        'values' => $values,
                        'errors' => ['task_number' => 'Could not generate a task number. Please try again.'],
                        'task' => null,
                    ], 422);
                    break;
                }

                flash('success', 'Task created successfully.');
                redirect('/tasks/' . $taskId);
            }

            if ($method !== 'GET') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            $filters = task_filter_values($_GET);

            render('tasks/index', [
                'pageTitle' => 'Tasks',
                'tasks' => list_tasks($filters),
                'filters' => $filters,
                'customers' => all_customers(),
                'successMessage' => flash('success'),
            ]);
            break;

        case $path === '/tasks/create':
            require_role(['admin', 'dispatcher']);

            if ($method !== 'GET') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            render('tasks/form', [
                'pageTitle' => 'Create Task',
                'formTitle' => 'Create Task',
                'formAction' => '/tasks',
                'submitLabel' => 'Create Task',
                'customers' => active_customers(),
                'locations' => list_active_locations(),
                'values' => task_form_values($_GET),
                'errors' => [],
                'task' => null,
            ]);
            break;

        case preg_match('#^/tasks/([1-9][0-9]*)$#', $path, $matches) === 1:
            require_role(['admin', 'dispatcher']);

            if ($method !== 'GET') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            $task = find_task_by_id((int) $matches[1]);

            if ($task === null) {
                not_found('Task');
            }

            render('tasks/show', [
                'pageTitle' => $task['task_number'],
                'task' => $task,
                'linkedJobs' => list_jobs_for_task((int) $task['id']),
                'successMessage' => flash('success'),
            ]);
            break;

        case preg_match('#^/tasks/([1-9][0-9]*)/edit$#', $path, $matches) === 1:
            require_role(['admin', 'dispatcher']);

            $task = find_task_by_id((int) $matches[1]);

            if ($task === null) {
                not_found('Task');
            }

            if ($method === 'GET') {
                render('tasks/form', [
                    'pageTitle' => 'Edit Task',
                    'formTitle' => 'Edit Task',
                    'formAction' => '/tasks/' . $task['id'] . '/edit',
                    'submitLabel' => 'Save Changes',
                    'customers' => active_customers(),
                    'locations' => list_active_locations(),
                    'values' => task_form_values([], [
                        'customer_id' => (int) $task['customer_id'],
                        'location_id' => $task['location_id'] !== null ? (int) $task['location_id'] : null,
                        'title' => (string) $task['title'],
                        'description' => (string) ($task['description'] ?? ''),
                        'status' => (string) $task['status'],
                        'priority' => (string) $task['priority'],
                        'requested_date' => (string) ($task['requested_date'] ?? ''),
                        'due_date' => (string) ($task['due_date'] ?? ''),
                    ]),
                    'errors' => [],
                    'task' => $task,
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

            $values = task_form_values($_POST);
            $errors = validate_task_form($values);

            if ($errors !== []) {
                render('tasks/form', [
                    'pageTitle' => 'Edit Task',
                    'formTitle' => 'Edit Task',
                    'formAction' => '/tasks/' . $task['id'] . '/edit',
                    'submitLabel' => 'Save Changes',
                    'customers' => active_customers(),
                    'locations' => list_active_locations(),
                    'values' => $values,
                    'errors' => $errors,
                    'task' => $task,
                ], 422);
                break;
            }

            update_task((int) $task['id'], save_task_payload($values, (int) ($task['created_by_user_id'] ?? current_user()['id'])));
            flash('success', 'Task updated successfully.');
            redirect('/tasks/' . $task['id']);
            break;

        case preg_match('#^/tasks/([1-9][0-9]*)/status$#', $path, $matches) === 1:
            require_role(['admin', 'dispatcher']);

            if ($method !== 'POST') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            $csrfToken = $_POST['_token'] ?? null;

            if (!verify_csrf_token(is_string($csrfToken) ? $csrfToken : null)) {
                abort(419, 'Session expired', 'The form token is invalid or has expired.');
            }

            $task = find_task_by_id((int) $matches[1]);

            if ($task === null) {
                not_found('Task');
            }

            $status = trim((string) ($_POST['status'] ?? ''));

            if (!array_key_exists($status, task_status_options())) {
                flash('success', 'Select a valid task status.');
                redirect('/tasks/' . $task['id']);
            }

            update_task_status((int) $task['id'], $status);
            flash('success', 'Task status updated successfully.');
            redirect('/tasks/' . $task['id']);
            break;

        case preg_match('#^/tasks/([1-9][0-9]*)/jobs/create$#', $path, $matches) === 1:
            require_role(['admin', 'dispatcher']);

            if ($method !== 'GET') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            $task = find_task_by_id((int) $matches[1]);

            if ($task === null) {
                not_found('Task');
            }

            redirect('/jobs/create?task_id=' . $task['id']);
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
                        'taskOptions' => list_tasks(),
                        'submitLabel' => 'Create Job',
                        'customers' => active_customers(),
                        'locations' => list_active_locations(),
                        'workers' => list_active_workers(),
                        'values' => $values,
                        'errors' => $errors,
                        'job' => null,
                        'taskContext' => $values['task_id'] !== null ? find_task_by_id((int) $values['task_id']) : null,
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
                        'taskOptions' => list_tasks(),
                        'submitLabel' => 'Create Job',
                        'customers' => active_customers(),
                        'locations' => list_active_locations(),
                        'workers' => list_active_workers(),
                        'values' => $values,
                        'errors' => ['job_number' => 'Could not generate a job number. Please try again.'],
                        'job' => null,
                        'taskContext' => $values['task_id'] !== null ? find_task_by_id((int) $values['task_id']) : null,
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

            $defaults = [];
            $preselectedTask = null;

            if (array_key_exists('task_id', $_GET) && $_GET['task_id'] !== '') {
                $taskId = positive_int_or_null($_GET['task_id']);

                if ($taskId === null) {
                    not_found('Task');
                }

                $preselectedTask = find_task_by_id($taskId);

                if ($preselectedTask === null) {
                    not_found('Task');
                }

                $defaults = [
                    'task_id' => (int) $preselectedTask['id'],
                    'customer_id' => (int) $preselectedTask['customer_id'],
                    'location_id' => $preselectedTask['location_id'] !== null ? (int) $preselectedTask['location_id'] : null,
                    'title' => (string) $preselectedTask['title'],
                    'description' => (string) ($preselectedTask['description'] ?? ''),
                    'priority' => (string) $preselectedTask['priority'],
                ];
            }

            render('jobs/form', [
                'pageTitle' => 'Create Job',
                'formTitle' => 'Create Job',
                'formAction' => '/jobs',
                'taskOptions' => list_tasks(),
                'submitLabel' => 'Create Job',
                'customers' => active_customers(),
                'locations' => list_active_locations(),
                'workers' => list_active_workers(),
                'values' => job_form_values($_GET, $defaults),
                'errors' => [],
                'job' => null,
                'taskContext' => $preselectedTask,
            ]);
            break;

        case $path === '/jobs/calendar':
            require_role(['admin', 'dispatcher', 'worker']);

            if ($method !== 'GET') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            $viewer = current_user();
            $calendarView = requested_calendar_view($_GET['view'] ?? null);
            $todayDate = new DateTimeImmutable('today');
            $selectedDate = requested_calendar_date($_GET['date'] ?? null);
            $selectedMonth = requested_calendar_month($_GET['month'] ?? null);
            $weekAnchorDate = $calendarView === 'month' ? $selectedMonth : $selectedDate;
            $weekStart = $weekAnchorDate->modify('-' . ((int) $weekAnchorDate->format('N') - 1) . ' days');
            $weekEnd = $weekStart->modify('+6 days');
            $monthStart = $selectedMonth->modify('first day of this month');
            $monthEnd = $selectedMonth->modify('last day of this month');
            $monthGridStart = $monthStart->modify('-' . ((int) $monthStart->format('N') - 1) . ' days');
            $monthGridEnd = $monthEnd->modify('+' . (7 - (int) $monthEnd->format('N')) . ' days');
            $queryStart = $calendarView === 'week' ? $weekStart : $monthGridStart;
            $queryEnd = $calendarView === 'week' ? $weekEnd : $monthGridEnd;
            $jobsByDate = [];

            foreach (find_jobs_for_calendar(
                jobs_connection(),
                $queryStart->format('Y-m-d'),
                $queryEnd->format('Y-m-d'),
                $viewer
            ) as $job) {
                $plannedDate = (string) ($job['planned_date'] ?? '');

                if ($plannedDate === '') {
                    continue;
                }

                $jobsByDate[$plannedDate] ??= [];
                $jobsByDate[$plannedDate][] = $job;
            }

            $weekDays = [];
            $weekPeriod = new DatePeriod(
                $weekStart,
                new DateInterval('P1D'),
                $weekEnd->modify('+1 day')
            );

            foreach ($weekPeriod as $day) {
                $dateKey = $day->format('Y-m-d');
                $weekDays[] = [
                    'date' => $day,
                    'date_key' => $dateKey,
                    'is_today' => $dateKey === $todayDate->format('Y-m-d'),
                    'jobs' => $jobsByDate[$dateKey] ?? [],
                ];
            }

            $calendarWeeks = [];
            $week = [];
            $monthGridPeriod = new DatePeriod(
                $monthGridStart,
                new DateInterval('P1D'),
                $monthGridEnd->modify('+1 day')
            );

            foreach ($monthGridPeriod as $day) {
                $dateKey = $day->format('Y-m-d');
                $week[] = [
                    'date' => $day,
                    'date_key' => $dateKey,
                    'is_current_month' => $day->format('Y-m') === $selectedMonth->format('Y-m'),
                    'is_today' => $dateKey === $todayDate->format('Y-m-d'),
                    'jobs' => $jobsByDate[$dateKey] ?? [],
                ];

                if (count($week) === 7) {
                    $calendarWeeks[] = $week;
                    $week = [];
                }
            }

            render('jobs/calendar', [
                'pageTitle' => 'Job Calendar',
                'calendarView' => $calendarView,
                'selectedDate' => $selectedDate,
                'selectedMonth' => $selectedMonth,
                'todayDate' => $todayDate,
                'weekStart' => $weekStart,
                'weekEnd' => $weekEnd,
                'previousWeek' => $weekStart->modify('-7 days'),
                'nextWeek' => $weekStart->modify('+7 days'),
                'previousMonth' => $selectedMonth->modify('-1 month'),
                'nextMonth' => $selectedMonth->modify('+1 month'),
                'weekDays' => $weekDays,
                'calendarWeeks' => $calendarWeeks,
                'unscheduledActiveJobsCount' => count_unscheduled_active_jobs(jobs_connection(), $viewer),
                'weekdayLabels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                'viewer' => $viewer,
            ]);
            break;

        case preg_match('#^/jobs/([1-9][0-9]*)/materials$#', $path, $matches) === 1:
            require_role(['admin', 'dispatcher']);

            if ($method !== 'POST') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            $csrfToken = $_POST['_token'] ?? null;

            if (!verify_csrf_token(is_string($csrfToken) ? $csrfToken : null)) {
                abort(419, 'Session expired', 'The form token is invalid or has expired.');
            }

            $viewer = current_user();
            $job = find_job_by_id((int) $matches[1], $viewer);

            if ($job === null) {
                not_found('Job');
            }

            if (!user_can_record_job_material($viewer, $job)) {
                abort(403, 'Access denied', 'You do not have permission to add materials to this job.');
            }

            $values = job_material_form_values($_POST);
            $errors = validate_job_material_create($values);

            if ($errors !== []) {
                render_job_show_page($job, $viewer, [
                    'materialUsageValues' => $values,
                    'materialUsageErrors' => $errors,
                ], false, 422);
                break;
            }

            add_job_material_usage(
                (int) $job['id'],
                (int) $values['material_id'],
                (string) normalize_quantity_value($values['quantity']),
                isset($viewer['id']) ? (int) $viewer['id'] : null
            );
            flash('success', 'Material usage recorded successfully.');
            redirect('/jobs/' . $job['id']);
            break;

        case preg_match('#^/jobs/([1-9][0-9]*)/materials/([1-9][0-9]*)/edit$#', $path, $matches) === 1:
            require_role(['admin', 'dispatcher']);

            if ($method !== 'POST') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            $csrfToken = $_POST['_token'] ?? null;

            if (!verify_csrf_token(is_string($csrfToken) ? $csrfToken : null)) {
                abort(419, 'Session expired', 'The form token is invalid or has expired.');
            }

            $viewer = current_user();
            $job = find_job_by_id((int) $matches[1], $viewer);

            if ($job === null) {
                not_found('Job');
            }

            $jobMaterial = find_job_material_by_id((int) $job['id'], (int) $matches[2]);

            if ($jobMaterial === null) {
                not_found('Job material');
            }

            if (!user_can_modify_job_material($viewer, $job)) {
                abort(403, 'Access denied', 'You do not have permission to update this material usage.');
            }

            $quantity = trim((string) ($_POST['quantity'] ?? ''));
            $error = validate_job_material_quantity($quantity);

            if ($error !== null) {
                render_job_show_page($job, $viewer, [
                    'materialEditValues' => [(int) $jobMaterial['id'] => ['quantity' => $quantity]],
                    'materialEditErrors' => [(int) $jobMaterial['id'] => ['quantity' => $error]],
                ], false, 422);
                break;
            }

            update_job_material_quantity(
                (int) $job['id'],
                (int) $jobMaterial['id'],
                (string) normalize_quantity_value($quantity),
                isset($viewer['id']) ? (int) $viewer['id'] : null
            );
            flash('success', 'Material quantity updated successfully.');
            redirect('/jobs/' . $job['id']);
            break;

        case preg_match('#^/jobs/([1-9][0-9]*)/materials/([1-9][0-9]*)/delete$#', $path, $matches) === 1:
            require_role(['admin', 'dispatcher']);

            if ($method !== 'POST') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            $csrfToken = $_POST['_token'] ?? null;

            if (!verify_csrf_token(is_string($csrfToken) ? $csrfToken : null)) {
                abort(419, 'Session expired', 'The form token is invalid or has expired.');
            }

            $viewer = current_user();
            $job = find_job_by_id((int) $matches[1], $viewer);

            if ($job === null) {
                not_found('Job');
            }

            $jobMaterial = find_job_material_by_id((int) $job['id'], (int) $matches[2]);

            if ($jobMaterial === null) {
                not_found('Job material');
            }

            if (!user_can_modify_job_material($viewer, $job)) {
                abort(403, 'Access denied', 'You do not have permission to remove this material usage.');
            }

            if (!delete_job_material((int) $job['id'], (int) $jobMaterial['id'])) {
                flash('error', 'The material usage could not be removed.');
                redirect('/jobs/' . $job['id']);
            }

            flash('success', 'Material usage removed successfully.');
            redirect('/jobs/' . $job['id']);
            break;

        case preg_match('#^/jobs/([1-9][0-9]*)/attachments$#', $path, $matches) === 1:
            require_role(['admin', 'dispatcher']);

            if ($method !== 'POST') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            $csrfToken = $_POST['_token'] ?? null;

            if (!verify_csrf_token(is_string($csrfToken) ? $csrfToken : null)) {
                abort(419, 'Session expired', 'The form token is invalid or has expired.');
            }

            $viewer = current_user();
            $job = find_job_by_id((int) $matches[1], $viewer);

            if ($job === null) {
                not_found('Job');
            }

            if (!user_can_upload_job_attachments($viewer)) {
                abort(403, 'Access denied', 'You do not have permission to upload attachments for this job.');
            }

            $attachmentError = validate_job_attachment_upload($_FILES['attachment'] ?? []);

            if ($attachmentError !== null) {
                render_job_show_page($job, $viewer, [
                    'attachmentError' => $attachmentError,
                ], false, 422);
                break;
            }

            store_uploaded_job_attachment((int) $job['id'], $_FILES['attachment'], (int) $viewer['id']);
            flash('success', 'Attachment uploaded successfully.');
            redirect('/jobs/' . $job['id']);
            break;

        case preg_match('#^/jobs/([1-9][0-9]*)/attachments/([1-9][0-9]*)/download$#', $path, $matches) === 1:
            require_role(['admin', 'dispatcher', 'worker']);

            if ($method !== 'GET') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            $viewer = current_user();
            $jobId = (int) $matches[1];
            $job = (($viewer['role'] ?? '') === 'worker')
                ? find_worker_accessible_job_by_id($jobId, $viewer)
                : find_job_by_id($jobId, $viewer);

            if ($job === null) {
                not_found('Job');
            }

            $attachment = find_job_attachment_by_id($jobId, (int) $matches[2]);

            if ($attachment === null) {
                not_found('Attachment');
            }

            send_stored_job_asset($attachment, 'attachment');
            break;

        case preg_match('#^/jobs/([1-9][0-9]*)/attachments/([1-9][0-9]*)/delete$#', $path, $matches) === 1:
            require_role(['admin', 'dispatcher']);

            if ($method !== 'POST') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            $csrfToken = $_POST['_token'] ?? null;

            if (!verify_csrf_token(is_string($csrfToken) ? $csrfToken : null)) {
                abort(419, 'Session expired', 'The form token is invalid or has expired.');
            }

            $viewer = current_user();
            $job = find_job_by_id((int) $matches[1], $viewer);

            if ($job === null) {
                not_found('Job');
            }

            if (!user_can_delete_job_attachments($viewer) || !delete_job_attachment((int) $job['id'], (int) $matches[2])) {
                flash('error', 'The attachment could not be removed.');
                redirect('/jobs/' . $job['id']);
            }

            flash('success', 'Attachment deleted successfully.');
            redirect('/jobs/' . $job['id']);
            break;

        case preg_match('#^/jobs/([1-9][0-9]*)/photos$#', $path, $matches) === 1:
            require_role(['admin', 'dispatcher']);

            if ($method !== 'POST') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            $csrfToken = $_POST['_token'] ?? null;

            if (!verify_csrf_token(is_string($csrfToken) ? $csrfToken : null)) {
                abort(419, 'Session expired', 'The form token is invalid or has expired.');
            }

            $viewer = current_user();
            $job = find_job_by_id((int) $matches[1], $viewer);

            if ($job === null) {
                not_found('Job');
            }

            if (!user_can_upload_job_photos($viewer, $job)) {
                abort(403, 'Access denied', 'You do not have permission to upload photos for this job.');
            }

            $photoCaption = job_photo_caption_value($_POST);
            $photoCaptionError = validate_job_photo_caption($photoCaption);
            $photoError = $photoCaptionError ?? validate_job_photo_upload($_FILES['photo'] ?? []);

            if ($photoError !== null) {
                render_job_show_page($job, $viewer, [
                    'photoError' => $photoError,
                    'photoCaption' => $photoCaption,
                    'photoCaptionError' => $photoCaptionError,
                ], false, 422);
                break;
            }

            store_uploaded_job_photo((int) $job['id'], $_FILES['photo'], (int) $viewer['id'], $photoCaption !== '' ? $photoCaption : null);
            flash('success', 'Photo uploaded successfully.');
            redirect('/jobs/' . $job['id']);
            break;

        case preg_match('#^/jobs/([1-9][0-9]*)/photos/([1-9][0-9]*)/view$#', $path, $matches) === 1:
            require_role(['admin', 'dispatcher', 'worker']);

            if ($method !== 'GET') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            $viewer = current_user();
            $jobId = (int) $matches[1];
            $job = (($viewer['role'] ?? '') === 'worker')
                ? find_worker_accessible_job_by_id($jobId, $viewer)
                : find_job_by_id($jobId, $viewer);

            if ($job === null) {
                not_found('Job');
            }

            $photo = find_job_photo_by_id($jobId, (int) $matches[2]);

            if ($photo === null) {
                not_found('Photo');
            }

            send_stored_job_asset($photo, 'inline');
            break;

        case preg_match('#^/jobs/([1-9][0-9]*)/photos/([1-9][0-9]*)/delete$#', $path, $matches) === 1:
            require_role(['admin', 'dispatcher']);

            if ($method !== 'POST') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            $csrfToken = $_POST['_token'] ?? null;

            if (!verify_csrf_token(is_string($csrfToken) ? $csrfToken : null)) {
                abort(419, 'Session expired', 'The form token is invalid or has expired.');
            }

            $viewer = current_user();
            $job = find_job_by_id((int) $matches[1], $viewer);

            if ($job === null) {
                not_found('Job');
            }

            if (!user_can_delete_job_photos($viewer, $job)) {
                abort(403, 'Access denied', 'This photo can no longer be deleted.');
            }

            if (!delete_job_photo((int) $job['id'], (int) $matches[2])) {
                flash('error', 'The photo could not be removed.');
                redirect('/jobs/' . $job['id']);
            }

            flash('success', 'Photo deleted successfully.');
            redirect('/jobs/' . $job['id']);
            break;

        case preg_match('#^/jobs/([1-9][0-9]*)/customer-confirmation$#', $path, $matches) === 1:
            require_role(['admin', 'dispatcher', 'worker']);

            if ($method !== 'POST') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            $csrfToken = $_POST['_token'] ?? null;

            if (!verify_csrf_token(is_string($csrfToken) ? $csrfToken : null)) {
                abort(419, 'Session expired', 'The form token is invalid or has expired.');
            }

            $viewer = current_user();
            $jobId = (int) $matches[1];
            $job = (($viewer['role'] ?? '') === 'worker')
                ? find_worker_accessible_job_by_id($jobId, $viewer)
                : find_job_by_id($jobId, $viewer);

            if ($job === null) {
                not_found('Job');
            }

            $values = customer_confirmation_form_values($_POST);
            $errors = validate_customer_confirmation_form($values, $job, $viewer);

            if ($errors !== []) {
                render_job_show_page($job, $viewer, [
                    'customerConfirmationValues' => $values,
                    'customerConfirmationErrors' => $errors,
                ], ((string) ($viewer['role'] ?? '')) === 'worker', 422);
                break;
            }

            create_job_customer_confirmation(
                (int) $job['id'],
                $values['customer_name'],
                $values['customer_email'] !== '' ? $values['customer_email'] : null,
                decoded_customer_confirmation_signature($values['signature_data']),
                (int) $viewer['id']
            );
            flash('success', 'Customer confirmation recorded successfully.');
            redirect(job_detail_route_for_user($viewer, (int) $job['id']));
            break;

        case preg_match('#^/jobs/([1-9][0-9]*)/customer-confirmation/signature$#', $path, $matches) === 1:
            require_role(['admin', 'dispatcher', 'worker']);

            if ($method !== 'GET') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            $viewer = current_user();
            $jobId = (int) $matches[1];
            $job = (($viewer['role'] ?? '') === 'worker')
                ? find_worker_accessible_job_by_id($jobId, $viewer)
                : find_job_by_id($jobId, $viewer);

            if ($job === null) {
                not_found('Job');
            }

            $confirmation = find_job_customer_confirmation($jobId);

            if ($confirmation === null) {
                not_found('Customer confirmation');
            }

            send_stored_job_asset(job_customer_confirmation_signature_asset($confirmation), 'inline');
            break;

        case preg_match('#^/jobs/([1-9][0-9]*)/customer-confirmation/delete$#', $path, $matches) === 1:
            require_role(['admin']);

            if ($method !== 'POST') {
                abort(405, 'Method not allowed', 'The requested method is not supported for this route.');
            }

            $csrfToken = $_POST['_token'] ?? null;

            if (!verify_csrf_token(is_string($csrfToken) ? $csrfToken : null)) {
                abort(419, 'Session expired', 'The form token is invalid or has expired.');
            }

            $viewer = current_user();
            $job = find_job_by_id((int) $matches[1], $viewer);

            if ($job === null) {
                not_found('Job');
            }

            if (!user_can_delete_job_customer_confirmation($viewer)) {
                abort(403, 'Access denied', 'You do not have permission to remove customer confirmation.');
            }

            if (!delete_job_customer_confirmation((int) $job['id'])) {
                flash('error', 'The customer confirmation could not be removed.');
                redirect('/jobs/' . $job['id']);
            }

            flash('success', 'Customer confirmation removed successfully.');
            redirect('/jobs/' . $job['id']);
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

            render_job_show_page($job, current_user());
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
                    'taskOptions' => list_tasks(),
                    'submitLabel' => 'Save Changes',
                    'customers' => active_customers(),
                    'locations' => list_active_locations(),
                    'workers' => list_active_workers(),
                    'values' => job_form_values([], [
                        'task_id' => $job['task_id'] !== null ? (int) $job['task_id'] : null,
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
                    'taskContext' => $job['task_id'] !== null ? find_task_by_id((int) $job['task_id']) : null,
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
                    'taskOptions' => list_tasks(),
                    'submitLabel' => 'Save Changes',
                    'customers' => active_customers(),
                    'locations' => list_active_locations(),
                    'workers' => list_active_workers(),
                    'values' => $values,
                    'errors' => $errors,
                    'job' => $job,
                    'taskContext' => $values['task_id'] !== null ? find_task_by_id((int) $values['task_id']) : null,
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

            render_job_show_page($job, $viewer, [], true);
            break;

        case preg_match('#^/work/jobs/([1-9][0-9]*)/materials$#', $path, $matches) === 1:
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

            if (!user_can_record_job_material($viewer, $job)) {
                abort(403, 'Access denied', 'You do not have permission to add materials to this job.');
            }

            $values = job_material_form_values($_POST);
            $errors = validate_job_material_create($values);

            if ($errors !== []) {
                render_job_show_page($job, $viewer, [
                    'materialUsageValues' => $values,
                    'materialUsageErrors' => $errors,
                ], true, 422);
                break;
            }

            add_job_material_usage(
                (int) $job['id'],
                (int) $values['material_id'],
                (string) normalize_quantity_value($values['quantity']),
                isset($viewer['id']) ? (int) $viewer['id'] : null
            );
            flash('success', 'Material usage recorded successfully.');
            redirect('/work/jobs/' . $job['id']);
            break;

        case preg_match('#^/work/jobs/([1-9][0-9]*)/materials/([1-9][0-9]*)/edit$#', $path, $matches) === 1:
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

            $jobMaterial = find_job_material_by_id((int) $job['id'], (int) $matches[2]);

            if ($jobMaterial === null) {
                not_found('Job material');
            }

            if (!user_can_modify_job_material($viewer, $job)) {
                abort(403, 'Access denied', 'This material usage can no longer be updated.');
            }

            $quantity = trim((string) ($_POST['quantity'] ?? ''));
            $error = validate_job_material_quantity($quantity);

            if ($error !== null) {
                render_job_show_page($job, $viewer, [
                    'materialEditValues' => [(int) $jobMaterial['id'] => ['quantity' => $quantity]],
                    'materialEditErrors' => [(int) $jobMaterial['id'] => ['quantity' => $error]],
                ], true, 422);
                break;
            }

            update_job_material_quantity(
                (int) $job['id'],
                (int) $jobMaterial['id'],
                (string) normalize_quantity_value($quantity),
                isset($viewer['id']) ? (int) $viewer['id'] : null
            );
            flash('success', 'Material quantity updated successfully.');
            redirect('/work/jobs/' . $job['id']);
            break;

        case preg_match('#^/work/jobs/([1-9][0-9]*)/materials/([1-9][0-9]*)/delete$#', $path, $matches) === 1:
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

            $jobMaterial = find_job_material_by_id((int) $job['id'], (int) $matches[2]);

            if ($jobMaterial === null) {
                not_found('Job material');
            }

            if (!user_can_modify_job_material($viewer, $job)) {
                abort(403, 'Access denied', 'This material usage can no longer be removed.');
            }

            if (!delete_job_material((int) $job['id'], (int) $jobMaterial['id'])) {
                flash('error', 'The material usage could not be removed.');
                redirect('/work/jobs/' . $job['id']);
            }

            flash('success', 'Material usage removed successfully.');
            redirect('/work/jobs/' . $job['id']);
            break;

        case preg_match('#^/work/jobs/([1-9][0-9]*)/photos$#', $path, $matches) === 1:
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

            if (!user_can_upload_job_photos($viewer, $job)) {
                abort(403, 'Access denied', 'You do not have permission to upload photos for this job.');
            }

            $photoCaption = job_photo_caption_value($_POST);
            $photoCaptionError = validate_job_photo_caption($photoCaption);
            $photoError = $photoCaptionError ?? validate_job_photo_upload($_FILES['photo'] ?? []);

            if ($photoError !== null) {
                render_job_show_page($job, $viewer, [
                    'photoError' => $photoError,
                    'photoCaption' => $photoCaption,
                    'photoCaptionError' => $photoCaptionError,
                ], true, 422);
                break;
            }

            store_uploaded_job_photo((int) $job['id'], $_FILES['photo'], (int) $viewer['id'], $photoCaption !== '' ? $photoCaption : null);
            flash('success', 'Photo uploaded successfully.');
            redirect('/work/jobs/' . $job['id']);
            break;

        case preg_match('#^/work/jobs/([1-9][0-9]*)/photos/([1-9][0-9]*)/delete$#', $path, $matches) === 1:
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

            if (!user_can_delete_job_photos($viewer, $job)) {
                abort(403, 'Access denied', 'This photo can no longer be deleted.');
            }

            if (!delete_job_photo((int) $job['id'], (int) $matches[2])) {
                flash('error', 'The photo could not be removed.');
                redirect('/work/jobs/' . $job['id']);
            }

            flash('success', 'Photo deleted successfully.');
            redirect('/work/jobs/' . $job['id']);
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
                render_job_show_page($job, $viewer, [
                    'noteValue' => $noteValue,
                    'noteError' => $noteError,
                ], true, 422);
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
