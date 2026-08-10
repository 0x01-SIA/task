<?php

declare(strict_types=1);

function materials_connection(): PDO
{
    $connection = database_connection();

    if ($connection === null) {
        throw new RuntimeException('Database connection is unavailable.');
    }

    return $connection;
}

function list_materials(array $filters = []): array
{
    $companyId = current_company_id();

    return $companyId === null ? [] : company_material_stock_list($companyId, $filters);
}

function list_active_materials(): array
{
    $params = [];
    $sql = 'SELECT
                id,
                company_id,
                name,
                sku,
                unit,
                description,
                is_device,
                is_device_accessory,
                is_active,
                created_at,
                updated_at
            FROM materials
            WHERE is_active = 1';
    $sql .= scoped_company_sql('company_id', $params);
    $sql .= ' ORDER BY name ASC, id ASC';
    $statement = materials_connection()->prepare($sql);
    $statement->execute($params);
    $materials = $statement->fetchAll();

    return is_array($materials) ? $materials : [];
}

function find_material_by_id(int $id): ?array
{
    $params = ['id' => $id];
    $sql = 'SELECT
                id,
                company_id,
                name,
                sku,
                unit,
                description,
                is_device,
                is_device_accessory,
                is_active,
                created_at,
                updated_at
            FROM materials
            WHERE id = :id';
    $sql .= scoped_company_sql('company_id', $params);
    $sql .= ' LIMIT 1';
    $statement = materials_connection()->prepare($sql);
    $statement->execute($params);
    $material = $statement->fetch();

    return is_array($material) ? $material : null;
}

function count_material_movements(int $materialId): int
{
    $companyId = current_company_id();

    return $companyId === null ? 0 : count_material_movement_history($companyId, $materialId);
}

function list_material_movements(int $materialId, int $limit, int $offset): array
{
    $companyId = current_company_id();

    return $companyId === null
        ? []
        : material_movement_history($companyId, $materialId, [], $limit, $offset);
}

function create_material(array $data): int
{
    $companyId = require_material_company_id($data);

    $statement = materials_connection()->prepare(
        'INSERT INTO materials (
            company_id,
            name,
            sku,
            unit,
            description,
            is_device,
            is_device_accessory,
            is_active
         ) VALUES (
            :company_id,
            :name,
            :sku,
            :unit,
            :description,
            :is_device,
            :is_device_accessory,
            :is_active
         )'
    );
    $statement->execute([
        'company_id' => $companyId,
        'name' => $data['name'],
        'sku' => $data['sku'],
        'unit' => $data['unit'],
        'description' => $data['description'],
        'is_device' => $data['is_device'],
        'is_device_accessory' => $data['is_device_accessory'],
        'is_active' => $data['is_active'],
    ]);

    return (int) materials_connection()->lastInsertId();
}

function update_material(int $id, array $data): void
{
    $companyId = require_material_company_id($data);

    $statement = materials_connection()->prepare(
        'UPDATE materials
         SET name = :name,
             sku = :sku,
             unit = :unit,
             description = :description,
             is_device = :is_device,
             is_device_accessory = :is_device_accessory,
             is_active = :is_active
         WHERE id = :id
           AND company_id = :company_id'
    );
    $statement->execute([
        'id' => $id,
        'company_id' => $companyId,
        'name' => $data['name'],
        'sku' => $data['sku'],
        'unit' => $data['unit'],
        'description' => $data['description'],
        'is_device' => $data['is_device'],
        'is_device_accessory' => $data['is_device_accessory'],
        'is_active' => $data['is_active'],
    ]);
}

function set_material_active_status(int $id, bool $isActive): void
{
    $sql = 'UPDATE materials
            SET is_active = :is_active
            WHERE id = :id';
    $params = [
        'id' => $id,
        'is_active' => $isActive ? 1 : 0,
    ];
    $sql .= scoped_company_sql('company_id', $params);
    $statement = materials_connection()->prepare($sql);
    $statement->execute($params);
}

