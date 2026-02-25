<?php
declare(strict_types=1);

const ADMIN_API_KEY = 'Simo.666$$$';
const ADMIN_UI_USER = 'capitainecommando';
const ADMIN_UI_PASSWORD_HASH = '';
const STRIPE_SECRET_KEY = '';
const STRIPE_PUBLISHABLE_KEY = '';
const STRIPE_WEBHOOK_SECRET = '';
const PAYPAL_ME_LINK = 'https://paypal.me/payheidi';
const PAYPAL_CLIENT_ID = 'AfIK_OpdPFBYQo0LcWO0LAJbn8b3Wu1m0unSPfZ2qJJnUuJgKMrxlcET2Q_aogZHzQ1e1JT5MEW8mK1k';
const PAYPAL_CURRENCY = 'CAD';
const FCM_PROJECT_ID = 'heidi-van-bookly';
const FCM_SERVICE_ACCOUNT_JSON = '/home/heidjkpc/secure/heidi-van-bookly-firebase-adminsdk-fbsvc-ca9b697b72.json';
const FCM_SERVER_KEY = '';
const INTERAC_EMAIL = 'hvh.conceptions@gmail.com';
const WISE_EMAIL = '';
const WISE_PAY_LINK = 'https://wise.com/pay/me/karinaelisabeth';
const USDC_WALLET = '0x914E494DFeF0597CA3726Eaa4606159Dd321A553';
const USDC_NETWORK = 'Base';
const BTC_WALLET = '32MSk6as4Jyi3aec8hc57c5fMyNhQuyybk';
const BTC_NETWORK = 'Coinbase';
const LTC_WALLET = 'MGzn9mxRW33NUVu5RbvnYtUglLf7BwW7ApS';
const LTC_NETWORK = 'Litecoin';

const EMAIL_ENABLED = true;
const EMAIL_FROM = 'bookings@heidivanhorny.com';
const EMAIL_REPLY_TO = 'bookings@heidivanhorny.com';
const EMAIL_SUBJECT = 'Booking Heidi Van Horny';
const ADMIN_NOTIFY_EMAIL = 'pornstar.heidi@gmail.com';
const CONTACT_SMS_WHATSAPP = '+1 514 607 6253';

const DATA_DIR = __DIR__ . '/../data';
const SITE_CONTENT_FILE = DATA_DIR . '/site_content.json';
const GALLERY_FILE = DATA_DIR . '/gallery.json';
const BLACKLIST_FILE = DATA_DIR . '/blacklist.json';
const DEFAULT_TOUR_CITY = 'Touring city not set';
const DEFAULT_TOUR_TZ = 'America/Toronto';
const DEFAULT_BUFFER_MINUTES = 30;

function ensure_data_dir(): void
{
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0755, true);
    }
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function get_request_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw !== false && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    return $_POST;
}

function read_json_file(string $path, array $default): array
{
    ensure_data_dir();
    if (!file_exists($path)) {
        return $default;
    }
    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') {
        return $default;
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return $default;
    }
    return $decoded;
}

function write_json_file(string $path, array $data): void
{
    ensure_data_dir();
    $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        json_response(['error' => 'Failed to encode JSON'], 500);
    }
    file_put_contents($path, $encoded, LOCK_EX);
}

function normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function normalize_phone(string $phone): string
{
    return preg_replace('/\D+/', '', $phone);
}

