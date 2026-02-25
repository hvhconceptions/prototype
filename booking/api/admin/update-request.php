<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

require_admin();

function is_valid_email_address(string $email): bool
{
    $value = trim($email);
    if ($value === '' || strlen($value) > 254) {
        return false;
    }
    if (preg_match('/\s/', $value)) {
        return false;
    }
    return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
}

function is_valid_phone_international(string $phone): bool
{
    $value = trim($phone);
    if ($value === '' || strpos($value, '+') !== 0) {
        return false;
    }
    if (!preg_match('/^\+[0-9\s().-]+$/', $value)) {
        return false;
    }
    $digits = preg_replace('/\D+/', '', $value);
    if (!is_string($digits) || $digits === '') {
        return false;
    }
    $len = strlen($digits);
    if ($len < 8 || $len > 15) {
        return false;
    }
    return $digits[0] !== '0';
}

function normalize_experience(string $experience): string
{
    $normalized = strtolower(trim($experience));
    if ($normalized === 'duo_gfe') {
        return 'gfe';
    }
    if (in_array($normalized, ['gfe', 'pse', 'filming', 'social'], true)) {
        return $normalized;
    }
    return 'gfe';
}

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
    $details = 'Booking updated.';
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

function append_calendar_links_lines(array &$lines, array $calendarLinks): void
{
    if (empty($calendarLinks)) {
        return;
    }
    $lines[] = '';
    $lines[] = 'Add to calendar:';
    if (!empty($calendarLinks['google'])) {
        $lines[] = '- Google Calendar: ' . $calendarLinks['google'];
    }
    if (!empty($calendarLinks['outlook'])) {
        $lines[] = '- Samsung / Microsoft Calendar: ' . $calendarLinks['outlook'];
    }
    if (!empty($calendarLinks['ics'])) {
        $lines[] = '- iCloud / Apple Calendar (ICS): ' . $calendarLinks['ics'];
    }
}

function build_booking_blocks(array $request, string $status): array
{
    $durationHours = isset($request['duration_hours']) ? (float) $request['duration_hours'] : 0.0;
    if ($durationHours <= 0) {
        return [];
    }
    $preferredDate = (string) ($request['preferred_date'] ?? '');
    $preferredTime = (string) ($request['preferred_time'] ?? '');
    if ($preferredDate === '' || $preferredTime === '') {
        return [];
    }
    $tourTz = (string) ($request['tour_timezone'] ?? DEFAULT_TOUR_TZ);
    try {
        $tourZone = resolve_tour_timezone($tourTz);
        $tourStart = DateTimeImmutable::createFromFormat('Y-m-d H:i', $preferredDate . ' ' . $preferredTime, $tourZone);
        if ($tourStart === false) {
            return [];
        }
        $durationMinutes = (int) round($durationHours * 60);
        $tourEnd = $tourStart->modify('+' . $durationMinutes . ' minutes');
        $blocks = [];
        $cursor = $tourStart;
        $label = trim((string) ($request['name'] ?? ''));
        $label = $label !== '' ? explode(' ', $label)[0] : 'Booking';
        while ($cursor < $tourEnd) {
            $next = $cursor->modify('+30 minutes');
            $blocks[] = [
                'date' => $cursor->format('Y-m-d'),
                'start' => $cursor->format('H:i'),
                'end' => $next > $tourEnd ? $tourEnd->format('H:i') : $next->format('H:i'),
                'kind' => 'booking',
                'booking_id' => (string) ($request['id'] ?? ''),
                'booking_status' => $status,
                'booking_type' => (string) ($request['booking_type'] ?? ''),
                'label' => $label,
                'city' => trim((string) ($request['city'] ?? '')),
            ];
            $cursor = $next;
        }
        return $blocks;
    } catch (Exception $error) {
        return [];
    }
}

