<?php

declare(strict_types=1);
?>
<div class="card shadow-sm border-0">
    <div class="card-body p-4 p-md-5">
        <p class="text-uppercase text-secondary small fw-semibold mb-2">Authenticated Area</p>
        <h1 class="h3 mb-3">Dashboard</h1>
        <p class="text-secondary mb-4">This is a temporary landing page for signed-in users.</p>

        <dl class="row mb-4">
            <dt class="col-sm-3">Name</dt>
            <dd class="col-sm-9"><?= htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8') ?></dd>

            <dt class="col-sm-3">Role</dt>
            <dd class="col-sm-9 text-capitalize"><?= htmlspecialchars((string) $user['role'], ENT_QUOTES, 'UTF-8') ?></dd>
        </dl>

        <form method="post" action="<?= htmlspecialchars(app_url('/logout'), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <button class="btn btn-outline-danger" type="submit">Log out</button>
        </form>
    </div>
</div>
