<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

function normalize_experience(string $experience): string
{
    $normalized = strtolower(trim($experience));
    if (in_array($normalized, ['gfe', 'pse', 'filming'], true)) {
        return $normalized;
    }
    return 'gfe';
}

function format_experience_label(string $experience): string
{
    $normalized = strtolower(trim($experience));
    if ($normalized === 'gfe') {
        return 'GFE';
    }
    if ($normalized === 'pse') {
        return 'PSE';
    }
    if ($normalized === 'filming') {
        return 'Filming';
    }
    return $normalized !== '' ? strtoupper($normalized) : 'GFE';
}

function normalize_city_name(string $city): string
{
    $city = strtolower(trim($city));
    $city = preg_replace('/\s+/', ' ', $city);
    return is_string($city) ? $city : '';
}

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

function parse_city_list(string $raw): array
{
    $parts = preg_split('/[,;\n\r]+/', $raw);
    if (!is_array($parts)) {
        return [];
    }
    $clean = [];
    foreach ($parts as $part) {
        $value = trim((string) $part);
        if ($value === '') {
            continue;
        }
        $key = normalize_city_name($value);
        if ($key === '' || isset($clean[$key])) {
            continue;
        }
        $clean[$key] = $value;
    }
    return array_values($clean);
}

function is_fly_me_city(string $city): bool
{
    return normalize_city_name($city) === 'fly me to you';
}

function get_touring_city_for_date(string $dateKey): string
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateKey)) {
        return '';
    }
    $touring = get_effective_touring_schedule();
    foreach ($touring as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $start = trim((string) ($entry['start'] ?? ''));
        $end = trim((string) ($entry['end'] ?? ''));
        $city = trim((string) ($entry['city'] ?? ''));
        if ($city === '') {
            continue;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            continue;
        }
        if ($start <= $dateKey && $dateKey <= $end) {
            return $city;
        }
    }
    return '';
}

function get_city_schedule_for_request(array $availability, string $city, string $dateKey): array
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateKey)) {
        return [];
    }
    $targetCity = normalize_city_name($city);
    if ($targetCity === '') {
        return [];
    }
    $citySchedules = $availability['city_schedules'] ?? [];
    if (!is_array($citySchedules)) {
        return [];
    }
    foreach ($citySchedules as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $entryCity = normalize_city_name((string) ($entry['city'] ?? ''));
        $start = trim((string) ($entry['start'] ?? ''));
        $end = trim((string) ($entry['end'] ?? ''));
        if ($entryCity === '' || $entryCity !== $targetCity) {
            continue;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            continue;
        }
        if ($start <= $dateKey && $dateKey <= $end) {
            return $entry;
        }
    }
    return [];
}

