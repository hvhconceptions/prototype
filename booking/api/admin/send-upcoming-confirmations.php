<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

require_admin();

function resolve_tour_timezone(string $value): DateTimeZone
{
    $tz = trim($value);
    if ($tz === '') {
        $tz = DEFAULT_TOUR_TZ;
    }
    try {
        return new DateTimeZone($tz);
    } catch (Exception $error) {
        return new DateTimeZone(DEFAULT_TOUR_TZ);
    }
}

function get_base_url(): string
{
    $scheme = 'https';
    $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    if ($forwardedProto !== '') {
        $scheme = explode(',', $forwardedProto)[0];
    } elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $scheme = 'https';
    } else {
        $scheme = 'http';
    }
    $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '') {
        return '';
    }
    return $scheme . '://' . $host;
}

function build_calendar_times(array $request): ?array
{
    $preferredDate = (string) ($request['preferred_date'] ?? '');
    $preferredTime = (string) ($request['preferred_time'] ?? '');
    if ($preferredDate === '' || $preferredTime === '') {
        return null;
    }
    $tourTz = (string) ($request['tour_timezone'] ?? DEFAULT_TOUR_TZ);
    $durationHours = isset($request['duration_hours']) ? (float) $request['duration_hours'] : 0.0;
    if ($durationHours <= 0) {
        return null;
    }
    try {
        $tourZone = resolve_tour_timezone($tourTz);
        $startLocal = DateTimeImmutable::createFromFormat('Y-m-d H:i', $preferredDate . ' ' . $preferredTime, $tourZone);
        if ($startLocal === false) {
            return null;
        }
        $endLocal = $startLocal->modify('+' . (int) round($durationHours * 60) . ' minutes');
        $utc = new DateTimeZone('UTC');
        $startUtc = $startLocal->setTimezone($utc);
        $endUtc = $endLocal->setTimezone($utc);
        return [
            'startIso' => $startUtc->format('Y-m-d\\TH:i:s\\Z'),
            'endIso' => $endUtc->format('Y-m-d\\TH:i:s\\Z'),
            'startCompact' => $startUtc->format('Ymd\\THis\\Z'),
            'endCompact' => $endUtc->format('Ymd\\THis\\Z'),
        ];
    } catch (Exception $error) {
        return null;
    }
}

function build_calendar_links(array $request): array
{
    $times = build_calendar_times($request);
    if ($times === null) {
        return [];
    }
    $title = 'Heidi Van Horny Booking';
    $id = (string) ($request['id'] ?? '');
    $city = trim((string) ($request['city'] ?? ''));
    $bookingType = trim((string) ($request['booking_type'] ?? ''));
    $address = trim((string) ($request['outcall_address'] ?? ''));
    $locationParts = [];
    if ($city !== '') {
        $locationParts[] = $city;
    }
    if ($bookingType !== '') {
        $locationParts[] = ucfirst($bookingType);
    }
    if ($address !== '') {
        $locationParts[] = $address;
    }
    $location = implode(' - ', $locationParts);
    $details = 'Booking reminder.';
    if ($id !== '') {
        $details .= " Reference: {$id}.";
    }

    $google = 'https://www.google.com/calendar/render?action=TEMPLATE'
        . '&text=' . rawurlencode($title)
        . '&dates=' . rawurlencode($times['startCompact'] . '/' . $times['endCompact'])
        . '&details=' . rawurlencode($details);
    if ($location !== '') {
        $google .= '&location=' . rawurlencode($location);
    }

    $outlook = 'https://outlook.live.com/calendar/0/deeplink/compose?path=/calendar/action/compose'
        . '&rru=addevent'
        . '&subject=' . rawurlencode($title)
        . '&startdt=' . rawurlencode($times['startIso'])
        . '&enddt=' . rawurlencode($times['endIso'])
        . '&body=' . rawurlencode($details);
    if ($location !== '') {
        $outlook .= '&location=' . rawurlencode($location);
    }

    $base = get_base_url();
    $scriptPath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $basePath = dirname(dirname($scriptPath));
    if ($basePath === '.') {
        $basePath = '';
    }
    $ics = $base !== '' ? $base . $basePath . '/api/calendar.php?id=' . rawurlencode($id) : '';

    return [
        'google' => $google,
        'outlook' => $outlook,
        'ics' => $ics,
    ];
}

