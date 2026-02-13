<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $content = read_site_content();
    $touring = $content['touring'] ?? [];
    if (!is_array($touring)) {
        $touring = [];
    }
    json_response([
        'touring' => $touring,
        'updated_at' => $content['updated_at'] ?? gmdate('c'),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$payload = get_request_body();
$touring = $payload['touring'] ?? [];
if (!is_array($touring)) {
    json_response(['error' => 'Invalid touring list'], 422);
}

$clean = [];
foreach ($touring as $entry) {
    if (!is_array($entry)) {
        continue;
    }
    $start = trim((string) ($entry['start'] ?? ''));
    $end = trim((string) ($entry['end'] ?? ''));
    $city = trim((string) ($entry['city'] ?? ''));
    if ($start === '' || $end === '' || $city === '') {
        continue;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
        continue;
    }
    if ($start > $end) {
        continue;
    }
    $clean[] = [
        'start' => $start,
        'end' => $end,
        'city' => $city,
    ];
}

if (!$clean) {
    json_response(['error' => 'No valid entries'], 422);
}

$content = read_site_content();
$content['touring'] = array_values($clean);
write_site_content($content);

json_response(['ok' => true, 'touring' => $content['touring']]);
