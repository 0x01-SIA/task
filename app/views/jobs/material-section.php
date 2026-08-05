<?php

declare(strict_types=1);

$materialCatalog = array_map(
    static function (array $material): array {
        return [
            'id' => (int) $material['id'],
            'name' => (string) $material['name'],
            'sku' => (string) ($material['sku'] ?? ''),
            'unit' => (string) $material['unit'],
            'is_device' => (int) ($material['is_device'] ?? 0) === 1,
        ];
    },
    $activeMaterials
);
$accessoryCatalog = array_map(
    static function (array $material): array {
        return [
            'id' => (int) $material['id'],
            'label' => material_option_label($material),
        ];
    },
    $allowedDeviceAccessories
);
$materialCatalogJson = h(base64_encode((string) json_encode($materialCatalog)));
$accessoryCatalogJson = h(base64_encode((string) json_encode($accessoryCatalog)));
$usedDeviceEditorOpen = is_array($usedDeviceEditorState ?? null);
$usedDeviceEditorValues = $usedDeviceEditorState['values'] ?? job_material_form_values([]);
$usedDeviceEditorErrors = $usedDeviceEditorState['errors'] ?? [];
$usedDeviceEditorMaterial = $usedDeviceEditorState['material'] ?? null;
$usedDeviceEditorAction = $usedDeviceEditorState['formAction'] ?? $materialRouteBase;
$usedDeviceEditorMode = $usedDeviceEditorState['mode'] ?? 'create';
$showReturnField = ($materialUsageValues['entry_type'] ?? 'used') === 'returned'
    && (
        isset($materialUsageErrors['device_identifier'])
        || trim((string) ($materialUsageValues['device_identifier'] ?? '')) !== ''
    );
