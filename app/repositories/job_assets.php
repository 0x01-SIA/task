<?php

declare(strict_types=1);

function job_asset_connection(): PDO
{
    $connection = database_connection();

    if ($connection === null) {
        throw new RuntimeException('Database connection is unavailable.');
    }

    return $connection;
}

function job_asset_table(string $type): string
{
    return match ($type) {
        'attachment' => 'job_attachments',
        'photo' => 'job_photos',
        default => throw new InvalidArgumentException('Unsupported job asset type.'),
    };
}

function job_asset_base_directory(): string
{
    $configured = trim((string) config('uploads.base_dir', ''));
    $fallback = base_path('storage/uploads');

    if ($configured !== '') {
        if (job_asset_directory_is_available($configured)) {
            return $configured;
        }

        error_log(sprintf('[job_assets.storage] Configured upload directory is not writable: %s', $configured));

        throw new RuntimeException('The upload directory is not available. Check UPLOAD_BASE_DIR for this environment.');
    }

    if (app_is_production()) {
        error_log('[job_assets.storage] UPLOAD_BASE_DIR is required in production.');
        throw new RuntimeException('The upload directory is not configured for this environment.');
    }

    if (job_asset_directory_is_available($fallback)) {
        return $fallback;
    }

    error_log(sprintf('[job_assets.storage] Development upload fallback is not writable: %s', $fallback));
    throw new RuntimeException('No writable upload directory is available. Configure UPLOAD_BASE_DIR for the PHP runtime.');
}

function job_asset_directory_is_available(string $directory): bool
{
    if ($directory === '') {
        return false;
    }

    if (is_dir($directory)) {
        return is_writable($directory);
    }

    $parentDirectory = dirname($directory);

    while ($parentDirectory !== '' && $parentDirectory !== '.' && !is_dir($parentDirectory)) {
        $nextParent = dirname($parentDirectory);

        if ($nextParent === $parentDirectory) {
            break;
        }

        $parentDirectory = $nextParent;
    }

    return is_dir($parentDirectory) && is_writable($parentDirectory);
}

function job_asset_directory(int $jobId, string $type): string
{
    return job_asset_base_directory() . DIRECTORY_SEPARATOR . job_asset_relative_directory($jobId, $type);
}

function job_asset_relative_directory(int $jobId, string $type): string
{
    $segment = $type === 'attachment' ? 'attachments' : 'photos';

    return 'jobs'
        . DIRECTORY_SEPARATOR
        . $jobId
        . DIRECTORY_SEPARATOR
        . $segment;
}

function ensure_job_asset_directory(int $jobId, string $type): string
{
    $directory = job_asset_directory($jobId, $type);

    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create the upload directory.');
    }

    return $directory;
}

function job_asset_relative_path(int $jobId, string $type, string $storedName): string
{
    return str_replace(DIRECTORY_SEPARATOR, '/', job_asset_relative_directory($jobId, $type))
        . '/'
        . ltrim($storedName, '/');
}

function job_asset_is_absolute_path(string $path): bool
{
    return $path !== '' && (
        str_starts_with($path, DIRECTORY_SEPARATOR)
        || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1
    );
}

function resolve_job_asset_path(string $storedPath): ?string
{
    $trimmedPath = trim($storedPath);

    if ($trimmedPath === '') {
        return null;
    }

    if (job_asset_is_absolute_path($trimmedPath)) {
        return resolve_legacy_job_asset_path($trimmedPath);
    }

    if (preg_match('#(^|[\\\\/])\.\.([\\\\/]|$)#', $trimmedPath) === 1) {
        throw new RuntimeException('Invalid stored asset path.');
    }

    $baseDirectory = job_asset_base_directory();
    $candidate = $baseDirectory . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($trimmedPath, '/\\'));

    return assert_job_asset_path_within_base($candidate, false);
}

