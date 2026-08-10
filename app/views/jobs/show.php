<?php

declare(strict_types=1);

$viewer = current_user();
$canManageJobs = in_array((string) ($viewer['role'] ?? ''), ['admin', 'dispatcher'], true);
$canRecordMaterials = user_can_record_job_material($viewer, $job);
$canModifyMaterials = user_can_modify_job_material($viewer, $job);
$canGenerateReport = user_can_generate_job_report($viewer, $job) && job_can_generate_report($job);
$materialRouteBase = '/jobs/' . $job['id'] . '/materials';
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
                    <?php if ($canGenerateReport): ?>
                        <details class="job-report-action">
                            <summary class="btn btn-outline-primary">Generate report</summary>
                            <form method="post" action="<?= h(app_url('/jobs/' . $job['id'] . '/report')) ?>" class="card card-body shadow-sm mt-2 border-0">
                                <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                                <label class="form-label" for="report-language">Report language</label>
                                <select class="form-select" id="report-language" name="language" required>
                                    <?php foreach (job_report_language_options() as $languageCode => $languageLabel): ?>
                                        <option value="<?= h($languageCode) ?>"><?= h($languageLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-primary mt-3" type="submit">Download PDF</button>
                            </form>
                        </details>
                    <?php endif; ?>
                    <?php if ($canManageJobs): ?>
                        <a class="btn btn-primary" href="<?= h(app_url('/jobs/' . $job['id'] . '/edit')) ?>">Edit Job</a>
                        <form method="post" action="<?= h(app_url('/jobs/' . $job['id'] . '/delete')) ?>" onsubmit="return confirm('Delete this job? This cannot be undone.');">
                            <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                            <button class="btn btn-outline-danger" type="submit">Delete Job</button>
                        </form>
                    <?php endif; ?>
                </div>
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

            <div class="mt-4">
                <p class="info-label">Field Updates</p>
                <?php if ($notes === []): ?>
                    <p class="mb-0 text-secondary">No field updates have been added yet.</p>
                <?php else: ?>
                    <div class="worker-notes-list">
                        <?php foreach ($notes as $note): ?>
                            <article class="worker-note">
                                <div class="worker-note__meta">
                                    <strong><?= h($note['author_name'] ?: 'Unknown user') ?></strong>
                                    <span><?= h(format_datetime($note['created_at'] ?? null)) ?></span>
                                </div>
                                <p class="mb-0"><?= nl2br(h($note['note'])) ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <div class="job-assets-header">
                <div>
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Job Files</p>
                    <h2 class="h5 mb-1">Attachments</h2>
                    <p class="text-secondary mb-0">Business documents are stored securely and downloaded through the application.</p>
                </div>
                <?php if (user_can_upload_job_attachments($viewer)): ?>
                    <form method="post" action="<?= h(app_url('/jobs/' . $job['id'] . '/attachments')) ?>" enctype="multipart/form-data" class="job-upload-form">
                        <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                        <div>
                            <label class="form-label" for="attachment">Upload attachment</label>
                            <input class="form-control<?= $attachmentError !== null ? ' is-invalid' : '' ?>" type="file" id="attachment" name="attachment" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip">
                            <div class="form-text">Maximum size: <?= h(format_file_size(job_attachment_rules()['max_bytes'])) ?>.</div>
                            <?php if ($attachmentError !== null): ?>
                                <div class="invalid-feedback d-block"><?= h($attachmentError) ?></div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <button class="btn btn-primary" type="submit">Upload Attachment</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <div class="job-asset-list mt-4">
                <?php if ($attachments === []): ?>
                    <p class="text-secondary mb-0">No attachments have been uploaded yet.</p>
                <?php else: ?>
                    <?php foreach ($attachments as $attachment): ?>
                        <article class="job-asset-item">
                            <div class="job-asset-item__meta">
                                <div>
                                    <p class="job-asset-item__title"><?= h($attachment['original_filename']) ?></p>
                                    <p class="job-asset-item__details mb-0">
                                        <?= h(format_file_size($attachment['file_size'])) ?>
                                        · <?= h($attachment['uploader_name'] ?: 'Unknown user') ?>
                                        · <?= h(format_datetime($attachment['uploaded_at'] ?? null)) ?>
                                    </p>
                                </div>
                                <div class="job-asset-actions">
                                    <a class="btn btn-outline-secondary btn-sm" href="<?= h(app_url('/jobs/' . $job['id'] . '/attachments/' . $attachment['id'] . '/download')) ?>">Download</a>
                                    <?php if (user_can_delete_job_attachments($viewer)): ?>
                                        <form method="post" action="<?= h(app_url('/jobs/' . $job['id'] . '/attachments/' . $attachment['id'] . '/delete')) ?>" onsubmit="return confirm('Delete this attachment?');">
                                            <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                                            <button class="btn btn-outline-danger btn-sm" type="submit">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <div class="job-assets-header">
                <div>
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Job Media</p>
                    <h2 class="h5 mb-1">Photos</h2>
                    <p class="text-secondary mb-0">Upload field photos at any stage. Deletion remains available only while the job is open.</p>
                </div>
                <?php if (user_can_upload_job_photos($viewer, $job)): ?>
                    <form method="post" action="<?= h(app_url('/jobs/' . $job['id'] . '/photos')) ?>" enctype="multipart/form-data" class="job-upload-form">
                        <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                        <div>
                            <label class="form-label" for="photo">Select photos</label>
                            <input class="form-control<?= $photoError !== null && $photoCaptionError === null ? ' is-invalid' : '' ?>" type="file" id="photo" name="photo[]" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple>
                            <div class="form-text">You can upload up to <?= h((string) job_photo_rules()['max_files']) ?> photos at once. Maximum size: <?= h(format_file_size(job_photo_rules()['max_bytes'])) ?> per photo.</div>
                        </div>
                        <div>
                            <label class="form-label" for="caption">Caption (optional)</label>
                            <input class="form-control<?= $photoCaptionError !== null ? ' is-invalid' : '' ?>" type="text" id="caption" name="caption" maxlength="255" value="<?= h($photoCaption) ?>">
                            <?php if ($photoCaptionError !== null): ?>
                                <div class="invalid-feedback"><?= h($photoCaptionError) ?></div>
                            <?php endif; ?>
                            <?php if ($photoError !== null): ?>
                                <div class="invalid-feedback d-block"><?= h($photoError) ?></div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <button class="btn btn-primary" type="submit">Upload Photos</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <div class="job-photo-grid mt-4">
                <?php if ($photos === []): ?>
                    <p class="text-secondary mb-0">No photos have been uploaded yet.</p>
                <?php else: ?>
                    <?php foreach ($photos as $photo): ?>
                        <article class="job-photo-card">
                            <a href="<?= h(app_url('/jobs/' . $job['id'] . '/photos/' . $photo['id'] . '/view')) ?>" target="_blank" rel="noreferrer" class="job-photo-preview">
                                <img
                                    class="job-photo-preview__image"
                                    src="<?= h(app_url('/jobs/' . $job['id'] . '/photos/' . $photo['id'] . '/view')) ?>"
                                    alt="<?= h($photo['caption'] ?: $photo['original_filename']) ?>"
                                    loading="lazy"
                                >
                            </a>
                            <div class="job-photo-meta">
                                <p class="job-photo-meta__details mb-1"><?= h($photo['uploader_name'] ?: 'Unknown user') ?> · <?= h(format_datetime($photo['uploaded_at'] ?? null)) ?></p>
                                <?php if (($photo['caption'] ?? null) !== null && trim((string) $photo['caption']) !== ''): ?>
                                    <p class="job-photo-meta__caption"><?= h($photo['caption']) ?></p>
                                <?php endif; ?>
                                <div class="job-asset-actions">
                                    <a class="btn btn-outline-secondary btn-sm" href="<?= h(app_url('/jobs/' . $job['id'] . '/photos/' . $photo['id'] . '/view')) ?>" target="_blank" rel="noreferrer">Open Full Size</a>
                                    <?php if (user_can_delete_job_photos($viewer, $job)): ?>
                                        <form method="post" action="<?= h(app_url('/jobs/' . $job['id'] . '/photos/' . $photo['id'] . '/delete')) ?>" onsubmit="return confirm('Delete this photo?');">
                                            <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                                            <button class="btn btn-outline-danger btn-sm" type="submit">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php require base_path('app/views/jobs/material-section.php'); ?>

    <?php require base_path('app/views/jobs/customer-confirmation.php'); ?>

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