function get_base_rate(float $hours, string $experience, string $rateKey): int
{
    if ($hours <= 0) {
        return 0;
    }
    if ($rateKey === 'social') {
        return 1000;
    }
    if ($hours >= 8 && $hours <= 12) {
        return 3000;
    }

    $entries = [
        ['hours' => 0.5, 'amount' => 400],
        ['hours' => 1.0, 'amount' => 700],
        ['hours' => 1.5, 'amount' => 1000],
        ['hours' => 2.0, 'amount' => 1300],
        ['hours' => 3.0, 'amount' => 1600],
        ['hours' => 4.0, 'amount' => 2000],
        ['hours' => 12.0, 'amount' => 3000],
    ];
    foreach ($entries as $entry) {
        if (abs($hours - $entry['hours']) < 0.001) {
            return (int) $entry['amount'];
        }
    }

    $lower = null;
    $upper = null;
    foreach ($entries as $entry) {
        if ($entry['hours'] < $hours) {
            $lower = $entry;
            continue;
        }
        if ($entry['hours'] > $hours) {
            $upper = $entry;
            break;
        }
    }

    if ($lower === null) {
        return (int) $entries[0]['amount'];
    }
    if ($upper === null) {
        return (int) $entries[count($entries) - 1]['amount'];
    }

    $ratio = ($hours - $lower['hours']) / ($upper['hours'] - $lower['hours']);
    return (int) round($lower['amount'] + ($upper['amount'] - $lower['amount']) * $ratio);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$payload = get_request_body();
$requestEmail = trim((string) ($payload['email'] ?? ''));
$requestPhone = trim((string) ($payload['phone'] ?? ''));
$clientIp = get_client_ip();
if (is_blacklisted($requestEmail, $requestPhone, $clientIp)) {
    json_response(['error' => 'Blocked'], 403);
}
$errors = [];
$required = [
    'name',
    'email',
    'phone',
    'city',
    'currency',
    'booking_type',
    'duration_label',
    'duration_hours',
    'preferred_date',
    'preferred_time',
    'experience',
    'payment_method',
    'deposit_confirm',
];

foreach ($required as $field) {
    if (empty($payload[$field])) {
        $errors[$field] = 'Required';
    }
}

$emailValue = trim((string) ($payload['email'] ?? ''));
$phoneValue = trim((string) ($payload['phone'] ?? ''));
if ($emailValue !== '' && !is_valid_email_address($emailValue)) {
    $errors['email'] = 'Invalid email';
}
if ($phoneValue !== '' && !is_valid_phone_international($phoneValue)) {
    $errors['phone'] = 'Use international format: +countrycode number';
}
$payload['email'] = $emailValue;
$payload['phone'] = $phoneValue;

if (($payload['booking_type'] ?? '') === 'outcall' && empty($payload['outcall_address'])) {
    $errors['outcall_address'] = 'Required for outcall';
}

if (!empty($errors)) {
    json_response(['error' => 'Validation failed', 'fields' => $errors], 422);
}

$currency = strtoupper(trim((string) ($payload['currency'] ?? '')));
if ($currency !== '' && !preg_match('/^[A-Z]{3,6}$/', $currency)) {
    $errors['currency'] = 'Invalid currency';
}

$depositConfirmRaw = strtolower(trim((string) ($payload['deposit_confirm'] ?? '')));
$depositConfirmed = in_array($depositConfirmRaw, ['1', 'true', 'yes', 'on'], true);
if (!$depositConfirmed) {
    $errors['deposit_confirm'] = 'Required';
}

$preferredDate = trim((string) ($payload['preferred_date'] ?? ''));
$preferredTime = trim((string) ($payload['preferred_time'] ?? ''));
$tourTimezone = trim((string) ($payload['tour_timezone'] ?? DEFAULT_TOUR_TZ));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $preferredDate)) {
    $errors['preferred_date'] = 'Invalid date';
}
if (!preg_match('/^\d{1,2}:\d{2}$/', $preferredTime)) {
    $errors['preferred_time'] = 'Invalid time';
}

$tourZone = null;
if (!isset($errors['preferred_date']) && !isset($errors['preferred_time'])) {
    try {
        $tourZone = new DateTimeZone($tourTimezone !== '' ? $tourTimezone : DEFAULT_TOUR_TZ);
    } catch (Exception $error) {
        $tourZone = new DateTimeZone(DEFAULT_TOUR_TZ);
    }
    $selectedDateTime = DateTimeImmutable::createFromFormat('Y-m-d H:i', $preferredDate . ' ' . $preferredTime, $tourZone);
    if ($selectedDateTime === false) {
        $errors['preferred_time'] = 'Invalid time';
    } else {
        $now = new DateTimeImmutable('now', $tourZone);
        if ($selectedDateTime <= $now) {
            $errors['preferred_time'] = 'Selected time is in the past';
        }
    }
}

$requestedCity = trim((string) ($payload['city'] ?? ''));

if (!empty($errors)) {
    json_response(['error' => 'Validation failed', 'fields' => $errors], 422);
}

$paymentMethod = strtolower(trim((string) ($payload['payment_method'] ?? 'paypal')));
if ($paymentMethod === 'e-transfer') {
    $paymentMethod = 'interac';
}
if ($paymentMethod === 'litecoin') {
    $paymentMethod = 'ltc';
}
if ($paymentMethod === 'bitcoin') {
    $paymentMethod = 'btc';
}
$allowedMethods = ['paypal', 'usdc', 'btc', 'ltc', 'interac', 'etransfer', 'wise'];
if (!in_array($paymentMethod, $allowedMethods, true)) {
    $paymentMethod = 'paypal';
}

