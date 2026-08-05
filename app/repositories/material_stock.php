<?php

declare(strict_types=1);

const MATERIAL_STOCK_DECIMAL_SCALE = 3;

function material_stock_connection(): PDO
{
    $connection = database_connection();

    if ($connection === null) {
        throw new RuntimeException('Database connection is unavailable.');
    }

    return $connection;
}

function normalize_material_quantity(string $value): ?string
{
    $normalized = trim($value);

    if ($normalized === '' || preg_match('/^\d+(?:\.\d{1,3})?$/', $normalized) !== 1) {
        return null;
    }

    if ((float) $normalized < 0) {
        return null;
    }

    return number_format((float) $normalized, MATERIAL_STOCK_DECIMAL_SCALE, '.', '');
}

function normalize_positive_material_quantity(string $value): ?string
{
    $normalized = normalize_material_quantity($value);

    if ($normalized === null || (float) $normalized <= 0) {
        return null;
    }

    return $normalized;
}

function user_can_manage_material_inventory(array $user): bool
{
    return is_super_admin($user) || in_array((string) ($user['role'] ?? ''), ['admin', 'dispatcher'], true);
}

function user_can_create_manual_material_movement(array $user): bool
{
    $role = (string) ($user['role'] ?? '');

    return is_super_admin($user) || in_array($role, ['admin', 'dispatcher', 'worker'], true);
}

function user_can_set_material_movement_datetime(array $user): bool
{
    return is_super_admin($user) || in_array((string) ($user['role'] ?? ''), ['admin', 'dispatcher'], true);
}

function material_stock_status_class(string $quantity): string
{
    $value = (float) $quantity;

    if ($value < 0) {
        return 'text-bg-danger';
    }

    if ($value === 0.0) {
        return 'text-bg-secondary';
    }

    return 'text-bg-success';
}

function material_stock_label(string $quantity): string
{
    $value = (float) $quantity;

    if ($value < 0) {
        return 'Negative stock';
    }

    if ($value === 0.0) {
        return 'Zero stock';
    }

    return 'In stock';
}

function material_movement_type_label(string $type): string
{
    return $type === 'in' ? 'Material In' : 'Material Out';
}

function material_stock_navigation_items(): array
{
    return [
        ['label' => 'Materials', 'path' => '/materials'],
        ['label' => 'Movements', 'path' => '/materials/movements'],
        ['label' => 'Inventory', 'path' => '/materials/inventories'],
    ];
}

function latest_approved_inventory_line(int $companyId, int $materialId): ?array
{
    $statement = material_stock_connection()->prepare(
        "SELECT
            il.id,
            il.inventory_id,
            il.company_id,
            il.material_id,
            il.counted_quantity,
            il.system_quantity_at_start,
            inv.approved_at,
            inv.approved_by_user_id
         FROM material_inventory_lines il
         INNER JOIN material_inventories inv ON inv.id = il.inventory_id
         WHERE il.company_id = :company_id
           AND il.material_id = :material_id
           AND inv.status = 'approved'
         ORDER BY inv.approved_at DESC, inv.id DESC
         LIMIT 1"
    );
    $statement->execute([
        'company_id' => $companyId,
        'material_id' => $materialId,
    ]);
    $line = $statement->fetch();

    return is_array($line) ? $line : null;
}

function material_current_stock(int $companyId, int $materialId): string
{
    $inventoryLine = latest_approved_inventory_line($companyId, $materialId);
    $connection = material_stock_connection();

    if ($inventoryLine !== null) {
        $statement = $connection->prepare(
            "SELECT CAST(
                :baseline + COALESCE((
                    SELECT SUM(CASE movement_type WHEN 'in' THEN quantity ELSE -quantity END)
                    FROM material_movements
                    WHERE company_id = :company_id
                      AND material_id = :material_id
                      AND occurred_at > :approved_at
                ), 0)
                AS DECIMAL(14,3)
            )"
        );
        $statement->execute([
            'baseline' => (string) $inventoryLine['counted_quantity'],
            'company_id' => $companyId,
            'material_id' => $materialId,
            'approved_at' => (string) $inventoryLine['approved_at'],
        ]);
    } else {
        $statement = $connection->prepare(
            "SELECT CAST(
                COALESCE(SUM(CASE movement_type WHEN 'in' THEN quantity ELSE -quantity END), 0)
                AS DECIMAL(14,3)
            )
             FROM material_movements
             WHERE company_id = :company_id
               AND material_id = :material_id"
        );
        $statement->execute([
            'company_id' => $companyId,
            'material_id' => $materialId,
        ]);
    }

    $stock = $statement->fetchColumn();

    return is_string($stock) ? $stock : number_format((float) $stock, MATERIAL_STOCK_DECIMAL_SCALE, '.', '');
}

