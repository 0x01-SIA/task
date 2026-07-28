<?php

declare(strict_types=1);
?>
<div class="d-grid gap-4">
    <section class="card shadow-sm border-0">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Materials</p>
                    <h1 class="h3 mb-2">Materials Catalogue</h1>
                    <p class="text-secondary mb-0">Manage reusable catalogue items that can be recorded against jobs.</p>
                </div>
                <a class="btn btn-primary" href="<?= h(app_url('/materials/create')) ?>">Create Material</a>
            </div>
        </div>
    </section>

    <?php if ($successMessage !== null): ?>
        <div class="alert alert-success mb-0" role="status"><?= h($successMessage) ?></div>
    <?php endif; ?>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <form method="get" action="<?= h(app_url('/materials')) ?>" class="row g-3 align-items-end">
                <div class="col-12 col-lg-6">
                    <label class="form-label" for="search">Search</label>
                    <input class="form-control" id="search" name="search" type="text" value="<?= h($filters['search'] ?? '') ?>" placeholder="Material name or SKU/code">
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label" for="status">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All statuses</option>
                        <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-12 d-flex flex-wrap gap-2">
                    <button class="btn btn-primary" type="submit">Apply Filters</button>
                    <a class="btn btn-outline-secondary" href="<?= h(app_url('/materials')) ?>">Clear Filters</a>
                </div>
            </form>
        </div>
    </section>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <?php if ($materials === []): ?>
                <p class="text-secondary mb-0">No materials matched the current filters.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                        <tr>
                            <th scope="col">Material</th>
                            <th scope="col">SKU/Code</th>
                            <th scope="col">Unit</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($materials as $material): ?>
                            <tr>
                                <td class="fw-semibold"><?= h($material['name']) ?></td>
                                <td><?= h($material['sku'] ?: 'Not provided') ?></td>
                                <td><?= h($material['unit']) ?></td>
                                <td>
                                    <span class="badge <?= (int) $material['is_active'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                        <?= (int) $material['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex flex-wrap gap-2 justify-content-end">
                                        <a class="btn btn-outline-primary btn-sm" href="<?= h(app_url('/materials/' . $material['id'])) ?>">View</a>
                                        <a class="btn btn-outline-secondary btn-sm" href="<?= h(app_url('/materials/' . $material['id'] . '/edit')) ?>">Edit</a>
                                    </div>
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
