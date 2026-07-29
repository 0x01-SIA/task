<?php

declare(strict_types=1);

function tasks_connection(): PDO
{
    $connection = database_connection();

    if ($connection === null) {
        throw new RuntimeException('Database connection is unavailable.');
    }

    return $connection;
}

function list_tasks(array $filters = []): array
{
    $sql = 'SELECT
                t.id,
                t.company_id,
                t.task_number,
                t.customer_id,
                t.location_id,
                t.title,
                t.description,
                t.status,
                t.priority,
                t.requested_date,
                t.due_date,
                t.created_by_user_id,
                t.created_at,
                t.updated_at,
                c.name AS customer_name,
                l.name AS location_name,
                l.address_line,
                u.name AS created_by_name,
                COUNT(j.id) AS linked_job_count
            FROM tasks t
            INNER JOIN customers c ON c.id = t.customer_id
            LEFT JOIN locations l ON l.id = t.location_id
            LEFT JOIN users u ON u.id = t.created_by_user_id
            LEFT JOIN jobs j ON j.task_id = t.id
            WHERE 1 = 1';
    $params = [];
    $sql .= scoped_company_sql('t.company_id', $params);

    $search = trim((string) ($filters['search'] ?? ''));

    if ($search !== '') {
        $sql .= ' AND (
            t.task_number LIKE :search_task_number
            OR t.title LIKE :search_title
            OR c.name LIKE :search_customer
            OR COALESCE(l.name, \'\') LIKE :search_location_name
            OR COALESCE(l.address_line, \'\') LIKE :search_address
        )';
        $searchTerm = '%' . $search . '%';
        $params['search_task_number'] = $searchTerm;
        $params['search_title'] = $searchTerm;
        $params['search_customer'] = $searchTerm;
        $params['search_location_name'] = $searchTerm;
        $params['search_address'] = $searchTerm;
    }

    if (($filters['status'] ?? '') !== '') {
        $sql .= ' AND t.status = :status';
        $params['status'] = $filters['status'];
    }

    if (($filters['priority'] ?? '') !== '') {
        $sql .= ' AND t.priority = :priority';
        $params['priority'] = $filters['priority'];
    }

    if (($filters['customer_id'] ?? null) !== null) {
        $sql .= ' AND t.customer_id = :customer_id';
        $params['customer_id'] = (int) $filters['customer_id'];
    }

    $dueState = (string) ($filters['due_state'] ?? '');

    if ($dueState === 'overdue') {
        $sql .= " AND t.due_date IS NOT NULL
                  AND t.due_date < CURRENT_DATE()
                  AND t.status NOT IN ('completed', 'cancelled')";
    } elseif ($dueState === 'due_today') {
        $sql .= " AND t.due_date = CURRENT_DATE()
                  AND t.status NOT IN ('completed', 'cancelled')";
    } elseif ($dueState === 'upcoming') {
        $sql .= " AND t.due_date IS NOT NULL
                  AND t.due_date > CURRENT_DATE()
                  AND t.status NOT IN ('completed', 'cancelled')";
    } elseif ($dueState === 'no_due_date') {
        $sql .= ' AND t.due_date IS NULL';
    }

    $sql .= " GROUP BY
                t.id,
                t.task_number,
                t.customer_id,
                t.location_id,
                t.title,
                t.description,
                t.status,
                t.priority,
                t.requested_date,
                t.due_date,
                t.created_by_user_id,
                t.created_at,
                t.updated_at,
                c.name,
                l.name,
                l.address_line,
                u.name
              ORDER BY
                CASE
                    WHEN t.status IN ('completed', 'cancelled') THEN 4
                    WHEN t.due_date IS NOT NULL AND t.due_date < CURRENT_DATE() THEN 0
                    WHEN t.due_date = CURRENT_DATE() THEN 1
                    WHEN t.due_date IS NOT NULL THEN 2
                    ELSE 3
                END ASC,
                CASE
                    WHEN t.status IN ('completed', 'cancelled') THEN NULL
                    WHEN t.due_date IS NOT NULL AND t.due_date >= CURRENT_DATE() THEN t.due_date
                    ELSE NULL
                END ASC,
                CASE
                    WHEN t.status IN ('completed', 'cancelled') THEN NULL
                    WHEN t.due_date IS NOT NULL AND t.due_date < CURRENT_DATE() THEN t.due_date
                    ELSE NULL
                END ASC,
                t.updated_at DESC,
                t.id DESC";

    $statement = tasks_connection()->prepare($sql);
    $statement->execute($params);
    $tasks = $statement->fetchAll();

    return is_array($tasks) ? $tasks : [];
}

function find_task_by_id(int $id): ?array
{
    $params = ['id' => $id];
    $sql = 'SELECT
            t.id,
            t.company_id,
            t.task_number,
            t.customer_id,
            t.location_id,
            t.title,
            t.description,
            t.status,
            t.priority,
            t.requested_date,
            t.due_date,
            t.created_by_user_id,
            t.created_at,
            t.updated_at,
            c.name AS customer_name,
            l.name AS location_name,
            l.address_line,
            l.city,
            l.postal_code,
            l.country,
            u.name AS created_by_name,
            COUNT(j.id) AS linked_job_count
         FROM tasks t
         INNER JOIN customers c ON c.id = t.customer_id
         LEFT JOIN locations l ON l.id = t.location_id
         LEFT JOIN users u ON u.id = t.created_by_user_id
         LEFT JOIN jobs j ON j.task_id = t.id
         WHERE t.id = :id';
    $sql .= scoped_company_sql('t.company_id', $params);
    $sql .= '
         GROUP BY
            t.id,
            t.task_number,
            t.customer_id,
            t.location_id,
            t.title,
            t.description,
            t.status,
            t.priority,
            t.requested_date,
            t.due_date,
            t.created_by_user_id,
            t.created_at,
            t.updated_at,
            c.name,
            l.name,
            l.address_line,
            l.city,
            l.postal_code,
            l.country,
            u.name
         LIMIT 1';
    $statement = tasks_connection()->prepare($sql);
    $statement->execute($params);
    $task = $statement->fetch();

    return is_array($task) ? $task : null;
}

function find_task_brief_by_id(int $id): ?array
{
    $params = ['id' => $id];
    $sql = 'SELECT id, company_id, task_number, customer_id, location_id, title, status, priority, due_date
            FROM tasks
            WHERE id = :id';
    $sql .= scoped_company_sql('company_id', $params);
    $sql .= ' LIMIT 1';
    $statement = tasks_connection()->prepare($sql);
    $statement->execute($params);
    $task = $statement->fetch();

    return is_array($task) ? $task : null;
}

function create_task(array $data): int
{
    $connection = tasks_connection();

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $taskNumber = next_task_number();

        try {
            $statement = $connection->prepare(
                'INSERT INTO tasks (
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
                ) VALUES (
                    :company_id,
                    :task_number,
                    :customer_id,
                    :location_id,
                    :title,
                    :description,
                    :status,
                    :priority,
                    :requested_date,
                    :due_date,
                    :created_by_user_id
                )'
            );
            $statement->execute([
                'company_id' => $data['company_id'],
                'task_number' => $taskNumber,
                'customer_id' => $data['customer_id'],
                'location_id' => $data['location_id'],
                'title' => $data['title'],
                'description' => $data['description'],
                'status' => $data['status'],
                'priority' => $data['priority'],
                'requested_date' => $data['requested_date'],
                'due_date' => $data['due_date'],
                'created_by_user_id' => $data['created_by_user_id'],
            ]);

            return (int) $connection->lastInsertId();
        } catch (PDOException $exception) {
            if (task_number_conflict($exception)) {
                continue;
            }

            throw $exception;
        }
    }

    throw new RuntimeException('Unable to generate a unique task number.');
}

