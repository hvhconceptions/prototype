<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

require_admin();

$statePath = DATA_DIR . '/notifications_state.json';

$normalizeIds = static function ($raw): array {
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    foreach ($raw as $value) {
        $id = trim((string) $value);
        if ($id === '') {
            continue;
        }
        $out[$id] = true;
    }
    return array_slice(array_keys($out), -2000);
};

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $store = read_json_file($statePath, ['read_ids' => []]);
    $readIds = $normalizeIds($store['read_ids'] ?? []);
    json_response(['read_ids' => $readIds, 'updated_at' => (string) ($store['updated_at'] ?? '')]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$payload = get_request_body();
$incoming = $normalizeIds($payload['read_ids'] ?? []);

$store = read_json_file($statePath, ['read_ids' => []]);
$current = $normalizeIds($store['read_ids'] ?? []);
$merged = [];
foreach ($current as $id) {
    $merged[$id] = true;
}
foreach ($incoming as $id) {
    $merged[$id] = true;
}
$result = array_slice(array_keys($merged), -2000);

write_json_file($statePath, [
    'read_ids' => $result,
    'updated_at' => gmdate('c'),
]);

json_response(['ok' => true, 'read_ids' => $result]);

