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
                company_id,
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
    $sql .= scoped_company_sql('company_id', $params);
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
    $params = ['material_id' => $materialId];
    $sql = 'SELECT COUNT(*)
            FROM job_materials
            WHERE material_id = :material_id';
    $params = ['material_id' => $materialId];
    $sql .= scoped_company_sql('company_id', $params);
    $statement = materials_connection()->prepare($sql);
    $statement->execute($params);

    return (int) $statement->fetchColumn();
}

function list_material_movements(int $materialId, int $limit, int $offset): array
{
    $sql = 'SELECT
            jm.id,
            jm.job_id,
            jm.quantity,
            jm.created_at,
            jm.updated_at,
            j.job_number,
            c.name AS customer_name,
            l.name AS location_name,
            m.unit AS material_unit,
            u.name AS recorded_by_name
         FROM job_materials jm
         INNER JOIN jobs j ON j.id = jm.job_id
         LEFT JOIN customers c ON c.id = j.customer_id
         LEFT JOIN locations l ON l.id = j.location_id
         INNER JOIN materials m ON m.id = jm.material_id
         LEFT JOIN users u ON u.id = jm.recorded_by_user_id
         WHERE jm.material_id = :material_id';
    $params = ['material_id' => $materialId];
    $sql .= scoped_company_sql('jm.company_id', $params);
    $sql .= '
         ORDER BY jm.updated_at DESC, jm.id DESC
         LIMIT :limit OFFSET :offset';
    $statement = materials_connection()->prepare($sql);
    $statement->bindValue(':material_id', $params['material_id'], PDO::PARAM_INT);
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->bindValue(':offset', $offset, PDO::PARAM_INT);

    if (isset($params['__scoped_company_id'])) {
        $statement->bindValue(':__scoped_company_id', $params['__scoped_company_id'], PDO::PARAM_INT);
    }

    $statement->execute();
    $movements = $statement->fetchAll();

    return is_array($movements) ? $movements : [];
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
         WHERE jm.job_id = :job_id';
    $params = ['job_id' => $jobId];
    $sql .= scoped_company_sql('jm.company_id', $params);
    $sql .= ' ORDER BY m.name ASC, jm.id ASC';
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
        $existingStatement = $connection->prepare(
            'SELECT id, quantity
             FROM job_materials
             WHERE job_id = :job_id
               AND material_id = :material_id
               AND company_id = :company_id
             LIMIT 1
             FOR UPDATE'
        );
        $existingStatement->execute([
            'company_id' => current_company_id(),
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
                    company_id,
                    job_id,
                    material_id,
                    quantity,
                    recorded_by_user_id
                 ) VALUES (
                    :company_id,
                    :job_id,
                    :material_id,
                    :quantity,
                    :recorded_by_user_id
                 )'
            );
            $statement->execute([
                'company_id' => current_company_id(),
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
    $sql = 'UPDATE job_materials
            SET quantity = :quantity,
                recorded_by_user_id = :recorded_by_user_id,
                updated_at = CURRENT_TIMESTAMP
            WHERE job_id = :job_id
              AND id = :id';
    $params = [
        'quantity' => $quantity,
        'recorded_by_user_id' => $recordedByUserId,
        'job_id' => $jobId,
        'id' => $jobMaterialId,
    ];
    $sql .= scoped_company_sql('company_id', $params);
    $statement = materials_connection()->prepare($sql);
    $statement->execute($params);

    return $statement->rowCount() > 0;
}

function delete_job_material(int $jobId, int $jobMaterialId): bool
{
    $sql = 'DELETE FROM job_materials
            WHERE job_id = :job_id
              AND id = :id';
    $params = [
        'job_id' => $jobId,
        'id' => $jobMaterialId,
    ];
    $sql .= scoped_company_sql('company_id', $params);
    $statement = materials_connection()->prepare($sql);
    $statement->execute($params);

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

function require_material_company_id(array $data): int
{
    $companyId = $data['company_id'] ?? null;

    if (!is_int($companyId) || $companyId <= 0) {
        throw new InvalidArgumentException('A valid active company is required to save a material.');
    }

    return $companyId;
}
