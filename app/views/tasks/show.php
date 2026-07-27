<?php

declare(strict_types=1);

$taskClosed = in_array((string) ($task['status'] ?? ''), ['completed', 'cancelled'], true);
?>
<div class="d-grid gap-4">
    <section class="card shadow-sm border-0">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Task</p>
                    <h1 class="h3 mb-2"><?= h($task['task_number']) ?></h1>
                    <p class="text-secondary mb-0"><?= h($task['title']) ?></p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-secondary" href="<?= h(app_url('/tasks')) ?>">Back to Tasks</a>
                    <a class="btn btn-primary" href="<?= h(app_url('/tasks/' . $task['id'] . '/edit')) ?>">Edit Task</a>
                    <?php if (!$taskClosed): ?>
                        <a class="btn btn-outline-primary" href="<?= h(app_url('/tasks/' . $task['id'] . '/jobs/create')) ?>">Create Job for This Task</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <?php if ($successMessage !== null): ?>
        <div class="alert alert-success mb-0" role="status"><?= h($successMessage) ?></div>
    <?php endif; ?>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <div class="info-grid">
                <div>
                    <p class="info-label">Status</p>
                    <p class="mb-0"><span class="badge <?= h(task_status_badge_class((string) $task['status'])) ?>"><?= h(task_status_label((string) $task['status'])) ?></span></p>
                </div>
                <div>
                    <p class="info-label">Priority</p>
                    <p class="mb-0"><?= h(task_priority_label((string) $task['priority'])) ?></p>
                </div>
                <div>
                    <p class="info-label">Customer</p>
                    <p class="mb-0"><a href="<?= h(app_url('/customers/' . $task['customer_id'])) ?>"><?= h($task['customer_name']) ?></a></p>
                </div>
                <div>
                    <p class="info-label">Location</p>
                    <p class="mb-0">
                        <?php if ($task['location_id'] !== null): ?>
                            <a href="<?= h(app_url('/locations/' . $task['location_id'])) ?>"><?= h($task['location_name'] ?: 'View location') ?></a>
                        <?php else: ?>
                            Not assigned
                        <?php endif; ?>
                    </p>
                </div>
                <div>
                    <p class="info-label">Requested Date</p>
                    <p class="mb-0"><?= h(format_date($task['requested_date'] ?? null)) ?></p>
                </div>
                <div>
                    <p class="info-label">Due Date</p>
                    <p class="mb-0"><?= h(format_date($task['due_date'] ?? null)) ?></p>
                </div>
                <div>
                    <p class="info-label">Created By</p>
                    <p class="mb-0"><?= h($task['created_by_name'] ?: 'Unknown user') ?></p>
                </div>
                <div>
                    <p class="info-label">Created</p>
                    <p class="mb-0"><?= h(format_datetime($task['created_at'] ?? null)) ?></p>
                </div>
                <div>
                    <p class="info-label">Updated</p>
                    <p class="mb-0"><?= h(format_datetime($task['updated_at'] ?? null)) ?></p>
                </div>
            </div>

            <div class="mt-4">
                <p class="info-label">Description</p>
                <p class="mb-0"><?= nl2br(h($task['description'] ?: 'No description provided.')) ?></p>
            </div>
        </div>
    </section>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Status</p>
                    <h2 class="h5 mb-1">Change Task Status</h2>
                    <p class="text-secondary mb-0">Use the existing workflow states without removing the task from history.</p>
                </div>
                <form method="post" action="<?= h(app_url('/tasks/' . $task['id'] . '/status')) ?>" class="d-flex flex-wrap gap-2 align-items-center">
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
        <div class="card-body p-4">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
                <div>
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Linked Jobs</p>
                    <h2 class="h5 mb-1">Scheduled Work</h2>
                    <p class="text-secondary mb-0">Every job linked to this task, ordered with upcoming scheduled work first.</p>
                </div>
                <?php if (!$taskClosed): ?>
                    <a class="btn btn-outline-primary" href="<?= h(app_url('/tasks/' . $task['id'] . '/jobs/create')) ?>">
                        <?= $linkedJobs === [] ? 'Create first job' : 'Create Job for This Task' ?>
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($linkedJobs === []): ?>
                <p class="text-secondary mb-3">No jobs have been created for this task yet.</p>
                <?php if (!$taskClosed): ?>
                    <a class="btn btn-primary" href="<?= h(app_url('/tasks/' . $task['id'] . '/jobs/create')) ?>">Create first job</a>
                <?php endif; ?>
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
