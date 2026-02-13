<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

require_admin();

$payload = get_request_body();
$action = strtolower((string) ($payload['action'] ?? $_GET['action'] ?? 'hide'));
$key = trim((string) ($payload['key'] ?? $_GET['key'] ?? ''));

if ($key === '') {
    json_response(['error' => 'Missing customer key'], 400);
}

$store = read_json_file(DATA_DIR . '/customers_hidden.json', ['hidden' => []]);
$hidden = $store['hidden'] ?? [];
if (!is_array($hidden)) {
    $hidden = [];
}
$hidden = array_values(array_unique(array_map('strval', $hidden)));

if ($action === 'unhide') {
    $hidden = array_values(array_filter($hidden, fn(string $item): bool => $item !== $key));
} else {
    if (!in_array($key, $hidden, true)) {
        $hidden[] = $key;
    }
}

$store['hidden'] = $hidden;
write_json_file(DATA_DIR . '/customers_hidden.json', $store);

json_response(['ok' => true, 'hidden_count' => count($hidden)]);