function resolve_legacy_job_asset_path(string $absolutePath): ?string
{
    $baseDirectory = job_asset_base_directory();
    $resolvedBase = realpath($baseDirectory);
    $resolvedPath = realpath($absolutePath);

    if ($resolvedBase === false || $resolvedPath === false || !is_file($resolvedPath)) {
        return null;
    }

    $normalizedBase = rtrim($resolvedBase, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    if (!str_starts_with($resolvedPath, $normalizedBase)) {
        throw new RuntimeException('Stored asset path is outside the upload directory.');
    }

    return $resolvedPath;
}

function assert_job_asset_path_within_base(string $path, bool $mustExist): ?string
{
    $baseDirectory = job_asset_base_directory();
    $resolvedBase = realpath($baseDirectory);

    if ($resolvedBase === false) {
        throw new RuntimeException('The upload directory is not available.');
    }

    if ($mustExist) {
        $resolvedPath = realpath($path);

        if ($resolvedPath === false || !is_file($resolvedPath)) {
            return null;
        }
    } else {
        $parentDirectory = dirname($path);
        $resolvedParent = realpath($parentDirectory);

        if ($resolvedParent === false) {
            return null;
        }

        $resolvedPath = $resolvedParent . DIRECTORY_SEPARATOR . basename($path);
    }

    $normalizedBase = rtrim($resolvedBase, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    if (!str_starts_with($resolvedPath, $normalizedBase)) {
        throw new RuntimeException('Stored asset path is outside the upload directory.');
    }

    return $resolvedPath;
}

function job_attachment_rules(): array
{
    $defaultMaxBytes = min(job_server_upload_limit_bytes(), 10 * 1024 * 1024);
    $configuredMaxBytes = (int) config('uploads.attachments.max_bytes', $defaultMaxBytes);
    $maxBytes = min($configuredMaxBytes > 0 ? $configuredMaxBytes : $defaultMaxBytes, job_server_upload_limit_bytes());

    return [
        'max_bytes' => $maxBytes,
        'extensions' => [
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xls' => ['application/vnd.ms-excel'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'csv' => ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'],
            'txt' => ['text/plain'],
            'zip' => ['application/zip', 'application/x-zip-compressed', 'multipart/x-zip'],
        ],
    ];
}

function job_photo_rules(): array
{
    $defaultMaxBytes = min(job_server_upload_limit_bytes(), 25 * 1024 * 1024);
    $configuredMaxBytes = (int) config('uploads.photos.max_bytes', $defaultMaxBytes);
    $maxBytes = min($configuredMaxBytes > 0 ? $configuredMaxBytes : $defaultMaxBytes, job_server_upload_limit_bytes());
    $configuredMaxFiles = (int) config('uploads.photos.max_files', 10);

    return [
        'max_bytes' => $maxBytes,
        'max_files' => $configuredMaxFiles > 0 ? $configuredMaxFiles : 10,
        'extensions' => [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp'],
        ],
    ];
}

function job_server_upload_limit_bytes(): int
{
    static $limit = null;

    if ($limit !== null) {
        return $limit;
    }

    $uploadMax = ini_size_to_bytes((string) ini_get('upload_max_filesize'));
    $postMax = ini_size_to_bytes((string) ini_get('post_max_size'));
    $candidates = array_filter([$uploadMax, $postMax], static fn (int $value): bool => $value > 0);

    $limit = $candidates === [] ? 10 * 1024 * 1024 : min($candidates);

    return $limit;
}

function ini_size_to_bytes(string $value): int
{
    $trimmed = trim($value);

    if ($trimmed === '') {
        return 0;
    }

    $unit = strtolower(substr($trimmed, -1));
    $bytes = (float) $trimmed;

    return match ($unit) {
        'g' => (int) ($bytes * 1024 * 1024 * 1024),
        'm' => (int) ($bytes * 1024 * 1024),
        'k' => (int) ($bytes * 1024),
        default => (int) $bytes,
    };
}

function detect_uploaded_file_mime(string $path): string
{
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($path);

    return is_string($mime) && $mime !== '' ? $mime : 'application/octet-stream';
}

function uploaded_file_error_message(int $errorCode): string
{
    return match ($errorCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the allowed size limit.',
        UPLOAD_ERR_PARTIAL => 'The file upload was incomplete. Please try again.',
        UPLOAD_ERR_NO_FILE => 'Choose a file before uploading.',
        UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'The server could not accept this upload.',
        default => 'The upload failed. Please try again.',
    };
}

function validate_job_attachment_upload(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return uploaded_file_error_message((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE));
    }

    $tmpPath = (string) ($file['tmp_name'] ?? '');

    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        return 'The uploaded file could not be verified.';
    }

    $originalName = trim((string) ($file['name'] ?? ''));

    if ($originalName === '') {
        return 'The uploaded file must have a filename.';
    }

    $rules = job_attachment_rules();
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (!array_key_exists($extension, $rules['extensions'])) {
        return 'Unsupported attachment type. Allowed types: PDF, DOC, DOCX, XLS, XLSX, CSV, TXT, ZIP.';
    }

    $size = (int) ($file['size'] ?? 0);

    if ($size <= 0) {
        return 'The uploaded file is empty.';
    }

    if ($size > $rules['max_bytes']) {
        return 'The attachment exceeds the configured size limit.';
    }

    $mime = detect_uploaded_file_mime($tmpPath);

    if (!in_array($mime, $rules['extensions'][$extension], true)) {
        return 'The uploaded file type does not match its extension.';
    }

    return null;
}

