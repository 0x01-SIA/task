<?php

declare(strict_types=1);
?>
<div class="d-grid gap-4">
    <section class="card shadow-sm border-0">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Materials</p>
                    <h1 class="h3 mb-2"><?= h($formTitle) ?></h1>
                    <p class="text-secondary mb-0">Keep material names, short codes, units and status aligned for job recording.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <?php if ($material !== null): ?>
                        <a class="btn btn-outline-primary" href="<?= h(app_url('/materials/' . $material['id'])) ?>">Back to Material</a>
                    <?php endif; ?>
                    <a class="btn btn-outline-secondary" href="<?= h(app_url('/materials')) ?>">Back to Materials</a>
                </div>
            </div>
        </div>
    </section>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <form method="post" action="<?= h(app_url($formAction)) ?>" class="d-grid gap-4">
                <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">

                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label" for="name">Name</label>
                        <input class="form-control<?= isset($errors['name']) ? ' is-invalid' : '' ?>" id="name" name="name" type="text" value="<?= h($values['name'] ?? '') ?>" required>
                        <?php if (isset($errors['name'])): ?>
                            <div class="invalid-feedback"><?= h($errors['name']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="unit">Unit</label>
                        <input class="form-control<?= isset($errors['unit']) ? ' is-invalid' : '' ?>" id="unit" name="unit" type="text" value="<?= h($values['unit'] ?? '') ?>" maxlength="50" required>
                        <?php if (isset($errors['unit'])): ?>
                            <div class="invalid-feedback"><?= h($errors['unit']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="sku">SKU or Code</label>
                        <input class="form-control" id="sku" name="sku" type="text" value="<?= h($values['sku'] ?? '') ?>" maxlength="100">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="is_active">Status</label>
                        <select class="form-select" id="is_active" name="is_active">
                            <option value="1" <?= ($values['is_active'] ?? '1') === '1' ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= ($values['is_active'] ?? '1') === '0' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="4"><?= h($values['description'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-primary" type="submit"><?= h($submitLabel) ?></button>
                    <a class="btn btn-outline-secondary" href="<?= h(app_url($material !== null ? '/materials/' . $material['id'] : '/materials')) ?>">Cancel</a>
                </div>
            </form>
        </div>
    </section>
</div>
