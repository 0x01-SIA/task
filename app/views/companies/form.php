<?php

declare(strict_types=1);
?>
<div class="d-grid gap-4">
    <section class="card shadow-sm border-0">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Companies</p>
                    <h1 class="h3 mb-2"><?= h($formTitle) ?></h1>
                    <p class="text-secondary mb-0">Maintain the company directory used for access control and operational scoping.</p>
                </div>
                <div class="d-flex gap-2">
                    <?php if ($companyRecord !== null): ?>
                        <a class="btn btn-outline-primary" href="<?= h(app_url('/companies/' . $companyRecord['id'])) ?>">Back to Company</a>
                    <?php endif; ?>
                    <a class="btn btn-outline-secondary" href="<?= h(app_url('/companies')) ?>">Back to Companies</a>
                </div>
            </div>
        </div>
    </section>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <form method="post" action="<?= h(app_url($formAction)) ?>" class="d-grid gap-4">
                <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="name">Company Name</label>
                        <input class="form-control<?= isset($errors['name']) ? ' is-invalid' : '' ?>" id="name" name="name" type="text" value="<?= h($values['name'] ?? '') ?>" required>
                        <?php if (isset($errors['name'])): ?>
                            <div class="invalid-feedback"><?= h($errors['name']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="slug">Slug</label>
                        <input class="form-control<?= isset($errors['slug']) ? ' is-invalid' : '' ?>" id="slug" name="slug" type="text" value="<?= h($values['slug'] ?? '') ?>" required>
                        <?php if (isset($errors['slug'])): ?>
                            <div class="invalid-feedback"><?= h($errors['slug']) ?></div>
                        <?php endif; ?>
                        <div class="form-text">Stable internal identifier, for example `northwind-services`.</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="registration_number">Registration Number</label>
                        <input class="form-control" id="registration_number" name="registration_number" type="text" value="<?= h($values['registration_number'] ?? '') ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-control<?= isset($errors['email']) ? ' is-invalid' : '' ?>" id="email" name="email" type="email" value="<?= h($values['email'] ?? '') ?>">
                        <?php if (isset($errors['email'])): ?>
                            <div class="invalid-feedback"><?= h($errors['email']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="phone">Phone</label>
                        <input class="form-control" id="phone" name="phone" type="text" value="<?= h($values['phone'] ?? '') ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="is_active">Status</label>
                        <select class="form-select<?= isset($errors['is_active']) ? ' is-invalid' : '' ?>" id="is_active" name="is_active">
                            <option value="1" <?= ($values['is_active'] ?? '1') === '1' ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= ($values['is_active'] ?? '1') === '0' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                        <?php if (isset($errors['is_active'])): ?>
                            <div class="invalid-feedback"><?= h($errors['is_active']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="address">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="3"><?= h($values['address'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button class="btn btn-primary" type="submit"><?= h($submitLabel) ?></button>
                    <a class="btn btn-outline-secondary" href="<?= h(app_url($companyRecord !== null ? '/companies/' . $companyRecord['id'] : '/companies')) ?>">Cancel</a>
                </div>
            </form>
        </div>
    </section>
</div>