function validate_job_photo_upload(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return uploaded_file_error_message((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE));
    }

    $tmpPath = (string) ($file['tmp_name'] ?? '');

    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        return 'The uploaded image could not be verified.';
    }

    $originalName = trim((string) ($file['name'] ?? ''));

    if ($originalName === '') {
        return 'The uploaded image must have a filename.';
    }

    $rules = job_photo_rules();
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (!array_key_exists($extension, $rules['extensions'])) {
        return 'Unsupported photo type. Allowed types: JPEG, PNG, WebP.';
    }

    $size = (int) ($file['size'] ?? 0);

    if ($size <= 0) {
        return 'The uploaded image is empty.';
    }

    if ($size > $rules['max_bytes']) {
        return 'The photo exceeds the configured size limit.';
    }

    $imageInfo = @getimagesize($tmpPath);

    if ($imageInfo === false) {
        return 'The uploaded file is not a valid image.';
    }

    $mime = detect_uploaded_file_mime($tmpPath);

    if (!in_array($mime, $rules['extensions'][$extension], true)) {
        return 'The uploaded image type does not match its extension.';
    }

    if (!in_array((string) ($imageInfo['mime'] ?? ''), $rules['extensions'][$extension], true)) {
        return 'The uploaded image contents are invalid.';
    }

    return null;
}

function normalize_uploaded_file_list(array $files): array
{
    $keys = ['name', 'type', 'tmp_name', 'error', 'size'];

    foreach ($keys as $key) {
        if (!array_key_exists($key, $files)) {
            return [
                'valid' => false,
                'files' => [],
            ];
        }
    }

    if (!is_array($files['name'])) {
        return [
            'valid' => true,
            'files' => [$files],
        ];
    }

    foreach ($keys as $key) {
        if (!is_array($files[$key]) || count($files[$key]) !== count($files['name'])) {
            return [
                'valid' => false,
                'files' => [],
            ];
        }
    }

    $normalized = [];
    $fileCount = count($files['name']);

    for ($index = 0; $index < $fileCount; $index++) {
        foreach ($keys as $key) {
            if (is_array($files[$key][$index] ?? null)) {
                return [
                    'valid' => false,
                    'files' => [],
                ];
            }
        }

        $normalized[] = [
            'name' => $files['name'][$index] ?? '',
            'type' => $files['type'][$index] ?? '',
            'tmp_name' => $files['tmp_name'][$index] ?? '',
            'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$index] ?? 0,
        ];
    }

    return [
        'valid' => true,
        'files' => $normalized,
    ];
}

