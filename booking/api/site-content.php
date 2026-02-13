<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$content = read_site_content();
$touring = $content['touring'] ?? [];
if (!is_array($touring)) {
    $touring = [];
}

json_response([
    'touring' => $touring,
    'updated_at' => $content['updated_at'] ?? gmdate('c'),
]);
