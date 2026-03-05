<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$payload = get_request_body();
$ip = get_client_ip();
$userAgent = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
$referer = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
$origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
$cut = static function (string $value, int $max): string {
    return strlen($value) > $max ? substr($value, 0, $max) : $value;
};

$entry = [
    'id' => 'ins_' . gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)),
    'created_at' => gmdate('c'),
    'ip' => $ip,
    'type' => trim((string) ($payload['type'] ?? 'insult')),
    'text' => $cut(trim((string) ($payload['text'] ?? '')), 280),
    'context' => $cut(trim((string) ($payload['context'] ?? '')), 80),
    'page' => $cut(trim((string) ($payload['page'] ?? '')), 500),
    'path' => $cut(trim((string) ($payload['path'] ?? '')), 200),
    'origin' => $origin,
    'referer' => $referer,
    'user_agent' => $cut($userAgent, 500),
];

$path = DATA_DIR . '/insult_events.json';
$store = read_json_file($path, ['events' => []]);
$events = $store['events'] ?? [];
if (!is_array($events)) {
    $events = [];
}
$events[] = $entry;

// Keep last 5000 events to avoid unbounded growth.
if (count($events) > 5000) {
    $events = array_slice($events, -5000);
}

$store['events'] = $events;
$store['updated_at'] = gmdate('c');
write_json_file($path, $store);

// Log-only mode: do not auto-blacklist from client-side profanity checks.

$adminBody = "Insult detection event\n\n";
$adminBody .= "IP: " . $entry['ip'] . "\n";
$adminBody .= "Time (UTC): " . $entry['created_at'] . "\n";
$adminBody .= "Context: " . $entry['context'] . "\n";
$adminBody .= "Text: " . ($entry['text'] !== '' ? $entry['text'] : '(empty)') . "\n";
$adminBody .= "Page: " . $entry['page'] . "\n";
$adminBody .= "Path: " . $entry['path'] . "\n";
$adminBody .= "Referer: " . $entry['referer'] . "\n";
$adminBody .= "Origin: " . $entry['origin'] . "\n";
$adminBody .= "User-Agent: " . $entry['user_agent'] . "\n";
if (function_exists('send_admin_email')) {
    send_admin_email($adminBody, 'Insult detected - IP ' . $entry['ip']);
}

json_response([
    'ok' => true,
    'id' => $entry['id'],
    'blacklisted' => false,
]);
