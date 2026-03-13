<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

require_admin();

function append_history_entry(array &$request, string $action, string $summary): void
{
    $history = $request['history'] ?? [];
    if (!is_array($history)) {
        $history = [];
    }
    $last = end($history);
    if (is_array($last)) {
        $lastAction = (string) ($last['action'] ?? '');
        $lastSource = (string) ($last['source'] ?? '');
        $lastSummary = (string) ($last['summary'] ?? '');
        if ($lastAction === $action && $lastSource === 'admin_status' && $lastSummary === $summary) {
            return;
        }
    }
    $history[] = [
        'at' => gmdate('c'),
        'action' => $action,
        'source' => 'admin_status',
        'summary' => $summary,
    ];
    $request['history'] = $history;
}

function should_skip_duplicate_status_update(array $request, string $status, string $reason, string $paymentLink): bool
{
    $existingStatus = strtolower(trim((string) ($request['status'] ?? 'pending')));
    $existingPayment = strtolower(trim((string) ($request['payment_status'] ?? '')));
    if ($existingStatus === 'paid') {
        $existingStatus = 'accepted';
        $existingPayment = 'paid';
    }

    if ($status === 'declined') {
        return $existingStatus === 'declined'
            && trim((string) ($request['decline_reason'] ?? '')) === $reason;
    }
    if ($status === 'cancelled') {
        return $existingStatus === 'cancelled'
            && trim((string) ($request['cancel_reason'] ?? '')) === $reason;
    }
    if ($status === 'blacklisted') {
        return $existingStatus === 'blacklisted'
            && trim((string) ($request['blacklist_reason'] ?? '')) === $reason;
    }
    if ($status === 'maybe') {
        return $existingStatus === 'maybe'
            && $existingPayment === ''
            && trim((string) ($request['maybe_reason'] ?? '')) === $reason;
    }
    if ($status === 'pending') {
        return $existingStatus === 'pending' && $existingPayment === '';
    }
    if ($status === 'paid') {
        return $existingStatus === 'accepted' && $existingPayment === 'paid';
    }
    if ($status === 'accepted') {
        if ($existingStatus !== 'accepted') {
            return false;
        }
        if ($paymentLink !== '' && trim((string) ($request['payment_link'] ?? '')) !== $paymentLink) {
            return false;
        }
        return true;
    }
    return $existingStatus === $status;
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
            'startUtc' => $startUtc,
            'endUtc' => $endUtc,
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
    $details = 'Booking confirmed.';
    if ($id !== '') {
        $details .= " Reference: {$id}.";
    }
    $details .= ' Please arrive on time.';

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

function generate_cancel_token(): string
{
    try {
        return bin2hex(random_bytes(16));
    } catch (Throwable $error) {
        return sha1('cancel_' . uniqid('', true));
    }
}

function ensure_cancel_token(array &$request): string
{
    $token = trim((string) ($request['cancel_token'] ?? ''));
    if ($token !== '') {
        return $token;
    }
    $token = generate_cancel_token();
    $request['cancel_token'] = $token;
    return $token;
}

function build_cancel_url(array &$request): string
{
    $requestId = trim((string) ($request['id'] ?? ''));
    if ($requestId === '') {
        return '';
    }
    $token = ensure_cancel_token($request);
    if ($token === '') {
        return '';
    }
    $base = rtrim((string) SITE_PRIMARY_URL, '/');
    if ($base === '') {
        return '';
    }
    return $base . '/booking/api/cancel.php?id=' . rawurlencode($requestId) . '&token=' . rawurlencode($token);
}

function append_customer_cancel_notice(string &$body, string $cancelUrl): void
{
    if ($cancelUrl !== '') {
        $body .= "Cancel booking: " . $cancelUrl . "\n";
    }
    $body .= "Do not reply to this email. Replies are not monitored for cancellations.\n";
}

function notify_customer_email_failure(array $request, string $statusLabel): void
{
    if (!function_exists('send_admin_email')) {
        return;
    }
    $targetEmail = trim((string) ($request['email'] ?? ''));
    $notice = "Customer status email failed\n\n";
    $notice .= "Status: " . $statusLabel . "\n";
    $notice .= "Request id: " . (string) ($request['id'] ?? '') . "\n";
    $notice .= "Name: " . (string) ($request['name'] ?? '') . "\n";
    $notice .= "Email: " . $targetEmail . "\n";
    $notice .= "Preferred: " . (string) ($request['preferred_date'] ?? '') . " " . (string) ($request['preferred_time'] ?? '') . "\n";
    if (function_exists('get_last_email_failure')) {
        $failure = get_last_email_failure($targetEmail);
        $detail = trim((string) ($failure['detail'] ?? ''));
        if ($detail !== '') {
            $notice .= "Mailer detail: " . $detail . "\n";
        }
    }
    send_admin_email($notice, 'Email delivery failure');
}

function notify_customer_sms_failure(array $request, string $statusLabel): void
{
    if (!function_exists('send_admin_email')) {
        return;
    }
    $notice = "Customer SMS failed\n\n";
    $notice .= "Status: " . $statusLabel . "\n";
    $notice .= "Request id: " . (string) ($request['id'] ?? '') . "\n";
    $notice .= "Name: " . (string) ($request['name'] ?? '') . "\n";
    $notice .= "Phone: " . (string) ($request['phone'] ?? '') . "\n";
    $notice .= "Preferred: " . (string) ($request['preferred_date'] ?? '') . " " . (string) ($request['preferred_time'] ?? '') . "\n";
    send_admin_email($notice, 'SMS delivery failure');
}

function normalize_city_key(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/\s+/', ' ', $value);
    return is_string($value) ? $value : '';
}

function get_request_time_window(array $request): ?array
{
    $preferredDate = trim((string) ($request['preferred_date'] ?? ''));
    $preferredTime = trim((string) ($request['preferred_time'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $preferredDate) || !preg_match('/^\d{1,2}:\d{2}$/', $preferredTime)) {
        return null;
    }
    $durationHours = isset($request['duration_hours']) ? (float) $request['duration_hours'] : 0.0;
    if ($durationHours <= 0) {
        return null;
    }
    $tourZone = resolve_tour_timezone((string) ($request['tour_timezone'] ?? DEFAULT_TOUR_TZ));
    $start = DateTimeImmutable::createFromFormat('Y-m-d H:i', $preferredDate . ' ' . $preferredTime, $tourZone);
    if ($start === false) {
        return null;
    }
    $durationMinutes = (int) round($durationHours * 60);
    if ($durationMinutes <= 0) {
        return null;
    }
    $end = $start->modify('+' . $durationMinutes . ' minutes');
    return [
        'start' => $start,
        'end' => $end,
    ];
}

function requests_overlap_for_displacement(array $paidRequest, array $candidate): bool
{
    $paidWindow = get_request_time_window($paidRequest);
    $candidateWindow = get_request_time_window($candidate);
    if ($paidWindow === null || $candidateWindow === null) {
        return false;
    }

    $paidCity = normalize_city_key((string) ($paidRequest['city'] ?? ''));
    $candidateCity = normalize_city_key((string) ($candidate['city'] ?? ''));
    if ($paidCity !== '' && $candidateCity !== '' && $paidCity !== $candidateCity) {
        return false;
    }

    return $paidWindow['start'] < $candidateWindow['end'] && $paidWindow['end'] > $candidateWindow['start'];
}

function build_paid_override_message(): string
{
    return "OH NOOO :( Someone really wanted their appointment and managed to send a deposit so you lost yours. Sorry! You have read the rules, you understood the risks, my costs are heavy to travel and ads arent free! I need a commitment next time or you can just hope for the best! XOX";
}

function notify_displaced_customer(array &$request, string $winnerRequestId): void
{
    $name = trim((string) ($request['name'] ?? ''));
    $email = trim((string) ($request['email'] ?? ''));
    $phone = trim((string) ($request['phone'] ?? ''));
    $date = trim((string) ($request['preferred_date'] ?? ''));
    $time = trim((string) ($request['preferred_time'] ?? ''));
    $duration = trim((string) ($request['duration_label'] ?? ''));
    $message = build_paid_override_message();

    if ($email !== '') {
        $body = "Hi " . ($name !== '' ? $name : 'there') . ",\n\n";
        $body .= $message . "\n\n";
        if ($date !== '' || $time !== '') {
            $body .= "Original slot: " . trim($date . ' ' . $time) . "\n";
        }
        if ($duration !== '') {
            $body .= "Duration: " . $duration . "\n";
        }
        if ($winnerRequestId !== '') {
            $body .= "Replacement ref: " . $winnerRequestId . "\n";
        }
        $body .= "\nIf you still want a chance next time, submit again with a deposit.\n";
        if (send_payment_email($email, $body, 'Booking update')) {
            $request['paid_override_email_sent_at'] = gmdate('c');
        } else {
            notify_customer_email_failure($request, 'paid_override_cancelled');
        }
    }

    if ($phone !== '' && function_exists('send_customer_sms')) {
        $sms = $message;
        if ($date !== '' || $time !== '') {
            $sms .= " Slot: " . trim($date . ' ' . $time) . ".";
        }
        if (send_customer_sms($phone, $sms)) {
            $request['paid_override_sms_sent_at'] = gmdate('c');
        } else {
            notify_customer_sms_failure($request, 'paid_override_cancelled');
        }
    }
}

function auto_displace_overlapping_unpaid_requests(array &$requests, int $paidIndex, array $paidRequest): array
{
    $winnerId = trim((string) ($paidRequest['id'] ?? ''));
    $displacedIds = [];
    foreach ($requests as $candidateIndex => &$candidate) {
        if (!is_array($candidate) || $candidateIndex === $paidIndex) {
            continue;
        }
        $candidateId = trim((string) ($candidate['id'] ?? ''));
        if ($candidateId === '') {
            continue;
        }
        $candidateStatus = strtolower(trim((string) ($candidate['status'] ?? 'pending')));
        $candidatePayment = strtolower(trim((string) ($candidate['payment_status'] ?? '')));
        if ($candidateStatus === 'paid') {
            $candidateStatus = 'accepted';
            $candidatePayment = 'paid';
        }
        if ($candidatePayment === 'paid') {
            continue;
        }
        if (!in_array($candidateStatus, ['maybe', 'accepted'], true)) {
            continue;
        }
        if (!requests_overlap_for_displacement($paidRequest, $candidate)) {
            continue;
        }

        $candidate['status'] = 'cancelled';
        $candidate['payment_status'] = '';
        $candidate['cancel_reason'] = 'Auto-cancelled by overlapping paid booking';
        $candidate['auto_replaced_by_paid_booking_id'] = $winnerId;
        $candidate['auto_replaced_at'] = gmdate('c');
        $candidate['updated_at'] = gmdate('c');
        append_history_entry(
            $candidate,
            'status',
            'Auto-cancelled: overlapping slot replaced by paid booking' . ($winnerId !== '' ? " ({$winnerId})" : '')
        );
        notify_displaced_customer($candidate, $winnerId);
        $displacedIds[] = $candidateId;
    }
    unset($candidate);

    return array_values(array_unique($displacedIds));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$payload = get_request_body();
$id = $payload['id'] ?? '';
$status = $payload['status'] ?? '';
$paymentLink = $payload['payment_link'] ?? '';
$reason = trim((string) ($payload['reason'] ?? ''));

if ($id === '' || $status === '') {
    json_response(['error' => 'Missing id or status'], 422);
}

$allowed = ['pending', 'maybe', 'accepted', 'declined', 'paid', 'cancelled', 'blacklisted'];
if (!in_array($status, $allowed, true)) {
    json_response(['error' => 'Invalid status'], 422);
}
if (in_array($status, ['declined', 'cancelled', 'blacklisted'], true) && $reason === '') {
    json_response(['error' => 'Reason is required for this status'], 422);
}

$store = read_json_file(DATA_DIR . '/requests.json', ['requests' => []]);
$requests = $store['requests'] ?? [];
$found = false;
$noop = false;
$removeIndex = null;
$displacedRequestIds = [];
$declinedPath = DATA_DIR . '/declined.json';
$declinedStore = read_json_file($declinedPath, ['requests' => []]);
$declinedRequests = $declinedStore['requests'] ?? [];
if (!is_array($declinedRequests)) {
    $declinedRequests = [];
}

foreach ($requests as $index => &$request) {
    if (($request['id'] ?? '') !== $id) {
        continue;
    }

    if (should_skip_duplicate_status_update($request, $status, $reason, (string) $paymentLink)) {
        $found = true;
        $noop = true;
        break;
    }

    $existingStatus = (string) ($request['status'] ?? 'pending');
    $existingPayment = (string) ($request['payment_status'] ?? '');
    if ($existingStatus === 'paid') {
        $existingStatus = 'accepted';
        $existingPayment = 'paid';
    }
    $request['status'] = $existingStatus;
    if ($existingPayment !== '') {
        $request['payment_status'] = $existingPayment;
    }
    $depositAmount = isset($request['deposit_amount']) ? (int) $request['deposit_amount'] : 0;
    $requestEmail = (string) ($request['email'] ?? '');
    $paymentMethod = (string) ($request['payment_method'] ?? '');
    $depositCurrency = (string) ($request['deposit_currency'] ?? '');
    $cancelUrl = build_cancel_url($request);

    if ($status === 'declined') {
        $request['status'] = 'declined';
        $request['payment_status'] = '';
        $request['decline_reason'] = $reason;
        $request['updated_at'] = gmdate('c');
        append_history_entry($request, 'status', 'Status set to declined' . ($reason !== '' ? " ({$reason})" : ''));
        if ($requestEmail !== '' && ($request['declined_email_sent_at'] ?? '') === '') {
            $body = "Hi " . ($request['name'] ?? '') . ",\n\n";
            $body .= "Your booking request was declined.\n";
            if ($reason !== '') {
                $body .= "Reason: " . $reason . "\n";
            }
            $body .= "\nThanks for understanding.\n";
            if (send_payment_email($requestEmail, $body, 'Booking update')) {
                $request['declined_email_sent_at'] = gmdate('c');
            } else {
                notify_customer_email_failure($request, 'declined');
            }
        }
        $declinedRequests = array_values(array_filter($declinedRequests, function ($item) use ($id): bool {
            return is_array($item) && (($item['id'] ?? '') !== $id);
        }));
        $declinedRequests[] = $request;
        $declinedStore['requests'] = $declinedRequests;
        write_json_file($declinedPath, $declinedStore);
        $removeIndex = $index;
        $found = true;
        break;
    }

    if ($status === 'blacklisted') {
        $request['status'] = 'blacklisted';
        $request['payment_status'] = '';
        $request['blacklist_reason'] = $reason;
        $request['updated_at'] = gmdate('c');
        append_history_entry($request, 'status', 'Status set to blacklisted' . ($reason !== '' ? " ({$reason})" : ''));
        if ($requestEmail !== '' && ($request['blacklisted_email_sent_at'] ?? '') === '') {
            $body = "Hi " . ($request['name'] ?? '') . ",\n\n";
            $body .= "Your booking request was blacklisted.\n";
            if ($reason !== '') {
                $body .= "Reason: " . $reason . "\n";
            }
            $body .= "\nIf this seems incorrect, reply to this email.\n";
            if (send_payment_email($requestEmail, $body, 'Booking update')) {
                $request['blacklisted_email_sent_at'] = gmdate('c');
            } else {
                notify_customer_email_failure($request, 'blacklisted');
            }
        }
        add_blacklist_entry([
            'email' => $request['email'] ?? '',
            'phone' => $request['phone'] ?? '',
            'ip' => $request['client_ip'] ?? '',
            'name' => $request['name'] ?? '',
            'reason' => $reason,
            'request_id' => $request['id'] ?? '',
        ]);
        $found = true;
        break;
    }

    if ($status === 'cancelled') {
        $request['status'] = 'cancelled';
        $request['payment_status'] = '';
        $request['cancel_reason'] = $reason;
        $request['updated_at'] = gmdate('c');
        append_history_entry($request, 'status', 'Status set to cancelled' . ($reason !== '' ? " ({$reason})" : ''));
        if ($requestEmail !== '' && ($request['cancelled_email_sent_at'] ?? '') === '') {
            $body = "Hi " . ($request['name'] ?? '') . ",\n\n";
            $body .= "Your booking request was cancelled.\n";
            if ($reason !== '') {
                $body .= "Reason: " . $reason . "\n";
            }
            $body .= "\nIf you want to book again, you can send a new request.\n";
            if (send_payment_email($requestEmail, $body, 'Booking update')) {
                $request['cancelled_email_sent_at'] = gmdate('c');
            } else {
                notify_customer_email_failure($request, 'cancelled');
            }
        }
        $found = true;
        break;
    }

    if ($status === 'maybe') {
        $request['status'] = 'maybe';
        $request['payment_status'] = '';
        if ($reason !== '') {
            $request['maybe_reason'] = $reason;
        } else {
            unset($request['maybe_reason']);
        }
        append_history_entry($request, 'status', 'Status set to maybe' . ($reason !== '' ? " ({$reason})" : ''));
        if ($requestEmail !== '' && ($request['maybe_email_sent_at'] ?? '') === '') {
            $body = "Hi " . ($request['name'] ?? '') . ",\n\n";
            $body .= "Your booking is marked as maybe for now.\n\n";
            $body .= "Date/time: " . ($request['preferred_date'] ?? '') . " " . ($request['preferred_time'] ?? '') . "\n";
            $body .= "Duration: " . ($request['duration_label'] ?? '') . "\n";
            if ($reason !== '') {
                $body .= "Note: " . $reason . "\n";
            }
            $body .= "\nI will confirm as soon as possible.\n";
            append_customer_cancel_notice($body, $cancelUrl);
            if (send_payment_email($requestEmail, $body, 'Booking update')) {
                $request['maybe_email_sent_at'] = gmdate('c');
            } else {
                notify_customer_email_failure($request, 'maybe');
            }
        }
        if (($request['maybe_admin_notified_at'] ?? '') === '') {
            $adminCurrency = $depositCurrency !== '' ? $depositCurrency : (PAYPAL_CURRENCY !== '' ? PAYPAL_CURRENCY : 'USD');
            $adminMethod = format_payment_method($paymentMethod);
            $adminBody = "Booking marked maybe\n\n";
            $adminBody .= "Name: " . ($request['name'] ?? '') . "\n";
            $adminBody .= "Email: " . ($request['email'] ?? '') . "\n";
            $adminBody .= "Phone: " . ($request['phone'] ?? '') . "\n";
            $adminBody .= "Date/time: " . ($request['preferred_date'] ?? '') . " " . ($request['preferred_time'] ?? '') . "\n";
            $adminBody .= "Duration: " . ($request['duration_label'] ?? '') . "\n";
            $adminBody .= "Payment method: " . $adminMethod . "\n";
            $adminBody .= "Deposit: " . $depositAmount . " " . $adminCurrency . "\n";
            if ($reason !== '') {
                $adminBody .= "Reason: " . $reason . "\n";
            }
            $adminBody .= "Request id: " . ($request['id'] ?? '') . "\n";
            if (send_admin_email($adminBody, 'Booking maybe')) {
                $request['maybe_admin_notified_at'] = gmdate('c');
            }
        }
    }

    if ($status === 'accepted') {
        $request['status'] = 'accepted';
        if ($paymentLink === '') {
            $paymentLink = build_payment_details($paymentMethod, $depositAmount);
        }
        if ($paymentLink !== '') {
            $request['payment_link'] = $paymentLink;
        }

        if ($requestEmail !== '' && ($request['accepted_email_sent_at'] ?? '') === '') {
            $methodLabel = format_payment_method($paymentMethod);
            $currencyLabel = $depositCurrency !== '' ? $depositCurrency : (PAYPAL_CURRENCY !== '' ? PAYPAL_CURRENCY : 'USD');
            $body = "Hi " . ($request['name'] ?? '') . ",\n\n";
            $body .= "Your booking is accepted.\n\n";
            $body .= "Date/time: " . ($request['preferred_date'] ?? '') . " " . ($request['preferred_time'] ?? '') . "\n";
            $body .= "Duration: " . ($request['duration_label'] ?? '') . "\n";
            $body .= "Payment method: " . $methodLabel . "\n";
            $body .= "Deposit: " . $depositAmount . " " . $currencyLabel . "\n";
            $calendarLinks = build_calendar_links($request);
            append_calendar_links_lines($body, $calendarLinks);
            if ($paymentLink !== '') {
                $body .= "Payment details: " . $paymentLink . "\n";
            }
            $body .= "\nOnce payment is in, the time is locked.\n";
            append_customer_cancel_notice($body, $cancelUrl);
            if (send_payment_email($requestEmail, $body)) {
                $request['accepted_email_sent_at'] = gmdate('c');
            } else {
                notify_customer_email_failure($request, 'accepted');
            }
        }
        if (($request['accepted_admin_notified_at'] ?? '') === '') {
            $adminCurrency = $depositCurrency !== '' ? $depositCurrency : (PAYPAL_CURRENCY !== '' ? PAYPAL_CURRENCY : 'USD');
            $adminMethod = format_payment_method($paymentMethod);
            $adminBody = "Booking accepted\n\n";
            $adminBody .= "Name: " . ($request['name'] ?? '') . "\n";
            $adminBody .= "Email: " . ($request['email'] ?? '') . "\n";
            $adminBody .= "Phone: " . ($request['phone'] ?? '') . "\n";
            $adminBody .= "Date/time: " . ($request['preferred_date'] ?? '') . " " . ($request['preferred_time'] ?? '') . "\n";
            $adminBody .= "Duration: " . ($request['duration_label'] ?? '') . "\n";
            $adminBody .= "Payment method: " . $adminMethod . "\n";
            $adminBody .= "Deposit: " . $depositAmount . " " . $adminCurrency . "\n";
            $calendarLinks = build_calendar_links($request);
            append_calendar_links_lines($adminBody, $calendarLinks);
            if ($paymentLink !== '') {
                $adminBody .= "Payment details: " . $paymentLink . "\n";
            }
            $adminBody .= "Request id: " . ($request['id'] ?? '') . "\n";
            if (send_admin_email($adminBody, 'Booking accepted')) {
                $request['accepted_admin_notified_at'] = gmdate('c');
            }
        }
        append_history_entry($request, 'status', 'Status set to accepted');
    } elseif ($status === 'paid') {
        // A paid request is always considered an accepted booking for availability blocking.
        $request['status'] = 'accepted';
        $request['payment_status'] = 'paid';
        append_history_entry($request, 'status', 'Marked as paid');
        if ($requestEmail !== '' && ($request['paid_email_sent_at'] ?? '') === '') {
            $currencyLabel = $depositCurrency !== '' ? $depositCurrency : (PAYPAL_CURRENCY !== '' ? PAYPAL_CURRENCY : 'USD');
            $body = "Hi " . ($request['name'] ?? '') . ",\n\n";
            $body .= "Payment received. Your booking is confirmed.\n\n";
            $body .= "Date/time: " . ($request['preferred_date'] ?? '') . " " . ($request['preferred_time'] ?? '') . "\n";
            $body .= "Duration: " . ($request['duration_label'] ?? '') . "\n";
            $body .= "Deposit received: " . $depositAmount . " " . $currencyLabel . "\n\n";
            $calendarLinks = build_calendar_links($request);
            append_calendar_links_lines($body, $calendarLinks);
            $body .= "See you soon.\n";
            append_customer_cancel_notice($body, $cancelUrl);
            if (send_payment_email($requestEmail, $body, 'Payment received')) {
                $request['paid_email_sent_at'] = gmdate('c');
            } else {
                notify_customer_email_failure($request, 'paid');
            }
        }
        if (($request['paid_admin_notified_at'] ?? '') === '') {
            $adminCurrency = $depositCurrency !== '' ? $depositCurrency : (PAYPAL_CURRENCY !== '' ? PAYPAL_CURRENCY : 'USD');
            $adminBody = "Payment received for booking\n\n";
            $adminBody .= "Name: " . ($request['name'] ?? '') . "\n";
            $adminBody .= "Email: " . ($request['email'] ?? '') . "\n";
            $adminBody .= "Phone: " . ($request['phone'] ?? '') . "\n";
            $adminBody .= "Date/time: " . ($request['preferred_date'] ?? '') . " " . ($request['preferred_time'] ?? '') . "\n";
            $adminBody .= "Duration: " . ($request['duration_label'] ?? '') . "\n";
            $adminBody .= "Deposit received: " . $depositAmount . " " . $adminCurrency . "\n";
            $calendarLinks = build_calendar_links($request);
            append_calendar_links_lines($adminBody, $calendarLinks);
            $adminBody .= "Request id: " . ($request['id'] ?? '') . "\n";
            if (send_admin_email($adminBody, 'Payment received')) {
                $request['paid_admin_notified_at'] = gmdate('c');
            }
        }
        $displacedRequestIds = auto_displace_overlapping_unpaid_requests($requests, (int) $index, $request);
        if (count($displacedRequestIds) > 0) {
            append_history_entry($request, 'status', 'Auto-replaced overlapping maybe/accepted requests: ' . implode(', ', $displacedRequestIds));
        }
    } elseif ($status !== 'maybe' && $paymentLink !== '') {
        $request['status'] = $status;
        $request['payment_link'] = $paymentLink;
        if ($status !== 'accepted') {
            $request['payment_status'] = '';
        }
        append_history_entry($request, 'status', "Status set to {$status}");
    } elseif ($status !== 'maybe') {
        $request['status'] = $status;
        if ($status !== 'accepted') {
            $request['payment_status'] = '';
        }
        append_history_entry($request, 'status', "Status set to {$status}");
    }

    $request['updated_at'] = gmdate('c');
    $found = true;
    break;
}

if ($removeIndex !== null) {
    array_splice($requests, $removeIndex, 1);
}

if (!$found) {
    json_response(['error' => 'Request not found'], 404);
}

if ($noop) {
    json_response(['ok' => true, 'noop' => true]);
}

$store['requests'] = $requests;
write_json_file(DATA_DIR . '/requests.json', $store);

if ($found) {
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
    $isPaid = (string) ($request['payment_status'] ?? '') === 'paid';
    if ($isPaid) {
        $bookingBlocks = build_booking_blocks($request, 'paid');
        $blocked = array_merge($blocked, $bookingBlocks);
    }
    $activeBookingIds = [];
    foreach ($requests as $requestItem) {
        if (!is_array($requestItem)) {
            continue;
        }
        $requestPayment = strtolower(trim((string) ($requestItem['payment_status'] ?? '')));
        if ($requestPayment !== 'paid') {
            continue;
        }
        $requestId = trim((string) ($requestItem['id'] ?? ''));
        if ($requestId !== '') {
            $activeBookingIds[$requestId] = true;
        }
    }
    $blocked = array_values(array_filter($blocked, function ($entry) use ($activeBookingIds): bool {
        if (!is_array($entry)) {
            return false;
        }
        if (($entry['kind'] ?? '') !== 'booking') {
            return true;
        }
        $entryBookingId = trim((string) ($entry['booking_id'] ?? ''));
        if ($entryBookingId === '') {
            return false;
        }
        return isset($activeBookingIds[$entryBookingId]);
    }));
    $availability['blocked'] = $blocked;
    $availability['updated_at'] = gmdate('c');
    write_json_file(DATA_DIR . '/availability.json', $availability);
}

$statusForPush = strtolower(trim((string) ($request['status'] ?? '')));
$paymentStatusForPush = strtolower(trim((string) ($request['payment_status'] ?? '')));
$isConfirmedForPush = $statusForPush === 'accepted' || $paymentStatusForPush === 'paid';
if ($isConfirmedForPush) {
    $tokens = get_push_token_strings();
    if ($tokens) {
        $pushTitle = 'Booking confirmed';
        $pushBodyParts = [];
        $durationLabel = trim((string) ($request['duration_label'] ?? ''));
        $dateLabel = trim((string) ($request['preferred_date'] ?? ''));
        $timeLabel = trim((string) ($request['preferred_time'] ?? ''));
        $cityLabel = trim((string) ($request['city'] ?? ''));
        if ($durationLabel !== '') {
            $pushBodyParts[] = $durationLabel;
        }
        $dateTimeLabel = trim($dateLabel . ' ' . $timeLabel);
        if ($dateTimeLabel !== '') {
            $pushBodyParts[] = $dateTimeLabel;
        }
        if ($cityLabel !== '') {
            $pushBodyParts[] = $cityLabel;
        }
        $pushBody = $pushBodyParts ? implode(' - ', $pushBodyParts) : 'Appointment confirmed';
        $pushData = [
            'id' => (string) ($request['id'] ?? ''),
            'name' => (string) ($request['name'] ?? ''),
            'email' => (string) ($request['email'] ?? ''),
            'phone' => (string) ($request['phone'] ?? ''),
            'city' => (string) ($request['city'] ?? ''),
            'preferred_date' => (string) ($request['preferred_date'] ?? ''),
            'preferred_time' => (string) ($request['preferred_time'] ?? ''),
            'duration_label' => (string) ($request['duration_label'] ?? ''),
            'duration_hours' => (string) ($request['duration_hours'] ?? ''),
            'status' => (string) ($request['status'] ?? ''),
            'payment_status' => (string) ($request['payment_status'] ?? ''),
        ];
        send_push_to_tokens($tokens, $pushTitle, $pushBody, $pushData);
    }
}

json_response([
    'ok' => true,
    'displaced_count' => count($displacedRequestIds),
    'displaced_request_ids' => $displacedRequestIds,
]);
