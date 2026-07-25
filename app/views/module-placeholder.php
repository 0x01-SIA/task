<?php

declare(strict_types=1);
?>
<div class="card shadow-sm border-0">
    <div class="card-body p-4 p-lg-5">
        <p class="text-uppercase text-secondary small fw-semibold mb-2">Authenticated Module</p>
        <h1 class="h3 mb-3"><?= htmlspecialchars((string) $heading, ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-secondary mb-0"><?= htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
</div>
