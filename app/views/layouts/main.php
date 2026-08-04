<?php

declare(strict_types=1);

$pageTitle = $pageTitle ?? config('app.name', 'Task App');
$user = current_user();
$navigationItems = auth_navigation_items($user);
$currentSectionLabel = current_navigation_label($navigationItems);
$companyLabel = $user !== null ? current_company_context_label($user) : '';
$companyOptions = $user !== null ? company_context_options($user) : [];
$canSwitchCompany = $user !== null && is_super_admin($user) && $companyOptions !== [];
$companyDetailHref = $user !== null && user_can_view_current_company_page($user) && is_int($user['active_company_id'] ?? null)
    ? app_url('/companies/' . $user['active_company_id'])
    : null;
$accountInitials = $user !== null ? user_initials($user) : 'TA';
$accountMenuId = 'account-menu';
$mobileDrawerId = 'mobile-nav-drawer';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <script>
        (() => {
            const storageKey = 'task-theme-preference';
            const root = document.documentElement;
            const media = window.matchMedia('(prefers-color-scheme: dark)');
            const storedTheme = localStorage.getItem(storageKey) || 'system';
            const resolvedTheme = storedTheme === 'dark' || (storedTheme === 'system' && media.matches) ? 'dark' : 'light';
            root.setAttribute('data-theme-preference', storedTheme);
            root.setAttribute('data-theme', resolvedTheme);
            root.setAttribute('data-bs-theme', resolvedTheme);
        })();
    </script>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
        crossorigin="anonymous"
    >
    <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('css/app.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="app-shell">
    <div class="app-mobile-backdrop" data-app-overlay hidden></div>

    <header class="app-header">
        <div class="container app-container">
            <div class="app-header-bar">
                <?php if ($user !== null && $navigationItems !== []): ?>
                    <button
                        class="app-menu-toggle"
                        type="button"
                        data-drawer-toggle
                        aria-expanded="false"
                        aria-controls="<?= h($mobileDrawerId) ?>"
                        aria-label="Open navigation menu"
                    >
                        <span class="app-menu-toggle__line" aria-hidden="true"></span>
                        <span class="app-menu-toggle__line" aria-hidden="true"></span>
                        <span class="app-menu-toggle__line" aria-hidden="true"></span>
                    </button>
                <?php endif; ?>

                <a class="app-brand" href="<?= h(app_url(home_path_for_user($user))) ?>">
                    <span class="app-brand__mark" aria-hidden="true">
                        <span></span>
                    </span>
                    <span class="app-brand__text">
                        <span class="app-brand__name">Task</span>
                        <?php if ($user !== null): ?>
                            <span class="app-brand__section"><?= h($currentSectionLabel) ?></span>
                        <?php endif; ?>
                    </span>
                </a>

                <?php if ($user !== null && $navigationItems !== []): ?>
                    <nav class="auth-nav" aria-label="Primary">
                        <?php foreach ($navigationItems as $item): ?>
                            <?php $isActive = is_current_path((string) $item['path']); ?>
                            <a
                                class="auth-nav-link<?= $isActive ? ' active' : '' ?>"
                                href="<?= h(app_url((string) $item['path'])) ?>"
                                <?= $isActive ? 'aria-current="page"' : '' ?>
                            >
                                <?= h((string) $item['label']) ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                <?php endif; ?>

                <?php if ($user !== null): ?>
                    <div class="app-account-shell">
                        <button
                            class="app-account-trigger"
                            type="button"
                            data-menu-toggle
                            aria-haspopup="menu"
                            aria-expanded="false"
                            aria-controls="<?= h($accountMenuId) ?>"
                        >
                            <span class="app-account-avatar" aria-hidden="true"><?= h($accountInitials) ?></span>
                            <span class="app-account-meta">
                                <span class="app-account-name" title="<?= h((string) $user['name']) ?>"><?= h((string) $user['name']) ?></span>
                                <span class="app-account-secondary">
                                    <span class="app-account-company" title="<?= h($companyLabel) ?>"><?= h($companyLabel) ?></span>
                                </span>
                            </span>
                            <span class="app-account-chevron" aria-hidden="true">▾</span>
                        </button>

                        <div class="app-account-menu" id="<?= h($accountMenuId) ?>" data-menu-panel role="menu" aria-label="Account menu" hidden>
                            <section class="app-menu-section">
                                <p class="app-menu-label">Signed in as</p>
                                <div class="app-menu-identity">
                                    <div class="app-menu-identity__name"><?= h((string) $user['name']) ?></div>
                                    <div class="app-menu-identity__meta"><?= h(role_label((string) $user['role'])) ?></div>
                                </div>
                            </section>

                            <section class="app-menu-section">
                                <div class="app-menu-heading">
                                    <span class="app-menu-label">Company</span>
                                    <?php if (!$canSwitchCompany && $companyDetailHref !== null): ?>
                                        <a class="app-menu-link" href="<?= h($companyDetailHref) ?>">Open</a>
                                    <?php endif; ?>
                                </div>

                                <?php if ($canSwitchCompany): ?>
                                    <div class="app-company-list" role="group" aria-label="Switch active company">
                                        <?php foreach ($companyOptions as $option): ?>
                                            <?php
                                            $selected = ($option['id'] === 'all' && current_company_context_value() === 'all')
                                                || ((int) $option['id'] > 0 && (int) $option['id'] === (int) ($user['active_company_id'] ?? 0));
                                            ?>
                                            <form method="post" action="<?= h(app_url('/company-context')) ?>" class="app-company-list__form">
                                                <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                                                <input type="hidden" name="company_id" value="<?= h((string) $option['id']) ?>">
                                                <button
                                                    class="app-company-list__item<?= $selected ? ' is-active' : '' ?>"
                                                    type="submit"
                                                    role="menuitemradio"
                                                    aria-checked="<?= $selected ? 'true' : 'false' ?>"
                                                >
                                                    <span class="app-company-list__name" title="<?= h($option['name']) ?>"><?= h($option['name']) ?></span>
                                                    <?php if ($selected): ?>
                                                        <span class="app-company-list__status">Current</span>
                                                    <?php endif; ?>
                                                </button>
                                            </form>
                                        <?php endforeach; ?>
                                    </div>
                                <?php elseif ($companyLabel !== ''): ?>
                                    <p class="app-menu-static-value mb-0"><?= h($companyLabel) ?></p>
                                <?php endif; ?>
                            </section>

                            <section class="app-menu-section">
                                <span class="app-menu-label">Theme</span>
                                <div class="theme-switcher" role="group" aria-label="Select theme">
                                    <?php foreach (['light' => 'Light', 'dark' => 'Dark', 'system' => 'System'] as $themeValue => $themeLabel): ?>
                                        <button
                                            class="theme-switcher__option"
                                            type="button"
                                            data-theme-option="<?= h($themeValue) ?>"
                                            role="menuitemradio"
                                            aria-checked="false"
                                        >
                                            <?= h($themeLabel) ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </section>

                            <section class="app-menu-section">
                                <form method="post" action="<?= h(app_url('/logout')) ?>">
                                    <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                                    <button class="app-menu-danger" type="submit" role="menuitem">Log out</button>
                                </form>
                            </section>
                        </div>
                    </div>
                <?php else: ?>
                    <nav>
                        <a class="app-login-link" href="<?= h(app_url('/login')) ?>">Login</a>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <?php if ($user !== null && $navigationItems !== []): ?>
        <aside
            class="app-mobile-drawer"
            id="<?= h($mobileDrawerId) ?>"
            data-drawer
            aria-hidden="true"
            tabindex="-1"
            hidden
        >
            <div class="app-mobile-drawer__header">
                <div>
                    <p class="app-menu-label mb-1">Navigation</p>
                    <h2 class="h5 mb-0"><?= h($currentSectionLabel) ?></h2>
                </div>
                <button class="app-drawer-close" type="button" data-drawer-close aria-label="Close navigation menu">Close</button>
            </div>

            <nav class="app-mobile-nav" aria-label="Mobile primary">
                <?php foreach ($navigationItems as $item): ?>
                    <?php $isActive = is_current_path((string) $item['path']); ?>
                    <a
                        class="app-mobile-nav__link<?= $isActive ? ' active' : '' ?>"
                        href="<?= h(app_url((string) $item['path'])) ?>"
                        <?= $isActive ? 'aria-current="page"' : '' ?>
                    >
                        <span><?= h((string) $item['label']) ?></span>
                        <span aria-hidden="true">›</span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <section class="app-mobile-panel">
                <p class="app-menu-label">Company</p>
                <?php if ($canSwitchCompany): ?>
                    <div class="app-company-list" role="group" aria-label="Switch active company">
                        <?php foreach ($companyOptions as $option): ?>
                            <?php
                            $selected = ($option['id'] === 'all' && current_company_context_value() === 'all')
                                || ((int) $option['id'] > 0 && (int) $option['id'] === (int) ($user['active_company_id'] ?? 0));
                            ?>
                            <form method="post" action="<?= h(app_url('/company-context')) ?>" class="app-company-list__form">
                                <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                                <input type="hidden" name="company_id" value="<?= h((string) $option['id']) ?>">
                                <button
                                    class="app-company-list__item<?= $selected ? ' is-active' : '' ?>"
                                    type="submit"
                                >
                                    <span class="app-company-list__name"><?= h($option['name']) ?></span>
                                    <?php if ($selected): ?>
                                        <span class="app-company-list__status">Current</span>
                                    <?php endif; ?>
                                </button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($companyDetailHref !== null): ?>
                    <a class="app-mobile-panel__link" href="<?= h($companyDetailHref) ?>"><?= h($companyLabel) ?></a>
                <?php else: ?>
                    <p class="app-menu-static-value mb-0"><?= h($companyLabel) ?></p>
                <?php endif; ?>
            </section>

            <section class="app-mobile-panel">
                <p class="app-menu-label">Theme</p>
                <div class="theme-switcher theme-switcher--stacked" role="group" aria-label="Select theme">
                    <?php foreach (['light' => 'Light', 'dark' => 'Dark', 'system' => 'System'] as $themeValue => $themeLabel): ?>
                        <button class="theme-switcher__option" type="button" data-theme-option="<?= h($themeValue) ?>">
                            <?= h($themeLabel) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="app-mobile-panel app-mobile-panel--danger">
                <form method="post" action="<?= h(app_url('/logout')) ?>">
                    <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                    <button class="app-menu-danger" type="submit">Log out</button>
                </form>
            </section>
        </aside>
    <?php endif; ?>

    <main class="app-main py-4 py-lg-5">
        <div class="container app-container">
            <?= $content ?>
        </div>
    </main>

    <footer class="py-4 text-center text-secondary small">
        <div class="container app-container">
            <div>0x01, SIA</div>
            <a class="link-secondary text-decoration-none" href="mailto:contact@0x01.lv">contact@0x01.lv</a>
        </div>
    </footer>

    <script src="<?= htmlspecialchars(asset_url('js/app.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