function list_job_materials(int $jobId): array
{
    $sql = 'SELECT
            jm.id,
            jm.job_id,
            jm.material_id,
            jm.movement_id,
            jm.entry_type,
            jm.quantity,
            jm.device_identifier,
            jm.recorded_by_user_id,
            jm.occurred_at,
            jm.created_at,
            jm.updated_at,
            m.name AS material_name,
            m.sku AS material_sku,
            m.unit AS material_unit,
            m.is_device AS material_is_device,
            m.is_active AS material_is_active,
            u.name AS recorded_by_name
         FROM job_materials jm
         INNER JOIN materials m ON m.id = jm.material_id
         LEFT JOIN users u ON u.id = jm.recorded_by_user_id
         WHERE jm.job_id = :job_id';
    $params = ['job_id' => $jobId];
    $sql .= scoped_company_sql('jm.company_id', $params);
    $sql .= ' ORDER BY jm.occurred_at DESC, jm.id DESC';
    $statement = materials_connection()->prepare($sql);
    $statement->execute($params);
    $materials = $statement->fetchAll();

    return is_array($materials) ? $materials : [];
}

function find_job_material_by_id(int $jobId, int $jobMaterialId): ?array
{
    $sql = 'SELECT
            jm.id,
            jm.job_id,
            jm.material_id,
            jm.movement_id,
            jm.entry_type,
            jm.quantity,
            jm.device_identifier,
            jm.recorded_by_user_id,
            jm.occurred_at,
            jm.created_at,
            jm.updated_at,
            m.name AS material_name,
            m.sku AS material_sku,
            m.unit AS material_unit,
            m.is_device AS material_is_device,
            m.is_active AS material_is_active,
            u.name AS recorded_by_name
         FROM job_materials jm
         INNER JOIN materials m ON m.id = jm.material_id
         LEFT JOIN users u ON u.id = jm.recorded_by_user_id
         WHERE jm.job_id = :job_id
           AND jm.id = :id';
    $params = [
        'job_id' => $jobId,
        'id' => $jobMaterialId,
    ];
    $sql .= scoped_company_sql('jm.company_id', $params);
    $sql .= ' LIMIT 1';
    $statement = materials_connection()->prepare($sql);
    $statement->execute($params);
    $jobMaterial = $statement->fetch();

    return is_array($jobMaterial) ? $jobMaterial : null;
}

function add_job_material_entry(
    int $jobId,
    int $materialId,
    string $entryType,
    string $quantity,
    ?int $recordedByUserId,
    array $deviceDetails = []
): void {
    $connection = materials_connection();
    $companyId = current_company_id();

    if ($companyId === null) {
        throw new RuntimeException('An active company is required to record material usage.');
    }

    $material = find_material_by_id($materialId);

    if ($material === null) {
        throw new InvalidArgumentException('The selected material is not available.');
    }

    if (material_is_device($material) && $quantity !== fixed_device_quantity()) {
        throw new InvalidArgumentException('Device materials must be recorded one unit at a time.');
    }

    $connection->beginTransaction();

    try {
        $occurredAt = utc_timestamp();
        $jobMaterialId = create_basic_job_material_entry(
            $connection,
            $companyId,
            $jobId,
            $materialId,
            $entryType,
            $quantity,
            $recordedByUserId,
            $occurredAt,
            $entryType === 'returned' ? trimmed_device_identifier($deviceDetails['device_identifier'] ?? null) : null
        );

        if (material_is_device($material) && $entryType === 'used') {
            save_device_installation_details(
                $connection,
                $companyId,
                $jobId,
                $jobMaterialId,
                $material,
                $recordedByUserId,
                $deviceDetails
            );
        }

        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        throw $exception;
    }
}

function update_job_material_entry(
    int $jobId,
    int $jobMaterialId,
    string $entryType,
    string $quantity,
    ?int $recordedByUserId,
    array $deviceDetails = []
): bool {
    $companyId = current_company_id();

    if ($companyId === null) {
        return false;
    }

    $jobMaterial = find_job_material_by_id($jobId, $jobMaterialId);

    if ($jobMaterial === null) {
        return false;
    }

    $isDevice = job_material_is_device($jobMaterial);

    if ($isDevice && $quantity !== fixed_device_quantity()) {
        throw new InvalidArgumentException('Device materials must be recorded one unit at a time.');
    }

    if ($isDevice && $entryType !== (string) $jobMaterial['entry_type']) {
        throw new InvalidArgumentException('Changing the direction of a device material entry is not supported.');
    }

    $connection = materials_connection();
    $connection->beginTransaction();

    try {
        $updated = update_basic_job_material_entry(
            $connection,
            $companyId,
            $jobMaterial,
            $entryType,
            $quantity,
            $recordedByUserId,
            $isDevice && $entryType === 'returned' ? trimmed_device_identifier($deviceDetails['device_identifier'] ?? null) : null
        );

        if ($isDevice && $entryType === 'used') {
            $material = find_material_by_id((int) $jobMaterial['material_id']);

            if ($material === null) {
                throw new InvalidArgumentException('The selected device material is no longer available.');
            }

            save_device_installation_details(
                $connection,
                $companyId,
                $jobId,
                $jobMaterialId,
                $material,
                $recordedByUserId,
                $deviceDetails
            );
        }

        $connection->commit();

        return $updated;
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        throw $exception;
    }
}

