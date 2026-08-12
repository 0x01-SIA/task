<?php

declare(strict_types=1);

function users_connection(): PDO
{
    $connection = database_connection();

    if ($connection === null) {
        throw new RuntimeException('Database connection is unavailable.');
    }

    return $connection;
}

function list_users(array $filters = []): array
{
    $viewer = current_user();
    $isSuperAdmin = is_super_admin($viewer);
    $params = [];
    $scopedCompanyId = current_company_id();

    if (!$isSuperAdmin) {
        if ($scopedCompanyId === null) {
            return [];
        }
    }

    if ($scopedCompanyId !== null) {
        $params['company_role_scope_id'] = $scopedCompanyId;
        $params['membership_status_scope_id'] = $scopedCompanyId;
        $params['company_memberships_scope_id'] = $scopedCompanyId;
        $params['active_job_scope_id'] = $scopedCompanyId;
        $params['user_scope_id'] = $scopedCompanyId;
    }

    $sql = "SELECT
                u.id,
                u.name,
                u.email,
                u.role AS system_role,
                u.signature_path,
                u.signature_mime_type,
                u.signature_file_size,
                u.is_active,
                u.created_at,
                u.updated_at,
                (
                    SELECT cu_current.role
                    FROM company_users cu_current
                    WHERE cu_current.user_id = u.id";

    if ($scopedCompanyId !== null) {
        $sql .= '
                      AND cu_current.company_id = :company_role_scope_id';
    }

    $sql .= '
                    LIMIT 1
                ) AS company_role,
                ';

    if ($scopedCompanyId !== null) {
        $sql .= "(
                    SELECT cu_current.is_active
                    FROM company_users cu_current
                    WHERE cu_current.user_id = u.id
                      AND cu_current.company_id = :membership_status_scope_id
                    LIMIT 1
                )";
    } else {
        $sql .= 'NULL';
    }

    $sql .= " AS membership_is_active,
                (
                    SELECT GROUP_CONCAT(DISTINCT CONCAT(c2.name, ' (', cu2.role, ')') ORDER BY c2.name ASC SEPARATOR ', ')
                    FROM company_users cu2
                    INNER JOIN companies c2 ON c2.id = cu2.company_id
                    WHERE cu2.user_id = u.id";

    if ($scopedCompanyId !== null) {
        $sql .= '
                      AND cu2.company_id = :company_memberships_scope_id';
    }

    $sql .= "
                ) AS company_memberships,
                (
                    SELECT COUNT(*)
                    FROM jobs j
                    WHERE j.assigned_user_id = u.id
                      AND j.status NOT IN ('completed', 'cancelled')";

    if ($scopedCompanyId !== null) {
        $sql .= '
                      AND j.company_id = :active_job_scope_id';
    }

    $sql .= "
                ) AS active_job_count
            FROM users u
            WHERE 1 = 1";

    if ($scopedCompanyId !== null) {
        $sql .= '
              AND EXISTS (
                    SELECT 1
                    FROM company_users scoped_cu
                    WHERE scoped_cu.user_id = u.id
                      AND scoped_cu.company_id = :user_scope_id
                )';
    }

    if (($filters['role'] ?? '') !== '') {
        $sql .= ' AND (
            u.role = :role
            OR EXISTS (
                SELECT 1
                FROM company_users role_cu
                WHERE role_cu.user_id = u.id
                  AND role_cu.role = :role';

        if ($scopedCompanyId !== null) {
            $sql .= '
                  AND role_cu.company_id = :role_scope_id';
            $params['role_scope_id'] = $scopedCompanyId;
        }

        $sql .= '
            )
        )';
        $params['role'] = $filters['role'];
    }

    if (($filters['is_active'] ?? '') !== '') {
        $sql .= ' AND u.is_active = :is_active';
        $params['is_active'] = (int) $filters['is_active'];
    }

    $search = trim((string) ($filters['search'] ?? ''));

    if ($search !== '') {
        $sql .= ' AND (u.name LIKE :search_name OR u.email LIKE :search_email)';
        $params['search_name'] = '%' . $search . '%';
        $params['search_email'] = '%' . $search . '%';
    }

    $sql .= '
              ORDER BY u.is_active DESC, u.name ASC, u.id ASC';

    $statement = users_connection()->prepare($sql);

    try {
        $statement->execute($params);
    } catch (PDOException $exception) {
        error_log(sprintf(
            'Failed to list users for company context. viewer_id=%s scoped_company_id=%s error=%s',
            isset($viewer['id']) ? (string) $viewer['id'] : 'guest',
            $scopedCompanyId !== null ? (string) $scopedCompanyId : 'all',
            $exception->getMessage()
        ));

        throw $exception;
    }
    $users = $statement->fetchAll();

    return is_array($users) ? $users : [];
}

