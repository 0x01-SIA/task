<?php

declare(strict_types=1);
?>
<div class="d-grid gap-4">
    <section class="card shadow-sm border-0">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Jobs</p>
                    <h1 class="h3 mb-2"><?= h($formTitle) ?></h1>
                    <p class="text-secondary mb-0">Capture the visit details, worker assignment, and planned schedule for this job.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <?php if ($job !== null): ?>
                        <a class="btn btn-outline-primary" href="<?= h(app_url('/jobs/' . $job['id'])) ?>">Back to Job</a>
                    <?php endif; ?>
                    <a class="btn btn-outline-secondary" href="<?= h(app_url('/jobs')) ?>">Back to Jobs</a>
                </div>
            </div>
        </div>
    </section>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <form method="post" action="<?= h(app_url($formAction)) ?>" class="d-grid gap-4">
                <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">

                <?php if (isset($errors['job_number'])): ?>
                    <div class="alert alert-danger mb-0" role="alert"><?= h($errors['job_number']) ?></div>
                <?php endif; ?>

                <?php if (($taskContext ?? null) !== null): ?>
                    <div class="alert alert-info mb-0" role="status">
                        Creating a job for task <strong><?= h($taskContext['task_number']) ?></strong> - <?= h($taskContext['title']) ?>.
                    </div>
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="task_id">Task</label>
                        <select class="form-select<?= isset($errors['task_id']) ? ' is-invalid' : '' ?>" id="task_id" name="task_id">
                            <option value="">Standalone job</option>
                            <?php foreach (($taskOptions ?? []) as $taskOption): ?>
                                <option value="<?= h($taskOption['id']) ?>" <?= (int) ($values['task_id'] ?? 0) === (int) $taskOption['id'] ? 'selected' : '' ?>>
                                    <?= h($taskOption['task_number']) ?> - <?= h($taskOption['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['task_id'])): ?>
                            <div class="invalid-feedback"><?= h($errors['task_id']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="customer_id">Customer</label>
                        <select
                            class="form-select<?= isset($errors['customer_id']) ? ' is-invalid' : '' ?>"
                            id="customer_id"
                            name="customer_id"
                            data-customer-location-filter="customer"
                            required
                        >
                            <option value="">Select a customer</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?= h($customer['id']) ?>" <?= (int) ($values['customer_id'] ?? 0) === (int) $customer['id'] ? 'selected' : '' ?>>
                                    <?= h($customer['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['customer_id'])): ?>
                            <div class="invalid-feedback"><?= h($errors['customer_id']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="location_id">Location</label>
                        <select
                            class="form-select<?= isset($errors['location_id']) ? ' is-invalid' : '' ?>"
                            id="location_id"
                            name="location_id"
                            data-customer-location-filter="location"
                            required
                        >
                            <option value="">Select a location</option>
                            <?php foreach ($locations as $location): ?>
                                <option
                                    value="<?= h($location['id']) ?>"
                                    data-customer-id="<?= h($location['customer_id']) ?>"
                                    <?= (int) ($values['location_id'] ?? 0) === (int) $location['id'] ? 'selected' : '' ?>
                                >
                                    <?= h($location['name']) ?><?= $location['address_line'] ? ' - ' . h($location['address_line']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['location_id'])): ?>
                            <div class="invalid-feedback"><?= h($errors['location_id']) ?></div>
                        <?php endif; ?>
                        <div class="form-text">Only locations belonging to the selected customer remain visible.</div>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="title">Title</label>
                        <input class="form-control<?= isset($errors['title']) ? ' is-invalid' : '' ?>" id="title" name="title" type="text" value="<?= h($values['title'] ?? '') ?>" required>
                        <?php if (isset($errors['title'])): ?>
                            <div class="invalid-feedback"><?= h($errors['title']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="4"><?= h($values['description'] ?? '') ?></textarea>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="job_type">Job Type</label>
                        <select class="form-select<?= isset($errors['job_type']) ? ' is-invalid' : '' ?>" id="job_type" name="job_type" required>
                            <?php foreach (job_type_options() as $value => $label): ?>
                                <option value="<?= h($value) ?>" <?= ($values['job_type'] ?? 'installation') === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['job_type'])): ?>
                            <div class="invalid-feedback"><?= h($errors['job_type']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="priority">Priority</label>
                        <select class="form-select<?= isset($errors['priority']) ? ' is-invalid' : '' ?>" id="priority" name="priority" required>
                            <?php foreach (job_priority_options() as $value => $label): ?>
                                <option value="<?= h($value) ?>" <?= ($values['priority'] ?? 'normal') === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['priority'])): ?>
                            <div class="invalid-feedback"><?= h($errors['priority']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="assigned_user_id">Assigned Worker</label>
                        <select class="form-select<?= isset($errors['assigned_user_id']) ? ' is-invalid' : '' ?>" id="assigned_user_id" name="assigned_user_id">
                            <option value="">Unassigned</option>
                            <?php foreach ($workers as $worker): ?>
                                <option value="<?= h($worker['id']) ?>" <?= (int) ($values['assigned_user_id'] ?? 0) === (int) $worker['id'] ? 'selected' : '' ?>>
                                    <?= h($worker['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['assigned_user_id'])): ?>
                            <div class="invalid-feedback"><?= h($errors['assigned_user_id']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="planned_date">Planned Date</label>
                        <input class="form-control<?= isset($errors['planned_date']) ? ' is-invalid' : '' ?>" id="planned_date" name="planned_date" type="date" value="<?= h($values['planned_date'] ?? '') ?>">
                        <?php if (isset($errors['planned_date'])): ?>
                            <div class="invalid-feedback"><?= h($errors['planned_date']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="planned_start_time">Planned Start Time</label>
                        <input class="form-control<?= isset($errors['planned_start_time']) ? ' is-invalid' : '' ?>" id="planned_start_time" name="planned_start_time" type="time" value="<?= h($values['planned_start_time'] ?? '') ?>">
                        <?php if (isset($errors['planned_start_time'])): ?>
                            <div class="invalid-feedback"><?= h($errors['planned_start_time']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="estimated_duration_minutes">Estimated Duration (minutes)</label>
                        <input class="form-control<?= isset($errors['estimated_duration_minutes']) ? ' is-invalid' : '' ?>" id="estimated_duration_minutes" name="estimated_duration_minutes" type="number" min="1" max="1440" value="<?= h($values['estimated_duration_minutes'] ?? '') ?>">
                        <?php if (isset($errors['estimated_duration_minutes'])): ?>
                            <div class="invalid-feedback"><?= h($errors['estimated_duration_minutes']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="internal_notes">Internal Notes</label>
                        <textarea class="form-control" id="internal_notes" name="internal_notes" rows="4"><?= h($values['internal_notes'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-primary" type="submit"><?= h($submitLabel) ?></button>
                    <a class="btn btn-outline-secondary" href="<?= h(app_url($job !== null ? '/jobs/' . $job['id'] : '/jobs')) ?>">Cancel</a>
                </div>
            </form>
        </div>
    </section>
</div>
