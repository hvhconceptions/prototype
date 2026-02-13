<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$payload = get_request_body();
$key = strtolower(trim((string) ($payload['key'] ?? '')));
$email = strtolower(trim((string) ($payload['email'] ?? '')));

if ($key === '' && $email === '') {
    json_response(['error' => 'Missing customer key'], 422);
}
$target = $key !== '' ? $key : $email;

$requestsPath = DATA_DIR . '/requests.json';
$declinedPath = DATA_DIR . '/declined.json';

$store = read_json_file($requestsPath, ['requests' => []]);
$requests = $store['requests'] ?? [];
if (!is_array($requests)) {
    $requests = [];
}
$beforeRequests = count($requests);
$requests = array_values(array_filter($requests, function ($request) use ($target) {
    $emailValue = strtolower(trim((string) ($request['email'] ?? '')));
    $phoneValue = strtolower(trim((string) ($request['phone'] ?? '')));
    $idValue = strtolower(trim((string) ($request['id'] ?? '')));
    return $emailValue !== $target && $phoneValue !== $target && $idValue !== $target;
}));
$store['requests'] = $requests;
write_json_file($requestsPath, $store);

$declinedStore = read_json_file($declinedPath, ['requests' => []]);
$declined = $declinedStore['requests'] ?? [];
if (!is_array($declined)) {
    $declined = [];
}
$beforeDeclined = count($declined);
$declined = array_values(array_filter($declined, function ($request) use ($target) {
    $emailValue = strtolower(trim((string) ($request['email'] ?? '')));
    $phoneValue = strtolower(trim((string) ($request['phone'] ?? '')));
    $idValue = strtolower(trim((string) ($request['id'] ?? '')));
    return $emailValue !== $target && $phoneValue !== $target && $idValue !== $target;
}));
$declinedStore['requests'] = $declined;
write_json_file($declinedPath, $declinedStore);

$removed = ($beforeRequests - count($requests)) + ($beforeDeclined - count($declined));
json_response(['ok' => true, 'removed' => $removed]);
