<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

require_admin();

function normalize_tour_date(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value;
    }
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $m)) {
        return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[1], (int) $m[2]);
    }
    if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $value, $m)) {
        return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[1], (int) $m[2]);
    }
    if (preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $value, $m)) {
        return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
    }
    return '';
}

function normalize_tour_type(string $value): string
{
    $type = strtolower(trim($value));
    if ($type === 'block') {
        return 'block';
    }
    return 'tour';
}

function normalize_partner_name(string $value): string
{
    return trim($value);
}

function normalize_partner_link(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (preg_match('/^https?:\/\//i', $value) || preg_match('/^mailto:/i', $value)) {
        return $value;
    }
    return 'https://' . ltrim($value, '/');
}

function normalize_partners($partners): array
{
    if (!is_array($partners)) {
        return [];
    }
    $clean = [];
    foreach ($partners as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $friend = normalize_partner_name((string) ($entry['friend'] ?? ''));
        $link = normalize_partner_link((string) ($entry['link'] ?? ''));
        if ($friend === '' || $link === '') {
            continue;
        }
        $clean[] = [
            'friend' => $friend,
            'link' => $link,
        ];
    }
    return $clean;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $content = read_site_content();
    $touring = get_effective_touring_schedule();
    $partners = normalize_partners($content['touring_partners'] ?? []);
    json_response([
        'touring' => $touring,
        'partners' => $partners,
        'updated_at' => $content['updated_at'] ?? gmdate('c'),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$payload = get_request_body();
$touring = $payload['touring'] ?? [];
$partners = $payload['partners'] ?? [];
if (!is_array($touring)) {
    json_response(['error' => 'Invalid touring list'], 422);
}

$clean = [];
foreach ($touring as $entry) {
    if (!is_array($entry)) {
        continue;
    }
    $start = normalize_tour_date((string) ($entry['start'] ?? ''));
    $end = normalize_tour_date((string) ($entry['end'] ?? ''));
    $city = trim((string) ($entry['city'] ?? ''));
    $type = normalize_tour_type((string) ($entry['type'] ?? 'tour'));
    $notes = trim((string) ($entry['notes'] ?? ''));
    if (strlen($notes) > 300) {
        $notes = substr($notes, 0, 300);
    }
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
        'type' => $type,
        'notes' => $notes,
    ];
}

if (!$clean) {
    json_response(['error' => 'No valid entries'], 422);
}

usort($clean, static function (array $a, array $b): int {
    $left = $a['start'] . '|' . $a['end'] . '|' . strtolower((string) $a['city']) . '|' . strtolower((string) ($a['type'] ?? 'tour'));
    $right = $b['start'] . '|' . $b['end'] . '|' . strtolower((string) $b['city']) . '|' . strtolower((string) ($b['type'] ?? 'tour'));
    return strcmp($left, $right);
});

$content = read_site_content();
$content['touring'] = array_values($clean);
$content['touring_partners'] = normalize_partners($partners);
write_site_content($content);

json_response([
    'ok' => true,
    'touring' => $content['touring'],
    'partners' => $content['touring_partners'],
]);
