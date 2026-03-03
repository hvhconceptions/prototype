<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['error' => 'Method not allowed'], 405);
}

$ip = get_client_ip();
$blocked = is_blacklisted('', '', $ip);

json_response([
    'ok' => true,
    'blocked' => $blocked,
    'ip' => $ip,
]);

