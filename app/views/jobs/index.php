<?php

declare(strict_types=1);

$canManageJobs = in_array((string) ($user['role'] ?? ''), ['admin', 'dispatcher'], true);
$jobStatusOptions = job_status_options();
?>
<div class="d-grid gap-4">
    <section class="card shadow-sm border-0">
        <div class="card-body p-4 p-lg-5">
            <div class="responsive-page-header">
                <div>
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Jobs</p>
                    <h1 class="h3 mb-2"><?= h($canManageJobs ? 'Job Management' : 'My Jobs') ?></h1>
                    <p class="text-secondary mb-0">Track scheduled field work, assignments, and current planning status.</p>
                </div>
                <div class="responsive-page-header__actions d-flex flex-wrap gap-2">
                    <?php if ($canManageJobs): ?>
                        <a class="btn btn-outline-secondary" href="<?= h(app_url('/jobs/calendar')) ?>">Calendar</a>
                        <a class="btn btn-primary" href="<?= h(app_url('/jobs/create')) ?>">Create Job</a>
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
            <form method="get" action="<?= h(app_url('/jobs')) ?>" class="row g-3 align-items-end responsive-index-toolbar">
                <?php if (($filters['schedule'] ?? '') === 'unscheduled'): ?>
                    <input type="hidden" name="schedule" value="unscheduled">
                <?php endif; ?>
                <div class="col-12 col-lg-4">
                    <label class="form-label" for="search">Search</label>
                    <input class="form-control" id="search" name="search" type="text" value="<?= h($filters['search'] ?? '') ?>" placeholder="Job number, title, customer, location">
                </div>

                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label" for="status">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All statuses</option>
                        <?php foreach (job_status_options() as $value => $label): ?>
                            <option value="<?= h($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($canManageJobs): ?>
                    <div class="col-12 col-sm-6 col-lg-2">
                        <label class="form-label" for="worker_id">Worker</label>
                        <select class="form-select" id="worker_id" name="worker_id">
                            <option value="">All workers</option>
                            <?php foreach ($workers as $worker): ?>
                                <option value="<?= h($worker['id']) ?>" <?= (int) ($filters['worker_id'] ?? 0) === (int) $worker['id'] ? 'selected' : '' ?>>
                                    <?= h($worker['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label" for="customer_id">Customer</label>
                    <select class="form-select" id="customer_id" name="customer_id">
                        <option value="">All customers</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?= h($customer['id']) ?>" <?= (int) ($filters['customer_id'] ?? 0) === (int) $customer['id'] ? 'selected' : '' ?>>
                                <?= h($customer['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label" for="planned_date">Planned date</label>
                    <input class="form-control" id="planned_date" name="planned_date" type="date" value="<?= h($filters['planned_date'] ?? '') ?>">
                </div>

                <div class="col-12 d-flex flex-wrap gap-2">
                    <button class="btn btn-primary" type="submit">Apply Filters</button>
                    <a class="btn btn-outline-secondary" href="<?= h(app_url('/jobs')) ?>">Clear Filters</a>
                </div>

                <div class="col-12">
                    <div class="responsive-status-chips" aria-label="Job status quick filters">
                        <a class="responsive-status-chips__chip<?= ($filters['status'] ?? '') === '' ? ' active' : '' ?>" href="<?= h(app_url('/jobs')) ?>">All</a>
                        <?php foreach ($jobStatusOptions as $value => $label): ?>
                            <?php
                            $query = array_filter([
                                'search' => $filters['search'] ?? '',
                                'status' => $value,
                                'worker_id' => $filters['worker_id'] ?? '',
                                'customer_id' => $filters['customer_id'] ?? '',
                                'planned_date' => $filters['planned_date'] ?? '',
                                'schedule' => $filters['schedule'] ?? '',
                            ], static fn ($item): bool => $item !== '' && $item !== null);
                            ?>
                            <a
                                class="responsive-status-chips__chip<?= ($filters['status'] ?? '') === $value ? ' active' : '' ?>"
                                href="<?= h(app_url('/jobs?' . http_build_query($query))) ?>"
                            >
                                <?= h($label) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <?php if (($filters['schedule'] ?? '') === 'unscheduled'): ?>
                <div class="alert alert-info mb-4" role="status">
                    Showing active jobs without a planned date.
                </div>
            <?php endif; ?>

            <?php if ($jobs === []): ?>
                <p class="text-secondary mb-0">No jobs matched the current filters.</p>
            <?php else: ?>
                <div class="table-responsive desktop-table">
                    <table class="table align-middle mb-0">
                        <thead>
                        <tr>
                            <th scope="col">Job Number</th>
                            <th scope="col">Customer</th>
                            <th scope="col">Location</th>
                            <th scope="col">Job Type</th>
                            <th scope="col">Assigned Worker</th>
                            <th scope="col">Planned</th>
                            <th scope="col">Status</th>
                            <th scope="col">Updated</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($jobs as $job): ?>
                            <tr>
                                <td class="fw-semibold"><a href="<?= h(app_url('/jobs/' . $job['id'])) ?>"><?= h($job['job_number']) ?></a></td>
                                <td><?= h($job['customer_name']) ?></td>
                                <td><?= h($job['location_name'] ?: 'Not assigned') ?></td>
                                <td><?= h(job_type_label((string) $job['job_type'])) ?></td>
                                <td><?= h($job['assigned_worker_name'] ?: 'Unassigned') ?></td>
                                <td><?= h(trim(format_date($job['planned_date'] ?? null) . ' ' . ($job['planned_start_time'] !== null ? format_time((string) $job['planned_start_time']) : ''))) ?></td>
                                <td>
                                    <span class="badge <?= h(job_status_badge_class((string) $job['status'])) ?>">
                                        <?= h(job_status_label((string) $job['status'])) ?>
                                    </span>
                                </td>
                                <td><?= h(format_datetime($job['updated_at'] ?? null)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mobile-card-list" aria-label="Jobs">
                    <?php foreach ($jobs as $job): ?>
                        <?php
                        $plannedAt = trim(format_date($job['planned_date'] ?? null) . ' ' . ($job['planned_start_time'] !== null ? format_time((string) $job['planned_start_time']) : ''));
                        $isOverdue = ($job['planned_date'] ?? null) !== null
                            && (string) $job['planned_date'] < date('Y-m-d')
                            && !in_array((string) ($job['status'] ?? ''), ['completed', 'cancelled'], true);
                        ?>
                        <a class="mobile-card-link" href="<?= h(app_url('/jobs/' . $job['id'])) ?>">
                            <div class="mobile-card__top">
                                <div>
                                    <div class="mobile-card__eyebrow">Job <?= h($job['job_number']) ?></div>
                                    <h2 class="mobile-card__title"><?= h($job['customer_name']) ?></h2>
                                </div>
                                <span class="badge <?= h(job_status_badge_class((string) $job['status'])) ?>">
                                    <?= h(job_status_label((string) $job['status'])) ?>
                                </span>
                            </div>

                            <div class="mobile-card__meta">
                                <div class="mobile-card__meta-item">
                                    <span class="mobile-card__meta-label">Location</span>
                                    <span class="mobile-card__meta-value"><?= h($job['location_name'] ?: 'Not assigned') ?></span>
                                </div>
                                <div class="mobile-card__meta-item">
                                    <span class="mobile-card__meta-label">Worker</span>
                                    <span class="mobile-card__meta-value"><?= h($job['assigned_worker_name'] ?: 'Unassigned') ?></span>
                                </div>
                                <div class="mobile-card__meta-item">
                                    <span class="mobile-card__meta-label">Planned</span>
                                    <span class="mobile-card__meta-value"><?= h($plannedAt !== '' ? $plannedAt : 'Not scheduled') ?></span>
                                </div>
                                <div class="mobile-card__meta-item">
                                    <span class="mobile-card__meta-label">Job type</span>
                                    <span class="mobile-card__meta-value"><?= h(job_type_label((string) $job['job_type'])) ?></span>
                                </div>
                            </div>

                            <div class="mobile-card__flag-row">
                                <?php if ($isOverdue): ?>
                                    <span class="mobile-card__flag mobile-card__flag--danger">Overdue</span>
                                <?php endif; ?>
                                <?php if ($plannedAt === ''): ?>
                                    <span class="mobile-card__flag">Needs scheduling</span>
                                <?php endif; ?>
                            </div>

                            <span class="mobile-card__cta">Open job <span aria-hidden="true">›</span></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