function summarize_job_photo_upload_result(array $result): string
{
    $successCount = (int) ($result['uploaded'] ?? 0);
    $failures = is_array($result['failures'] ?? null) ? $result['failures'] : [];

    if ($successCount === 0 && count($failures) === 1 && is_string($failures[0]['message'] ?? null)) {
        return $failures[0]['message'];
    }

    $parts = [];

    if ($successCount > 0) {
        $parts[] = $successCount . ' ' . ($successCount === 1 ? 'photo uploaded successfully.' : 'photos uploaded successfully.');
    }

    if ($failures !== []) {
        $counts = [];

        foreach ($failures as $failure) {
            $category = (string) ($failure['category'] ?? 'failed');
            $counts[$category] = ($counts[$category] ?? 0) + 1;
        }

        $labels = [
            'too_large' => ['file was too large.', 'files were too large.'],
            'unsupported_type' => ['file type was not supported.', 'file types were not supported.'],
            'invalid_image' => ['file was not a valid image.', 'files were not valid images.'],
            'incomplete' => ['upload was incomplete.', 'uploads were incomplete.'],
            'too_many' => ['file exceeded the upload count limit.', 'files exceeded the upload count limit.'],
            'failed' => ['file could not be uploaded.', 'files could not be uploaded.'],
        ];

        foreach ($counts as $category => $count) {
            $labelSet = $labels[$category] ?? $labels['failed'];
            $parts[] = $count . ' ' . ($count === 1 ? $labelSet[0] : $labelSet[1]);
        }
    }

    if ($parts === []) {
        return 'No photos were uploaded.';
    }

    return implode(' ', $parts);
}

function store_uploaded_job_photo_batch(int $jobId, array $files, int $userId, ?string $caption = null): array
{
    $normalized = normalize_uploaded_file_list($files);

    if (!$normalized['valid']) {
        return [
            'uploaded' => 0,
            'failures' => [
                [
                    'category' => 'failed',
                    'message' => 'The uploaded files could not be processed. Please try again.',
                ],
            ],
        ];
    }

    $selectedFiles = array_values(array_filter(
        $normalized['files'],
        static fn (array $file): bool => !(
            ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE)) === UPLOAD_ERR_NO_FILE
            && trim((string) ($file['name'] ?? '')) === ''
            && trim((string) ($file['tmp_name'] ?? '')) === ''
            && (int) ($file['size'] ?? 0) === 0
        )
    ));

    if ($selectedFiles === []) {
        return [
            'uploaded' => 0,
            'failures' => [
                ['category' => 'failed', 'message' => 'Choose at least one photo before uploading.'],
            ],
        ];
    }

    $rules = job_photo_rules();
    $processableFiles = array_slice($selectedFiles, 0, $rules['max_files']);
    $extraFiles = max(0, count($selectedFiles) - count($processableFiles));
    $result = [
        'uploaded' => 0,
        'failures' => [],
    ];

    for ($index = 0; $index < $extraFiles; $index++) {
        $result['failures'][] = [
            'category' => 'too_many',
            'message' => 'You can upload up to ' . $rules['max_files'] . ' photos at once.',
        ];
    }

    foreach ($processableFiles as $file) {
        $validationError = validate_job_photo_upload($file);

        if ($validationError !== null) {
            $result['failures'][] = [
                'category' => job_photo_upload_failure_category($validationError),
                'message' => $validationError,
            ];
            continue;
        }

        try {
            store_uploaded_job_photo($jobId, $file, $userId, $caption);
            $result['uploaded']++;
        } catch (Throwable $exception) {
            error_log(sprintf(
                '[job_photos.upload] company_id=%d job_id=%d user_id=%d: %s',
                (int) (current_company_id() ?? 0),
                $jobId,
                $userId,
                $exception->getMessage()
            ));
            $result['failures'][] = [
                'category' => 'failed',
                'message' => 'The photo could not be uploaded.',
            ];
        }
    }

    return $result;
}

function job_photo_upload_failure_category(string $message): string
{
    return match ($message) {
        'The uploaded file exceeds the allowed size limit.',
        'The photo exceeds the configured size limit.' => 'too_large',
        'Unsupported photo type. Allowed types: JPEG, PNG, WebP.' => 'unsupported_type',
        'The file upload was incomplete. Please try again.' => 'incomplete',
        'The uploaded image could not be verified.',
        'The uploaded image must have a filename.',
        'The uploaded image is empty.',
        'The uploaded file is not a valid image.',
        'The uploaded image type does not match its extension.',
        'The uploaded image contents are invalid.' => 'invalid_image',
        default => 'failed',
    };
}

