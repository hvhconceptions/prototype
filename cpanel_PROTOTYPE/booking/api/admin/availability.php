<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$payload = get_request_body();
$tourCity = trim((string) ($payload['tour_city'] ?? ''));
$tourTz = trim((string) ($payload['tour_timezone'] ?? ''));
$buffer = isset($payload['buffer_minutes']) ? (int) $payload['buffer_minutes'] : DEFAULT_BUFFER_MINUTES;
$mode = (string) ($payload['availability_mode'] ?? 'open');
$autoTemplateBlocks = !empty($payload['auto_template_blocks']);
$hiddenBookingIds = $payload['hidden_booking_ids'] ?? [];
$blocked = $payload['blocked'] ?? [];
$recurring = $payload['recurring'] ?? [];
$citySchedules = $payload['city_schedules'] ?? [];

if ($tourTz === '') {
    $tourTz = DEFAULT_TOUR_TZ;
}

if (!is_array($blocked)) {
    $blocked = [];
}
if (!is_array($recurring)) {
    $recurring = [];
}
if (!is_array($citySchedules)) {
    $citySchedules = [];
}
if (!is_array($hiddenBookingIds)) {
    $hiddenBookingIds = [];
}

$cleanHiddenBookingIds = [];
foreach ($hiddenBookingIds as $id) {
    if (!is_scalar($id)) {
        continue;
    }
    $value = trim((string) $id);
    if ($value === '') {
        continue;
    }
    $cleanHiddenBookingIds[$value] = true;
}

$cleanCitySchedules = [];
foreach ($citySchedules as $entry) {
    if (!is_array($entry)) {
        continue;
    }
    $city = trim((string) ($entry['city'] ?? ''));
    $start = trim((string) ($entry['start'] ?? ''));
    $end = trim((string) ($entry['end'] ?? ''));
    $timezone = trim((string) ($entry['timezone'] ?? $tourTz));
    $readyStart = trim((string) ($entry['ready_start'] ?? ''));
    $leaveDayEnd = trim((string) ($entry['leave_day_end'] ?? ''));
    $bufferMinutes = isset($entry['buffer_minutes']) ? (int) $entry['buffer_minutes'] : $buffer;
    $hasSleep = !empty($entry['has_sleep']);
    $sleepStart = trim((string) ($entry['sleep_start'] ?? ''));
    $sleepEnd = trim((string) ($entry['sleep_end'] ?? ''));
    $hasBreak = !empty($entry['has_break']);
    $breakStart = trim((string) ($entry['break_start'] ?? ''));
    $breakEnd = trim((string) ($entry['break_end'] ?? ''));

    if ($city === '' || $start === '' || $end === '') {
        continue;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
        continue;
    }
    if ($start > $end) {
        continue;
    }
    if ($readyStart !== '' && !preg_match('/^\d{2}:\d{2}$/', $readyStart)) {
        $readyStart = '';
    }
    if ($leaveDayEnd !== '' && !preg_match('/^\d{2}:\d{2}$/', $leaveDayEnd)) {
        $leaveDayEnd = '';
    }
    if ($sleepStart !== '' && !preg_match('/^\d{2}:\d{2}$/', $sleepStart)) {
        $sleepStart = '';
    }
    if ($sleepEnd !== '' && !preg_match('/^\d{2}:\d{2}$/', $sleepEnd)) {
        $sleepEnd = '';
    }
    if ($breakStart !== '' && !preg_match('/^\d{2}:\d{2}$/', $breakStart)) {
        $breakStart = '';
    }
    if ($breakEnd !== '' && !preg_match('/^\d{2}:\d{2}$/', $breakEnd)) {
        $breakEnd = '';
    }

    $cleanCitySchedules[] = [
        'city' => $city,
        'start' => $start,
        'end' => $end,
        'timezone' => $timezone !== '' ? $timezone : DEFAULT_TOUR_TZ,
        'ready_start' => $readyStart,
        'buffer_minutes' => max(0, min(240, $bufferMinutes)),
        'has_sleep' => $hasSleep,
        'sleep_start' => $sleepStart,
        'sleep_end' => $sleepEnd,
        'has_break' => $hasBreak,
        'break_start' => $breakStart,
        'break_end' => $breakEnd,
        'leave_day_end' => $leaveDayEnd,
    ];
}

$data = [
    'tour_city' => $tourCity !== '' ? $tourCity : DEFAULT_TOUR_CITY,
    'tour_timezone' => $tourTz,
    'buffer_minutes' => max(0, min(240, $buffer)),
    'availability_mode' => $mode,
    'auto_template_blocks' => $autoTemplateBlocks,
    'hidden_booking_ids' => array_values(array_keys($cleanHiddenBookingIds)),
    'blocked' => $blocked,
    'recurring' => $recurring,
    'city_schedules' => $cleanCitySchedules,
    'updated_at' => gmdate('c'),
];

write_json_file(DATA_DIR . '/availability.json', $data);
json_response(['ok' => true]);
