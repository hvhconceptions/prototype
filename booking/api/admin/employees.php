<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

require_admin();

$employeePath = DATA_DIR . '/admin_employees.json';

function admin_basic_auth_credentials(): array
{
    $user = $_SERVER['PHP_AUTH_USER'] ?? '';
    $pass = $_SERVER['PHP_AUTH_PW'] ?? '';
    if ($user !== '' || $pass !== '') {
        return [strtolower(trim((string) $user)), (string) $pass];
    }
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($header === '' && isset($_SERVER['Authorization'])) {
        $header = (string) $_SERVER['Authorization'];
    }
    if ($header !== '' && stripos($header, 'basic ') === 0) {
        $decoded = base64_decode(substr($header, 6), true);
        if ($decoded !== false) {
            $parts = explode(':', $decoded, 2);
            return [strtolower(trim((string) ($parts[0] ?? ''))), (string) ($parts[1] ?? '')];
        }
    }
    return ['', ''];
}

function expected_employer_user(): string
{
    $value = defined('ADMIN_UI_USER') && ADMIN_UI_USER !== '' ? ADMIN_UI_USER : 'admin';
    return strtolower(trim((string) $value));
}

function require_employer_user(): void
{
    [$current, $password] = admin_basic_auth_credentials();
    $expected = expected_employer_user();
    if ($current === '' || $expected === '' || !hash_equals($expected, $current)) {
        json_response(['error' => 'Only employer can manage employees'], 403);
    }
    if (defined('ADMIN_UI_PASSWORD_HASH') && ADMIN_UI_PASSWORD_HASH !== '') {
        if (!password_verify((string) $password, ADMIN_UI_PASSWORD_HASH)) {
            json_response(['error' => 'Only employer can manage employees'], 403);
        }
        return;
    }
    if (!hash_equals(ADMIN_API_KEY, (string) $password)) {
        json_response(['error' => 'Only employer can manage employees'], 403);
    }
}

function normalize_employee_username(string $value): string
{
    $username = strtolower(trim($value));
    if ($username === '') {
        return '';
    }
    if (!preg_match('/^[a-z0-9._-]{3,40}$/', $username)) {
        return '';
    }
    return $username;
}

function read_employee_store(string $path): array
{
    $store = read_json_file($path, ['employees' => []]);
    $employees = $store['employees'] ?? [];
    if (!is_array($employees)) {
        $employees = [];
    }
    $clean = [];
    foreach ($employees as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $username = normalize_employee_username((string) ($entry['username'] ?? ''));
        $hash = (string) ($entry['password_hash'] ?? '');
        if ($username === '' || $hash === '') {
            continue;
        }
        $clean[] = [
            'username' => $username,
            'password_hash' => $hash,
            'created_at' => (string) ($entry['created_at'] ?? ''),
            'updated_at' => (string) ($entry['updated_at'] ?? ''),
        ];
    }
    return $clean;
}

function public_employee_rows(array $employees): array
{
    $rows = array_map(static function (array $entry): array {
        return [
            'username' => (string) ($entry['username'] ?? ''),
            'created_at' => (string) ($entry['created_at'] ?? ''),
            'updated_at' => (string) ($entry['updated_at'] ?? ''),
        ];
    }, $employees);
    usort($rows, static function (array $a, array $b): int {
        return strcmp((string) ($a['username'] ?? ''), (string) ($b['username'] ?? ''));
    });
    return $rows;
}

require_employer_user();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $employees = read_employee_store($employeePath);
    json_response(['ok' => true, 'employees' => public_employee_rows($employees)]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$payload = get_request_body();
$action = strtolower(trim((string) ($payload['action'] ?? '')));
if ($action === '') {
    $action = 'add';
}

$employees = read_employee_store($employeePath);

if ($action === 'add') {
    $username = normalize_employee_username((string) ($payload['username'] ?? ''));
    $password = (string) ($payload['password'] ?? '');
    if ($username === '') {
        json_response(['error' => 'Invalid username'], 422);
    }
    if (hash_equals($username, expected_employer_user())) {
        json_response(['error' => 'This username is reserved'], 422);
    }
    if (strlen($password) < 8) {
        json_response(['error' => 'Password must be at least 8 characters'], 422);
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    if (!is_string($hash) || $hash === '') {
        json_response(['error' => 'Failed to hash password'], 500);
    }

    $found = false;
    foreach ($employees as &$entry) {
        if (hash_equals((string) ($entry['username'] ?? ''), $username)) {
            $entry['password_hash'] = $hash;
            $entry['updated_at'] = gmdate('c');
            if (($entry['created_at'] ?? '') === '') {
                $entry['created_at'] = (string) $entry['updated_at'];
            }
            $found = true;
            break;
        }
    }
    unset($entry);

    if (!$found) {
        $employees[] = [
            'username' => $username,
            'password_hash' => $hash,
            'created_at' => gmdate('c'),
            'updated_at' => gmdate('c'),
        ];
    }

    write_json_file($employeePath, [
        'employees' => $employees,
        'updated_at' => gmdate('c'),
    ]);

    json_response(['ok' => true, 'employees' => public_employee_rows($employees)]);
}

if ($action === 'delete') {
    $username = normalize_employee_username((string) ($payload['username'] ?? ''));
    if ($username === '') {
        json_response(['error' => 'Invalid username'], 422);
    }
    $filtered = array_values(array_filter($employees, static function (array $entry) use ($username): bool {
        return !hash_equals((string) ($entry['username'] ?? ''), $username);
    }));

    write_json_file($employeePath, [
        'employees' => $filtered,
        'updated_at' => gmdate('c'),
    ]);

    json_response(['ok' => true, 'employees' => public_employee_rows($filtered)]);
}

json_response(['error' => 'Invalid action'], 422);
