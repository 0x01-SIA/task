<?php

declare(strict_types=1);
?>
<div class="d-grid gap-4">
    <section class="card shadow-sm border-0">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Users</p>
                    <h1 class="h3 mb-2"><?= h($formTitle) ?></h1>
                    <p class="text-secondary mb-0">Create or update application access without exposing existing passwords.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <?php if ($userRecord !== null): ?>
                        <a class="btn btn-outline-primary" href="<?= h(app_url('/users/' . $userRecord['id'])) ?>">Back to User</a>
                    <?php endif; ?>
                    <a class="btn btn-outline-secondary" href="<?= h(app_url('/users')) ?>">Back to Users</a>
                </div>
            </div>
        </div>
    </section>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <form
                method="post"
                action="<?= h(app_url($formAction)) ?>"
                class="d-grid gap-4"
                onsubmit="return this.is_active.value !== '0' || confirm('Deactivate this user? They will no longer be able to sign in or appear as an assignable worker.');"
            >
                <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="name">Name</label>
                        <input class="form-control<?= isset($errors['name']) ? ' is-invalid' : '' ?>" id="name" name="name" type="text" value="<?= h($values['name'] ?? '') ?>" required>
                        <?php if (isset($errors['name'])): ?>
                            <div class="invalid-feedback"><?= h($errors['name']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-control<?= isset($errors['email']) ? ' is-invalid' : '' ?>" id="email" name="email" type="email" value="<?= h($values['email'] ?? '') ?>" required>
                        <?php if (isset($errors['email'])): ?>
                            <div class="invalid-feedback"><?= h($errors['email']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="role">Role</label>
                        <select class="form-select<?= isset($errors['role']) ? ' is-invalid' : '' ?>" id="role" name="role" required>
                            <?php foreach (user_role_options() as $value => $label): ?>
                                <option value="<?= h($value) ?>" <?= ($values['role'] ?? 'worker') === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['role'])): ?>
                            <div class="invalid-feedback"><?= h($errors['role']) ?></div>
                        <?php endif; ?>
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

                    <?php if (array_key_exists('password', $values)): ?>
                        <div class="col-md-6">
                            <label class="form-label" for="password">Password</label>
                            <input class="form-control<?= isset($errors['password']) ? ' is-invalid' : '' ?>" id="password" name="password" type="password" required>
                            <?php if (isset($errors['password'])): ?>
                                <div class="invalid-feedback"><?= h($errors['password']) ?></div>
                            <?php endif; ?>
                            <div class="form-text">Minimum <?= h((string) password_min_length()) ?> characters.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="password_confirmation">Confirm Password</label>
                            <input class="form-control<?= isset($errors['password_confirmation']) ? ' is-invalid' : '' ?>" id="password_confirmation" name="password_confirmation" type="password" required>
                            <?php if (isset($errors['password_confirmation'])): ?>
                                <div class="invalid-feedback"><?= h($errors['password_confirmation']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-primary" type="submit"><?= h($submitLabel) ?></button>
                    <a class="btn btn-outline-secondary" href="<?= h(app_url($userRecord !== null ? '/users/' . $userRecord['id'] : '/users')) ?>">Cancel</a>
                </div>
            </form>
        </div>
    </section>
</div>