function delete_job_material(int $jobId, int $jobMaterialId, ?int $actingUserId = null): bool
{
    $companyId = current_company_id();

    if ($companyId === null) {
        return false;
    }

    $jobMaterial = find_job_material_by_id($jobId, $jobMaterialId);

    if ($jobMaterial === null) {
        return false;
    }

    $connection = materials_connection();
    $connection->beginTransaction();

    try {
        if (job_material_is_device($jobMaterial) && (string) $jobMaterial['entry_type'] === 'used') {
            $deleted = delete_device_installation_cascade($connection, $companyId, $jobId, $jobMaterial, $actingUserId);
        } elseif (($accessoryLink = find_device_installation_accessory_by_usage_id($companyId, $jobMaterialId)) !== null) {
            $deleted = delete_device_installation_accessory_usage($connection, $companyId, $jobId, $jobMaterial, $accessoryLink, $actingUserId);
        } else {
            $deleted = delete_basic_job_material_entry($connection, $companyId, $jobId, $jobMaterial, $actingUserId);
        }

        $connection->commit();

        return $deleted;
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        throw $exception;
    }
}

function create_basic_job_material_entry(
    PDO $connection,
    int $companyId,
    int $jobId,
    int $materialId,
    string $entryType,
    string $quantity,
    ?int $recordedByUserId,
    string $occurredAt,
    ?string $deviceIdentifier = null
): int {
    $statement = $connection->prepare(
        'INSERT INTO job_materials (
            company_id,
            job_id,
            material_id,
            quantity,
            entry_type,
            device_identifier,
            recorded_by_user_id,
            occurred_at
         ) VALUES (
            :company_id,
            :job_id,
            :material_id,
            :quantity,
            :entry_type,
            :device_identifier,
            :recorded_by_user_id,
            :occurred_at
         )'
    );
    $statement->execute([
        'company_id' => $companyId,
        'job_id' => $jobId,
        'material_id' => $materialId,
        'quantity' => $quantity,
        'entry_type' => $entryType,
        'device_identifier' => $deviceIdentifier,
        'recorded_by_user_id' => $recordedByUserId,
        'occurred_at' => $occurredAt,
    ]);

    $jobMaterialId = (int) $connection->lastInsertId();
    $movementId = create_material_movement([
        'company_id' => $companyId,
        'material_id' => $materialId,
        'movement_type' => job_material_entry_movement_type($entryType),
        'quantity' => $quantity,
        'job_id' => $jobId,
        'job_material_id' => $jobMaterialId,
        'created_by_user_id' => $recordedByUserId,
        'note' => '',
        'occurred_at' => $occurredAt,
    ]);

    $linkStatement = $connection->prepare(
        'UPDATE job_materials
         SET movement_id = :movement_id
         WHERE id = :id
           AND company_id = :company_id'
    );
    $linkStatement->execute([
        'movement_id' => $movementId,
        'id' => $jobMaterialId,
        'company_id' => $companyId,
    ]);

    return $jobMaterialId;
}

function update_basic_job_material_entry(
    PDO $connection,
    int $companyId,
    array $jobMaterial,
    string $entryType,
    string $quantity,
    ?int $recordedByUserId,
    ?string $deviceIdentifier = null
): bool {
    $movement = find_linked_job_material_movement($companyId, $jobMaterial);

    if ($movement === null) {
        error_log(sprintf(
            '[job_materials.update] Linked movement could not be resolved (job_id=%d, job_material_id=%d, material_id=%d, movement_id=%s, company_id=%s, user_id=%s)',
            (int) $jobMaterial['job_id'],
            (int) $jobMaterial['id'],
            (int) $jobMaterial['material_id'],
            $jobMaterial['movement_id'] !== null ? (string) $jobMaterial['movement_id'] : 'null',
            $companyId,
            $recordedByUserId !== null ? (string) $recordedByUserId : 'null'
        ));

        return false;
    }

    if (material_movement_is_protected($companyId, (int) $jobMaterial['material_id'], (string) $jobMaterial['occurred_at'])) {
        return false;
    }

    $statement = $connection->prepare(
        'UPDATE job_materials
         SET quantity = :quantity,
             entry_type = :entry_type,
             device_identifier = :device_identifier,
             recorded_by_user_id = :recorded_by_user_id,
             updated_at = CURRENT_TIMESTAMP
         WHERE company_id = :company_id
           AND job_id = :job_id
           AND id = :id'
    );
    $statement->execute([
        'quantity' => $quantity,
        'entry_type' => $entryType,
        'device_identifier' => $deviceIdentifier,
        'recorded_by_user_id' => $recordedByUserId,
        'company_id' => $companyId,
        'job_id' => (int) $jobMaterial['job_id'],
        'id' => (int) $jobMaterial['id'],
    ]);

    $movementStatement = $connection->prepare(
        'UPDATE material_movements
         SET movement_type = :movement_type,
             quantity = :quantity,
             created_by_user_id = :created_by_user_id
         WHERE id = :movement_id
           AND company_id = :company_id'
    );
    $movementStatement->execute([
        'movement_type' => job_material_entry_movement_type($entryType),
        'quantity' => $quantity,
        'created_by_user_id' => $recordedByUserId,
        'movement_id' => (int) $movement['id'],
        'company_id' => $companyId,
    ]);

    return true;
}