function get_client_ip(): string
{
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
    if ($ip !== '') {
        return trim(explode(',', $ip)[0]);
    }
    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($forwarded !== '') {
        return trim(explode(',', $forwarded)[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function read_blacklist(): array
{
    return read_json_file(BLACKLIST_FILE, ['entries' => []]);
}

function save_blacklist(array $entries): void
{
    write_json_file(BLACKLIST_FILE, ['entries' => $entries, 'updated_at' => gmdate('c')]);
}

function is_blacklisted(string $email = '', string $phone = '', string $ip = ''): bool
{
    $emailKey = normalize_email($email);
    $phoneKey = normalize_phone($phone);
    $ipKey = trim($ip);
    $store = read_blacklist();
    $entries = $store['entries'] ?? [];
    if (!is_array($entries)) {
        return false;
    }
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $entryEmail = normalize_email((string) ($entry['email'] ?? ''));
        $entryPhone = normalize_phone((string) ($entry['phone'] ?? ''));
        $entryIp = trim((string) ($entry['ip'] ?? ''));
        if ($emailKey !== '' && $entryEmail !== '' && $emailKey === $entryEmail) {
            return true;
        }
        if ($phoneKey !== '' && $entryPhone !== '' && $phoneKey === $entryPhone) {
            return true;
        }
        if ($ipKey !== '' && $entryIp !== '' && $ipKey === $entryIp) {
            return true;
        }
    }
    return false;
}

function add_blacklist_entry(array $entry): void
{
    $email = normalize_email((string) ($entry['email'] ?? ''));
    $phone = normalize_phone((string) ($entry['phone'] ?? ''));
    $ip = trim((string) ($entry['ip'] ?? ''));
    if ($email === '' && $phone === '' && $ip === '') {
        return;
    }
    $store = read_blacklist();
    $entries = $store['entries'] ?? [];
    if (!is_array($entries)) {
        $entries = [];
    }
    foreach ($entries as $existing) {
        if (!is_array($existing)) {
            continue;
        }
        if ($email !== '' && normalize_email((string) ($existing['email'] ?? '')) === $email) {
            return;
        }
        if ($phone !== '' && normalize_phone((string) ($existing['phone'] ?? '')) === $phone) {
            return;
        }
        if ($ip !== '' && trim((string) ($existing['ip'] ?? '')) === $ip) {
            return;
        }
    }
    $entries[] = [
        'email' => $email,
        'phone' => $phone,
        'ip' => $ip,
        'name' => trim((string) ($entry['name'] ?? '')),
        'reason' => trim((string) ($entry['reason'] ?? '')),
        'request_id' => trim((string) ($entry['request_id'] ?? '')),
        'created_at' => gmdate('c'),
    ];
    save_blacklist($entries);
}

function get_default_site_content(): array
{
    return [
        'touring' => [
            ['start' => '2026-02-08', 'end' => '2026-02-14', 'city' => 'Montreal'],
            ['start' => '2026-02-15', 'end' => '2026-02-18', 'city' => 'Toronto'],
            ['start' => '2026-02-19', 'end' => '2026-02-21', 'city' => 'Vancouver'],
            ['start' => '2026-02-22', 'end' => '2026-03-04', 'city' => 'Montreal'],
            ['start' => '2026-03-05', 'end' => '2026-03-09', 'city' => 'London (UK)'],
            ['start' => '2026-03-10', 'end' => '2026-03-13', 'city' => 'Berlin'],
            ['start' => '2026-03-14', 'end' => '2026-03-19', 'city' => 'Paris'],
        ],
        'touring_partners' => [],
        'rates' => [
            'gfe' => [
                ['hours' => 0.5, 'amount' => 400],
                ['hours' => 1.0, 'amount' => 700],
                ['hours' => 1.5, 'amount' => 1000],
                ['hours' => 2.0, 'amount' => 1300],
                ['hours' => 3.0, 'amount' => 1600],
                ['hours' => 4.0, 'amount' => 2000],
                ['hours' => 12.0, 'amount' => 3000],
            ],
            'pse' => [
                ['hours' => 0.5, 'amount' => 800],
                ['hours' => 1.0, 'amount' => 800],
                ['hours' => 1.5, 'amount' => 1100],
                ['hours' => 2.0, 'amount' => 1400],
                ['hours' => 3.0, 'amount' => 1600],
                ['hours' => 4.0, 'amount' => 2000],
                ['hours' => 12.0, 'amount' => 3000],
            ],
            'social' => [
                'label' => 'Social experiment (2h restaurant/public + 1h private)',
                'amount' => 1000,
            ],
            'long_session' => [
                'min' => 8,
                'max' => 12,
                'amount' => 3000,
            ],
        ],
        'conditions' => [
            'deposit' => '<strong>deposit.</strong> I\'m not born yesterday - I have socials, reviews, and I\'m verified on multiple platforms. It\'s on you to do your homework. I carry heavy costs for ads, travel stays, and self-maintenance so I stay eye candy for you and easy to reach, so every booking is secured with a 20% deposit of the total rate. Payment details are sent by email after you request. No deposit? Grab a gift card online or at the nearest convenience store. Other currencies are handled through <a href="https://wise.com" target="_blank" rel="noopener noreferrer">Wise</a> or crypto on request.',
            'fast' => '<strong>fast transfers.</strong> Speed matters: locals can send Interac e-Transfers for instant CAD, travellers can hit me on <a href="https://wise.com" target="_blank" rel="noopener noreferrer">Wise</a> for wire-speed multi-currency drops, and crypto (USDT/USDC/BTC on fast chains) is the quickest for near-instant confirmation. Ask for the wallet or beneficiary details you need.',
            'outcall' => '<strong>outcall.</strong> To avoid wasted trips, send my Uber (round trip) or your deposit ahead of time. Minimum booking is 1 hour in Montreal (home base) and 1.5 hours when I\'m touring. Keep timing realistic - I\'m not doing half hours outcall or travelling far for a short appointment.',
            'fly' => '<strong>fly me to you.</strong> You cover deposit and accommodations; I\'ll be flexible on rates for multi-day escapades. I\'m passport-ready, but be aware my status keeps me out of the U.S.',
            'philosophy' => '<strong>my philosophy.</strong> My rates match the market and the energy I bring. I don\'t stack back-to-back bookings. Staying low volume keeps me happier, hornier, and safer. This service is a luxury, not a necessity. I\'m picky about who I see because my mental health matters. Bring good vibes, good hygiene, and a sharp sense of humor. It gets you three steps closer than looks ever will. (P.S. keep your unsolicited D-pics and named selfies to yourself; your looks won\'t get you a discount.)',
        ],
        'updated_at' => gmdate('c'),
    ];
}

function read_site_content(): array
{
    return read_json_file(SITE_CONTENT_FILE, get_default_site_content());
}

function write_site_content(array $data): void
{
    $data['updated_at'] = gmdate('c');
    write_json_file(SITE_CONTENT_FILE, $data);
}

function normalize_touring_city_label(string $value): string
{
    $city = trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($city === '') {
        return '';
    }

    $normalized = strtolower($city);
    $normalized = str_replace(['ß', 'β'], ['ss', 'b'], $normalized);
    $normalized = preg_replace('/[^a-z0-9]+/i', ' ', $normalized);
    $normalized = is_string($normalized) ? trim($normalized) : '';
    if ($normalized === 'tba' || $normalized === 'to be announced') {
        return '';
    }
    if ($normalized !== '' && preg_match('/(^| )((b|ss) mode)( |$)/', $normalized)) {
        return 'Montreal';
    }
    return $city;
}

function normalize_touring_entries(array $touring): array
{
    $clean = [];
    foreach ($touring as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $start = trim((string) ($entry['start'] ?? ''));
        $end = trim((string) ($entry['end'] ?? ''));
        $city = normalize_touring_city_label((string) ($entry['city'] ?? ''));
        if ($city === '') {
            continue;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            continue;
        }
        if ($start > $end) {
            continue;
        }
        $clean[] = [
            'start' => $start,
            'end' => $end,
            'city' => $city,
        ];
    }
    usort($clean, static function (array $a, array $b): int {
        return strcmp($a['start'] . '|' . $a['end'] . '|' . strtolower($a['city']), $b['start'] . '|' . $b['end'] . '|' . strtolower($b['city']));
    });
    return $clean;
}

function parse_inside_month_token(string $token): int
{
    $token = strtolower(trim($token));
    if ($token === '') {
        return 0;
    }
    $token = substr($token, 0, 3);
    $map = [
        'jan' => 1,
        'feb' => 2,
        'mar' => 3,
        'apr' => 4,
        'may' => 5,
        'jun' => 6,
        'jul' => 7,
        'aug' => 8,
        'sep' => 9,
        'oct' => 10,
        'nov' => 11,
        'dec' => 12,
    ];
    return $map[$token] ?? 0;
}

function parse_inside_touring_schedule(string $html): array
{
    if ($html === '') {
        return [];
    }

    if (!preg_match_all('/<div[^>]*class="[^"]*\btouring-row\b[^"]*"[^>]*>(.*?)<\/div>/is', $html, $rowMatches)) {
        return [];
    }

    $pending = [];
    foreach ($rowMatches[1] as $rowHtml) {
        if (!is_string($rowHtml)) {
            continue;
        }
        if (!preg_match('/<span[^>]*class="[^"]*\btouring-city\b[^"]*"[^>]*>(.*?)<\/span>/is', $rowHtml, $cityMatch)) {
            continue;
        }
        $city = normalize_touring_city_label((string) ($cityMatch[1] ?? ''));
        if ($city === '') {
            continue;
        }

        $dateText = html_entity_decode(strip_tags($rowHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $dateText = strtolower(trim(preg_replace('/\s+/', ' ', $dateText) ?? ''));
        if ($dateText === '') {
            continue;
        }

        if (!preg_match('/(\d{1,2})\s*([a-z]{3,9})\s*-\s*(\d{1,2})\s*([a-z]{3,9})/i', $dateText, $range)) {
            continue;
        }

        $startDay = (int) $range[1];
        $startMonth = parse_inside_month_token((string) $range[2]);
        $endDay = (int) $range[3];
        $endMonth = parse_inside_month_token((string) $range[4]);
        if ($startDay < 1 || $startDay > 31 || $endDay < 1 || $endDay > 31 || $startMonth < 1 || $endMonth < 1) {
            continue;
        }

        $pending[] = [
            'city' => $city,
            'start_day' => $startDay,
            'start_month' => $startMonth,
            'end_day' => $endDay,
            'end_month' => $endMonth,
        ];
    }

    if (!$pending) {
        return [];
    }

    $year = (int) gmdate('Y');
    $previousStartMonth = null;
    $clean = [];
    foreach ($pending as $row) {
        $startMonth = (int) $row['start_month'];
        $endMonth = (int) $row['end_month'];
        if ($previousStartMonth !== null && $startMonth < $previousStartMonth) {
            $year += 1;
        }
        $previousStartMonth = $startMonth;
        $startYear = $year;
        $endYear = $endMonth < $startMonth ? ($startYear + 1) : $startYear;
        $start = sprintf('%04d-%02d-%02d', $startYear, $startMonth, (int) $row['start_day']);
        $end = sprintf('%04d-%02d-%02d', $endYear, $endMonth, (int) $row['end_day']);
        if ($start > $end) {
            continue;
        }
        $clean[] = [
            'start' => $start,
            'end' => $end,
            'city' => (string) $row['city'],
        ];
    }

    return normalize_touring_entries($clean);
}

function read_inside_touring_schedule(): array
{
    $insidePath = dirname(dirname(__DIR__)) . '/inside.html';
    if (!is_file($insidePath)) {
        return [];
    }
    $html = file_get_contents($insidePath);
    if (!is_string($html) || $html === '') {
        return [];
    }
    return parse_inside_touring_schedule($html);
}

function get_effective_touring_schedule(): array
{
    $content = read_site_content();
    $touring = $content['touring'] ?? [];
    if (is_array($touring)) {
        $normalized = normalize_touring_entries($touring);
        if ($normalized) {
            return $normalized;
        }
    }

    // Fallback for legacy/static installs where schedule still lives in inside.html.
    $insideSchedule = read_inside_touring_schedule();
    if ($insideSchedule) {
        return $insideSchedule;
    }

    return [];
}

function get_default_gallery_items(): array
{
    return [
        ['src' => '/photos/heidi1.jpg', 'alt' => 'Heidi photo 1'],
        ['src' => '/photos/heidi2.jpg', 'alt' => 'Heidi photo 2'],
        ['src' => '/photos/heidi3.jpg', 'alt' => 'Heidi photo 3'],
        ['src' => '/photos/heidi4.jpg', 'alt' => 'Heidi photo 4'],
        ['src' => '/photos/heidi5.jpg', 'alt' => 'Heidi photo 5'],
        ['src' => '/photos/heidi6.jpg', 'alt' => 'Heidi photo 6'],
        ['src' => '/photos/heidi7.jpg', 'alt' => 'Heidi photo 7'],
        ['src' => '/photos/heidi8.jpg', 'alt' => 'Heidi photo 8'],
        ['src' => '/photos/heidi9.jpg', 'alt' => 'Heidi photo 9'],
        ['src' => '/photos/heidi10.jpg', 'alt' => 'Heidi photo 10'],
        ['src' => '/photos/heidi11.jpg', 'alt' => 'Heidi photo 11'],
        ['src' => '/photos/heidi12.png', 'alt' => 'Heidi photo 12'],
        ['src' => '/photos/heidi13.JPG', 'alt' => 'Heidi photo 13'],
        ['src' => '/photos/heidi14.jpg', 'alt' => 'Heidi photo 14'],
    ];
}

function normalize_gallery_mode(string $mode): string
{
    $normalized = strtolower(trim($mode));
    if ($normalized === 'album') {
        return 'album';
    }
    if ($normalized === 'carousel') {
        return 'carousel';
    }
    return 'next';
}

function normalize_gallery_seconds($value): int
{
    $seconds = (int) $value;
    if ($seconds < 2) {
        return 2;
    }
    if ($seconds > 30) {
        return 30;
    }
    return $seconds;
}

function normalize_gallery_items(array $items): array
{
    $clean = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $src = trim((string) ($item['src'] ?? ''));
        if ($src === '') {
            continue;
        }
        $clean[] = [
            'src' => $src,
            'alt' => trim((string) ($item['alt'] ?? '')),
        ];
    }
    return $clean ?: get_default_gallery_items();
}

function normalize_gallery_payload(array $raw): array
{
    $items = $raw['items'] ?? [];
    if (!is_array($items)) {
        $items = [];
    }
    return [
        'items' => normalize_gallery_items($items),
        'display_mode' => normalize_gallery_mode((string) ($raw['display_mode'] ?? 'next')),
        'carousel_seconds' => normalize_gallery_seconds($raw['carousel_seconds'] ?? 5),
        'updated_at' => (string) ($raw['updated_at'] ?? gmdate('c')),
    ];
}

function read_gallery_data(): array
{
    $default = [
        'items' => get_default_gallery_items(),
        'display_mode' => 'next',
        'carousel_seconds' => 5,
        'updated_at' => gmdate('c'),
    ];
    $raw = read_json_file(GALLERY_FILE, $default);
    return normalize_gallery_payload($raw);
}

function write_gallery_data(array $data): void
{
    // Backward compatibility: allow write_gallery_data($itemsOnly)
    $isList = true;
    $expectedIndex = 0;
    foreach ($data as $key => $_value) {
        if ($key !== $expectedIndex) {
            $isList = false;
            break;
        }
        $expectedIndex++;
    }
    if ($isList) {
        $data = ['items' => $data];
    }
    $payload = normalize_gallery_payload($data);
    $payload['updated_at'] = gmdate('c');
    write_json_file(GALLERY_FILE, $payload);
}

function require_admin(): void
{
    if (ADMIN_API_KEY === '' || ADMIN_API_KEY === 'change-this-admin-key') {
        json_response(['error' => 'Admin key not configured'], 500);
    }
    $headerKey = $_SERVER['HTTP_X_ADMIN_KEY'] ?? '';
    $queryKey = $_GET['key'] ?? '';
    $postKey = $_POST['key'] ?? '';
    $provided = $headerKey !== '' ? $headerKey : ($queryKey !== '' ? $queryKey : $postKey);
    if (!hash_equals(ADMIN_API_KEY, (string) $provided)) {
        json_response(['error' => 'Unauthorized'], 401);
    }
}

function build_paypal_link(int $amount): string
{
    if ($amount <= 0) {
        return '';
    }
    $base = rtrim(PAYPAL_ME_LINK, '/');
    $currency = PAYPAL_CURRENCY !== '' ? PAYPAL_CURRENCY : 'USD';
    return $base . '/' . $amount . '?currencyCode=' . rawurlencode($currency);
}

function format_payment_method(string $method): string
{
    $normalized = strtolower(trim($method));
    if ($normalized === 'e-transfer' || $normalized === 'etransfer' || $normalized === 'interac') {
        return 'Interac e-Transfer';
    }
    if ($normalized === 'wise') {
        return 'Wise';
    }
    if ($normalized === 'litecoin' || $normalized === 'ltc') {
        return 'Litecoin';
    }
    if ($normalized === 'usdc') {
        return 'USDC';
    }
    if ($normalized === 'btc') {
        return 'Bitcoin';
    }
    if ($normalized === 'paypal') {
        return 'Interac e-Transfer';
    }
    return $normalized !== '' ? strtoupper($normalized) : 'Interac e-Transfer';
}

function build_crypto_details(string $label, string $wallet, string $network): string
{
    if ($wallet === '') {
        return '';
    }
    $suffix = $network !== '' ? ' (' . $network . ')' : '';
    return $label . $suffix . ': ' . $wallet;
}

function build_interac_details(): string
{
    if (INTERAC_EMAIL === '') {
        return '';
    }
    return 'Interac e-Transfer (Canada only): ' . INTERAC_EMAIL;
}

function build_wise_details(): string
{
    if (WISE_PAY_LINK !== '') {
        return WISE_PAY_LINK;
    }
    if (WISE_EMAIL !== '') {
        return 'Wise (email): ' . WISE_EMAIL;
    }
    return '';
}

function build_payment_details(string $method, int $amount): string
{
    $normalized = strtolower(trim($method));
    if ($normalized === 'e-transfer' || $normalized === 'etransfer' || $normalized === 'interac') {
        $details = build_interac_details();
        if ($details !== '') {
            return $details;
        }
    }
    if ($normalized === 'wise') {
        $details = build_wise_details();
        if ($details !== '') {
            return $details;
        }
    }
    if ($normalized === 'usdc') {
        $details = build_crypto_details('USDC', USDC_WALLET, USDC_NETWORK);
        if ($details !== '') {
            return $details;
        }
    }
    if ($normalized === 'btc') {
        $details = build_crypto_details('BTC', BTC_WALLET, BTC_NETWORK);
        if ($details !== '') {
            return $details;
        }
    }
    if ($normalized === 'litecoin' || $normalized === 'ltc') {
        $details = build_crypto_details('LTC', LTC_WALLET, LTC_NETWORK);
        if ($details !== '') {
            return $details;
        }
    }
    $details = build_interac_details();
    if ($details !== '') {
        return $details;
    }
    $details = build_wise_details();
    if ($details !== '') {
        return $details;
    }
    return '';
}

function escape_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function email_link_label_for_url(string $url): string
{
    $lower = strtolower(trim($url));
    if ($lower === '') {
        return 'Open link';
    }
    if (strpos($lower, 'google.com/calendar/render') !== false) {
        return 'Add to Google Calendar';
    }
    if (strpos($lower, 'outlook.live.com/calendar') !== false) {
        return 'Add to Samsung / Microsoft Calendar';
    }
    if (strpos($lower, '/api/calendar.php') !== false) {
        return 'Add to iCloud / Apple Calendar';
    }
    $path = parse_url($url, PHP_URL_PATH);
    if (is_string($path) && strtolower(substr($path, -4)) === '.ics') {
        return 'Add to iCloud / Apple Calendar';
    }
    $host = parse_url($url, PHP_URL_HOST);
    if (is_string($host) && $host !== '') {
        $host = preg_replace('/^www\./i', '', $host) ?: $host;
        return 'Open link (' . $host . ')';
    }
    return 'Open link';
}

function linkify_text_for_email(string $plainText): string
{
    $escaped = escape_html($plainText);
    $linked = preg_replace_callback(
        '~(https?://[^\s<]+)~i',
        static function (array $matches): string {
            $url = $matches[1];
            $safeUrl = escape_html($url);
            $label = escape_html(email_link_label_for_url($url));
            return '<a href="' . $safeUrl . '" title="' . $safeUrl . '" target="_blank" rel="noopener noreferrer" style="color:#e0006d;text-decoration:underline;">' . $label . '</a>';
        },
        $escaped
    );
    if (!is_string($linked)) {
        $linked = $escaped;
    }
    return nl2br($linked, false);
}

function build_email_html(string $subject, string $plainBody): string
{
    $subjectSafe = escape_html($subject);
    $bodyHtml = linkify_text_for_email($plainBody);
    $year = gmdate('Y');
    $siteUrl = 'https://heidivanhorny.com';
    return '<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>' . $subjectSafe . '</title>
  </head>
  <body style="margin:0;padding:0;background:#11050b;font-family:Arial,sans-serif;color:#ffffff;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#11050b;padding:24px 12px;">
      <tr>
        <td align="center">
          <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:680px;background:#1b0712;border:1px solid #3c1228;border-radius:18px;overflow:hidden;">
            <tr>
              <td style="padding:26px 24px 10px;text-align:center;background:linear-gradient(135deg,#300a1f,#11050b);">
                <a href="' . $siteUrl . '" target="_blank" rel="noopener noreferrer" style="display:inline-block;text-decoration:none;color:#ff4ca8;font-size:56px;line-height:1;font-weight:800;letter-spacing:0.1em;">HVH</a>
                <div style="margin-top:8px;color:#f9b8d9;font-size:12px;letter-spacing:0.14em;text-transform:uppercase;">Click to open website</div>
              </td>
            </tr>
            <tr>
              <td style="padding:20px 24px 10px;">
                <h1 style="margin:0 0 12px;font-size:22px;line-height:1.3;color:#ffe3f3;">' . $subjectSafe . '</h1>
                <div style="padding:16px;border-radius:12px;background:#2a0d1a;border:1px solid #4f1735;color:#fff3fa;font-size:15px;line-height:1.65;">' . $bodyHtml . '</div>
              </td>
            </tr>
            <tr>
              <td style="padding:16px 24px 24px;color:#d8a8c2;font-size:12px;line-height:1.5;">
                This message was sent by Heidi Van Horny booking system.<br>
                <a href="' . $siteUrl . '" target="_blank" rel="noopener noreferrer" style="color:#ff70bb;text-decoration:underline;">heidivanhorny.com</a>
                &nbsp;|&nbsp;
                ' . $year . '
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>';
}

function send_multipart_email(string $to, string $subjectLine, string $plainBody): bool
{
    if ($to === '') {
        return false;
    }
    $normalizedPlain = str_replace(["\r\n", "\r"], "\n", $plainBody);
    $htmlBody = build_email_html($subjectLine, $normalizedPlain);
    try {
        $boundary = 'hvh_' . bin2hex(random_bytes(12));
    } catch (Exception $error) {
        $boundary = 'hvh_' . md5(uniqid((string) mt_rand(), true));
    }

    $headers = [];
    if (EMAIL_FROM !== '') {
        $headers[] = 'From: ' . EMAIL_FROM;
    }
    if (EMAIL_REPLY_TO !== '') {
        $headers[] = 'Reply-To: ' . EMAIL_REPLY_TO;
    }
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

    $message = '';
    $message .= '--' . $boundary . "\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= $normalizedPlain . "\r\n\r\n";
    $message .= '--' . $boundary . "\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= $htmlBody . "\r\n\r\n";
    $message .= '--' . $boundary . "--";

    return mail($to, $subjectLine, $message, implode("\r\n", $headers));
}

function send_plain_email(string $to, string $subjectLine, string $plainBody): bool
{
    if ($to === '') {
        return false;
    }
    $headers = [];
    if (EMAIL_FROM !== '') {
        $headers[] = 'From: ' . EMAIL_FROM;
    }
    if (EMAIL_REPLY_TO !== '') {
        $headers[] = 'Reply-To: ' . EMAIL_REPLY_TO;
    }
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    return mail($to, $subjectLine, $plainBody, implode("\r\n", $headers));
}

function append_customer_contact_footer(string $body): string
{
    $message = trim($body);
    $phone = trim((string) CONTACT_SMS_WHATSAPP);
    if ($phone === '') {
        return $message;
    }
    if (stripos($message, $phone) !== false) {
        return $message;
    }
    return rtrim($message) . "\n\nPhone: " . $phone . "\n";
}

function send_payment_email(string $to, string $body, ?string $subject = null): bool
{
    if (!EMAIL_ENABLED) {
        return false;
    }
    if ($to === '') {
        return false;
    }
    $subjectLine = $subject ?: EMAIL_SUBJECT;
    $customerBody = append_customer_contact_footer($body);
    if (send_multipart_email($to, $subjectLine, $customerBody)) {
        return true;
    }
    return send_plain_email($to, $subjectLine, $customerBody);
}

function send_admin_email(string $body, ?string $subject = null): bool
{
    if (!EMAIL_ENABLED) {
        return false;
    }
    if (ADMIN_NOTIFY_EMAIL === '') {
        return false;
    }
    $subjectLine = $subject ?: 'New booking request';
    if (send_multipart_email(ADMIN_NOTIFY_EMAIL, $subjectLine, $body)) {
        return true;
    }
    return send_plain_email(ADMIN_NOTIFY_EMAIL, $subjectLine, $body);
}

function read_push_tokens(): array
{
    return read_json_file(DATA_DIR . '/push_tokens.json', ['tokens' => []]);
}

function save_push_tokens(array $tokens): void
{
    write_json_file(DATA_DIR . '/push_tokens.json', ['tokens' => $tokens, 'updated_at' => gmdate('c')]);
}

function upsert_push_token(string $token, string $platform = ''): void
{
    $token = trim($token);
    if ($token === '') {
        return;
    }
    $store = read_push_tokens();
    $tokens = $store['tokens'] ?? [];
    if (!is_array($tokens)) {
        $tokens = [];
    }
    $now = gmdate('c');
    $updated = false;
    foreach ($tokens as &$entry) {
        if (!is_array($entry)) {
            continue;
        }
        if (($entry['token'] ?? '') === $token) {
            $entry['last_seen'] = $now;
            if ($platform !== '') {
                $entry['platform'] = $platform;
            }
            $updated = true;
            break;
        }
    }
    unset($entry);
    if (!$updated) {
        $tokens[] = [
            'token' => $token,
            'platform' => $platform,
            'created_at' => $now,
            'last_seen' => $now,
        ];
    }
    save_push_tokens($tokens);
}

function get_push_token_strings(): array
{
    $store = read_push_tokens();
    $tokens = $store['tokens'] ?? [];
    if (!is_array($tokens)) {
        return [];
    }
    $list = [];
    foreach ($tokens as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $token = trim((string) ($entry['token'] ?? ''));
        if ($token !== '') {
            $list[] = $token;
        }
    }
    return array_values(array_unique($list));
}

function remove_push_tokens(array $tokensToRemove): void
{
    if (!$tokensToRemove) {
        return;
    }
    $removeSet = [];
    foreach ($tokensToRemove as $token) {
        $trimmed = trim((string) $token);
        if ($trimmed !== '') {
            $removeSet[$trimmed] = true;
        }
    }
    if (!$removeSet) {
        return;
    }
    $store = read_push_tokens();
    $tokens = $store['tokens'] ?? [];
    if (!is_array($tokens)) {
        return;
    }
    $kept = [];
    foreach ($tokens as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $token = trim((string) ($entry['token'] ?? ''));
        if ($token === '' || isset($removeSet[$token])) {
            continue;
        }
        $kept[] = $entry;
    }
    save_push_tokens($kept);
}

function fcm_base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function resolve_fcm_service_account_path(): string
{
    $candidates = [];

    $configured = trim((string) FCM_SERVICE_ACCOUNT_JSON);
    if ($configured !== '') {
        $candidates[] = $configured;
    }

    $envPath = getenv('GOOGLE_APPLICATION_CREDENTIALS');
    if (is_string($envPath) && trim($envPath) !== '') {
        $candidates[] = trim($envPath);
    }

    $fileName = '';
    foreach ($candidates as $candidate) {
        $name = basename(str_replace('\\', '/', (string) $candidate));
        if ($name !== '') {
            $fileName = $name;
            break;
        }
    }

    if ($fileName !== '') {
        $homeDir = dirname(dirname(dirname(__DIR__)));
        $candidates[] = $homeDir . '/secure/' . $fileName;
        $candidates[] = $homeDir . '/private/' . $fileName;
        $candidates[] = $homeDir . '/public_html/secure/' . $fileName;
        $candidates[] = DATA_DIR . '/' . $fileName;
    }

    $seen = [];
    foreach ($candidates as $candidate) {
        $path = str_replace('\\', '/', trim((string) $candidate));
        if ($path === '') {
            continue;
        }
        $key = strtolower($path);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        if (is_file($path)) {
            return $path;
        }
    }

    return '';
}

function get_fcm_service_account(): ?array
{
    static $cached = null;
    static $loaded = false;
    if ($loaded) {
        return $cached;
    }
    $loaded = true;
    $path = resolve_fcm_service_account_path();
    if ($path === '') {
        $cached = null;
        return null;
    }
    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') {
        $cached = null;
        return null;
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $cached = null;
        return null;
    }
    if (empty($data['client_email']) || empty($data['private_key'])) {
        $cached = null;
        return null;
    }
    $cached = $data;
    return $cached;
}

function get_fcm_project_id(): string
{
    if (FCM_PROJECT_ID !== '') {
        return FCM_PROJECT_ID;
    }
    $serviceAccount = get_fcm_service_account();
    return is_array($serviceAccount) ? (string) ($serviceAccount['project_id'] ?? '') : '';
}

function fcm_http_post(string $url, array $headers, string $body): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $response = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$code, is_string($response) ? $response : ''];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'timeout' => 8,
        ],
    ]);
    $response = @file_get_contents($url, false, $context);
    $status = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $header) {
            if (preg_match('/^HTTP\\/\\S+\\s+(\\d+)/', $header, $matches)) {
                $status = (int) $matches[1];
                break;
            }
        }
    }
    return [$status, $response !== false ? $response : ''];
}

