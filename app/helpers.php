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

function app_environment(): string
{
    return strtolower((string) config('app.env', 'development'));
}

function app_is_production(): bool
{
    return app_environment() === 'production';
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

function available_locales(): array
{
    return ['en', 'lv'];
}

function default_locale(): string
{
    return 'en';
}

function normalize_locale(?string $locale): string
{
    $locale = strtolower(trim((string) $locale));

    return in_array($locale, available_locales(), true) ? $locale : default_locale();
}

function language_cookie_name(): string
{
    return 'task_locale';
}

function current_locale(): string
{
    static $resolvedLocale = null;

    if ($resolvedLocale !== null) {
        return $resolvedLocale;
    }

    start_session();

    $resolvedLocale = normalize_locale(
        is_string($_SESSION['locale'] ?? null)
            ? $_SESSION['locale']
            : (is_string($_COOKIE[language_cookie_name()] ?? null) ? $_COOKIE[language_cookie_name()] : null)
    );

    $_SESSION['locale'] = $resolvedLocale;

    return $resolvedLocale;
}

function set_current_locale(?string $locale): string
{
    $normalized = normalize_locale($locale);

    start_session();
    $_SESSION['locale'] = $normalized;

    setcookie(language_cookie_name(), $normalized, [
        'expires' => time() + (365 * 24 * 60 * 60),
        'path' => '/',
        'secure' => app_is_production() ? request_is_https() : false,
        'httponly' => false,
        'samesite' => 'Lax',
    ]);

    return $normalized;
}

function language_label(string $locale): string
{
    return match (normalize_locale($locale)) {
        'lv' => 'LV',
        default => 'EN',
    };
}

function language_native_name(string $locale): string
{
    return match (normalize_locale($locale)) {
        'lv' => 'Latviesu',
        default => 'English',
    };
}

function translation_catalog(string $locale): array
{
    static $catalogs = [];
    $locale = normalize_locale($locale);

    if (!array_key_exists($locale, $catalogs)) {
        $file = base_path('app/lang/' . $locale . '.php');
        $catalogs[$locale] = is_file($file) ? require $file : [];
    }

    return $catalogs[$locale];
}

function translation_value(string $key, string $locale): mixed
{
    $value = translation_catalog($locale);

    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return null;
        }

        $value = $value[$segment];
    }

    return $value;
}

function interpolate_translation(string $text, array $replace = []): string
{
    if ($replace === []) {
        return $text;
    }

    $replacements = [];

    foreach ($replace as $key => $value) {
        $replacements[':' . $key] = (string) $value;
    }

    return strtr($text, $replacements);
}

function __(string $key, array $replace = [], ?string $locale = null): string
{
    $locale ??= current_locale();
    $value = translation_value($key, $locale);

    if (!is_string($value)) {
        $value = translation_value($key, default_locale());
    }

    if (!is_string($value)) {
        return $key;
    }

    return interpolate_translation($value, $replace);
}

function translation_phrase_map(?string $locale = null): array
{
    $locale ??= current_locale();
    $phrases = translation_value('phrases', $locale);

    return is_array($phrases) ? $phrases : [];
}

function translate_literal(string $text, ?string $locale = null): string
{
    $locale ??= current_locale();

    if ($locale === default_locale()) {
        return $text;
    }

    $translated = translation_phrase_map($locale)[$text] ?? null;

    return is_string($translated) ? $translated : $text;
}

function localize_output(string $content, ?string $locale = null): string
{
    $locale ??= current_locale();

    if ($locale === default_locale()) {
        return $content;
    }

    $phrases = translation_phrase_map($locale);

    if ($phrases === []) {
        return $content;
    }

    uksort($phrases, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));

    return strtr($content, $phrases);
}

function locale_month_names(string $locale): array
{
    return normalize_locale($locale) === 'lv'
        ? ['janv.', 'febr.', 'marts', 'apr.', 'maijs', 'jūn.', 'jūl.', 'aug.', 'sept.', 'okt.', 'nov.', 'dec.']
        : ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
}

function localized_short_month(int $month, ?string $locale = null): string
{
    $locale ??= current_locale();
    $months = locale_month_names($locale);

    return $months[max(0, min(11, $month - 1))];
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
    $content = localize_output(ob_get_clean() ?: '');

    require base_path('app/views/layouts/main.php');
}