function append_history_entry(array &$request, string $summary, array $changes = []): void
{
    $history = $request['history'] ?? [];
    if (!is_array($history)) {
        $history = [];
    }
    $entry = [
        'at' => gmdate('c'),
        'action' => 'edited',
        'source' => 'admin_edit',
        'summary' => $summary,
    ];
    if (!empty($changes)) {
        $entry['changes'] = $changes;
    }
    $history[] = $entry;
    $request['history'] = $history;
}

function booking_update_field_label(string $field): string
{
    $labels = [
        'name' => 'Name',
        'email' => 'Email',
        'phone' => 'Phone',
        'city' => 'City',
        'booking_type' => 'Booking type',
        'outcall_address' => 'Outcall address',
        'experience' => 'Service',
        'duration_label' => 'Duration',
        'duration_hours' => 'Duration (hours)',
        'preferred_date' => 'Date',
        'preferred_time' => 'Time',
        'notes' => 'Notes',
    ];
    return $labels[$field] ?? $field;
}

function booking_update_value_label(string $value): string
{
    $clean = trim($value);
    return $clean === '' ? '(empty)' : $clean;
}

function build_booking_update_email_body(array $request, array $changes): string
{
    $lines = [];
    $lines[] = 'Your booking details were updated.';
    $lines[] = 'Reference: ' . (string) ($request['id'] ?? '');
    $lines[] = '';
    $lines[] = 'Updated fields:';
    foreach ($changes as $field => $change) {
        $before = booking_update_value_label((string) ($change['from'] ?? ''));
        $after = booking_update_value_label((string) ($change['to'] ?? ''));
        $lines[] = '- ' . booking_update_field_label((string) $field) . ': ' . $before . ' -> ' . $after;
    }
    $lines[] = '';
    $lines[] = 'Current booking details:';
    $lines[] = '- Name: ' . booking_update_value_label((string) ($request['name'] ?? ''));
    $lines[] = '- Date: ' . booking_update_value_label((string) ($request['preferred_date'] ?? ''));
    $lines[] = '- Time: ' . booking_update_value_label((string) ($request['preferred_time'] ?? ''));
    $lines[] = '- City: ' . booking_update_value_label((string) ($request['city'] ?? ''));
    $lines[] = '- Duration: ' . booking_update_value_label((string) ($request['duration_label'] ?? ''));
    $lines[] = '- Service: ' . strtoupper((string) ($request['experience'] ?? 'gfe'));
    $lines[] = '- Type: ' . booking_update_value_label((string) ($request['booking_type'] ?? 'incall'));
    $outcallAddress = trim((string) ($request['outcall_address'] ?? ''));
    if ($outcallAddress !== '') {
        $lines[] = '- Outcall address: ' . $outcallAddress;
    }
    $notes = trim((string) ($request['notes'] ?? ''));
    if ($notes !== '') {
        $lines[] = '- Notes: ' . $notes;
    }
    append_calendar_links_lines($lines, build_calendar_links($request));
    $lines[] = '';
    $lines[] = 'If anything looks wrong, reply to this email.';
    return implode("\n", $lines);
}

