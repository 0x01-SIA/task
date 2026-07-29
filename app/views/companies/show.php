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
                    <p class="text-secondary mb-0">Current memberships are shown here for quick verification while the wider membership UI is being completed.</p>
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