function company_material_stock_list(int $companyId, array $filters = []): array
{
    $sql = "SELECT
                m.id,
                m.company_id,
                m.name,
                m.sku,
                m.unit,
                m.description,
                m.is_device,
                m.is_device_accessory,
                m.is_active,
                m.created_at,
                m.updated_at,
                COALESCE((
                    SELECT CAST(
                        il.counted_quantity + COALESCE((
                            SELECT SUM(CASE mm.movement_type WHEN 'in' THEN mm.quantity ELSE -mm.quantity END)
                            FROM material_movements mm
                            WHERE mm.company_id = m.company_id
                              AND mm.material_id = m.id
                              AND mm.occurred_at > inv.approved_at
                        ), 0)
                        AS DECIMAL(14,3)
                    )
                    FROM material_inventory_lines il
                    INNER JOIN material_inventories inv ON inv.id = il.inventory_id
                    WHERE il.company_id = m.company_id
                      AND il.material_id = m.id
                      AND inv.status = 'approved'
                    ORDER BY inv.approved_at DESC, inv.id DESC
                    LIMIT 1
                ), (
                    SELECT CAST(
                        COALESCE(SUM(CASE mm2.movement_type WHEN 'in' THEN mm2.quantity ELSE -mm2.quantity END), 0)
                        AS DECIMAL(14,3)
                    )
                    FROM material_movements mm2
                    WHERE mm2.company_id = m.company_id
                      AND mm2.material_id = m.id
                ), '0.000') AS current_stock
            FROM materials m
            WHERE m.company_id = :company_id";
    $params = ['company_id' => $companyId];

    $search = trim((string) ($filters['search'] ?? ''));
    $status = (string) ($filters['status'] ?? '');

    if ($search !== '') {
        $sql .= ' AND (m.name LIKE :search_name OR COALESCE(m.sku, \'\') LIKE :search_sku)';
        $params['search_name'] = '%' . $search . '%';
        $params['search_sku'] = '%' . $search . '%';
    }

    if ($status === 'active') {
        $sql .= ' AND m.is_active = 1';
    } elseif ($status === 'inactive') {
        $sql .= ' AND m.is_active = 0';
    }

    $sql .= ' ORDER BY m.name ASC, m.id ASC';

    $statement = material_stock_connection()->prepare($sql);
    $statement->execute($params);
    $materials = $statement->fetchAll();

    return is_array($materials) ? $materials : [];
}

