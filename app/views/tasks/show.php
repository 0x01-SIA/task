<?php

declare(strict_types=1);

$taskClosed = in_array((string) ($task['status'] ?? ''), ['completed', 'cancelled'], true);
$createJobLabel = $linkedJobs === [] ? 'Create first job' : 'Create Job';
?>
<div class="d-grid task-detail">
    <section class="card shadow-sm border-0">
        <div class="card-body">
            <div class="task-header">
                <div class="task-header__content">
                    <p class="task-header__eyebrow">Task</p>
                    <h1 class="task-header__number"><?= h($task['task_number']) ?></h1>
                    <p class="task-header__title"><?= h($task['title']) ?></p>
                </div>
                <div class="task-header__actions">
                    <a class="btn btn-outline-secondary" href="<?= h(app_url('/tasks')) ?>">Back to Tasks</a>
                    <a class="btn btn-outline-primary" href="<?= h(app_url('/tasks/' . $task['id'] . '/edit')) ?>">Edit Task</a>
                    <form method="post" action="<?= h(app_url('/tasks/' . $task['id'] . '/delete')) ?>" onsubmit="return confirm('Delete this task? This cannot be undone.');">
                        <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                        <button class="btn btn-outline-danger" type="submit">Delete Task</button>
                    </form>
                    <?php if (!$taskClosed): ?>
                        <a class="btn btn-primary" href="<?= h(app_url('/tasks/' . $task['id'] . '/jobs/create')) ?>"><?= h($createJobLabel) ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <?php if ($successMessage !== null): ?>
        <div class="alert alert-success mb-0" role="status"><?= h($successMessage) ?></div>
    <?php endif; ?>

    <?php if (($errorMessage ?? null) !== null): ?>
        <div class="alert alert-danger mb-0" role="alert"><?= h($errorMessage) ?></div>
    <?php endif; ?>

    <section class="card shadow-sm border-0">
        <div class="card-body">
            <div class="info-grid task-info-grid">
                <div class="task-info-grid__item">
                    <p class="info-label">Status</p>
                    <p class="task-value"><span class="badge <?= h(task_status_badge_class((string) $task['status'])) ?>"><?= h(task_status_label((string) $task['status'])) ?></span></p>
                </div>
                <div class="task-info-grid__item">
                    <p class="info-label">Priority</p>
                    <p class="task-value"><span class="badge <?= h(task_priority_badge_class((string) $task['priority'])) ?>"><?= h(task_priority_label((string) $task['priority'])) ?></span></p>
                </div>
                <div class="task-info-grid__item">
                    <p class="info-label">Customer</p>
                    <p class="task-value"><a href="<?= h(app_url('/customers/' . $task['customer_id'])) ?>"><?= h($task['customer_name']) ?></a></p>
                </div>
                <div class="task-info-grid__item">
                    <p class="info-label">Location</p>
                    <p class="task-value">
                        <?php if ($task['location_id'] !== null): ?>
                            <a href="<?= h(app_url('/locations/' . $task['location_id'])) ?>"><?= h($task['location_name'] ?: 'View location') ?></a>
                        <?php else: ?>
                            Not set
                        <?php endif; ?>
                    </p>
                </div>
                <div class="task-info-grid__item">
                    <p class="info-label">Requested Date</p>
                    <p class="task-value"><?= h(format_display_date($task['requested_date'] ?? null)) ?></p>
                </div>
                <div class="task-info-grid__item">
                    <p class="info-label">Due Date</p>
                    <p class="task-value"><?= h(format_display_date($task['due_date'] ?? null)) ?></p>
                </div>
                <div class="task-info-grid__item">
                    <p class="info-label">Created By</p>
                    <p class="task-value"><?= h($task['created_by_name'] ?: 'Unknown user') ?></p>
                </div>
                <div class="task-info-grid__item">
                    <p class="info-label">Created</p>
                    <p class="task-value"><?= h(format_display_datetime($task['created_at'] ?? null)) ?></p>
                </div>
                <div class="task-info-grid__item">
                    <p class="info-label">Updated</p>
                    <p class="task-value"><?= h(format_display_datetime($task['updated_at'] ?? null)) ?></p>
                </div>
                <div class="task-info-grid__item task-info-grid__item--full">
                <p class="info-label">Description</p>
                    <p class="task-value"><?= nl2br(h($task['description'] ?: 'No description provided.')) ?></p>
                </div>
            </div>
        </div>
    </section>

    <section class="card shadow-sm border-0">
        <div class="card-body">
            <div class="task-status-panel">
                <div class="task-status-panel__copy">
                    <h2 class="h5 task-status-panel__title">Change Task Status</h2>
                    <p class="task-status-panel__text">Use the existing workflow states without removing the task from history.</p>
                </div>
                <form method="post" action="<?= h(app_url('/tasks/' . $task['id'] . '/status')) ?>" class="task-status-form">
                    <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                    <select class="form-select" name="status" aria-label="Task status">
                        <?php foreach (task_status_options() as $value => $label): ?>
                            <option value="<?= h($value) ?>" <?= (string) $task['status'] === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-outline-primary" type="submit">Update Status</button>
                </form>
            </div>
        </div>
    </section>

    <section class="card shadow-sm border-0">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 task-linked-jobs__header">
                <div>
                    <h2 class="h5 task-linked-jobs__title">Linked Jobs</h2>
                    <p class="text-secondary task-linked-jobs__text">Jobs created for this task, ordered by operational relevance.</p>
                </div>
                <?php if (!$taskClosed): ?>
                    <a class="btn btn-outline-primary" href="<?= h(app_url('/tasks/' . $task['id'] . '/jobs/create')) ?>">
                        <?= h($createJobLabel) ?>
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($linkedJobs === []): ?>
                <p class="dashboard-empty mb-0">No jobs have been created for this task yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                        <tr>
                            <th scope="col">Job Number</th>
                            <th scope="col">Job Type</th>
                            <th scope="col">Assigned Worker</th>
                            <th scope="col">Scheduled</th>
                            <th scope="col">Status</th>
                            <th scope="col">Completion</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($linkedJobs as $job): ?>
                            <tr>
                                <td class="fw-semibold"><a href="<?= h(app_url('/jobs/' . $job['id'])) ?>"><?= h($job['job_number']) ?></a></td>
                                <td><?= h(job_type_label((string) $job['job_type'])) ?></td>
                                <td><?= h($job['assigned_worker_name'] ?: 'Unassigned') ?></td>
                                <td><?= h(format_job_scheduled_start($job)) ?></td>
                                <td><span class="badge <?= h(job_status_badge_class((string) $job['status'])) ?>"><?= h(job_status_label((string) $job['status'])) ?></span></td>
                                <td><?= h((string) $job['status'] === 'completed' ? 'Completed' : ((string) $job['status'] === 'cancelled' ? 'Cancelled' : 'Open')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
