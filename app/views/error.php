<?php

declare(strict_types=1);
?>
<div class="card shadow-sm border-0">
    <div class="card-body p-4 p-md-5">
        <p class="text-uppercase text-secondary small fw-semibold mb-2">Error</p>
        <h1 class="h3 mb-3"><?= htmlspecialchars($heading ?? 'Something went wrong', ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-secondary mb-4"><?= htmlspecialchars($message ?? 'An unexpected error occurred.', ENT_QUOTES, 'UTF-8') ?></p>
        <div class="d-flex flex-wrap align-items-center gap-3">
            <span class="badge text-bg-secondary">HTTP <?= (int) ($statusCode ?? 500) ?></span>
            <a class="btn btn-outline-primary btn-sm" href="<?= htmlspecialchars(app_url('/'), ENT_QUOTES, 'UTF-8') ?>">Back to home</a>
        </div>

        <?php if (!empty($details) && is_debug()): ?>
            <hr class="my-4">
            <pre class="debug-block mb-0"><?= htmlspecialchars((string) $details, ENT_QUOTES, 'UTF-8') ?></pre>
        <?php endif; ?>
    </div>
</div>
