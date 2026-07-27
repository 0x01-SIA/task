<?php

declare(strict_types=1);

$viewer = current_user();
$canManageJobs = in_array((string) ($viewer['role'] ?? ''), ['admin', 'dispatcher'], true);
?>
<div class="d-grid gap-4">
    <section class="card shadow-sm border-0">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Job</p>
                    <h1 class="h3 mb-2"><?= h($job['job_number']) ?></h1>
                    <p class="text-secondary mb-0"><?= h($job['title']) ?></p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-secondary" href="<?= h(app_url('/jobs')) ?>">Back to Jobs</a>
                    <?php if ($canManageJobs): ?>
                        <a class="btn btn-primary" href="<?= h(app_url('/jobs/' . $job['id'] . '/edit')) ?>">Edit Job</a>
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
                    <p class="mb-0"><span class="badge <?= h(job_status_badge_class((string) $job['status'])) ?>"><?= h(job_status_label((string) $job['status'])) ?></span></p>
                </div>
                <div>
                    <p class="info-label">Task</p>
                    <p class="mb-0">
                        <?php if ($job['task_id'] !== null): ?>
                            <a href="<?= h(app_url('/tasks/' . $job['task_id'])) ?>"><?= h($job['linked_task_number']) ?> - <?= h($job['linked_task_title']) ?></a>
                        <?php else: ?>
                            Standalone job
                        <?php endif; ?>
                    </p>
                </div>
                <div>
                    <p class="info-label">Job Type</p>
                    <p class="mb-0"><?= h(job_type_label((string) $job['job_type'])) ?></p>
                </div>
                <div>
                    <p class="info-label">Priority</p>
                    <p class="mb-0"><?= h(job_priority_label((string) $job['priority'])) ?></p>
                </div>
                <div>
                    <p class="info-label">Assigned Worker</p>
                    <p class="mb-0"><?= h($job['assigned_worker_name'] ?: 'Unassigned') ?></p>
                </div>
                <div>
                    <p class="info-label">Customer</p>
                    <p class="mb-0">
                        <?php if ($canManageJobs): ?>
                            <a href="<?= h(app_url('/customers/' . $job['customer_id'])) ?>"><?= h($job['customer_name']) ?></a>
                        <?php else: ?>
                            <?= h($job['customer_name']) ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div>
                    <p class="info-label">Location</p>
                    <p class="mb-0">
                        <?php if ($job['location_id'] !== null): ?>
                            <?php if ($canManageJobs): ?>
                                <a href="<?= h(app_url('/locations/' . $job['location_id'])) ?>"><?= h($job['location_name'] ?: 'View location') ?></a>
                            <?php else: ?>
                                <?= h($job['location_name'] ?: 'Assigned location') ?>
                            <?php endif; ?>
                        <?php else: ?>
                            Not assigned
                        <?php endif; ?>
                    </p>
                </div>
                <div>
                    <p class="info-label">Planned Date</p>
                    <p class="mb-0"><?= h(format_date($job['planned_date'] ?? null)) ?></p>
                </div>
                <div>
                    <p class="info-label">Planned Start Time</p>
                    <p class="mb-0"><?= h($job['planned_start_time'] !== null ? format_time((string) $job['planned_start_time']) : 'Not scheduled') ?></p>
                </div>
                <div>
                    <p class="info-label">Estimated Duration</p>
                    <p class="mb-0"><?= h($job['estimated_duration_minutes'] !== null ? $job['estimated_duration_minutes'] . ' minutes' : 'Not provided') ?></p>
                </div>
                <div>
                    <p class="info-label">Created</p>
                    <p class="mb-0"><?= h(format_datetime($job['created_at'] ?? null)) ?></p>
                </div>
                <div>
                    <p class="info-label">Updated</p>
                    <p class="mb-0"><?= h(format_datetime($job['updated_at'] ?? null)) ?></p>
                </div>
            </div>

            <div class="mt-4">
                <p class="info-label">Full Service Address</p>
                <p class="mb-0"><?= nl2br(h(location_address($job) ?: 'Not provided')) ?></p>
            </div>

            <div class="mt-4">
                <p class="info-label">Description</p>
                <p class="mb-0"><?= nl2br(h($job['description'] ?: 'No description provided.')) ?></p>
            </div>

            <div class="mt-4">
                <p class="info-label">Internal Notes</p>
                <p class="mb-0"><?= nl2br(h($job['internal_notes'] ?: 'No internal notes recorded.')) ?></p>
            </div>
        </div>
    </section>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <div class="mb-3">
                <p class="text-uppercase text-secondary small fw-semibold mb-2">Later Version</p>
                <h2 class="h5 mb-1">Deferred Job Features</h2>
                <p class="text-secondary mb-0">These sections are included for structure in the MVP and will become available in a later version.</p>
            </div>

            <div class="deferred-feature-grid">
                <section class="deferred-feature-panel" aria-labelledby="job-attachments-placeholder">
                    <p class="info-label mb-2" id="job-attachments-placeholder">Attachments</p>
                    <p class="text-secondary mb-0">Attachments will be available in a later version.</p>
                </section>

                <section class="deferred-feature-panel" aria-labelledby="job-photos-placeholder">
                    <p class="info-label mb-2" id="job-photos-placeholder">Photos</p>
                    <p class="text-secondary mb-0">Photo uploads will be available in a later version.</p>
                </section>

                <section class="deferred-feature-panel" aria-labelledby="job-confirmation-placeholder">
                    <p class="info-label mb-2" id="job-confirmation-placeholder">Customer Confirmation</p>
                    <p class="text-secondary mb-0">Customer confirmation and signature will be available in a later version.</p>
                </section>
            </div>
        </div>
    </section>

    <?php if ($canManageJobs): ?>
        <section class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                    <div>
                        <p class="text-uppercase text-secondary small fw-semibold mb-2">Actions</p>
                        <h2 class="h5 mb-1">Operational Controls</h2>
                        <p class="text-secondary mb-0">
                            <?= $job['status'] === 'cancelled'
                                ? 'Reactivate this cancelled job when it is ready to return to planning.'
                                : 'Cancelling keeps the record and removes it from active planning.' ?>
                        </p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <?php if ($job['status'] === 'cancelled'): ?>
                            <form method="post" action="<?= h(app_url('/jobs/' . $job['id'] . '/reactivate')) ?>">
                                <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                                <button class="btn btn-outline-primary" type="submit">Reactivate Job</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="<?= h(app_url('/jobs/' . $job['id'] . '/cancel')) ?>" onsubmit="return confirm('Cancel this job? The record will be kept and can be reactivated later.');">
                                <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                                <button class="btn btn-outline-danger" type="submit">Cancel Job</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>
</div>
