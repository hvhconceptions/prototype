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
$declinedStore = read_json_file(DATA_DIR . '/declined.json', ['requests' => []]);
$declinedRequests = $declinedStore['requests'] ?? [];
if (!is_array($declinedRequests)) {
    $declinedRequests = [];
}
$sourceRequests = array_merge($requests, $declinedRequests);

function request_alias_keys(array $request): array
{
    $email = strtolower(trim((string) ($request['email'] ?? '')));
    $phone = preg_replace('/\D+/', '', (string) ($request['phone'] ?? ''));
    $date = trim((string) ($request['preferred_date'] ?? ''));
    $time = trim((string) ($request['preferred_time'] ?? ''));
    $city = strtolower(trim((string) ($request['city'] ?? '')));
    $name = strtolower(trim((string) ($request['name'] ?? '')));
    $keys = [];

    $id = trim((string) ($request['id'] ?? ''));
    if ($id !== '') {
        $keys[] = 'id:' . $id;
    }
    if ($date !== '' && $time !== '') {
        if ($email !== '' && $phone !== '') {
            $keys[] = "dt-ep:{$date}|{$time}|{$email}|{$phone}";
        }
        if ($email !== '') {
            $keys[] = "dt-e:{$date}|{$time}|{$email}";
        }
        if ($phone !== '') {
            $keys[] = "dt-p:{$date}|{$time}|{$phone}";
        }
        if ($name !== '') {
            $keys[] = "dt-n:{$date}|{$time}|{$name}";
            if ($city !== '') {
                $keys[] = "dt-nc:{$date}|{$time}|{$name}|{$city}";
            }
        }
    }
    return array_values(array_unique(array_filter($keys)));
}

$kept = [];
$indexByAlias = [];

foreach ($sourceRequests as $request) {
    if (!is_array($request)) {
        continue;
    }
    $aliases = request_alias_keys($request);
    if (!$aliases) {
        continue;
    }

    $matchIndex = null;
    foreach ($aliases as $alias) {
        if (isset($indexByAlias[$alias])) {
            $matchIndex = $indexByAlias[$alias];
            break;
        }
    }

    if ($matchIndex === null) {
        $kept[] = $request;
        $newIndex = count($kept) - 1;
        foreach ($aliases as $alias) {
            $indexByAlias[$alias] = $newIndex;
        }
        continue;
    }

    $current = $kept[$matchIndex];
    $currentUpdated = (string) ($current['updated_at'] ?? ($current['created_at'] ?? ''));
    $nextUpdated = (string) ($request['updated_at'] ?? ($request['created_at'] ?? ''));
    if ($nextUpdated >= $currentUpdated) {
        $kept[$matchIndex] = $request;
        $aliases = request_alias_keys($request);
    } else {
        $aliases = array_values(array_unique(array_merge(request_alias_keys($current), $aliases)));
    }
    foreach ($aliases as $alias) {
        $indexByAlias[$alias] = $matchIndex;
    }
}

json_response(['requests' => array_values($kept)]);