function safe_error_message(string $message): string
{
    return is_debug() ? $message : __('errors.generic');
}

function request_is_https(): bool
{
    if (PHP_SAPI === 'cli') {
        return false;
    }

    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }

    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function session_cookie_options(): array
{
    return [
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => app_is_production() ? request_is_https() : false,
        'use_strict_mode' => true,
        'use_only_cookies' => true,
    ];
}

function start_session(): void
{
    if (PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_start(session_cookie_options());
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

    return is_string($value) ? translate_literal($value) : null;
}

function current_user_id(): ?int
{
    start_session();

    $userId = $_SESSION['user_id'] ?? null;

    return is_int($userId) ? $userId : null;
}

function current_company_context_value(): int|string|null
{
    start_session();

    $value = $_SESSION['active_company_id'] ?? null;

    if ($value === 'all') {
        return 'all';
    }

    if (is_int($value) && $value > 0) {
        return $value;
    }

    if (is_string($value) && preg_match('/^[1-9][0-9]*$/', $value) === 1) {
        return (int) $value;
    }

    return null;
}

function set_current_company_context(int|string|null $companyId): void
{
    start_session();

    if ($companyId === 'all') {
        $_SESSION['active_company_id'] = 'all';

        return;
    }

    if (is_int($companyId) && $companyId > 0) {
        $_SESSION['active_company_id'] = $companyId;

        return;
    }

    unset($_SESSION['active_company_id']);
}

function is_super_admin(?array $user = null): bool
{
    $user ??= current_user();

    return $user !== null && (string) ($user['global_role'] ?? $user['role'] ?? '') === 'super_admin';
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

function normalize_user_company_context(array $user): array
{
    $user['global_role'] = (string) ($user['role'] ?? '');
    $user['is_super_admin'] = $user['global_role'] === 'super_admin';
    $user['available_companies'] = list_companies_for_user((int) $user['id'], false);
    $user['active_company'] = null;
    $user['active_company_id'] = null;
    $user['active_company_name'] = null;
    $user['current_membership_role'] = null;
    $user['can_access_all_companies'] = $user['is_super_admin'];

    $activeCompanies = array_values(array_filter(
        $user['available_companies'],
        static fn (array $company): bool => (int) ($company['is_active'] ?? 0) === 1
            && (int) ($company['membership_is_active'] ?? 0) === 1
    ));
    $requestedContext = current_company_context_value();

    if ($user['is_super_admin']) {
        if ($requestedContext === 'all') {
            $user['role'] = 'super_admin';

            return $user;
        }

        if (is_int($requestedContext)) {
            $selectedCompany = find_company_by_id($requestedContext);

            if ($selectedCompany !== null && (int) ($selectedCompany['is_active'] ?? 0) === 1) {
                $user['active_company'] = $selectedCompany;
                $user['active_company_id'] = (int) $selectedCompany['id'];
                $user['active_company_name'] = (string) $selectedCompany['name'];
            }
        }

        if ($user['active_company'] === null) {
            set_current_company_context('all');
        }

        $user['role'] = 'super_admin';

        return $user;
    }

    $selectedCompany = null;

    if (is_int($requestedContext)) {
        foreach ($activeCompanies as $company) {
            if ((int) $company['id'] === $requestedContext) {
                $selectedCompany = $company;
                break;
            }
        }
    }

    if ($selectedCompany === null && count($activeCompanies) === 1) {
        $selectedCompany = $activeCompanies[0];
        set_current_company_context((int) $selectedCompany['id']);
    }

    if ($selectedCompany !== null) {
        $user['active_company'] = $selectedCompany;
        $user['active_company_id'] = (int) $selectedCompany['id'];
        $user['active_company_name'] = (string) $selectedCompany['name'];
        $user['current_membership_role'] = (string) $selectedCompany['membership_role'];
        $user['role'] = (string) $selectedCompany['membership_role'];
    } else {
        $user['role'] = '';
        set_current_company_context(null);
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

    $resolvedUser = find_user_by_id($userId);

    if ($resolvedUser === null) {
        logout_user();

        return null;
    }

    $user = normalize_user_company_context($resolvedUser);

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
    $user = find_user_by_id($userId);

    if ($user === null) {
        return;
    }

    if ((string) ($user['role'] ?? '') === 'super_admin') {
        set_current_company_context('all');

        return;
    }

    $companies = list_companies_for_user($userId, false);

    if (count($companies) === 1) {
        set_current_company_context((int) $companies[0]['id']);

        return;
    }

    set_current_company_context(null);
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
    session_start(session_cookie_options());
    session_regenerate_id(true);
}

function utc_now(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone('UTC'));
}

function utc_timestamp(): string
{
    return utc_now()->format('Y-m-d H:i:s');
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

    if (!is_super_admin($user) && (string) ($user['role'] ?? '') === '') {
        redirect('/company-context');
    }

    if (is_super_admin($user)) {
        return;
    }

    if (!in_array((string) $user['role'], $allowedRoles, true)) {
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
            ['label' => translate_literal('My Work'), 'path' => '/work'],
            ['label' => translate_literal('Materials'), 'path' => '/materials'],
            ['label' => translate_literal('Calendar'), 'path' => '/jobs/calendar'],
        ];
    }

    $items = [
        ['label' => translate_literal('Dashboard'), 'path' => '/dashboard'],
        ['label' => translate_literal('Customers'), 'path' => '/customers'],
        ['label' => translate_literal('Locations'), 'path' => '/locations'],
        ['label' => translate_literal('Tasks'), 'path' => '/tasks'],
        ['label' => translate_literal('Jobs'), 'path' => '/jobs'],
        ['label' => translate_literal('Materials'), 'path' => '/materials'],
        ['label' => translate_literal('Calendar'), 'path' => '/jobs/calendar'],
    ];

    if ($role === 'admin' || is_super_admin($user)) {
        $items[] = ['label' => translate_literal('Users'), 'path' => '/users'];
    }

    if (is_super_admin($user)) {
        $items[] = ['label' => translate_literal('Companies'), 'path' => '/companies'];
    }

    return $items;
}

function role_label(string $role): string
{
    return translate_literal(match ($role) {
        'super_admin' => 'Super Admin',
        'admin' => 'Administrator',
        'dispatcher' => 'Dispatcher',
        'worker' => 'Field Worker',
        '' => 'No company selected',
        default => ucfirst($role),
    });
}

function user_initials(?array $user = null): string
{
    $user ??= current_user();
    $name = trim((string) ($user['name'] ?? ''));

    if ($name === '') {
        return 'TA';
    }

    $parts = preg_split('/\s+/', $name) ?: [];
    $initials = '';

    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }

        $initials .= strtoupper(substr($part, 0, 1));

        if (strlen($initials) >= 2) {
            break;
        }
    }

    return $initials !== '' ? $initials : 'TA';
}