function delete_basic_job_material_entry(
    PDO $connection,
    int $companyId,
    int $jobId,
    array $jobMaterial,
    ?int $actingUserId = null
): bool
{
    $movement = find_linked_job_material_movement($companyId, $jobMaterial);

    if ($movement === null) {
        error_log(sprintf(
            '[job_materials.delete] Linked movement could not be resolved (job_id=%d, job_material_id=%d, material_id=%d, movement_id=%s, company_id=%s)',
            $jobId,
            (int) $jobMaterial['id'],
            (int) $jobMaterial['material_id'],
            $jobMaterial['movement_id'] !== null ? (string) $jobMaterial['movement_id'] : 'null',
            $companyId
        ));

        return false;
    }

    $movementProtected = material_movement_is_protected($companyId, (int) $jobMaterial['material_id'], (string) $jobMaterial['occurred_at']);

    $movementId = (int) $movement['id'];

    $unlinkJobMaterialStatement = $connection->prepare(
        'UPDATE job_materials
         SET movement_id = NULL
         WHERE company_id = :company_id
           AND job_id = :job_id
           AND id = :id'
    );
    $unlinkJobMaterialStatement->execute([
        'company_id' => $companyId,
        'job_id' => $jobId,
        'id' => (int) $jobMaterial['id'],
    ]);

    $unlinkMovementStatement = $connection->prepare(
        'UPDATE material_movements
         SET job_material_id = NULL
         WHERE id = :movement_id
           AND company_id = :company_id'
    );
    $unlinkMovementStatement->execute([
        'movement_id' => $movementId,
        'company_id' => $companyId,
    ]);

    if ($movementProtected) {
        create_job_material_removal_reversal(
            $companyId,
            $jobMaterial,
            $movement,
            $actingUserId
        );
    } else {
        $movementStatement = $connection->prepare(
            'DELETE FROM material_movements
             WHERE id = :movement_id
               AND company_id = :company_id'
        );
        $movementStatement->execute([
            'movement_id' => $movementId,
            'company_id' => $companyId,
        ]);
    }

    $statement = $connection->prepare(
        'DELETE FROM job_materials
         WHERE company_id = :company_id
           AND job_id = :job_id
           AND id = :id'
    );
    $statement->execute([
        'company_id' => $companyId,
        'job_id' => $jobId,
        'id' => (int) $jobMaterial['id'],
    ]);

    return $statement->rowCount() > 0;
}

function delete_device_installation_accessory_usage(
    PDO $connection,
    int $companyId,
    int $jobId,
    array $jobMaterial,
    array $accessoryLink,
    ?int $actingUserId = null
): bool {
    $statement = $connection->prepare(
        'DELETE FROM device_installation_accessories
         WHERE id = :id
           AND company_id = :company_id
           AND device_installation_id = :device_installation_id
           AND accessory_material_usage_id = :accessory_material_usage_id'
    );
    $statement->execute([
        'id' => (int) $accessoryLink['id'],
        'company_id' => $companyId,
        'device_installation_id' => (int) $accessoryLink['device_installation_id'],
        'accessory_material_usage_id' => (int) $accessoryLink['accessory_material_usage_id'],
    ]);

    if ($statement->rowCount() !== 1) {
        error_log(sprintf(
            '[job_materials.delete] Accessory link could not be removed (job_id=%d, job_material_id=%d, accessory_link_id=%d, device_installation_id=%d, company_id=%d)',
            $jobId,
            (int) $jobMaterial['id'],
            (int) $accessoryLink['id'],
            (int) $accessoryLink['device_installation_id'],
            $companyId
        ));

        return false;
    }

    return delete_basic_job_material_entry($connection, $companyId, $jobId, $jobMaterial, $actingUserId);
}

