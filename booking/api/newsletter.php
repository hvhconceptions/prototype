<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$payload = get_request_body();
$honeypot = trim((string) ($payload['newsletter_signal'] ?? ''));
if ($honeypot !== '') {
    json_response(['error' => 'Spam detected'], 422);
}

$email = trim((string) ($payload['nl_email'] ?? ''));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['error' => 'Email required'], 422);
}
$email = strtolower($email);

$ip = get_client_ip();
$now = time();
$guardPath = DATA_DIR . '/newsletter_guard.json';
$guardStore = read_json_file($guardPath, ['events' => []]);
$events = $guardStore['events'] ?? [];
if (!is_array($events)) {
    $events = [];
}
$events = array_values(array_filter($events, static function ($entry) use ($now): bool {
    if (!is_array($entry)) {
        return false;
    }
    $atUnix = (int) ($entry['at_unix'] ?? 0);
    return $atUnix >= ($now - 86400);
}));

$recentIpAttempts = 0;
$recentEmailAttempts = 0;
foreach ($events as $entry) {
    $atUnix = (int) ($entry['at_unix'] ?? 0);
    if ($ip !== '' && (string) ($entry['ip'] ?? '') === $ip && $atUnix >= ($now - 900)) {
        $recentIpAttempts++;
    }
    if ((string) ($entry['email'] ?? '') === $email && $atUnix >= ($now - 86400)) {
        $recentEmailAttempts++;
    }
}
if ($recentIpAttempts >= 6 || $recentEmailAttempts >= 3) {
    json_response(['error' => 'Too many requests. Please try again later.'], 429);
}

$name = trim((string) ($payload['nl_name'] ?? ''));
$phone = trim((string) ($payload['nl_phone'] ?? ''));
$cities = $payload['nl_city'] ?? [];
if (!is_array($cities)) {
    $cities = [$cities];
}
$cities = array_values(array_filter(array_map('trim', array_map('strval', $cities))));
$suggestions = trim((string) ($payload['nl_suggestions'] ?? ''));
if (strlen($suggestions) > 800) {
    json_response(['error' => 'Suggestions are too long'], 422);
}

$parseSuggestedCities = static function (string $value): array {
    $parts = preg_split('/[\r\n,;|\/]+/', $value) ?: [];
    $parts = array_map('trim', $parts);
    return array_values(array_filter($parts, static fn(string $city): bool => $city !== ''));
};
$normalizeSuggestedCity = static function (string $city): string {
    $city = strtolower($city);
    $city = (string) preg_replace('/\(.*?\)/', '', $city);
    $city = (string) preg_replace('/[^a-z\s.\'-]/', ' ', $city);
    $city = (string) preg_replace('/\s+/', ' ', $city);
    return trim($city);
};

$suggestedCities = [];
if ($suggestions !== '') {
    $blockedPatterns = [
        '/\bcalgary\b/i',
        '/\bedmonton\b/i',
        '/\bottawa\b/i',
        '/\bjamais\b/i',
        '/\bindia\b/i',
    ];
    $usaPatterns = [
        '/\busa\b/i',
        '/\bu\.s\.a\b/i',
        '/\bunited states\b/i',
        '/\bunited states of america\b/i',
        '/\bamerica\b/i',
        '/\bnew york\b/i',
        '/\blos angeles\b/i',
        '/\bchicago\b/i',
        '/\bmiami\b/i',
        '/\blas vegas\b/i',
        '/\bdallas\b/i',
        '/\bhouston\b/i',
        '/\bsan francisco\b/i',
        '/\bseattle\b/i',
        '/\batlanta\b/i',
        '/\bboston\b/i',
        '/\bwashington\b/i',
        '/\bphoenix\b/i',
        '/\bsan diego\b/i',
        '/\bphiladelphia\b/i',
    ];
    $allowedMajorNonUsCities = [
        'amsterdam',
        'athens',
        'auckland',
        'bangkok',
        'barcelona',
        'beirut',
        'berlin',
        'bogota',
        'brussels',
        'buenos aires',
        'cape town',
        'casablanca',
        'copenhagen',
        'dublin',
        'dubai',
        'helsinki',
        'hong kong',
        'istanbul',
        'jakarta',
        'johannesburg',
        'lima',
        'lisbon',
        'london',
        'madrid',
        'manila',
        'marrakesh',
        'medellin',
        'melbourne',
        'mexico city',
        'milan',
        'monterrey',
        'montreal',
        'osaka',
        'oslo',
        'panama city',
        'paris',
        'prague',
        'rio de janeiro',
        'rome',
        'santiago',
        'sao paulo',
        'seoul',
        'singapore',
        'stockholm',
        'sydney',
        'taipei',
        'tokyo',
        'toronto',
        'vancouver',
        'vienna',
        'warsaw',
    ];
    $allowedLookup = array_fill_keys($allowedMajorNonUsCities, true);
    $suggestedCities = $parseSuggestedCities($suggestions);

    foreach ($suggestedCities as $suggestedCity) {
        foreach ($blockedPatterns as $pattern) {
            if (preg_match($pattern, $suggestedCity) === 1) {
                json_response(['error' => 'Use major non-USA cities only. For other places, book Fly Me To You.'], 422);
            }
        }
        foreach ($usaPatterns as $pattern) {
            if (preg_match($pattern, $suggestedCity) === 1) {
                json_response(['error' => 'Use major non-USA cities only. For other places, book Fly Me To You.'], 422);
            }
        }

        $normalized = $normalizeSuggestedCity($suggestedCity);
        if ($normalized === '' || !isset($allowedLookup[$normalized])) {
            json_response(['error' => 'Use major non-USA cities only. For other places, book Fly Me To You.'], 422);
        }
    }
}

$entry = [
    'id' => 'nl_' . gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)),
    'created_at' => gmdate('c'),
    'name' => $name,
    'email' => $email,
    'phone' => $phone,
    'cities' => $cities,
    'suggestions' => $suggestions,
    'suggested_cities' => $suggestedCities,
];

$path = DATA_DIR . '/newsletter.json';
$store = read_json_file($path, ['subscribers' => []]);
$subscribers = $store['subscribers'] ?? [];
if (!is_array($subscribers)) {
    $subscribers = [];
}
$subscribers[] = $entry;
$store['subscribers'] = $subscribers;
write_json_file($path, $store);

$events[] = [
    'at_unix' => $now,
    'at' => gmdate('c'),
    'ip' => $ip,
    'email' => $email,
];
$guardStore['events'] = $events;
write_json_file($guardPath, $guardStore);

$adminBody = "New newsletter signup\n\n";
$adminBody .= "Name: " . $name . "\n";
$adminBody .= "Email: " . $email . "\n";
if ($phone !== '') {
    $adminBody .= "Phone: " . $phone . "\n";
}
if (!empty($cities)) {
    $adminBody .= "Cities: " . implode(', ', $cities) . "\n";
}
if ($suggestions !== '') {
    $adminBody .= "Suggestions: " . $suggestions . "\n";
}
send_admin_email($adminBody, 'Newsletter signup');

$userBody = "Thanks for subscribing! You're now on my radar for tour updates.\n";
send_payment_email($email, $userBody, 'You are subscribed');

json_response(['ok' => true]);
