<?php

declare(strict_types=1);
?>
<div class="d-grid gap-4">
    <section class="card shadow-sm border-0">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Material</p>
                    <h1 class="h3 mb-2"><?= h($material['name']) ?></h1>
                    <p class="text-secondary mb-0"><?= h(($material['sku'] ?? '') !== '' ? $material['sku'] : 'No SKU/code provided') ?></p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-secondary" href="<?= h(app_url('/materials')) ?>">Back to Materials</a>
                    <a class="btn btn-primary" href="<?= h(app_url('/materials/' . $material['id'] . '/edit')) ?>">Edit Material</a>
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
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Status</p>
                    <h2 class="h5 mb-1">Activation</h2>
                    <p class="text-secondary mb-0">Inactive materials remain visible on historical jobs but cannot be selected for new usage.</p>
                </div>
                <form method="post" action="<?= h(app_url('/materials/' . $material['id'] . '/status')) ?>" onsubmit="return confirm('Update this material status?');">
                    <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="is_active" value="<?= (int) $material['is_active'] === 1 ? '0' : '1' ?>">
                    <button class="btn <?= (int) $material['is_active'] === 1 ? 'btn-outline-danger' : 'btn-outline-success' ?>" type="submit">
                        <?= (int) $material['is_active'] === 1 ? 'Deactivate Material' : 'Activate Material' ?>
                    </button>
                </form>
            </div>
        </div>
    </section>
</div>
