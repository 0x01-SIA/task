<?php

declare(strict_types=1);
?>
<div class="d-grid gap-4">
    <section class="card shadow-sm border-0">
        <div class="card-body p-4 p-lg-5">
            <p class="text-uppercase text-secondary small fw-semibold mb-2">Operations</p>
            <h1 class="h3 mb-2">Dashboard</h1>
            <p class="text-secondary mb-0">A compact operational view of jobs needing attention, today’s schedule, active field work, and recent completions.</p>
        </div>
    </section>

    <section class="row g-3">
        <div class="col-sm-6 col-xl">
            <div class="dashboard-card h-100">
                <p class="dashboard-card-label">Unassigned Jobs</p>
                <p class="dashboard-card-value"><?= h((string) $summaryCounts['unassigned_jobs']) ?></p>
            </div>
        </div>
        <div class="col-sm-6 col-xl">
            <div class="dashboard-card h-100">
                <p class="dashboard-card-label">Scheduled Jobs</p>
                <p class="dashboard-card-value"><?= h((string) $summaryCounts['scheduled_jobs']) ?></p>
            </div>
        </div>
        <div class="col-sm-6 col-xl">
            <div class="dashboard-card h-100">
                <p class="dashboard-card-label">In Progress</p>
                <p class="dashboard-card-value"><?= h((string) $summaryCounts['in_progress_jobs']) ?></p>
            </div>
        </div>
        <div class="col-sm-6 col-xl">
            <div class="dashboard-card h-100">
                <p class="dashboard-card-label">Overdue Jobs</p>
                <p class="dashboard-card-value"><?= h((string) $summaryCounts['overdue_jobs']) ?></p>
            </div>
        </div>
        <div class="col-sm-6 col-xl">
            <div class="dashboard-card h-100">
                <p class="dashboard-card-label">Completed Today</p>
                <p class="dashboard-card-value"><?= h((string) $summaryCounts['completed_today']) ?></p>
            </div>
        </div>
    </section>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
                <div>
                    <h2 class="h5 mb-1">Tasks requiring attention</h2>
                    <p class="text-secondary mb-0">Overdue tasks, urgent open requests, due-today work, and new tasks without jobs are listed first.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-secondary" href="<?= h(app_url('/tasks')) ?>">View all tasks</a>
                    <a class="btn btn-primary" href="<?= h(app_url('/tasks/create')) ?>">New Task</a>
                </div>
            </div>

            <?php if ($attentionTasks === []): ?>
                <p class="dashboard-empty mb-0">No tasks currently require attention.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Task</th>
                                <th scope="col">Customer</th>
                                <th scope="col">Due</th>
                                <th scope="col">Jobs</th>
                                <th scope="col">Priority</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attentionTasks as $task): ?>
                                <tr>
                                    <td><a href="<?= h(app_url('/tasks/' . $task['id'])) ?>"><?= h($task['task_number']) ?></a></td>
                                    <td><?= h($task['customer_name']) ?></td>
                                    <td><?= h(format_date($task['due_date'] ?? null)) ?></td>
                                    <td><?= h((string) ($task['linked_job_count'] ?? 0)) ?></td>
                                    <td><?= h(task_priority_label((string) $task['priority'])) ?></td>
                                    <td><span class="badge <?= h(task_status_badge_class((string) $task['status'])) ?>"><?= h(task_status_label((string) $task['status'])) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
                <div>
                    <h2 class="h5 mb-1">Jobs requiring attention</h2>
                    <p class="text-secondary mb-0">Overdue work is listed first, followed by unassigned jobs scheduled soon and active jobs still underway.</p>
                </div>
                <a class="btn btn-outline-primary" href="<?= h(app_url('/jobs')) ?>">View all jobs</a>
            </div>

            <?php if ($attentionJobs === []): ?>
                <p class="dashboard-empty mb-0">No jobs currently require attention.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Job</th>
                                <th scope="col">Customer</th>
                                <th scope="col">Location</th>
                                <th scope="col">Scheduled</th>
                                <th scope="col">Assigned Worker</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attentionJobs as $job): ?>
                                <tr>
                                    <td><a href="<?= h(app_url('/jobs/' . $job['id'])) ?>"><?= h($job['job_number']) ?></a></td>
                                    <td><?= h($job['customer_name']) ?></td>
                                    <td><?= h($job['location_name'] ?? '—') ?></td>
                                    <td><?= h(format_job_scheduled_start($job)) ?></td>
                                    <td><?= h($job['assigned_worker_name'] ?? 'Unassigned') ?></td>
                                    <td><span class="badge <?= h(job_status_badge_class((string) $job['status'])) ?>"><?= h(job_status_label((string) $job['status'])) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="row g-4">
        <div class="col-12 col-xl-7">
            <section class="card shadow-sm border-0 h-100">
                <div class="card-body p-4">
                    <div class="mb-3">
                        <h2 class="h5 mb-1">Today&apos;s schedule</h2>
                        <p class="text-secondary mb-0">Scheduled jobs for <?= h(format_date(date('Y-m-d'))) ?>, ordered by start time.</p>
                    </div>

                    <?php if ($todaysSchedule === []): ?>
                        <p class="dashboard-empty mb-0">No jobs are scheduled for today.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">Time</th>
                                        <th scope="col">Job</th>
                                        <th scope="col">Customer</th>
                                        <th scope="col">Location</th>
                                        <th scope="col">Assigned Worker</th>
                                        <th scope="col">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($todaysSchedule as $job): ?>
                                        <tr>
                                            <td><?= h($job['planned_start_time'] !== null ? format_time((string) $job['planned_start_time']) : '—') ?></td>
                                            <td><a href="<?= h(app_url('/jobs/' . $job['id'])) ?>"><?= h($job['job_number']) ?></a></td>
                                            <td><?= h($job['customer_name']) ?></td>
                                            <td><?= h($job['location_name'] ?? '—') ?></td>
                                            <td><?= h($job['assigned_worker_name'] ?? 'Unassigned') ?></td>
                                            <td><span class="badge <?= h(job_status_badge_class((string) $job['status'])) ?>"><?= h(job_status_label((string) $job['status'])) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <div class="col-12 col-xl-5">
            <section class="card shadow-sm border-0 h-100">
                <div class="card-body p-4">
                    <div class="mb-3">
                        <h2 class="h5 mb-1">Active workers</h2>
                        <p class="text-secondary mb-0">Workers who currently have one or more in-progress jobs.</p>
                    </div>

                    <?php if ($activeWorkers === []): ?>
                        <p class="dashboard-empty mb-0">No workers currently have active jobs.</p>
                    <?php else: ?>
                        <div class="dashboard-worker-list">
                            <?php foreach ($activeWorkers as $worker): ?>
                                <article class="dashboard-worker-card">
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                        <div>
                                            <h3 class="h6 mb-1"><?= h($worker['name']) ?></h3>
                                            <p class="text-secondary mb-0"><?= h((string) $worker['active_job_count']) ?> active job<?= (int) $worker['active_job_count'] === 1 ? '' : 's' ?></p>
                                        </div>
                                    </div>
                                    <p class="dashboard-worker-jobs mb-0"><?= h((string) $worker['job_numbers']) ?></p>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <div class="mb-3">
                <h2 class="h5 mb-1">Recently completed jobs</h2>
                <p class="text-secondary mb-0">The latest completed work, ordered by completion time.</p>
            </div>

            <?php if ($recentlyCompletedJobs === []): ?>
                <p class="dashboard-empty mb-0">No jobs have been completed yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Job</th>
                                <th scope="col">Customer</th>
                                <th scope="col">Assigned Worker</th>
                                <th scope="col">Completed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentlyCompletedJobs as $job): ?>
                                <tr>
                                    <td><a href="<?= h(app_url('/jobs/' . $job['id'])) ?>"><?= h($job['job_number']) ?></a></td>
                                    <td><?= h($job['customer_name']) ?></td>
                                    <td><?= h($job['assigned_worker_name'] ?? 'Unassigned') ?></td>
                                    <td><?= h(format_datetime($job['actual_completed_at'] ?? null)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
