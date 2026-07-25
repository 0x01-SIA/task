<?php

declare(strict_types=1);
?>
<div class="d-grid gap-4">
    <section class="card shadow-sm border-0">
        <div class="card-body p-4 p-lg-5">
            <p class="text-uppercase text-secondary small fw-semibold mb-2">Field Work</p>
            <h1 class="h3 mb-2">My Work</h1>
            <p class="text-secondary mb-0">A focused view of your assigned jobs and the work currently in motion.</p>
        </div>
    </section>

    <section class="row g-3">
        <div class="col-sm-6 col-xl-4">
            <div class="dashboard-card h-100">
                <p class="dashboard-card-label">Assigned Jobs</p>
                <p class="dashboard-card-value"><?= htmlspecialchars((string) $counts['assigned_jobs'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>
        <div class="col-sm-6 col-xl-4">
            <div class="dashboard-card h-100">
                <p class="dashboard-card-label">Planned Jobs</p>
                <p class="dashboard-card-value"><?= htmlspecialchars((string) $counts['planned_jobs'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>
        <div class="col-sm-6 col-xl-4">
            <div class="dashboard-card h-100">
                <p class="dashboard-card-label">Scheduled Today</p>
                <p class="dashboard-card-value"><?= htmlspecialchars((string) $counts['scheduled_today'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>
    </section>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h2 class="h5 mb-1">Assigned jobs</h2>
                <p class="text-secondary mb-0">Open the assigned jobs list for the next task details.</p>
            </div>
            <a class="btn btn-primary" href="<?= htmlspecialchars(app_url('/work/jobs'), ENT_QUOTES, 'UTF-8') ?>">View assigned jobs</a>
        </div>
    </section>
</div>
