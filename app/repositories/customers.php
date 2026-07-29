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