function current_navigation_label(array $navigationItems): string
{
    foreach ($navigationItems as $item) {
        if (is_current_path((string) ($item['path'] ?? ''))) {
            return (string) ($item['label'] ?? translate_literal('Task'));
        }
    }

    return translate_literal('Task');
}

function user_role_options(): array
{
    return [
        'admin' => translate_literal('Administrator'),
        'dispatcher' => translate_literal('Dispatcher'),
        'worker' => translate_literal('Field Worker'),
    ];
}

function current_company_id(): ?int
{
    $user = current_user();

    return $user !== null && is_int($user['active_company_id'] ?? null)
        ? (int) $user['active_company_id']
        : null;
}

function has_active_company_context(?array $user = null, bool $allowAll = false): bool
{
    $user ??= current_user();

    if ($user === null) {
        return false;
    }

    if (is_super_admin($user) && $allowAll && current_company_context_value() === 'all') {
        return true;
    }

    return is_int($user['active_company_id'] ?? null);
}

function require_active_company_context(bool $allowAll = false): void
{
    if (!has_active_company_context(current_user(), $allowAll)) {
        redirect('/company-context');
    }
}

function company_context_options(?array $user = null): array
{
    $user ??= current_user();

    if ($user === null) {
        return [];
    }

    $options = [];

    if (is_super_admin($user)) {
        $options[] = [
            'id' => 'all',
            'name' => translate_literal('All companies'),
        ];

        foreach (list_companies(false) as $company) {
            $options[] = [
                'id' => (int) $company['id'],
                'name' => (string) $company['name'],
            ];
        }

        return $options;
    }

    foreach ($user['available_companies'] as $company) {
        $options[] = [
            'id' => (int) $company['id'],
            'name' => (string) $company['name'],
        ];
    }

    return $options;
}

