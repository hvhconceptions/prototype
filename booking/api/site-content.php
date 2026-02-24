<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$content = read_site_content();
$touring = get_effective_touring_schedule();

json_response([
    'touring' => $touring,
    'touring_partners' => is_array($content['touring_partners'] ?? null) ? array_values($content['touring_partners']) : [],
    'updated_at' => $content['updated_at'] ?? gmdate('c'),
]);