function material_movement_history(int $companyId, ?int $materialId = null, array $filters = [], int $limit = 50, int $offset = 0): array
{
    $sql = "SELECT
                mm.id,
                mm.company_id,
                mm.material_id,
                mm.movement_type,
                mm.quantity,
                mm.job_id,
                mm.job_material_id,
                mm.created_by_user_id,
                mm.note,
                mm.occurred_at,
                mm.created_at,
                m.name AS material_name,
                m.sku AS material_sku,
                m.unit AS material_unit,
                j.job_number,
                u.name AS created_by_name
            FROM material_movements mm
            INNER JOIN materials m ON m.id = mm.material_id
            LEFT JOIN jobs j ON j.id = mm.job_id
            LEFT JOIN users u ON u.id = mm.created_by_user_id
            WHERE mm.company_id = :company_id";
    $params = ['company_id' => $companyId];

    if ($materialId !== null) {
        $sql .= ' AND mm.material_id = :material_id';
        $params['material_id'] = $materialId;
    }

    $movementType = (string) ($filters['movement_type'] ?? '');
    if (in_array($movementType, ['in', 'out'], true)) {
        $sql .= ' AND mm.movement_type = :movement_type';
        $params['movement_type'] = $movementType;
    }

    $movementSource = (string) ($filters['movement_source'] ?? '');
    if ($movementSource === 'job') {
        $sql .= ' AND mm.job_id IS NOT NULL';
    } elseif ($movementSource === 'manual') {
        $sql .= ' AND mm.job_id IS NULL';
    }

    $userId = positive_int_or_null($filters['user_id'] ?? null);
    if ($userId !== null) {
        $sql .= ' AND mm.created_by_user_id = :created_by_user_id';
        $params['created_by_user_id'] = $userId;
    }

    $dateFrom = trim((string) ($filters['date_from'] ?? ''));
    if ($dateFrom !== '') {
        $sql .= ' AND mm.occurred_at >= :date_from';
        $params['date_from'] = $dateFrom . ' 00:00:00';
    }

    $dateTo = trim((string) ($filters['date_to'] ?? ''));
    if ($dateTo !== '') {
        $sql .= ' AND mm.occurred_at <= :date_to';
        $params['date_to'] = $dateTo . ' 23:59:59';
    }

    $sql .= ' ORDER BY mm.occurred_at DESC, mm.id DESC LIMIT :limit OFFSET :offset';
    $statement = material_stock_connection()->prepare($sql);

    foreach ($params as $name => $value) {
        $statement->bindValue(':' . $name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }

    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
    $statement->execute();

    $rows = $statement->fetchAll();

    return is_array($rows) ? $rows : [];
}

function count_material_movement_history(int $companyId, ?int $materialId = null, array $filters = []): int
{
    $sql = 'SELECT COUNT(*) FROM material_movements WHERE company_id = :company_id';
    $params = ['company_id' => $companyId];

    if ($materialId !== null) {
        $sql .= ' AND material_id = :material_id';
        $params['material_id'] = $materialId;
    }

    $movementType = (string) ($filters['movement_type'] ?? '');
    if (in_array($movementType, ['in', 'out'], true)) {
        $sql .= ' AND movement_type = :movement_type';
        $params['movement_type'] = $movementType;
    }

    $movementSource = (string) ($filters['movement_source'] ?? '');
    if ($movementSource === 'job') {
        $sql .= ' AND job_id IS NOT NULL';
    } elseif ($movementSource === 'manual') {
        $sql .= ' AND job_id IS NULL';
    }

    $userId = positive_int_or_null($filters['user_id'] ?? null);
    if ($userId !== null) {
        $sql .= ' AND created_by_user_id = :created_by_user_id';
        $params['created_by_user_id'] = $userId;
    }

    $dateFrom = trim((string) ($filters['date_from'] ?? ''));
    if ($dateFrom !== '') {
        $sql .= ' AND occurred_at >= :date_from';
        $params['date_from'] = $dateFrom . ' 00:00:00';
    }

    $dateTo = trim((string) ($filters['date_to'] ?? ''));
    if ($dateTo !== '') {
        $sql .= ' AND occurred_at <= :date_to';
        $params['date_to'] = $dateTo . ' 23:59:59';
    }

    $statement = material_stock_connection()->prepare($sql);
    $statement->execute($params);

    return (int) $statement->fetchColumn();
}

function create_material_movement(array $data): int
{
    $companyId = (int) $data['company_id'];
    $materialId = (int) $data['material_id'];
    $movementType = (string) $data['movement_type'];
    $quantity = (string) $data['quantity'];
    $jobId = $data['job_id'] !== null ? (int) $data['job_id'] : null;
    $jobMaterialId = $data['job_material_id'] !== null ? (int) $data['job_material_id'] : null;
    $userId = $data['created_by_user_id'] !== null ? (int) $data['created_by_user_id'] : null;
    $occurredAt = (string) $data['occurred_at'];

    $statement = material_stock_connection()->prepare(
        'INSERT INTO material_movements (
            company_id,
            material_id,
            movement_type,
            quantity,
            job_id,
            job_material_id,
            created_by_user_id,
            note,
            occurred_at
         ) VALUES (
            :company_id,
            :material_id,
            :movement_type,
            :quantity,
            :job_id,
            :job_material_id,
            :created_by_user_id,
            :note,
            :occurred_at
         )'
    );
    $statement->execute([
        'company_id' => $companyId,
        'material_id' => $materialId,
        'movement_type' => $movementType,
        'quantity' => $quantity,
        'job_id' => $jobId,
        'job_material_id' => $jobMaterialId,
        'created_by_user_id' => $userId,
        'note' => $data['note'] !== '' ? $data['note'] : null,
        'occurred_at' => $occurredAt,
    ]);

    return (int) material_stock_connection()->lastInsertId();
}

