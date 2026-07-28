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
    $sql = 'SELECT
                id,
                name,
                sku,
                unit,
                description,
                is_active,
                created_at,
                updated_at
            FROM materials
            WHERE 1 = 1';
    $params = [];
    $search = trim((string) ($filters['search'] ?? ''));
    $status = (string) ($filters['status'] ?? '');

    if ($search !== '') {
        $sql .= ' AND (
            name LIKE :search
            OR COALESCE(sku, \'\') LIKE :search
        )';
        $params['search'] = '%' . $search . '%';
    }

    if ($status === 'active') {
        $sql .= ' AND is_active = 1';
    } elseif ($status === 'inactive') {
        $sql .= ' AND is_active = 0';
    }

    $sql .= ' ORDER BY name ASC, id ASC';

    $statement = materials_connection()->prepare($sql);
    $statement->execute($params);
    $materials = $statement->fetchAll();

    return is_array($materials) ? $materials : [];
}

function list_active_materials(): array
{
    $statement = materials_connection()->query(
        'SELECT
            id,
            name,
            sku,
            unit,
            description,
            is_active,
            created_at,
            updated_at
         FROM materials
         WHERE is_active = 1
         ORDER BY name ASC, id ASC'
    );
    $materials = $statement->fetchAll();

    return is_array($materials) ? $materials : [];
}

function find_material_by_id(int $id): ?array
{
    $statement = materials_connection()->prepare(
        'SELECT
            id,
            name,
            sku,
            unit,
            description,
            is_active,
            created_at,
            updated_at
         FROM materials
         WHERE id = :id
         LIMIT 1'
    );
    $statement->execute(['id' => $id]);
    $material = $statement->fetch();

    return is_array($material) ? $material : null;
}

function create_material(array $data): int
{
    $statement = materials_connection()->prepare(
        'INSERT INTO materials (
            name,
            sku,
            unit,
            description,
            is_active
         ) VALUES (
            :name,
            :sku,
            :unit,
            :description,
            :is_active
         )'
    );
    $statement->execute([
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
    $statement = materials_connection()->prepare(
        'UPDATE materials
         SET name = :name,
             sku = :sku,
             unit = :unit,
             description = :description,
             is_active = :is_active
         WHERE id = :id'
    );
    $statement->execute([
        'id' => $id,
        'name' => $data['name'],
        'sku' => $data['sku'],
        'unit' => $data['unit'],
        'description' => $data['description'],
        'is_active' => $data['is_active'],
    ]);
}

function set_material_active_status(int $id, bool $isActive): void
{
    $statement = materials_connection()->prepare(
        'UPDATE materials
         SET is_active = :is_active
         WHERE id = :id'
    );
    $statement->execute([
        'id' => $id,
        'is_active' => $isActive ? 1 : 0,
    ]);
}

function list_job_materials(int $jobId): array
{
    $statement = materials_connection()->prepare(
        'SELECT
            jm.id,
            jm.job_id,
            jm.material_id,
            jm.quantity,
            jm.recorded_by_user_id,
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
         ORDER BY m.name ASC, jm.id ASC'
    );
    $statement->execute(['job_id' => $jobId]);
    $materials = $statement->fetchAll();

    return is_array($materials) ? $materials : [];
}

function find_job_material_by_id(int $jobId, int $jobMaterialId): ?array
{
    $statement = materials_connection()->prepare(
        'SELECT
            jm.id,
            jm.job_id,
            jm.material_id,
            jm.quantity,
            jm.recorded_by_user_id,
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
           AND jm.id = :id
         LIMIT 1'
    );
    $statement->execute([
        'job_id' => $jobId,
        'id' => $jobMaterialId,
    ]);
    $jobMaterial = $statement->fetch();

    return is_array($jobMaterial) ? $jobMaterial : null;
}

function add_job_material_usage(int $jobId, int $materialId, string $quantity, ?int $recordedByUserId): void
{
    $connection = materials_connection();
    $connection->beginTransaction();

    try {
        $existingStatement = $connection->prepare(
            'SELECT id, quantity
             FROM job_materials
             WHERE job_id = :job_id
               AND material_id = :material_id
             LIMIT 1
             FOR UPDATE'
        );
        $existingStatement->execute([
            'job_id' => $jobId,
            'material_id' => $materialId,
        ]);
        $existing = $existingStatement->fetch();

        if (is_array($existing)) {
            $statement = $connection->prepare(
                'UPDATE job_materials
                 SET quantity = quantity + :quantity,
                     recorded_by_user_id = :recorded_by_user_id,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id'
            );
            $statement->execute([
                'quantity' => $quantity,
                'recorded_by_user_id' => $recordedByUserId,
                'id' => $existing['id'],
            ]);
        } else {
            $statement = $connection->prepare(
                'INSERT INTO job_materials (
                    job_id,
                    material_id,
                    quantity,
                    recorded_by_user_id
                 ) VALUES (
                    :job_id,
                    :material_id,
                    :quantity,
                    :recorded_by_user_id
                 )'
            );
            $statement->execute([
                'job_id' => $jobId,
                'material_id' => $materialId,
                'quantity' => $quantity,
                'recorded_by_user_id' => $recordedByUserId,
            ]);
        }

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
    $statement = materials_connection()->prepare(
        'UPDATE job_materials
         SET quantity = :quantity,
             recorded_by_user_id = :recorded_by_user_id,
             updated_at = CURRENT_TIMESTAMP
         WHERE job_id = :job_id
           AND id = :id'
    );
    $statement->execute([
        'quantity' => $quantity,
        'recorded_by_user_id' => $recordedByUserId,
        'job_id' => $jobId,
        'id' => $jobMaterialId,
    ]);

    return $statement->rowCount() > 0;
}

function delete_job_material(int $jobId, int $jobMaterialId): bool
{
    $statement = materials_connection()->prepare(
        'DELETE FROM job_materials
         WHERE job_id = :job_id
           AND id = :id'
    );
    $statement->execute([
        'job_id' => $jobId,
        'id' => $jobMaterialId,
    ]);

    return $statement->rowCount() > 0;
}

function user_can_manage_materials_catalogue(array $user): bool
{
    return in_array((string) ($user['role'] ?? ''), ['admin', 'dispatcher'], true);
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
