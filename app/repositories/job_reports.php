<?php

declare(strict_types=1);

function job_report_language_options(): array
{
    return [
        'lv' => 'Latvian',
        'en' => 'English',
    ];
}

function job_reports_connection(): PDO
{
    $connection = database_connection();

    if ($connection === null) {
        throw new RuntimeException('Database connection is unavailable.');
    }

    return $connection;
}

function job_reports_table_exists(string $table): bool
{
    $statement = job_reports_connection()->prepare(
        'SELECT 1
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
         LIMIT 1'
    );
    $statement->execute(['table_name' => $table]);

    return $statement->fetchColumn() !== false;
}

function job_report_language_label(string $language): string
{
    return match ($language) {
        'lv' => 'Latviešu',
        default => 'English',
    };
}

function validate_job_report_language(?string $value): ?string
{
    $language = is_string($value) ? trim($value) : '';

    return array_key_exists($language, job_report_language_options())
        ? null
        : 'Select a valid report language.';
}

function user_can_generate_job_report(array $user, array $job): bool
{
    $role = (string) ($user['role'] ?? '');
    $activeCompanyId = current_company_id();

    if (!in_array($role, ['admin', 'dispatcher'], true)) {
        return false;
    }

    return $activeCompanyId !== null
        && (int) $activeCompanyId === (int) ($job['company_id'] ?? 0);
}

function job_can_generate_report(array $job): bool
{
    return (string) ($job['status'] ?? '') === 'completed';
}

function job_report_download_name(array $job, string $language): string
{
    $jobNumber = preg_replace('/[^A-Za-z0-9\-]+/', '-', (string) ($job['job_number'] ?? 'job')) ?: 'job';

    return $jobNumber . '-report-' . $language . '.pdf';
}

function build_job_report_payload(array $job, string $language): array
{
    $company = find_company_by_id((int) $job['company_id']);

    if ($company === null) {
        throw new RuntimeException('The company for this job could not be found.');
    }

    $task = ($job['task_id'] ?? null) !== null ? find_task_by_id((int) $job['task_id']) : null;
    $notes = list_job_notes((int) $job['id']);
    $materials = list_job_materials((int) $job['id']);
    $deviceInstallations = list_job_device_installations((int) $job['id']);
    $confirmation = find_job_customer_confirmation((int) $job['id']);
    $labels = job_report_labels($language);
    $snapshotData = build_job_report_snapshot_data($job, $task, $notes, $materials, $deviceInstallations, $confirmation, $company);

    return [
        'language' => $language,
        'labels' => $labels,
        'title' => $labels['report_title'],
        'generated_at' => gmdate('Y-m-d H:i:s'),
        'job' => $job,
        'task' => $task,
        'company' => $company,
        'customer_rows' => job_report_customer_rows($job, $labels),
        'performed_work' => job_report_performed_work($job, $task, $notes),
        'work_notes' => job_report_work_notes($job, $notes, $language),
        'materials' => job_report_material_rows($materials, $deviceInstallations, $labels),
        'comments' => job_report_comment_rows($notes, $labels),
        'confirmation' => $confirmation,
        'snapshot' => $snapshotData,
        'worker' => [
            'id' => (int) ($job['assigned_user_id'] ?? 0),
            'name' => trim((string) ($job['assigned_worker_name'] ?? '')),
            'email' => trim((string) ($job['assigned_worker_email'] ?? '')),
            'signature_path' => (string) ($job['assigned_worker_signature_path'] ?? ''),
            'signature_mime_type' => (string) ($job['assigned_worker_signature_mime_type'] ?? ''),
            'signature_file_size' => (int) ($job['assigned_worker_signature_file_size'] ?? 0),
            'company_name' => (string) ($company['name'] ?? ''),
        ],
    ];
}

function stream_generated_job_report(array $job, string $language, ?int $createdByUserId = null): never
{
    $payload = build_job_report_payload($job, $language);
    persist_job_report_snapshot((int) $job['id'], (int) $job['company_id'], $payload['snapshot'], $createdByUserId);
    $pdfBinary = render_job_report_pdf($payload);
    $downloadName = job_report_download_name($job, $language);

    header('Content-Type: application/pdf');
    header('Content-Length: ' . strlen($pdfBinary));
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: attachment; filename="' . addcslashes($downloadName, "\"\\") . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));

    echo $pdfBinary;
    exit;
}

function find_job_report_snapshot(int $jobId): ?array
{
    if (!job_reports_table_exists('job_report_snapshots')) {
        return null;
    }

    $statement = job_reports_connection()->prepare(
        'SELECT id, company_id, job_id, snapshot_json, report_version, created_by_user_id, created_at
         FROM job_report_snapshots
         WHERE job_id = :job_id
         LIMIT 1'
    );
    $statement->execute(['job_id' => $jobId]);
    $snapshot = $statement->fetch(PDO::FETCH_ASSOC);

    return is_array($snapshot) ? $snapshot : null;
}

function persist_job_report_snapshot(int $jobId, int $companyId, array $snapshot, ?int $createdByUserId): void
{
    if (!job_reports_table_exists('job_report_snapshots')) {
        return;
    }

    if (find_job_report_snapshot($jobId) !== null) {
        return;
    }

    $encodedSnapshot = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $statement = job_reports_connection()->prepare(
        'INSERT INTO job_report_snapshots (
            company_id,
            job_id,
            snapshot_json,
            report_version,
            created_by_user_id
        ) VALUES (
            :company_id,
            :job_id,
            :snapshot_json,
            :report_version,
            :created_by_user_id
        )'
    );

    try {
        $statement->execute([
            'company_id' => $companyId,
            'job_id' => $jobId,
            'snapshot_json' => $encodedSnapshot,
            'report_version' => 1,
            'created_by_user_id' => $createdByUserId,
        ]);
    } catch (PDOException $exception) {
        $errorInfo = $exception->errorInfo;
        $duplicateKey = ($exception->getCode() === '23000' || ($errorInfo[0] ?? null) === '23000');

        if (!$duplicateKey) {
            throw $exception;
        }
    }
}

