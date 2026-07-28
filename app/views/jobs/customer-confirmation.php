<?php

declare(strict_types=1);

$confirmation = $customerConfirmation ?? null;
$confirmationValues = $customerConfirmationValues ?? customer_confirmation_form_values([]);
$confirmationErrors = $customerConfirmationErrors ?? [];
$canRecordConfirmation = user_can_record_job_customer_confirmation($viewer, $job);
$canDeleteConfirmation = user_can_delete_job_customer_confirmation($viewer);
$jobAllowsConfirmation = job_can_accept_customer_confirmation($job);
$signatureError = $confirmationErrors['signature_data'] ?? null;
$generalError = $confirmationErrors['authorization'] ?? $confirmationErrors['status'] ?? $confirmationErrors['duplicate'] ?? $confirmationErrors['job'] ?? null;
?>
<section class="card shadow-sm border-0">
    <div class="card-body p-4">
        <div class="customer-confirmation-header">
            <div>
                <p class="text-uppercase text-secondary small fw-semibold mb-2">Customer Confirmation</p>
                <h2 class="h5 mb-1">Sign-Off</h2>
                <p class="text-secondary mb-0">
                    <?= $confirmation !== null
                        ? 'Customer acceptance has been captured for this completed job.'
                        : 'Capture the customer name and signature once the work is completed.' ?>
                </p>
            </div>
            <?php if ($confirmation !== null && $canDeleteConfirmation): ?>
                <form method="post" action="<?= h(app_url('/jobs/' . $job['id'] . '/customer-confirmation/delete')) ?>" onsubmit="return confirm('Remove this customer confirmation?');">
                    <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                    <button class="btn btn-outline-danger btn-sm" type="submit">Remove Confirmation</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($confirmation !== null): ?>
            <div class="customer-confirmation-card mt-4">
                <div class="customer-confirmation-card__details">
                    <div>
                        <p class="info-label">Customer Name</p>
                        <p class="mb-0"><?= h($confirmation['customer_name']) ?></p>
                    </div>
                    <div>
                        <p class="info-label">Customer Email</p>
                        <p class="mb-0"><?= h(($confirmation['customer_email'] ?? '') !== '' ? $confirmation['customer_email'] : 'Not provided') ?></p>
                    </div>
                    <div>
                        <p class="info-label">Confirmed At</p>
                        <p class="mb-0"><?= h(format_datetime($confirmation['confirmed_at'] ?? null)) ?></p>
                    </div>
                    <div>
                        <p class="info-label">Recorded By</p>
                        <p class="mb-0"><?= h($confirmation['confirmed_by_user_name'] ?: 'Unknown user') ?></p>
                    </div>
                </div>

                <div class="customer-confirmation-signature">
                    <p class="info-label">Signature</p>
                    <a class="customer-confirmation-signature__link" href="<?= h(app_url('/jobs/' . $job['id'] . '/customer-confirmation/signature')) ?>" target="_blank" rel="noreferrer">
                        <img
                            class="customer-confirmation-signature__image"
                            src="<?= h(app_url('/jobs/' . $job['id'] . '/customer-confirmation/signature')) ?>"
                            alt="Customer signature for <?= h($job['job_number']) ?>"
                        >
                    </a>
                </div>
            </div>
        <?php elseif ($canRecordConfirmation): ?>
            <?php if ($generalError !== null): ?>
                <div class="alert alert-danger mt-4 mb-0" role="alert"><?= h($generalError) ?></div>
            <?php elseif (!$jobAllowsConfirmation): ?>
                <div class="alert alert-secondary mt-4 mb-0" role="status">Complete the job before collecting customer confirmation.</div>
            <?php endif; ?>

            <form method="post" action="<?= h(app_url('/jobs/' . $job['id'] . '/customer-confirmation')) ?>" class="customer-confirmation-form mt-4" data-signature-form>
                <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="signature_data" value="<?= h($confirmationValues['signature_data']) ?>" data-signature-output>

                <div>
                    <label class="form-label" for="customer_name">Customer name</label>
                    <input
                        class="form-control<?= isset($confirmationErrors['customer_name']) ? ' is-invalid' : '' ?>"
                        id="customer_name"
                        name="customer_name"
                        type="text"
                        maxlength="255"
                        value="<?= h($confirmationValues['customer_name']) ?>"
                        required
                    >
                    <?php if (isset($confirmationErrors['customer_name'])): ?>
                        <div class="invalid-feedback"><?= h($confirmationErrors['customer_name']) ?></div>
                    <?php endif; ?>
                </div>

                <div>
                    <label class="form-label" for="customer_email">Customer email (optional)</label>
                    <input
                        class="form-control<?= isset($confirmationErrors['customer_email']) ? ' is-invalid' : '' ?>"
                        id="customer_email"
                        name="customer_email"
                        type="email"
                        maxlength="255"
                        value="<?= h($confirmationValues['customer_email']) ?>"
                    >
                    <?php if (isset($confirmationErrors['customer_email'])): ?>
                        <div class="invalid-feedback"><?= h($confirmationErrors['customer_email']) ?></div>
                    <?php endif; ?>
                </div>

                <div>
                    <div class="customer-confirmation-signature-pad<?= $signatureError !== null ? ' is-invalid' : '' ?>" data-signature-pad>
                        <canvas
                            class="customer-confirmation-signature-pad__canvas"
                            data-signature-canvas
                            width="640"
                            height="240"
                            aria-label="Customer signature pad"
                        ></canvas>
                    </div>
                    <div class="form-text">Draw the customer signature using a mouse, finger, or stylus.</div>
                    <div class="customer-confirmation-signature-pad__actions">
                        <button class="btn btn-outline-secondary btn-sm" type="button" data-signature-clear>Clear Signature</button>
                    </div>
                    <?php if ($signatureError !== null): ?>
                        <div class="invalid-feedback d-block"><?= h($signatureError) ?></div>
                    <?php endif; ?>
                </div>

                <div class="customer-confirmation-form__actions">
                    <button class="btn btn-primary" type="submit">Save Customer Confirmation</button>
                </div>
            </form>
        <?php else: ?>
            <div class="alert alert-secondary mt-4 mb-0" role="status">Customer confirmation is only available to administrators, dispatchers, or the assigned worker.</div>
        <?php endif; ?>
    </div>
</section>
