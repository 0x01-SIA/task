<?php

declare(strict_types=1);

$newCustomerUrl = app_url('/customers/create?' . http_build_query([
    'return_to' => 'tasks_create',
    'return_state' => encode_customer_return_state(task_inline_customer_return_state($values)),
]));
?>
<div class="d-grid gap-4">
    <section class="card shadow-sm border-0">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Tasks</p>
                    <h1 class="h3 mb-2"><?= h($formTitle) ?></h1>
                    <p class="text-secondary mb-0">Capture the customer request, planning dates, and overall operational priority.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <?php if ($task !== null): ?>
                        <a class="btn btn-outline-primary" href="<?= h(app_url('/tasks/' . $task['id'])) ?>">Back to Task</a>
                    <?php endif; ?>
                    <a class="btn btn-outline-secondary" href="<?= h(app_url('/tasks')) ?>">Back to Tasks</a>
                </div>
            </div>
        </div>
    </section>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <form method="post" action="<?= h(app_url($formAction)) ?>" class="d-grid gap-4">
                <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">

                <?php if (isset($errors['task_number'])): ?>
                    <div class="alert alert-danger mb-0" role="alert"><?= h($errors['task_number']) ?></div>
                <?php endif; ?>

                <?php if (isset($errors['form'])): ?>
                    <div class="alert alert-danger mb-0" role="alert"><?= h($errors['form']) ?></div>
                <?php endif; ?>

                <?php if (($successMessage ?? null) !== null): ?>
                    <div class="alert alert-success mb-0" role="status"><?= h($successMessage) ?></div>
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="customer_id">Customer</label>
                        <select class="form-select<?= isset($errors['customer_id']) ? ' is-invalid' : '' ?>" id="customer_id" name="customer_id" data-customer-location-filter="customer" required>
                            <option value="">Select a customer</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?= h($customer['id']) ?>" <?= (int) ($values['customer_id'] ?? 0) === (int) $customer['id'] ? 'selected' : '' ?>>
                                    <?= h($customer['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['customer_id'])): ?>
                            <div class="invalid-feedback"><?= h($errors['customer_id']) ?></div>
                        <?php endif; ?>
                        <div class="form-text d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <span>Choose an existing customer or add a new one without losing this form.</span>
                            <a class="btn btn-outline-secondary btn-sm" href="<?= h($newCustomerUrl) ?>">New Customer</a>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="location_id">Location</label>
                        <select class="form-select<?= isset($errors['location_id']) ? ' is-invalid' : '' ?>" id="location_id" name="location_id" data-customer-location-filter="location" data-location-catalog="<?= h((string) json_encode($locationCatalog ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>">
                            <option value="">No specific location</option>
                            <?php foreach ($locations as $location): ?>
                                <option value="<?= h($location['id']) ?>" data-customer-id="<?= h($location['customer_id']) ?>" <?= (int) ($values['location_id'] ?? 0) === (int) $location['id'] ? 'selected' : '' ?>>
                                    <?= h($location['name']) ?><?= $location['address_line'] ? ' - ' . h($location['address_line']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['location_id'])): ?>
                            <div class="invalid-feedback"><?= h($errors['location_id']) ?></div>
                        <?php endif; ?>
                        <div class="form-text">Only locations belonging to the selected customer remain visible.</div>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="title">Title</label>
                        <input class="form-control<?= isset($errors['title']) ? ' is-invalid' : '' ?>" id="title" name="title" type="text" value="<?= h($values['title'] ?? '') ?>" required>
                        <?php if (isset($errors['title'])): ?>
                            <div class="invalid-feedback"><?= h($errors['title']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="4"><?= h($values['description'] ?? '') ?></textarea>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select<?= isset($errors['status']) ? ' is-invalid' : '' ?>" id="status" name="status" required>
                            <?php foreach (task_status_options() as $value => $label): ?>
                                <option value="<?= h($value) ?>" <?= ($values['status'] ?? 'new') === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['status'])): ?>
                            <div class="invalid-feedback"><?= h($errors['status']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="priority">Priority</label>
                        <select class="form-select<?= isset($errors['priority']) ? ' is-invalid' : '' ?>" id="priority" name="priority" required>
                            <?php foreach (task_priority_options() as $value => $label): ?>
                                <option value="<?= h($value) ?>" <?= ($values['priority'] ?? 'normal') === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['priority'])): ?>
                            <div class="invalid-feedback"><?= h($errors['priority']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="requested_date">Requested Date</label>
                        <input class="form-control<?= isset($errors['requested_date']) ? ' is-invalid' : '' ?>" id="requested_date" name="requested_date" type="date" value="<?= h($values['requested_date'] ?? '') ?>">
                        <?php if (isset($errors['requested_date'])): ?>
                            <div class="invalid-feedback"><?= h($errors['requested_date']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="due_date">Due Date</label>
                        <input class="form-control<?= isset($errors['due_date']) ? ' is-invalid' : '' ?>" id="due_date" name="due_date" type="date" value="<?= h($values['due_date'] ?? '') ?>">
                        <?php if (isset($errors['due_date'])): ?>
                            <div class="invalid-feedback"><?= h($errors['due_date']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-primary" type="submit"><?= h($submitLabel) ?></button>
                    <a class="btn btn-outline-secondary" href="<?= h(app_url($task !== null ? '/tasks/' . $task['id'] : '/tasks')) ?>">Cancel</a>
                </div>
            </form>
        </div>
    </section>
</div>