function build_booking_update_admin_email_body(array $request, array $changes): string
{
    $lines = [];
    $lines[] = 'Admin notice: booking was edited.';
    $lines[] = 'Reference: ' . (string) ($request['id'] ?? '');
    $lines[] = 'Status: ' . (string) ($request['status'] ?? 'pending');
    $lines[] = 'Payment: ' . (string) ($request['payment_status'] ?? 'unpaid');
    $lines[] = '';
    $lines[] = 'Changed fields:';
    foreach ($changes as $field => $change) {
        $before = booking_update_value_label((string) ($change['from'] ?? ''));
        $after = booking_update_value_label((string) ($change['to'] ?? ''));
        $lines[] = '- ' . booking_update_field_label((string) $field) . ': ' . $before . ' -> ' . $after;
    }
    $lines[] = '';
    $lines[] = 'Current details:';
    $lines[] = '- Name: ' . booking_update_value_label((string) ($request['name'] ?? ''));
    $lines[] = '- Email: ' . booking_update_value_label((string) ($request['email'] ?? ''));
    $lines[] = '- Phone: ' . booking_update_value_label((string) ($request['phone'] ?? ''));
    $lines[] = '- Date: ' . booking_update_value_label((string) ($request['preferred_date'] ?? ''));
    $lines[] = '- Time: ' . booking_update_value_label((string) ($request['preferred_time'] ?? ''));
    $lines[] = '- City: ' . booking_update_value_label((string) ($request['city'] ?? ''));
    $lines[] = '- Duration: ' . booking_update_value_label((string) ($request['duration_label'] ?? ''));
    $lines[] = '- Service: ' . strtoupper((string) ($request['experience'] ?? 'gfe'));
    $lines[] = '- Type: ' . booking_update_value_label((string) ($request['booking_type'] ?? 'incall'));
    append_calendar_links_lines($lines, build_calendar_links($request));
    return implode("\n", $lines);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$payload = get_request_body();
$id = trim((string) ($payload['id'] ?? ''));
if ($id === '') {
    json_response(['error' => 'Missing id'], 422);
}

$store = read_json_file(DATA_DIR . '/requests.json', ['requests' => []]);
$requests = $store['requests'] ?? [];
if (!is_array($requests)) {
    $requests = [];
}

$targetIndex = null;
foreach ($requests as $index => $request) {
    if (!is_array($request)) {
        continue;
    }
    if ((string) ($request['id'] ?? '') === $id) {
        $targetIndex = $index;
        break;
    }
}

if ($targetIndex === null) {
    json_response(['error' => 'Request not found'], 404);
}

$request = $requests[$targetIndex];
$original = $request;
$allowedBookingTypes = ['incall', 'outcall'];

$request['name'] = trim((string) ($payload['name'] ?? ($request['name'] ?? '')));
$request['email'] = trim((string) ($payload['email'] ?? ($request['email'] ?? '')));
$request['phone'] = trim((string) ($payload['phone'] ?? ($request['phone'] ?? '')));
$request['city'] = trim((string) ($payload['city'] ?? ($request['city'] ?? '')));
$request['booking_type'] = strtolower(trim((string) ($payload['booking_type'] ?? ($request['booking_type'] ?? 'incall'))));
$request['outcall_address'] = trim((string) ($payload['outcall_address'] ?? ($request['outcall_address'] ?? '')));
$request['experience'] = normalize_experience((string) ($payload['experience'] ?? ($request['experience'] ?? 'gfe')));
$request['duration_label'] = trim((string) ($payload['duration_label'] ?? ($request['duration_label'] ?? '')));
$request['duration_hours'] = trim((string) ($payload['duration_hours'] ?? ($request['duration_hours'] ?? '0')));
$request['preferred_date'] = trim((string) ($payload['preferred_date'] ?? ($request['preferred_date'] ?? '')));
$request['preferred_time'] = trim((string) ($payload['preferred_time'] ?? ($request['preferred_time'] ?? '')));
$request['notes'] = trim((string) ($payload['notes'] ?? ($request['notes'] ?? '')));

$errors = [];
if ($request['name'] === '') {
    $errors['name'] = 'Required';
}
if (!is_valid_email_address((string) $request['email'])) {
    $errors['email'] = 'Invalid email';
}
if (!is_valid_phone_international((string) $request['phone'])) {
    $errors['phone'] = 'Use international format: +countrycode number';
}
if ($request['city'] === '') {
    $errors['city'] = 'Required';
}
if (!in_array((string) $request['booking_type'], $allowedBookingTypes, true)) {
    $errors['booking_type'] = 'Invalid booking type';
}
if ((string) $request['booking_type'] === 'outcall' && $request['outcall_address'] === '') {
    $errors['outcall_address'] = 'Required for outcall';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request['preferred_date'])) {
    $errors['preferred_date'] = 'Invalid date';
}
if (!preg_match('/^\d{2}:\d{2}$/', (string) $request['preferred_time'])) {
    $errors['preferred_time'] = 'Invalid time';
}
$durationHours = is_numeric($request['duration_hours']) ? (float) $request['duration_hours'] : 0.0;
if ($durationHours <= 0 || $durationHours > 24) {
    $errors['duration_hours'] = 'Invalid duration';
}
if ($request['duration_label'] === '') {
    $errors['duration_label'] = 'Required';
}

