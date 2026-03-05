<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

function find_request_by_id(string $id): ?array
{
    $store = read_json_file(DATA_DIR . '/requests.json', ['requests' => []]);
    $requests = $store['requests'] ?? [];
    if (!is_array($requests)) {
        return null;
    }
    foreach ($requests as $request) {
        if (is_array($request) && ($request['id'] ?? '') === $id) {
            return $request;
        }
    }
    return null;
}

function build_calendar_event(array $request): ?array
{
    $preferredDate = (string) ($request['preferred_date'] ?? '');
    $preferredTime = (string) ($request['preferred_time'] ?? '');
    $durationHours = isset($request['duration_hours']) ? (float) $request['duration_hours'] : 0.0;
    if ($preferredDate === '' || $preferredTime === '' || $durationHours <= 0) {
        return null;
    }
    $tourTz = (string) ($request['tour_timezone'] ?? DEFAULT_TOUR_TZ);
    try {
        $tourZone = new DateTimeZone($tourTz !== '' ? $tourTz : DEFAULT_TOUR_TZ);
        $startLocal = DateTimeImmutable::createFromFormat('Y-m-d H:i', $preferredDate . ' ' . $preferredTime, $tourZone);
        if ($startLocal === false) {
            return null;
        }
        $endLocal = $startLocal->modify('+' . (int) round($durationHours * 60) . ' minutes');
        $utc = new DateTimeZone('UTC');
        $startUtc = $startLocal->setTimezone($utc);
        $endUtc = $endLocal->setTimezone($utc);
        return [
            'start' => $startUtc->format('Ymd\\THis\\Z'),
            'end' => $endUtc->format('Ymd\\THis\\Z'),
        ];
    } catch (Exception $error) {
        return null;
    }
}

$id = trim((string) ($_GET['id'] ?? ''));
if ($id === '') {
    http_response_code(400);
    echo 'Missing id';
    exit;
}

$request = find_request_by_id($id);
if ($request === null) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$event = build_calendar_event($request);
if ($event === null) {
    http_response_code(422);
    echo 'Invalid booking data';
    exit;
}

$summary = 'Heidi Van Horny Booking';
$locationParts = [];
$city = trim((string) ($request['city'] ?? ''));
if ($city !== '') {
    $locationParts[] = $city;
}
$bookingType = trim((string) ($request['booking_type'] ?? ''));
if ($bookingType !== '') {
    $locationParts[] = ucfirst($bookingType);
}
$address = trim((string) ($request['outcall_address'] ?? ''));
if ($address !== '') {
    $locationParts[] = $address;
}
$location = implode(' - ', $locationParts);

$descriptionParts = [
    'Booking confirmed with Heidi Van Horny.',
    'Reference: ' . $id,
];
$durationLabel = trim((string) ($request['duration_label'] ?? ''));
if ($durationLabel !== '') {
    $descriptionParts[] = 'Duration: ' . $durationLabel;
}
$description = implode('\\n', $descriptionParts);

$uid = $id . '@heidivanhorny.com';
$dtstamp = gmdate('Ymd\\THis\\Z');

$lines = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//Heidi Van Horny//Booking//EN',
    'CALSCALE:GREGORIAN',
    'METHOD:PUBLISH',
    'BEGIN:VEVENT',
    'UID:' . $uid,
    'DTSTAMP:' . $dtstamp,
    'DTSTART:' . $event['start'],
    'DTEND:' . $event['end'],
    'SUMMARY:' . $summary,
    'DESCRIPTION:' . $description,
];

if ($location !== '') {
    $lines[] = 'LOCATION:' . $location;
}

$lines[] = 'END:VEVENT';
$lines[] = 'END:VCALENDAR';

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="heidi-booking.ics"');
echo implode("\r\n", $lines);
