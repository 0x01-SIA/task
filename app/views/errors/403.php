<?php

declare(strict_types=1);
?>
<div class="card shadow-sm border-0">
    <div class="card-body p-4 p-md-5">
        <p class="text-uppercase text-secondary small fw-semibold mb-2">Forbidden</p>
        <h1 class="h3 mb-3">Access denied</h1>
        <p class="text-secondary mb-4">Your account is signed in, but it does not have permission to view this page.</p>
        <div class="d-flex flex-wrap align-items-center gap-3">
            <span class="badge text-bg-secondary">HTTP 403</span>
            <a class="btn btn-outline-primary btn-sm" href="<?= htmlspecialchars(app_url(home_path_for_user()), ENT_QUOTES, 'UTF-8') ?>">Back to home</a>
        </div>
    </div>
</div>
