<?php

declare(strict_types=1);
?>
<div class="d-grid gap-4">
    <section class="card shadow-sm border-0">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Locations</p>
                    <h1 class="h3 mb-2"><?= h($formTitle) ?></h1>
                    <p class="text-secondary mb-0">Capture the site details that field work will be linked to later.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <?php if ($location !== null): ?>
                        <a class="btn btn-outline-primary" href="<?= h(app_url('/locations/' . $location['id'])) ?>">Back to Location</a>
                    <?php endif; ?>
                    <a class="btn btn-outline-secondary" href="<?= h(app_url('/locations')) ?>">Back to Locations</a>
                </div>
            </div>
        </div>
    </section>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <?php if ($customers === []): ?>
                <p class="text-secondary mb-0">Add a customer before creating locations.</p>
            <?php else: ?>
                <form method="post" action="<?= h(app_url($formAction)) ?>" class="d-grid gap-4">
                    <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">

                    <?php if (isset($errors['form'])): ?>
                        <div class="alert alert-danger mb-0" role="alert"><?= h($errors['form']) ?></div>
                    <?php endif; ?>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="customer_id">Customer</label>
                            <select
                                class="form-select<?= isset($errors['customer_id']) ? ' is-invalid' : '' ?>"
                                id="customer_id"
                                name="customer_id"
                                required
                            >
                                <option value="">Select a customer</option>
                                <?php foreach ($customers as $customer): ?>
                                    <option
                                        value="<?= h($customer['id']) ?>"
                                        <?= (int) ($values['customer_id'] ?? 0) === (int) $customer['id'] ? 'selected' : '' ?>
                                    >
                                        <?= h($customer['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['customer_id'])): ?>
                                <div class="invalid-feedback"><?= h($errors['customer_id']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="name">Location Name</label>
                            <input
                                class="form-control<?= isset($errors['name']) ? ' is-invalid' : '' ?>"
                                id="name"
                                name="name"
                                type="text"
                                value="<?= h($values['name'] ?? '') ?>"
                                required
                            >
                            <?php if (isset($errors['name'])): ?>
                                <div class="invalid-feedback"><?= h($errors['name']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="address">Address</label>
                            <textarea
                                class="form-control<?= isset($errors['address']) ? ' is-invalid' : '' ?>"
                                id="address"
                                name="address"
                                rows="3"
                                required
                            ><?= h($values['address'] ?? '') ?></textarea>
                            <?php if (isset($errors['address'])): ?>
                                <div class="invalid-feedback"><?= h($errors['address']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="contact_name">Contact Name</label>
                            <input class="form-control" id="contact_name" name="contact_name" type="text" value="<?= h($values['contact_name'] ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="contact_phone">Contact Phone</label>
                            <input class="form-control" id="contact_phone" name="contact_phone" type="text" value="<?= h($values['contact_phone'] ?? '') ?>">
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="access_notes">Access Notes</label>
                            <textarea class="form-control" id="access_notes" name="access_notes" rows="4"><?= h($values['access_notes'] ?? '') ?></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="is_active">Status</label>
                            <select class="form-select" id="is_active" name="is_active">
                                <option value="1" <?= ($values['is_active'] ?? '1') === '1' ? 'selected' : '' ?>>Active</option>
                                <option value="0" <?= ($values['is_active'] ?? '1') === '0' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-primary" type="submit"><?= h($submitLabel) ?></button>
                        <a class="btn btn-outline-secondary" href="<?= h(app_url($location !== null ? '/locations/' . $location['id'] : '/locations')) ?>">Cancel</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </section>
</div>
