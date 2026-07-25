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
    $sql = "SELECT
                u.id,
                u.name,
                u.email,
                u.role,
                u.is_active,
                u.created_at,
                u.updated_at,
                SUM(CASE WHEN j.status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) AS active_job_count
            FROM users u
            LEFT JOIN jobs j ON j.assigned_user_id = u.id
            WHERE 1 = 1";
    $params = [];

    if (($filters['role'] ?? '') !== '') {
        $sql .= ' AND u.role = :role';
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

    $sql .= ' GROUP BY u.id
              ORDER BY u.is_active DESC, u.name ASC, u.id ASC';

    $statement = users_connection()->prepare($sql);
    $statement->execute($params);
    $users = $statement->fetchAll();

    return is_array($users) ? $users : [];
}

function find_managed_user_by_id(int $id): ?array
{
    $statement = users_connection()->prepare(
        "SELECT
            u.id,
            u.name,
            u.email,
            u.role,
            u.is_active,
            u.created_at,
            u.updated_at,
            SUM(CASE WHEN j.status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) AS active_job_count,
            COUNT(j.id) AS total_job_count,
            SUM(CASE WHEN j.status = 'completed' THEN 1 ELSE 0 END) AS completed_job_count
         FROM users u
         LEFT JOIN jobs j ON j.assigned_user_id = u.id
         WHERE u.id = :id
         GROUP BY u.id
         LIMIT 1"
    );
    $statement->execute(['id' => $id]);
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
         WHERE role = 'admin'
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
    $statement = users_connection()->prepare(
        "SELECT
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
         ORDER BY
            CASE WHEN j.status IN ('completed', 'cancelled') THEN 1 ELSE 0 END ASC,
            CASE WHEN j.planned_date IS NULL THEN 1 ELSE 0 END ASC,
            j.planned_date DESC,
            j.updated_at DESC,
            j.id DESC
         LIMIT :limit"
    );
    $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->execute();
    $jobs = $statement->fetchAll();

    return is_array($jobs) ? $jobs : [];
}

function list_active_workers(): array
{
    $statement = users_connection()->query(
        "SELECT id, name, email, role
         FROM users
         WHERE role = 'worker' AND is_active = 1
         ORDER BY name ASC, id ASC"
    );
    $workers = $statement->fetchAll();

    return is_array($workers) ? $workers : [];
}

function active_worker_exists(int $id): bool
{
    $statement = users_connection()->prepare(
        "SELECT 1
         FROM users
         WHERE id = :id
           AND role = 'worker'
           AND is_active = 1
         LIMIT 1"
    );
    $statement->execute(['id' => $id]);

    return $statement->fetchColumn() !== false;
}
