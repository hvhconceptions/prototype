<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$content = read_site_content();
$touring = get_effective_touring_schedule();

json_response([
    'touring' => $touring,
    'touring_partners' => is_array($content['touring_partners'] ?? null) ? array_values($content['touring_partners']) : [],
    'rates' => is_array($content['rates'] ?? null) ? $content['rates'] : [],
    'updated_at' => $content['updated_at'] ?? gmdate('c'),
]);
