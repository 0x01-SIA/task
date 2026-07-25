<?php

declare(strict_types=1);

$isSelf = current_user() !== null && (int) current_user()['id'] === (int) $managedUser['id'];
?>
<div class="d-grid gap-4">
    <section class="card shadow-sm border-0">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">User</p>
                    <h1 class="h3 mb-2"><?= h($managedUser['name']) ?></h1>
                    <p class="text-secondary mb-0"><?= h($managedUser['email']) ?></p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-secondary" href="<?= h(app_url('/users')) ?>">Back to Users</a>
                    <a class="btn btn-primary" href="<?= h(app_url('/users/' . $managedUser['id'] . '/edit')) ?>">Edit User</a>
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
                    <p class="info-label">Role</p>
                    <p class="mb-0"><?= h(role_label((string) $managedUser['role'])) ?></p>
                </div>
                <div>
                    <p class="info-label">Status</p>
                    <p class="mb-0">
                        <span class="badge <?= (int) $managedUser['is_active'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>">
                            <?= (int) $managedUser['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                        </span>
                    </p>
                </div>
                <div>
                    <p class="info-label">Created</p>
                    <p class="mb-0"><?= h(format_datetime($managedUser['created_at'] ?? null)) ?></p>
                </div>
                <div>
                    <p class="info-label">Updated</p>
                    <p class="mb-0"><?= h(format_datetime($managedUser['updated_at'] ?? null)) ?></p>
                </div>
                <div>
                    <p class="info-label">Assigned Active Jobs</p>
                    <p class="mb-0"><?= h((string) (int) ($managedUser['active_job_count'] ?? 0)) ?></p>
                </div>
                <div>
                    <p class="info-label">Assigned Jobs Total</p>
                    <p class="mb-0"><?= h((string) (int) ($managedUser['total_job_count'] ?? 0)) ?></p>
                </div>
                <div>
                    <p class="info-label">Completed Jobs</p>
                    <p class="mb-0"><?= h((string) (int) ($managedUser['completed_job_count'] ?? 0)) ?></p>
                </div>
                <div>
                    <p class="info-label">Administration Guardrails</p>
                    <p class="mb-0">
                        <?= $isSelf ? 'Your own admin access is protected.' : 'Last active admin protections remain enforced.' ?>
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
                <div>
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Assignments</p>
                    <h2 class="h5 mb-1">Recent Assigned Jobs</h2>
                    <p class="text-secondary mb-0">Historical assignments stay visible even when an account is inactive.</p>
                </div>
            </div>

            <?php if ($recentJobs === []): ?>
                <p class="text-secondary mb-0">No jobs have been assigned to this user yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                        <tr>
                            <th scope="col">Job</th>
                            <th scope="col">Customer</th>
                            <th scope="col">Location</th>
                            <th scope="col">Planned</th>
                            <th scope="col">Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recentJobs as $job): ?>
                            <tr>
                                <td class="fw-semibold">
                                    <a href="<?= h(app_url('/jobs/' . $job['id'])) ?>"><?= h($job['job_number']) ?></a>
                                    <div class="small text-secondary"><?= h($job['title']) ?></div>
                                </td>
                                <td><?= h($job['customer_name']) ?></td>
                                <td><?= h($job['location_name'] ?: 'Not assigned') ?></td>
                                <td><?= h(format_job_scheduled_start($job)) ?></td>
                                <td>
                                    <span class="badge <?= h(job_status_badge_class((string) $job['status'])) ?>">
                                        <?= h(job_status_label((string) $job['status'])) ?>
                                    </span>
                                </td>
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
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Security</p>
                    <h2 class="h5 mb-1">Reset Password</h2>
                    <p class="text-secondary mb-0">This updates only the stored password hash. Existing passwords are never shown.</p>
                </div>
            </div>

            <form method="post" action="<?= h(app_url('/users/' . $managedUser['id'] . '/password')) ?>" class="row g-3">
                <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">

                <div class="col-md-6">
                    <label class="form-label" for="password">New Password</label>
                    <input class="form-control<?= isset($passwordErrors['password']) ? ' is-invalid' : '' ?>" id="password" name="password" type="password" required>
                    <?php if (isset($passwordErrors['password'])): ?>
                        <div class="invalid-feedback"><?= h($passwordErrors['password']) ?></div>
                    <?php endif; ?>
                    <div class="form-text">Minimum <?= h((string) password_min_length()) ?> characters.</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="password_confirmation">Confirm Password</label>
                    <input class="form-control<?= isset($passwordErrors['password_confirmation']) ? ' is-invalid' : '' ?>" id="password_confirmation" name="password_confirmation" type="password" required>
                    <?php if (isset($passwordErrors['password_confirmation'])): ?>
                        <div class="invalid-feedback"><?= h($passwordErrors['password_confirmation']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12 d-flex flex-wrap gap-2">
                    <button class="btn btn-primary" type="submit">Reset Password</button>
                    <a class="btn btn-outline-secondary" href="<?= h(app_url('/users/' . $managedUser['id'])) ?>">Refresh</a>
                </div>
            </form>
        </div>
    </section>
</div>