function get_fcm_access_token(): ?string
{
    $cachePath = DATA_DIR . '/fcm_token_cache.json';
    $now = time();
    $cache = read_json_file($cachePath, []);
    if (is_array($cache)) {
        $token = (string) ($cache['access_token'] ?? '');
        $expiresAt = (int) ($cache['expires_at'] ?? 0);
        if ($token !== '' && $expiresAt > $now + 60) {
            return $token;
        }
    }

    $serviceAccount = get_fcm_service_account();
    if (!is_array($serviceAccount)) {
        return null;
    }
    $email = (string) ($serviceAccount['client_email'] ?? '');
    $privateKey = (string) ($serviceAccount['private_key'] ?? '');
    if ($email === '' || $privateKey === '') {
        return null;
    }

    $header = ['alg' => 'RS256', 'typ' => 'JWT'];
    $payload = [
        'iss' => $email,
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600,
    ];
    $signingInput = fcm_base64url_encode(json_encode($header)) . '.' . fcm_base64url_encode(json_encode($payload));
    $signature = '';
    $ok = openssl_sign($signingInput, $signature, $privateKey, 'sha256');
    if (!$ok) {
        return null;
    }
    $jwt = $signingInput . '.' . fcm_base64url_encode($signature);
    $postBody = http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt,
    ]);
    [$status, $body] = fcm_http_post(
        'https://oauth2.googleapis.com/token',
        ['Content-Type: application/x-www-form-urlencoded'],
        $postBody
    );
    if ($status < 200 || $status >= 300 || $body === '') {
        return null;
    }
    $data = json_decode($body, true);
    if (!is_array($data)) {
        return null;
    }
    $token = (string) ($data['access_token'] ?? '');
    $expiresIn = (int) ($data['expires_in'] ?? 0);
    if ($token === '' || $expiresIn <= 0) {
        return null;
    }
    write_json_file($cachePath, [
        'access_token' => $token,
        'expires_at' => $now + $expiresIn,
        'updated_at' => gmdate('c'),
    ]);
    return $token;
}