function build_job_report_snapshot_data(
    array $job,
    ?array $task,
    array $notes,
    array $materials,
    array $deviceInstallations,
    ?array $confirmation,
    array $company
): array {
    $customer = [
        'name' => (string) ($job['customer_name'] ?? ''),
        'registration_number' => (string) ($job['customer_registration_number'] ?? ''),
        'location_name' => (string) ($job['location_name'] ?? ''),
        'full_address' => (string) (location_address($job) ?? ''),
        'contact_person' => trim((string) ($job['location_contact_name'] ?? '')) !== ''
            ? (string) $job['location_contact_name']
            : (string) ($job['customer_contact_name'] ?? ''),
        'contact_phone' => trim((string) ($job['location_contact_phone'] ?? '')) !== ''
            ? (string) $job['location_contact_phone']
            : (string) ($job['customer_contact_phone'] ?? ''),
        'contact_email' => (string) ($job['customer_contact_email'] ?? ''),
    ];

    return [
        'company' => [
            'name' => (string) ($company['name'] ?? ''),
            'registration_number' => (string) ($company['registration_number'] ?? ''),
            'address' => (string) ($company['address'] ?? ''),
            'email' => (string) ($company['email'] ?? ''),
        ],
        'job' => [
            'id' => (int) ($job['id'] ?? 0),
            'company_id' => (int) ($job['company_id'] ?? 0),
            'job_number' => (string) ($job['job_number'] ?? ''),
            'title' => (string) ($job['title'] ?? ''),
            'description' => (string) ($job['description'] ?? ''),
            'planned_date' => (string) ($job['planned_date'] ?? ''),
            'planned_start_time' => (string) ($job['planned_start_time'] ?? ''),
            'actual_completed_at' => (string) ($job['actual_completed_at'] ?? ''),
            'assigned_user_id' => (int) ($job['assigned_user_id'] ?? 0),
            'assigned_worker_name' => (string) ($job['assigned_worker_name'] ?? ''),
            'assigned_worker_email' => (string) ($job['assigned_worker_email'] ?? ''),
            'job_type' => (string) ($job['job_type'] ?? ''),
            'status' => (string) ($job['status'] ?? ''),
        ],
        'task' => $task === null ? null : [
            'id' => (int) ($task['id'] ?? 0),
            'task_number' => (string) ($task['task_number'] ?? ''),
            'title' => (string) ($task['title'] ?? ''),
            'description' => (string) ($task['description'] ?? ''),
            'status' => (string) ($task['status'] ?? ''),
        ],
        'customer' => $customer,
        'notes' => array_map(
            static fn (array $note): array => [
                'author_name' => (string) ($note['author_name'] ?? ''),
                'note' => (string) ($note['note'] ?? ''),
                'created_at' => (string) ($note['created_at'] ?? ''),
            ],
            $notes
        ),
        'materials' => job_report_material_rows($materials, $deviceInstallations, job_report_labels('en')),
        'confirmation' => $confirmation === null ? null : [
            'customer_name' => (string) ($confirmation['customer_name'] ?? ''),
            'customer_email' => (string) ($confirmation['customer_email'] ?? ''),
            'signature_path' => (string) ($confirmation['signature_path'] ?? ''),
            'signature_mime_type' => (string) ($confirmation['signature_mime_type'] ?? ''),
            'confirmed_at' => (string) ($confirmation['confirmed_at'] ?? ''),
        ],
        'worker' => [
            'id' => (int) ($job['assigned_user_id'] ?? 0),
            'name' => trim((string) ($job['assigned_worker_name'] ?? '')),
            'email' => trim((string) ($job['assigned_worker_email'] ?? '')),
            'signature_path' => (string) ($job['assigned_worker_signature_path'] ?? ''),
            'signature_mime_type' => (string) ($job['assigned_worker_signature_mime_type'] ?? ''),
            'signature_file_size' => (int) ($job['assigned_worker_signature_file_size'] ?? 0),
            'company_name' => (string) ($company['name'] ?? ''),
        ],
    ];
}

function job_report_labels(string $language): array
{
    if ($language === 'lv') {
        return [
            'report_title' => 'Darbu pieņemšanas-nodošanas akts',
            'report_identification' => 'Atskaites informācija',
            'company_section' => 'Pakalpojuma sniedzējs',
            'customer_section' => 'Klienta informācija',
            'job_section' => 'Darba informācija',
            'work_section' => 'Darba uzdevums',
            'work_notes_section' => 'Darba piezīmes',
            'time_column' => 'Laiks',
            'note_column' => 'Piezīme',
            'materials_section' => 'Materiāli un ierīces',
            'comments_section' => 'Komentāri',
            'confirmation_section' => 'Apstiprinājums',
            'worker_column' => 'Darbinieks',
            'customer_column' => 'Klienta pārstāvis',
            'company_name' => 'Uzņēmums',
            'registration_number' => 'Reģistrācijas numurs',
            'legal_address' => 'Juridiskā adrese',
            'email' => 'E-pasts',
            'report_number' => 'Darba numurs',
            'completion_datetime' => 'Pabeigts',
            'customer_name' => 'Klients',
            'customer_registration_number' => 'Reģistrācijas numurs',
            'location_name' => 'Objekts',
            'full_address' => 'Objekta adrese',
            'contact_person' => 'Kontaktpersona',
            'contact_phone' => 'Tālrunis',
            'contact_email' => 'Kontaktpersonas e-pasts',
            'job_title' => 'Darba nosaukums',
            'job_description' => 'Apraksts',
            'scheduled_datetime' => 'Plānotais laiks',
            'task_reference' => 'Saistītais uzdevums',
            'assigned_workers' => 'Atbildīgais darbinieks',
            'material_name' => 'Materiāls',
            'sku' => 'SKU',
            'quantity' => 'Daudzums',
            'movement_type' => 'Darbība',
            'object_name' => 'Objekta nosaukums',
            'device_identifier' => 'Ierīces ID',
            'accessories' => 'Piederumi',
            'used' => 'Izlietots / Uzstādīts',
            'returned' => 'Atgriezts / Noņemts',
            'none_recorded' => 'Nav ierakstu.',
            'not_captured' => 'Nav fiksēts',
            'signature' => 'Paraksts',
            'signed_at' => 'Apstiprināts',
            'legal_note' => 'Dokuments parakstīts elektroniski Izpildītāja darbu vadības sistēmā. Puses atzīst šādā veidā sagatavotu un parakstītu aktu par juridiski saistošu un spēkā esošu.',
        ];
    }

    return [
        'report_title' => 'Work Completion Report',
        'report_identification' => 'Report Identification',
        'company_section' => 'Service provider',
        'customer_section' => 'Customer Information',
        'job_section' => 'Job Information',
        'work_section' => 'Performed Work',
        'work_notes_section' => 'Work notes',
        'time_column' => 'Time',
        'note_column' => 'Note',
        'materials_section' => 'Materials and Devices',
        'comments_section' => 'Comments',
        'confirmation_section' => 'Confirmation',
        'worker_column' => 'Worker',
        'customer_column' => 'Customer Representative',
        'company_name' => 'Company',
        'registration_number' => 'Registration Number',
        'legal_address' => 'Legal Address',
        'email' => 'Email',
        'report_number' => 'Job Number',
        'completion_datetime' => 'Completed At',
        'customer_name' => 'Customer',
        'customer_registration_number' => 'Registration Number',
        'location_name' => 'Location',
        'full_address' => 'Site Address',
        'contact_person' => 'Contact Person',
        'contact_phone' => 'Contact Phone',
        'contact_email' => 'Contact Email',
        'job_title' => 'Job Title',
        'job_description' => 'Description',
        'scheduled_datetime' => 'Scheduled Time',
        'task_reference' => 'Linked Task',
        'assigned_workers' => 'Assigned Worker',
        'material_name' => 'Material',
        'sku' => 'SKU',
        'quantity' => 'Quantity',
        'movement_type' => 'Movement',
        'object_name' => 'Object Name',
        'device_identifier' => 'Device ID',
        'accessories' => 'Accessories',
        'used' => 'Used / Installed',
        'returned' => 'Returned / Removed',
        'none_recorded' => 'No records.',
        'not_captured' => 'Not captured',
        'signature' => 'Signature',
        'signed_at' => 'Signed at',
        'legal_note' => 'This document was electronically signed in the Contractor work management system. The parties acknowledge this prepared and signed acceptance act as legally binding and in force.',
    ];
}

