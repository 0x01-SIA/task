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
                    <div class="app-account-controls">
                        <div class="app-account-meta">
                            <div class="fw-semibold"><?= htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="small text-secondary"><?= htmlspecialchars(role_label((string) $user['role']), ENT_QUOTES, 'UTF-8') ?></div>
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
