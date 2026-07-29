<?php

declare(strict_types=1);

function locations_connection(): PDO
{
    $connection = database_connection();

    if ($connection === null) {
        throw new RuntimeException('Database connection is unavailable.');
    }

    return $connection;
}

function list_locations(?int $customerId = null): array
{
    $sql = 'SELECT
                l.id,
                l.company_id,
                l.customer_id,
                l.name,
                l.address_line,
                l.city,
                l.postal_code,
                l.country,
                l.contact_name,
                l.contact_phone,
                l.notes,
                l.is_active,
                l.created_at,
                l.updated_at,
                c.name AS customer_name
            FROM locations l
            INNER JOIN customers c ON c.id = l.customer_id
            WHERE 1 = 1';
    $params = [];

    $sql .= scoped_company_sql('l.company_id', $params);

    if ($customerId !== null) {
        $sql .= ' AND l.customer_id = :customer_id';
        $params['customer_id'] = $customerId;
    }

    $sql .= ' ORDER BY c.name ASC, l.name ASC, l.id ASC';

    $statement = locations_connection()->prepare($sql);
    $statement->execute($params);
    $locations = $statement->fetchAll();

    return is_array($locations) ? $locations : [];
}

function list_locations_for_customer(int $customerId): array
{
    $params = ['customer_id' => $customerId];
    $sql = 'SELECT
                id,
                company_id,
                customer_id,
                name,
                address_line,
                city,
                postal_code,
                country,
                contact_name,
                contact_phone,
                notes,
                is_active,
                created_at,
                updated_at
            FROM locations
            WHERE customer_id = :customer_id';
    $sql .= scoped_company_sql('company_id', $params);
    $sql .= ' ORDER BY name ASC, id ASC';
    $statement = locations_connection()->prepare($sql);
    $statement->execute($params);
    $locations = $statement->fetchAll();

    return is_array($locations) ? $locations : [];
}

function list_active_locations(): array
{
    $params = [];
    $sql = 'SELECT
                id,
                company_id,
                customer_id,
                name,
                address_line,
                city,
                postal_code,
                country,
                contact_name,
                contact_phone,
                notes,
                is_active,
                created_at,
                updated_at
            FROM locations
            WHERE is_active = 1';
    $sql .= scoped_company_sql('company_id', $params);
    $sql .= ' ORDER BY name ASC, id ASC';
    $statement = locations_connection()->prepare($sql);
    $statement->execute($params);
    $locations = $statement->fetchAll();

    return is_array($locations) ? $locations : [];
}

function find_location_by_id(int $id): ?array
{
    $params = ['id' => $id];
    $sql = 'SELECT
                l.id,
                l.company_id,
                l.customer_id,
                l.name,
                l.address_line,
                l.city,
                l.postal_code,
                l.country,
                l.contact_name,
                l.contact_phone,
                l.notes,
                l.is_active,
                l.created_at,
                l.updated_at,
                c.name AS customer_name
            FROM locations l
            INNER JOIN customers c ON c.id = l.customer_id
            WHERE l.id = :id';
    $sql .= scoped_company_sql('l.company_id', $params);
    $sql .= ' LIMIT 1';
    $statement = locations_connection()->prepare($sql);
    $statement->execute($params);
    $location = $statement->fetch();

    return is_array($location) ? $location : null;
}

function create_location(array $data): int
{
    $statement = locations_connection()->prepare(
        'INSERT INTO locations (
            company_id,
            customer_id,
            name,
            address_line,
            contact_name,
            contact_phone,
            notes,
            is_active
        ) VALUES (
            :company_id,
            :customer_id,
            :name,
            :address_line,
            :contact_name,
            :contact_phone,
            :notes,
            :is_active
        )'
    );
    $statement->execute([
        'company_id' => $data['company_id'],
        'customer_id' => $data['customer_id'],
        'name' => $data['name'],
        'address_line' => $data['address_line'],
        'contact_name' => $data['contact_name'],
        'contact_phone' => $data['contact_phone'],
        'notes' => $data['notes'],
        'is_active' => $data['is_active'],
    ]);

    return (int) locations_connection()->lastInsertId();
}

function update_location(int $id, array $data): void
{
    $statement = locations_connection()->prepare(
        'UPDATE locations
         SET customer_id = :customer_id,
             company_id = :company_id,
             name = :name,
             address_line = :address_line,
             contact_name = :contact_name,
             contact_phone = :contact_phone,
             notes = :notes,
             is_active = :is_active
         WHERE id = :id'
    );
    $statement->execute([
        'id' => $id,
        'company_id' => $data['company_id'],
        'customer_id' => $data['customer_id'],
        'name' => $data['name'],
        'address_line' => $data['address_line'],
        'contact_name' => $data['contact_name'],
        'contact_phone' => $data['contact_phone'],
        'notes' => $data['notes'],
        'is_active' => $data['is_active'],
    ]);
}