function save_device_installation_details(
    PDO $connection,
    int $companyId,
    int $jobId,
    int $jobMaterialId,
    array $material,
    ?int $recordedByUserId,
    array $deviceDetails
): void {
    $deviceIdentifier = trimmed_device_identifier($deviceDetails['device_identifier'] ?? null);
    $objectName = trimmed_device_object_name($deviceDetails['object_name'] ?? null);

    if ($deviceIdentifier === null || $objectName === null) {
        throw new InvalidArgumentException('Device installation details are incomplete.');
    }

    $installation = find_device_installation_by_usage_id($companyId, $jobMaterialId);

    if ($installation === null) {
        $statement = $connection->prepare(
            'INSERT INTO device_installations (
                company_id,
                job_id,
                device_material_usage_id,
                device_material_id,
                device_identifier,
                object_name,
                created_by
             ) VALUES (
                :company_id,
                :job_id,
                :device_material_usage_id,
                :device_material_id,
                :device_identifier,
                :object_name,
                :created_by
             )'
        );
        $statement->execute([
            'company_id' => $companyId,
            'job_id' => $jobId,
            'device_material_usage_id' => $jobMaterialId,
            'device_material_id' => (int) $material['id'],
            'device_identifier' => $deviceIdentifier,
            'object_name' => $objectName,
            'created_by' => $recordedByUserId,
        ]);
        $installationId = (int) $connection->lastInsertId();
    } else {
        $statement = $connection->prepare(
            'UPDATE device_installations
             SET device_identifier = :device_identifier,
                 object_name = :object_name,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id
               AND company_id = :company_id'
        );
        $statement->execute([
            'device_identifier' => $deviceIdentifier,
            'object_name' => $objectName,
            'id' => (int) $installation['id'],
            'company_id' => $companyId,
        ]);
        $installationId = (int) $installation['id'];
    }

    sync_device_installation_accessories(
        $connection,
        $companyId,
        $jobId,
        $installationId,
        $recordedByUserId,
        $deviceDetails['accessories'] ?? []
    );
}

function sync_device_installation_accessories(
    PDO $connection,
    int $companyId,
    int $jobId,
    int $installationId,
    ?int $recordedByUserId,
    array $submittedAccessories
): void {
    $existingAccessories = list_device_installation_accessory_links($companyId, $installationId);
    $existingByMaterial = [];

    foreach ($existingAccessories as $existingAccessory) {
        $existingByMaterial[(int) $existingAccessory['accessory_material_id']] = $existingAccessory;
    }

    $seenMaterialIds = [];

    foreach ($submittedAccessories as $accessory) {
        $materialId = (int) ($accessory['material_id'] ?? 0);
        $quantity = (string) ($accessory['quantity'] ?? '');

        if ($materialId <= 0) {
            continue;
        }

        $seenMaterialIds[$materialId] = true;

        if (isset($existingByMaterial[$materialId])) {
            $existing = $existingByMaterial[$materialId];
            $jobMaterial = find_job_material_by_id($jobId, (int) $existing['accessory_material_usage_id']);

            if ($jobMaterial === null) {
                throw new RuntimeException('An existing accessory usage could not be found.');
            }

            if (!update_basic_job_material_entry($connection, $companyId, $jobMaterial, 'used', $quantity, $recordedByUserId, null)) {
                throw new RuntimeException('An existing accessory usage could not be updated safely.');
            }

            $statement = $connection->prepare(
                'UPDATE device_installation_accessories
                 SET quantity = :quantity,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id
                   AND company_id = :company_id'
            );
            $statement->execute([
                'quantity' => $quantity,
                'id' => (int) $existing['id'],
                'company_id' => $companyId,
            ]);

            continue;
        }

        $occurredAt = utc_timestamp();
        $accessoryJobMaterialId = create_basic_job_material_entry(
            $connection,
            $companyId,
            $jobId,
            $materialId,
            'used',
            $quantity,
            $recordedByUserId,
            $occurredAt
        );

        $statement = $connection->prepare(
            'INSERT INTO device_installation_accessories (
                company_id,
                device_installation_id,
                accessory_material_id,
                accessory_material_usage_id,
                quantity
             ) VALUES (
                :company_id,
                :device_installation_id,
                :accessory_material_id,
                :accessory_material_usage_id,
                :quantity
             )'
        );
        $statement->execute([
            'company_id' => $companyId,
            'device_installation_id' => $installationId,
            'accessory_material_id' => $materialId,
            'accessory_material_usage_id' => $accessoryJobMaterialId,
            'quantity' => $quantity,
        ]);
    }

    foreach ($existingAccessories as $existingAccessory) {
        $materialId = (int) $existingAccessory['accessory_material_id'];

        if (isset($seenMaterialIds[$materialId])) {
            continue;
        }

        $jobMaterial = find_job_material_by_id($jobId, (int) $existingAccessory['accessory_material_usage_id']);

        if ($jobMaterial === null
            || !delete_device_installation_accessory_usage($connection, $companyId, $jobId, $jobMaterial, $existingAccessory, $recordedByUserId)) {
            throw new RuntimeException('A linked accessory usage could not be removed safely.');
        }
    }
}