function store_uploaded_job_attachment(int $jobId, array $file, int $userId): void
{
    $directory = ensure_job_asset_directory($jobId, 'attachment');
    $originalName = (string) $file['name'];
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $storedName = bin2hex(random_bytes(16)) . ($extension !== '' ? '.' . $extension : '');
    $storedPath = $directory . DIRECTORY_SEPARATOR . $storedName;
    $relativePath = job_asset_relative_path($jobId, 'attachment', $storedName);

    if (!move_uploaded_file((string) $file['tmp_name'], $storedPath)) {
        throw new RuntimeException('The attachment could not be stored.');
    }

    try {
        create_job_asset_record('attachment', [
            'company_id' => current_company_id(),
            'job_id' => $jobId,
            'original_filename' => basename($originalName),
            'stored_filename' => $storedName,
            'storage_path' => $relativePath,
            'mime_type' => detect_uploaded_file_mime($storedPath),
            'file_size' => filesize($storedPath) ?: (int) $file['size'],
            'uploaded_by_user_id' => $userId,
        ]);
    } catch (Throwable $exception) {
        if (is_file($storedPath)) {
            @unlink($storedPath);
        }

        error_log(sprintf(
            '[job_attachments.upload] company_id=%d job_id=%d user_id=%d: %s',
            (int) (current_company_id() ?? 0),
            $jobId,
            $userId,
            $exception->getMessage()
        ));

        throw $exception;
    }
}

function store_uploaded_job_photo(int $jobId, array $file, int $userId, ?string $caption = null): void
{
    $directory = ensure_job_asset_directory($jobId, 'photo');
    $originalName = (string) $file['name'];
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $storedName = bin2hex(random_bytes(16)) . ($extension !== '' ? '.' . $extension : '');
    $storedPath = $directory . DIRECTORY_SEPARATOR . $storedName;
    $relativePath = job_asset_relative_path($jobId, 'photo', $storedName);

    if (!move_uploaded_file((string) $file['tmp_name'], $storedPath)) {
        throw new RuntimeException('The photo could not be stored.');
    }

    try {
        create_job_asset_record('photo', [
            'company_id' => current_company_id(),
            'job_id' => $jobId,
            'original_filename' => basename($originalName),
            'stored_filename' => $storedName,
            'storage_path' => $relativePath,
            'mime_type' => detect_uploaded_file_mime($storedPath),
            'file_size' => filesize($storedPath) ?: (int) $file['size'],
            'uploaded_by_user_id' => $userId,
            'caption' => $caption,
        ]);
    } catch (Throwable $exception) {
        if (is_file($storedPath)) {
            @unlink($storedPath);
        }

        error_log(sprintf(
            '[job_photos.upload] company_id=%d job_id=%d user_id=%d: %s',
            (int) (current_company_id() ?? 0),
            $jobId,
            $userId,
            $exception->getMessage()
        ));

        throw $exception;
    }
}

function create_job_asset_record(string $type, array $data): void
{
    $table = job_asset_table($type);

    $columns = [
        'company_id',
        'job_id',
        'original_filename',
        'stored_filename',
        'storage_path',
        'mime_type',
        'file_size',
        'uploaded_by_user_id',
    ];
    $placeholders = [
        ':company_id',
        ':job_id',
        ':original_filename',
        ':stored_filename',
        ':storage_path',
        ':mime_type',
        ':file_size',
        ':uploaded_by_user_id',
    ];

    if ($type === 'photo') {
        $columns[] = 'caption';
        $placeholders[] = ':caption';
    }

    $params = [
        'company_id' => $data['company_id'],
        'job_id' => $data['job_id'],
        'original_filename' => $data['original_filename'],
        'stored_filename' => $data['stored_filename'],
        'storage_path' => $data['storage_path'],
        'mime_type' => $data['mime_type'],
        'file_size' => $data['file_size'],
        'uploaded_by_user_id' => $data['uploaded_by_user_id'],
    ];

    if ($type === 'photo') {
        $params['caption'] = $data['caption'] ?? null;
    }

    $statement = job_asset_connection()->prepare(
        'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ')
         VALUES (' . implode(', ', $placeholders) . ')'
    );
    $statement->execute($params);
}

function list_job_attachments(int $jobId): array
{
    return list_job_assets($jobId, 'attachment');
}

function list_job_photos(int $jobId): array
{
    return list_job_assets($jobId, 'photo');
}

