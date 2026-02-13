<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$payload = $_SERVER['REQUEST_METHOD'] === 'POST' ? get_request_body() : [];
$email = trim((string) ($payload['email'] ?? ''));
$phone = trim((string) ($payload['phone'] ?? ''));
$ip = get_client_ip();

$blocked = is_blacklisted($email, $phone, $ip);

json_response([
    'blocked' => $blocked,
]);
