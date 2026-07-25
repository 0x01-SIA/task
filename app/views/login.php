<?php

declare(strict_types=1);
?>
<div class="row justify-content-center">
    <div class="col-lg-5 col-md-7">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 p-md-5">
                <p class="text-uppercase text-secondary small fw-semibold mb-2">Authentication</p>
                <h1 class="h3 mb-3">Sign in</h1>
                <p class="text-secondary mb-4">Use a seeded development account to access the authenticated application shell.</p>

                <?php if (!empty($errorMessage)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?= htmlspecialchars((string) $errorMessage, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= htmlspecialchars(app_url('/login'), ENT_QUOTES, 'UTF-8') ?>" class="d-grid gap-3">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                    <div>
                        <label class="form-label" for="email">Email</label>
                        <input
                            class="form-control"
                            id="email"
                            name="email"
                            type="email"
                            value="<?= htmlspecialchars((string) ($email ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            autocomplete="email"
                            required
                        >
                    </div>

                    <div>
                        <label class="form-label" for="password">Password</label>
                        <input
                            class="form-control"
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="current-password"
                            required
                        >
                    </div>

                    <button class="btn btn-primary" type="submit">Log in</button>
                </form>
            </div>
        </div>
    </div>
</div>