function current_company_context_label(?array $user = null): string
{
    $user ??= current_user();

    if ($user === null) {
        return '';
    }

    if (is_super_admin($user) && current_company_context_value() === 'all') {
        return translate_literal('All companies');
    }

    return (string) ($user['active_company_name'] ?? translate_literal('Select company'));
}

function user_can_view_current_company_page(?array $user = null): bool
{
    $user ??= current_user();

    if ($user === null) {
        return false;
    }

    if (is_super_admin($user)) {
        return is_int($user['active_company_id'] ?? null);
    }

    return (string) ($user['role'] ?? '') === 'admin'
        && is_int($user['active_company_id'] ?? null);
}

function scoped_company_sql(string $column, array &$params, ?array $user = null, bool $allowAll = true): string
{
    $user ??= current_user();

    if ($user === null) {
        return ' AND 1 = 0';
    }

    if (is_super_admin($user) && $allowAll && current_company_context_value() === 'all') {
        return '';
    }

    $companyId = $user['active_company_id'] ?? null;

    if (!is_int($companyId)) {
        return ' AND 1 = 0';
    }

    $params['__scoped_company_id'] = $companyId;

    return sprintf(' AND %s = :__scoped_company_id', $column);
}

function password_min_length(): int
{
    return 8;
}

function validate_password_strength(string $password): ?string
{
    if (strlen($password) < password_min_length()) {
        return translate_literal(sprintf('Password must be at least %d characters.', password_min_length()));
    }

    return null;
}

function is_current_path(string $path): bool
{
    $currentPath = request_path();

    if ($currentPath === '/jobs/calendar' && $path === '/jobs') {
        return false;
    }

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

function job_contact_details(array $job): array
{
    $name = trim((string) ($job['location_contact_name'] ?? ''));
    if ($name === '') {
        $name = trim((string) ($job['customer_contact_name'] ?? ''));
    }

    $phone = trim((string) ($job['location_contact_phone'] ?? ''));
    if ($phone === '') {
        $phone = trim((string) ($job['customer_contact_phone'] ?? ''));
    }

    $email = trim((string) ($job['customer_contact_email'] ?? ''));

    return [
        'name' => $name,
        'phone' => $phone,
        'email' => $email,
    ];
}

function job_has_contact_details(array $job): bool
{
    $contact = job_contact_details($job);

    return $contact['name'] !== '' || $contact['phone'] !== '' || $contact['email'] !== '';
}

function phone_href(string $phone): string
{
    $clean = preg_replace('/[^0-9+]/', '', trim($phone));

    return $clean === null ? '' : $clean;
}

function maps_uri(string $address): ?string
{
    $normalized = trim($address);

    if ($normalized === '') {
        return null;
    }

    return 'geo:0,0?q=' . rawurlencode($normalized);
}

function maps_search_url(string $address): ?string
{
    $normalized = trim($address);

    if ($normalized === '') {
        return null;
    }

    return 'https://www.openstreetmap.org/search?query=' . rawurlencode($normalized);
}

function format_datetime(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return __('common.not_available');
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return $value;
    }

    return date('Y-m-d H:i', $timestamp);
}

function format_file_size(int|string|null $bytes): string
{
    $value = is_numeric($bytes) ? (float) $bytes : 0.0;

    if ($value < 1024) {
        return (string) (int) $value . ' B';
    }

    $units = ['KB', 'MB', 'GB', 'TB'];
    $scaled = $value;
    $unitIndex = -1;

    while ($scaled >= 1024 && $unitIndex < count($units) - 1) {
        $scaled /= 1024;
        $unitIndex++;
    }

    return number_format($scaled, $scaled >= 10 ? 0 : 1) . ' ' . $units[$unitIndex];
}

function format_display_datetime(?string $value, string $fallback = 'Not set'): string
{
    if ($value === null || trim($value) === '') {
        return translate_literal($fallback);
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return $value;
    }

    return date('d', $timestamp) . ' ' . localized_short_month((int) date('n', $timestamp)) . ' ' . date('Y, H:i', $timestamp);
}

