<?php

declare(strict_types=1);
?>
<div class="d-grid gap-4">
    <section class="card shadow-sm border-0">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Assigned Job</p>
                    <h1 class="h3 mb-2"><?= h($job['job_number']) ?></h1>
                    <p class="text-secondary mb-0"><?= h($job['title']) ?></p>
                </div>
                <a class="btn btn-outline-secondary" href="<?= h(app_url('/work')) ?>">Back to My Work</a>
            </div>
        </div>
    </section>

    <?php if ($successMessage !== null): ?>
        <div class="alert alert-success mb-0" role="status"><?= h($successMessage) ?></div>
    <?php endif; ?>

    <?php if ($errorMessage !== null): ?>
        <div class="alert alert-danger mb-0" role="alert"><?= h($errorMessage) ?></div>
    <?php endif; ?>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <div class="worker-action-bar">
                <span class="badge <?= h(job_status_badge_class((string) $job['status'])) ?>"><?= h(job_status_label((string) $job['status'])) ?></span>

                <div class="d-flex flex-wrap gap-2">
                    <?php if (worker_can_start_job($job)): ?>
                        <form method="post" action="<?= h(app_url('/work/jobs/' . $job['id'] . '/start')) ?>">
                            <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                            <button class="btn btn-primary" type="submit">Start Job</button>
                        </form>
                    <?php endif; ?>

                    <?php if (worker_can_complete_job($job)): ?>
                        <form method="post" action="<?= h(app_url('/work/jobs/' . $job['id'] . '/complete')) ?>" onsubmit="return confirm('Complete this job?');">
                            <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                            <button class="btn btn-success" type="submit">Complete Job</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="info-grid mt-4">
                <div>
                    <p class="info-label">Task</p>
                    <p class="mb-0">
                        <?php if ($job['task_id'] !== null): ?>
                            <?= h($job['linked_task_number']) ?> - <?= h($job['linked_task_title']) ?>
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
                    <p class="info-label">Scheduled Start</p>
                    <p class="mb-0"><?= h(format_job_scheduled_start($job)) ?></p>
                </div>
                <div>
                    <p class="info-label">Priority</p>
                    <p class="mb-0"><?= h(job_priority_label((string) $job['priority'])) ?></p>
                </div>
                <div>
                    <p class="info-label">Scheduled End</p>
                    <p class="mb-0"><?= h(format_job_scheduled_end($job)) ?></p>
                </div>
                <div>
                    <p class="info-label">Customer</p>
                    <p class="mb-0"><?= h($job['customer_name']) ?></p>
                </div>
                <div>
                    <p class="info-label">Location</p>
                    <p class="mb-0"><?= h($job['location_name'] ?: 'Assigned location') ?></p>
                </div>
                <div>
                    <p class="info-label">Full Address</p>
                    <p class="mb-0 worker-selectable-text"><?= nl2br(h(location_address($job) ?: 'Not provided')) ?></p>
                </div>
                <div>
                    <p class="info-label">Assigned Worker</p>
                    <p class="mb-0"><?= h($job['assigned_worker_name'] ?: 'Unassigned') ?></p>
                </div>
                <div>
                    <p class="info-label">Started</p>
                    <p class="mb-0"><?= h(format_datetime($job['actual_start_at'] ?? null)) ?></p>
                </div>
                <div>
                    <p class="info-label">Completed</p>
                    <p class="mb-0"><?= h(format_datetime($job['actual_completed_at'] ?? null)) ?></p>
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
                <p class="info-label">Description</p>
                <p class="mb-0"><?= nl2br(h($job['description'] ?: 'No description or instructions provided.')) ?></p>
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
                <section class="deferred-feature-panel" aria-labelledby="worker-attachments-placeholder">
                    <p class="info-label mb-2" id="worker-attachments-placeholder">Attachments</p>
                    <p class="text-secondary mb-0">Attachments will be available in a later version.</p>
                </section>

                <section class="deferred-feature-panel" aria-labelledby="worker-photos-placeholder">
                    <p class="info-label mb-2" id="worker-photos-placeholder">Photos</p>
                    <p class="text-secondary mb-0">Photo uploads will be available in a later version.</p>
                </section>

                <section class="deferred-feature-panel" aria-labelledby="worker-confirmation-placeholder">
                    <p class="info-label mb-2" id="worker-confirmation-placeholder">Customer Confirmation</p>
                    <p class="text-secondary mb-0">Customer confirmation and signature will be available in a later version.</p>
                </section>
            </div>
        </div>
    </section>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <div class="mb-3">
                <p class="text-uppercase text-secondary small fw-semibold mb-2">Job Notes</p>
                <h2 class="h5 mb-1">Field Updates</h2>
                <p class="text-secondary mb-0">Add simple text notes for work completed, findings, or delivery updates.</p>
            </div>

            <form method="post" action="<?= h(app_url('/work/jobs/' . $job['id'] . '/notes')) ?>" class="d-grid gap-3">
                <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                <div>
                    <label class="form-label" for="note">Add note</label>
                    <textarea
                        class="form-control<?= $noteError !== null ? ' is-invalid' : '' ?>"
                        id="note"
                        name="note"
                        rows="4"
                        maxlength="2000"
                        placeholder="What happened on site?"
                    ><?= h($noteValue) ?></textarea>
                    <?php if ($noteError !== null): ?>
                        <div class="invalid-feedback"><?= h($noteError) ?></div>
                    <?php endif; ?>
                </div>
                <div>
                    <button class="btn btn-outline-primary" type="submit">Save Note</button>
                </div>
            </form>

            <div class="worker-notes-list mt-4">
                <?php if ($notes === []): ?>
                    <p class="text-secondary mb-0">No notes have been added yet.</p>
                <?php else: ?>
                    <?php foreach ($notes as $note): ?>
                        <article class="worker-note">
                            <div class="worker-note__meta">
                                <strong><?= h($note['author_name'] ?: 'Unknown user') ?></strong>
                                <span><?= h(format_datetime($note['created_at'] ?? null)) ?></span>
                            </div>
                            <p class="mb-0"><?= nl2br(h($note['note'])) ?></p>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>
