<?php

declare(strict_types=1);

$cancelPath = '/customers';

if (($returnTo ?? null) !== null) {
    $returnRoutes = customer_return_route_map();
    $returnPath = $returnRoutes[$returnTo] ?? null;

    if (is_string($returnPath)) {
        $cancelPath = $returnPath . customer_creation_return_query($returnTo, $returnState ?? null);
    }
}
?>
<div class="d-grid gap-4">
    <section class="card shadow-sm border-0">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Customers</p>
                    <h1 class="h3 mb-2"><?= h($formTitle) ?></h1>
                    <p class="text-secondary mb-0">Capture the customer account details used for tasks, jobs, and service locations.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-secondary" href="<?= h(app_url($cancelPath)) ?>">
                        <?= ($returnTo ?? null) !== null ? 'Back to Form' : 'Back to Customers' ?>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <form method="post" action="<?= h(app_url($formAction)) ?>" class="d-grid gap-4">
                <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                <?php if (($returnTo ?? null) !== null): ?>
                    <input type="hidden" name="return_to" value="<?= h((string) $returnTo) ?>">
                <?php endif; ?>
                <?php if (($returnState ?? null) !== null && $returnState !== ''): ?>
                    <input type="hidden" name="return_state" value="<?= h((string) $returnState) ?>">
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label" for="name">Customer Name</label>
                        <input class="form-control<?= isset($errors['name']) ? ' is-invalid' : '' ?>" id="name" name="name" type="text" value="<?= h($values['name'] ?? '') ?>" maxlength="255" required>
                        <?php if (isset($errors['name'])): ?>
                            <div class="invalid-feedback"><?= h($errors['name']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="registration_number">Registration Number</label>
                        <input class="form-control<?= isset($errors['registration_number']) ? ' is-invalid' : '' ?>" id="registration_number" name="registration_number" type="text" value="<?= h($values['registration_number'] ?? '') ?>" maxlength="100">
                        <?php if (isset($errors['registration_number'])): ?>
                            <div class="invalid-feedback"><?= h($errors['registration_number']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="contact_name">Contact Name</label>
                        <input class="form-control<?= isset($errors['contact_name']) ? ' is-invalid' : '' ?>" id="contact_name" name="contact_name" type="text" value="<?= h($values['contact_name'] ?? '') ?>" maxlength="255">
                        <?php if (isset($errors['contact_name'])): ?>
                            <div class="invalid-feedback"><?= h($errors['contact_name']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="contact_email">Contact Email</label>
                        <input class="form-control<?= isset($errors['contact_email']) ? ' is-invalid' : '' ?>" id="contact_email" name="contact_email" type="email" value="<?= h($values['contact_email'] ?? '') ?>" maxlength="255">
                        <?php if (isset($errors['contact_email'])): ?>
                            <div class="invalid-feedback"><?= h($errors['contact_email']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="contact_phone">Contact Phone</label>
                        <input class="form-control<?= isset($errors['contact_phone']) ? ' is-invalid' : '' ?>" id="contact_phone" name="contact_phone" type="text" value="<?= h($values['contact_phone'] ?? '') ?>" maxlength="50">
                        <?php if (isset($errors['contact_phone'])): ?>
                            <div class="invalid-feedback"><?= h($errors['contact_phone']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="is_active">Status</label>
                        <select class="form-select" id="is_active" name="is_active">
                            <option value="1" <?= ($values['is_active'] ?? '1') === '1' ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= ($values['is_active'] ?? '1') === '0' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="notes">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="4"><?= h($values['notes'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-primary" type="submit"><?= h($submitLabel) ?></button>
                    <a class="btn btn-outline-secondary" href="<?= h(app_url($cancelPath)) ?>">Cancel</a>
                </div>
            </form>
        </div>
    </section>
</div>