function update_task(int $id, array $data): void
{
    $statement = tasks_connection()->prepare(
        'UPDATE tasks
         SET customer_id = :customer_id,
             company_id = :company_id,
             location_id = :location_id,
             title = :title,
             description = :description,
             status = :status,
             priority = :priority,
             requested_date = :requested_date,
             due_date = :due_date
         WHERE id = :id'
    );
    $statement->execute([
        'id' => $id,
        'company_id' => $data['company_id'],
        'customer_id' => $data['customer_id'],
        'location_id' => $data['location_id'],
        'title' => $data['title'],
        'description' => $data['description'],
        'status' => $data['status'],
        'priority' => $data['priority'],
        'requested_date' => $data['requested_date'],
        'due_date' => $data['due_date'],
    ]);
}

function update_task_status(int $id, string $status): void
{
    $statement = tasks_connection()->prepare(
        'UPDATE tasks
         SET status = :status
         WHERE id = :id'
    );
    $statement->execute([
        'id' => $id,
        'status' => $status,
    ]);
}

function list_jobs_for_task(int $taskId): array
{
    $params = ['task_id' => $taskId];
    $sql = (
        "SELECT
            j.id,
            j.job_number,
            j.title,
            j.job_type,
            j.status,
            j.priority,
            j.assigned_user_id,
            j.planned_date,
            j.planned_start_time,
            j.actual_completed_at,
            u.name AS assigned_worker_name
         FROM jobs j
         LEFT JOIN users u ON u.id = j.assigned_user_id
         WHERE j.task_id = :task_id"
    );
    $sql .= scoped_company_sql('j.company_id', $params);
    $sql .= (
        "
         ORDER BY
            CASE
                WHEN j.status IN ('completed', 'cancelled') THEN 2
                WHEN j.planned_date IS NULL THEN 1
                ELSE 0
            END ASC,
            CASE
                WHEN j.status IN ('completed', 'cancelled') OR j.planned_date IS NULL THEN NULL
                ELSE j.planned_date
            END ASC,
            CASE
                WHEN j.status IN ('completed', 'cancelled') THEN NULL
                WHEN j.planned_date IS NULL THEN j.updated_at
                ELSE NULL
            END DESC,
            CASE WHEN j.planned_start_time IS NULL THEN 1 ELSE 0 END ASC,
            j.planned_start_time ASC,
            j.actual_completed_at DESC,
            j.updated_at DESC,
            j.id DESC"
    );
    $statement = tasks_connection()->prepare($sql);
    $statement->execute($params);
    $jobs = $statement->fetchAll();

    return is_array($jobs) ? $jobs : [];
}