function create_material_inventory(int $companyId, int $startedByUserId, ?string $note = null): int
{
    $connection = material_stock_connection();
    $connection->beginTransaction();

    try {
        $statement = $connection->prepare(
            'INSERT INTO material_inventories (
                company_id,
                status,
                started_by_user_id,
                note
             ) VALUES (
                :company_id,
                :status,
                :started_by_user_id,
                :note
             )'
        );
        $statement->execute([
            'company_id' => $companyId,
            'status' => 'draft',
            'started_by_user_id' => $startedByUserId,
            'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
        ]);

        $inventoryId = (int) $connection->lastInsertId();
        $materials = list_active_materials_for_company($companyId);

        $lineStatement = $connection->prepare(
            'INSERT INTO material_inventory_lines (
                inventory_id,
                company_id,
                material_id,
                counted_quantity,
                system_quantity_at_start
             ) VALUES (
                :inventory_id,
                :company_id,
                :material_id,
                NULL,
                :system_quantity_at_start
             )'
        );

        foreach ($materials as $material) {
            $lineStatement->execute([
                'inventory_id' => $inventoryId,
                'company_id' => $companyId,
                'material_id' => (int) $material['id'],
                'system_quantity_at_start' => material_current_stock($companyId, (int) $material['id']),
            ]);
        }

        $connection->commit();

        return $inventoryId;
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        throw $exception;
    }
}

function list_active_materials_for_company(int $companyId): array
{
    $statement = material_stock_connection()->prepare(
        'SELECT id, company_id, name, sku, unit, description, is_active, created_at, updated_at
         FROM materials
         WHERE company_id = :company_id
           AND is_active = 1
         ORDER BY name ASC, id ASC'
    );
    $statement->execute(['company_id' => $companyId]);
    $rows = $statement->fetchAll();

    return is_array($rows) ? $rows : [];
}

function list_material_inventories(int $companyId, array $filters = []): array
{
    $sql = "SELECT
                inv.id,
                inv.company_id,
                inv.status,
                inv.started_by_user_id,
                inv.submitted_by_user_id,
                inv.approved_by_user_id,
                inv.started_at,
                inv.submitted_at,
                inv.approved_at,
                inv.note,
                inv.created_at,
                inv.updated_at,
                starter.name AS started_by_name,
                submitter.name AS submitted_by_name,
                approver.name AS approved_by_name,
                COALESCE(line_counts.counted_material_count, 0) AS counted_material_count,
                COALESCE(line_counts.completed_material_count, 0) AS completed_material_count
            FROM material_inventories inv
            LEFT JOIN (
                SELECT
                    inventory_id,
                    company_id,
                    COUNT(id) AS counted_material_count,
                    SUM(CASE WHEN counted_quantity IS NOT NULL THEN 1 ELSE 0 END) AS completed_material_count
                FROM material_inventory_lines
                GROUP BY inventory_id, company_id
            ) line_counts ON line_counts.inventory_id = inv.id
                AND line_counts.company_id = inv.company_id
            LEFT JOIN users starter ON starter.id = inv.started_by_user_id
            LEFT JOIN users submitter ON submitter.id = inv.submitted_by_user_id
            LEFT JOIN users approver ON approver.id = inv.approved_by_user_id
            WHERE inv.company_id = :company_id";
    $params = ['company_id' => $companyId];

    $status = (string) ($filters['status'] ?? '');
    if (in_array($status, ['draft', 'pending_approval', 'approved', 'cancelled'], true)) {
        $sql .= ' AND inv.status = :status';
        $params['status'] = $status;
    }

    $sql .= ' ORDER BY inv.started_at DESC, inv.id DESC';

    $statement = material_stock_connection()->prepare($sql);
    $statement->execute($params);
    $rows = $statement->fetchAll();

    return is_array($rows) ? $rows : [];
}