function job_report_customer_rows(array $job, array $labels): array
{
    $rows = [];
    $contactName = trim((string) ($job['location_contact_name'] ?? '')) !== ''
        ? (string) $job['location_contact_name']
        : (string) ($job['customer_contact_name'] ?? '');
    $contactPhone = trim((string) ($job['location_contact_phone'] ?? '')) !== ''
        ? (string) $job['location_contact_phone']
        : (string) ($job['customer_contact_phone'] ?? '');

    job_report_add_row($rows, $labels['customer_name'], (string) ($job['customer_name'] ?? ''));
    job_report_add_row($rows, $labels['customer_registration_number'], (string) ($job['customer_registration_number'] ?? ''));
    job_report_add_row($rows, $labels['location_name'], (string) ($job['location_name'] ?? ''));
    job_report_add_row($rows, $labels['full_address'], (string) (location_address($job) ?? ''));
    job_report_add_row($rows, $labels['contact_person'], $contactName);
    job_report_add_row($rows, $labels['contact_phone'], $contactPhone);
    job_report_add_row($rows, $labels['contact_email'], (string) ($job['customer_contact_email'] ?? ''));

    return $rows;
}

function job_report_job_rows(array $job, ?array $task, array $labels, string $language): array
{
    $rows = [];

    job_report_add_row($rows, $labels['job_title'], (string) ($job['title'] ?? ''));
    job_report_add_row($rows, $labels['job_description'], trim((string) ($job['description'] ?? '')));
    job_report_add_row($rows, $labels['scheduled_datetime'], job_report_scheduled_datetime($job, $language));
    job_report_add_row($rows, $labels['assigned_workers'], (string) ($job['assigned_worker_name'] ?? ''));

    if ($task !== null) {
        job_report_add_row(
            $rows,
            $labels['task_reference'],
            trim((string) ($task['task_number'] ?? ''))
        );
    }

    return $rows;
}

function job_report_performed_work(array $job, ?array $task, array $notes): array
{
    $items = [];

    $description = trim((string) ($job['description'] ?? ''));

    if ($description !== '') {
        $items[] = $description;
    }

    return $items;
}

function job_report_work_notes(array $job, array $notes, string $language): array
{
    $items = [];
    $showAuthor = job_report_should_show_note_author($job, $notes);

    foreach ($notes as $note) {
        $noteText = trim((string) ($note['note'] ?? ''));

        if ($noteText === '') {
            continue;
        }

        $timestamp = job_report_format_datetime((string) ($note['created_at'] ?? ''), $language, true);
        $author = $showAuthor ? job_report_author_short_label((string) ($note['author_name'] ?? '')) : '';
        $meta = trim(implode('  ', array_filter([$timestamp, $author], static fn (string $value): bool => $value !== '')));

        $items[] = [
            'meta' => $meta,
            'text' => $noteText,
        ];
    }

    return $items;
}

function job_report_comment_rows(array $notes, array $labels): array
{
    return [];
}

function job_report_material_rows(array $materials, array $deviceInstallations, array $labels): array
{
    $rows = [];
    $linkedAccessoryUsageIds = job_report_linked_accessory_usage_ids($deviceInstallations);

    foreach ($materials as $material) {
        $usageId = (int) ($material['id'] ?? 0);

        if (isset($linkedAccessoryUsageIds[$usageId])) {
            continue;
        }

        $installation = $deviceInstallations[$usageId] ?? null;
        $accessories = [];

        if ($installation !== null) {
            foreach (($installation['accessories'] ?? []) as $accessory) {
                $accessories[] = trim(
                    ((string) ($accessory['accessory_material_sku'] ?? '') !== ''
                        ? (string) $accessory['accessory_material_sku']
                        : (string) ($accessory['accessory_material_name'] ?? ''))
                    . ' x '
                    . format_decimal_quantity($accessory['quantity'] ?? '0')
                );
            }
        }

        $rows[] = [
            'material' => (string) ($material['material_name'] ?? ''),
            'sku' => (string) ($material['material_sku'] ?? ''),
            'quantity' => format_decimal_quantity($material['quantity'] ?? '0') . ' ' . (string) ($material['material_unit'] ?? ''),
            'movement' => (string) ($material['entry_type'] ?? '') === 'returned' ? $labels['returned'] : $labels['used'],
            'object_name' => $installation['object_name'] ?? '',
            'device_identifier' => $installation['device_identifier']
                ?? (string) ($material['device_identifier'] ?? ''),
            'accessories' => $accessories,
        ];
    }

    return $rows;
}

function job_report_linked_accessory_usage_ids(array $deviceInstallations): array
{
    $usageIds = [];

    foreach ($deviceInstallations as $installation) {
        foreach (($installation['accessories'] ?? []) as $accessory) {
            $usageId = (int) ($accessory['accessory_material_usage_id'] ?? 0);

            if ($usageId > 0) {
                $usageIds[$usageId] = true;
            }
        }
    }

    return $usageIds;
}

function job_report_add_row(array &$rows, string $label, string $value): void
{
    $normalized = trim($value);

    if ($normalized === '') {
        return;
    }

    $rows[] = [
        'label' => $label,
        'value' => $normalized,
    ];
}

function job_report_scheduled_datetime(array $job, string $language = 'lv'): string
{
    $date = trim((string) ($job['planned_date'] ?? ''));
    $time = trim((string) ($job['planned_start_time'] ?? ''));

    if ($date === '' && $time === '') {
        return '';
    }

    return trim(job_report_format_local_datetime($date, $time, $language));
}

function job_report_format_datetime(string $value, string $language, bool $timeOnly = false): string
{
    $normalized = trim($value);

    if ($normalized === '') {
        return '';
    }

    try {
        $utc = new DateTimeZone('UTC');
        $riga = new DateTimeZone('Europe/Riga');
        $datetime = new DateTimeImmutable($normalized, $utc);
        $datetime = $datetime->setTimezone($riga);
    } catch (Exception) {
        return $normalized;
    }

    if ($timeOnly) {
        return $datetime->format('H:i');
    }

    return $language === 'lv'
        ? $datetime->format('d.m.Y H:i')
        : $datetime->format('d M Y H:i');
}

function job_report_format_time(string $value): string
{
    $timestamp = strtotime($value);

    return $timestamp === false ? trim($value) : date('H:i', $timestamp);
}

function job_report_format_local_datetime(string $date, string $time, string $language = 'lv'): string
{
    $date = trim($date);
    $time = trim($time);

    if ($date === '' && $time === '') {
        return '';
    }

    if ($date === '') {
        return job_report_format_time($time);
    }

    if ($time === '') {
        $timestamp = strtotime($date);

        if ($timestamp === false) {
            return $date;
        }

        return $language === 'lv' ? date('d.m.Y', $timestamp) : date('d M Y', $timestamp);
    }

    $timestamp = strtotime($date . ' ' . $time);

    if ($timestamp === false) {
        return trim($date . ' ' . job_report_format_time($time));
    }

    return $language === 'lv'
        ? date('d.m.Y H:i', $timestamp)
        : date('d M Y H:i', $timestamp);
}