$depositPercent = 20.0;
$serviceAddons = [
    'pse' => 100,
    'filming' => 500,
];
$displayRate = 1.0;
$fiatOverrides = [
    'USD' => 1.0,
    'EUR' => 0.7,
    'GBP' => 0.65,
];
$depositCurrency = $currency !== '' ? $currency : (PAYPAL_CURRENCY !== '' ? PAYPAL_CURRENCY : 'CAD');
if ($paymentMethod === 'paypal') {
    $depositCurrency = PAYPAL_CURRENCY !== '' ? PAYPAL_CURRENCY : 'CAD';
    $displayRate = 1.0;
} elseif (in_array($depositCurrency, ['USDC', 'BTC', 'LTC'], true)) {
    $depositCurrency = 'CAD';
} elseif (isset($fiatOverrides[$depositCurrency])) {
    $displayRate = (float) $fiatOverrides[$depositCurrency];
}

$hours = is_numeric($payload['duration_hours']) ? (float) $payload['duration_hours'] : 0.0;
$rateKey = strtolower(trim((string) ($payload['duration_rate_key'] ?? '')));
if ($rateKey !== 'social') {
    $rateKey = '';
}
$experience = normalize_experience((string) ($payload['experience'] ?? ''));
$baseRate = get_base_rate($hours, $experience, $rateKey);
$serviceAddonAmount = ($baseRate > 0 && isset($serviceAddons[$experience])) ? (int) $serviceAddons[$experience] : 0;
$totalRate = $baseRate + $serviceAddonAmount;
$deposit = $totalRate > 0 ? (int) round(($totalRate * ($depositPercent / 100)) * $displayRate) : 0;
$billingCurrency = $depositCurrency !== '' ? $depositCurrency : ($currency !== '' ? $currency : 'CAD');
$displayTotalRate = (int) round($totalRate * $displayRate);
$displayServiceAddonAmount = (int) round($serviceAddonAmount * $displayRate);
$displayBaseRate = max(0, $displayTotalRate - $displayServiceAddonAmount);
$serviceAddonLabel = '';
if ($experience === 'pse') {
    $serviceAddonLabel = 'PSE add-on';
} elseif ($experience === 'filming') {
    $serviceAddonLabel = 'Filming add-on';
}

$availability = read_json_file(DATA_DIR . '/availability.json', [
    'availability_mode' => 'open',
    'buffer_minutes' => DEFAULT_BUFFER_MINUTES,
    'blocked' => [],
    'city_schedules' => [],
]);
$availabilityMode = (string) ($availability['availability_mode'] ?? 'open');
$bufferMinutes = (int) ($availability['buffer_minutes'] ?? DEFAULT_BUFFER_MINUTES);
$blockedSlots = $availability['blocked'] ?? [];
$recurringBlocks = $availability['recurring'] ?? [];
$selectedCitySchedule = [];
if ($requestedCity !== '' && $preferredDate !== '' && !is_fly_me_city($requestedCity)) {
    $selectedCitySchedule = get_city_schedule_for_request($availability, $requestedCity, $preferredDate);
}
if ($selectedCitySchedule) {
    $cityBuffer = isset($selectedCitySchedule['buffer_minutes']) ? (int) $selectedCitySchedule['buffer_minutes'] : $bufferMinutes;
    $bufferMinutes = max(0, min(240, $cityBuffer));
    $cityTimezone = trim((string) ($selectedCitySchedule['timezone'] ?? ''));
    if ($cityTimezone !== '') {
        $tourTimezone = $cityTimezone;
    }
}
if (!is_array($blockedSlots)) {
    $blockedSlots = [];
}
if (!is_array($recurringBlocks)) {
    $recurringBlocks = [];
}

if ($availabilityMode === 'closed') {
    json_response(['error' => 'Currently unavailable'], 409);
}

