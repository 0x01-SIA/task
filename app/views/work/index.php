<?php

declare(strict_types=1);

$sectionMeta = [
    'today' => [
        'eyebrow' => 'Today',
        'title' => 'Today\'s Jobs',
        'empty' => 'No active jobs are scheduled for today.',
    ],
    'upcoming' => [
        'eyebrow' => 'Upcoming',
        'title' => 'Upcoming Jobs',
        'empty' => 'No future assigned jobs are scheduled yet.',
    ],
    'completed' => [
        'eyebrow' => 'Completed',
        'title' => 'Recently Completed',
        'empty' => 'No recently completed jobs are available.',
    ],
];
?>
<div class="d-grid gap-4">
    <section class="card shadow-sm border-0">
        <div class="card-body p-4 p-lg-5">
            <p class="text-uppercase text-secondary small fw-semibold mb-2">Field Work</p>
            <h1 class="h3 mb-2">My Work</h1>
            <p class="text-secondary mb-0">Assigned jobs are grouped for quick field access on mobile and desktop.</p>
        </div>
    </section>

    <?php if ($successMessage !== null): ?>
        <div class="alert alert-success mb-0" role="status"><?= h($successMessage) ?></div>
    <?php endif; ?>

    <?php if ($errorMessage !== null): ?>
        <div class="alert alert-danger mb-0" role="alert"><?= h($errorMessage) ?></div>
    <?php endif; ?>

    <?php foreach ($sectionMeta as $sectionKey => $meta): ?>
        <?php $jobs = $jobSections[$sectionKey] ?? []; ?>
        <section class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="mb-3">
                    <p class="text-uppercase text-secondary small fw-semibold mb-2"><?= h($meta['eyebrow']) ?></p>
                    <h2 class="h5 mb-0"><?= h($meta['title']) ?></h2>
                </div>

                <?php if ($jobs === []): ?>
                    <p class="text-secondary mb-0"><?= h($meta['empty']) ?></p>
                <?php else: ?>
                    <div class="worker-job-list">
                        <?php foreach ($jobs as $job): ?>
                            <a class="worker-job-card" href="<?= h(app_url('/work/jobs/' . $job['id'])) ?>">
                                <div class="worker-job-card__top">
                                    <div>
                                        <p class="worker-job-card__number"><?= h($job['job_number']) ?></p>
                                        <h3 class="worker-job-card__title"><?= h($job['title']) ?></h3>
                                    </div>
                                    <span class="badge <?= h(job_status_badge_class((string) $job['status'])) ?>">
                                        <?= h(job_status_label((string) $job['status'])) ?>
                                    </span>
                                </div>

                                <div class="worker-job-card__meta">
                                    <span><?= h(job_type_label((string) $job['job_type'])) ?></span>
                                    <span><?= h(job_priority_label((string) $job['priority'])) ?> priority</span>
                                </div>

                                <dl class="worker-job-card__details">
                                    <div>
                                        <dt>Customer</dt>
                                        <dd><?= h($job['customer_name']) ?></dd>
                                    </div>
                                    <div>
                                        <dt>Location</dt>
                                        <dd><?= h($job['location_name'] ?: location_address($job) ?: 'Location not provided') ?></dd>
                                    </div>
                                    <div>
                                        <dt>Scheduled</dt>
                                        <dd><?= h(format_job_scheduled_start($job)) ?></dd>
                                    </div>
                                </dl>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>