function job_report_should_show_note_author(array $job, array $notes): bool
{
    $workerName = mb_strtolower(trim((string) ($job['assigned_worker_name'] ?? '')));
    $authors = [];

    foreach ($notes as $note) {
        $author = trim((string) ($note['author_name'] ?? ''));

        if ($author === '') {
            continue;
        }

        $authors[mb_strtolower($author)] = true;
    }

    if (count($authors) > 1) {
        return true;
    }

    if ($authors === []) {
        return false;
    }

    $authorName = array_key_first($authors);

    return $workerName === '' || $authorName !== $workerName;
}

function job_report_author_short_label(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    $initials = '';

    foreach ($parts as $part) {
        $initials .= mb_strtoupper(mb_substr($part, 0, 1));
    }

    return $initials !== '' ? $initials : trim($name);
}

function render_job_report_pdf(array $payload): string
{
    $renderer = job_report_renderer_create($payload);

    job_report_renderer_draw_header($renderer, $payload);
    job_report_renderer_draw_two_column_summary(
        $renderer,
        [
            'title' => $payload['labels']['company_section'],
            'rows' => [
                ['label' => $payload['labels']['company_name'], 'value' => (string) ($payload['company']['name'] ?? '')],
                ['label' => $payload['labels']['registration_number'], 'value' => (string) ($payload['company']['registration_number'] ?? '')],
                ['label' => $payload['labels']['legal_address'], 'value' => (string) ($payload['company']['address'] ?? '')],
                ['label' => $payload['labels']['email'], 'value' => (string) ($payload['company']['email'] ?? '')],
            ],
        ],
        [
            'title' => $payload['labels']['customer_section'],
            'rows' => $payload['customer_rows'],
        ]
    );
    job_report_renderer_draw_work_section($renderer, $payload['labels']['work_section'], $payload['performed_work'], $payload['labels']['none_recorded']);
    job_report_renderer_draw_work_notes_section($renderer, $payload['labels']['work_notes_section'], $payload['work_notes'], $payload['labels']);

    if ($payload['materials'] !== []) {
        job_report_renderer_draw_material_section($renderer, $payload['labels']['materials_section'], $payload['materials'], $payload['labels']);
    }

    if ($payload['comments'] !== []) {
        job_report_renderer_draw_work_section($renderer, $payload['labels']['comments_section'], $payload['comments'], $payload['labels']['none_recorded']);
    }

    job_report_renderer_draw_confirmation_section($renderer, $payload);
    job_report_renderer_draw_legal_note($renderer, (string) ($payload['labels']['legal_note'] ?? ''));

    return job_report_images_to_pdf(job_report_renderer_export_pages($renderer));
}

function job_report_renderer_create(array $payload): array
{
    $fontRegular = job_report_font_path();
    $fontBold = $fontRegular;
    $pageWidth = 1240;
    $pageHeight = 1754;
    $margin = 86;
    $page = imagecreatetruecolor($pageWidth, $pageHeight);

    if (function_exists('imageantialias')) {
        imageantialias($page, true);
    }

    $white = imagecolorallocate($page, 255, 255, 255);
    imagefilledrectangle($page, 0, 0, $pageWidth, $pageHeight, $white);

    return [
        'pages' => [$page],
        'current_page' => 0,
        'page_width' => $pageWidth,
        'page_height' => $pageHeight,
        'margin' => $margin,
        'footer_margin' => 62,
        'y' => $margin,
        'font_regular' => $fontRegular,
        'font_bold' => $fontBold,
        'colors' => [
            'white' => $white,
            'text' => imagecolorallocate($page, 34, 39, 46),
            'muted' => imagecolorallocate($page, 108, 117, 125),
            'line' => imagecolorallocate($page, 224, 228, 233),
            'accent' => imagecolorallocate($page, 65, 109, 181),
            'soft' => imagecolorallocate($page, 247, 249, 252),
            'panel' => imagecolorallocate($page, 250, 251, 253),
        ],
        'report_title' => (string) ($payload['title'] ?? ''),
        'job_number' => (string) ($payload['job']['job_number'] ?? ''),
    ];
}

function job_report_font_path(): string
{
    static $fontPath = null;

    if ($fontPath !== null) {
        return $fontPath;
    }

    $candidates = [
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSansCondensed.ttf',
        '/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
        '/usr/share/fonts/truetype/noto/NotoSans-Regular.ttf',
        '/usr/share/fonts/opentype/noto/NotoSans-Regular.ttf',
        '/usr/share/fonts/truetype/freefont/FreeSans.ttf',
        '/Library/Fonts/Arial Unicode.ttf',
        '/System/Library/Fonts/Supplemental/Arial Unicode.ttf',
        '/System/Library/Fonts/Supplemental/Arial.ttf',
        '/System/Library/Fonts/Supplemental/Helvetica.ttf',
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate) && is_readable($candidate) && job_report_font_is_usable($candidate)) {
            $fontPath = $candidate;

            return $fontPath;
        }
    }

    throw new RuntimeException('No report font is available on this server.');
}

function job_report_font_is_usable(string $fontPath): bool
{
    if (!function_exists('imagettfbbox')) {
        return false;
    }

    $box = @imagettfbbox(12, 0, $fontPath, 'Report');

    return is_array($box) && count($box) === 8;
}

function job_report_renderer_draw_header(array &$renderer, array $payload): void
{
    $margin = $renderer['margin'];
    $width = $renderer['page_width'] - ($margin * 2);
    $metaWidth = 230;
    $metaX = $renderer['page_width'] - $margin - $metaWidth;
    $titleWidth = $width - $metaWidth - 40;
    $jobNumber = (string) ($payload['job']['job_number'] ?? '');
    $completedAt = job_report_format_datetime((string) ($payload['job']['actual_completed_at'] ?? ''), (string) $payload['language']);
    $page = &job_report_renderer_current_page($renderer);

    imagefilledrectangle($page, $margin, $renderer['y'], $margin + $width, $renderer['y'] + 7, $renderer['colors']['accent']);
    $renderer['y'] += 30;

    $titleHeight = job_report_renderer_text_block($renderer, (string) $payload['title'], $margin, $renderer['y'], 27, $titleWidth, true);
    $jobNumberY = $renderer['y'] + $titleHeight + 8;
    $jobNumberHeight = $jobNumber !== ''
        ? job_report_renderer_text_block($renderer, $jobNumber, $margin, $jobNumberY, 13, $titleWidth, false, $renderer['colors']['muted'])
        : 0;
    $metaHeight = $completedAt !== ''
        ? job_report_renderer_text_block($renderer, $completedAt, $metaX, $renderer['y'] + 6, 17, $metaWidth, true)
        : 0;

    $renderer['y'] += max($titleHeight + $jobNumberHeight + 16, $metaHeight + 10) + 26;
}

function job_report_renderer_draw_page_heading(array &$renderer): void
{
    $page = &job_report_renderer_current_page($renderer);
    $margin = $renderer['margin'];
    $width = $renderer['page_width'] - ($margin * 2);

    imageline($page, $margin, $margin - 16, $margin + $width, $margin - 16, $renderer['colors']['line']);
    job_report_renderer_text($renderer, $renderer['report_title'], $margin, $margin + 2, 14, true);
    job_report_renderer_text($renderer, $renderer['job_number'], $renderer['page_width'] - $renderer['margin'] - 170, $margin + 3, 10, false, $renderer['colors']['muted']);
    $renderer['y'] = $margin + 38;
}