function delete_device_installation_cascade(
    PDO $connection,
    int $companyId,
    int $jobId,
    array $jobMaterial,
    ?int $actingUserId = null
): bool
{
    $installation = find_device_installation_by_usage_id($companyId, (int) $jobMaterial['id']);

    if ($installation !== null) {
        foreach (list_device_installation_accessory_links($companyId, (int) $installation['id']) as $accessoryLink) {
            $accessoryJobMaterial = find_job_material_by_id($jobId, (int) $accessoryLink['accessory_material_usage_id']);

            if ($accessoryJobMaterial === null
                || !delete_device_installation_accessory_usage($connection, $companyId, $jobId, $accessoryJobMaterial, $accessoryLink, $actingUserId)) {
                throw new RuntimeException('A linked accessory usage could not be removed safely.');
            }
        }

        $statement = $connection->prepare(
            'DELETE FROM device_installations
             WHERE id = :id
               AND company_id = :company_id'
        );
        $statement->execute([
            'id' => (int) $installation['id'],
            'company_id' => $companyId,
        ]);
    }

    return delete_basic_job_material_entry($connection, $companyId, $jobId, $jobMaterial, $actingUserId);
}

function create_job_material_removal_reversal(
    int $companyId,
    array $jobMaterial,
    array $movement,
    ?int $actingUserId = null
): void {
    $movementType = (string) ($movement['movement_type'] ?? '');

    if (!in_array($movementType, ['in', 'out'], true)) {
        throw new RuntimeException('The linked stock movement could not be reversed safely.');
    }

    create_material_movement([
        'company_id' => $companyId,
        'material_id' => (int) $jobMaterial['material_id'],
        'movement_type' => $movementType === 'in' ? 'out' : 'in',
        'quantity' => (string) $jobMaterial['quantity'],
        'job_id' => (int) $jobMaterial['job_id'],
        'job_material_id' => null,
        'created_by_user_id' => $actingUserId,
        'note' => sprintf(
            'Reversal for removed job material #%d (original movement #%d).',
            (int) $jobMaterial['id'],
            (int) $movement['id']
        ),
        'occurred_at' => utc_timestamp(),
    ]);
}

function find_linked_job_material_movement(int $companyId, array $jobMaterial): ?array
{
    $connection = materials_connection();

    if (($jobMaterial['movement_id'] ?? null) !== null) {
        $statement = $connection->prepare(
            'SELECT id, company_id, material_id, movement_type, quantity, job_id, job_material_id
             FROM material_movements
             WHERE id = :id
               AND company_id = :company_id
             LIMIT 1'
        );
        $statement->execute([
            'id' => (int) $jobMaterial['movement_id'],
            'company_id' => $companyId,
        ]);
        $movement = $statement->fetch();

        if (is_array($movement)
            && (int) ($movement['job_material_id'] ?? 0) === (int) $jobMaterial['id']
            && (int) ($movement['material_id'] ?? 0) === (int) $jobMaterial['material_id']) {
            return $movement;
        }
    }

    $fallback = $connection->prepare(
        'SELECT id, company_id, material_id, movement_type, quantity, job_id, job_material_id
         FROM material_movements
         WHERE company_id = :company_id
           AND job_material_id = :job_material_id
         LIMIT 1'
    );
    $fallback->execute([
        'company_id' => $companyId,
        'job_material_id' => (int) $jobMaterial['id'],
    ]);
    $movement = $fallback->fetch();

    return is_array($movement)
        && (int) ($movement['material_id'] ?? 0) === (int) $jobMaterial['material_id']
        ? $movement
        : null;
}

