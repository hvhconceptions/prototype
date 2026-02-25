<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$default = [
    'tour_city' => DEFAULT_TOUR_CITY,
    'tour_timezone' => DEFAULT_TOUR_TZ,
    'buffer_minutes' => DEFAULT_BUFFER_MINUTES,
    'availability_mode' => 'open',
    'auto_template_blocks' => false,
    'hidden_booking_ids' => [],
    'blocked' => [],
    'recurring' => [],
    'city_schedules' => [],
    'updated_at' => gmdate('c'),
];

$data = read_json_file(DATA_DIR . '/availability.json', $default);
foreach ($default as $key => $value) {
    if (!array_key_exists($key, $data)) {
        $data[$key] = $value;
    }
}

json_response($data);