function job_report_renderer_draw_two_column_summary(array &$renderer, array $left, array $right): void
{
    $margin = $renderer['margin'];
    $gap = 34;
    $columnWidth = (int) floor(($renderer['page_width'] - ($margin * 2) - $gap) / 2);
    $leftHeight = job_report_renderer_summary_panel_height($renderer, $left['title'], $left['rows'], $columnWidth);
    $rightHeight = job_report_renderer_summary_panel_height($renderer, $right['title'], $right['rows'], $columnWidth);
    $panelHeight = max($leftHeight, $rightHeight);

    job_report_renderer_ensure_space($renderer, $panelHeight + 16);

    $startY = $renderer['y'];
    job_report_renderer_draw_summary_panel($renderer, $left['title'], $left['rows'], $margin, $startY, $columnWidth, $panelHeight);
    job_report_renderer_draw_summary_panel($renderer, $right['title'], $right['rows'], $margin + $columnWidth + $gap, $startY, $columnWidth, $panelHeight);
    $renderer['y'] = $startY + $panelHeight + 34;
}

function job_report_renderer_summary_panel_height(array $renderer, string $title, array $rows, int $width): int
{
    $height = 76;

    foreach ($rows as $row) {
        $height += job_report_renderer_wrapped_height($renderer, (string) ($row['label'] ?? ''), 9, $width - 36);
        $height += job_report_renderer_wrapped_height($renderer, (string) ($row['value'] ?? ''), 12, $width - 36);
        $height += 10;
    }

    return $height;
}

function job_report_renderer_draw_summary_panel(array &$renderer, string $title, array $rows, int $x, int $y, int $width, int $height): void
{
    $page = &job_report_renderer_current_page($renderer);
    imagefilledrectangle($page, $x, $y, $x + $width, $y + $height, $renderer['colors']['panel']);
    imagerectangle($page, $x, $y, $x + $width, $y + $height, $renderer['colors']['line']);
    imagefilledrectangle($page, $x + 18, $y + 18, $x + 84, $y + 21, $renderer['colors']['accent']);
    job_report_renderer_text_block($renderer, mb_strtoupper($title), $x + 18, $y + 30, 10, $width - 36, true);

    $currentY = $y + 56;

    foreach ($rows as $index => $row) {
        job_report_renderer_text_block($renderer, (string) ($row['label'] ?? ''), $x + 18, $currentY, 9, $width - 36, false, $renderer['colors']['muted']);
        $currentY += job_report_renderer_wrapped_height($renderer, (string) ($row['label'] ?? ''), 9, $width - 36) - 2;
        job_report_renderer_text_block($renderer, (string) ($row['value'] ?? ''), $x + 18, $currentY, 12, $width - 36);
        $currentY += job_report_renderer_wrapped_height($renderer, (string) ($row['value'] ?? ''), 12, $width - 36) + 6;

        if ($index !== array_key_last($rows)) {
            imageline($page, $x + 18, $currentY, $x + $width - 18, $currentY, $renderer['colors']['line']);
            $currentY += 10;
        }
    }
}

function job_report_renderer_draw_job_section(array &$renderer, string $title, array $rows): void
{
    if ($rows === []) {
        return;
    }

    $titleValue = '';
    $descriptionValue = '';
    $metaRows = [];

    foreach ($rows as $row) {
        $label = (string) ($row['label'] ?? '');

        if (in_array($label, ['Darba nosaukums', 'Job Title'], true)) {
            $titleValue = (string) ($row['value'] ?? '');
            continue;
        }

        if (in_array($label, ['Apraksts', 'Description'], true)) {
            $descriptionValue = (string) ($row['value'] ?? '');
            continue;
        }

        $metaRows[] = $row;
    }

    $needed = 34
        + ($titleValue !== '' ? job_report_renderer_wrapped_height($renderer, $titleValue, 16, 980) + 6 : 0)
        + ($descriptionValue !== '' ? job_report_renderer_wrapped_height($renderer, $descriptionValue, 12, 980) + 8 : 0);

    foreach ($metaRows as $row) {
        $needed += max(
            job_report_renderer_wrapped_height($renderer, (string) ($row['label'] ?? ''), 10, 180),
            job_report_renderer_wrapped_height($renderer, (string) ($row['value'] ?? ''), 12, 800)
        ) + 8;
    }

    job_report_renderer_ensure_space($renderer, $needed + 12);
    job_report_renderer_section_title($renderer, $title);

    if ($titleValue !== '') {
        $renderer['y'] += job_report_renderer_text_block($renderer, $titleValue, $renderer['margin'], $renderer['y'], 16, 980, true);
        $renderer['y'] += 4;
    }

    if ($descriptionValue !== '') {
        $renderer['y'] += job_report_renderer_text_block($renderer, $descriptionValue, $renderer['margin'], $renderer['y'], 12, 980);
        $renderer['y'] += 12;
    }

    foreach ($metaRows as $row) {
        $startY = $renderer['y'];
        job_report_renderer_text_block($renderer, (string) ($row['label'] ?? ''), $renderer['margin'], $startY + 2, 10, 180, false, $renderer['colors']['muted']);
        $textHeight = job_report_renderer_text_block($renderer, (string) ($row['value'] ?? ''), $renderer['margin'] + 170, $startY, 12, 810);
        $renderer['y'] = $startY + max(20, $textHeight) + 6;
    }

    $renderer['y'] += 10;
}

function job_report_renderer_draw_work_section(array &$renderer, string $title, array $items, string $emptyLabel): void
{
    $content = $items === [] ? [$emptyLabel] : $items;
    $needed = 40;

    foreach ($content as $item) {
        $needed += job_report_renderer_wrapped_height($renderer, (string) $item, 12, 944) + 10;
    }

    job_report_renderer_ensure_space($renderer, $needed + 16);
    job_report_renderer_section_title($renderer, $title);

    $boxTop = $renderer['y'];
    $boxHeight = 18;

    foreach ($content as $item) {
        $boxHeight += job_report_renderer_wrapped_height($renderer, (string) $item, 12, 944) + 10;
    }

    $page = &job_report_renderer_current_page($renderer);
    imagefilledrectangle($page, $renderer['margin'], $boxTop, $renderer['page_width'] - $renderer['margin'], $boxTop + $boxHeight, $renderer['colors']['soft']);
    imagerectangle($page, $renderer['margin'], $boxTop, $renderer['page_width'] - $renderer['margin'], $boxTop + $boxHeight, $renderer['colors']['line']);
    $renderer['y'] = $boxTop + 14;

    if (count($content) === 1) {
        $renderer['y'] += job_report_renderer_text_block($renderer, (string) $content[0], $renderer['margin'] + 18, $renderer['y'], 12, 944);
        $renderer['y'] = $boxTop + $boxHeight + 18;

        return;
    }

    foreach ($content as $item) {
        $startY = $renderer['y'];
        imagefilledellipse(job_report_renderer_current_page($renderer), $renderer['margin'] + 18, $startY + 9, 6, 6, $renderer['colors']['accent']);
        $renderer['y'] += job_report_renderer_text_block($renderer, (string) $item, $renderer['margin'] + 34, $startY, 12, 928) + 2;
    }

    $renderer['y'] = $boxTop + $boxHeight + 18;
}