if ($hours > 0) {
    $payloadTourTimezone = trim((string) ($payload['tour_timezone'] ?? ''));
    $tourTz = $payloadTourTimezone !== '' ? $payloadTourTimezone : ($tourTimezone !== '' ? $tourTimezone : DEFAULT_TOUR_TZ);
    $preferredDate = (string) ($payload['preferred_date'] ?? '');
    $preferredTime = (string) ($payload['preferred_time'] ?? '');

    if ($preferredDate !== '' && $preferredTime !== '') {
        try {
            $tourZone = new DateTimeZone($tourTz !== '' ? $tourTz : DEFAULT_TOUR_TZ);
            $dateTime = DateTimeImmutable::createFromFormat('Y-m-d H:i', $preferredDate . ' ' . $preferredTime, $tourZone);
            if ($dateTime !== false) {
                $tourTime = $dateTime;
                $tourDateKey = $tourTime->format('Y-m-d');
                $startMinutes = ((int) $tourTime->format('H')) * 60 + (int) $tourTime->format('i');
                $durationMinutes = (int) round($hours * 60);
                $endMinutes = $startMinutes + $durationMinutes;
                $windowStart = max(0, $startMinutes - $bufferMinutes);
                $windowEnd = min(1440, $endMinutes + $bufferMinutes);
                $weekdayIndex = (int) $tourTime->format('w');
                foreach ($recurringBlocks as $block) {
                    if (!is_array($block)) {
                        continue;
                    }
                    $days = $block['days'] ?? [];
                    if (!is_array($days) || !in_array($weekdayIndex, $days, true)) {
                        continue;
                    }
                    if (!empty($block['all_day'])) {
                        json_response(['error' => 'Selected time is unavailable'], 409);
                    }
                    $start = (string) ($block['start'] ?? '');
                    $end = (string) ($block['end'] ?? '');
                    if (!preg_match('/^\d{1,2}:\d{2}$/', $start) || !preg_match('/^\d{1,2}:\d{2}$/', $end)) {
                        continue;
                    }
                    [$sHour, $sMin] = array_map('intval', explode(':', $start));
                    [$eHour, $eMin] = array_map('intval', explode(':', $end));
                    $blockStart = $sHour * 60 + $sMin;
                    $blockEnd = $eHour * 60 + $eMin;
                    if ($windowStart < $blockEnd && $windowEnd > $blockStart) {
                        json_response(['error' => 'Selected time is unavailable'], 409);
                    }
                }
                foreach ($blockedSlots as $entry) {
                    if (!is_array($entry)) {
                        continue;
                    }
                    if (($entry['date'] ?? '') !== $tourDateKey) {
                        continue;
                    }
                    $start = (string) ($entry['start'] ?? '');
                    $end = (string) ($entry['end'] ?? '');
                    if (!preg_match('/^\d{1,2}:\d{2}$/', $start) || !preg_match('/^\d{1,2}:\d{2}$/', $end)) {
                        continue;
                    }
                    [$sHour, $sMin] = array_map('intval', explode(':', $start));
                    [$eHour, $eMin] = array_map('intval', explode(':', $end));
                    $blockStart = $sHour * 60 + $sMin;
                    $blockEnd = $eHour * 60 + $eMin;
                    if ($windowStart < $blockEnd && $windowEnd > $blockStart) {
                        json_response(['error' => 'Selected time is unavailable'], 409);
                    }
                }
            }
        } catch (Exception $error) {
        }
    }
}

$requestsPath = DATA_DIR . '/requests.json';
$store = read_json_file($requestsPath, ['requests' => []]);
$requests = $store['requests'] ?? [];
if (!is_array($requests)) {
    $requests = [];
}

$followupPhoneRaw = strtolower(trim((string) ($payload['contact_followup_phone'] ?? 'no')));
$followupEmailRaw = strtolower(trim((string) ($payload['contact_followup_email'] ?? 'no')));
$followupPhone = in_array($followupPhoneRaw, ['yes', 'true', '1', 'on'], true);
$followupEmail = in_array($followupEmailRaw, ['yes', 'true', '1', 'on'], true);
$contactFollowup = ($followupPhone || $followupEmail) ? 'yes' : 'no';
$followupChannels = [];
if ($followupPhone) {
    $followupChannels[] = 'phone';
}
if ($followupEmail) {
    $followupChannels[] = 'email';
}
$contactChannel = implode(',', $followupChannels);
$followupCities = [];
$requestCity = trim((string) ($payload['city'] ?? ''));
if ($contactFollowup === 'yes' && $requestCity !== '') {
    $followupCities[] = $requestCity;
}
$followupCities = array_merge(
    $followupCities,
    parse_city_list((string) ($payload['followup_phone_other_cities'] ?? '')),
    parse_city_list((string) ($payload['followup_email_other_cities'] ?? ''))
);
$followupCitiesUnique = [];
foreach ($followupCities as $city) {
    $key = normalize_city_name((string) $city);
    if ($key === '' || isset($followupCitiesUnique[$key])) {
        continue;
    }
    $followupCitiesUnique[$key] = trim((string) $city);
}
$followupCities = array_values($followupCitiesUnique);

