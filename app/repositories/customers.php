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
    $statement = customers_connection()->query(
        'SELECT id, name, registration_number, contact_name, contact_email, contact_phone, notes, is_active, created_at, updated_at
         FROM customers
         ORDER BY name ASC, id ASC'
    );

    $customers = $statement->fetchAll();

    return is_array($customers) ? $customers : [];
}

function active_customers(): array
{
    $statement = customers_connection()->query(
        'SELECT id, name, registration_number, contact_name, contact_email, contact_phone, notes, is_active, created_at, updated_at
         FROM customers
         WHERE is_active = 1
         ORDER BY name ASC, id ASC'
    );

    $customers = $statement->fetchAll();

    return is_array($customers) ? $customers : [];
}

function find_customer_by_id(int $id): ?array
{
    $statement = customers_connection()->prepare(
        'SELECT id, name, registration_number, contact_name, contact_email, contact_phone, notes, is_active, created_at, updated_at
         FROM customers
         WHERE id = :id
         LIMIT 1'
    );
    $statement->execute(['id' => $id]);
    $customer = $statement->fetch();

    return is_array($customer) ? $customer : null;
}

function customer_exists(int $id): bool
{
    $statement = customers_connection()->prepare(
        'SELECT 1
         FROM customers
         WHERE id = :id
         LIMIT 1'
    );
    $statement->execute(['id' => $id]);

    return $statement->fetchColumn() !== false;
}