function job_report_renderer_draw_work_notes_section(array &$renderer, string $title, array $items, array $labels): void
{
    if ($items === []) {
        return;
    }

    $needed = 54;

    foreach ($items as $item) {
        $needed += max(
            job_report_renderer_wrapped_height($renderer, (string) ($item['meta'] ?? ''), 10, 140),
            job_report_renderer_wrapped_height($renderer, (string) ($item['text'] ?? ''), 11, 820)
        ) + 8;
    }

    job_report_renderer_ensure_space($renderer, $needed + 14);
    job_report_renderer_section_title($renderer, $title);

    $tableTop = $renderer['y'];
    $page = &job_report_renderer_current_page($renderer);
    imagefilledrectangle($page, $renderer['margin'], $tableTop, $renderer['page_width'] - $renderer['margin'], $tableTop + $needed - 16, $renderer['colors']['panel']);
    imagerectangle($page, $renderer['margin'], $tableTop, $renderer['page_width'] - $renderer['margin'], $tableTop + $needed - 16, $renderer['colors']['line']);

    $headerY = $tableTop + 14;
    job_report_renderer_text($renderer, (string) ($labels['time_column'] ?? 'Time'), $renderer['margin'] + 18, $headerY, 10, true, $renderer['colors']['muted']);
    job_report_renderer_text($renderer, (string) ($labels['note_column'] ?? 'Note'), $renderer['margin'] + 170, $headerY, 10, true, $renderer['colors']['muted']);
    imageline($page, $renderer['margin'] + 18, $headerY + 18, $renderer['page_width'] - $renderer['margin'] - 18, $headerY + 18, $renderer['colors']['line']);
    $renderer['y'] = $headerY + 28;

    foreach ($items as $index => $item) {
        $startY = $renderer['y'];
        $metaHeight = job_report_renderer_text_block($renderer, (string) ($item['meta'] ?? ''), $renderer['margin'] + 18, $startY + 1, 10, 130, true, $renderer['colors']['muted']);
        $textHeight = job_report_renderer_text_block($renderer, (string) ($item['text'] ?? ''), $renderer['margin'] + 170, $startY, 11, 900);
        $renderer['y'] = $startY + max($metaHeight, $textHeight) + 6;

        if ($index !== array_key_last($items)) {
            imageline($page, $renderer['margin'] + 18, $renderer['y'], $renderer['page_width'] - $renderer['margin'] - 18, $renderer['y'], $renderer['colors']['line']);
            $renderer['y'] += 8;
        }
    }

    $renderer['y'] += 18;
}

function job_report_renderer_draw_material_section(array &$renderer, string $title, array $rows, array $labels): void
{
    $needed = 64;

    foreach ($rows as $row) {
        $secondary = 0;

        if (trim((string) ($row['object_name'] ?? '')) !== '') {
            $secondary += job_report_renderer_wrapped_height($renderer, $labels['object_name'] . ': ' . trim((string) $row['object_name']), 10, 960) + 2;
        }

        if (trim((string) ($row['device_identifier'] ?? '')) !== '') {
            $secondary += job_report_renderer_wrapped_height($renderer, $labels['device_identifier'] . ': ' . trim((string) $row['device_identifier']), 10, 960) + 2;
        }

        if (($row['accessories'] ?? []) !== []) {
            foreach ($row['accessories'] as $accessory) {
                $secondary += job_report_renderer_wrapped_height($renderer, '- ' . (string) $accessory, 10, 940) + 2;
            }
        }

        $needed += max(
            job_report_renderer_wrapped_height($renderer, (string) ($row['material'] ?? ''), 11, 280),
            job_report_renderer_wrapped_height($renderer, (string) ($row['sku'] ?? ''), 10, 150),
            job_report_renderer_wrapped_height($renderer, (string) ($row['movement'] ?? ''), 10, 180),
            job_report_renderer_wrapped_height($renderer, (string) ($row['quantity'] ?? ''), 11, 140)
        ) + $secondary + 18;
    }

    job_report_renderer_ensure_space($renderer, $needed + 14);
    job_report_renderer_section_title($renderer, $title);

    $tableTop = $renderer['y'];
    $page = &job_report_renderer_current_page($renderer);
    imagefilledrectangle($page, $renderer['margin'], $tableTop, $renderer['page_width'] - $renderer['margin'], $tableTop + $needed - 18, $renderer['colors']['panel']);
    imagerectangle($page, $renderer['margin'], $tableTop, $renderer['page_width'] - $renderer['margin'], $tableTop + $needed - 18, $renderer['colors']['line']);

    $headerY = $tableTop + 14;
    $columns = [
        ['title' => $labels['material_name'], 'x' => $renderer['margin'] + 18, 'width' => 280],
        ['title' => $labels['sku'], 'x' => $renderer['margin'] + 318, 'width' => 150],
        ['title' => $labels['movement_type'], 'x' => $renderer['margin'] + 488, 'width' => 180],
        ['title' => $labels['quantity'], 'x' => $renderer['page_width'] - $renderer['margin'] - 158, 'width' => 140],
    ];

    foreach ($columns as $column) {
        job_report_renderer_text_block($renderer, $column['title'], $column['x'], $headerY, 10, $column['width'], true, $renderer['colors']['muted']);
    }

    imageline($page, $renderer['margin'] + 18, $headerY + 18, $renderer['page_width'] - $renderer['margin'] - 18, $headerY + 18, $renderer['colors']['line']);
    $renderer['y'] = $headerY + 28;

    foreach ($rows as $index => $row) {
        $startY = $renderer['y'];
        $maxPrimaryHeight = 0;
        $maxPrimaryHeight = max($maxPrimaryHeight, job_report_renderer_text_block($renderer, (string) ($row['material'] ?? ''), $renderer['margin'] + 18, $startY, 11, 280));
        $maxPrimaryHeight = max($maxPrimaryHeight, job_report_renderer_text_block($renderer, (string) ($row['sku'] ?? ''), $renderer['margin'] + 318, $startY + 1, 10, 150, false, $renderer['colors']['muted']));
        $maxPrimaryHeight = max($maxPrimaryHeight, job_report_renderer_text_block($renderer, (string) ($row['movement'] ?? ''), $renderer['margin'] + 488, $startY + 1, 10, 180, false, $renderer['colors']['text']));
        $maxPrimaryHeight = max($maxPrimaryHeight, job_report_renderer_text_block($renderer, (string) ($row['quantity'] ?? ''), $renderer['page_width'] - $renderer['margin'] - 158, $startY, 11, 140));

        $currentY = $startY + $maxPrimaryHeight + 2;

        if (trim((string) ($row['object_name'] ?? '')) !== '') {
            $currentY += job_report_renderer_text_block($renderer, $labels['object_name'] . ': ' . trim((string) $row['object_name']), $renderer['margin'] + 18, $currentY, 10, 960, false, $renderer['colors']['muted']);
        }

        if (trim((string) ($row['device_identifier'] ?? '')) !== '') {
            $currentY += job_report_renderer_text_block($renderer, $labels['device_identifier'] . ': ' . trim((string) $row['device_identifier']), $renderer['margin'] + 18, $currentY, 10, 960, false, $renderer['colors']['muted']);
        }

        if (($row['accessories'] ?? []) !== []) {
            foreach ($row['accessories'] as $accessory) {
                $currentY += job_report_renderer_text_block($renderer, '- ' . (string) $accessory, $renderer['margin'] + 36, $currentY, 10, 940, false, $renderer['colors']['muted']);
            }
        }

        $renderer['y'] = $currentY + 4;

        if ($index !== array_key_last($rows)) {
            imageline($page, $renderer['margin'] + 18, $renderer['y'], $renderer['page_width'] - $renderer['margin'] - 18, $renderer['y'], $renderer['colors']['line']);
            $renderer['y'] += 8;
        }
    }

    $renderer['y'] += 18;
}

