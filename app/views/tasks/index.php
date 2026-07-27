<?php

declare(strict_types=1);
?>
<div class="d-grid gap-4">
    <section class="card shadow-sm border-0">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Tasks</p>
                    <h1 class="h3 mb-2">Task Management</h1>
                    <p class="text-secondary mb-0">Track customer requests, due dates, and the jobs planned underneath each task.</p>
                </div>
                <a class="btn btn-primary" href="<?= h(app_url('/tasks/create')) ?>">New Task</a>
            </div>
        </div>
    </section>

    <?php if ($successMessage !== null): ?>
        <div class="alert alert-success mb-0" role="status"><?= h($successMessage) ?></div>
    <?php endif; ?>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <form method="get" action="<?= h(app_url('/tasks')) ?>" class="row g-3 align-items-end">
                <div class="col-12 col-lg-3">
                    <label class="form-label" for="search">Search</label>
                    <input class="form-control" id="search" name="search" type="text" value="<?= h($filters['search'] ?? '') ?>" placeholder="Task number, title, customer, location">
                </div>

                <div class="col-6 col-lg-2">
                    <label class="form-label" for="status">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All statuses</option>
                        <?php foreach (task_status_options() as $value => $label): ?>
                            <option value="<?= h($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-6 col-lg-2">
                    <label class="form-label" for="priority">Priority</label>
                    <select class="form-select" id="priority" name="priority">
                        <option value="">All priorities</option>
                        <?php foreach (task_priority_options() as $value => $label): ?>
                            <option value="<?= h($value) ?>" <?= ($filters['priority'] ?? '') === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-6 col-lg-3">
                    <label class="form-label" for="customer_id">Customer</label>
                    <select class="form-select" id="customer_id" name="customer_id">
                        <option value="">All customers</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?= h($customer['id']) ?>" <?= (int) ($filters['customer_id'] ?? 0) === (int) $customer['id'] ? 'selected' : '' ?>>
                                <?= h($customer['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-6 col-lg-2">
                    <label class="form-label" for="due_state">Due State</label>
                    <select class="form-select" id="due_state" name="due_state">
                        <option value="">All due states</option>
                        <?php foreach (task_due_state_options() as $value => $label): ?>
                            <option value="<?= h($value) ?>" <?= ($filters['due_state'] ?? '') === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 d-flex flex-wrap gap-2">
                    <button class="btn btn-primary" type="submit">Apply Filters</button>
                    <a class="btn btn-outline-secondary" href="<?= h(app_url('/tasks')) ?>">Clear Filters</a>
                </div>
            </form>
        </div>
    </section>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <?php if ($tasks === []): ?>
                <p class="text-secondary mb-0">No tasks matched the current filters.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                        <tr>
                            <th scope="col">Task Number</th>
                            <th scope="col">Title</th>
                            <th scope="col">Customer</th>
                            <th scope="col">Location</th>
                            <th scope="col">Status</th>
                            <th scope="col">Priority</th>
                            <th scope="col">Requested</th>
                            <th scope="col">Due</th>
                            <th scope="col">Jobs</th>
                            <th scope="col">Updated</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($tasks as $task): ?>
                            <?php $dueState = task_due_state($task); ?>
                            <tr>
                                <td class="fw-semibold"><a href="<?= h(app_url('/tasks/' . $task['id'])) ?>"><?= h($task['task_number']) ?></a></td>
                                <td>
                                    <?= h($task['title']) ?>
                                    <?php if ($dueState === 'overdue'): ?>
                                        <span class="badge text-bg-danger ms-2">Overdue</span>
                                    <?php elseif ($dueState === 'due_today'): ?>
                                        <span class="badge text-bg-warning ms-2">Due Today</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= h($task['customer_name']) ?></td>
                                <td><?= h($task['location_name'] ?: 'Not assigned') ?></td>
                                <td><span class="badge <?= h(task_status_badge_class((string) $task['status'])) ?>"><?= h(task_status_label((string) $task['status'])) ?></span></td>
                                <td>
                                    <?= h(task_priority_label((string) $task['priority'])) ?>
                                    <?php if (($task['priority'] ?? '') === 'urgent'): ?>
                                        <span class="badge text-bg-danger ms-2">Urgent</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= h(format_date($task['requested_date'] ?? null)) ?></td>
                                <td><?= h(format_date($task['due_date'] ?? null)) ?></td>
                                <td><?= h((string) ($task['linked_job_count'] ?? 0)) ?></td>
                                <td><?= h(format_datetime($task['updated_at'] ?? null)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
