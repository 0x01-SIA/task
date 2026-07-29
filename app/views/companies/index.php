<?php

declare(strict_types=1);
?>
<div class="d-grid gap-4">
    <section class="card shadow-sm border-0">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Companies</p>
                    <h1 class="h3 mb-2">Company Administration</h1>
                    <p class="text-secondary mb-0">Create and manage companies, activation state, and membership capacity.</p>
                </div>
                <a class="btn btn-primary" href="<?= h(app_url('/companies/create')) ?>">Create Company</a>
            </div>
        </div>
    </section>

    <?php if ($successMessage !== null): ?>
        <div class="alert alert-success mb-0" role="status"><?= h($successMessage) ?></div>
    <?php endif; ?>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <?php if ($companies === []): ?>
                <p class="text-secondary mb-0">No companies have been created yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                        <tr>
                            <th scope="col">Company</th>
                            <th scope="col">Slug</th>
                            <th scope="col">Members</th>
                            <th scope="col">Status</th>
                            <th scope="col">Updated</th>
                            <th scope="col" class="text-end">Details</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($companies as $company): ?>
                            <tr>
                                <td class="fw-semibold"><?= h($company['name']) ?></td>
                                <td><code><?= h($company['slug']) ?></code></td>
                                <td><?= h((string) (int) ($company['active_member_count'] ?? 0)) ?></td>
                                <td>
                                    <span class="badge <?= (int) $company['is_active'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                        <?= (int) $company['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td><?= h(format_datetime($company['updated_at'] ?? null)) ?></td>
                                <td class="text-end">
                                    <a class="btn btn-outline-primary btn-sm" href="<?= h(app_url('/companies/' . $company['id'])) ?>">View</a>
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
