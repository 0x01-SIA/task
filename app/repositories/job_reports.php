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
        'job_rows' => job_report_job_rows($job, $task, $labels, $language),
        'performed_work' => job_report_performed_work($job, $task, $notes),
        'work_notes' => job_report_work_notes($job, $notes, $language),
        'materials' => job_report_material_rows($materials, $deviceInstallations, $labels),
        'comments' => job_report_comment_rows($notes, $labels),
        'confirmation' => $confirmation,
        'snapshot' => $snapshotData,
        'worker' => [
            'name' => trim((string) ($job['assigned_worker_name'] ?? '')),
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
            'assigned_worker_name' => (string) ($job['assigned_worker_name'] ?? ''),
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
            'name' => trim((string) ($job['assigned_worker_name'] ?? '')),
            'company_name' => (string) ($company['name'] ?? ''),
        ],
    ];
}

function job_report_labels(string $language): array
{
    if ($language === 'lv') {
        return [
            'report_title' => 'Darbu izpildes atskaite',
            'report_identification' => 'Atskaites informācija',
            'company_section' => 'Pakalpojuma sniedzējs',
            'customer_section' => 'Klienta informācija',
            'job_section' => 'Darba informācija',
            'work_section' => 'Izpildītie darbi',
            'work_notes_section' => 'Darba piezīmes',
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
            'generated_datetime' => 'Atskaite sagatavota',
            'customer_name' => 'Klients',
            'location_name' => 'Objekts',
            'full_address' => 'Adrese',
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
            'device_identifier' => 'Ierīces ID',
            'accessories' => 'Piederumi',
            'used' => 'Izlietots / Uzstādīts',
            'returned' => 'Atgriezts / Noņemts',
            'none_recorded' => 'Nav ierakstu.',
            'not_captured' => 'Nav fiksēts',
            'signature' => 'Paraksts',
            'signed_at' => 'Apstiprināts',
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
        'generated_datetime' => 'Generated At',
        'customer_name' => 'Customer',
        'location_name' => 'Location',
        'full_address' => 'Address',
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
        'device_identifier' => 'Device ID',
        'accessories' => 'Accessories',
        'used' => 'Used / Installed',
        'returned' => 'Returned / Removed',
        'none_recorded' => 'No records.',
        'not_captured' => 'Not captured',
        'signature' => 'Signature',
        'signed_at' => 'Signed at',
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

    foreach ($materials as $material) {
        $usageId = (int) ($material['id'] ?? 0);
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
            'device_identifier' => $installation['device_identifier']
                ?? (string) ($material['device_identifier'] ?? ''),
            'accessories' => $accessories,
        ];
    }

    return $rows;
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
    job_report_renderer_draw_compact_metadata($renderer, [
        ['label' => $payload['labels']['generated_datetime'], 'value' => job_report_format_datetime((string) $payload['generated_at'], (string) $payload['language'])],
    ]);
    job_report_renderer_draw_key_value_section($renderer, $payload['labels']['company_section'], [
        ['label' => $payload['labels']['company_name'], 'value' => (string) ($payload['company']['name'] ?? '')],
        ['label' => $payload['labels']['registration_number'], 'value' => (string) ($payload['company']['registration_number'] ?? '')],
        ['label' => $payload['labels']['legal_address'], 'value' => (string) ($payload['company']['address'] ?? '')],
        ['label' => $payload['labels']['email'], 'value' => (string) ($payload['company']['email'] ?? '')],
    ], ['label_width' => 240, 'value_width' => 800]);
    job_report_renderer_draw_key_value_section($renderer, $payload['labels']['customer_section'], $payload['customer_rows']);
    job_report_renderer_draw_key_value_section($renderer, $payload['labels']['job_section'], $payload['job_rows']);
    job_report_renderer_draw_bullet_section($renderer, $payload['labels']['work_section'], $payload['performed_work'], $payload['labels']['none_recorded']);
    job_report_renderer_draw_work_notes_section($renderer, $payload['labels']['work_notes_section'], $payload['work_notes']);

    if ($payload['materials'] !== []) {
        job_report_renderer_draw_material_section($renderer, $payload['labels']['materials_section'], $payload['materials'], $payload['labels']);
    }

    if ($payload['comments'] !== []) {
        job_report_renderer_draw_bullet_section($renderer, $payload['labels']['comments_section'], $payload['comments'], $payload['labels']['none_recorded']);
    }

    job_report_renderer_draw_confirmation_section($renderer, $payload);

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
        'y' => $margin,
        'font_regular' => $fontRegular,
        'font_bold' => $fontBold,
        'colors' => [
            'white' => $white,
            'text' => imagecolorallocate($page, 34, 39, 46),
            'muted' => imagecolorallocate($page, 95, 104, 114),
            'line' => imagecolorallocate($page, 222, 226, 230),
            'accent' => imagecolorallocate($page, 28, 73, 128),
            'soft' => imagecolorallocate($page, 248, 249, 250),
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
    $page = &job_report_renderer_current_page($renderer);
    $margin = $renderer['margin'];
    $width = $renderer['page_width'] - ($margin * 2);
    $accent = $renderer['colors']['accent'];

    imagefilledrectangle($page, $margin, $renderer['y'], $margin + $width, $renderer['y'] + 6, $accent);
    $renderer['y'] += 28;

    job_report_renderer_text($renderer, (string) $payload['title'], $margin, $renderer['y'], 26, true);
    job_report_renderer_text($renderer, (string) ($payload['job']['job_number'] ?? ''), $margin, $renderer['y'] + 40, 14, false, $renderer['colors']['muted']);

    $metaX = $renderer['page_width'] - $margin - 320;
    $metaY = $renderer['y'] + 4;
    job_report_renderer_text_block(
        $renderer,
        $payload['labels']['completion_datetime'] . ': ' . job_report_format_datetime((string) ($payload['job']['actual_completed_at'] ?? ''), (string) $payload['language']),
        $metaX,
        $metaY,
        12,
        320,
        false,
        $renderer['colors']['text']
    );

    $renderer['y'] += 74;
}

function job_report_renderer_draw_page_heading(array &$renderer): void
{
    $page = &job_report_renderer_current_page($renderer);
    $margin = $renderer['margin'];
    $width = $renderer['page_width'] - ($margin * 2);
    $line = $renderer['colors']['line'];

    imageline($page, $margin, $margin - 18, $margin + $width, $margin - 18, $line);
    job_report_renderer_text($renderer, $renderer['report_title'], $margin, $margin + 4, 16, true);
    job_report_renderer_text($renderer, $renderer['job_number'], $margin + 760, $margin + 4, 11, false, $renderer['colors']['muted']);
    $renderer['y'] = $margin + 40;
}

function job_report_renderer_draw_compact_metadata(array &$renderer, array $rows): void
{
    $filteredRows = array_values(array_filter(
        $rows,
        static fn (array $row): bool => trim((string) ($row['value'] ?? '')) !== ''
    ));

    if ($filteredRows === []) {
        return;
    }

    $height = 0;

    foreach ($filteredRows as $row) {
        $height += max(
            job_report_renderer_wrapped_height($renderer, (string) $row['label'], 11, 220),
            job_report_renderer_wrapped_height($renderer, (string) $row['value'], 11, 420)
        ) + 6;
    }

    job_report_renderer_ensure_space($renderer, $height + 16);

    foreach ($filteredRows as $row) {
        $startY = $renderer['y'];
        job_report_renderer_text_block($renderer, (string) $row['label'], $renderer['margin'], $startY, 11, 220, true, $renderer['colors']['muted']);
        $textHeight = job_report_renderer_text_block($renderer, (string) $row['value'], $renderer['margin'] + 228, $startY, 11, 420, false, $renderer['colors']['muted']);
        $renderer['y'] = $startY + max(18, $textHeight) + 4;
    }

    $renderer['y'] += 10;
}

function job_report_renderer_draw_key_value_section(array &$renderer, string $title, array $rows, array $options = []): void
{
    $filteredRows = array_values(array_filter(
        $rows,
        static fn (array $row): bool => trim((string) ($row['value'] ?? '')) !== ''
    ));

    if ($filteredRows === []) {
        return;
    }

    $labelWidth = (int) ($options['label_width'] ?? 220);
    $valueWidth = (int) ($options['value_width'] ?? 790);
    $valueX = $renderer['margin'] + $labelWidth + 24;
    $needed = 34;

    foreach ($filteredRows as $row) {
        $needed += max(
            job_report_renderer_wrapped_height($renderer, (string) $row['label'], 10, $labelWidth),
            job_report_renderer_wrapped_height($renderer, (string) $row['value'], 12, $valueWidth)
        ) + 8;
    }

    job_report_renderer_ensure_space($renderer, $needed);
    job_report_renderer_section_title($renderer, $title);

    foreach ($filteredRows as $row) {
        $startY = $renderer['y'];
        job_report_renderer_text_block($renderer, (string) $row['label'], $renderer['margin'], $startY + 1, 10, $labelWidth, true, $renderer['colors']['muted']);
        $textHeight = job_report_renderer_text_block($renderer, (string) $row['value'], $valueX, $startY, 12, $valueWidth);
        $renderer['y'] = $startY + max(20, $textHeight) + 8;
    }

    $renderer['y'] += 6;
}

function job_report_renderer_draw_bullet_section(array &$renderer, string $title, array $items, string $emptyLabel): void
{
    $content = $items === [] ? [$emptyLabel] : $items;
    $needed = 34;

    foreach ($content as $item) {
        $needed += job_report_renderer_wrapped_height($renderer, (string) $item, 12, 990) + 8;
    }

    job_report_renderer_ensure_space($renderer, $needed);
    job_report_renderer_section_title($renderer, $title);

    foreach ($content as $item) {
        $startY = $renderer['y'];
        imagefilledellipse(
            job_report_renderer_current_page($renderer),
            $renderer['margin'] + 8,
            $startY + 9,
            6,
            6,
            $renderer['colors']['accent']
        );
        $textHeight = job_report_renderer_text_block($renderer, (string) $item, $renderer['margin'] + 24, $startY, 12, 990);
        $renderer['y'] = $startY + max(20, $textHeight) + 4;
    }

    $renderer['y'] += 6;
}

function job_report_renderer_draw_work_notes_section(array &$renderer, string $title, array $items): void
{
    if ($items === []) {
        return;
    }

    $needed = 34;

    foreach ($items as $item) {
        $needed += max(
            job_report_renderer_wrapped_height($renderer, (string) ($item['meta'] ?? ''), 10, 180),
            job_report_renderer_wrapped_height($renderer, (string) ($item['text'] ?? ''), 11, 820)
        ) + 8;
    }

    job_report_renderer_ensure_space($renderer, $needed);
    job_report_renderer_section_title($renderer, $title);

    foreach ($items as $item) {
        $startY = $renderer['y'];
        job_report_renderer_text_block($renderer, (string) ($item['meta'] ?? ''), $renderer['margin'], $startY + 1, 10, 180, true, $renderer['colors']['muted']);
        $textHeight = job_report_renderer_text_block($renderer, (string) ($item['text'] ?? ''), $renderer['margin'] + 190, $startY, 11, 820);
        $renderer['y'] = $startY + max(18, $textHeight) + 4;
    }

    $renderer['y'] += 6;
}

function job_report_renderer_draw_material_section(array &$renderer, string $title, array $rows, array $labels): void
{
    $needed = 64;

    foreach ($rows as $row) {
        $needed += job_report_renderer_wrapped_height($renderer, (string) ($row['material'] ?? ''), 11, 280);
        $needed += job_report_renderer_wrapped_height($renderer, (string) ($row['sku'] ?? ''), 10, 150);
        $needed += job_report_renderer_wrapped_height($renderer, (string) ($row['movement'] ?? ''), 10, 180);
        $needed += job_report_renderer_wrapped_height($renderer, (string) ($row['quantity'] ?? ''), 11, 140);
        $secondary = 0;

        if (trim((string) ($row['device_identifier'] ?? '')) !== '') {
            $secondary += job_report_renderer_wrapped_height($renderer, $labels['device_identifier'] . ': ' . trim((string) $row['device_identifier']), 10, 960) + 2;
        }

        if (($row['accessories'] ?? []) !== []) {
            foreach ($row['accessories'] as $accessory) {
                $secondary += job_report_renderer_wrapped_height($renderer, '- ' . (string) $accessory, 10, 940) + 2;
            }
        }

        $needed += max(26, $secondary) + 20;
    }

    job_report_renderer_ensure_space($renderer, $needed);
    job_report_renderer_section_title($renderer, $title);
    $headerY = $renderer['y'];
    $columns = [
        ['title' => $labels['material_name'], 'x' => $renderer['margin'], 'width' => 280],
        ['title' => $labels['sku'], 'x' => $renderer['margin'] + 300, 'width' => 150],
        ['title' => $labels['movement_type'], 'x' => $renderer['margin'] + 470, 'width' => 180],
        ['title' => $labels['quantity'], 'x' => $renderer['page_width'] - $renderer['margin'] - 140, 'width' => 140],
    ];

    foreach ($columns as $column) {
        job_report_renderer_text_block($renderer, $column['title'], $column['x'], $headerY, 10, $column['width'], true, $renderer['colors']['muted']);
    }

    $renderer['y'] = $headerY + 22;
    imageline(
        job_report_renderer_current_page($renderer),
        $renderer['margin'],
        $renderer['y'],
        $renderer['page_width'] - $renderer['margin'],
        $renderer['y'],
        $renderer['colors']['line']
    );
    $renderer['y'] += 10;

    foreach ($rows as $row) {
        $startY = $renderer['y'];
        $maxPrimaryHeight = 0;

        $maxPrimaryHeight = max($maxPrimaryHeight, job_report_renderer_text_block($renderer, (string) ($row['material'] ?? ''), $renderer['margin'], $startY, 11, 280));
        $maxPrimaryHeight = max($maxPrimaryHeight, job_report_renderer_text_block($renderer, (string) ($row['sku'] ?? ''), $renderer['margin'] + 300, $startY + 1, 10, 150, false, $renderer['colors']['muted']));
        $maxPrimaryHeight = max($maxPrimaryHeight, job_report_renderer_text_block($renderer, (string) ($row['movement'] ?? ''), $renderer['margin'] + 470, $startY + 1, 10, 180, false, $renderer['colors']['text']));
        $maxPrimaryHeight = max($maxPrimaryHeight, job_report_renderer_text_block($renderer, (string) ($row['quantity'] ?? ''), $renderer['page_width'] - $renderer['margin'] - 140, $startY, 11, 140));

        $currentY = $startY + $maxPrimaryHeight + 2;

        if (trim((string) ($row['device_identifier'] ?? '')) !== '') {
            $lineHeight = job_report_renderer_text_block($renderer, $labels['device_identifier'] . ': ' . trim((string) $row['device_identifier']), $renderer['margin'], $currentY, 10, 960, false, $renderer['colors']['muted']);
            $currentY += $lineHeight;
        }

        if (($row['accessories'] ?? []) !== []) {
            foreach ($row['accessories'] as $accessory) {
                $lineHeight = job_report_renderer_text_block($renderer, '- ' . (string) $accessory, $renderer['margin'] + 18, $currentY, 10, 940, false, $renderer['colors']['muted']);
                $currentY += $lineHeight;
            }
        }

        $renderer['y'] = $currentY + 4;
        imageline(
            job_report_renderer_current_page($renderer),
            $renderer['margin'],
            $renderer['y'],
            $renderer['page_width'] - $renderer['margin'],
            $renderer['y'],
            $renderer['colors']['line']
        );
        $renderer['y'] += 8;
    }
}

function job_report_renderer_draw_confirmation_section(array &$renderer, array $payload): void
{
    $confirmation = is_array($payload['confirmation']) ? $payload['confirmation'] : [];
    $margin = $renderer['margin'];
    $gap = 28;
    $columnWidth = (int) floor(($renderer['page_width'] - ($margin * 2) - $gap) / 2);
    $leftX = $margin;
    $rightX = $margin + $columnWidth + $gap;

    $workerName = trim((string) ($payload['worker']['name'] ?? '')) !== ''
        ? trim((string) $payload['worker']['name'])
        : $payload['labels']['not_captured'];
    $workerCompany = trim((string) ($payload['worker']['company_name'] ?? ''));
    $customerName = trim((string) ($confirmation['customer_name'] ?? '')) !== ''
        ? trim((string) $confirmation['customer_name'])
        : $payload['labels']['not_captured'];
    $confirmedAt = job_report_format_datetime((string) ($confirmation['confirmed_at'] ?? ''), (string) $payload['language']);

    $workerHeight = 34
        + job_report_renderer_wrapped_height($renderer, $workerName, 12, $columnWidth - 24)
        + ($workerCompany !== '' ? job_report_renderer_wrapped_height($renderer, $workerCompany, 10, $columnWidth - 24) : 0);
    $customerHeight = 34
        + job_report_renderer_wrapped_height($renderer, $customerName, 12, $columnWidth - 24)
        + job_report_renderer_confirmation_signature_height($confirmation)
        + ($confirmedAt !== '' ? job_report_renderer_wrapped_height($renderer, $confirmedAt, 10, $columnWidth - 24) : 0);
    $needed = 40 + max($workerHeight, $customerHeight) + 28;

    job_report_renderer_ensure_space($renderer, $needed);
    job_report_renderer_section_title($renderer, $payload['labels']['confirmation_section']);
    $boxTop = $renderer['y'];
    $page = &job_report_renderer_current_page($renderer);
    imageline($page, $leftX + $columnWidth + (int) floor($gap / 2), $boxTop, $leftX + $columnWidth + (int) floor($gap / 2), $boxTop + max($workerHeight, $customerHeight), $renderer['colors']['line']);

    job_report_renderer_text($renderer, $payload['labels']['worker_column'], $leftX, $boxTop, 12, true);
    job_report_renderer_text($renderer, $payload['labels']['customer_column'], $rightX, $boxTop, 12, true);

    $leftY = $boxTop + 28;
    $leftY += job_report_renderer_text_block($renderer, $workerName, $leftX, $leftY, 12, $columnWidth - 8);

    if ($workerCompany !== '') {
        $leftY += job_report_renderer_text_block($renderer, $workerCompany, $leftX, $leftY, 10, $columnWidth - 8, false, $renderer['colors']['muted']);
    }

    $rightY = $boxTop + 28;
    $rightY += job_report_renderer_text_block($renderer, $customerName, $rightX, $rightY, 12, $columnWidth - 8);

    if (trim((string) ($confirmation['signature_path'] ?? '')) !== '' && is_file((string) $confirmation['signature_path'])) {
        $signature = @imagecreatefrompng((string) $confirmation['signature_path']);

        if ($signature !== false) {
            $srcWidth = imagesx($signature);
            $srcHeight = imagesy($signature);
            $targetWidth = $columnWidth - 8;
            $targetHeight = 58;
            $scale = min($targetWidth / max(1, $srcWidth), $targetHeight / max(1, $srcHeight));
            $drawWidth = max(1, (int) floor($srcWidth * $scale));
            $drawHeight = max(1, (int) floor($srcHeight * $scale));
            imagecopyresampled(
                $page,
                $signature,
                $rightX,
                $rightY + 6,
                0,
                0,
                $drawWidth,
                $drawHeight,
                $srcWidth,
                $srcHeight
            );
            imagedestroy($signature);
            $rightY += $drawHeight + 10;
        }
    }

    if ($confirmedAt !== '') {
        $rightY += job_report_renderer_text_block($renderer, $confirmedAt, $rightX, $rightY, 10, $columnWidth - 8, false, $renderer['colors']['muted']);
    }

    $renderer['y'] = max($leftY, $rightY, $boxTop + max($workerHeight, $customerHeight)) + 16;
}

function job_report_renderer_confirmation_signature_height(array $confirmation): int
{
    $path = trim((string) ($confirmation['signature_path'] ?? ''));

    if ($path === '' || !is_file($path)) {
        return 0;
    }

    return 68;
}

function job_report_renderer_section_title(array &$renderer, string $title): void
{
    job_report_renderer_text($renderer, $title, $renderer['margin'], $renderer['y'], 15, true);
    $renderer['y'] += 14;
    imagefilledrectangle(
        job_report_renderer_current_page($renderer),
        $renderer['margin'],
        $renderer['y'],
        $renderer['margin'] + 72,
        $renderer['y'] + 3,
        $renderer['colors']['accent']
    );
    $renderer['y'] += 16;
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
    $bottom = $renderer['page_height'] - $renderer['margin'];

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
