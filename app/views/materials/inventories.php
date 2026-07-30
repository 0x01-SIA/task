<?php

declare(strict_types=1);
?>
<div class="d-grid gap-4">
    <nav class="nav nav-pills gap-2">
        <?php foreach ($stockNavigationItems as $item): ?>
            <a class="nav-link<?= is_current_path($item['path']) ? ' active' : '' ?>" href="<?= h(app_url($item['path'])) ?>"><?= h($item['label']) ?></a>
        <?php endforeach; ?>
    </nav>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Stock</p>
                    <h1 class="h3 mb-2">Material Inventory</h1>
                    <p class="text-secondary mb-0">Inventories capture an absolute stock baseline for every active material in the current company.</p>
                </div>
                <?php if (user_can_manage_material_inventory($viewer)): ?>
                    <form method="post" action="<?= h(app_url('/materials/inventories/create')) ?>">
                        <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                        <button class="btn btn-primary" type="submit">Start Inventory</button>
                    </form>
                <?php endif; ?>
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
            <form method="get" action="<?= h(app_url('/materials/inventories')) ?>" class="row g-3 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label" for="status">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All statuses</option>
                        <option value="draft" <?= ($filters['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="pending_approval" <?= ($filters['status'] ?? '') === 'pending_approval' ? 'selected' : '' ?>>Pending Approval</option>
                        <option value="approved" <?= ($filters['status'] ?? '') === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="cancelled" <?= ($filters['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-12 d-flex flex-wrap gap-2">
                    <button class="btn btn-primary" type="submit">Apply Filters</button>
                    <a class="btn btn-outline-secondary" href="<?= h(app_url('/materials/inventories')) ?>">Clear Filters</a>
                </div>
            </form>
        </div>
    </section>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <?php if ($inventories === []): ?>
                <p class="text-secondary mb-0">No inventories matched the current filters.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                        <tr>
                            <th scope="col">Inventory</th>
                            <th scope="col">Status</th>
                            <th scope="col">Started By</th>
                            <th scope="col">Started</th>
                            <th scope="col">Submitted</th>
                            <th scope="col">Approved</th>
                            <th scope="col">Counted Materials</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($inventories as $inventory): ?>
                            <tr>
                                <td class="fw-semibold">#<?= h($inventory['id']) ?></td>
                                <td><?= h(ucwords(str_replace('_', ' ', (string) $inventory['status']))) ?></td>
                                <td><?= h($inventory['started_by_name'] ?: '—') ?></td>
                                <td><?= h(format_datetime($inventory['started_at'] ?? null)) ?></td>
                                <td><?= h(format_datetime($inventory['submitted_at'] ?? null)) ?></td>
                                <td>
                                    <?= h(format_datetime($inventory['approved_at'] ?? null)) ?>
                                    <?php if (($inventory['approved_by_name'] ?? '') !== ''): ?>
                                        <div class="small text-secondary"><?= h($inventory['approved_by_name']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= h((string) $inventory['completed_material_count']) ?> / <?= h((string) $inventory['counted_material_count']) ?></td>
                                <td class="text-end">
                                    <div class="d-inline-flex flex-wrap gap-2 justify-content-end">
                                        <a class="btn btn-outline-primary btn-sm" href="<?= h(app_url('/materials/inventories/' . $inventory['id'])) ?>">View</a>
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
