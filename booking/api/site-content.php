<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$content = read_site_content();
$touring = get_effective_touring_schedule();

json_response([
    'touring' => $touring,
    'updated_at' => $content['updated_at'] ?? gmdate('c'),
]);
