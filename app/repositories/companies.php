<?php

declare(strict_types=1);

function companies_connection(): PDO
{
    $connection = database_connection();

    if ($connection === null) {
        throw new RuntimeException('Database connection is unavailable.');
    }

    return $connection;
}

function list_companies(bool $includeInactive = true): array
{
    $sql = 'SELECT
                c.id,
                c.name,
                c.registration_number,
                c.email,
                c.phone,
                c.address,
                c.is_active,
                c.created_at,
                c.updated_at,
                COUNT(DISTINCT cu.user_id) AS active_member_count
            FROM companies c
            LEFT JOIN company_users cu
                ON cu.company_id = c.id
               AND cu.is_active = 1
            WHERE 1 = 1';

    if (!$includeInactive) {
        $sql .= ' AND c.is_active = 1';
    }

    $sql .= ' GROUP BY c.id
              ORDER BY c.is_active DESC, c.name ASC, c.id ASC';

    $statement = companies_connection()->query($sql);
    $companies = $statement->fetchAll();

    return is_array($companies) ? $companies : [];
}

function list_companies_for_user(int $userId, bool $includeInactive = false): array
{
    $sql = 'SELECT
                c.id,
                c.name,
                c.registration_number,
                c.email,
                c.phone,
                c.address,
                c.is_active,
                cu.role AS membership_role,
                cu.is_active AS membership_is_active
            FROM companies c
            INNER JOIN company_users cu
                ON cu.company_id = c.id
               AND cu.user_id = :user_id
            WHERE cu.is_active = 1';

    if (!$includeInactive) {
        $sql .= ' AND c.is_active = 1';
    }

    $sql .= ' ORDER BY c.name ASC, c.id ASC';

    $statement = companies_connection()->prepare($sql);
    $statement->execute(['user_id' => $userId]);
    $companies = $statement->fetchAll();

    return is_array($companies) ? $companies : [];
}

function find_company_by_id(int $id): ?array
{
    $statement = companies_connection()->prepare(
        'SELECT id, name, registration_number, email, phone, address, is_active, created_at, updated_at
         FROM companies
         WHERE id = :id
         LIMIT 1'
    );
    $statement->execute(['id' => $id]);
    $company = $statement->fetch();

    return is_array($company) ? $company : null;
}

function create_company(array $data): int
{
    $statement = companies_connection()->prepare(
        'INSERT INTO companies (name, registration_number, email, phone, address, is_active)
         VALUES (:name, :registration_number, :email, :phone, :address, :is_active)'
    );
    $statement->execute([
        'name' => $data['name'],
        'registration_number' => $data['registration_number'],
        'email' => $data['email'],
        'phone' => $data['phone'],
        'address' => $data['address'],
        'is_active' => $data['is_active'],
    ]);

    return (int) companies_connection()->lastInsertId();
}

function update_company(int $id, array $data): void
{
    $statement = companies_connection()->prepare(
        'UPDATE companies
         SET name = :name,
             registration_number = :registration_number,
             email = :email,
             phone = :phone,
             address = :address,
             is_active = :is_active
         WHERE id = :id'
    );
    $statement->execute([
        'id' => $id,
        'name' => $data['name'],
        'registration_number' => $data['registration_number'],
        'email' => $data['email'],
        'phone' => $data['phone'],
        'address' => $data['address'],
        'is_active' => $data['is_active'],
    ]);
}

function set_company_active_status(int $id, bool $isActive): void
{
    $statement = companies_connection()->prepare(
        'UPDATE companies
         SET is_active = :is_active
         WHERE id = :id'
    );
    $statement->execute([
        'id' => $id,
        'is_active' => $isActive ? 1 : 0,
    ]);
}

function list_company_memberships(int $companyId): array
{
    $statement = companies_connection()->prepare(
        "SELECT
            u.id,
            u.name,
            u.email,
            u.role AS global_role,
            u.is_active,
            cu.role AS membership_role,
            cu.is_active AS membership_is_active,
            cu.created_at,
            cu.updated_at
         FROM company_users cu
         INNER JOIN users u ON u.id = cu.user_id
         WHERE cu.company_id = :company_id
         ORDER BY
            cu.is_active DESC,
            CASE cu.role
                WHEN 'admin' THEN 0
                WHEN 'dispatcher' THEN 1
                ELSE 2
            END ASC,
            u.name ASC"
    );
    $statement->execute(['company_id' => $companyId]);
    $memberships = $statement->fetchAll();

    return is_array($memberships) ? $memberships : [];
}

function find_company_membership(int $companyId, int $userId): ?array
{
    $statement = companies_connection()->prepare(
        "SELECT
            cu.company_id,
            cu.user_id,
            cu.role,
            cu.is_active,
            cu.created_at,
            cu.updated_at
         FROM company_users cu
         WHERE cu.company_id = :company_id
           AND cu.user_id = :user_id
         LIMIT 1"
    );
    $statement->execute([
        'company_id' => $companyId,
        'user_id' => $userId,
    ]);
    $membership = $statement->fetch();

    return is_array($membership) ? $membership : null;
}

function upsert_company_membership(int $companyId, int $userId, string $role, bool $isActive): void
{
    $statement = companies_connection()->prepare(
        "INSERT INTO company_users (company_id, user_id, role, is_active)
         VALUES (:company_id, :user_id, :role, :is_active)
         ON DUPLICATE KEY UPDATE
            role = VALUES(role),
            is_active = VALUES(is_active),
            updated_at = CURRENT_TIMESTAMP"
    );
    $statement->execute([
        'company_id' => $companyId,
        'user_id' => $userId,
        'role' => $role,
        'is_active' => $isActive ? 1 : 0,
    ]);
}

function remove_company_membership(int $companyId, int $userId): void
{
    $statement = companies_connection()->prepare(
        'DELETE FROM company_users
         WHERE company_id = :company_id
           AND user_id = :user_id'
    );
    $statement->execute([
        'company_id' => $companyId,
        'user_id' => $userId,
    ]);
}
