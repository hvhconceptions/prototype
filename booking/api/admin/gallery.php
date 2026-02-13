<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $gallery = read_gallery_data();
    $items = $gallery['items'] ?? [];
    if (!is_array($items)) {
        $items = [];
    }
    json_response([
        'items' => $items,
        'updated_at' => $gallery['updated_at'] ?? gmdate('c'),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$payload = get_request_body();
$items = $payload['items'] ?? [];
if (!is_array($items)) {
    json_response(['error' => 'Invalid items'], 422);
}

$clean = [];
foreach ($items as $item) {
    if (!is_array($item)) {
        continue;
    }
    $src = trim((string) ($item['src'] ?? ''));
    $alt = trim((string) ($item['alt'] ?? ''));
    if ($src === '') {
        continue;
    }
    $clean[] = [
        'src' => $src,
        'alt' => $alt,
    ];
}

if (!$clean) {
    json_response(['error' => 'No valid items'], 422);
}

write_gallery_data(array_values($clean));

json_response(['ok' => true, 'items' => $clean]);