function list_job_assets(int $jobId, string $type): array
{
    $table = job_asset_table($type);
    $captionSelect = $type === 'photo' ? ', a.caption' : '';
    $params = ['job_id' => $jobId];
    $sql = 'SELECT
            a.id,
            a.company_id,
            a.job_id,
            a.original_filename,
            a.stored_filename,
            a.storage_path,
            a.mime_type,
            a.file_size,
            a.uploaded_by_user_id,
            a.uploaded_at' . $captionSelect . ',
            u.name AS uploader_name
         FROM ' . $table . ' a
         LEFT JOIN users u ON u.id = a.uploaded_by_user_id
         WHERE a.job_id = :job_id';
    $sql .= scoped_company_sql('a.company_id', $params);
    $sql .= ' ORDER BY a.uploaded_at DESC, a.id DESC';
    $statement = job_asset_connection()->prepare($sql);
    $statement->execute($params);
    $rows = $statement->fetchAll();

    return is_array($rows) ? $rows : [];
}

function find_job_attachment_by_id(int $jobId, int $attachmentId): ?array
{
    return find_job_asset_by_id($jobId, $attachmentId, 'attachment');
}

function find_job_photo_by_id(int $jobId, int $photoId): ?array
{
    return find_job_asset_by_id($jobId, $photoId, 'photo');
}

function find_job_asset_by_id(int $jobId, int $assetId, string $type): ?array
{
    $table = job_asset_table($type);
    $captionSelect = $type === 'photo' ? ', a.caption' : '';
    $params = [
        'job_id' => $jobId,
        'asset_id' => $assetId,
    ];
    $sql = 'SELECT
            a.id,
            a.company_id,
            a.job_id,
            a.original_filename,
            a.stored_filename,
            a.storage_path,
            a.mime_type,
            a.file_size,
            a.uploaded_by_user_id,
            a.uploaded_at' . $captionSelect . ',
            u.name AS uploader_name
         FROM ' . $table . ' a
         LEFT JOIN users u ON u.id = a.uploaded_by_user_id
         WHERE a.job_id = :job_id
           AND a.id = :asset_id';
    $sql .= scoped_company_sql('a.company_id', $params);
    $sql .= ' LIMIT 1';
    $statement = job_asset_connection()->prepare($sql);
    $statement->execute($params);
    $asset = $statement->fetch();

    return is_array($asset) ? $asset : null;
}

function delete_job_attachment(int $jobId, int $attachmentId): bool
{
    return delete_job_asset($jobId, $attachmentId, 'attachment');
}

function delete_job_photo(int $jobId, int $photoId): bool
{
    return delete_job_asset($jobId, $photoId, 'photo');
}

function delete_job_asset(int $jobId, int $assetId, string $type): bool
{
    $asset = find_job_asset_by_id($jobId, $assetId, $type);

    if ($asset === null) {
        return false;
    }

    $table = job_asset_table($type);
    $params = [
        'job_id' => $jobId,
        'asset_id' => $assetId,
    ];
    $sql = 'DELETE FROM ' . $table . '
            WHERE job_id = :job_id
              AND id = :asset_id';
    $sql .= scoped_company_sql('company_id', $params);
    $statement = job_asset_connection()->prepare($sql);
    $statement->execute($params);

    if ($statement->rowCount() < 1) {
        return false;
    }

    $path = resolve_job_asset_path((string) ($asset['storage_path'] ?? ''));

    if ($path !== null && is_file($path)) {
        @unlink($path);
    }

    return true;
}

function job_is_open(array $job): bool
{
    return !in_array((string) ($job['status'] ?? ''), ['completed', 'cancelled'], true);
}

function user_can_upload_job_attachments(array $user): bool
{
    return in_array((string) ($user['role'] ?? ''), ['admin', 'dispatcher'], true);
}

function user_can_delete_job_attachments(array $user): bool
{
    return user_can_upload_job_attachments($user);
}

function user_can_upload_job_photos(array $user, array $job): bool
{
    $role = (string) ($user['role'] ?? '');

    if (in_array($role, ['admin', 'dispatcher'], true)) {
        return true;
    }

    return $role === 'worker' && (int) ($job['assigned_user_id'] ?? 0) === (int) ($user['id'] ?? 0);
}

function user_can_delete_job_photos(array $user, array $job): bool
{
    return job_is_open($job) && user_can_upload_job_photos($user, $job);
}
