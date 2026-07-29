<?php

declare(strict_types=1);
?>
<div class="d-grid gap-4">
    <section class="card shadow-sm border-0">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Users</p>
                    <h1 class="h3 mb-2">User Administration</h1>
                    <p class="text-secondary mb-0">Manage application accounts, roles, status, and access to worker assignments.</p>
                </div>
                <a class="btn btn-primary" href="<?= h(app_url('/users/create')) ?>">Create User</a>
            </div>
        </div>
    </section>

    <?php if ($successMessage !== null): ?>
        <div class="alert alert-success mb-0" role="status"><?= h($successMessage) ?></div>
    <?php endif; ?>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <form method="get" action="<?= h(app_url('/users')) ?>" class="row g-3 align-items-end">
                <div class="col-12 col-lg-5">
                    <label class="form-label" for="search">Search</label>
                    <input class="form-control" id="search" name="search" type="text" value="<?= h($filters['search'] ?? '') ?>" placeholder="Name or email">
                </div>

                <div class="col-6 col-lg-3">
                    <label class="form-label" for="role">Role</label>
                    <select class="form-select" id="role" name="role">
                        <option value="">All roles</option>
                        <?php foreach (user_role_options() as $value => $label): ?>
                            <option value="<?= h($value) ?>" <?= ($filters['role'] ?? '') === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-6 col-lg-2">
                    <label class="form-label" for="is_active">Status</label>
                    <select class="form-select" id="is_active" name="is_active">
                        <option value="">All statuses</option>
                        <option value="1" <?= ($filters['is_active'] ?? '') === '1' ? 'selected' : '' ?>>Active</option>
                        <option value="0" <?= ($filters['is_active'] ?? '') === '0' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>

                <div class="col-12 col-lg-2 d-flex flex-wrap gap-2">
                    <button class="btn btn-primary" type="submit">Apply Filters</button>
                    <a class="btn btn-outline-secondary" href="<?= h(app_url('/users')) ?>">Clear</a>
                </div>
            </form>
        </div>
    </section>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <?php if ($users === []): ?>
                <p class="text-secondary mb-0">No users matched the current filters.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Email</th>
                            <th scope="col">Role</th>
                            <th scope="col">Status</th>
                            <th scope="col">Active Jobs</th>
                            <th scope="col">Updated</th>
                            <th scope="col" class="text-end">Details</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users as $managedUser): ?>
                            <tr>
                                <td class="fw-semibold"><?= h($managedUser['name']) ?></td>
                                <td><?= h($managedUser['email']) ?></td>
                                <td>
                                    <?php
                                    $systemRole = (string) ($managedUser['system_role'] ?? '');
                                    $companyRole = (string) ($managedUser['company_role'] ?? '');
                                    $displayRole = $systemRole === 'super_admin'
                                        ? $systemRole
                                        : ($companyRole !== '' ? $companyRole : $systemRole);
                                    ?>
                                    <?= h(role_label($displayRole)) ?>
                                </td>
                                <td>
                                    <span class="badge <?= (int) $managedUser['is_active'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                        <?= (int) $managedUser['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td><?= h((string) (int) ($managedUser['active_job_count'] ?? 0)) ?></td>
                                <td><?= h(format_datetime($managedUser['updated_at'] ?? null)) ?></td>
                                <td class="text-end">
                                    <a class="btn btn-outline-primary btn-sm" href="<?= h(app_url('/users/' . $managedUser['id'])) ?>">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
