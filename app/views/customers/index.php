<?php

declare(strict_types=1);
?>
<div class="d-grid gap-4">
    <section class="card shadow-sm border-0">
        <div class="card-body p-4 p-lg-5">
            <p class="text-uppercase text-secondary small fw-semibold mb-2">Customers</p>
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <h1 class="h3 mb-2">Customer Directory</h1>
                    <p class="text-secondary mb-0">Open a customer to review account details and managed service locations.</p>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="badge text-bg-light border"><?= h((string) count($customers)) ?> customers</span>
                    <a class="btn btn-primary" href="<?= h(app_url('/customers/create')) ?>">New Customer</a>
                </div>
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

            <?php if ($customers === []): ?>
                <p class="text-secondary mb-0">No customers have been added yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                        <tr>
                            <th scope="col">Customer</th>
                            <th scope="col">Primary Contact</th>
                            <th scope="col">Phone</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-end">Details</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($customers as $customer): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= h($customer['name']) ?></div>
                                    <?php if ((string) ($customer['registration_number'] ?? '') !== ''): ?>
                                        <div class="small text-secondary"><?= h($customer['registration_number']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= h($customer['contact_name'] ?: 'Not provided') ?></td>
                                <td><?= h($customer['contact_phone'] ?: 'Not provided') ?></td>
                                <td>
                                    <span class="badge <?= (int) $customer['is_active'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                        <?= (int) $customer['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a class="btn btn-outline-primary btn-sm" href="<?= h(app_url('/customers/' . $customer['id'])) ?>">View</a>
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
