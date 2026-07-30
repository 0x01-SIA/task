<?php

declare(strict_types=1);

function jobs_connection(): PDO
{
    $connection = database_connection();

    if ($connection === null) {
        throw new RuntimeException('Database connection is unavailable.');
    }

    return $connection;
}

function list_jobs(array $filters = [], ?array $viewer = null): array
{
    $sql = 'SELECT
                j.id,
                j.company_id,
                j.job_number,
                j.task_id,
                j.customer_id,
                j.location_id,
                j.title,
                j.job_type,
                j.status,
                j.priority,
                j.assigned_user_id,
                j.planned_date,
                j.planned_start_time,
                j.estimated_duration_minutes,
                j.updated_at,
                c.name AS customer_name,
                l.name AS location_name,
                l.address_line,
                l.city,
                l.postal_code,
                l.country,
                u.name AS assigned_worker_name,
                t.task_number AS linked_task_number,
                t.title AS linked_task_title
            FROM jobs j
            INNER JOIN customers c ON c.id = j.customer_id
            LEFT JOIN locations l ON l.id = j.location_id
            LEFT JOIN users u ON u.id = j.assigned_user_id
            LEFT JOIN tasks t ON t.id = j.task_id
            WHERE 1 = 1';
    $params = [];
    $sql .= scoped_company_sql('j.company_id', $params, $viewer);

    if (($viewer['role'] ?? null) === 'worker') {
        $sql .= ' AND j.assigned_user_id = :viewer_user_id';
        $params['viewer_user_id'] = (int) $viewer['id'];
    }

    $search = trim((string) ($filters['search'] ?? ''));

    if ($search !== '') {
        $sql .= ' AND (
            j.job_number LIKE :search_job_number
            OR j.title LIKE :search_title
            OR c.name LIKE :search_customer
            OR COALESCE(l.name, \'\') LIKE :search_location_name
            OR COALESCE(l.address_line, \'\') LIKE :search_address
            OR COALESCE(l.city, \'\') LIKE :search_city
            OR COALESCE(l.postal_code, \'\') LIKE :search_postal_code
            OR COALESCE(l.country, \'\') LIKE :search_country
        )';
        $searchTerm = '%' . $search . '%';
        $params['search_job_number'] = $searchTerm;
        $params['search_title'] = $searchTerm;
        $params['search_customer'] = $searchTerm;
        $params['search_location_name'] = $searchTerm;
        $params['search_address'] = $searchTerm;
        $params['search_city'] = $searchTerm;
        $params['search_postal_code'] = $searchTerm;
        $params['search_country'] = $searchTerm;
    }

    if (($filters['status'] ?? '') !== '') {
        $sql .= ' AND j.status = :status';
        $params['status'] = $filters['status'];
    }

    if (($filters['customer_id'] ?? null) !== null) {
        $sql .= ' AND j.customer_id = :customer_id';
        $params['customer_id'] = (int) $filters['customer_id'];
    }

    if (($filters['planned_date'] ?? '') !== '') {
        $sql .= ' AND j.planned_date = :planned_date';
        $params['planned_date'] = $filters['planned_date'];
    }

    if (($filters['schedule'] ?? '') === 'unscheduled') {
        $sql .= " AND j.planned_date IS NULL
                  AND j.status NOT IN ('completed', 'cancelled')";
    }

    if (($viewer['role'] ?? null) !== 'worker' && ($filters['worker_id'] ?? null) !== null) {
        $sql .= ' AND j.assigned_user_id = :worker_id';
        $params['worker_id'] = (int) $filters['worker_id'];
    }

    $sql .= ' ORDER BY
        CASE WHEN j.planned_date IS NULL THEN 2
             WHEN j.planned_date < CURRENT_DATE() THEN 1
             ELSE 0
        END ASC,
        CASE WHEN j.planned_date < CURRENT_DATE() THEN NULL ELSE j.planned_date END ASC,
        CASE WHEN j.planned_date < CURRENT_DATE() THEN j.planned_date ELSE NULL END DESC,
        CASE WHEN j.planned_start_time IS NULL THEN 1 ELSE 0 END ASC,
        j.planned_start_time ASC,
        j.updated_at DESC,
        j.id DESC';

    $statement = jobs_connection()->prepare($sql);
    $statement->execute($params);
    $jobs = $statement->fetchAll();

    return is_array($jobs) ? $jobs : [];
}

function find_job_by_id(int $id, ?array $viewer = null): ?array
{
    $sql = 'SELECT
                j.id,
                j.company_id,
                j.job_number,
                j.task_id,
                j.customer_id,
                j.location_id,
                j.title,
                j.description,
                j.job_type,
                j.status,
                j.priority,
                j.assigned_user_id,
                j.planned_date,
                j.planned_start_time,
                j.estimated_duration_minutes,
                j.actual_start_at,
                j.actual_completed_at,
                j.internal_notes,
                j.created_by_user_id,
                j.created_at,
                j.updated_at,
                c.name AS customer_name,
                l.name AS location_name,
                l.address_line,
                l.city,
                l.postal_code,
                l.country,
                u.name AS assigned_worker_name,
                t.task_number AS linked_task_number,
                t.title AS linked_task_title
            FROM jobs j
            INNER JOIN customers c ON c.id = j.customer_id
            LEFT JOIN locations l ON l.id = j.location_id
            LEFT JOIN users u ON u.id = j.assigned_user_id
            LEFT JOIN tasks t ON t.id = j.task_id
            WHERE j.id = :id';
    $params = ['id' => $id];
    $sql .= scoped_company_sql('j.company_id', $params, $viewer);

    if (($viewer['role'] ?? null) === 'worker') {
        $sql .= ' AND j.assigned_user_id = :viewer_user_id';
        $params['viewer_user_id'] = (int) $viewer['id'];
    }

    $sql .= ' LIMIT 1';

    $statement = jobs_connection()->prepare($sql);
    $statement->execute($params);
    $job = $statement->fetch();

    return is_array($job) ? $job : null;
}

function find_jobs_for_calendar(PDO $pdo, string $startDate, string $endDate, ?array $viewer = null): array
{
    $sql = "SELECT
            j.id,
            j.job_number,
            j.task_id,
            j.status,
            j.planned_date,
            j.planned_start_time,
            c.name AS customer_name,
            l.name AS location_name,
            l.address_line,
            u.name AS assigned_worker_name,
            t.task_number AS linked_task_number,
            t.title AS linked_task_title
         FROM jobs j
         INNER JOIN customers c ON c.id = j.customer_id
         LEFT JOIN locations l ON l.id = j.location_id
         LEFT JOIN users u ON u.id = j.assigned_user_id
         LEFT JOIN tasks t ON t.id = j.task_id
         WHERE j.planned_date BETWEEN :start_date AND :end_date";
    $params = [
        'start_date' => $startDate,
        'end_date' => $endDate,
    ];
    $sql .= scoped_company_sql('j.company_id', $params, $viewer);

    if (($viewer['role'] ?? '') === 'worker') {
        $sql .= ' AND j.assigned_user_id = :viewer_user_id';
        $params['viewer_user_id'] = (int) $viewer['id'];
    }

    $sql .= " ORDER BY
            j.planned_date ASC,
            CASE WHEN j.planned_start_time IS NULL THEN 1 ELSE 0 END ASC,
            j.planned_start_time ASC,
            j.job_number ASC";

    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $jobs = $statement->fetchAll();

    return is_array($jobs) ? $jobs : [];
}

function count_unscheduled_active_jobs(PDO $pdo, ?array $viewer = null): int
{
    $sql = "SELECT COUNT(*)
         FROM jobs
         WHERE planned_date IS NULL
           AND status NOT IN ('completed', 'cancelled')";
    $params = [];
    $sql .= scoped_company_sql('company_id', $params, $viewer);

    if (($viewer['role'] ?? '') === 'worker') {
        $sql .= ' AND assigned_user_id = :viewer_user_id';
        $params['viewer_user_id'] = (int) $viewer['id'];
    }

    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    return (int) $statement->fetchColumn();
}

function create_job(array $data): int
{
    $connection = jobs_connection();

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $jobNumber = next_job_number();

        try {
            $statement = $connection->prepare(
                'INSERT INTO jobs (
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
                    internal_notes,
                    created_by_user_id
                ) VALUES (
                    :company_id,
                    :job_number,
                    :task_id,
                    :customer_id,
                    :location_id,
                    :title,
                    :description,
                    :job_type,
                    :status,
                    :priority,
                    :assigned_user_id,
                    :planned_date,
                    :planned_start_time,
                    :estimated_duration_minutes,
                    :internal_notes,
                    :created_by_user_id
                )'
            );
            $statement->execute([
                'company_id' => $data['company_id'],
                'job_number' => $jobNumber,
                'task_id' => $data['task_id'],
                'customer_id' => $data['customer_id'],
                'location_id' => $data['location_id'],
                'title' => $data['title'],
                'description' => $data['description'],
                'job_type' => $data['job_type'],
                'status' => $data['status'],
                'priority' => $data['priority'],
                'assigned_user_id' => $data['assigned_user_id'],
                'planned_date' => $data['planned_date'],
                'planned_start_time' => $data['planned_start_time'],
                'estimated_duration_minutes' => $data['estimated_duration_minutes'],
                'internal_notes' => $data['internal_notes'],
                'created_by_user_id' => $data['created_by_user_id'],
            ]);

            return (int) $connection->lastInsertId();
        } catch (PDOException $exception) {
            if (job_number_conflict($exception)) {
                continue;
            }

            error_log(sprintf(
                '[jobs.create] Failed to insert job (company_id=%s, task_id=%s, customer_id=%s, location_id=%s, user_id=%s, attempt=%d): %s',
                isset($data['company_id']) ? (string) $data['company_id'] : 'null',
                isset($data['task_id']) ? (string) $data['task_id'] : 'null',
                isset($data['customer_id']) ? (string) $data['customer_id'] : 'null',
                isset($data['location_id']) ? (string) $data['location_id'] : 'null',
                isset($data['created_by_user_id']) ? (string) $data['created_by_user_id'] : 'null',
                $attempt + 1,
                $exception->getMessage()
            ));

            throw $exception;
        }
    }

    throw new RuntimeException('Unable to generate a unique job number.');
}

function update_job(int $id, array $data): void
{
    $statement = jobs_connection()->prepare(
        'UPDATE jobs
         SET company_id = :company_id,
             task_id = :task_id,
             customer_id = :customer_id,
             location_id = :location_id,
             title = :title,
             description = :description,
             job_type = :job_type,
             status = :status,
             priority = :priority,
             assigned_user_id = :assigned_user_id,
             planned_date = :planned_date,
             planned_start_time = :planned_start_time,
             estimated_duration_minutes = :estimated_duration_minutes,
             internal_notes = :internal_notes
         WHERE id = :id'
    );
    $statement->execute([
        'id' => $id,
        'company_id' => $data['company_id'],
        'task_id' => $data['task_id'],
        'customer_id' => $data['customer_id'],
        'location_id' => $data['location_id'],
        'title' => $data['title'],
        'description' => $data['description'],
        'job_type' => $data['job_type'],
        'status' => $data['status'],
        'priority' => $data['priority'],
        'assigned_user_id' => $data['assigned_user_id'],
        'planned_date' => $data['planned_date'],
        'planned_start_time' => $data['planned_start_time'],
        'estimated_duration_minutes' => $data['estimated_duration_minutes'],
        'internal_notes' => $data['internal_notes'],
    ]);
}

function cancel_job(int $id): void
{
    $params = ['id' => $id];
    $sql = "UPDATE jobs
            SET status = 'cancelled'
            WHERE id = :id";
    $sql .= scoped_company_sql('company_id', $params);
    $statement = jobs_connection()->prepare($sql);
    $statement->execute($params);
}

function reactivate_job(int $id): void
{
    $params = ['id' => $id];
    $sql = "UPDATE jobs
            SET status = CASE
                   WHEN assigned_user_id IS NOT NULL AND planned_date IS NOT NULL THEN 'planned'
                   ELSE 'draft'
                END,
                actual_start_at = NULL,
                actual_completed_at = NULL
            WHERE id = :id";
    $sql .= scoped_company_sql('company_id', $params);
    $statement = jobs_connection()->prepare($sql);
    $statement->execute($params);
}

function list_worker_jobs_grouped(int $userId, int $completedLimit = 20): array
{
    $sql = "SELECT
            j.id,
            j.company_id,
            j.job_number,
            j.task_id,
            j.customer_id,
            j.location_id,
            j.title,
            j.job_type,
            j.status,
            j.priority,
            j.assigned_user_id,
            j.planned_date,
            j.planned_start_time,
            j.estimated_duration_minutes,
            j.actual_start_at,
            j.actual_completed_at,
            j.updated_at,
            c.name AS customer_name,
            l.name AS location_name,
            l.address_line,
            l.city,
            l.postal_code,
            l.country,
            t.task_number AS linked_task_number,
            t.title AS linked_task_title
        FROM jobs j
        INNER JOIN customers c ON c.id = j.customer_id
        LEFT JOIN locations l ON l.id = j.location_id
        LEFT JOIN tasks t ON t.id = j.task_id
        WHERE j.assigned_user_id = :user_id";
    $params = ['user_id' => $userId];
    $sql .= scoped_company_sql('j.company_id', $params, current_user(), false);
    $sql .= "
        ORDER BY
            CASE
                WHEN j.status = 'completed' THEN 2
                WHEN j.planned_date > CURRENT_DATE() THEN 1
                ELSE 0
            END ASC,
            CASE WHEN j.planned_date IS NULL THEN 1 ELSE 0 END ASC,
            j.planned_date ASC,
            CASE WHEN j.planned_start_time IS NULL THEN 1 ELSE 0 END ASC,
            j.planned_start_time ASC,
            j.actual_completed_at DESC,
            j.updated_at DESC,
            j.id DESC";
    $statement = jobs_connection()->prepare($sql);
    $statement->execute($params);
    $jobs = $statement->fetchAll();

    if (!is_array($jobs)) {
        $jobs = [];
    }

    $grouped = [
        'today' => [],
        'upcoming' => [],
        'completed' => [],
    ];
    $completedCount = 0;
    $today = date('Y-m-d');

    foreach ($jobs as $job) {
        $status = (string) ($job['status'] ?? '');
        $plannedDate = (string) ($job['planned_date'] ?? '');

        if ($status === 'cancelled') {
            continue;
        }

        if ($status === 'completed') {
            if ($completedCount < $completedLimit) {
                $grouped['completed'][] = $job;
                $completedCount++;
            }

            continue;
        }

        if ($plannedDate !== '' && $plannedDate > $today) {
            $grouped['upcoming'][] = $job;
            continue;
        }

        $grouped['today'][] = $job;
    }

    usort($grouped['completed'], static function (array $left, array $right): int {
        return strcmp((string) ($right['actual_completed_at'] ?? $right['updated_at'] ?? ''), (string) ($left['actual_completed_at'] ?? $left['updated_at'] ?? ''));
    });

    return $grouped;
}

function find_worker_accessible_job_by_id(int $id, array $viewer): ?array
{
    $sql = 'SELECT
                j.id,
                j.company_id,
                j.job_number,
                j.task_id,
                j.customer_id,
                j.location_id,
                j.title,
                j.description,
                j.job_type,
                j.status,
                j.priority,
                j.assigned_user_id,
                j.planned_date,
                j.planned_start_time,
                j.estimated_duration_minutes,
                j.actual_start_at,
                j.actual_completed_at,
                j.internal_notes,
                j.created_by_user_id,
                j.created_at,
                j.updated_at,
                c.name AS customer_name,
                l.name AS location_name,
                l.address_line,
                l.city,
                l.postal_code,
                l.country,
                u.name AS assigned_worker_name,
                t.task_number AS linked_task_number,
                t.title AS linked_task_title
            FROM jobs j
            INNER JOIN customers c ON c.id = j.customer_id
            LEFT JOIN locations l ON l.id = j.location_id
            LEFT JOIN users u ON u.id = j.assigned_user_id
            LEFT JOIN tasks t ON t.id = j.task_id
            WHERE j.id = :id';
    $params = ['id' => $id];
    $sql .= scoped_company_sql('j.company_id', $params, $viewer, false);

    if (($viewer['role'] ?? '') === 'worker') {
        $sql .= ' AND j.assigned_user_id = :viewer_user_id';
        $params['viewer_user_id'] = (int) $viewer['id'];
    }

    $sql .= ' LIMIT 1';

    $statement = jobs_connection()->prepare($sql);
    $statement->execute($params);
    $job = $statement->fetch();

    return is_array($job) ? $job : null;
}

function worker_can_start_job(array $job): bool
{
    return in_array((string) ($job['status'] ?? ''), ['planned'], true);
}

function worker_can_complete_job(array $job): bool
{
    return (string) ($job['status'] ?? '') === 'in_progress';
}

function start_worker_job(int $jobId, int $viewerId, string $viewerRole): bool
{
    $sql = "UPDATE jobs
            SET status = 'in_progress',
                actual_start_at = COALESCE(actual_start_at, CURRENT_TIMESTAMP),
                actual_completed_at = NULL
            WHERE id = :id
              AND status = 'planned'";
    $params = ['id' => $jobId];
    $sql .= scoped_company_sql('company_id', $params, current_user(), false);

    if ($viewerRole === 'worker') {
        $sql .= ' AND assigned_user_id = :viewer_user_id';
        $params['viewer_user_id'] = $viewerId;
    }

    $statement = jobs_connection()->prepare($sql);
    $statement->execute($params);

    return $statement->rowCount() > 0;
}

function complete_worker_job(int $jobId, int $viewerId, string $viewerRole): bool
{
    $sql = "UPDATE jobs
            SET status = 'completed',
                actual_start_at = COALESCE(actual_start_at, CURRENT_TIMESTAMP),
                actual_completed_at = CURRENT_TIMESTAMP
            WHERE id = :id
              AND status = 'in_progress'";
    $params = ['id' => $jobId];
    $sql .= scoped_company_sql('company_id', $params, current_user(), false);

    if ($viewerRole === 'worker') {
        $sql .= ' AND assigned_user_id = :viewer_user_id';
        $params['viewer_user_id'] = $viewerId;
    }

    $statement = jobs_connection()->prepare($sql);
    $statement->execute($params);

    return $statement->rowCount() > 0;
}

function list_job_notes(int $jobId): array
{
    $params = ['job_id' => $jobId];
    $sql = 'SELECT
            n.id,
            n.company_id,
            n.job_id,
            n.user_id,
            n.note,
            n.created_at,
            u.name AS author_name
         FROM job_notes n
         LEFT JOIN users u ON u.id = n.user_id
         WHERE n.job_id = :job_id';
    $sql .= scoped_company_sql('n.company_id', $params);
    $sql .= ' ORDER BY n.created_at ASC, n.id ASC';
    $statement = jobs_connection()->prepare($sql);
    $statement->execute($params);
    $notes = $statement->fetchAll();

    return is_array($notes) ? $notes : [];
}

function create_job_note(int $jobId, ?int $userId, string $note): void
{
    $statement = jobs_connection()->prepare(
        'INSERT INTO job_notes (company_id, job_id, user_id, note)
         VALUES (:company_id, :job_id, :user_id, :note)'
    );
    $statement->execute([
        'company_id' => current_company_id(),
        'job_id' => $jobId,
        'user_id' => $userId,
        'note' => $note,
    ]);
}

function next_job_number(): string
{
    try {
        $statement = jobs_connection()->query(
            "SELECT job_number
             FROM jobs
             WHERE job_number REGEXP '^JOB-[0-9]{6}$'
             ORDER BY CAST(SUBSTRING(job_number, 5) AS UNSIGNED) DESC
             LIMIT 1"
        );
        $lastNumber = $statement->fetchColumn();
        $nextValue = 1;

        if (is_string($lastNumber) && preg_match('/^JOB-([0-9]{6})$/', $lastNumber, $matches) === 1) {
            $nextValue = ((int) $matches[1]) + 1;
        }

        return sprintf('JOB-%06d', $nextValue);
    } catch (PDOException $exception) {
        error_log(sprintf('[jobs.numbering] Failed to generate next job number: %s', $exception->getMessage()));

        throw $exception;
    }
}

function job_number_conflict(PDOException $exception): bool
{
    $errorInfo = $exception->errorInfo;
    $message = $exception->getMessage();

    return ($exception->getCode() === '23000' || ($errorInfo[0] ?? null) === '23000')
        && str_contains(strtolower($message), 'job_number');
}

function recent_jobs_for_location(int $locationId, int $limit = 5): array
{
    $params = ['location_id' => $locationId];
    $sql = 'SELECT id, company_id, job_number, task_id, title, status, planned_date, planned_start_time
            FROM jobs
            WHERE location_id = :location_id';
    $sql .= scoped_company_sql('company_id', $params);
    $sql .= ' ORDER BY
            CASE WHEN planned_date IS NULL THEN 1 ELSE 0 END ASC,
            planned_date ASC,
            CASE WHEN planned_start_time IS NULL THEN 1 ELSE 0 END ASC,
            planned_start_time ASC,
            id DESC
         LIMIT ' . max(1, $limit);
    $statement = jobs_connection()->prepare($sql);
    $statement->execute($params);
    $jobs = $statement->fetchAll();

    return is_array($jobs) ? $jobs : [];
}