function next_task_number(): string
{
    $statement = tasks_connection()->query(
        "SELECT task_number
         FROM tasks
         WHERE task_number REGEXP '^TASK-[0-9]{6}$'
         ORDER BY CAST(SUBSTRING(task_number, 6) AS UNSIGNED) DESC
         LIMIT 1"
    );
    $lastNumber = $statement->fetchColumn();
    $nextValue = 1;

    if (is_string($lastNumber) && preg_match('/^TASK-([0-9]{6})$/', $lastNumber, $matches) === 1) {
        $nextValue = ((int) $matches[1]) + 1;
    }

    return sprintf('TASK-%06d', $nextValue);
}

function task_number_conflict(PDOException $exception): bool
{
    $errorInfo = $exception->errorInfo;
    $message = $exception->getMessage();

    return ($exception->getCode() === '23000' || ($errorInfo[0] ?? null) === '23000')
        && str_contains(strtolower($message), 'task_number');
}

function dashboard_attention_tasks(int $limit = 6): array
{
    $limit = max(1, $limit);

    return dashboard_fetch_all(
        "SELECT
            t.id,
            t.task_number,
            t.title,
            t.status,
            t.priority,
            t.due_date,
            t.updated_at,
            c.name AS customer_name,
            COUNT(j.id) AS linked_job_count
         FROM tasks t
         INNER JOIN customers c ON c.id = t.customer_id
         LEFT JOIN jobs j ON j.task_id = t.id
         WHERE t.status NOT IN ('completed', 'cancelled')
           AND (
                (t.due_date IS NOT NULL AND t.due_date < CURRENT_DATE())
                OR (t.priority = 'urgent')
                OR (t.due_date = CURRENT_DATE())
                OR (t.status = 'new' AND j.id IS NULL)
           )
         GROUP BY
            t.id,
            t.task_number,
            t.title,
            t.status,
            t.priority,
            t.due_date,
            t.updated_at,
            c.name
         ORDER BY
            CASE
                WHEN t.due_date IS NOT NULL AND t.due_date < CURRENT_DATE() THEN 0
                WHEN t.priority = 'urgent' THEN 1
                WHEN t.due_date = CURRENT_DATE() THEN 2
                WHEN t.status = 'new' AND COUNT(j.id) = 0 THEN 3
                ELSE 4
            END ASC,
            CASE WHEN t.due_date IS NULL THEN 1 ELSE 0 END ASC,
            t.due_date ASC,
            t.updated_at DESC
         LIMIT {$limit}"
    );
}
