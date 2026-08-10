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
                    <p class="text-uppercase text-secondary small fw-semibold mb-2"><?= h(translate_literal('Materials')) ?></p>
                    <h1 class="h3 mb-2"><?= h(translate_literal('Materials Catalogue')) ?></h1>
                    <p class="text-secondary mb-0"><?= h(translate_literal('Current stock is calculated per company from approved inventories and later movements.')) ?></p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <?php if (user_can_create_manual_material_movement($viewer)): ?>
                        <a class="btn btn-outline-primary" href="<?= h(app_url('/materials/movements/create')) ?>"><?= h(translate_literal('Add Material Movement')) ?></a>
                    <?php endif; ?>
                    <?php if (user_can_manage_material_inventory($viewer)): ?>
                        <form method="post" action="<?= h(app_url('/materials/inventories/create')) ?>">
                            <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                            <button class="btn btn-outline-secondary" type="submit"><?= h(translate_literal('Start Inventory')) ?></button>
                        </form>
                    <?php endif; ?>
                    <?php if (user_can_manage_materials_catalogue($viewer)): ?>
                        <a class="btn btn-primary" href="<?= h(app_url('/materials/create')) ?>"><?= h(translate_literal('Create Material')) ?></a>
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
            <form method="get" action="<?= h(app_url('/materials')) ?>" class="row g-3 align-items-end">
                <div class="col-12 col-lg-6">
                    <label class="form-label" for="search"><?= h(translate_literal('Search')) ?></label>
                    <input class="form-control" id="search" name="search" type="text" value="<?= h($filters['search'] ?? '') ?>" placeholder="<?= h(translate_literal('Material name or SKU/code')) ?>">
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label" for="status"><?= h(translate_literal('Status')) ?></label>
                    <select class="form-select" id="status" name="status">
                        <option value=""><?= h(translate_literal('All statuses')) ?></option>
                        <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>><?= h(translate_literal('Active')) ?></option>
                        <option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>><?= h(translate_literal('Inactive')) ?></option>
                    </select>
                </div>
                <div class="col-12 d-flex flex-wrap gap-2">
                    <button class="btn btn-primary" type="submit"><?= h(translate_literal('Apply Filters')) ?></button>
                    <a class="btn btn-outline-secondary" href="<?= h(app_url('/materials')) ?>"><?= h(translate_literal('Clear Filters')) ?></a>
                </div>
            </form>
        </div>
    </section>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <?php if ($materials === []): ?>
                <p class="text-secondary mb-0"><?= h(translate_literal('No materials matched the current filters.')) ?></p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                        <tr>
                            <th scope="col"><?= h(translate_literal('Material')) ?></th>
                            <th scope="col"><?= h(translate_literal('SKU/Code')) ?></th>
                            <th scope="col"><?= h(translate_literal('Unit')) ?></th>
                            <th scope="col"><?= h(translate_literal('Status')) ?></th>
                            <th scope="col"><?= h(translate_literal('Current Stock')) ?></th>
                            <th scope="col" class="text-end"><?= h(translate_literal('Actions')) ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($materials as $material): ?>
                            <tr>
                                <td class="fw-semibold"><?= h($material['name']) ?></td>
                                <td>
                                    <div><?= h($material['sku'] ?: translate_literal('Not provided')) ?></div>
                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                        <?php if ((int) ($material['is_device'] ?? 0) === 1): ?>
                                            <span class="badge text-bg-primary"><?= h(translate_literal('Device')) ?></span>
                                        <?php endif; ?>
                                        <?php if ((int) ($material['is_device_accessory'] ?? 0) === 1): ?>
                                            <span class="badge text-bg-info"><?= h(translate_literal('Accessory')) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?= h($material['unit']) ?></td>
                                <td>
                                    <span class="badge <?= (int) $material['is_active'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                        <?= h(translate_literal((int) $material['is_active'] === 1 ? 'Active' : 'Inactive')) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= h(format_decimal_quantity($material['current_stock'])) ?> <?= h($material['unit']) ?></div>
                                    <span class="badge <?= h(material_stock_status_class((string) $material['current_stock'])) ?>">
                                        <?= h(material_stock_label((string) $material['current_stock'])) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex flex-wrap gap-2 justify-content-end">
                                        <a class="btn btn-outline-primary btn-sm" href="<?= h(app_url('/materials/' . $material['id'])) ?>"><?= h(translate_literal('View')) ?></a>
                                        <?php if (user_can_create_manual_material_movement($viewer)): ?>
                                            <a class="btn btn-outline-secondary btn-sm" href="<?= h(app_url('/materials/movements/create?material_id=' . $material['id'])) ?>"><?= h(translate_literal('Move')) ?></a>
                                        <?php endif; ?>
                                        <?php if (user_can_manage_materials_catalogue($viewer)): ?>
                                            <a class="btn btn-outline-secondary btn-sm" href="<?= h(app_url('/materials/' . $material['id'] . '/edit')) ?>"><?= h(translate_literal('Edit')) ?></a>
                                            <form method="post" action="<?= h(app_url('/materials/' . $material['id'] . '/delete')) ?>" onsubmit="return confirm('<?= h(translate_literal('Delete this material? This cannot be undone.')) ?>');">
                                                <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                                                <button class="btn btn-outline-danger btn-sm" type="submit"><?= h(translate_literal('Delete')) ?></button>
                                            </form>
                                        <?php endif; ?>
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
