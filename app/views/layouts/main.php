<?php

declare(strict_types=1);

$pageTitle = $pageTitle ?? config('app.name', 'Task App');
$user = current_user();
$navigationItems = auth_navigation_items($user);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
        crossorigin="anonymous"
    >
    <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('css/app.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="app-shell">
    <header class="border-bottom bg-white">
        <div class="container app-container">
            <div class="app-header-bar">
                <?php if ($user !== null && $navigationItems !== []): ?>
                    <nav class="auth-nav" aria-label="Primary">
                        <?php foreach ($navigationItems as $item): ?>
                            <?php $isActive = is_current_path($item['path']); ?>
                            <a
                                class="auth-nav-link<?= $isActive ? ' active' : '' ?>"
                                href="<?= htmlspecialchars(app_url($item['path']), ENT_QUOTES, 'UTF-8') ?>"
                                <?= $isActive ? 'aria-current="page"' : '' ?>
                            >
                                <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                <?php else: ?>
                    <div></div>
                <?php endif; ?>

                <?php if ($user !== null): ?>
                    <?php
                    $companyLabel = current_company_context_label($user);
                    $companyOptions = company_context_options($user);
                    $showSuperAdminSwitcher = is_super_admin($user) && $companyOptions !== [];
                    $showAdminCompanyLink = !$showSuperAdminSwitcher
                        && (string) ($user['role'] ?? '') === 'admin'
                        && $companyLabel !== '';
                    $companyDetailHref = user_can_view_current_company_page($user) && is_int($user['active_company_id'] ?? null)
                        ? app_url('/companies/' . $user['active_company_id'])
                        : null;
                    ?>
                    <div class="app-account-controls">
                        <div class="app-account-meta">
                            <div class="app-account-name" title="<?= h((string) $user['name']) ?>"><?= htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="app-account-secondary small text-secondary">
                                <?php if ($showSuperAdminSwitcher): ?>
                                    <details class="account-company-switcher">
                                        <summary class="account-company-trigger" aria-label="Switch active company">
                                            <span><?= h(role_label((string) $user['role'])) ?></span>
                                            <span aria-hidden="true">·</span>
                                            <span class="account-company-trigger__label" title="<?= h($companyLabel) ?>"><?= h($companyLabel) ?></span>
                                            <span class="account-company-trigger__chevron" aria-hidden="true">▾</span>
                                        </summary>
                                        <div class="account-company-menu" role="menu" aria-label="Active company options">
                                            <?php foreach ($companyOptions as $option): ?>
                                                <?php
                                                $selected = ($option['id'] === 'all' && current_company_context_value() === 'all')
                                                    || ((int) $option['id'] > 0 && (int) $option['id'] === (int) ($user['active_company_id'] ?? 0));
                                                ?>
                                                <form method="post" action="<?= h(app_url('/company-context')) ?>" class="account-company-menu__form">
                                                    <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                                                    <input type="hidden" name="company_id" value="<?= h((string) $option['id']) ?>">
                                                    <button class="account-company-menu__item<?= $selected ? ' is-active' : '' ?>" type="submit" role="menuitem">
                                                        <span class="account-company-menu__name" title="<?= h($option['name']) ?>"><?= h($option['name']) ?></span>
                                                        <?php if ($selected): ?>
                                                            <span class="account-company-menu__status" aria-hidden="true">Current</span>
                                                        <?php endif; ?>
                                                    </button>
                                                </form>
                                            <?php endforeach; ?>
                                        </div>
                                    </details>
                                <?php elseif ($showAdminCompanyLink): ?>
                                    <span><?= h(role_label((string) $user['role'])) ?></span>
                                    <?php if ($companyDetailHref !== null): ?>
                                        <span aria-hidden="true">·</span>
                                        <a class="account-company-link" href="<?= h($companyDetailHref) ?>" title="<?= h($companyLabel) ?>"><?= h($companyLabel) ?></a>
                                    <?php elseif ($companyLabel !== ''): ?>
                                        <span aria-hidden="true">·</span>
                                        <span title="<?= h($companyLabel) ?>"><?= h($companyLabel) ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?= htmlspecialchars(role_label((string) $user['role']), ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <form method="post" action="<?= htmlspecialchars(app_url('/logout'), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                            <button class="btn btn-outline-danger btn-sm" type="submit">Log out</button>
                        </form>
                    </div>
                <?php else: ?>
                    <nav>
                        <a class="link-secondary text-decoration-none" href="<?= htmlspecialchars(app_url('/login'), ENT_QUOTES, 'UTF-8') ?>">Login</a>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="py-5">
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
