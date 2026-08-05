<?php

declare(strict_types=1);

$movementBaseQuery = ['limit' => $movementLimit];
$jobPathBase = (($viewer['role'] ?? '') === 'worker') ? '/work/jobs/' : '/jobs/';
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
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Material</p>
                    <h1 class="h3 mb-2"><?= h($material['name']) ?></h1>
                    <p class="text-secondary mb-2"><?= h(($material['sku'] ?? '') !== '' ? $material['sku'] : 'No SKU/code provided') ?></p>
                    <div class="d-flex flex-wrap gap-2">
                        <?php if ((int) ($material['is_device'] ?? 0) === 1): ?>
                            <span class="badge text-bg-primary">Device</span>
                        <?php endif; ?>
                        <?php if ((int) ($material['is_device_accessory'] ?? 0) === 1): ?>
                            <span class="badge text-bg-info">Device accessory</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-secondary" href="<?= h(app_url('/materials')) ?>">Back to Materials</a>
                    <?php if (user_can_create_manual_material_movement($viewer)): ?>
                        <a class="btn btn-outline-primary" href="<?= h(app_url('/materials/movements/create?material_id=' . $material['id'])) ?>">Add Movement</a>
                    <?php endif; ?>
                    <?php if (user_can_manage_materials_catalogue($viewer)): ?>
                        <a class="btn btn-primary" href="<?= h(app_url('/materials/' . $material['id'] . '/edit')) ?>">Edit Material</a>
                        <form method="post" action="<?= h(app_url('/materials/' . $material['id'] . '/delete')) ?>" onsubmit="return confirm('Delete this material? This cannot be undone.');">
                            <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                            <button class="btn btn-outline-danger" type="submit">Delete Material</button>
                        </form>
                    <?php endif; ?>
                </div>
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
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
                <div>
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Current Stock</p>
                    <h2 class="h4 mb-1"><?= h(format_decimal_quantity($currentStock)) ?> <?= h($material['unit']) ?></h2>
                    <span class="badge <?= h(material_stock_status_class((string) $currentStock)) ?>">
                        <?= h(material_stock_label((string) $currentStock)) ?>
                    </span>
                </div>
                <?php if ($latestApprovedInventory !== null): ?>
                    <div class="text-secondary small">
                        Baseline from approved inventory at <?= h(format_datetime((string) $latestApprovedInventory['approved_at'])) ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="info-grid">
                <div>
                    <p class="info-label">Name</p>
                    <p class="mb-0"><?= h($material['name']) ?></p>
                </div>
                <div>
                    <p class="info-label">SKU/Code</p>
                    <p class="mb-0"><?= h($material['sku'] ?: 'Not provided') ?></p>
                </div>
                <div>
                    <p class="info-label">Unit</p>
                    <p class="mb-0"><?= h($material['unit']) ?></p>
                </div>
                <div>
                    <p class="info-label">Status</p>
                    <p class="mb-0">
                        <span class="badge <?= (int) $material['is_active'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>">
                            <?= (int) $material['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                        </span>
                    </p>
                </div>
                <div>
                    <p class="info-label">Device</p>
                    <p class="mb-0"><?= (int) ($material['is_device'] ?? 0) === 1 ? 'Yes' : 'No' ?></p>
                </div>
                <div>
                    <p class="info-label">Accessory Eligible</p>
                    <p class="mb-0"><?= (int) ($material['is_device_accessory'] ?? 0) === 1 ? 'Yes' : 'No' ?></p>
                </div>
                <div>
                    <p class="info-label">Created</p>
                    <p class="mb-0"><?= h(format_datetime($material['created_at'] ?? null)) ?></p>
                </div>
                <div>
                    <p class="info-label">Updated</p>
                    <p class="mb-0"><?= h(format_datetime($material['updated_at'] ?? null)) ?></p>
                </div>
            </div>

            <div class="mt-4">
                <p class="info-label">Description</p>
                <p class="mb-0"><?= nl2br(h($material['description'] ?: 'No description provided.')) ?></p>
            </div>
        </div>
    </section>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">History</p>
                    <h2 class="h5 mb-1">Material Movements</h2>
                    <p class="text-secondary mb-0">Manual and job-linked movement history for this material.</p>
                </div>
                <form method="get" action="<?= h(app_url('/materials/' . $material['id'])) ?>" class="material-movements-toolbar">
                    <label class="form-label mb-0" for="limit">Show</label>
                    <select class="form-select form-select-sm" id="limit" name="limit">
                        <?php foreach ([10, 50, 100] as $limitOption): ?>
                            <option value="<?= h((string) $limitOption) ?>" <?= $movementLimit === $limitOption ? 'selected' : '' ?>><?= h((string) $limitOption) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="text-secondary small">entries</span>
                    <button class="btn btn-outline-secondary btn-sm" type="submit">Apply</button>
                </form>
            </div>

            <div class="mt-4">
                <?php if ($materialMovements === []): ?>
                    <p class="text-secondary mb-0">No movements have been recorded for this material.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                            <tr>
                                <th scope="col">Date and Time</th>
                                <th scope="col">Type</th>
                                <th scope="col">Job Number</th>
                                <th scope="col">Quantity</th>
                                <th scope="col">Direction</th>
                                <th scope="col">Note</th>
                                <th scope="col">Recorded By</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($materialMovements as $movement): ?>
                                <tr>
                                    <td><?= h(format_datetime($movement['occurred_at'] ?? $movement['created_at'] ?? null)) ?></td>
                                    <td><?= h(material_movement_type_label((string) $movement['movement_type'])) ?></td>
                                    <td>
                                        <?php if (($movement['job_id'] ?? null) !== null): ?>
                                            <a href="<?= h(app_url($jobPathBase . $movement['job_id'])) ?>"><?= h($movement['job_number']) ?></a>
                                        <?php else: ?>
                                            Manual
                                        <?php endif; ?>
                                    </td>
                                    <td><?= h(format_decimal_quantity($movement['quantity']) . ' ' . $movement['material_unit']) ?></td>
                                    <td><?= h($movement['movement_type'] === 'in' ? '+' : '-') ?></td>
                                    <td><?= h(($movement['note'] ?? '') !== '' ? $movement['note'] : '—') ?></td>
                                    <td><?= h($movement['created_by_name'] ?: '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($movementLastPage > 1): ?>
                        <div class="material-movements-pagination mt-4">
                            <?php if ($movementPage > 1): ?>
                                <a class="btn btn-outline-secondary btn-sm" href="<?= h(app_url('/materials/' . $material['id'] . '?' . http_build_query($movementBaseQuery + ['page' => $movementPage - 1]))) ?>">Previous</a>
                            <?php else: ?>
                                <span class="btn btn-outline-secondary btn-sm disabled" aria-disabled="true">Previous</span>
                            <?php endif; ?>

                            <span class="text-secondary small">Page <?= h((string) $movementPage) ?> of <?= h((string) $movementLastPage) ?></span>

                            <?php if ($movementPage < $movementLastPage): ?>
                                <a class="btn btn-outline-secondary btn-sm" href="<?= h(app_url('/materials/' . $material['id'] . '?' . http_build_query($movementBaseQuery + ['page' => $movementPage + 1]))) ?>">Next</a>
                            <?php else: ?>
                                <span class="btn btn-outline-secondary btn-sm disabled" aria-disabled="true">Next</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Status</p>
                    <h2 class="h5 mb-1">Activation</h2>
                    <p class="text-secondary mb-0">Inactive materials remain visible on historical jobs but cannot be selected for new usage.</p>
                </div>
                <?php if (user_can_manage_materials_catalogue($viewer)): ?>
                    <form method="post" action="<?= h(app_url('/materials/' . $material['id'] . '/status')) ?>" onsubmit="return confirm('Update this material status?');">
                        <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="is_active" value="<?= (int) $material['is_active'] === 1 ? '0' : '1' ?>">
                        <button class="btn <?= (int) $material['is_active'] === 1 ? 'btn-outline-danger' : 'btn-outline-success' ?>" type="submit">
                            <?= (int) $material['is_active'] === 1 ? 'Deactivate Material' : 'Activate Material' ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>
