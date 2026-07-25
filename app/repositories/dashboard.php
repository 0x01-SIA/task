<?php

declare(strict_types=1);

function dashboard_connection(): PDO
{
    $connection = database_connection();

    if ($connection === null) {
        throw new RuntimeException('Database connection is unavailable.');
    }

    return $connection;
}

function dashboard_fetch_all(string $sql, array $params = []): array
{
    $statement = dashboard_connection()->prepare($sql);
    $statement->execute($params);
    $rows = $statement->fetchAll();

    return is_array($rows) ? $rows : [];
}

function fetch_count(string $sql, array $params = []): int
{
    $statement = dashboard_connection()->prepare($sql);
    $statement->execute($params);

    return (int) $statement->fetchColumn();
}

function dashboard_summary_counts(): array
{
    return [
        'unassigned_jobs' => fetch_count(
            "SELECT COUNT(*)
             FROM jobs
             WHERE status IN ('draft', 'planned')
               AND assigned_user_id IS NULL"
        ),
        'scheduled_jobs' => fetch_count(
            "SELECT COUNT(*)
             FROM jobs
             WHERE status IN ('draft', 'planned')
               AND planned_date IS NOT NULL
               AND planned_date >= CURRENT_DATE()"
        ),
        'in_progress_jobs' => fetch_count(
            "SELECT COUNT(*)
             FROM jobs
             WHERE status = 'in_progress'"
        ),
        'overdue_jobs' => fetch_count(
            "SELECT COUNT(*)
             FROM jobs
             WHERE status NOT IN ('completed', 'cancelled')
               AND planned_date IS NOT NULL
               AND (
                    planned_date < CURRENT_DATE()
                    OR (
                        planned_date = CURRENT_DATE()
                        AND planned_start_time IS NOT NULL
                        AND planned_start_time < CURRENT_TIME()
                    )
               )"
        ),
        'completed_today' => fetch_count(
            "SELECT COUNT(*)
             FROM jobs
             WHERE status = 'completed'
               AND actual_completed_at IS NOT NULL
               AND DATE(actual_completed_at) = CURRENT_DATE()"
        ),
    ];
}

function dashboard_attention_jobs(int $limit = 8): array
{
    $limit = max(1, $limit);

    return dashboard_fetch_all(
        "SELECT
            j.id,
            j.job_number,
            j.status,
            j.planned_date,
            j.planned_start_time,
            c.name AS customer_name,
            l.name AS location_name,
            u.name AS assigned_worker_name
         FROM jobs j
         INNER JOIN customers c ON c.id = j.customer_id
         LEFT JOIN locations l ON l.id = j.location_id
         LEFT JOIN users u ON u.id = j.assigned_user_id
         WHERE j.status NOT IN ('completed', 'cancelled')
           AND (
                (
                    j.planned_date IS NOT NULL
                    AND (
                        j.planned_date < CURRENT_DATE()
                        OR (
                            j.planned_date = CURRENT_DATE()
                            AND j.planned_start_time IS NOT NULL
                            AND j.planned_start_time < CURRENT_TIME()
                        )
                    )
                )
                OR (
                    j.assigned_user_id IS NULL
                    AND j.status IN ('draft', 'planned')
                    AND j.planned_date IS NOT NULL
                    AND j.planned_date <= DATE_ADD(CURRENT_DATE(), INTERVAL 2 DAY)
                )
                OR j.status = 'in_progress'
           )
         ORDER BY
            CASE
                WHEN j.planned_date IS NOT NULL
                    AND (
                        j.planned_date < CURRENT_DATE()
                        OR (
                            j.planned_date = CURRENT_DATE()
                            AND j.planned_start_time IS NOT NULL
                            AND j.planned_start_time < CURRENT_TIME()
                        )
                    ) THEN 0
                WHEN j.assigned_user_id IS NULL AND j.status IN ('draft', 'planned') THEN 1
                WHEN j.status = 'in_progress' THEN 2
                ELSE 3
            END ASC,
            CASE WHEN j.planned_date IS NULL THEN 1 ELSE 0 END ASC,
            j.planned_date ASC,
            CASE WHEN j.planned_start_time IS NULL THEN 1 ELSE 0 END ASC,
            j.planned_start_time ASC,
            j.id DESC
         LIMIT {$limit}"
    );
}

function dashboard_todays_schedule(): array
{
    return dashboard_fetch_all(
        "SELECT
            j.id,
            j.job_number,
            j.status,
            j.planned_date,
            j.planned_start_time,
            c.name AS customer_name,
            l.name AS location_name,
            u.name AS assigned_worker_name
         FROM jobs j
         INNER JOIN customers c ON c.id = j.customer_id
         LEFT JOIN locations l ON l.id = j.location_id
         LEFT JOIN users u ON u.id = j.assigned_user_id
         WHERE j.status NOT IN ('cancelled', 'completed')
           AND j.planned_date = CURRENT_DATE()
         ORDER BY
            CASE WHEN j.planned_start_time IS NULL THEN 1 ELSE 0 END ASC,
            j.planned_start_time ASC,
            j.id ASC"
    );
}

function dashboard_active_workers(): array
{
    return dashboard_fetch_all(
        "SELECT
            u.id,
            u.name,
            COUNT(j.id) AS active_job_count,
            GROUP_CONCAT(j.job_number ORDER BY j.planned_date ASC, j.planned_start_time ASC, j.id ASC SEPARATOR ', ') AS job_numbers
         FROM users u
         INNER JOIN jobs j ON j.assigned_user_id = u.id
         WHERE u.role = 'worker'
           AND u.is_active = 1
           AND j.status = 'in_progress'
         GROUP BY u.id, u.name
         ORDER BY u.name ASC"
    );
}

function dashboard_recently_completed_jobs(int $limit = 8): array
{
    $limit = max(1, $limit);

    return dashboard_fetch_all(
        "SELECT
            j.id,
            j.job_number,
            j.actual_completed_at,
            c.name AS customer_name,
            u.name AS assigned_worker_name
         FROM jobs j
         INNER JOIN customers c ON c.id = j.customer_id
         LEFT JOIN users u ON u.id = j.assigned_user_id
         WHERE j.status = 'completed'
         ORDER BY
            CASE WHEN j.actual_completed_at IS NULL THEN 1 ELSE 0 END ASC,
            j.actual_completed_at DESC,
            j.id DESC
         LIMIT {$limit}"
    );
}

function worker_dashboard_counts(int $userId): array
{
    return [
        'assigned_jobs' => fetch_count(
            'SELECT COUNT(*) FROM jobs WHERE assigned_user_id = :user_id',
            ['user_id' => $userId]
        ),
        'planned_jobs' => fetch_count(
            "SELECT COUNT(*) FROM jobs WHERE assigned_user_id = :user_id AND status = 'planned'",
            ['user_id' => $userId]
        ),
        'scheduled_today' => fetch_count(
            'SELECT COUNT(*) FROM jobs
             WHERE assigned_user_id = :user_id
               AND planned_date = CURRENT_DATE()',
            ['user_id' => $userId]
        ),
    ];
}
