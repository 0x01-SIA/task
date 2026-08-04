<?php

declare(strict_types=1);

function customers_connection(): PDO
{
    $connection = database_connection();

    if ($connection === null) {
        throw new RuntimeException('Database connection is unavailable.');
    }

    return $connection;
}

function all_customers(): array
{
    $params = [];
    $sql = 'SELECT id, company_id, name, registration_number, contact_name, contact_email, contact_phone, notes, is_active, created_at, updated_at
            FROM customers
            WHERE 1 = 1';
    $sql .= scoped_company_sql('company_id', $params);
    $sql .= ' ORDER BY name ASC, id ASC';
    $statement = customers_connection()->prepare($sql);
    $statement->execute($params);

    $customers = $statement->fetchAll();

    return is_array($customers) ? $customers : [];
}

function active_customers(): array
{
    $params = [];
    $sql = 'SELECT id, company_id, name, registration_number, contact_name, contact_email, contact_phone, notes, is_active, created_at, updated_at
            FROM customers
            WHERE is_active = 1';
    $sql .= scoped_company_sql('company_id', $params);
    $sql .= ' ORDER BY name ASC, id ASC';
    $statement = customers_connection()->prepare($sql);
    $statement->execute($params);

    $customers = $statement->fetchAll();

    return is_array($customers) ? $customers : [];
}

function find_customer_by_id(int $id): ?array
{
    $params = ['id' => $id];
    $sql = 'SELECT id, company_id, name, registration_number, contact_name, contact_email, contact_phone, notes, is_active, created_at, updated_at
            FROM customers
            WHERE id = :id';
    $sql .= scoped_company_sql('company_id', $params);
    $sql .= ' LIMIT 1';
    $statement = customers_connection()->prepare($sql);
    $statement->execute($params);
    $customer = $statement->fetch();

    return is_array($customer) ? $customer : null;
}

function find_customer_by_id_in_company(int $id, int $companyId): ?array
{
    $statement = customers_connection()->prepare(
        'SELECT id, company_id, name, registration_number, contact_name, contact_email, contact_phone, notes, is_active, created_at, updated_at
         FROM customers
         WHERE id = :id
           AND company_id = :company_id
         LIMIT 1'
    );
    $statement->execute([
        'id' => $id,
        'company_id' => $companyId,
    ]);
    $customer = $statement->fetch();

    return is_array($customer) ? $customer : null;
}

function customer_exists(int $id): bool
{
    $params = ['id' => $id];
    $sql = 'SELECT 1
            FROM customers
            WHERE id = :id';
    $sql .= scoped_company_sql('company_id', $params);
    $sql .= ' LIMIT 1';
    $statement = customers_connection()->prepare($sql);
    $statement->execute($params);

    return $statement->fetchColumn() !== false;
}

function create_customer(array $data): int
{
    $companyId = current_company_id();

    if ($companyId === null) {
        throw new RuntimeException('An active company must be selected before creating a customer.');
    }

    $statement = customers_connection()->prepare(
        'INSERT INTO customers (
            company_id,
            name,
            registration_number,
            contact_name,
            contact_email,
            contact_phone,
            notes,
            is_active
        ) VALUES (
            :company_id,
            :name,
            :registration_number,
            :contact_name,
            :contact_email,
            :contact_phone,
            :notes,
            :is_active
        )'
    );
    $statement->execute([
        'company_id' => $companyId,
        'name' => $data['name'],
        'registration_number' => $data['registration_number'],
        'contact_name' => $data['contact_name'],
        'contact_email' => $data['contact_email'],
        'contact_phone' => $data['contact_phone'],
        'notes' => $data['notes'],
        'is_active' => $data['is_active'],
    ]);

    return (int) customers_connection()->lastInsertId();
}

function customer_deletion_dependencies(int $customerId): array
{
    $companyId = current_company_id();

    if ($companyId === null) {
        return [];
    }

    $connection = customers_connection();
    $definitions = [
        'locations' => [
            'label' => 'locations',
            'sql' => 'SELECT COUNT(*) FROM locations WHERE company_id = :company_id AND customer_id = :customer_id',
        ],
        'tasks' => [
            'label' => 'tasks',
            'sql' => 'SELECT COUNT(*) FROM tasks WHERE company_id = :company_id AND customer_id = :customer_id',
        ],
        'jobs' => [
            'label' => 'jobs',
            'sql' => 'SELECT COUNT(*) FROM jobs WHERE company_id = :company_id AND customer_id = :customer_id',
        ],
    ];
    $dependencies = [];

    foreach ($definitions as $key => $definition) {
        $statement = $connection->prepare($definition['sql']);
        $statement->execute([
            'company_id' => $companyId,
            'customer_id' => $customerId,
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

function can_delete_customer(int $customerId): array
{
    $customer = find_customer_by_id($customerId);

    if ($customer === null) {
        return [
            'allowed' => false,
            'customer' => null,
            'dependencies' => [],
            'message' => 'The customer could not be found.',
        ];
    }

    $dependencies = customer_deletion_dependencies($customerId);

    if ($dependencies !== []) {
        return [
            'allowed' => false,
            'customer' => $customer,
            'dependencies' => $dependencies,
            'message' => 'This customer cannot be deleted until its linked locations, tasks, and jobs are removed.',
        ];
    }

    return [
        'allowed' => true,
        'customer' => $customer,
        'dependencies' => [],
        'message' => null,
    ];
}

function delete_customer_record(int $customerId): bool
{
    $params = ['id' => $customerId];
    $sql = 'DELETE FROM customers WHERE id = :id';
    $sql .= scoped_company_sql('company_id', $params);
    $statement = customers_connection()->prepare($sql);
    $statement->execute($params);

    return $statement->rowCount() > 0;
}