function find_managed_user_by_id(int $id): ?array
{
    $params = ['id' => $id];
    $sql = (
        "SELECT
            u.id,
            u.name,
            u.email,
            u.role AS global_role,
            u.signature_path,
            u.signature_mime_type,
            u.signature_file_size,
            u.is_active,
            u.created_at,
            u.updated_at,
            SUM(CASE WHEN j.status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) AS active_job_count,
            COUNT(j.id) AS total_job_count,
            SUM(CASE WHEN j.status = 'completed' THEN 1 ELSE 0 END) AS completed_job_count
         FROM users u
         LEFT JOIN jobs j ON j.assigned_user_id = u.id
         WHERE u.id = :id"
    );

    if (!is_super_admin()) {
        $companyId = current_company_id();

        if ($companyId === null) {
            return null;
        }

        $sql .= ' AND EXISTS (
            SELECT 1
            FROM company_users scoped_cu
            WHERE scoped_cu.user_id = u.id
              AND scoped_cu.company_id = :company_id
        )';
        $params['company_id'] = $companyId;
    }

    $sql .= '
         GROUP BY u.id
         LIMIT 1';
    $statement = users_connection()->prepare($sql);
    $statement->execute($params);
    $user = $statement->fetch();

    return is_array($user) ? $user : null;
}

function create_user(array $data): int
{
    $connection = users_connection();
    $statement = $connection->prepare(
        'INSERT INTO users (name, email, password_hash, role, is_active)
         VALUES (:name, :email, :password_hash, :role, :is_active)'
    );
    $statement->execute([
        'name' => $data['name'],
        'email' => $data['email'],
        'password_hash' => $data['password_hash'],
        'role' => $data['role'],
        'is_active' => $data['is_active'],
    ]);

    return (int) $connection->lastInsertId();
}

function update_user(int $id, array $data): void
{
    $statement = users_connection()->prepare(
        'UPDATE users
         SET name = :name,
             email = :email,
             role = :role,
             is_active = :is_active
         WHERE id = :id'
    );
    $statement->execute([
        'id' => $id,
        'name' => $data['name'],
        'email' => $data['email'],
        'role' => $data['role'],
        'is_active' => $data['is_active'],
    ]);
}

function update_user_signature(int $id, ?string $storagePath, ?string $mimeType, ?int $fileSize): void
{
    $statement = users_connection()->prepare(
        'UPDATE users
         SET signature_path = :signature_path,
             signature_mime_type = :signature_mime_type,
             signature_file_size = :signature_file_size
         WHERE id = :id'
    );
    $statement->execute([
        'id' => $id,
        'signature_path' => $storagePath,
        'signature_mime_type' => $mimeType,
        'signature_file_size' => $fileSize,
    ]);
}

function update_user_password(int $id, string $passwordHash): void
{
    $statement = users_connection()->prepare(
        'UPDATE users
         SET password_hash = :password_hash
         WHERE id = :id'
    );
    $statement->execute([
        'id' => $id,
        'password_hash' => $passwordHash,
    ]);
}

