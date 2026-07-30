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
                    <h1 class="h3 mb-2">Add Material Movement</h1>
                    <p class="text-secondary mb-0">Record an incoming or outgoing company-level stock movement.</p>
                </div>
                <a class="btn btn-outline-secondary" href="<?= h(app_url('/materials/movements')) ?>">Back to Movements</a>
            </div>
        </div>
    </section>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <?php if (isset($errors['form'])): ?>
                <div class="alert alert-danger" role="alert"><?= h($errors['form']) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= h(app_url('/materials/movements/create')) ?>" class="row g-3">
                <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                <div class="col-12">
                    <label class="form-label" for="material_id">Material</label>
                    <select class="form-select<?= isset($errors['material_id']) ? ' is-invalid' : '' ?>" id="material_id" name="material_id" required>
                        <option value="">Select a material</option>
                        <?php foreach ($materials as $material): ?>
                            <option value="<?= h($material['id']) ?>" <?= (int) ($values['material_id'] ?? 0) === (int) $material['id'] ? 'selected' : '' ?>>
                                <?= h($material['name'] . ' - ' . format_decimal_quantity($material['current_stock']) . ' ' . $material['unit']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['material_id'])): ?>
                        <div class="invalid-feedback"><?= h($errors['material_id']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label" for="movement_type">Movement Type</label>
                    <select class="form-select<?= isset($errors['movement_type']) ? ' is-invalid' : '' ?>" id="movement_type" name="movement_type" required>
                        <option value="in" <?= ($values['movement_type'] ?? '') === 'in' ? 'selected' : '' ?>>Material In</option>
                        <option value="out" <?= ($values['movement_type'] ?? '') === 'out' ? 'selected' : '' ?>>Material Out</option>
                    </select>
                    <?php if (isset($errors['movement_type'])): ?>
                        <div class="invalid-feedback"><?= h($errors['movement_type']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label" for="quantity">Quantity</label>
                    <input class="form-control<?= isset($errors['quantity']) ? ' is-invalid' : '' ?>" id="quantity" name="quantity" type="text" value="<?= h($values['quantity'] ?? '') ?>" inputmode="decimal" placeholder="0.000" required>
                    <?php if (isset($errors['quantity'])): ?>
                        <div class="invalid-feedback"><?= h($errors['quantity']) ?></div>
                    <?php endif; ?>
                </div>

                <?php if (user_can_set_material_movement_datetime($viewer)): ?>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="occurred_at">Movement Date and Time</label>
                        <input class="form-control<?= isset($errors['occurred_at']) ? ' is-invalid' : '' ?>" id="occurred_at" name="occurred_at" type="datetime-local" value="<?= h($values['occurred_at'] ?? '') ?>">
                        <?php if (isset($errors['occurred_at'])): ?>
                            <div class="invalid-feedback"><?= h($errors['occurred_at']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="col-12">
                    <label class="form-label" for="note">Note</label>
                    <textarea class="form-control" id="note" name="note" rows="3"><?= h($values['note'] ?? '') ?></textarea>
                </div>

                <div class="col-12 d-flex flex-wrap gap-2">
                    <button class="btn btn-primary" type="submit">Record Movement</button>
                    <a class="btn btn-outline-secondary" href="<?= h(app_url('/materials/movements')) ?>">Cancel</a>
                </div>
            </form>
        </div>
    </section>
</div>