function find_material_inventory_by_id(int $companyId, int $inventoryId): ?array
{
    $statement = material_stock_connection()->prepare(
        "SELECT
            inv.*,
            starter.name AS started_by_name,
            submitter.name AS submitted_by_name,
            approver.name AS approved_by_name,
            COALESCE(line_counts.counted_material_count, 0) AS counted_material_count,
            COALESCE(line_counts.completed_material_count, 0) AS completed_material_count
         FROM material_inventories inv
         LEFT JOIN (
             SELECT
                 inventory_id,
                 company_id,
                 COUNT(id) AS counted_material_count,
                 SUM(CASE WHEN counted_quantity IS NOT NULL THEN 1 ELSE 0 END) AS completed_material_count
             FROM material_inventory_lines
             GROUP BY inventory_id, company_id
         ) line_counts ON line_counts.inventory_id = inv.id
             AND line_counts.company_id = inv.company_id
         LEFT JOIN users starter ON starter.id = inv.started_by_user_id
         LEFT JOIN users submitter ON submitter.id = inv.submitted_by_user_id
         LEFT JOIN users approver ON approver.id = inv.approved_by_user_id
         WHERE inv.company_id = :company_id
           AND inv.id = :inventory_id
         LIMIT 1"
    );
    $statement->execute([
        'company_id' => $companyId,
        'inventory_id' => $inventoryId,
    ]);
    $row = $statement->fetch();

    return is_array($row) ? $row : null;
}

function list_material_inventory_lines(int $companyId, int $inventoryId): array
{
    $statement = material_stock_connection()->prepare(
        "SELECT
            inventory_line.id,
            inventory_line.inventory_id,
            inventory_line.company_id,
            inventory_line.material_id,
            inventory_line.counted_quantity,
            inventory_line.system_quantity_at_start,
            inventory_line.created_at,
            inventory_line.updated_at,
            m.name AS material_name,
            m.sku AS material_sku,
            m.unit AS material_unit,
            m.is_active AS material_is_active
         FROM material_inventory_lines inventory_line
         INNER JOIN materials m ON m.id = inventory_line.material_id
         WHERE inventory_line.company_id = :company_id
           AND inventory_line.inventory_id = :inventory_id
         ORDER BY m.name ASC, m.id ASC"
    );
    $statement->execute([
        'company_id' => $companyId,
        'inventory_id' => $inventoryId,
    ]);
    $rows = $statement->fetchAll();

    return is_array($rows) ? $rows : [];
}

