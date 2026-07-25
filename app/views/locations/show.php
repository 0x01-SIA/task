<?php

declare(strict_types=1);
?>
<div class="d-grid gap-4">
    <section class="card shadow-sm border-0">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Location</p>
                    <h1 class="h3 mb-2"><?= h($location['name']) ?></h1>
                    <p class="text-secondary mb-0">Location details for <?= h($location['customer_name']) ?>.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-primary" href="<?= h(app_url('/customers/' . $location['customer_id'])) ?>">View Customer</a>
                    <a class="btn btn-primary" href="<?= h(app_url('/locations/' . $location['id'] . '/edit')) ?>">Edit Location</a>
                </div>
            </div>
        </div>
    </section>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <div class="info-grid">
                <div>
                    <p class="info-label">Customer</p>
                    <p class="mb-0"><a href="<?= h(app_url('/customers/' . $location['customer_id'])) ?>"><?= h($location['customer_name']) ?></a></p>
                </div>
                <div>
                    <p class="info-label">Status</p>
                    <p class="mb-0">
                        <span class="badge <?= (int) $location['is_active'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>">
                            <?= (int) $location['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                        </span>
                    </p>
                </div>
                <div>
                    <p class="info-label">Address</p>
                    <p class="mb-0"><?= nl2br(h(location_address($location) ?: 'Not provided')) ?></p>
                </div>
                <div>
                    <p class="info-label">Contact Name</p>
                    <p class="mb-0"><?= h($location['contact_name'] ?: 'Not provided') ?></p>
                </div>
                <div>
                    <p class="info-label">Contact Phone</p>
                    <p class="mb-0"><?= h($location['contact_phone'] ?: 'Not provided') ?></p>
                </div>
                <div>
                    <p class="info-label">Created</p>
                    <p class="mb-0"><?= h(format_datetime($location['created_at'] ?? null)) ?></p>
                </div>
                <div>
                    <p class="info-label">Updated</p>
                    <p class="mb-0"><?= h(format_datetime($location['updated_at'] ?? null)) ?></p>
                </div>
            </div>

            <div class="mt-4">
                <p class="info-label">Access Notes</p>
                <p class="mb-0"><?= nl2br(h($location['notes'] ?: 'No access notes recorded.')) ?></p>
            </div>
        </div>
    </section>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <p class="text-uppercase text-secondary small fw-semibold mb-2">Recent Jobs</p>
            <h2 class="h5 mb-2">Location Jobs</h2>
            <?php if ($recentJobs === []): ?>
                <p class="text-secondary mb-0">No jobs are linked to this location yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                        <tr>
                            <th scope="col">Job</th>
                            <th scope="col">Title</th>
                            <th scope="col">Planned</th>
                            <th scope="col">Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recentJobs as $job): ?>
                            <tr>
                                <td class="fw-semibold"><a href="<?= h(app_url('/jobs/' . $job['id'])) ?>"><?= h($job['job_number']) ?></a></td>
                                <td><?= h($job['title']) ?></td>
                                <td><?= h(trim(format_date($job['planned_date'] ?? null) . ' ' . ($job['planned_start_time'] !== null ? format_time((string) $job['planned_start_time']) : ''))) ?></td>
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