function append_calendar_links_lines(string &$body, array $calendarLinks): void
{
    if (empty($calendarLinks)) {
        return;
    }
    $body .= "Add to calendar:\n";
    if (!empty($calendarLinks['google'])) {
        $body .= "- Google Calendar: " . $calendarLinks['google'] . "\n";
    }
    if (!empty($calendarLinks['outlook'])) {
        $body .= "- Samsung / Microsoft Calendar: " . $calendarLinks['outlook'] . "\n";
    }
    if (!empty($calendarLinks['ics'])) {
        $body .= "- iCloud / Apple Calendar (ICS): " . $calendarLinks['ics'] . "\n";
    }
    $body .= "\n";
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$payload = get_request_body();
$force = !empty($payload['force']);

$store = read_json_file(DATA_DIR . '/requests.json', ['requests' => []]);
$requests = $store['requests'] ?? [];
if (!is_array($requests)) {
    $requests = [];
}

$scanned = 0;
$eligible = 0;
$sent = 0;
$skippedAlreadySent = 0;
$skippedNoEmail = 0;
$failed = 0;
$sentIds = [];
$failedIds = [];

foreach ($requests as &$request) {
    if (!is_array($request)) {
        continue;
    }
    $scanned++;

    $status = strtolower(trim((string) ($request['status'] ?? 'pending')));
    $paymentStatus = strtolower(trim((string) ($request['payment_status'] ?? '')));
    if ($status === 'paid') {
        $status = 'accepted';
        $paymentStatus = 'paid';
    }

    $bucket = '';
    if ($paymentStatus === 'paid') {
        $bucket = 'paid';
    } elseif ($status === 'accepted') {
        $bucket = 'accepted';
    } elseif ($status === 'maybe') {
        $bucket = 'maybe';
    } else {
        continue;
    }

    $preferredDate = trim((string) ($request['preferred_date'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $preferredDate)) {
        continue;
    }
    $tourTz = (string) ($request['tour_timezone'] ?? DEFAULT_TOUR_TZ);
    $todayLocal = (new DateTimeImmutable('now', resolve_tour_timezone($tourTz)))->format('Y-m-d');
    if ($preferredDate < $todayLocal) {
        continue;
    }

    $eligible++;
    $email = trim((string) ($request['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $skippedNoEmail++;
        continue;
    }

    $sentAtField = 'upcoming_' . $bucket . '_email_sent_at';
    if (!$force && trim((string) ($request[$sentAtField] ?? '')) !== '') {
        $skippedAlreadySent++;
        continue;
    }

    $name = trim((string) ($request['name'] ?? ''));
    $duration = trim((string) ($request['duration_label'] ?? ''));
    $maybeReason = trim((string) ($request['maybe_reason'] ?? ''));

    $subject = 'Upcoming booking confirmation';
    $body = "Hi " . $name . ",\n\n";
    if ($bucket === 'paid') {
        $subject = 'Upcoming booking confirmed (paid)';
        $body .= "Your upcoming booking is confirmed and marked as paid.\n\n";
    } elseif ($bucket === 'accepted') {
        $subject = 'Upcoming booking confirmed';
        $body .= "Your upcoming booking is confirmed.\n\n";
    } else {
        $subject = 'Upcoming booking update';
        $body .= "Your upcoming booking is currently marked as maybe.\n\n";
    }

    $body .= "Date/time: " . $preferredDate . " " . trim((string) ($request['preferred_time'] ?? '')) . "\n";
    if ($duration !== '') {
        $body .= "Duration: " . $duration . "\n";
    }
    if ($bucket === 'maybe' && $maybeReason !== '') {
        $body .= "Note: " . $maybeReason . "\n";
    }
    $body .= "\n";

    $calendarLinks = build_calendar_links($request);
    append_calendar_links_lines($body, $calendarLinks);
    $body .= "If anything changed, reply to this email.\n";

    if (send_payment_email($email, $body, $subject)) {
        $request[$sentAtField] = gmdate('c');
        $request['upcoming_last_email_sent_at'] = gmdate('c');
        $request['updated_at'] = gmdate('c');
        $sent++;
        $sentIds[] = (string) ($request['id'] ?? '');
    } else {
        $failed++;
        $failedIds[] = (string) ($request['id'] ?? '');
    }
}
unset($request);

$store['requests'] = $requests;
write_json_file(DATA_DIR . '/requests.json', $store);

json_response([
    'ok' => true,
    'scanned' => $scanned,
    'eligible' => $eligible,
    'sent' => $sent,
    'skipped_already_sent' => $skippedAlreadySent,
    'skipped_no_email' => $skippedNoEmail,
    'failed' => $failed,
    'sent_ids' => $sentIds,
    'failed_ids' => $failedIds,
]);
