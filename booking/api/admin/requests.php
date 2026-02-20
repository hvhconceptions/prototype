<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['error' => 'Method not allowed'], 405);
}

$store = read_json_file(DATA_DIR . '/requests.json', ['requests' => []]);
$requests = $store['requests'] ?? [];
if (!is_array($requests)) {
    $requests = [];
}

$dedupedById = [];
$dedupedFallback = [];
foreach ($requests as $request) {
    if (!is_array($request)) {
        continue;
    }
    $id = trim((string) ($request['id'] ?? ''));
    $updated = (string) ($request['updated_at'] ?? ($request['created_at'] ?? ''));
    if ($id !== '') {
        if (!isset($dedupedById[$id])) {
            $dedupedById[$id] = $request;
            continue;
        }
        $currentUpdated = (string) ($dedupedById[$id]['updated_at'] ?? ($dedupedById[$id]['created_at'] ?? ''));
        if ($updated >= $currentUpdated) {
            $dedupedById[$id] = $request;
        }
        continue;
    }

    $composite = implode('|', [
        strtolower(trim((string) ($request['email'] ?? ''))),
        preg_replace('/\D+/', '', (string) ($request['phone'] ?? '')),
        trim((string) ($request['preferred_date'] ?? '')),
        trim((string) ($request['preferred_time'] ?? '')),
        strtolower(trim((string) ($request['city'] ?? ''))),
    ]);
    if (!isset($dedupedFallback[$composite])) {
        $dedupedFallback[$composite] = $request;
        continue;
    }
    $currentUpdated = (string) ($dedupedFallback[$composite]['updated_at'] ?? ($dedupedFallback[$composite]['created_at'] ?? ''));
    if ($updated >= $currentUpdated) {
        $dedupedFallback[$composite] = $request;
    }
}

$merged = array_values(array_merge(array_values($dedupedById), array_values($dedupedFallback)));
json_response(['requests' => $merged]);
