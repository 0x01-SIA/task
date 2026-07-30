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
            is_active
         ) VALUES (
            :company_id,
            :name,
            :sku,
            :unit,
            :description,
            :is_active
         )'
    );
    $statement->execute([
        'company_id' => $companyId,
        'name' => $data['name'],
        'sku' => $data['sku'],
        'unit' => $data['unit'],
        'description' => $data['description'],
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
            jm.quantity,
            jm.recorded_by_user_id,
            jm.occurred_at,
            jm.created_at,
            jm.updated_at,
            m.name AS material_name,
            m.sku AS material_sku,
            m.unit AS material_unit,
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
            jm.quantity,
            jm.recorded_by_user_id,
            jm.occurred_at,
            jm.created_at,
            jm.updated_at,
            m.name AS material_name,
            m.sku AS material_sku,
            m.unit AS material_unit,
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

function add_job_material_usage(int $jobId, int $materialId, string $quantity, ?int $recordedByUserId): void
{
    $connection = materials_connection();
    $connection->beginTransaction();

    try {
        $companyId = current_company_id();

        if ($companyId === null) {
            throw new RuntimeException('An active company is required to record material usage.');
        }

        $statement = $connection->prepare(
            'INSERT INTO job_materials (
                company_id,
                job_id,
                material_id,
                quantity,
                recorded_by_user_id,
                occurred_at
             ) VALUES (
                :company_id,
                :job_id,
                :material_id,
                :quantity,
                :recorded_by_user_id,
                CURRENT_TIMESTAMP
             )'
        );
        $statement->execute([
            'company_id' => $companyId,
            'job_id' => $jobId,
            'material_id' => $materialId,
            'quantity' => $quantity,
            'recorded_by_user_id' => $recordedByUserId,
        ]);

        $jobMaterialId = (int) $connection->lastInsertId();
        $movementId = create_material_movement([
            'company_id' => $companyId,
            'material_id' => $materialId,
            'movement_type' => 'out',
            'quantity' => $quantity,
            'job_id' => $jobId,
            'job_material_id' => $jobMaterialId,
            'created_by_user_id' => $recordedByUserId,
            'note' => '',
            'occurred_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
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

        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        throw $exception;
    }
}

function update_job_material_quantity(int $jobId, int $jobMaterialId, string $quantity, ?int $recordedByUserId): bool
{
    $companyId = current_company_id();

    if ($companyId === null) {
        return false;
    }

    $jobMaterial = find_job_material_by_id($jobId, $jobMaterialId);

    if ($jobMaterial === null) {
        return false;
    }

    if (material_movement_is_protected($companyId, (int) $jobMaterial['material_id'], (string) $jobMaterial['occurred_at'])) {
        return false;
    }

    $connection = materials_connection();
    $connection->beginTransaction();

    try {
        $statement = $connection->prepare(
            'UPDATE job_materials
             SET quantity = :quantity,
                 recorded_by_user_id = :recorded_by_user_id,
                 updated_at = CURRENT_TIMESTAMP
             WHERE company_id = :company_id
               AND job_id = :job_id
               AND id = :id'
        );
        $statement->execute([
            'quantity' => $quantity,
            'recorded_by_user_id' => $recordedByUserId,
            'company_id' => $companyId,
            'job_id' => $jobId,
            'id' => $jobMaterialId,
        ]);

        $movementStatement = $connection->prepare(
            'UPDATE material_movements
             SET quantity = :quantity,
                 created_by_user_id = :created_by_user_id
             WHERE id = :movement_id
               AND company_id = :company_id'
        );
        $movementStatement->execute([
            'quantity' => $quantity,
            'created_by_user_id' => $recordedByUserId,
            'movement_id' => (int) $jobMaterial['movement_id'],
            'company_id' => $companyId,
        ]);

        $connection->commit();

        return $statement->rowCount() > 0;
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        throw $exception;
    }
}

function delete_job_material(int $jobId, int $jobMaterialId): bool
{
    $companyId = current_company_id();

    if ($companyId === null) {
        return false;
    }

    $jobMaterial = find_job_material_by_id($jobId, $jobMaterialId);

    if ($jobMaterial === null) {
        return false;
    }

    if (material_movement_is_protected($companyId, (int) $jobMaterial['material_id'], (string) $jobMaterial['occurred_at'])) {
        return false;
    }

    $connection = materials_connection();
    $connection->beginTransaction();

    try {
        $movementStatement = $connection->prepare(
            'DELETE FROM material_movements
             WHERE id = :movement_id
               AND company_id = :company_id'
        );
        $movementStatement->execute([
            'movement_id' => (int) $jobMaterial['movement_id'],
            'company_id' => $companyId,
        ]);

        $statement = $connection->prepare(
            'DELETE FROM job_materials
             WHERE company_id = :company_id
               AND job_id = :job_id
               AND id = :id'
        );
        $statement->execute([
            'company_id' => $companyId,
            'job_id' => $jobId,
            'id' => $jobMaterialId,
        ]);

        $connection->commit();

        return $statement->rowCount() > 0;
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        throw $exception;
    }
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