function list_allowed_device_accessory_materials(): array
{
    $params = [];
    $sql = 'SELECT
                id,
                company_id,
                name,
                sku,
                unit,
                description,
                is_device,
                is_device_accessory,
                is_active,
                created_at,
                updated_at
            FROM materials
            WHERE is_active = 1
              AND is_device_accessory = 1';
    $sql .= scoped_company_sql('company_id', $params);
    $sql .= ' ORDER BY name ASC, id ASC';
    $statement = materials_connection()->prepare($sql);
    $statement->execute($params);
    $materials = $statement->fetchAll();

    return is_array($materials) ? $materials : [];
}

function list_job_device_installations(int $jobId): array
{
    $companyId = current_company_id();

    if ($companyId === null) {
        return [];
    }

    $statement = materials_connection()->prepare(
        'SELECT
            di.id,
            di.company_id,
            di.job_id,
            di.device_material_usage_id,
            di.device_material_id,
            di.device_identifier,
            di.object_name,
            di.created_by,
            di.created_at,
            di.updated_at,
            u.name AS created_by_name
         FROM device_installations di
         LEFT JOIN users u ON u.id = di.created_by
         WHERE di.company_id = :company_id
           AND di.job_id = :job_id
         ORDER BY di.id ASC'
    );
    $statement->execute([
        'company_id' => $companyId,
        'job_id' => $jobId,
    ]);
    $installations = $statement->fetchAll();

    if (!is_array($installations) || $installations === []) {
        return [];
    }

    $installationIds = array_map(
        static fn (array $installation): int => (int) $installation['id'],
        $installations
    );
    $placeholders = implode(', ', array_fill(0, count($installationIds), '?'));
    $accessoryStatement = materials_connection()->prepare(
        'SELECT
            dia.id,
            dia.device_installation_id,
            dia.accessory_material_id,
            dia.accessory_material_usage_id,
            dia.quantity,
            dia.created_at,
            dia.updated_at,
            m.name AS accessory_material_name,
            m.sku AS accessory_material_sku,
            m.unit AS accessory_material_unit
         FROM device_installation_accessories dia
         INNER JOIN materials m ON m.id = dia.accessory_material_id
         WHERE dia.company_id = ?
           AND dia.device_installation_id IN (' . $placeholders . ')
         ORDER BY m.name ASC, dia.id ASC'
    );
    $accessoryStatement->execute(array_merge([$companyId], $installationIds));
    $accessoryRows = $accessoryStatement->fetchAll();
    $accessoriesByInstallation = [];

    if (is_array($accessoryRows)) {
        foreach ($accessoryRows as $row) {
            $accessoriesByInstallation[(int) $row['device_installation_id']][] = $row;
        }
    }

    $mapped = [];

    foreach ($installations as $installation) {
        $installation['accessories'] = $accessoriesByInstallation[(int) $installation['id']] ?? [];
        $mapped[(int) $installation['device_material_usage_id']] = $installation;
    }

    return $mapped;
}

function find_device_installation_by_usage_id(int $companyId, int $jobMaterialId): ?array
{
    $statement = materials_connection()->prepare(
        'SELECT
            id,
            company_id,
            job_id,
            device_material_usage_id,
            device_material_id,
            device_identifier,
            object_name,
            created_by,
            created_at,
            updated_at
         FROM device_installations
         WHERE company_id = :company_id
           AND device_material_usage_id = :device_material_usage_id
         LIMIT 1'
    );
    $statement->execute([
        'company_id' => $companyId,
        'device_material_usage_id' => $jobMaterialId,
    ]);
    $installation = $statement->fetch();

    return is_array($installation) ? $installation : null;
}

function list_device_installation_accessory_links(int $companyId, int $installationId): array
{
    $statement = materials_connection()->prepare(
        'SELECT
            id,
            company_id,
            device_installation_id,
            accessory_material_id,
            accessory_material_usage_id,
            quantity,
            created_at,
            updated_at
         FROM device_installation_accessories
         WHERE company_id = :company_id
           AND device_installation_id = :device_installation_id
         ORDER BY id ASC'
    );
    $statement->execute([
        'company_id' => $companyId,
        'device_installation_id' => $installationId,
    ]);
    $rows = $statement->fetchAll();

    return is_array($rows) ? $rows : [];
}