function save_material_inventory_counts(int $companyId, int $inventoryId, array $counts): void
{
    $inventory = find_material_inventory_by_id($companyId, $inventoryId);

    if ($inventory === null) {
        throw new RuntimeException('Inventory was not found.');
    }

    if (in_array((string) $inventory['status'], ['approved', 'cancelled'], true)) {
        throw new RuntimeException('This inventory can no longer be edited.');
    }

    $connection = material_stock_connection();
    $connection->beginTransaction();

    try {
        $statement = $connection->prepare(
            'UPDATE material_inventory_lines
             SET counted_quantity = :counted_quantity,
                 updated_at = CURRENT_TIMESTAMP
             WHERE inventory_id = :inventory_id
               AND company_id = :company_id
               AND id = :id'
        );

        foreach ($counts as $lineId => $countedQuantity) {
            $statement->execute([
                'counted_quantity' => $countedQuantity,
                'inventory_id' => $inventoryId,
                'company_id' => $companyId,
                'id' => $lineId,
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

function submit_material_inventory(int $companyId, int $inventoryId, int $submittedByUserId): void
{
    $connection = material_stock_connection();
    $connection->beginTransaction();

    try {
        $statement = $connection->prepare(
            'SELECT status
             FROM material_inventories
             WHERE id = :inventory_id
               AND company_id = :company_id
             LIMIT 1
             FOR UPDATE'
        );
        $statement->execute([
            'inventory_id' => $inventoryId,
            'company_id' => $companyId,
        ]);
        $status = $statement->fetchColumn();

        if (!is_string($status)) {
            throw new RuntimeException('Inventory was not found.');
        }

        if (in_array($status, ['approved', 'cancelled'], true)) {
            throw new RuntimeException('This inventory can no longer be submitted.');
        }

        $missingCountStatement = $connection->prepare(
            'SELECT COUNT(*)
             FROM material_inventory_lines
             WHERE inventory_id = :inventory_id
               AND company_id = :company_id
               AND counted_quantity IS NULL'
        );
        $missingCountStatement->execute([
            'inventory_id' => $inventoryId,
            'company_id' => $companyId,
        ]);

        if ((int) $missingCountStatement->fetchColumn() > 0) {
            throw new RuntimeException('Every material must have a counted quantity before submission.');
        }

        $updateStatement = $connection->prepare(
            "UPDATE material_inventories
             SET status = 'pending_approval',
                 submitted_by_user_id = :submitted_by_user_id,
                 submitted_at = CURRENT_TIMESTAMP,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :inventory_id
               AND company_id = :company_id"
        );
        $updateStatement->execute([
            'submitted_by_user_id' => $submittedByUserId,
            'inventory_id' => $inventoryId,
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

function approve_material_inventory(int $companyId, int $inventoryId, int $approvedByUserId): void
{
    $connection = material_stock_connection();
    $connection->beginTransaction();

    try {
        $statement = $connection->prepare(
            'SELECT status
             FROM material_inventories
             WHERE id = :inventory_id
               AND company_id = :company_id
             LIMIT 1
             FOR UPDATE'
        );
        $statement->execute([
            'inventory_id' => $inventoryId,
            'company_id' => $companyId,
        ]);
        $status = $statement->fetchColumn();

        if (!is_string($status)) {
            throw new RuntimeException('Inventory was not found.');
        }

        if ($status === 'approved') {
            throw new RuntimeException('This inventory has already been approved.');
        }

        if ($status !== 'pending_approval') {
            throw new RuntimeException('Only inventories pending approval can be approved.');
        }

        $updateStatement = $connection->prepare(
            "UPDATE material_inventories
             SET status = 'approved',
                 approved_by_user_id = :approved_by_user_id,
                 approved_at = CURRENT_TIMESTAMP,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :inventory_id
               AND company_id = :company_id
               AND status = 'pending_approval'"
        );
        $updateStatement->execute([
            'approved_by_user_id' => $approvedByUserId,
            'inventory_id' => $inventoryId,
            'company_id' => $companyId,
        ]);

        if ($updateStatement->rowCount() !== 1) {
            throw new RuntimeException('This inventory could not be approved.');
        }

        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        throw $exception;
    }
}

function cancel_material_inventory(int $companyId, int $inventoryId): void
{
    $statement = material_stock_connection()->prepare(
        "UPDATE material_inventories
         SET status = 'cancelled',
             updated_at = CURRENT_TIMESTAMP
         WHERE id = :inventory_id
           AND company_id = :company_id
           AND status IN ('draft', 'pending_approval')"
    );
    $statement->execute([
        'inventory_id' => $inventoryId,
        'company_id' => $companyId,
    ]);
}

function material_movement_is_protected(int $companyId, int $materialId, string $occurredAt): bool
{
    $statement = material_stock_connection()->prepare(
        "SELECT 1
         FROM material_inventory_lines lines
         INNER JOIN material_inventories inv ON inv.id = lines.inventory_id
         WHERE lines.company_id = :company_id
           AND lines.material_id = :material_id
           AND inv.status = 'approved'
           AND inv.approved_at >= :occurred_at
         LIMIT 1"
    );
    $statement->execute([
        'company_id' => $companyId,
        'material_id' => $materialId,
        'occurred_at' => $occurredAt,
    ]);

    return $statement->fetchColumn() !== false;
}
