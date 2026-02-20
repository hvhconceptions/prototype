<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$gallery = read_gallery_data();

json_response([
    'items' => $gallery['items'] ?? [],
    'display_mode' => $gallery['display_mode'] ?? 'next',
    'carousel_seconds' => (int) ($gallery['carousel_seconds'] ?? 5),
    'updated_at' => $gallery['updated_at'] ?? gmdate('c'),
]);