function job_report_renderer_draw_confirmation_section(array &$renderer, array $payload): void
{
    $confirmation = is_array($payload['confirmation']) ? $payload['confirmation'] : [];
    $margin = $renderer['margin'];
    $gap = 32;
    $sectionGap = 30;
    $headingHeight = 20;
    $sectionBottomSpacing = 22;
    $columnWidth = (int) floor(($renderer['page_width'] - ($margin * 2) - $gap) / 2);
    $workerName = trim((string) ($payload['worker']['name'] ?? '')) !== ''
        ? trim((string) $payload['worker']['name'])
        : $payload['labels']['not_captured'];
    $workerCompany = trim((string) ($payload['worker']['company_name'] ?? ''));
    $workerSignaturePath = job_report_worker_signature_path($payload['worker']);
    $customerName = trim((string) ($confirmation['customer_name'] ?? '')) !== ''
        ? trim((string) $confirmation['customer_name'])
        : $payload['labels']['not_captured'];
    $confirmedAt = job_report_format_datetime((string) ($confirmation['confirmed_at'] ?? ''), (string) $payload['language']);
    $workerHeight = 86
        + job_report_renderer_wrapped_height($renderer, $workerName, 13, $columnWidth - 40)
        + ($workerCompany !== '' ? job_report_renderer_wrapped_height($renderer, $workerCompany, 11, $columnWidth - 40) : 0)
        + job_report_renderer_signature_height($workerSignaturePath);
    $customerHeight = 86
        + job_report_renderer_wrapped_height($renderer, $customerName, 13, $columnWidth - 40)
        + job_report_renderer_signature_height(trim((string) ($confirmation['signature_path'] ?? '')))
        + ($confirmedAt !== '' ? job_report_renderer_wrapped_height($renderer, $confirmedAt, 10, $columnWidth - 40) : 0);
    $blockHeight = max($workerHeight, $customerHeight);
    $requiredHeight = $sectionGap + $headingHeight + $blockHeight + $sectionBottomSpacing;

    job_report_renderer_ensure_space($renderer, $requiredHeight);
    $renderer['y'] += $sectionGap;
    job_report_renderer_section_title($renderer, $payload['labels']['confirmation_section']);

    $boxTop = $renderer['y'];
    job_report_renderer_draw_confirmation_block(
        $renderer,
        $payload['labels']['worker_column'],
        $workerName,
        $workerCompany,
        $margin,
        $boxTop,
        $columnWidth,
        $blockHeight,
        $workerSignaturePath,
        ''
    );
    job_report_renderer_draw_confirmation_block(
        $renderer,
        $payload['labels']['customer_column'],
        $customerName,
        '',
        $margin + $columnWidth + $gap,
        $boxTop,
        $columnWidth,
        $blockHeight,
        trim((string) ($confirmation['signature_path'] ?? '')),
        $confirmedAt
    );

    $renderer['y'] = $boxTop + $blockHeight + $sectionBottomSpacing;
}

function job_report_worker_signature_path(array $worker): string
{
    return resolved_user_signature_path($worker);
}

function job_report_renderer_signature_height(string $path): int
{
    return $path !== '' && is_file($path) ? 88 : 0;
}

function job_report_renderer_draw_confirmation_block(
    array &$renderer,
    string $title,
    string $primary,
    string $secondary,
    int $x,
    int $y,
    int $width,
    int $height,
    string $signaturePath,
    string $confirmedAt
): void {
    $page = &job_report_renderer_current_page($renderer);
    imagefilledrectangle($page, $x, $y, $x + $width, $y + $height, $renderer['colors']['panel']);
    imagerectangle($page, $x, $y, $x + $width, $y + $height, $renderer['colors']['line']);
    imagefilledrectangle($page, $x + 18, $y + 18, $x + 84, $y + 21, $renderer['colors']['accent']);
    job_report_renderer_text_block($renderer, $title, $x + 18, $y + 30, 11, $width - 36, true);

    $currentY = $y + 56;
    $currentY += job_report_renderer_text_block($renderer, $primary, $x + 18, $currentY, 13, $width - 36, true);

    if ($secondary !== '') {
        $currentY += job_report_renderer_text_block($renderer, $secondary, $x + 18, $currentY + 2, 11, $width - 36, false, $renderer['colors']['muted']);
    }

    if ($signaturePath !== '' && is_file($signaturePath)) {
        $signature = @imagecreatefrompng($signaturePath);

        if ($signature !== false) {
            $srcWidth = imagesx($signature);
            $srcHeight = imagesy($signature);
            $targetWidth = $width - 36;
            $targetHeight = 78;
            $scale = min($targetWidth / max(1, $srcWidth), $targetHeight / max(1, $srcHeight));
            $drawWidth = max(1, (int) floor($srcWidth * $scale));
            $drawHeight = max(1, (int) floor($srcHeight * $scale));
            imagecopyresampled($page, $signature, $x + 18, $currentY + 8, 0, 0, $drawWidth, $drawHeight, $srcWidth, $srcHeight);
            imagedestroy($signature);
            $currentY += $drawHeight + 14;
        }
    }

    if ($confirmedAt !== '') {
        job_report_renderer_text_block($renderer, $confirmedAt, $x + 18, $currentY + 2, 10, $width - 36, false, $renderer['colors']['muted']);
    }
}

function job_report_renderer_draw_legal_note(array &$renderer, string $text): void
{
    $text = trim($text);

    if ($text === '') {
        return;
    }

    $width = $renderer['page_width'] - ($renderer['margin'] * 2);
    $needed = job_report_renderer_wrapped_height($renderer, $text, 9, $width) + 16;
    job_report_renderer_ensure_space($renderer, $needed);
    $renderer['y'] += 8;
    $renderer['y'] += job_report_renderer_text_block(
        $renderer,
        $text,
        $renderer['margin'],
        $renderer['y'],
        9,
        $width,
        false,
        $renderer['colors']['muted']
    );
}

function job_report_renderer_section_title(array &$renderer, string $title): void
{
    job_report_renderer_text_block($renderer, mb_strtoupper($title), $renderer['margin'], $renderer['y'], 12, 980, true);
    $renderer['y'] += 20;
}

function &job_report_renderer_current_page(array &$renderer)
{
    return $renderer['pages'][$renderer['current_page']];
}

function job_report_renderer_text(
    array &$renderer,
    string $text,
    int $x,
    int $y,
    int $fontSize,
    bool $bold = false,
    ?int $color = null
): void {
    if (trim($text) === '') {
        return;
    }

    imagettftext(
        job_report_renderer_current_page($renderer),
        $fontSize,
        0,
        $x,
        $y + $fontSize,
        $color ?? $renderer['colors']['text'],
        $bold ? $renderer['font_bold'] : $renderer['font_regular'],
        $text
    );
}

