<?php

declare(strict_types=1);

function base_path(string $path = ''): string
{
    $basePath = dirname(__DIR__);

    return $path === '' ? $basePath : $basePath . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
}

function load_env_file(string $filePath): void
{
    static $loadedFiles = [];

    if (isset($loadedFiles[$filePath]) || !is_file($filePath)) {
        return;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = array_map('trim', explode('=', $line, 2));

        if ($name === '') {
            continue;
        }

        $value = trim($value, "\"'");

        if (getenv($name) === false) {
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }

    $loadedFiles[$filePath] = true;
}

function env_value(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return match (strtolower((string) $value)) {
        'true', '(true)' => true,
        'false', '(false)' => false,
        'null', '(null)' => null,
        default => $value,
    };
}

function app_config(): array
{
    static $config;

    if ($config === null) {
        load_env_file(base_path('.env'));
        $config = require base_path('app/config/config.php');
    }

    return $config;
}

function config(string $key, mixed $default = null): mixed
{
    $segments = explode('.', $key);
    $value = app_config();

    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }

        $value = $value[$segment];
    }

    return $value;
}

function is_debug(): bool
{
    return (bool) config('app.debug', false);
}

function request_path(): string
{
    $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $basePath = str_replace('\\', '/', dirname($scriptName));

    if ($basePath !== '/' && $basePath !== '.' && str_starts_with($uriPath, $basePath)) {
        $uriPath = substr($uriPath, strlen($basePath)) ?: '/';
    }

    return '/' . trim($uriPath, '/');
}

function app_url(string $path = ''): string
{
    $configuredBaseUrl = rtrim((string) config('app.url', ''), '/');
    $normalizedPath = '/' . ltrim($path, '/');

    if ($configuredBaseUrl !== '') {
        return $path === '' ? $configuredBaseUrl : $configuredBaseUrl . $normalizedPath;
    }

    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $basePath = str_replace('\\', '/', dirname($scriptName));
    $basePath = $basePath === '/' || $basePath === '.' ? '' : rtrim($basePath, '/');

    return ($basePath . ($path === '' ? '/' : $normalizedPath)) ?: '/';
}

function asset_url(string $path): string
{
    return app_url('assets/' . ltrim($path, '/'));
}

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function render(string $view, array $data = [], int $statusCode = 200): void
{
    http_response_code($statusCode);
    extract($data, EXTR_SKIP);

    $viewFile = base_path('app/views/' . $view . '.php');

    if (!is_file($viewFile)) {
        throw new RuntimeException('View not found: ' . $view);
    }

    ob_start();
    require $viewFile;
    $content = ob_get_clean() ?: '';

    require base_path('app/views/layouts/main.php');
}

function safe_error_message(string $message): string
{
    return is_debug() ? $message : 'Something went wrong. Please try again later.';
}

