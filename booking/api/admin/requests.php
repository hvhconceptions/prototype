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

function request_fallback_key(array $request): string
{
    return implode('|', [
        strtolower(trim((string) ($request['email'] ?? ''))),
        preg_replace('/\D+/', '', (string) ($request['phone'] ?? '')),
        trim((string) ($request['preferred_date'] ?? '')),
        trim((string) ($request['preferred_time'] ?? '')),
        strtolower(trim((string) ($request['city'] ?? ''))),
        strtolower(trim((string) ($request['name'] ?? ''))),
    ]);
}

$kept = [];
$indexById = [];
$indexByFallback = [];

foreach ($requests as $request) {
    if (!is_array($request)) {
        continue;
    }
    $id = trim((string) ($request['id'] ?? ''));
    $fallback = request_fallback_key($request);

    $matchIndex = null;
    if ($id !== '' && isset($indexById[$id])) {
        $matchIndex = $indexById[$id];
    } elseif (isset($indexByFallback[$fallback])) {
        $matchIndex = $indexByFallback[$fallback];
    }

    if ($matchIndex === null) {
        $kept[] = $request;
        $newIndex = count($kept) - 1;
        if ($id !== '') {
            $indexById[$id] = $newIndex;
        }
        $indexByFallback[$fallback] = $newIndex;
        continue;
    }

    $current = $kept[$matchIndex];
    $currentUpdated = (string) ($current['updated_at'] ?? ($current['created_at'] ?? ''));
    $nextUpdated = (string) ($request['updated_at'] ?? ($request['created_at'] ?? ''));
    if ($nextUpdated >= $currentUpdated) {
        $kept[$matchIndex] = $request;
        $id = trim((string) ($request['id'] ?? ''));
        $fallback = request_fallback_key($request);
        if ($id !== '') {
            $indexById[$id] = $matchIndex;
        }
        $indexByFallback[$fallback] = $matchIndex;
    } else {
        $currentId = trim((string) ($current['id'] ?? ''));
        $currentFallback = request_fallback_key($current);
        if ($currentId !== '') {
            $indexById[$currentId] = $matchIndex;
        }
        $indexByFallback[$currentFallback] = $matchIndex;
        if ($id !== '') {
            $indexById[$id] = $matchIndex;
        }
        $indexByFallback[$fallback] = $matchIndex;
    }
}

json_response(['requests' => array_values($kept)]);