if (!empty($errors)) {
    json_response(['error' => 'Validation failed', 'fields' => $errors], 422);
}

if ((string) $request['booking_type'] !== 'outcall') {
    $request['outcall_address'] = '';
}

$request['duration_hours'] = rtrim(rtrim(number_format($durationHours, 2, '.', ''), '0'), '.');
$trackedFields = [
    'name',
    'email',
    'phone',
    'city',
    'booking_type',
    'outcall_address',
    'experience',
    'duration_label',
    'duration_hours',
    'preferred_date',
    'preferred_time',
    'notes',
];
$changes = [];
foreach ($trackedFields as $field) {
    $before = trim((string) ($original[$field] ?? ''));
    $after = trim((string) ($request[$field] ?? ''));
    if ($before !== $after) {
        $changes[$field] = ['from' => $before, 'to' => $after];
    }
}
if (!empty($changes)) {
    append_history_entry($request, 'Appointment details edited', $changes);
}
$request['updated_at'] = gmdate('c');
$editedEmailSentAt = '';
$editedAdminEmailSentAt = '';
if (!empty($changes)) {
    $requestEmail = trim((string) ($request['email'] ?? ''));
    if ($requestEmail !== '') {
        $emailBody = build_booking_update_email_body($request, $changes);
        if (send_payment_email($requestEmail, $emailBody, 'Booking updated')) {
            $editedEmailSentAt = gmdate('c');
            $request['edited_email_sent_at'] = $editedEmailSentAt;
        }
    }
    $adminEmailBody = build_booking_update_admin_email_body($request, $changes);
    if (send_admin_email($adminEmailBody, 'Booking updated (admin)')) {
        $editedAdminEmailSentAt = gmdate('c');
        $request['edited_admin_email_sent_at'] = $editedAdminEmailSentAt;
    }
}

$requests[$targetIndex] = $request;
$store['requests'] = $requests;
write_json_file(DATA_DIR . '/requests.json', $store);

$availability = read_json_file(DATA_DIR . '/availability.json', [
    'tour_city' => DEFAULT_TOUR_CITY,
    'tour_timezone' => DEFAULT_TOUR_TZ,
    'buffer_minutes' => DEFAULT_BUFFER_MINUTES,
    'availability_mode' => 'open',
    'blocked' => [],
    'recurring' => [],
    'city_schedules' => [],
    'updated_at' => gmdate('c'),
]);
$blocked = $availability['blocked'] ?? [];
if (!is_array($blocked)) {
    $blocked = [];
}
$blocked = array_values(array_filter($blocked, function ($entry) use ($id): bool {
    if (!is_array($entry)) {
        return true;
    }
    $entryBookingId = trim((string) ($entry['booking_id'] ?? ''));
    return $entryBookingId === '' || $entryBookingId !== $id;
}));
$isAccepted = (string) ($request['status'] ?? '') === 'accepted';
$isPaid = (string) ($request['payment_status'] ?? '') === 'paid';
if ($isAccepted || $isPaid) {
    $bookingStatus = $isPaid ? 'paid' : 'accepted';
    $bookingBlocks = build_booking_blocks($request, $bookingStatus);
    $blocked = array_merge($blocked, $bookingBlocks);
}
$availability['blocked'] = $blocked;
$availability['updated_at'] = gmdate('c');
write_json_file(DATA_DIR . '/availability.json', $availability);

json_response([
    'ok' => true,
    'request' => $request,
    'edited_email_sent' => $editedEmailSentAt !== '',
    'edited_admin_email_sent' => $editedAdminEmailSentAt !== '',
]);
