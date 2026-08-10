<?php

declare(strict_types=1);
?>
<div class="d-grid gap-4">
    <section class="card shadow-sm border-0">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <p class="text-uppercase text-secondary small fw-semibold mb-2"><?= h(translate_literal('Locations')) ?></p>
                    <h1 class="h3 mb-2"><?= h(translate_literal('Customer Locations')) ?></h1>
                    <p class="text-secondary mb-0"><?= h(translate_literal('Manage the physical sites where future field work will be performed.')) ?></p>
                </div>
                <a class="btn btn-primary" href="<?= h(app_url('/locations/create' . ($selectedCustomerId !== null ? '?customer_id=' . $selectedCustomerId : ''))) ?>"><?= h(translate_literal('Add Location')) ?></a>
            </div>
        </div>
    </section>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <?php if (($successMessage ?? null) !== null): ?>
                <div class="alert alert-success mb-4" role="status"><?= h($successMessage) ?></div>
            <?php endif; ?>

            <?php if (($errorMessage ?? null) !== null): ?>
                <div class="alert alert-danger mb-4" role="alert"><?= h($errorMessage) ?></div>
            <?php endif; ?>

            <form method="get" action="<?= h(app_url('/locations')) ?>" class="row g-3 align-items-end">
                <div class="col-md-8 col-lg-6">
                    <label class="form-label" for="customer_id"><?= h(translate_literal('Filter by Customer')) ?></label>
                    <select class="form-select" id="customer_id" name="customer_id">
                        <option value=""><?= h(translate_literal('All customers')) ?></option>
                        <?php foreach ($customers as $customer): ?>
                            <option
                                value="<?= h($customer['id']) ?>"
                                <?= $selectedCustomerId === (int) $customer['id'] ? 'selected' : '' ?>
                            >
                                <?= h($customer['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-primary" type="submit"><?= h(translate_literal('Apply Filter')) ?></button>
                </div>
                <?php if ($selectedCustomerId !== null): ?>
                    <div class="col-auto">
                        <a class="btn btn-outline-secondary" href="<?= h(app_url('/locations')) ?>"><?= h(translate_literal('Clear')) ?></a>
                    </div>
                <?php endif; ?>
            </form>

            <?php if ($selectedCustomer !== null): ?>
                <p class="small text-secondary mt-3 mb-0"><?= h(translate_literal('Showing locations for')) ?> <a href="<?= h(app_url('/customers/' . $selectedCustomer['id'])) ?>"><?= h($selectedCustomer['name']) ?></a>.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <?php if ($locations === []): ?>
                <p class="text-secondary mb-0">
                    <?= h(translate_literal($selectedCustomer !== null
                        ? 'No locations have been added for this customer yet.'
                        : 'No locations have been added yet.')) ?>
                </p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                        <tr>
                            <th scope="col"><?= h(translate_literal('Location')) ?></th>
                            <th scope="col"><?= h(translate_literal('Customer')) ?></th>
                            <th scope="col"><?= h(translate_literal('Address')) ?></th>
                            <th scope="col"><?= h(translate_literal('Contact Name')) ?></th>
                            <th scope="col"><?= h(translate_literal('Contact Phone')) ?></th>
                            <th scope="col"><?= h(translate_literal('Status')) ?></th>
                            <th scope="col" class="text-end"><?= h(translate_literal('Details')) ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($locations as $location): ?>
                            <tr>
                                <td class="fw-semibold"><?= h($location['name']) ?></td>
                                <td>
                                    <a href="<?= h(app_url('/customers/' . $location['customer_id'])) ?>"><?= h($location['customer_name']) ?></a>
                                </td>
                                <td><?= h(location_address($location) ?: translate_literal('Not provided')) ?></td>
                                <td><?= h($location['contact_name'] ?: translate_literal('Not provided')) ?></td>
                                <td><?= h($location['contact_phone'] ?: translate_literal('Not provided')) ?></td>
                                <td>
                                    <span class="badge <?= (int) $location['is_active'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                        <?= h(translate_literal((int) $location['is_active'] === 1 ? 'Active' : 'Inactive')) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a class="btn btn-outline-primary btn-sm" href="<?= h(app_url('/locations/' . $location['id'])) ?>"><?= h(translate_literal('View')) ?></a>
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
