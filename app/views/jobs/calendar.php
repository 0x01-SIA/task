<?php

declare(strict_types=1);
?>
<div class="d-grid gap-4">
    <section class="card shadow-sm border-0">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Jobs</p>
                    <h1 class="h3 mb-2">Calendar</h1>
                    <p class="text-secondary mb-0">Monthly view of scheduled jobs for admins and dispatchers.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-secondary" href="<?= h(app_url('/jobs')) ?>">Back to Jobs</a>
                    <a class="btn btn-primary" href="<?= h(app_url('/jobs/create')) ?>">Create Job</a>
                </div>
            </div>
        </div>
    </section>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <div class="calendar-toolbar">
                <div>
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Month</p>
                    <h2 class="h4 mb-0"><?= h($selectedMonth->format('F Y')) ?></h2>
                </div>
                <div class="calendar-toolbar__actions">
                    <a class="btn btn-outline-secondary btn-sm" href="<?= h(app_url('/jobs/calendar') . '?month=' . $previousMonth->format('Y-m')) ?>">Previous</a>
                    <a class="btn btn-outline-secondary btn-sm" href="<?= h(app_url('/jobs/calendar') . '?month=' . $nextMonth->format('Y-m')) ?>">Next</a>
                    <a class="btn btn-outline-primary btn-sm" href="<?= h(app_url('/jobs/calendar') . '?month=' . (new DateTimeImmutable('today'))->format('Y-m')) ?>">Today</a>
                </div>
            </div>

            <div class="calendar-shell mt-4">
                <div class="calendar-grid" role="table" aria-label="Job calendar">
                    <?php foreach ($weekdayLabels as $weekdayLabel): ?>
                        <div class="calendar-grid__weekday" role="columnheader"><?= h($weekdayLabel) ?></div>
                    <?php endforeach; ?>

                    <?php foreach ($calendarWeeks as $week): ?>
                        <?php foreach ($week as $day): ?>
                            <?php
                            $visibleJobs = array_slice($day['jobs'], 0, 3);
                            $hiddenJobsCount = max(0, count($day['jobs']) - count($visibleJobs));
                            ?>
                            <section class="calendar-day<?= $day['is_current_month'] ? '' : ' calendar-day--outside' ?><?= $day['is_today'] ? ' calendar-day--today' : '' ?>" role="cell" aria-label="<?= h($day['date']->format('Y-m-d')) ?>">
                                <div class="calendar-day__header">
                                    <span class="calendar-day__number"><?= h($day['date']->format('j')) ?></span>
                                    <?php if ($day['jobs'] !== []): ?>
                                        <span class="calendar-day__count"><?= h(count($day['jobs']) === 1 ? '1 job' : count($day['jobs']) . ' jobs') ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="calendar-day__jobs">
                                    <?php foreach ($visibleJobs as $job): ?>
                                        <?php
                                        $calendarJobClasses = ['calendar-job'];
                                        $jobStatus = (string) ($job['status'] ?? '');
                                        $jobTime = ($job['planned_start_time'] ?? null) !== null
                                            ? format_time((string) $job['planned_start_time'])
                                            : 'Any time';
                                        $jobWorker = $job['assigned_worker_name'] ?: 'Unassigned';

                                        if ($jobStatus === 'completed') {
                                            $calendarJobClasses[] = 'calendar-job--completed';
                                        }

                                        if ($jobStatus === 'cancelled') {
                                            $calendarJobClasses[] = 'calendar-job--cancelled';
                                        }
                                        ?>
                                        <a class="<?= h(implode(' ', $calendarJobClasses)) ?>" href="<?= h(app_url('/jobs/' . $job['id'])) ?>">
                                            <div class="calendar-job__top">
                                                <strong class="calendar-job__number" title="<?= h($job['job_number']) ?>"><?= h($job['job_number']) ?></strong>
                                                <time class="calendar-job__time" datetime="<?= h(($job['planned_date'] ?? '') . 'T' . ($job['planned_start_time'] ?? '00:00:00')) ?>"><?= h($jobTime) ?></time>
                                            </div>
                                            <div class="calendar-job__customer" title="<?= h($job['customer_name']) ?>"><?= h($job['customer_name']) ?></div>
                                            <div class="calendar-job__bottom">
                                                <span class="calendar-job__worker" title="<?= h($jobWorker) ?>"><?= h($jobWorker) ?></span>
                                                <span class="calendar-job__status calendar-job__status--<?= h($jobStatus) ?>">
                                                    <span class="calendar-job__status-dot" aria-hidden="true"></span>
                                                    <span><?= h(job_status_label($jobStatus)) ?></span>
                                                </span>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>

                                    <?php if ($hiddenJobsCount > 0): ?>
                                        <a class="calendar-day__more" href="<?= h(app_url('/jobs?planned_date=' . $day['date_key'])) ?>">
                                            <?= h('+' . $hiddenJobsCount . ' more') ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="calendar-agenda mt-4" aria-label="Job calendar agenda">
                <?php foreach ($calendarWeeks as $week): ?>
                    <?php foreach ($week as $day): ?>
                        <?php if (!$day['is_current_month']) {
                            continue;
                        } ?>
                        <section class="calendar-agenda__day<?= $day['is_today'] ? ' calendar-agenda__day--today' : '' ?>">
                            <div class="calendar-agenda__header">
                                <h3 class="calendar-agenda__title"><?= h($day['date']->format('l, j F')) ?></h3>
                                <?php if ($day['jobs'] !== []): ?>
                                    <span class="calendar-agenda__count"><?= h(count($day['jobs']) === 1 ? '1 job' : count($day['jobs']) . ' jobs') ?></span>
                                <?php endif; ?>
                            </div>

                            <?php if ($day['jobs'] === []): ?>
                                <p class="calendar-agenda__empty">No jobs.</p>
                            <?php else: ?>
                                <div class="calendar-agenda__jobs">
                                    <?php foreach ($day['jobs'] as $job): ?>
                                        <?php
                                        $agendaJobClasses = ['calendar-job', 'calendar-job--agenda'];
                                        $jobStatus = (string) ($job['status'] ?? '');
                                        $jobTime = ($job['planned_start_time'] ?? null) !== null
                                            ? format_time((string) $job['planned_start_time'])
                                            : 'Any time';
                                        $jobWorker = $job['assigned_worker_name'] ?: 'Unassigned';

                                        if ($jobStatus === 'completed') {
                                            $agendaJobClasses[] = 'calendar-job--completed';
                                        }

                                        if ($jobStatus === 'cancelled') {
                                            $agendaJobClasses[] = 'calendar-job--cancelled';
                                        }
                                        ?>
                                        <a class="<?= h(implode(' ', $agendaJobClasses)) ?>" href="<?= h(app_url('/jobs/' . $job['id'])) ?>">
                                            <div class="calendar-job__top">
                                                <strong class="calendar-job__number" title="<?= h($job['job_number']) ?>"><?= h($job['job_number']) ?></strong>
                                                <time class="calendar-job__time" datetime="<?= h(($job['planned_date'] ?? '') . 'T' . ($job['planned_start_time'] ?? '00:00:00')) ?>"><?= h($jobTime) ?></time>
                                            </div>
                                            <div class="calendar-job__customer" title="<?= h($job['customer_name']) ?>"><?= h($job['customer_name']) ?></div>
                                            <div class="calendar-job__bottom">
                                                <span class="calendar-job__worker" title="<?= h($jobWorker) ?>"><?= h($jobWorker) ?></span>
                                                <span class="calendar-job__status calendar-job__status--<?= h($jobStatus) ?>">
                                                    <span class="calendar-job__status-dot" aria-hidden="true"></span>
                                                    <span><?= h(job_status_label($jobStatus)) ?></span>
                                                </span>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </section>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Planning</p>
                    <h2 class="h5 mb-1">Unscheduled Active Jobs</h2>
                    <p class="text-secondary mb-0">Jobs without a planned date stay out of the calendar until they are scheduled.</p>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <span class="badge rounded-pill text-bg-light border fs-6"><?= h((string) $unscheduledActiveJobsCount) ?></span>
                    <a class="btn btn-outline-secondary btn-sm" href="<?= h(app_url('/jobs?schedule=unscheduled')) ?>">View Unscheduled Jobs</a>
                </div>
            </div>
        </div>
    </section>
</div>