function start_session(): void
{
    if (PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function is_post_request(): bool
{
    return request_method() === 'POST';
}

function redirect(string $path): never
{
    header('Location: ' . app_url($path));
    exit;
}

function home_path_for_role(string $role): string
{
    return $role === 'worker' ? '/work' : '/dashboard';
}

function home_path_for_user(?array $user = null): string
{
    $user ??= current_user();

    return $user === null ? '/login' : home_path_for_role((string) ($user['role'] ?? ''));
}

function redirect_to_home(?array $user = null): never
{
    redirect(home_path_for_user($user));
}

function abort(int $statusCode, string $heading, string $message, mixed $details = null): never
{
    render('error', [
        'pageTitle' => $heading,
        'heading' => $heading,
        'message' => $message,
        'statusCode' => $statusCode,
        'details' => $details,
    ], $statusCode);

    exit;
}

function csrf_token(): string
{
    start_session();

    if (!isset($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

function verify_csrf_token(?string $token): bool
{
    start_session();

    return is_string($token)
        && isset($_SESSION['_csrf_token'])
        && is_string($_SESSION['_csrf_token'])
        && hash_equals($_SESSION['_csrf_token'], $token);
}

function flash(string $key, ?string $message = null): ?string
{
    start_session();

    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;

        return null;
    }

    $value = $_SESSION['_flash'][$key] ?? null;

    if (isset($_SESSION['_flash'][$key])) {
        unset($_SESSION['_flash'][$key]);
    }

    return is_string($value) ? $value : null;
}

function current_user_id(): ?int
{
    start_session();

    $userId = $_SESSION['user_id'] ?? null;

    return is_int($userId) ? $userId : null;
}

function find_user_by_email(string $email): ?array
{
    $connection = database_connection();

    if ($connection === null) {
        throw new RuntimeException('Database connection is unavailable.');
    }

    $statement = $connection->prepare(
        'SELECT id, name, email, password_hash, role, is_active
         FROM users
         WHERE email = :email
         LIMIT 1'
    );
    $statement->execute(['email' => $email]);
    $user = $statement->fetch();

    return is_array($user) ? $user : null;
}

function find_user_by_id(int $id): ?array
{
    $connection = database_connection();

    if ($connection === null) {
        throw new RuntimeException('Database connection is unavailable.');
    }

    $statement = $connection->prepare(
        'SELECT id, name, email, role, is_active
         FROM users
         WHERE id = :id
         LIMIT 1'
    );
    $statement->execute(['id' => $id]);
    $user = $statement->fetch();

    if (!is_array($user) || (int) ($user['is_active'] ?? 0) !== 1) {
        return null;
    }

    return $user;
}

function current_user(): ?array
{
    static $resolved = false;
    static $user = null;

    if ($resolved) {
        return $user;
    }

    $resolved = true;
    $userId = current_user_id();

    if ($userId === null) {
        return null;
    }

    $user = find_user_by_id($userId);

    if ($user === null) {
        logout_user();
    }

    return $user;
}

function is_authenticated(): bool
{
    return current_user() !== null;
}

function login_user(int $userId): void
{
    start_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
}

function logout_user(): void
{
    start_session();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
    session_regenerate_id(true);
}

function require_auth(): void
{
    if (!is_authenticated()) {
        redirect('/login');
    }
}

function require_guest(): void
{
    if (is_authenticated()) {
        redirect_to_home();
    }
}

function require_role(array $allowedRoles): void
{
    $user = current_user();

    if ($user === null) {
        redirect('/login');
    }

    if (!in_array($user['role'], $allowedRoles, true)) {
        render('errors/403', [
            'pageTitle' => 'Access denied',
            'allowedRoles' => $allowedRoles,
        ], 403);
        exit;
    }
}

function auth_navigation_items(?array $user = null): array
{
    $user ??= current_user();

    if ($user === null) {
        return [];
    }

    $role = (string) ($user['role'] ?? '');

    if ($role === 'worker') {
        return [
            ['label' => 'My Work', 'path' => '/work'],
        ];
    }

    $items = [
        ['label' => 'Dashboard', 'path' => '/dashboard'],
        ['label' => 'Customers', 'path' => '/customers'],
        ['label' => 'Locations', 'path' => '/locations'],
        ['label' => 'Tasks', 'path' => '/tasks'],
        ['label' => 'Jobs', 'path' => '/jobs'],
    ];

    if ($role === 'admin') {
        $items[] = ['label' => 'Users', 'path' => '/users'];
    }

    return $items;
}

function role_label(string $role): string
{
    return match ($role) {
        'admin' => 'Administrator',
        'dispatcher' => 'Dispatcher',
        'worker' => 'Field Worker',
        default => ucfirst($role),
    };
}

function user_role_options(): array
{
    return [
        'admin' => 'Administrator',
        'dispatcher' => 'Dispatcher',
        'worker' => 'Field Worker',
    ];
}

function password_min_length(): int
{
    return 8;
}

function validate_password_strength(string $password): ?string
{
    if (strlen($password) < password_min_length()) {
        return sprintf('Password must be at least %d characters.', password_min_length());
    }

    return null;
}

function is_current_path(string $path): bool
{
    $currentPath = request_path();

    return $currentPath === $path
        || ($path !== '/' && str_starts_with($currentPath, rtrim($path, '/') . '/'));
}

function positive_int_or_null(mixed $value): ?int
{
    if (is_int($value) && $value > 0) {
        return $value;
    }

    if (!is_string($value) || !preg_match('/^[1-9][0-9]*$/', $value)) {
        return null;
    }

    return (int) $value;
}

function location_address(array $location): string
{
    $parts = [];

    foreach (['address_line', 'city', 'postal_code', 'country'] as $field) {
        $value = trim((string) ($location[$field] ?? ''));

        if ($value !== '') {
            $parts[] = $value;
        }
    }

    return implode(', ', $parts);
}

function format_datetime(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return 'Not available';
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return $value;
    }

    return date('Y-m-d H:i', $timestamp);
}

function format_date(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return 'Not scheduled';
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return $value;
    }

    return date('Y-m-d', $timestamp);
}

function format_time(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return 'Not scheduled';
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return $value;
    }

    return date('H:i', $timestamp);
}

function format_job_scheduled_start(array $job): string
{
    $date = $job['planned_date'] ?? null;
    $time = $job['planned_start_time'] ?? null;

    if (($date === null || trim((string) $date) === '') && ($time === null || trim((string) $time) === '')) {
        return 'Not scheduled';
    }

    if ($date === null || trim((string) $date) === '') {
        return format_time(is_string($time) ? $time : null);
    }

    $formatted = format_date((string) $date);

    if ($time !== null && trim((string) $time) !== '') {
        $formatted .= ' ' . format_time((string) $time);
    }

    return $formatted;
}

function format_job_scheduled_end(array $job): string
{
    $plannedDate = trim((string) ($job['planned_date'] ?? ''));
    $plannedStartTime = trim((string) ($job['planned_start_time'] ?? ''));
    $estimatedDuration = (int) ($job['estimated_duration_minutes'] ?? 0);

    if ($plannedDate === '' || $plannedStartTime === '' || $estimatedDuration <= 0) {
        return 'Not scheduled';
    }

    $timestamp = strtotime($plannedDate . ' ' . $plannedStartTime);

    if ($timestamp === false) {
        return 'Not scheduled';
    }

    return date('Y-m-d H:i', $timestamp + ($estimatedDuration * 60));
}

function job_type_options(): array
{
    return [
        'installation' => 'Installation',
        'maintenance' => 'Maintenance',
        'repair' => 'Repair',
        'inspection' => 'Inspection',
        'delivery' => 'Delivery',
        'other' => 'Other',
    ];
}

function job_priority_options(): array
{
    return [
        'low' => 'Low',
        'normal' => 'Normal',
        'high' => 'High',
        'urgent' => 'Urgent',
    ];
}

function job_status_options(): array
{
    return [
        'draft' => 'Draft',
        'planned' => 'Planned',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];
}

function job_type_label(string $value): string
{
    return job_type_options()[$value] ?? ucfirst($value);
}

function job_priority_label(string $value): string
{
    return job_priority_options()[$value] ?? ucfirst($value);
}

function job_status_label(string $value): string
{
    return job_status_options()[$value] ?? ucfirst($value);
}

function job_status_badge_class(string $value): string
{
    return match ($value) {
        'planned' => 'text-bg-primary',
        'in_progress' => 'text-bg-warning',
        'completed' => 'text-bg-success',
        'cancelled' => 'text-bg-secondary',
        default => 'text-bg-light text-dark',
    };
}

start_session();
