<?php

declare(strict_types=1);
?>
<div class="d-grid gap-4">
    <section class="card shadow-sm border-0">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Company</p>
                    <h1 class="h3 mb-2"><?= h($company['name']) ?></h1>
                    <p class="text-secondary mb-0"><code><?= h($company['slug']) ?></code></p>
                </div>
                <div class="d-flex gap-2">
                    <a class="btn btn-outline-secondary" href="<?= h(app_url('/companies')) ?>">Back to Companies</a>
                    <a class="btn btn-primary" href="<?= h(app_url('/companies/' . $company['id'] . '/edit')) ?>">Edit Company</a>
                </div>
            </div>
        </div>
    </section>

    <?php if ($successMessage !== null): ?>
        <div class="alert alert-success mb-0" role="status"><?= h($successMessage) ?></div>
    <?php endif; ?>

    <?php if (($errorMessage ?? null) !== null): ?>
        <div class="alert alert-danger mb-0" role="alert"><?= h($errorMessage) ?></div>
    <?php endif; ?>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <div class="info-grid">
                <div>
                    <p class="info-label">Status</p>
                    <p class="mb-0">
                        <span class="badge <?= (int) $company['is_active'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>">
                            <?= (int) $company['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                        </span>
                    </p>
                </div>
                <div>
                    <p class="info-label">Registration Number</p>
                    <p class="mb-0"><?= h($company['registration_number'] ?: 'Not provided') ?></p>
                </div>
                <div>
                    <p class="info-label">Email</p>
                    <p class="mb-0"><?= h($company['email'] ?: 'Not provided') ?></p>
                </div>
                <div>
                    <p class="info-label">Phone</p>
                    <p class="mb-0"><?= h($company['phone'] ?: 'Not provided') ?></p>
                </div>
                <div>
                    <p class="info-label">Address</p>
                    <p class="mb-0"><?= h($company['address'] ?: 'Not provided') ?></p>
                </div>
                <div>
                    <p class="info-label">Members</p>
                    <p class="mb-0"><?= h((string) count($memberships)) ?></p>
                </div>
            </div>
        </div>
    </section>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
                <div>
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Members</p>
                    <h2 class="h5 mb-1">Company Memberships</h2>
                    <p class="text-secondary mb-0">
                        <?= ($canManageMemberships ?? false)
                            ? 'Manage the people assigned to this company.'
                            : 'Review the people currently assigned to this company.' ?>
                    </p>
                </div>
            </div>

            <?php if ($memberships === []): ?>
                <p class="text-secondary mb-0">No memberships have been assigned yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Email</th>
                            <th scope="col">Company Role</th>
                            <th scope="col">Global Status</th>
                            <th scope="col">Membership Status</th>
                            <?php if (($canManageMemberships ?? false)): ?>
                                <th scope="col" class="text-end">Actions</th>
                            <?php endif; ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($memberships as $membership): ?>
                            <tr>
                                <td class="fw-semibold"><?= h($membership['name']) ?></td>
                                <td><?= h($membership['email']) ?></td>
                                <td><?= h(role_label((string) $membership['membership_role'])) ?></td>
                                <td>
                                    <span class="badge <?= (int) $membership['is_active'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                        <?= (int) $membership['is_active'] === 1 ? 'Active account' : 'Inactive account' ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= (int) $membership['membership_is_active'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                        <?= (int) $membership['membership_is_active'] === 1 ? 'Active membership' : 'Inactive membership' ?>
                                    </span>
                                </td>
                                <?php if (($canManageMemberships ?? false)): ?>
                                    <td class="text-end">
                                        <form method="post" action="<?= h(app_url('/companies/' . $company['id'] . '/memberships')) ?>" class="d-inline-flex flex-wrap gap-2 justify-content-end">
                                            <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                                            <input type="hidden" name="user_id" value="<?= h((string) $membership['id']) ?>">
                                            <select class="form-select form-select-sm" name="role">
                                                <?php foreach (user_role_options() as $value => $label): ?>
                                                    <option value="<?= h($value) ?>" <?= (string) $membership['membership_role'] === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <select class="form-select form-select-sm" name="is_active">
                                                <option value="1" <?= (int) $membership['membership_is_active'] === 1 ? 'selected' : '' ?>>Active</option>
                                                <option value="0" <?= (int) $membership['membership_is_active'] === 1 ? '' : 'selected' ?>>Inactive</option>
                                            </select>
                                            <button class="btn btn-outline-primary btn-sm" type="submit">Save</button>
                                        </form>
                                        <form method="post" action="<?= h(app_url('/companies/' . $company['id'] . '/memberships/' . $membership['id'] . '/delete')) ?>" class="d-inline-flex ms-2">
                                            <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                                            <button class="btn btn-outline-danger btn-sm" type="submit">Remove</button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if (($canManageMemberships ?? false)): ?>
        <section class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
                    <div>
                        <p class="text-uppercase text-secondary small fw-semibold mb-2">Memberships</p>
                        <h2 class="h5 mb-1">Add or Reassign User</h2>
                        <p class="text-secondary mb-0">Assign an existing account to this company or update an inactive membership.</p>
                    </div>
                </div>

                <form method="post" action="<?= h(app_url('/companies/' . $company['id'] . '/memberships')) ?>" class="row g-3 align-items-end">
                    <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">

                    <div class="col-md-5">
                        <label class="form-label" for="user_id">User</label>
                        <select class="form-select" id="user_id" name="user_id" required>
                            <option value="">Select user</option>
                            <?php foreach ($assignableUsers as $user): ?>
                                <option value="<?= h((string) $user['id']) ?>">
                                    <?= h($user['name']) ?> · <?= h($user['email']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="role">Role</label>
                        <select class="form-select" id="role" name="role" required>
                            <?php foreach (user_role_options() as $value => $label): ?>
                                <option value="<?= h($value) ?>"><?= h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label" for="membership_status">Status</label>
                        <select class="form-select" id="membership_status" name="is_active">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <button class="btn btn-primary w-100" type="submit">Save Membership</button>
                    </div>
                </form>
            </div>
        </section>
    <?php endif; ?>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <form method="post" action="<?= h(app_url('/companies/' . $company['id'] . '/status')) ?>" class="d-flex gap-2">
                <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="is_active" value="<?= (int) $company['is_active'] === 1 ? '0' : '1' ?>">
                <button class="btn <?= (int) $company['is_active'] === 1 ? 'btn-outline-warning' : 'btn-outline-success' ?>" type="submit">
                    <?= (int) $company['is_active'] === 1 ? 'Deactivate Company' : 'Activate Company' ?>
                </button>
            </form>
        </div>
    </section>
</div>
