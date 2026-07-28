<?php

declare(strict_types=1);

function job_customer_confirmation_connection(): PDO
{
    $connection = database_connection();

    if ($connection === null) {
        throw new RuntimeException('Database connection is unavailable.');
    }

    return $connection;
}

function job_customer_confirmation_rules(): array
{
    return [
        'max_name_length' => 255,
        'max_email_length' => 255,
        'max_signature_bytes' => 1024 * 1024,
    ];
}

function job_customer_confirmation_directory(int $jobId): string
{
    return job_asset_base_directory()
        . DIRECTORY_SEPARATOR . 'jobs'
        . DIRECTORY_SEPARATOR . $jobId
        . DIRECTORY_SEPARATOR . 'confirmations';
}

function ensure_job_customer_confirmation_directory(int $jobId): string
{
    $directory = job_customer_confirmation_directory($jobId);

    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create the confirmation directory.');
    }

    return $directory;
}

function find_job_customer_confirmation(int $jobId): ?array
{
    $statement = job_customer_confirmation_connection()->prepare(
        'SELECT
            c.id,
            c.job_id,
            c.customer_name,
            c.customer_email,
            c.signature_path,
            c.signature_mime_type,
            c.signature_file_size,
            c.confirmed_at,
            c.confirmed_by_user_id,
            c.created_at,
            c.updated_at,
            u.name AS confirmed_by_user_name
         FROM job_customer_confirmations c
         LEFT JOIN users u ON u.id = c.confirmed_by_user_id
         WHERE c.job_id = :job_id
         LIMIT 1'
    );
    $statement->execute(['job_id' => $jobId]);
    $confirmation = $statement->fetch();

    return is_array($confirmation) ? $confirmation : null;
}

function create_job_customer_confirmation(
    int $jobId,
    string $customerName,
    ?string $customerEmail,
    string $signatureBinary,
    int $confirmedByUserId
): void {
    $directory = ensure_job_customer_confirmation_directory($jobId);
    $storedFilename = bin2hex(random_bytes(16)) . '.png';
    $storagePath = $directory . DIRECTORY_SEPARATOR . $storedFilename;
    $connection = job_customer_confirmation_connection();

    $connection->beginTransaction();

    try {
        if (file_put_contents($storagePath, $signatureBinary, LOCK_EX) === false) {
            throw new RuntimeException('The signature could not be stored.');
        }

        $statement = $connection->prepare(
            'INSERT INTO job_customer_confirmations (
                job_id,
                customer_name,
                customer_email,
                signature_path,
                signature_mime_type,
                signature_file_size,
                confirmed_by_user_id
             ) VALUES (
                :job_id,
                :customer_name,
                :customer_email,
                :signature_path,
                :signature_mime_type,
                :signature_file_size,
                :confirmed_by_user_id
             )'
        );
        $statement->execute([
            'job_id' => $jobId,
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'signature_path' => $storagePath,
            'signature_mime_type' => 'image/png',
            'signature_file_size' => filesize($storagePath) ?: strlen($signatureBinary),
            'confirmed_by_user_id' => $confirmedByUserId,
        ]);

        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        if (is_file($storagePath)) {
            @unlink($storagePath);
        }

        throw $exception;
    }
}

function delete_job_customer_confirmation(int $jobId): bool
{
    $confirmation = find_job_customer_confirmation($jobId);

    if ($confirmation === null) {
        return false;
    }

    $connection = job_customer_confirmation_connection();
    $connection->beginTransaction();

    try {
        $statement = $connection->prepare(
            'DELETE FROM job_customer_confirmations
             WHERE job_id = :job_id'
        );
        $statement->execute(['job_id' => $jobId]);

        if ($statement->rowCount() < 1) {
            $connection->rollBack();

            return false;
        }

        $path = (string) ($confirmation['signature_path'] ?? '');

        if ($path !== '' && is_file($path) && !@unlink($path)) {
            throw new RuntimeException('The stored signature could not be removed.');
        }

        $connection->commit();

        return true;
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        throw $exception;
    }
}

function job_customer_confirmation_signature_asset(array $confirmation): array
{
    return [
        'storage_path' => (string) ($confirmation['signature_path'] ?? ''),
        'mime_type' => (string) ($confirmation['signature_mime_type'] ?? 'image/png'),
        'file_size' => (int) ($confirmation['signature_file_size'] ?? 0),
        'original_filename' => 'customer-signature.png',
    ];
}

function user_can_record_job_customer_confirmation(array $user, array $job): bool
{
    $role = (string) ($user['role'] ?? '');

    if (in_array($role, ['admin', 'dispatcher'], true)) {
        return true;
    }

    return $role === 'worker' && (int) ($job['assigned_user_id'] ?? 0) === (int) ($user['id'] ?? 0);
}

function user_can_delete_job_customer_confirmation(array $user): bool
{
    return (string) ($user['role'] ?? '') === 'admin';
}

function job_can_accept_customer_confirmation(array $job): bool
{
    return (string) ($job['status'] ?? '') === 'completed';
}