$paymentLink = build_payment_details($paymentMethod, $deposit);
$paymentEmailSentAt = '';
$requestEmail = trim((string) ($payload['email'] ?? ''));
$experienceLabel = format_experience_label((string) ($payload['experience'] ?? ''));
if ($requestEmail !== '') {
    $methodLabel = format_payment_method($paymentMethod);
    $currencyLabel = $billingCurrency !== '' ? $billingCurrency : 'CAD';
    $body = "Hi " . trim((string) $payload['name']) . ",\n\n";
    if ($deposit > 0) {
        $body .= "We got your request. To lock it in, send the deposit.\n";
    } else {
        $body .= "Your request is in. No deposit was selected, so the time is not held or prioritized.\n";
    }
    if ($paymentLink !== '') {
        $body .= "Payment details: " . $paymentLink . "\n";
    }
    $body .= "\nPayment method: " . $methodLabel . "\n";
    $body .= "Base rate: " . $displayBaseRate . " " . $currencyLabel . "\n";
    $body .= "Service: " . $experienceLabel . "\n";
    $body .= "Duration: " . ($payload['duration_label'] ?? '') . "\n";
    if ($displayServiceAddonAmount > 0) {
        $body .= ($serviceAddonLabel !== '' ? $serviceAddonLabel : 'Service add-on') . ": +" . $displayServiceAddonAmount . " " . $currencyLabel . "\n";
    }
    $body .= "Total rate: " . $displayTotalRate . " " . $currencyLabel . "\n";
    if ($deposit > 0) {
        $body .= "Deposit: " . $deposit . " " . $currencyLabel . "\n";
    }
    $body .= "Requested date/time: " . ($payload['preferred_date'] ?? '') . " " . ($payload['preferred_time'] ?? '') . "\n\n";
    if ($deposit > 0) {
        $body .= "You will receive a confirmation email once your payment is accepted.\n";
    }
    $contactPhone = '(your phone number)';
    if (defined('CONTACT_SMS_WHATSAPP')) {
        $configuredPhone = trim((string) CONTACT_SMS_WHATSAPP);
        if ($configuredPhone !== '') {
            $contactPhone = $configuredPhone;
        }
    }
    $body .= "If you have any questions, contact me at " . $contactPhone . " through SMS or WHATSAPP.\n";
    if (function_exists('send_payment_email') && send_payment_email($requestEmail, $body)) {
        $paymentEmailSentAt = gmdate('c');
    }
}

$request = [
    'id' => 'req_' . gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)),
    'status' => 'pending',
    'created_at' => gmdate('c'),
    'deposit_amount' => $deposit,
    'name' => trim((string) $payload['name']),
    'email' => trim((string) $payload['email']),
    'phone' => trim((string) $payload['phone']),
    'client_ip' => $clientIp,
    'city' => trim((string) $payload['city']),
    'currency' => $currency,
    'booking_type' => (string) $payload['booking_type'],
    'outcall_address' => trim((string) ($payload['outcall_address'] ?? '')),
    'experience' => $experience,
    'duration_label' => (string) $payload['duration_label'],
    'duration_hours' => (string) $payload['duration_hours'],
    'preferred_date' => (string) $payload['preferred_date'],
    'preferred_time' => (string) $payload['preferred_time'],
    'client_timezone' => (string) ($payload['client_timezone'] ?? ''),
    'tour_timezone' => $tourTimezone,
    'buffer_minutes' => (string) ($payload['buffer_minutes'] ?? ''),
    'notes' => trim((string) ($payload['notes'] ?? '')),
    'contact_followup' => $contactFollowup,
    'contact_channel' => $contactChannel,
    'contact_followup_phone' => $followupPhone ? 'yes' : 'no',
    'contact_followup_email' => $followupEmail ? 'yes' : 'no',
    'followup_phone_other_cities' => trim((string) ($payload['followup_phone_other_cities'] ?? '')),
    'followup_email_other_cities' => trim((string) ($payload['followup_email_other_cities'] ?? '')),
    'followup_cities' => $followupCities,
    'deposit_confirmed' => $depositConfirmed,
    'payment_method' => $paymentMethod,
    'deposit_currency' => $depositCurrency,
    'deposit_percent' => (string) $depositPercent,
    'base_rate' => $baseRate,
    'pse_addon' => ($experience === 'pse') ? $serviceAddonAmount : 0,
    'service_addon' => $serviceAddonAmount,
    'service_addon_label' => $serviceAddonLabel,
    'total_rate' => $totalRate,
    'payment_link' => $paymentLink,
    'payment_email_sent_at' => $paymentEmailSentAt,
    'history' => [
        [
            'at' => gmdate('c'),
            'action' => 'created',
            'source' => 'booking_form',
            'summary' => 'Request created',
        ],
    ],
];