function user_email_exists(string $email, ?int $excludeUserId = null): bool
{
    $sql = 'SELECT 1 FROM users WHERE email = :email';
    $params = ['email' => $email];

    if ($excludeUserId !== null) {
        $sql .= ' AND id <> :exclude_user_id';
        $params['exclude_user_id'] = $excludeUserId;
    }

    $sql .= ' LIMIT 1';

    $statement = users_connection()->prepare($sql);
    $statement->execute($params);

    return $statement->fetchColumn() !== false;
}

function count_active_admin_users(): int
{
    $statement = users_connection()->query(
        "SELECT COUNT(*)
         FROM users
         WHERE role = 'super_admin'
           AND is_active = 1"
    );

    return (int) $statement->fetchColumn();
}

function count_assigned_active_jobs_for_user(int $userId): int
{
    $statement = users_connection()->prepare(
        "SELECT COUNT(*)
         FROM jobs
         WHERE assigned_user_id = :user_id
           AND status NOT IN ('completed', 'cancelled')"
    );
    $statement->execute(['user_id' => $userId]);

    return (int) $statement->fetchColumn();
}

function recent_assigned_jobs_for_user(int $userId, int $limit = 5): array
{
    $params = ['user_id' => $userId];
    $sql = "SELECT
            j.id,
            j.job_number,
            j.title,
            j.status,
            j.priority,
            j.planned_date,
            j.planned_start_time,
            j.updated_at,
            c.name AS customer_name,
            l.name AS location_name
         FROM jobs j
         INNER JOIN customers c ON c.id = j.customer_id
         LEFT JOIN locations l ON l.id = j.location_id
         WHERE j.assigned_user_id = :user_id
    ";
    $sql .= scoped_company_sql('j.company_id', $params);
    $sql .= " ORDER BY
            CASE WHEN j.status IN ('completed', 'cancelled') THEN 1 ELSE 0 END ASC,
            CASE WHEN j.planned_date IS NULL THEN 1 ELSE 0 END ASC,
            j.planned_date DESC,
            j.updated_at DESC,
            j.id DESC
         LIMIT :limit";
    $statement = users_connection()->prepare($sql);
    $statement->bindValue(':user_id', $params['user_id'], PDO::PARAM_INT);
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);

    if (isset($params['__scoped_company_id'])) {
        $statement->bindValue(':__scoped_company_id', $params['__scoped_company_id'], PDO::PARAM_INT);
    }

    $statement->execute();
    $jobs = $statement->fetchAll();

    return is_array($jobs) ? $jobs : [];
}

function user_signature_rules(): array
{
    $defaultMaxBytes = min(job_server_upload_limit_bytes(), 2 * 1024 * 1024);

    return [
        'max_bytes' => $defaultMaxBytes,
        'extensions' => [
            'png' => ['image/png'],
        ],
    ];
}

function user_signature_relative_directory(int $userId): string
{
    return 'users'
        . DIRECTORY_SEPARATOR
        . $userId
        . DIRECTORY_SEPARATOR
        . 'signature';
}

function user_signature_directory(int $userId): string
{
    return job_asset_base_directory() . DIRECTORY_SEPARATOR . user_signature_relative_directory($userId);
}

function ensure_user_signature_directory(int $userId): string
{
    $directory = user_signature_directory($userId);

    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create the signature upload directory.');
    }

    return $directory;
}

function user_signature_relative_path(int $userId, string $storedName): string
{
    return str_replace(DIRECTORY_SEPARATOR, '/', user_signature_relative_directory($userId))
        . '/'
        . ltrim($storedName, '/');
}

function validate_user_signature_upload(array $file): ?string
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($error !== UPLOAD_ERR_OK) {
        return uploaded_file_error_message($error);
    }

    $tmpPath = (string) ($file['tmp_name'] ?? '');

    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        return 'The uploaded signature image could not be verified.';
    }

    $originalName = trim((string) ($file['name'] ?? ''));

    if ($originalName === '') {
        return 'The uploaded signature image must have a filename.';
    }

    if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'png') {
        return 'The signature image must be a PNG file.';
    }

    $fileSize = (int) ($file['size'] ?? 0);

    if ($fileSize <= 0) {
        return 'The uploaded signature image is empty.';
    }

    $rules = user_signature_rules();

    if ($fileSize > $rules['max_bytes']) {
        return 'The signature image exceeds the allowed size limit.';
    }

    $imageInfo = @getimagesize($tmpPath);

    if ($imageInfo === false || (string) ($imageInfo['mime'] ?? '') !== 'image/png') {
        return 'The uploaded signature image must be a valid PNG.';
    }

    $mime = detect_uploaded_file_mime($tmpPath);

    if ($mime !== 'image/png') {
        return 'The uploaded signature image type does not match its extension.';
    }

    return null;
}

