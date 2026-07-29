<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/helpers.php';
require dirname(__DIR__) . '/app/database/connection.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

if (!database_configured()) {
    fwrite(STDERR, "Database credentials are missing. Update .env before running this command.\n");
    exit(1);
}

$options = getopt('', ['email:', 'name:', 'password:']);
$email = trim((string) ($options['email'] ?? ''));
$name = trim((string) ($options['name'] ?? ''));
$password = (string) ($options['password'] ?? '');

if ($email === '' || $name === '' || $password === '') {
    fwrite(STDERR, "Usage: php bin/create-super-admin.php --email=you@example.com --name=\"Your Name\" --password='strong-password'\n");
    exit(1);
}

if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, "Invalid email address.\n");
    exit(1);
}

$passwordError = validate_password_strength($password);

if ($passwordError !== null) {
    fwrite(STDERR, $passwordError . "\n");
    exit(1);
}

$connection = database_connection();

if ($connection === null) {
    fwrite(STDERR, "Unable to create a PDO connection.\n");
    exit(1);
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

if ($passwordHash === false) {
    fwrite(STDERR, "Unable to hash the password.\n");
    exit(1);
}

$statement = $connection->prepare(
    "INSERT INTO users (name, email, password_hash, role, is_active)
     VALUES (:name, :email, :password_hash, 'super_admin', 1)
     ON DUPLICATE KEY UPDATE
        name = VALUES(name),
        password_hash = VALUES(password_hash),
        role = 'super_admin',
        is_active = 1,
        updated_at = CURRENT_TIMESTAMP"
);
$statement->execute([
    'name' => $name,
    'email' => $email,
    'password_hash' => $passwordHash,
]);

fwrite(STDOUT, "Super admin is ready: {$email}\n");