function send_fcm_v1_message(array $message): array
{
    $result = [
        'ok' => false,
        'status' => 0,
        'body' => '',
        'invalid_token' => false,
    ];
    $projectId = get_fcm_project_id();
    if ($projectId === '') {
        return $result;
    }
    $accessToken = get_fcm_access_token();
    if ($accessToken === null) {
        return $result;
    }
    $json = json_encode(['message' => $message]);
    if ($json === false) {
        return $result;
    }
    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json; charset=utf-8',
    ];
    [$status, $body] = fcm_http_post(
        'https://fcm.googleapis.com/v1/projects/' . rawurlencode($projectId) . '/messages:send',
        $headers,
        $json
    );
    $result['status'] = $status;
    $result['body'] = $body;
    $result['ok'] = $status >= 200 && $status < 300 && $body !== '';
    if (!$result['ok'] && $body !== '') {
        $lower = strtolower($body);
        if (
            strpos($lower, 'unregistered') !== false ||
            strpos($lower, 'registration token is not valid') !== false ||
            strpos($lower, 'invalid registration token') !== false ||
            strpos($lower, 'not a valid fcm registration token') !== false
        ) {
            $result['invalid_token'] = true;
        }
    }
    return $result;
}

function send_fcm_payload(array $payload): bool
{
    if (FCM_SERVER_KEY === '') {
        return false;
    }
    $json = json_encode($payload);
    if ($json === false) {
        return false;
    }
    $headers = [
        'Authorization: key=' . FCM_SERVER_KEY,
        'Content-Type: application/json',
    ];
    [$status, $body] = fcm_http_post('https://fcm.googleapis.com/fcm/send', $headers, $json);
    return $status >= 200 && $status < 300 && $body !== '';
}