function store_uploaded_user_signature(int $userId, array $file): array
{
    $directory = ensure_user_signature_directory($userId);
    $storedName = 'signature-' . bin2hex(random_bytes(8)) . '.png';
    $storedPath = $directory . DIRECTORY_SEPARATOR . $storedName;

    if (!move_uploaded_file((string) $file['tmp_name'], $storedPath)) {
        throw new RuntimeException('The signature image could not be stored.');
    }

    return [
        'storage_path' => user_signature_relative_path($userId, $storedName),
        'mime_type' => 'image/png',
        'file_size' => filesize($storedPath) ?: (int) ($file['size'] ?? 0),
    ];
}

function resolved_user_signature_path(array $user): ?string
{
    return resolve_job_asset_path((string) ($user['signature_path'] ?? ''));
}

function remove_user_signature_file(array $user): void
{
    $path = resolved_user_signature_path($user);

    if ($path !== null && is_file($path) && !unlink($path)) {
        throw new RuntimeException('The stored user signature could not be removed.');
    }
}

function user_signature_asset(array $user): array
{
    return [
        'storage_path' => (string) ($user['signature_path'] ?? ''),
        'mime_type' => (string) ($user['signature_mime_type'] ?? 'image/png'),
        'file_size' => (int) ($user['signature_file_size'] ?? 0),
        'original_filename' => 'user-signature.png',
    ];
}

function list_active_workers(): array
{
    $params = [];
    $sql = "SELECT DISTINCT u.id, u.name, u.email, cu.role
            FROM users u
            INNER JOIN company_users cu
                ON cu.user_id = u.id
               AND cu.role = 'worker'
               AND cu.is_active = 1
            WHERE u.is_active = 1";
    $sql .= scoped_company_sql('cu.company_id', $params, current_user(), false);
    $sql .= ' ORDER BY u.name ASC, u.id ASC';
    $statement = users_connection()->prepare($sql);
    $statement->execute($params);
    $workers = $statement->fetchAll();

    return is_array($workers) ? $workers : [];
}

function active_worker_exists(int $id): bool
{
    $params = ['id' => $id];
    $sql = "SELECT 1
            FROM users u
            INNER JOIN company_users cu
                ON cu.user_id = u.id
               AND cu.role = 'worker'
               AND cu.is_active = 1
            WHERE u.id = :id
              AND u.is_active = 1";
    $sql .= scoped_company_sql('cu.company_id', $params, current_user(), false);
    $sql .= ' LIMIT 1';
    $statement = users_connection()->prepare($sql);
    $statement->execute($params);

    return $statement->fetchColumn() !== false;
}

function list_user_memberships(int $userId): array
{
    $statement = users_connection()->prepare(
        "SELECT
            cu.company_id,
            c.name AS company_name,
            c.is_active AS company_is_active,
            cu.role,
            cu.is_active,
            cu.created_at,
            cu.updated_at
         FROM company_users cu
         INNER JOIN companies c ON c.id = cu.company_id
         WHERE cu.user_id = :user_id
         ORDER BY c.name ASC"
    );
    $statement->execute(['user_id' => $userId]);
    $memberships = $statement->fetchAll();

    return is_array($memberships) ? $memberships : [];
}

function list_all_users_basic(): array
{
    $statement = users_connection()->query(
        'SELECT id, name, email, role AS global_role, is_active
         FROM users
         ORDER BY name ASC, id ASC'
    );
    $users = $statement->fetchAll();

    return is_array($users) ? $users : [];
}
