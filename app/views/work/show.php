<?php

declare(strict_types=1);

$canRecordMaterials = user_can_record_job_material($viewer, $job);
$canModifyMaterials = user_can_modify_job_material($viewer, $job);
$materialRouteBase = '/work/jobs/' . $job['id'] . '/materials';
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
            <div class="job-assets-header">
                <div>
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Job Files</p>
                    <h2 class="h5 mb-1">Attachments</h2>
                    <p class="text-secondary mb-0">Attachments are available here in read-only mode for assigned workers.</p>
                </div>
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
                    <p class="text-secondary mb-0">Photos remain visible after completion, but can only be deleted while the job is still open.</p>
                </div>
                <?php if (user_can_upload_job_photos($viewer, $job)): ?>
                    <form method="post" action="<?= h(app_url('/work/jobs/' . $job['id'] . '/photos')) ?>" enctype="multipart/form-data" class="job-upload-form">
                        <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                        <div>
                            <label class="form-label" for="photo">Upload photo</label>
                            <input class="form-control<?= $photoError !== null && $photoCaptionError === null ? ' is-invalid' : '' ?>" type="file" id="photo" name="photo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                            <div class="form-text">Maximum size: <?= h(format_file_size(job_photo_rules()['max_bytes'])) ?>.</div>
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
                            <button class="btn btn-primary" type="submit">Upload Photo</button>
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
                                        <form method="post" action="<?= h(app_url('/work/jobs/' . $job['id'] . '/photos/' . $photo['id'] . '/delete')) ?>" onsubmit="return confirm('Delete this photo?');">
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
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Materials</p>
                    <h2 class="h5 mb-1">Job Material Movements</h2>
                    <p class="text-secondary mb-0">Record materials used on site or returned back into stock. Worker corrections stop once the job is completed or cancelled.</p>
                </div>
            </div>

            <?php if ($canRecordMaterials): ?>
                <?php if ($activeMaterials === []): ?>
                    <div class="alert alert-secondary mt-4 mb-0" role="status">No active materials are available.</div>
                <?php else: ?>
                    <form method="post" action="<?= h(app_url($materialRouteBase)) ?>" class="row g-3 align-items-end mt-1">
                        <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                        <div class="col-12 col-lg-8">
                            <label class="form-label" for="material_id">Material</label>
                            <select class="form-select<?= isset($materialUsageErrors['material_id']) ? ' is-invalid' : '' ?>" id="material_id" name="material_id" required>
                                <option value="">Select a material</option>
                                <?php foreach ($activeMaterials as $material): ?>
                                    <option value="<?= h($material['id']) ?>" <?= (int) ($materialUsageValues['material_id'] ?? 0) === (int) $material['id'] ? 'selected' : '' ?>>
                                        <?= h(material_option_label($material)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($materialUsageErrors['material_id'])): ?>
                                <div class="invalid-feedback"><?= h($materialUsageErrors['material_id']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-2">
                            <label class="form-label" for="material_entry_type">Type</label>
                            <select class="form-select<?= isset($materialUsageErrors['entry_type']) ? ' is-invalid' : '' ?>" id="material_entry_type" name="entry_type" required>
                                <option value="used" <?= ($materialUsageValues['entry_type'] ?? 'used') === 'used' ? 'selected' : '' ?>>Used</option>
                                <option value="returned" <?= ($materialUsageValues['entry_type'] ?? '') === 'returned' ? 'selected' : '' ?>>Returned</option>
                            </select>
                            <?php if (isset($materialUsageErrors['entry_type'])): ?>
                                <div class="invalid-feedback"><?= h($materialUsageErrors['entry_type']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-2">
                            <label class="form-label" for="material_quantity">Quantity</label>
                            <input class="form-control<?= isset($materialUsageErrors['quantity']) ? ' is-invalid' : '' ?>" id="material_quantity" name="quantity" type="text" value="<?= h($materialUsageValues['quantity'] ?? '') ?>" inputmode="decimal" placeholder="0.00" required>
                            <?php if (isset($materialUsageErrors['quantity'])): ?>
                                <div class="invalid-feedback"><?= h($materialUsageErrors['quantity']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-2">
                            <button class="btn btn-primary w-100" type="submit">Add Material</button>
                        </div>
                    </form>
                <?php endif; ?>
            <?php endif; ?>

            <div class="table-responsive mt-4">
                <?php if ($jobMaterials === []): ?>
                    <p class="text-secondary mb-0">No materials have been recorded for this job.</p>
                <?php else: ?>
                    <table class="table align-middle mb-0">
                        <thead>
                        <tr>
                            <th scope="col">Material</th>
                            <th scope="col">Type</th>
                            <th scope="col">Quantity</th>
                            <th scope="col">Recorded By</th>
                            <th scope="col">Recorded</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($jobMaterials as $jobMaterial): ?>
                            <?php $editError = $materialEditErrors[(int) $jobMaterial['id']]['quantity'] ?? null; ?>
                            <?php $editTypeError = $materialEditErrors[(int) $jobMaterial['id']]['entry_type'] ?? null; ?>
                            <?php $editValue = $materialEditValues[(int) $jobMaterial['id']]['quantity'] ?? format_decimal_quantity($jobMaterial['quantity']); ?>
                            <?php $editEntryType = $materialEditValues[(int) $jobMaterial['id']]['entry_type'] ?? (string) ($jobMaterial['entry_type'] ?? 'used'); ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= h($jobMaterial['material_name']) ?></div>
                                    <div class="small text-secondary">
                                        <?= h(($jobMaterial['material_sku'] ?: 'No SKU/code') . ' - ' . $jobMaterial['material_unit']) ?>
                                        <?php if ((int) ($jobMaterial['material_is_active'] ?? 0) !== 1): ?>
                                            · Inactive
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?= h(job_material_entry_type_label((string) ($jobMaterial['entry_type'] ?? 'used'))) ?></td>
                                <td><?= h(format_decimal_quantity($jobMaterial['quantity']) . ' ' . $jobMaterial['material_unit']) ?></td>
                                <td><?= h($jobMaterial['recorded_by_name'] ?: 'Unknown user') ?></td>
                                <td><?= h(format_datetime($jobMaterial['updated_at'] ?? $jobMaterial['created_at'] ?? null)) ?></td>
                                <td class="text-end">
                                    <?php if ($canModifyMaterials): ?>
                                        <div class="job-material-actions">
                                            <form method="post" action="<?= h(app_url($materialRouteBase . '/' . $jobMaterial['id'] . '/edit')) ?>" class="job-material-inline-form">
                                                <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                                                <select class="form-select form-select-sm<?= $editTypeError !== null ? ' is-invalid' : '' ?>" name="entry_type" aria-label="Type for <?= h($jobMaterial['material_name']) ?>">
                                                    <option value="used" <?= $editEntryType === 'used' ? 'selected' : '' ?>>Used</option>
                                                    <option value="returned" <?= $editEntryType === 'returned' ? 'selected' : '' ?>>Returned</option>
                                                </select>
                                                <input class="form-control form-control-sm<?= $editError !== null ? ' is-invalid' : '' ?>" name="quantity" type="text" value="<?= h($editValue) ?>" inputmode="decimal" aria-label="Quantity for <?= h($jobMaterial['material_name']) ?>">
                                                <button class="btn btn-outline-primary btn-sm" type="submit">Update</button>
                                                <?php if ($editTypeError !== null): ?>
                                                    <div class="invalid-feedback d-block text-start"><?= h($editTypeError) ?></div>
                                                <?php endif; ?>
                                                <?php if ($editError !== null): ?>
                                                    <div class="invalid-feedback d-block text-start"><?= h($editError) ?></div>
                                                <?php endif; ?>
                                            </form>
                                            <form method="post" action="<?= h(app_url($materialRouteBase . '/' . $jobMaterial['id'] . '/delete')) ?>" onsubmit="return confirm('Remove this job material entry?');">
                                                <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                                                <button class="btn btn-outline-danger btn-sm" type="submit">Remove</button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-secondary small">Read only</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php require base_path('app/views/jobs/customer-confirmation.php'); ?>

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