function send_push_to_tokens(array $tokens, string $title, string $body, array $data = []): bool
{
    if (!$tokens) {
        return false;
    }
    $ok = false;
    if (get_fcm_project_id() !== '' && get_fcm_service_account() !== null) {
        $invalidTokens = [];
        foreach ($tokens as $token) {
            $message = [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $data,
            ];
            $result = send_fcm_v1_message($message);
            if (!empty($result['invalid_token'])) {
                $invalidTokens[] = $token;
            }
            if (!empty($result['ok'])) {
                $ok = true;
            }
        }
        if ($invalidTokens) {
            remove_push_tokens($invalidTokens);
        }
        return $ok;
    }

    if (FCM_SERVER_KEY === '') {
        return false;
    }
    $chunks = array_chunk($tokens, 500);
    foreach ($chunks as $chunk) {
        $payload = [
            'registration_ids' => $chunk,
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
            'data' => $data,
        ];
        if (send_fcm_payload($payload)) {
            $ok = true;
        }
    }
    return $ok;
}

function send_booking_push(array $request): bool
{
    $tokens = get_push_token_strings();
    if (!$tokens) {
        return false;
    }
    $title = 'New booking request';
    $summary = trim((string) ($request['duration_label'] ?? ''));
    $time = trim((string) ($request['preferred_date'] ?? '')) . ' ' . trim((string) ($request['preferred_time'] ?? ''));
    $city = trim((string) ($request['city'] ?? ''));
    $bodyParts = [];
    if ($summary !== '') {
        $bodyParts[] = $summary;
    }
    if (trim($time) !== '') {
        $bodyParts[] = trim($time);
    }
    if ($city !== '') {
        $bodyParts[] = $city;
    }
    $body = $bodyParts ? implode(' - ', $bodyParts) : 'Open the admin panel to review.';
    $data = [
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
        'contact_followup' => (string) ($request['contact_followup'] ?? ''),
    ];
    return send_push_to_tokens($tokens, $title, $body, $data);
}
