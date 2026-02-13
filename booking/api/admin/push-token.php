<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$payload = get_request_body();
$token = trim((string) ($payload['token'] ?? ''));
$platform = trim((string) ($payload['platform'] ?? ''));

if ($token === '' || strlen($token) < 10 || strlen($token) > 500) {
    json_response(['error' => 'Invalid token'], 422);
}

upsert_push_token($token, $platform);

json_response(['ok' => true]);