function format_date(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return __('common.not_scheduled');
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return $value;
    }

    return date('Y-m-d', $timestamp);
}

function format_display_date(?string $value, string $fallback = 'Not set'): string
{
    if ($value === null || trim($value) === '') {
        return translate_literal($fallback);
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return $value;
    }

    return date('d', $timestamp) . ' ' . localized_short_month((int) date('n', $timestamp)) . ' ' . date('Y', $timestamp);
}

function format_time(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return __('common.not_scheduled');
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
        return __('common.not_scheduled');
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
        return __('common.not_scheduled');
    }

    $timestamp = strtotime($plannedDate . ' ' . $plannedStartTime);

    if ($timestamp === false) {
        return __('common.not_scheduled');
    }

    return date('Y-m-d H:i', $timestamp + ($estimatedDuration * 60));
}

function job_type_options(): array
{
    return [
        'installation' => translate_literal('Installation'),
        'maintenance' => translate_literal('Maintenance'),
        'repair' => translate_literal('Repair'),
        'inspection' => translate_literal('Inspection'),
        'delivery' => translate_literal('Delivery'),
        'other' => translate_literal('Other'),
    ];
}

function job_priority_options(): array
{
    return [
        'low' => translate_literal('Low'),
        'normal' => translate_literal('Normal'),
        'high' => translate_literal('High'),
        'urgent' => translate_literal('Urgent'),
    ];
}

function task_priority_options(): array
{
    return job_priority_options();
}

function task_status_options(): array
{
    return [
        'new' => translate_literal('New'),
        'planned' => translate_literal('Planned'),
        'in_progress' => translate_literal('In Progress'),
        'completed' => translate_literal('Completed'),
        'cancelled' => translate_literal('Cancelled'),
    ];
}

function task_due_state_options(): array
{
    return [
        'overdue' => __('tasks.due_state.overdue'),
        'due_today' => __('tasks.due_state.due_today'),
        'upcoming' => __('tasks.due_state.upcoming'),
        'no_due_date' => __('tasks.due_state.no_due_date'),
    ];
}

function job_status_options(): array
{
    return [
        'draft' => __('jobs.status.draft'),
        'planned' => __('jobs.status.planned'),
        'in_progress' => __('jobs.status.in_progress'),
        'completed' => __('jobs.status.completed'),
        'cancelled' => __('jobs.status.cancelled'),
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

function task_priority_label(string $value): string
{
    return task_priority_options()[$value] ?? ucfirst($value);
}

function task_priority_badge_class(string $value): string
{
    return match ($value) {
        'urgent' => 'text-bg-danger',
        'high' => 'text-bg-warning text-dark',
        'normal' => 'text-bg-secondary',
        default => 'text-bg-light text-dark border',
    };
}

function task_status_label(string $value): string
{
    return task_status_options()[$value] ?? ucfirst(str_replace('_', ' ', $value));
}

function job_status_label(string $value): string
{
    return job_status_options()[$value] ?? ucfirst($value);
}

function format_decimal_quantity(mixed $value): string
{
    $formatted = trim((string) $value);

    if ($formatted === '') {
        return '0';
    }

    if (str_contains($formatted, '.')) {
        $formatted = rtrim($formatted, '0');
        $formatted = rtrim($formatted, '.');
    }

    return $formatted === '' ? '0' : $formatted;
}

function task_status_badge_class(string $value): string
{
    return match ($value) {
        'planned' => 'text-bg-primary',
        'in_progress' => 'text-bg-warning',
        'completed' => 'text-bg-success',
        'cancelled' => 'text-bg-secondary',
        default => 'text-bg-light text-dark',
    };
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

function task_due_state(array $task): string
{
    $status = (string) ($task['status'] ?? '');
    $dueDate = trim((string) ($task['due_date'] ?? ''));
    $today = date('Y-m-d');

    if ($status === 'completed' || $status === 'cancelled') {
        return $status;
    }

    if ($dueDate === '') {
        return 'no_due_date';
    }

    if ($dueDate < $today) {
        return 'overdue';
    }

    if ($dueDate === $today) {
        return 'due_today';
    }

    return 'upcoming';
}

start_session();