function find_device_installation_accessory_by_usage_id(int $companyId, int $jobMaterialId): ?array
{
    $statement = materials_connection()->prepare(
        'SELECT
            id,
            company_id,
            device_installation_id,
            accessory_material_id,
            accessory_material_usage_id,
            quantity,
            created_at,
            updated_at
         FROM device_installation_accessories
         WHERE company_id = :company_id
           AND accessory_material_usage_id = :accessory_material_usage_id
         LIMIT 1'
    );
    $statement->execute([
        'company_id' => $companyId,
        'accessory_material_usage_id' => $jobMaterialId,
    ]);
    $row = $statement->fetch();

    return is_array($row) ? $row : null;
}

function material_is_device(array $material): bool
{
    return (int) ($material['is_device'] ?? 0) === 1;
}

function job_material_is_device(array $jobMaterial): bool
{
    return (int) ($jobMaterial['material_is_device'] ?? 0) === 1;
}

function fixed_device_quantity(): string
{
    return '1.000';
}

function trimmed_device_identifier(mixed $value): ?string
{
    $identifier = trim((string) $value);

    return $identifier === '' ? null : $identifier;
}

function trimmed_device_object_name(mixed $value): ?string
{
    $objectName = trim((string) $value);

    return $objectName === '' ? null : $objectName;
}

function job_material_entry_movement_type(string $entryType): string
{
    return $entryType === 'returned' ? 'in' : 'out';
}

function user_can_manage_materials_catalogue(array $user): bool
{
    return is_super_admin($user) || in_array((string) ($user['role'] ?? ''), ['admin', 'dispatcher'], true);
}

function user_can_record_job_material(array $user, array $job): bool
{
    if (user_can_manage_materials_catalogue($user)) {
        return true;
    }

    return job_is_open($job)
        && (string) ($user['role'] ?? '') === 'worker'
        && (int) ($job['assigned_user_id'] ?? 0) === (int) ($user['id'] ?? 0);
}

function user_can_modify_job_material(array $user, array $job): bool
{
    if (user_can_manage_materials_catalogue($user)) {
        return true;
    }

    return user_can_record_job_material($user, $job);
}

function require_material_company_id(array $data): int
{
    $companyId = $data['company_id'] ?? null;

    if (!is_int($companyId) || $companyId <= 0) {
        throw new InvalidArgumentException('A valid active company is required to save a material.');
    }

    return $companyId;
}

function material_deletion_dependencies(int $materialId): array
{
    $companyId = current_company_id();

    if ($companyId === null) {
        return [];
    }

    $connection = materials_connection();
    $definitions = [
        'job_usage' => [
            'label' => 'job usage history',
            'sql' => 'SELECT COUNT(*) FROM job_materials WHERE company_id = :company_id AND material_id = :material_id',
        ],
        'movement_history' => [
            'label' => 'stock history',
            'sql' => 'SELECT COUNT(*) FROM material_movements WHERE company_id = :company_id AND material_id = :material_id',
        ],
        'inventory_history' => [
            'label' => 'inventory records',
            'sql' => 'SELECT COUNT(*) FROM material_inventory_lines WHERE company_id = :company_id AND material_id = :material_id',
        ],
    ];
    $dependencies = [];

    foreach ($definitions as $key => $definition) {
        $statement = $connection->prepare($definition['sql']);
        $statement->execute([
            'company_id' => $companyId,
            'material_id' => $materialId,
        ]);
        $count = (int) $statement->fetchColumn();

        if ($count > 0) {
            $dependencies[$key] = [
                'label' => $definition['label'],
                'count' => $count,
            ];
        }
    }

    return $dependencies;
}

function can_delete_material(int $materialId): array
{
    $material = find_material_by_id($materialId);

    if ($material === null) {
        return [
            'allowed' => false,
            'material' => null,
            'dependencies' => [],
            'message' => 'The material could not be found.',
        ];
    }

    $dependencies = material_deletion_dependencies($materialId);

    if ($dependencies !== []) {
        return [
            'allowed' => false,
            'material' => $material,
            'dependencies' => $dependencies,
            'message' => 'This material cannot be deleted because it has stock or usage history.',
        ];
    }

    return [
        'allowed' => true,
        'material' => $material,
        'dependencies' => [],
        'message' => null,
    ];
}

function delete_material_record(int $materialId): bool
{
    $params = ['id' => $materialId];
    $sql = 'DELETE FROM materials WHERE id = :id';
    $sql .= scoped_company_sql('company_id', $params);
    $statement = materials_connection()->prepare($sql);
    $statement->execute($params);

    return $statement->rowCount() > 0;
}
