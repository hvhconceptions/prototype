<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$gallery = read_gallery_data();
$items = $gallery['items'] ?? [];
if (!is_array($items)) {
    $items = [];
}

json_response([
    'items' => $items,
    'updated_at' => $gallery['updated_at'] ?? gmdate('c'),
]);