?>
<section class="card shadow-sm border-0">
    <div class="card-body p-4">
        <div class="job-assets-header">
            <div>
                <p class="text-uppercase text-secondary small fw-semibold mb-2">Materials</p>
                <h2 class="h5 mb-1">Job Material Movements</h2>
                <p class="text-secondary mb-0">Record materials used on this job or returned back into stock, while keeping the history visible even if catalogue items are later deactivated.</p>
            </div>
        </div>

        <?php if ($canRecordMaterials): ?>
            <?php if ($activeMaterials === []): ?>
                <div class="alert alert-secondary mt-4 mb-0" role="status">No active materials are available.</div>
            <?php else: ?>
                <form
                    method="post"
                    action="<?= h(app_url($materialRouteBase)) ?>"
                    class="row g-3 align-items-end mt-1"
                    data-job-material-form
                    data-material-catalog="<?= $materialCatalogJson ?>"
                >
                    <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                    <div class="col-12 col-lg-8">
                        <label class="form-label" for="material_id">Material</label>
                        <select class="form-select<?= isset($materialUsageErrors['material_id']) ? ' is-invalid' : '' ?>" id="material_id" name="material_id" required data-job-material-select>
                            <option value="">Select a material</option>
                            <?php foreach ($activeMaterials as $material): ?>
                                <option value="<?= h($material['id']) ?>" <?= (int) ($materialUsageValues['material_id'] ?? 0) === (int) $material['id'] ? 'selected' : '' ?>>
                                    <?= h(material_option_label($material)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($materialUsageErrors['material_id'])): ?>
                            <div class="invalid-feedback"><?= h($materialUsageErrors['material_id']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-2">
                        <label class="form-label" for="material_entry_type">Type</label>
                        <select class="form-select<?= isset($materialUsageErrors['entry_type']) ? ' is-invalid' : '' ?>" id="material_entry_type" name="entry_type" required data-job-material-entry-type>
                            <option value="used" <?= ($materialUsageValues['entry_type'] ?? 'used') === 'used' ? 'selected' : '' ?>>Used</option>
                            <option value="returned" <?= ($materialUsageValues['entry_type'] ?? '') === 'returned' ? 'selected' : '' ?>>Returned</option>
                        </select>
                        <?php if (isset($materialUsageErrors['entry_type'])): ?>
                            <div class="invalid-feedback"><?= h($materialUsageErrors['entry_type']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-2">
                        <label class="form-label" for="material_quantity">Quantity</label>
                        <input class="form-control<?= isset($materialUsageErrors['quantity']) ? ' is-invalid' : '' ?>" id="material_quantity" name="quantity" type="text" value="<?= h($materialUsageValues['quantity'] ?? '') ?>" inputmode="decimal" placeholder="0.00" required data-job-material-quantity>
                        <?php if (isset($materialUsageErrors['quantity'])): ?>
                            <div class="invalid-feedback"><?= h($materialUsageErrors['quantity']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-12 col-lg-4 device-return-field<?= $showReturnField ? ' is-visible' : '' ?>" data-device-return-field <?= $showReturnField ? '' : 'hidden' ?>>
                        <label class="form-label" for="device_identifier">Returned device ID</label>
                        <input class="form-control<?= isset($materialUsageErrors['device_identifier']) ? ' is-invalid' : '' ?>" id="device_identifier" name="device_identifier" type="text" maxlength="255" value="<?= h($materialUsageValues['device_identifier'] ?? '') ?>">
                        <?php if (isset($materialUsageErrors['device_identifier'])): ?>
                            <div class="invalid-feedback"><?= h($materialUsageErrors['device_identifier']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-2">
                        <button class="btn btn-primary w-100" type="submit">Add Material</button>
                    </div>
                </form>

                <section
                    class="device-editor mt-4<?= $usedDeviceEditorOpen ? ' is-open' : '' ?>"
                    data-used-device-editor
                    data-accessory-catalog="<?= $accessoryCatalogJson ?>"
                    <?= $usedDeviceEditorOpen ? '' : 'hidden' ?>
                >
                    <div class="device-editor__header">
                        <div>
                            <p class="text-uppercase text-secondary small fw-semibold mb-2">Device Install</p>
                            <h3 class="h5 mb-1"><?= $usedDeviceEditorMode === 'edit' ? 'Edit installed device' : 'Add installed device' ?></h3>
                            <p class="text-secondary mb-0">
                                <?php if ($usedDeviceEditorMaterial !== null): ?>
                                    <?= h(material_option_label($usedDeviceEditorMaterial)) ?> · Quantity fixed to 1
                                <?php else: ?>
                                    Quantity is fixed to 1 for device materials.
                                <?php endif; ?>
                            </p>
                        </div>
                        <button class="btn btn-outline-secondary btn-sm" type="button" data-used-device-editor-close>Cancel</button>
                    </div>

                    <form method="post" action="<?= h(app_url($usedDeviceEditorAction)) ?>" class="row g-3 mt-1" data-used-device-editor-form>
                        <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="material_id" value="<?= h((string) ($usedDeviceEditorValues['material_id'] ?? '')) ?>" data-used-device-material-id>
                        <input type="hidden" name="entry_type" value="used">
                        <input type="hidden" name="quantity" value="1.000">
                        <div class="col-12 col-lg-6">
                            <label class="form-label" for="used_device_identifier">Device ID</label>
                            <input class="form-control<?= isset($usedDeviceEditorErrors['device_identifier']) ? ' is-invalid' : '' ?>" id="used_device_identifier" name="device_identifier" type="text" maxlength="255" value="<?= h($usedDeviceEditorValues['device_identifier'] ?? '') ?>" required>
                            <?php if (isset($usedDeviceEditorErrors['device_identifier'])): ?>
                                <div class="invalid-feedback"><?= h($usedDeviceEditorErrors['device_identifier']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12 col-lg-6">
                            <label class="form-label" for="object_name">Object name</label>
                            <input class="form-control<?= isset($usedDeviceEditorErrors['object_name']) ? ' is-invalid' : '' ?>" id="object_name" name="object_name" type="text" maxlength="255" value="<?= h($usedDeviceEditorValues['object_name'] ?? '') ?>" required>
                            <?php if (isset($usedDeviceEditorErrors['object_name'])): ?>
                                <div class="invalid-feedback"><?= h($usedDeviceEditorErrors['object_name']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <label class="form-label mb-0">Connected accessories</label>
                                <button class="btn btn-outline-secondary btn-sm" type="button" data-add-accessory-row>Add accessory</button>
                            </div>
                            <div class="device-editor__hint">Add any configured accessory materials here. Quantities are recorded as normal spent materials on the same job.</div>
                            <?php if (isset($usedDeviceEditorErrors['accessories']) && is_array($usedDeviceEditorErrors['accessories'])): ?>
                                <div class="alert alert-danger mt-3 mb-0">
                                    <?php foreach ($usedDeviceEditorErrors['accessories'] as $accessoryError): ?>
                                        <div><?= h($accessoryError) ?></div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <div class="device-accessory-list mt-3" data-accessory-rows>
                                <?php foreach (($usedDeviceEditorValues['accessories'] ?? []) as $row): ?>
                                    <div class="device-accessory-row">
                                        <select class="form-select" name="accessory_material_id[]">
                                            <option value="">Select accessory</option>
                                            <?php foreach ($allowedDeviceAccessories as $accessoryMaterial): ?>
                                                <option value="<?= h($accessoryMaterial['id']) ?>" <?= (int) ($row['material_id'] ?? 0) === (int) $accessoryMaterial['id'] ? 'selected' : '' ?>>
                                                    <?= h(material_option_label($accessoryMaterial)) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input class="form-control" name="accessory_quantity[]" type="text" inputmode="decimal" placeholder="Qty" value="<?= h($row['quantity'] ?? '') ?>">
                                        <button class="btn btn-outline-danger btn-sm" type="button" data-remove-accessory-row>Remove</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <p class="text-secondary small mt-3 mb-0<?= $allowedDeviceAccessories === [] ? '' : ' d-none' ?>" data-accessory-empty-state>No device accessories are configured yet.</p>
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2">
                            <button class="btn btn-primary" type="submit">Save device</button>
                            <button class="btn btn-outline-secondary" type="button" data-used-device-editor-close>Cancel</button>
                        </div>
                    </form>
                </section>
            <?php endif; ?>
        <?php endif; ?>

        <div class="table-responsive mt-4">
            <?php if ($jobMaterials === []): ?>
                <p class="text-secondary mb-0">No materials have been recorded for this job.</p>
            <?php else: ?>
                <table class="table align-middle mb-0">
                    <thead>
                    <tr>
                        <th scope="col">Material</th>
                        <th scope="col">Type</th>
                        <th scope="col">Quantity</th>
                        <th scope="col">Recorded By</th>
                        <th scope="col">Recorded</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($jobMaterials as $jobMaterial): ?>
                        <?php
                        $editErrors = $materialEditErrors[(int) $jobMaterial['id']] ?? [];
                        $editValues = $materialEditValues[(int) $jobMaterial['id']] ?? [];
                        $isDevice = (int) ($jobMaterial['material_is_device'] ?? 0) === 1;
                        $installation = $deviceInstallationsByUsage[(int) $jobMaterial['id']] ?? null;
                        $editPayload = $isDevice && (string) ($jobMaterial['entry_type'] ?? '') === 'used'
                            ? h(base64_encode((string) json_encode([
                                'formAction' => $materialRouteBase . '/' . $jobMaterial['id'] . '/edit',
                                'material_id' => (int) $jobMaterial['material_id'],
                                'material_label' => material_option_label($jobMaterial),
                                'device_identifier' => $installation['device_identifier'] ?? '',
                                'object_name' => $installation['object_name'] ?? '',
                                'accessories' => array_map(
                                    static fn (array $accessory): array => [
                                        'material_id' => (int) $accessory['accessory_material_id'],
                                        'quantity' => format_decimal_quantity((string) $accessory['quantity']),
                                    ],
                                    $installation['accessories'] ?? []
                                ),
                            ])))
                            : '';
                        ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= h($jobMaterial['material_name']) ?></div>
                                <div class="small text-secondary">
                                    <?= h(((string) ($jobMaterial['material_sku'] ?? '') !== '' ? $jobMaterial['material_sku'] : 'No SKU/code') . ' - ' . $jobMaterial['material_unit']) ?>
                                    <?php if ((int) ($jobMaterial['material_is_active'] ?? 0) !== 1): ?>
                                        · Inactive
                                    <?php endif; ?>
                                </div>
                                <?php if ($isDevice): ?>
                                    <div class="device-material-summary mt-2">
                                        <?php if ((string) ($jobMaterial['entry_type'] ?? '') === 'used'): ?>
                                            <?php if ($installation !== null): ?>
                                                <div><strong>Device ID:</strong> <?= h($installation['device_identifier']) ?></div>
                                                <div><strong>Object:</strong> <?= h($installation['object_name']) ?></div>
                                                <div><strong>Accessories:</strong></div>
                                                <?php if (($installation['accessories'] ?? []) === []): ?>
                                                    <div class="text-secondary">None</div>
                                                <?php else: ?>
                                                    <?php foreach ($installation['accessories'] as $accessory): ?>
                                                        <div><?= h(($accessory['accessory_material_sku'] ?: $accessory['accessory_material_name']) . ' - ' . format_decimal_quantity((string) $accessory['quantity'])) ?></div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <div class="text-secondary">No installation details recorded for this legacy device entry.</div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div><strong>Returned device ID:</strong> <?= h((string) ($jobMaterial['device_identifier'] ?? 'Not provided')) ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?= h(job_material_entry_type_label((string) ($jobMaterial['entry_type'] ?? 'used'))) ?></td>
                            <td><?= h(format_decimal_quantity($jobMaterial['quantity']) . ' ' . $jobMaterial['material_unit']) ?></td>
                            <td><?= h($jobMaterial['recorded_by_name'] ?: 'Unknown user') ?></td>
                            <td><?= h(format_datetime($jobMaterial['updated_at'] ?? $jobMaterial['created_at'] ?? null)) ?></td>
                            <td class="text-end">
                                <?php if ($canModifyMaterials): ?>
                                    <div class="job-material-actions">
                                        <?php if ($isDevice && (string) ($jobMaterial['entry_type'] ?? '') === 'used'): ?>
                                            <button class="btn btn-outline-primary btn-sm" type="button" data-open-used-device-editor="<?= $editPayload ?>">Edit Device</button>
                                        <?php elseif ($isDevice): ?>
                                            <form method="post" action="<?= h(app_url($materialRouteBase . '/' . $jobMaterial['id'] . '/edit')) ?>" class="job-material-inline-form">
                                                <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                                                <input type="hidden" name="entry_type" value="returned">
                                                <input type="hidden" name="quantity" value="1.000">
                                                <input class="form-control form-control-sm<?= isset($editErrors['device_identifier']) ? ' is-invalid' : '' ?>" name="device_identifier" type="text" maxlength="255" value="<?= h($editValues['device_identifier'] ?? $jobMaterial['device_identifier'] ?? '') ?>" placeholder="Returned device ID" aria-label="Returned device ID for <?= h($jobMaterial['material_name']) ?>">
                                                <button class="btn btn-outline-primary btn-sm" type="submit">Update</button>
                                                <?php if (isset($editErrors['device_identifier'])): ?>
                                                    <div class="invalid-feedback d-block text-start"><?= h($editErrors['device_identifier']) ?></div>
                                                <?php endif; ?>
                                            </form>
                                        <?php else: ?>
                                            <?php $editError = $editErrors['quantity'] ?? null; ?>
                                            <?php $editTypeError = $editErrors['entry_type'] ?? null; ?>
                                            <?php $editValue = $editValues['quantity'] ?? format_decimal_quantity($jobMaterial['quantity']); ?>
                                            <?php $editEntryType = $editValues['entry_type'] ?? (string) ($jobMaterial['entry_type'] ?? 'used'); ?>
                                            <form method="post" action="<?= h(app_url($materialRouteBase . '/' . $jobMaterial['id'] . '/edit')) ?>" class="job-material-inline-form">
                                                <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                                                <select class="form-select form-select-sm<?= $editTypeError !== null ? ' is-invalid' : '' ?>" name="entry_type" aria-label="Type for <?= h($jobMaterial['material_name']) ?>">
                                                    <option value="used" <?= $editEntryType === 'used' ? 'selected' : '' ?>>Used</option>
                                                    <option value="returned" <?= $editEntryType === 'returned' ? 'selected' : '' ?>>Returned</option>
                                                </select>
                                                <input class="form-control form-control-sm<?= $editError !== null ? ' is-invalid' : '' ?>" name="quantity" type="text" value="<?= h($editValue) ?>" inputmode="decimal" aria-label="Quantity for <?= h($jobMaterial['material_name']) ?>">
                                                <button class="btn btn-outline-primary btn-sm" type="submit">Update</button>
                                                <?php if ($editTypeError !== null): ?>
                                                    <div class="invalid-feedback d-block text-start"><?= h($editTypeError) ?></div>
                                                <?php endif; ?>
                                                <?php if ($editError !== null): ?>
                                                    <div class="invalid-feedback d-block text-start"><?= h($editError) ?></div>
                                                <?php endif; ?>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post" action="<?= h(app_url($materialRouteBase . '/' . $jobMaterial['id'] . '/delete')) ?>" onsubmit="return confirm('Remove this job material entry?');">
                                            <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                                            <button class="btn btn-outline-danger btn-sm" type="submit">Remove</button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span class="text-secondary small">Read only</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</section>
