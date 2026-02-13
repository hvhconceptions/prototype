<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$payload = get_request_body();
$honeypot = trim((string) ($payload['newsletter_signal'] ?? ''));
if ($honeypot !== '') {
    json_response(['error' => 'Spam detected'], 422);
}

$antispam = strtolower(trim((string) ($payload['nl_antispam'] ?? '')));
if ($antispam !== 'matrix') {
    json_response(['error' => 'Anti-spam failed'], 422);
}

$email = trim((string) ($payload['nl_email'] ?? ''));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['error' => 'Email required'], 422);
}

$name = trim((string) ($payload['nl_name'] ?? ''));
$phone = trim((string) ($payload['nl_phone'] ?? ''));
$cities = $payload['nl_city'] ?? [];
if (!is_array($cities)) {
    $cities = [$cities];
}
$cities = array_values(array_filter(array_map('trim', array_map('strval', $cities))));

$entry = [
    'id' => 'nl_' . gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)),
    'created_at' => gmdate('c'),
    'name' => $name,
    'email' => $email,
    'phone' => $phone,
    'cities' => $cities,
];

$path = DATA_DIR . '/newsletter.json';
$store = read_json_file($path, ['subscribers' => []]);
$subscribers = $store['subscribers'] ?? [];
if (!is_array($subscribers)) {
    $subscribers = [];
}
$subscribers[] = $entry;
$store['subscribers'] = $subscribers;
write_json_file($path, $store);

$adminBody = "New newsletter signup\n\n";
$adminBody .= "Name: " . $name . "\n";
$adminBody .= "Email: " . $email . "\n";
if ($phone !== '') {
    $adminBody .= "Phone: " . $phone . "\n";
}
if (!empty($cities)) {
    $adminBody .= "Cities: " . implode(', ', $cities) . "\n";
}
send_admin_email($adminBody, 'Newsletter signup');

$userBody = "Thanks for subscribing! You're now on my radar for tour updates.\n";
send_payment_email($email, $userBody, 'You are subscribed');

json_response(['ok' => true]);
