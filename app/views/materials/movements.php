<?php

declare(strict_types=1);

$movementBaseQuery = array_filter([
    'movement_type' => $filters['movement_type'] ?? '',
    'movement_source' => $filters['movement_source'] ?? '',
    'user_id' => $filters['user_id'] ?? null,
    'date_from' => $filters['date_from'] ?? '',
    'date_to' => $filters['date_to'] ?? '',
    'limit' => $movementLimit,
]);
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
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Stock</p>
                    <h1 class="h3 mb-2">Material Movements</h1>
                    <p class="text-secondary mb-0">Company-wide movement history across manual corrections and job usage.</p>
                </div>
                <?php if (user_can_create_manual_material_movement($viewer)): ?>
                    <a class="btn btn-primary" href="<?= h(app_url('/materials/movements/create')) ?>">Add Material Movement</a>
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
            <form method="get" action="<?= h(app_url('/materials/movements')) ?>" class="row g-3 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label" for="movement_type">Movement Type</label>
                    <select class="form-select" id="movement_type" name="movement_type">
                        <option value="">All types</option>
                        <option value="in" <?= ($filters['movement_type'] ?? '') === 'in' ? 'selected' : '' ?>>Material In</option>
                        <option value="out" <?= ($filters['movement_type'] ?? '') === 'out' ? 'selected' : '' ?>>Material Out</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label" for="movement_source">Source</label>
                    <select class="form-select" id="movement_source" name="movement_source">
                        <option value="">All sources</option>
                        <option value="manual" <?= ($filters['movement_source'] ?? '') === 'manual' ? 'selected' : '' ?>>Manual</option>
                        <option value="job" <?= ($filters['movement_source'] ?? '') === 'job' ? 'selected' : '' ?>>Job-linked</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label" for="date_from">Date From</label>
                    <input class="form-control" id="date_from" name="date_from" type="date" value="<?= h($filters['date_from'] ?? '') ?>">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label" for="date_to">Date To</label>
                    <input class="form-control" id="date_to" name="date_to" type="date" value="<?= h($filters['date_to'] ?? '') ?>">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label" for="limit">Page Size</label>
                    <select class="form-select" id="limit" name="limit">
                        <?php foreach ([10, 50, 100] as $limitOption): ?>
                            <option value="<?= h((string) $limitOption) ?>" <?= $movementLimit === $limitOption ? 'selected' : '' ?>><?= h((string) $limitOption) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 d-flex flex-wrap gap-2">
                    <button class="btn btn-primary" type="submit">Apply Filters</button>
                    <a class="btn btn-outline-secondary" href="<?= h(app_url('/materials/movements')) ?>">Clear Filters</a>
                </div>
            </form>
        </div>
    </section>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <?php if ($movements === []): ?>
                <p class="text-secondary mb-0">No material movements matched the current filters.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                        <tr>
                            <th scope="col">Date and Time</th>
                            <th scope="col">Material</th>
                            <th scope="col">Type</th>
                            <th scope="col">Quantity</th>
                            <th scope="col">Job</th>
                            <th scope="col">Note</th>
                            <th scope="col">Created By</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($movements as $movement): ?>
                            <tr>
                                <td><?= h(format_datetime($movement['occurred_at'] ?? null)) ?></td>
                                <td>
                                    <a href="<?= h(app_url('/materials/' . $movement['material_id'])) ?>"><?= h($movement['material_name']) ?></a>
                                    <div class="small text-secondary"><?= h(($movement['material_sku'] ?: 'No SKU/code') . ' - ' . $movement['material_unit']) ?></div>
                                </td>
                                <td><?= h(material_movement_type_label((string) $movement['movement_type'])) ?></td>
                                <td><?= h(format_decimal_quantity($movement['quantity']) . ' ' . $movement['material_unit']) ?></td>
                                <td>
                                    <?php if (($movement['job_id'] ?? null) !== null): ?>
                                        <a href="<?= h(app_url($jobPathBase . $movement['job_id'])) ?>"><?= h($movement['job_number']) ?></a>
                                    <?php else: ?>
                                        Manual
                                    <?php endif; ?>
                                </td>
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
                            <a class="btn btn-outline-secondary btn-sm" href="<?= h(app_url('/materials/movements?' . http_build_query($movementBaseQuery + ['page' => $movementPage - 1]))) ?>">Previous</a>
                        <?php else: ?>
                            <span class="btn btn-outline-secondary btn-sm disabled" aria-disabled="true">Previous</span>
                        <?php endif; ?>

                        <span class="text-secondary small">Page <?= h((string) $movementPage) ?> of <?= h((string) $movementLastPage) ?></span>

                        <?php if ($movementPage < $movementLastPage): ?>
                            <a class="btn btn-outline-secondary btn-sm" href="<?= h(app_url('/materials/movements?' . http_build_query($movementBaseQuery + ['page' => $movementPage + 1]))) ?>">Next</a>
                        <?php else: ?>
                            <span class="btn btn-outline-secondary btn-sm disabled" aria-disabled="true">Next</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
</div>
