<?php

declare(strict_types=1);

$taskStatusOptions = task_status_options();
?>
<div class="d-grid gap-4">
    <section class="card shadow-sm border-0">
        <div class="card-body p-4 p-lg-5">
            <div class="responsive-page-header">
                <div>
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Tasks</p>
                    <h1 class="h3 mb-2">Task Management</h1>
                    <p class="text-secondary mb-0">Track customer requests, due dates, and the jobs planned underneath each task.</p>
                </div>
                <div class="responsive-page-header__actions">
                    <a class="btn btn-primary" href="<?= h(app_url('/tasks/create')) ?>">New Task</a>
                </div>
            </div>
        </div>
    </section>

    <?php if ($successMessage !== null): ?>
        <div class="alert alert-success mb-0" role="status"><?= h($successMessage) ?></div>
    <?php endif; ?>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <form method="get" action="<?= h(app_url('/tasks')) ?>" class="row g-3 align-items-end responsive-index-toolbar">
                <div class="col-12 col-lg-3">
                    <label class="form-label" for="search">Search</label>
                    <input class="form-control" id="search" name="search" type="text" value="<?= h($filters['search'] ?? '') ?>" placeholder="Task number, title, customer, location">
                </div>

                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label" for="status">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All statuses</option>
                        <?php foreach (task_status_options() as $value => $label): ?>
                            <option value="<?= h($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label" for="priority">Priority</label>
                    <select class="form-select" id="priority" name="priority">
                        <option value="">All priorities</option>
                        <?php foreach (task_priority_options() as $value => $label): ?>
                            <option value="<?= h($value) ?>" <?= ($filters['priority'] ?? '') === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-sm-6 col-lg-3">
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

                <div class="col-12 col-sm-6 col-lg-2">
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

                <div class="col-12">
                    <div class="responsive-status-chips" aria-label="Task status quick filters">
                        <a class="responsive-status-chips__chip<?= ($filters['status'] ?? '') === '' ? ' active' : '' ?>" href="<?= h(app_url('/tasks')) ?>">All</a>
                        <?php foreach ($taskStatusOptions as $value => $label): ?>
                            <?php
                            $query = array_filter([
                                'search' => $filters['search'] ?? '',
                                'status' => $value,
                                'priority' => $filters['priority'] ?? '',
                                'customer_id' => $filters['customer_id'] ?? '',
                                'due_state' => $filters['due_state'] ?? '',
                            ], static fn ($item): bool => $item !== '' && $item !== null);
                            ?>
                            <a
                                class="responsive-status-chips__chip<?= ($filters['status'] ?? '') === $value ? ' active' : '' ?>"
                                href="<?= h(app_url('/tasks?' . http_build_query($query))) ?>"
                            >
                                <?= h($label) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <?php if ($tasks === []): ?>
                <p class="text-secondary mb-0">No tasks matched the current filters.</p>
            <?php else: ?>
                <div class="table-responsive desktop-table">
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

                <div class="mobile-card-list" aria-label="Tasks">
                    <?php foreach ($tasks as $task): ?>
                        <?php $dueState = task_due_state($task); ?>
                        <a class="mobile-card-link" href="<?= h(app_url('/tasks/' . $task['id'])) ?>">
                            <div class="mobile-card__top">
                                <div>
                                    <div class="mobile-card__eyebrow">Task <?= h($task['task_number']) ?></div>
                                    <h2 class="mobile-card__title"><?= h($task['title']) ?></h2>
                                </div>
                                <span class="badge <?= h(task_status_badge_class((string) $task['status'])) ?>">
                                    <?= h(task_status_label((string) $task['status'])) ?>
                                </span>
                            </div>

                            <div class="mobile-card__meta">
                                <div class="mobile-card__meta-item">
                                    <span class="mobile-card__meta-label">Customer</span>
                                    <span class="mobile-card__meta-value"><?= h($task['customer_name']) ?></span>
                                </div>
                                <div class="mobile-card__meta-item">
                                    <span class="mobile-card__meta-label">Location</span>
                                    <span class="mobile-card__meta-value"><?= h($task['location_name'] ?: 'Not assigned') ?></span>
                                </div>
                                <div class="mobile-card__meta-item">
                                    <span class="mobile-card__meta-label">Due</span>
                                    <span class="mobile-card__meta-value"><?= h(format_date($task['due_date'] ?? null) ?: 'No due date') ?></span>
                                </div>
                                <div class="mobile-card__meta-item">
                                    <span class="mobile-card__meta-label">Priority</span>
                                    <span class="mobile-card__meta-value"><?= h(task_priority_label((string) $task['priority'])) ?></span>
                                </div>
                            </div>

                            <div class="mobile-card__flag-row">
                                <?php if ($dueState === 'overdue'): ?>
                                    <span class="mobile-card__flag mobile-card__flag--danger">Overdue</span>
                                <?php elseif ($dueState === 'due_today'): ?>
                                    <span class="mobile-card__flag">Due today</span>
                                <?php endif; ?>
                                <?php if (($task['priority'] ?? '') === 'urgent'): ?>
                                    <span class="mobile-card__flag mobile-card__flag--danger">Urgent</span>
                                <?php endif; ?>
                                <?php if ((int) ($task['linked_job_count'] ?? 0) > 0): ?>
                                    <span class="mobile-card__flag"><?= h((string) $task['linked_job_count']) ?> linked jobs</span>
                                <?php endif; ?>
                            </div>

                            <span class="mobile-card__cta">Open task <span aria-hidden="true">›</span></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
