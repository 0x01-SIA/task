<?php

declare(strict_types=1);
?>
<div class="card shadow-sm border-0">
    <div class="card-body p-4 p-md-5">
        <div class="mb-4">
            <p class="text-uppercase text-secondary small fw-semibold mb-2">Project Foundation</p>
            <h1 class="h2 mb-2"><?= htmlspecialchars(config('app.name', 'Task App'), ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="text-secondary mb-0">Lightweight field service management.</p>
        </div>

        <div class="row g-3">
            <div class="col-md-4">
                <div class="status-panel">
                    <div class="status-label">Application</div>
                    <div class="status-value">
                        <span class="status-dot bg-success"></span>
                        Running
                    </div>
                    <p class="status-help mb-0">Server-rendered PHP entry point is responding.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="status-panel">
                    <div class="status-label">Database</div>
                    <div class="status-value">
                        <span class="status-dot bg-<?= htmlspecialchars($databaseStatus['variant'], ENT_QUOTES, 'UTF-8') ?>"></span>
                        <?= htmlspecialchars($databaseStatus['label'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <p class="status-help mb-0"><?= htmlspecialchars($databaseStatus['message'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="status-panel">
                    <div class="status-label">Environment</div>
                    <div class="status-value">
                        <span class="status-dot bg-primary"></span>
                        <?= htmlspecialchars((string) config('app.env', 'development'), ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <p class="status-help mb-0">Debug mode: <?= is_debug() ? 'enabled' : 'disabled' ?>.</p>
                </div>
            </div>
        </div>
    </div>
</div>