$requests[] = $request;
$store['requests'] = $requests;
write_json_file($requestsPath, $store);

$adminBody = "New booking request\n\n";
$adminBody .= "Name: " . ($request['name'] ?? '') . "\n";
$adminBody .= "Email: " . ($request['email'] ?? '') . "\n";
$adminBody .= "Phone: " . ($request['phone'] ?? '') . "\n";
$adminBody .= "Future contact by phone: " . (($request['contact_followup_phone'] ?? 'no') === 'yes' ? 'yes' : 'no') . "\n";
$adminBody .= "Future contact by email: " . (($request['contact_followup_email'] ?? 'no') === 'yes' ? 'yes' : 'no') . "\n";
$adminBody .= "Future-contact cities: " . (is_array($request['followup_cities'] ?? null) ? implode(', ', $request['followup_cities']) : '') . "\n";
$adminBody .= "City: " . ($request['city'] ?? '') . "\n";
$adminBody .= "Currency: " . ($request['currency'] ?? '') . "\n";
$adminBody .= "Type: " . ($request['booking_type'] ?? '') . "\n";
$adminBody .= "Service: " . $experienceLabel . "\n";
$adminBody .= "Duration: " . ($request['duration_label'] ?? '') . "\n";
$adminBody .= "Preferred: " . ($request['preferred_date'] ?? '') . " " . ($request['preferred_time'] ?? '') . "\n";
$adminBody .= "Base rate (CAD): " . $baseRate . "\n";
if ($serviceAddonAmount > 0) {
    $adminBody .= ($serviceAddonLabel !== '' ? $serviceAddonLabel : 'Service add-on') . " (CAD): +" . $serviceAddonAmount . "\n";
}
$adminBody .= "Total rate (CAD): " . $totalRate . "\n";
$adminBody .= "Payment method: " . format_payment_method($paymentMethod) . "\n";
$adminBody .= "Deposit confirmed: " . ($depositConfirmed ? 'yes' : 'no') . "\n";
$adminBody .= "Deposit: " . $deposit . " " . ($depositCurrency !== '' ? $depositCurrency : PAYPAL_CURRENCY) . "\n";
$adminBody .= "Request id: " . ($request['id'] ?? '') . "\n";
if (function_exists('send_admin_email')) {
    send_admin_email($adminBody, 'New booking request');
}
if (function_exists('send_booking_push')) {
    send_booking_push($request);
}

$paymentLinkIsUrl = preg_match('/^https?:\\/\\//i', $paymentLink) === 1;
$response = [
    'ok' => true,
    'id' => $request['id'],
    'deposit_amount' => $deposit,
    'payment_link' => $paymentLink,
    'payment_link_is_url' => $paymentLinkIsUrl,
    'payment_email_sent' => $paymentEmailSentAt !== '',
];

if ($deposit > 0) {
    $apiPath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $basePath = str_replace('\\', '/', dirname($apiPath));
    $basePath = $basePath === '/' ? '' : rtrim($basePath, '/');
    if ($basePath === '.') {
        $basePath = '';
    }
    $response['payment_page'] = $basePath . '/pay/index.php?id=' . rawurlencode((string) $request['id']);
}

json_response($response);
