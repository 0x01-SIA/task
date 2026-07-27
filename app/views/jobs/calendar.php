<?php

declare(strict_types=1);

$isWeekView = $calendarView === 'week';
$monthSwitchMonth = $isWeekView ? $selectedDate->format('Y-m') : $selectedMonth->format('Y-m');
$weekSwitchDate = $isWeekView ? $selectedDate->format('Y-m-d') : $selectedMonth->format('Y-m-01');
?>
<div class="d-grid gap-4">
    <section class="card shadow-sm border-0">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Jobs</p>
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <h1 class="h3 mb-0">Calendar</h1>
                        <nav class="calendar-view-switcher" aria-label="Calendar view">
                            <a
                                class="calendar-view-switcher__link<?= $isWeekView ? ' active' : '' ?>"
                                href="<?= h(app_url('/jobs/calendar?view=week&date=' . $weekSwitchDate)) ?>"
                                <?= $isWeekView ? 'aria-current="page"' : '' ?>
                            >
                                Week
                            </a>
                            <a
                                class="calendar-view-switcher__link<?= !$isWeekView ? ' active' : '' ?>"
                                href="<?= h(app_url('/jobs/calendar?view=month&month=' . $monthSwitchMonth)) ?>"
                                <?= !$isWeekView ? 'aria-current="page"' : '' ?>
                            >
                                Month
                            </a>
                        </nav>
                    </div>
                    <p class="text-secondary mb-0 mt-2">
                        <?= h($isWeekView
                            ? 'Default operational view for today, this week, and assigned workers.'
                            : 'Broader planning view for the current month.') ?>
                    </p>
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
            <?php if ($isWeekView): ?>
                <div class="calendar-toolbar">
                    <div>
                        <p class="text-uppercase text-secondary small fw-semibold mb-2">Week</p>
                        <h2 class="h4 mb-1"><?= h($weekStart->format('j F') . ' – ' . $weekEnd->format('j F Y')) ?></h2>
                        <p class="text-secondary mb-0">Monday to Sunday scheduling overview.</p>
                    </div>
                    <div class="calendar-toolbar__actions">
                        <a class="btn btn-outline-secondary btn-sm" href="<?= h(app_url('/jobs/calendar?view=week&date=' . $previousWeek->format('Y-m-d'))) ?>">Previous week</a>
                        <a class="btn btn-outline-primary btn-sm" href="<?= h(app_url('/jobs/calendar?view=week&date=' . $todayDate->format('Y-m-d'))) ?>">Today</a>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= h(app_url('/jobs/calendar?view=week&date=' . $nextWeek->format('Y-m-d'))) ?>">Next week</a>
                    </div>
                </div>

                <div class="calendar-week mt-4" role="table" aria-label="Weekly job calendar">
                    <?php foreach ($weekDays as $day): ?>
                        <section class="calendar-week__day<?= $day['is_today'] ? ' calendar-week__day--today' : '' ?>" role="cell" aria-label="<?= h($day['date']->format('Y-m-d')) ?>">
                            <div class="calendar-week__header">
                                <div>
                                    <div class="calendar-week__weekday">
                                        <?= h($day['date']->format('l')) ?>
                                        <?php if ($day['is_today']): ?>
                                            <span class="calendar-today-pill">Today</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="calendar-week__date"><?= h($day['date']->format('j F')) ?></div>
                                </div>
                                <div class="calendar-week__count"><?= h($day['jobs'] === [] ? 'No jobs' : (count($day['jobs']) === 1 ? '1 job' : count($day['jobs']) . ' jobs')) ?></div>
                            </div>

                            <?php if ($day['jobs'] === []): ?>
                                <p class="calendar-week__empty">No jobs</p>
                            <?php else: ?>
                                <div class="calendar-week__jobs">
                                    <?php foreach ($day['jobs'] as $job): ?>
                                        <?php
                                        $jobStatus = (string) ($job['status'] ?? '');
                                        $jobTime = ($job['planned_start_time'] ?? null) !== null
                                            ? format_time((string) $job['planned_start_time'])
                                            : 'Any time';
                                        $jobWorker = $job['assigned_worker_name'] ?: 'Unassigned';
                                        $jobLocation = $job['location_name'] ?: $job['address_line'] ?: 'Location pending';
                                        $weekJobClasses = ['calendar-job', 'calendar-job--week'];

                                        if ($jobStatus === 'completed') {
                                            $weekJobClasses[] = 'calendar-job--completed';
                                        }

                                        if ($jobStatus === 'cancelled') {
                                            $weekJobClasses[] = 'calendar-job--cancelled';
                                        }
                                        ?>
                                        <a class="<?= h(implode(' ', $weekJobClasses)) ?>" href="<?= h(app_url('/jobs/' . $job['id'])) ?>">
                                            <div class="calendar-job__top">
                                                <time class="calendar-job__time" datetime="<?= h(($job['planned_date'] ?? '') . 'T' . ($job['planned_start_time'] ?? '00:00:00')) ?>"><?= h($jobTime) ?></time>
                                                <strong class="calendar-job__number" title="<?= h($job['job_number']) ?>"><?= h($job['job_number']) ?></strong>
                                            </div>
                                            <div class="calendar-job__customer" title="<?= h($job['customer_name']) ?>"><?= h($job['customer_name']) ?></div>
                                            <div class="calendar-job__location" title="<?= h($jobLocation) ?>"><?= h($jobLocation) ?></div>
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
                </div>

                <div class="calendar-week-mobile mt-4" aria-label="Weekly job agenda">
                    <?php foreach ($weekDays as $day): ?>
                        <section class="calendar-agenda__day<?= $day['is_today'] ? ' calendar-agenda__day--today' : '' ?>">
                            <div class="calendar-agenda__header">
                                <div>
                                    <h3 class="calendar-agenda__title"><?= h($day['date']->format('l, j F')) ?></h3>
                                    <?php if ($day['is_today']): ?>
                                        <span class="calendar-today-pill mt-1">Today</span>
                                    <?php endif; ?>
                                </div>
                                <span class="calendar-agenda__count"><?= h($day['jobs'] === [] ? 'No jobs' : (count($day['jobs']) === 1 ? '1 job' : count($day['jobs']) . ' jobs')) ?></span>
                            </div>

                            <?php if ($day['jobs'] === []): ?>
                                <p class="calendar-agenda__empty">No jobs</p>
                            <?php else: ?>
                                <div class="calendar-agenda__jobs">
                                    <?php foreach ($day['jobs'] as $job): ?>
                                        <?php
                                        $jobStatus = (string) ($job['status'] ?? '');
                                        $jobTime = ($job['planned_start_time'] ?? null) !== null
                                            ? format_time((string) $job['planned_start_time'])
                                            : 'Any time';
                                        $jobWorker = $job['assigned_worker_name'] ?: 'Unassigned';
                                        $jobLocation = $job['location_name'] ?: $job['address_line'] ?: 'Location pending';
                                        $weekAgendaClasses = ['calendar-job', 'calendar-job--agenda'];

                                        if ($jobStatus === 'completed') {
                                            $weekAgendaClasses[] = 'calendar-job--completed';
                                        }

                                        if ($jobStatus === 'cancelled') {
                                            $weekAgendaClasses[] = 'calendar-job--cancelled';
                                        }
                                        ?>
                                        <a class="<?= h(implode(' ', $weekAgendaClasses)) ?>" href="<?= h(app_url('/jobs/' . $job['id'])) ?>">
                                            <div class="calendar-job__top">
                                                <time class="calendar-job__time" datetime="<?= h(($job['planned_date'] ?? '') . 'T' . ($job['planned_start_time'] ?? '00:00:00')) ?>"><?= h($jobTime) ?></time>
                                                <strong class="calendar-job__number" title="<?= h($job['job_number']) ?>"><?= h($job['job_number']) ?></strong>
                                            </div>
                                            <div class="calendar-job__customer" title="<?= h($job['customer_name']) ?>"><?= h($job['customer_name']) ?></div>
                                            <div class="calendar-job__location" title="<?= h($jobLocation) ?>"><?= h($jobLocation) ?></div>
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
                </div>
            <?php else: ?>
                <div class="calendar-toolbar">
                    <div>
                        <p class="text-uppercase text-secondary small fw-semibold mb-2">Month</p>
                        <h2 class="h4 mb-1"><?= h($selectedMonth->format('F Y')) ?></h2>
                        <p class="text-secondary mb-0">Broader monthly planning overview.</p>
                    </div>
                    <div class="calendar-toolbar__actions">
                        <a class="btn btn-outline-secondary btn-sm" href="<?= h(app_url('/jobs/calendar?view=month&month=' . $previousMonth->format('Y-m'))) ?>">Previous month</a>
                        <a class="btn btn-outline-primary btn-sm" href="<?= h(app_url('/jobs/calendar?view=month&month=' . $todayDate->format('Y-m'))) ?>">Today</a>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= h(app_url('/jobs/calendar?view=month&month=' . $nextMonth->format('Y-m'))) ?>">Next month</a>
                    </div>
                </div>

                <div class="calendar-shell mt-4">
                    <div class="calendar-grid" role="table" aria-label="Monthly job calendar">
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
                                        <span class="calendar-day__number">
                                            <?= h($day['date']->format('j')) ?>
                                            <?php if ($day['is_today']): ?>
                                                <span class="calendar-today-pill calendar-today-pill--inline">Today</span>
                                            <?php endif; ?>
                                        </span>
                                        <?php if ($day['jobs'] !== []): ?>
                                            <span class="calendar-day__count"><?= h(count($day['jobs']) === 1 ? '1 job' : count($day['jobs']) . ' jobs') ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="calendar-day__jobs">
                                        <?php foreach ($visibleJobs as $job): ?>
                                            <?php
                                            $jobStatus = (string) ($job['status'] ?? '');
                                            $jobTime = ($job['planned_start_time'] ?? null) !== null
                                                ? format_time((string) $job['planned_start_time'])
                                                : 'Any time';
                                            $monthJobClasses = ['calendar-job'];

                                            if ($jobStatus === 'completed') {
                                                $monthJobClasses[] = 'calendar-job--completed';
                                            }

                                            if ($jobStatus === 'cancelled') {
                                                $monthJobClasses[] = 'calendar-job--cancelled';
                                            }
                                            ?>
                                            <a class="<?= h(implode(' ', $monthJobClasses)) ?>" href="<?= h(app_url('/jobs/' . $job['id'])) ?>">
                                                <div class="calendar-job__top">
                                                    <time class="calendar-job__time" datetime="<?= h(($job['planned_date'] ?? '') . 'T' . ($job['planned_start_time'] ?? '00:00:00')) ?>"><?= h($jobTime) ?></time>
                                                    <strong class="calendar-job__number" title="<?= h($job['job_number']) ?>"><?= h($job['job_number']) ?></strong>
                                                </div>
                                                <div class="calendar-job__customer" title="<?= h($job['customer_name']) ?>"><?= h($job['customer_name']) ?></div>
                                                <div class="calendar-job__bottom">
                                                    <span class="calendar-job__status calendar-job__status--<?= h($jobStatus) ?>">
                                                        <span class="calendar-job__status-dot" aria-hidden="true"></span>
                                                        <span><?= h(job_status_label($jobStatus)) ?></span>
                                                    </span>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>

                                        <?php if ($hiddenJobsCount > 0): ?>
                                            <a class="calendar-day__more" href="<?= h(app_url('/jobs/calendar?view=week&date=' . $day['date_key'])) ?>">
                                                <?= h('+' . $hiddenJobsCount . ' more') ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </section>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="calendar-month-mobile mt-4" aria-label="Monthly job agenda">
                    <?php foreach ($calendarWeeks as $week): ?>
                        <?php foreach ($week as $day): ?>
                            <?php if (!$day['is_current_month']) {
                                continue;
                            } ?>
                            <section class="calendar-agenda__day<?= $day['is_today'] ? ' calendar-agenda__day--today' : '' ?>">
                                <div class="calendar-agenda__header">
                                    <div>
                                        <h3 class="calendar-agenda__title"><?= h($day['date']->format('l, j F')) ?></h3>
                                        <?php if ($day['is_today']): ?>
                                            <span class="calendar-today-pill mt-1">Today</span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="calendar-agenda__count"><?= h($day['jobs'] === [] ? 'No jobs' : (count($day['jobs']) === 1 ? '1 job' : count($day['jobs']) . ' jobs')) ?></span>
                                </div>

                                <?php if ($day['jobs'] === []): ?>
                                    <p class="calendar-agenda__empty">No jobs</p>
                                <?php else: ?>
                                    <div class="calendar-agenda__jobs">
                                        <?php foreach ($day['jobs'] as $job): ?>
                                            <?php
                                            $jobStatus = (string) ($job['status'] ?? '');
                                            $jobTime = ($job['planned_start_time'] ?? null) !== null
                                                ? format_time((string) $job['planned_start_time'])
                                                : 'Any time';
                                            $monthAgendaClasses = ['calendar-job', 'calendar-job--agenda'];

                                            if ($jobStatus === 'completed') {
                                                $monthAgendaClasses[] = 'calendar-job--completed';
                                            }

                                            if ($jobStatus === 'cancelled') {
                                                $monthAgendaClasses[] = 'calendar-job--cancelled';
                                            }
                                            ?>
                                            <a class="<?= h(implode(' ', $monthAgendaClasses)) ?>" href="<?= h(app_url('/jobs/' . $job['id'])) ?>">
                                                <div class="calendar-job__top">
                                                    <time class="calendar-job__time" datetime="<?= h(($job['planned_date'] ?? '') . 'T' . ($job['planned_start_time'] ?? '00:00:00')) ?>"><?= h($jobTime) ?></time>
                                                    <strong class="calendar-job__number" title="<?= h($job['job_number']) ?>"><?= h($job['job_number']) ?></strong>
                                                </div>
                                                <div class="calendar-job__customer" title="<?= h($job['customer_name']) ?>"><?= h($job['customer_name']) ?></div>
                                                <div class="calendar-job__bottom">
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
            <?php endif; ?>
        </div>
    </section>

    <section class="card shadow-sm border-0">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <p class="text-uppercase text-secondary small fw-semibold mb-2">Planning</p>
                    <h2 class="h5 mb-1">Unscheduled Active Jobs</h2>
                    <p class="text-secondary mb-0">Jobs without a planned date stay out of scheduled views until they are assigned a date.</p>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <span class="badge rounded-pill text-bg-light border fs-6"><?= h((string) $unscheduledActiveJobsCount) ?></span>
                    <a class="btn btn-outline-secondary btn-sm" href="<?= h(app_url('/jobs?schedule=unscheduled')) ?>">View Unscheduled Jobs</a>
                </div>
            </div>
        </div>
    </section>
</div>
