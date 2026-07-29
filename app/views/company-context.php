<?php

declare(strict_types=1);
?>
<div class="row justify-content-center">
    <div class="col-lg-6">
        <section class="card shadow-sm border-0">
            <div class="card-body p-4 p-lg-5">
                <p class="text-uppercase text-secondary small fw-semibold mb-2">Company Context</p>
                <h1 class="h3 mb-2">Choose a company</h1>
                <p class="text-secondary mb-4">Select the company you want to work in for this session. Super admins can also stay in an all-company view.</p>

                <?php if ($errorMessage !== null): ?>
                    <div class="alert alert-danger" role="alert"><?= h($errorMessage) ?></div>
                <?php endif; ?>

                <form method="post" action="<?= h(app_url('/company-context')) ?>" class="d-grid gap-3">
                    <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                    <div>
                        <label class="form-label" for="company_id">Active company</label>
                        <select class="form-select" id="company_id" name="company_id" required>
                            <?php foreach ($options as $option): ?>
                                <option value="<?= h((string) $option['id']) ?>" <?= (string) $selectedValue === (string) $option['id'] ? 'selected' : '' ?>>
                                    <?= h($option['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-primary" type="submit">Use This Company</button>
                        <a class="btn btn-outline-secondary" href="<?= h(app_url('/dashboard')) ?>">Back</a>
                    </div>
                </form>
            </div>
        </section>
    </div>
</div>
