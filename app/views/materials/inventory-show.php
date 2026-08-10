<?php

declare(strict_types=1);

$isReadOnly = (string) $inventory['status'] !== 'draft' || !user_can_manage_material_inventory($viewer);
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
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Inventory</p>
                    <h1 class="h3 mb-2">Inventory #<?= h($inventory['id']) ?></h1>
                    <p class="text-secondary mb-0">Status: <?= h(ucwords(str_replace('_', ' ', (string) $inventory['status']))) ?></p>
                </div>
                <a class="btn btn-outline-secondary" href="<?= h(app_url('/materials/inventories')) ?>">Back to Inventory</a>
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
            <div class="info-grid">
                <div>
                    <p class="info-label">Started By</p>
                    <p class="mb-0"><?= h($inventory['started_by_name'] ?: '—') ?></p>
                </div>
                <div>
                    <p class="info-label">Started At</p>
                    <p class="mb-0"><?= h(format_datetime($inventory['started_at'] ?? null)) ?></p>
                </div>
                <div>
                    <p class="info-label">Submitted At</p>
                    <p class="mb-0"><?= h(format_datetime($inventory['submitted_at'] ?? null)) ?></p>
                </div>
                <div>
                    <p class="info-label">Effective At</p>
                    <p class="mb-0"><?= h(format_datetime($inventory['effective_at'] ?? null)) ?></p>
                </div>
                <div>
                    <p class="info-label">Approved At</p>
                    <p class="mb-0"><?= h(format_datetime($inventory['approved_at'] ?? null)) ?></p>
                </div>
                <div>
                    <p class="info-label">Approved By</p>
                    <p class="mb-0"><?= h($inventory['approved_by_name'] ?: '—') ?></p>
                </div>
                <div>
                    <p class="info-label">Counted Materials</p>
                    <p class="mb-0"><?= h((string) $inventory['completed_material_count']) ?> / <?= h((string) $inventory['counted_material_count']) ?></p>
                </div>
            </div>
        </div>
    </section>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <?php if (!$isReadOnly): ?>
                <form id="<?= h('inventory-save-' . $inventory['id']) ?>" method="post" action="<?= h(app_url('/materials/inventories/' . $inventory['id'] . '/save')) ?>">
                    <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                            <tr>
                                <th scope="col">Material</th>
                                <th scope="col">System Quantity at Start</th>
                                <th scope="col">Counted Quantity</th>
                                <th scope="col">Difference</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($lines as $line): ?>
                                <?php
                                $lineId = (int) $line['id'];
                                $displayValue = array_key_exists($lineId, $submittedValues)
                                    ? (string) $submittedValues[$lineId]
                                    : (($line['counted_quantity'] ?? null) !== null ? format_decimal_quantity($line['counted_quantity']) : '');
                                $difference = ($line['counted_quantity'] ?? null) !== null
                                    ? format_decimal_quantity((string) ((float) $line['counted_quantity'] - (float) $line['system_quantity_at_start']))
                                    : '—';
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= h($line['material_name']) ?></div>
                                        <div class="small text-secondary"><?= h(($line['material_sku'] ?: 'No SKU/code') . ' - ' . $line['material_unit']) ?></div>
                                    </td>
                                    <td><?= h(format_decimal_quantity($line['system_quantity_at_start']) . ' ' . $line['material_unit']) ?></td>
                                    <td>
                                        <input class="form-control<?= isset($lineErrors[$lineId]) ? ' is-invalid' : '' ?>" name="counted_quantity[<?= h((string) $lineId) ?>]" type="text" value="<?= h($displayValue) ?>" inputmode="decimal" placeholder="0.000">
                                        <?php if (isset($lineErrors[$lineId])): ?>
                                            <div class="invalid-feedback d-block"><?= h($lineErrors[$lineId]) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= h($difference !== '—' ? $difference . ' ' . $line['material_unit'] : '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                        <tr>
                            <th scope="col">Material</th>
                            <th scope="col">System Quantity at Start</th>
                            <th scope="col">Counted Quantity</th>
                            <th scope="col">Difference</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($lines as $line): ?>
                            <?php
                            $difference = ($line['counted_quantity'] ?? null) !== null
                                ? format_decimal_quantity((string) ((float) $line['counted_quantity'] - (float) $line['system_quantity_at_start']))
                                : '—';
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= h($line['material_name']) ?></div>
                                    <div class="small text-secondary"><?= h(($line['material_sku'] ?: 'No SKU/code') . ' - ' . $line['material_unit']) ?></div>
                                </td>
                                <td><?= h(format_decimal_quantity($line['system_quantity_at_start']) . ' ' . $line['material_unit']) ?></td>
                                <td><?= h(($line['counted_quantity'] ?? null) !== null ? format_decimal_quantity($line['counted_quantity']) . ' ' . $line['material_unit'] : 'Uncounted') ?></td>
                                <td><?= h($difference !== '—' ? $difference . ' ' . $line['material_unit'] : '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if (!$isReadOnly): ?>
                <div class="d-flex flex-wrap gap-2 mt-4">
                    <button class="btn btn-primary" type="submit" form="<?= h('inventory-save-' . $inventory['id']) ?>">Save Counts</button>
                    <?php if ((string) $inventory['status'] !== 'pending_approval'): ?>
                        <form method="post" action="<?= h(app_url('/materials/inventories/' . $inventory['id'] . '/submit')) ?>" onsubmit="return confirm('Submit this inventory for approval? Every line must be counted.');">
                            <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                            <button class="btn btn-outline-secondary" type="submit">Submit for Approval</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="<?= h(app_url('/materials/inventories/' . $inventory['id'] . '/cancel')) ?>" onsubmit="return confirm('Cancel this inventory?');">
                        <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                        <button class="btn btn-outline-danger" type="submit">Cancel Inventory</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if ((string) $inventory['status'] === 'pending_approval' && user_can_manage_material_inventory($viewer)): ?>
        <section class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                    <div>
                        <p class="text-uppercase text-secondary small fw-semibold mb-2">Approval</p>
                        <h2 class="h5 mb-1">Approve Inventory</h2>
                        <p class="text-secondary mb-0">Approval confirms the counted baseline from the effective timestamp and locks the inventory.</p>
                    </div>
                    <form method="post" action="<?= h(app_url('/materials/inventories/' . $inventory['id'] . '/approve')) ?>" onsubmit="return confirm('Approve this inventory? This will create a new stock baseline and cannot be undone.');">
                        <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                        <button class="btn btn-primary" type="submit">Approve Inventory</button>
                    </form>
                </div>
            </div>
        </section>
    <?php endif; ?>
</div>