function job_report_renderer_text_block(
    array &$renderer,
    string $text,
    int $x,
    int $y,
    int $fontSize,
    int $maxWidth,
    bool $bold = false,
    ?int $color = null
): int {
    $lines = job_report_wrap_text($text, $maxWidth, $fontSize, $bold ? $renderer['font_bold'] : $renderer['font_regular']);
    $lineHeight = $fontSize + 10;
    $offset = 0;

    foreach ($lines as $line) {
        job_report_renderer_text($renderer, $line, $x, $y + $offset, $fontSize, $bold, $color);
        $offset += $lineHeight;
    }

    return max($lineHeight, $offset);
}

function job_report_renderer_wrapped_height(array $renderer, string $text, int $fontSize, int $maxWidth): int
{
    $lines = job_report_wrap_text($text, $maxWidth, $fontSize, $renderer['font_regular']);

    return max(1, count($lines)) * ($fontSize + 10);
}

function job_report_wrap_text(string $text, int $maxWidth, int $fontSize, string $fontPath): array
{
    $normalized = preg_replace("/\r\n|\r/u", "\n", trim($text)) ?? '';

    if ($normalized === '') {
        return [''];
    }

    $lines = [];

    foreach (explode("\n", $normalized) as $paragraph) {
        $words = preg_split('/\s+/u', trim($paragraph)) ?: [];

        if ($words === []) {
            $lines[] = '';
            continue;
        }

        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;

            if (job_report_text_width($candidate, $fontSize, $fontPath) <= $maxWidth) {
                $current = $candidate;
                continue;
            }

            if ($current !== '') {
                $lines[] = $current;
                $current = '';
            }

            if (job_report_text_width($word, $fontSize, $fontPath) <= $maxWidth) {
                $current = $word;
                continue;
            }

            foreach (job_report_split_long_word($word, $maxWidth, $fontSize, $fontPath) as $part) {
                if (job_report_text_width($part, $fontSize, $fontPath) <= $maxWidth) {
                    if ($current === '') {
                        $current = $part;
                    } else {
                        $lines[] = $current;
                        $current = $part;
                    }
                }
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }
    }

    return $lines === [] ? [''] : $lines;
}

function job_report_split_long_word(string $word, int $maxWidth, int $fontSize, string $fontPath): array
{
    $chars = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $parts = [];
    $current = '';

    foreach ($chars as $char) {
        $candidate = $current . $char;

        if ($current !== '' && job_report_text_width($candidate, $fontSize, $fontPath) > $maxWidth) {
            $parts[] = $current;
            $current = $char;
            continue;
        }

        $current = $candidate;
    }

    if ($current !== '') {
        $parts[] = $current;
    }

    return $parts;
}

function job_report_text_width(string $text, int $fontSize, string $fontPath): int
{
    $box = imagettfbbox($fontSize, 0, $fontPath, $text);

    if (!is_array($box)) {
        return strlen($text) * $fontSize;
    }

    return (int) abs($box[2] - $box[0]);
}

function job_report_renderer_ensure_space(array &$renderer, int $neededHeight): void
{
    $bottom = $renderer['page_height'] - $renderer['margin'] - $renderer['footer_margin'];

    if ($renderer['y'] + $neededHeight <= $bottom) {
        return;
    }

    $page = imagecreatetruecolor($renderer['page_width'], $renderer['page_height']);

    if (function_exists('imageantialias')) {
        imageantialias($page, true);
    }

    imagefilledrectangle($page, 0, 0, $renderer['page_width'], $renderer['page_height'], $renderer['colors']['white']);
    $renderer['pages'][] = $page;
    $renderer['current_page'] = count($renderer['pages']) - 1;
    $renderer['y'] = $renderer['margin'];
    job_report_renderer_draw_page_heading($renderer);
}

function job_report_renderer_export_pages(array $renderer): array
{
    $renderer = job_report_renderer_add_footers($renderer);
    $binaries = [];

    foreach ($renderer['pages'] as $page) {
        ob_start();
        imagejpeg($page, null, 92);
        $binary = ob_get_clean();

        if (!is_string($binary)) {
            throw new RuntimeException('The report page could not be encoded.');
        }

        $binaries[] = $binary;
        imagedestroy($page);
    }

    return $binaries;
}

function job_report_renderer_add_footers(array $renderer): array
{
    $totalPages = count($renderer['pages']);

    if ($totalPages <= 1) {
        return $renderer;
    }

    foreach ($renderer['pages'] as $index => $page) {
        $footerY = $renderer['page_height'] - $renderer['footer_margin'];
        imageline($page, $renderer['margin'], $footerY - 16, $renderer['page_width'] - $renderer['margin'], $footerY - 16, $renderer['colors']['line']);
        imagettftext($page, 10, 0, $renderer['margin'], $footerY, $renderer['colors']['muted'], $renderer['font_regular'], $renderer['job_number']);
        $pageLabel = ($index + 1) . ' / ' . $totalPages;
        $pageWidth = job_report_text_width($pageLabel, 10, $renderer['font_regular']);
        imagettftext($page, 10, 0, $renderer['page_width'] - $renderer['margin'] - $pageWidth, $footerY, $renderer['colors']['muted'], $renderer['font_regular'], $pageLabel);
    }

    return $renderer;
}

function job_report_images_to_pdf(array $jpegPages): string
{
    if ($jpegPages === []) {
        throw new RuntimeException('The report contains no pages.');
    }

    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
    ];
    $kids = [];
    $nextObjectId = 3;

    foreach ($jpegPages as $index => $jpegBinary) {
        $imageInfo = @getimagesizefromstring($jpegBinary);

        if ($imageInfo === false) {
            throw new RuntimeException('A report page image could not be read.');
        }

        $imageObjectId = $nextObjectId++;
        $contentObjectId = $nextObjectId++;
        $pageObjectId = $nextObjectId++;
        $resourceName = 'Im' . ($index + 1);
        $content = "q\n595 0 0 842 0 0 cm\n/" . $resourceName . " Do\nQ\n";

        $objects[$imageObjectId] = '<< /Type /XObject /Subtype /Image /Width ' . (int) $imageInfo[0]
            . ' /Height ' . (int) $imageInfo[1]
            . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($jpegBinary)
            . " >>\nstream\n" . $jpegBinary . "\nendstream";
        $objects[$contentObjectId] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "endstream";
        $objects[$pageObjectId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /XObject << /'
            . $resourceName . ' ' . $imageObjectId . ' 0 R >> >> /Contents ' . $contentObjectId . ' 0 R >>';
        $kids[] = $pageObjectId . ' 0 R';
    }

    $objects[2] = '<< /Type /Pages /Count ' . count($kids) . ' /Kids [ ' . implode(' ', $kids) . ' ] >>';
    ksort($objects);

    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [0];

    foreach ($objects as $objectId => $objectBody) {
        $offsets[$objectId] = strlen($pdf);
        $pdf .= $objectId . " 0 obj\n" . $objectBody . "\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $objectCount = max(array_keys($objects));
    $pdf .= "xref\n0 " . ($objectCount + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";

    for ($i = 1; $i <= $objectCount; $i++) {
        $offset = $offsets[$i] ?? 0;
        $pdf .= sprintf('%010d 00000 n ', $offset) . "\n";
    }

    $pdf .= "trailer\n<< /Size " . ($objectCount + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF";

    return $pdf;
}
