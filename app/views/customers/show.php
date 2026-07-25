<?php

declare(strict_types=1);
?>
<div class="d-grid gap-4">
    <section class="card shadow-sm border-0">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Customer</p>
                    <h1 class="h3 mb-2"><?= h($customer['name']) ?></h1>
                    <p class="text-secondary mb-0">Customer record and linked service locations.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-primary" href="<?= h(app_url('/customers')) ?>">Back to Customers</a>
                    <a class="btn btn-primary" href="<?= h(app_url('/locations/create?customer_id=' . $customer['id'])) ?>">Add Location</a>
                </div>
            </div>
        </div>
    </section>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <div class="info-grid">
                <div>
                    <p class="info-label">Registration</p>
                    <p class="mb-0"><?= h($customer['registration_number'] ?: 'Not provided') ?></p>
                </div>
                <div>
                    <p class="info-label">Status</p>
                    <p class="mb-0">
                        <span class="badge <?= (int) $customer['is_active'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>">
                            <?= (int) $customer['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                        </span>
                    </p>
                </div>
                <div>
                    <p class="info-label">Contact Name</p>
                    <p class="mb-0"><?= h($customer['contact_name'] ?: 'Not provided') ?></p>
                </div>
                <div>
                    <p class="info-label">Contact Email</p>
                    <p class="mb-0"><?= h($customer['contact_email'] ?: 'Not provided') ?></p>
                </div>
                <div>
                    <p class="info-label">Contact Phone</p>
                    <p class="mb-0"><?= h($customer['contact_phone'] ?: 'Not provided') ?></p>
                </div>
                <div>
                    <p class="info-label">Updated</p>
                    <p class="mb-0"><?= h(format_datetime($customer['updated_at'] ?? null)) ?></p>
                </div>
            </div>

            <div class="mt-4">
                <p class="info-label">Notes</p>
                <p class="mb-0"><?= nl2br(h($customer['notes'] ?: 'No notes recorded.')) ?></p>
            </div>
        </div>
    </section>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
                <div>
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Locations</p>
                    <h2 class="h5 mb-1">Customer Locations</h2>
                    <p class="text-secondary mb-0">Locations remain visible even when marked inactive.</p>
                </div>
                <a class="btn btn-outline-primary btn-sm" href="<?= h(app_url('/locations?customer_id=' . $customer['id'])) ?>">View All for Customer</a>
            </div>

            <?php if ($locations === []): ?>
                <p class="text-secondary mb-0">No locations have been added for this customer yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                        <tr>
                            <th scope="col">Location</th>
                            <th scope="col">Address</th>
                            <th scope="col">Contact</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-end">Details</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($locations as $location): ?>
                            <tr>
                                <td class="fw-semibold"><?= h($location['name']) ?></td>
                                <td><?= h(location_address($location) ?: 'Not provided') ?></td>
                                <td><?= h($location['contact_name'] ?: 'Not provided') ?></td>
                                <td>
                                    <span class="badge <?= (int) $location['is_active'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                        <?= (int) $location['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a class="btn btn-outline-primary btn-sm" href="<?= h(app_url('/locations/' . $location['id'])) ?>">View</a>
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
