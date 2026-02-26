<?php
declare(strict_types=1);

$configPath = __DIR__ . '/../api/config.php';
if (!file_exists($configPath)) {
    $alternatePath = __DIR__ . '/../../api/config.php';
    if (file_exists($alternatePath)) {
        $configPath = $alternatePath;
    }
}

if (!file_exists($configPath)) {
    http_response_code(500);
    echo 'Admin config missing.';
    exit;
}

require_once $configPath;

const ADMIN_RATE_LIMIT_MAX = 3;
const ADMIN_RATE_LIMIT_WINDOW = 900;
const ADMIN_RATE_LIMIT_BLOCK = 1800;
const ADMIN_RATE_LIMIT_FILE = DATA_DIR . '/admin_rate_limit.json';
const ADMIN_EMPLOYEE_FILE = DATA_DIR . '/admin_employees.json';

function normalize_admin_username(string $value): string
{
    return strtolower(trim($value));
}

function read_admin_employees(): array
{
    $store = read_json_file(ADMIN_EMPLOYEE_FILE, ['employees' => []]);
    $employees = $store['employees'] ?? [];
    if (!is_array($employees)) {
        return [];
    }
    $clean = [];
    foreach ($employees as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $username = normalize_admin_username((string) ($entry['username'] ?? ''));
        $hash = (string) ($entry['password_hash'] ?? '');
        if ($username === '' || $hash === '') {
            continue;
        }
        $clean[] = [
            'username' => $username,
            'password_hash' => $hash,
        ];
    }
    return $clean;
}

function admin_get_client_ip(): string
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

function load_admin_rate_state(): array
{
    return read_json_file(ADMIN_RATE_LIMIT_FILE, ['ips' => []]);
}

function normalize_admin_rate_record(array $record, int $now): array
{
    $count = (int) ($record['count'] ?? 0);
    $first = (int) ($record['first'] ?? $now);
    $blockedUntil = (int) ($record['blocked_until'] ?? 0);
    $strikes = (int) ($record['strikes'] ?? 0);
    if ($blockedUntil < 0) {
        $blockedUntil = 0;
    }
    if ($blockedUntil > $now) {
        return ['count' => $count, 'first' => $first, 'blocked_until' => $blockedUntil, 'strikes' => $strikes];
    }
    if (($now - $first) > ADMIN_RATE_LIMIT_WINDOW) {
        return ['count' => 0, 'first' => $now, 'blocked_until' => 0, 'strikes' => $strikes];
    }
    return ['count' => $count, 'first' => $first, 'blocked_until' => $blockedUntil, 'strikes' => $strikes];
}

function record_admin_failure(array $state, string $ip, array $record, int $now): void
{
    $count = (int) ($record['count'] ?? 0) + 1;
    $first = (int) ($record['first'] ?? $now);
    $blockedUntil = max(0, (int) ($record['blocked_until'] ?? 0));
    $strikes = (int) ($record['strikes'] ?? 0);
    if (($now - $first) > ADMIN_RATE_LIMIT_WINDOW) {
        $count = 1;
        $first = $now;
        $blockedUntil = 0;
    }
    if ($count >= ADMIN_RATE_LIMIT_MAX) {
        $strikes += 1;
        $multiplier = max(1, min($strikes, 8));
        $blockedUntil = $now + (ADMIN_RATE_LIMIT_BLOCK * $multiplier);
        $count = 0;
        $first = $now;
    }
    $state['ips'][$ip] = [
        'count' => $count,
        'first' => $first,
        'blocked_until' => $blockedUntil,
        'strikes' => $strikes,
    ];
    write_json_file(ADMIN_RATE_LIMIT_FILE, $state);
}

function clear_admin_rate_record(array $state, string $ip): void
{
    if (isset($state['ips'][$ip])) {
        unset($state['ips'][$ip]);
        write_json_file(ADMIN_RATE_LIMIT_FILE, $state);
    }
}

function deny_rate_limit(): void
{
    http_response_code(429);
    echo 'Too many attempts. Try again later.';
    exit;
}

function get_basic_auth_credentials(): array
{
    $user = $_SERVER['PHP_AUTH_USER'] ?? '';
    $pass = $_SERVER['PHP_AUTH_PW'] ?? '';
    if ($user !== '' || $pass !== '') {
        return [$user, $pass];
    }
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($header === '' && isset($_SERVER['Authorization'])) {
        $header = (string) $_SERVER['Authorization'];
    }
    if ($header !== '' && stripos($header, 'basic ') === 0) {
        $decoded = base64_decode(substr($header, 6), true);
        if ($decoded !== false) {
            $parts = explode(':', $decoded, 2);
            return [$parts[0] ?? '', $parts[1] ?? ''];
        }
    }
    return ['', ''];
}

function deny_admin_access(): void
{
    header('WWW-Authenticate: Basic realm="Booking Admin"');
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

function require_admin_ui(): array
{
    if (ADMIN_API_KEY === '' || ADMIN_API_KEY === 'change-this-admin-key') {
        http_response_code(500);
        echo 'Admin key not configured.';
        exit;
    }
    $ip = admin_get_client_ip();
    $now = time();
    $state = load_admin_rate_state();
    $record = normalize_admin_rate_record($state['ips'][$ip] ?? [], $now);
    $blockedUntil = (int) ($record['blocked_until'] ?? 0);
    if ($blockedUntil > $now) {
        deny_rate_limit();
    }
    [$user, $pass] = get_basic_auth_credentials();
    $user = normalize_admin_username((string) $user);
    $pass = (string) $pass;
    $expectedUser = defined('ADMIN_UI_USER') && ADMIN_UI_USER !== '' ? ADMIN_UI_USER : 'admin';
    $expectedUser = normalize_admin_username((string) $expectedUser);
    if ($user === '' || $pass === '') {
        deny_admin_access();
    }
    $isEmployerAuth = false;
    if (defined('ADMIN_UI_PASSWORD_HASH') && ADMIN_UI_PASSWORD_HASH !== '') {
        $isEmployerAuth = hash_equals($expectedUser, $user) && password_verify($pass, ADMIN_UI_PASSWORD_HASH);
    } else {
        $isEmployerAuth = hash_equals($expectedUser, $user) && hash_equals(ADMIN_API_KEY, $pass);
    }
    if ($isEmployerAuth) {
        clear_admin_rate_record($state, $ip);
        return ['username' => $user, 'is_employer' => true];
    }

    $employees = read_admin_employees();
    foreach ($employees as $employee) {
        $employeeUser = normalize_admin_username((string) ($employee['username'] ?? ''));
        $employeeHash = (string) ($employee['password_hash'] ?? '');
        if ($employeeUser === '' || $employeeHash === '') {
            continue;
        }
        if (!hash_equals($employeeUser, $user)) {
            continue;
        }
        if (password_verify($pass, $employeeHash)) {
            clear_admin_rate_record($state, $ip);
            return ['username' => $user, 'is_employer' => false];
        }
    }

    record_admin_failure($state, $ip, $record, $now);
    deny_admin_access();
}

$adminSession = require_admin_ui();
$currentAdminUser = (string) ($adminSession['username'] ?? '');
$currentAdminIsEmployer = (bool) ($adminSession['is_employer'] ?? false);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>BombaCLOUD!</title>
    <style>
      :root {
        --bg: #fff5fb;
        --bg-2: #ffe3f2;
        --ink: #12040a;
        --pink: #ff2d93;
        --hot: #ff006e;
        --line: rgba(255, 0, 110, 0.25);
        --shadow: rgba(255, 0, 110, 0.2);
        --body-gradient: radial-gradient(circle at 15% 10%, #ffe1f0 0%, #fff5fb 45%, #fff 100%);
        --mono: "Avenir Next", "Trebuchet MS", "Segoe UI", "Helvetica Neue", Arial, sans-serif;
        --bubble: "Baloo 2", "Cooper Black", "Bookman Old Style", "Georgia", serif;
      }

      * {
        box-sizing: border-box;
      }

      body {
        margin: 0;
        font-family: var(--mono);
        color: var(--ink);
        background: var(--body-gradient);
      }

      .age-language {
        position: static;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        padding: 0;
        border: none;
        background: transparent;
        box-shadow: none;
      }

      .age-language .language-button {
        appearance: none;
        -webkit-appearance: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 2px 4px;
        border: 0 !important;
        border-radius: 0;
        background: transparent !important;
        box-shadow: none !important;
        color: #2a0d1a;
        cursor: pointer;
        letter-spacing: 1px;
        text-transform: uppercase;
        font-size: 12px;
        line-height: 1;
        -webkit-tap-highlight-color: transparent;
        transition: transform 0.12s ease, opacity 0.12s ease;
      }

      .age-language .language-button[aria-pressed="true"] {
        opacity: 1;
        text-decoration: underline;
        text-underline-offset: 3px;
      }

      .age-language .language-button:focus-visible,
      .age-language .language-button:hover {
        transform: translateY(-1px);
        outline: none;
        opacity: 0.9;
      }

      .age-language .language-flag {
        display: inline-flex;
        width: 20px;
        height: 14px;
      }

      .age-language .language-flag svg {
        width: 100%;
        height: auto;
        display: block;
      }

      .account-language-switch {
        gap: 14px;
        margin-top: 10px;
        margin-bottom: 14px;
      }

      .account-language-switch .language-button {
        padding: 4px 8px;
      }

      .account-center-actions {
        margin-top: 8px;
        gap: 14px;
      }

      #accountAccentColor {
        width: 100%;
        height: 48px;
        padding: 4px;
        border-radius: 12px;
        border: 1px solid var(--line);
        background: #fff;
        cursor: pointer;
      }

      #accountAccentColor::-webkit-color-swatch-wrapper {
        padding: 0;
      }

      #accountAccentColor::-webkit-color-swatch {
        border: none;
        border-radius: 8px;
      }

      #accountAccentColor::-moz-color-swatch {
        border: none;
        border-radius: 8px;
      }

      header {
        max-width: 1100px;
        margin: 0 auto;
        padding: 48px 24px 24px 24px;
      }

      .header-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
      }

      .header-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-left: auto;
        justify-content: flex-end;
      }

      .icon-menu-btn {
        width: 46px;
        height: 46px;
        border-radius: 12px;
        border: 1px solid var(--line);
        background: #fff;
        color: #7a1c45;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 10px 20px var(--shadow);
      }

      .icon-menu-btn:focus-visible,
      .icon-menu-btn:hover {
        border-color: var(--hot);
        outline: none;
      }

      .icon-menu-btn .line-stack {
        display: grid;
        gap: 4px;
      }

      .icon-menu-btn .line-stack span {
        width: 16px;
        height: 2px;
        border-radius: 999px;
        background: currentColor;
        display: block;
      }

      .icon-menu-btn .notif-icon {
        font-size: 20px;
        line-height: 1;
        display: inline-block;
        transform: translateY(-1px);
      }

      .notif-badge {
        min-width: 18px;
        height: 18px;
        border-radius: 999px;
        background: var(--hot);
        color: #fff;
        font-size: 11px;
        line-height: 18px;
        text-align: center;
        padding: 0 5px;
        margin-left: 6px;
      }

      .notif-panel {
        position: absolute;
        top: 56px;
        right: 0;
        width: min(360px, calc(100vw - 32px));
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 14px;
        box-shadow: 0 20px 40px var(--shadow);
        padding: 10px;
        z-index: 60;
      }

      .notif-title {
        margin: 0 0 8px;
        font-size: 0.8rem;
        letter-spacing: 0.12em;
        color: #7a1c45;
        text-transform: uppercase;
      }

      .notif-list {
        display: grid;
        gap: 8px;
        max-height: 300px;
        overflow: auto;
      }

      .notif-item {
        width: 100%;
        text-align: left;
        border: 1px solid var(--line);
        border-radius: 10px;
        background: #fff7fc;
        padding: 8px 10px;
        cursor: pointer;
        color: #5c1738;
      }

      .notif-item:hover,
      .notif-item:focus-visible {
        background: #ffeef8;
        outline: none;
      }

      .notif-empty {
        margin: 0;
        color: #7b4b61;
        font-size: 0.86rem;
      }

      .header-anchor {
        position: relative;
      }

      .admin-menu-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(19, 4, 12, 0.36);
        z-index: 150;
      }

      .admin-menu-drawer {
        position: fixed;
        top: 0;
        right: 0;
        height: 100%;
        width: min(520px, 94vw);
        background: #fff;
        border-left: 1px solid var(--line);
        box-shadow: -24px 0 48px var(--shadow);
        z-index: 160;
        overflow: auto;
        transform: translateX(100%);
        transition: transform 0.2s ease;
        padding: 16px;
      }

      .admin-menu-drawer.open {
        transform: translateX(0);
      }

      .admin-menu-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 14px;
      }

      .admin-menu-title {
        margin: 0;
        font-size: 1rem;
        letter-spacing: 0.16em;
        text-transform: uppercase;
        color: var(--hot);
      }

      .menu-group {
        border: 1px solid var(--line);
        border-radius: 14px;
        padding: 14px;
        margin-bottom: 12px;
        background: #fff9fd;
        display: grid;
        gap: 12px;
      }

      .menu-section-list {
        display: grid;
        gap: 12px;
        margin-bottom: 14px;
      }

      .menu-section-btn {
        width: 100%;
        text-align: left;
        border: 1px solid var(--line);
        background: #fff;
        border-radius: 12px;
        color: #6f1a41;
        padding: 12px 14px;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        font-weight: 700;
        cursor: pointer;
      }

      .menu-section-btn:hover,
      .menu-section-btn:focus-visible {
        border-color: var(--hot);
        outline: none;
      }

      .menu-page-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 0;
      }

      .menu-page-head h3 {
        margin: 0;
      }

      .menu-group h3 {
        margin: 0 0 10px;
        font-size: 0.84rem;
        letter-spacing: 0.15em;
        text-transform: uppercase;
        color: #7a1c45;
      }

      .menu-inline-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
      }

      .menu-day-choices {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
      }

      .menu-day-choices label {
        margin: 0;
        font-size: 0.78rem;
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 999px;
        padding: 6px 8px;
      }

      .menu-list {
        display: grid;
        gap: 8px;
      }

      .menu-list-row {
        display: grid;
        grid-template-columns: 1fr minmax(88px, 120px) auto;
        gap: 8px;
      }

      .menu-list-row input {
        min-width: 0;
      }

      .menu-list-row .btn {
        padding: 8px 10px;
      }

      .menu-avatar-preview {
        width: 74px;
        height: 74px;
        border-radius: 14px;
        border: 1px solid var(--line);
        object-fit: cover;
        background: #fff;
        display: block;
      }

      .legacy-admin-section {
        display: none;
      }

      h1 {
        font-family: var(--bubble);
        color: var(--hot);
        margin: 0 0 8px;
        font-size: clamp(2.2rem, 4vw, 3.4rem);
      }

      .subtitle {
        margin: 0;
        color: #55122b;
        font-size: 1rem;
      }

      main {
        max-width: 1100px;
        margin: 0 auto;
        padding: 0 24px 64px;
      }

      .folder-switcher {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        margin: 0 0 18px;
      }

      .folder-button {
        border: 1px solid var(--line);
        background: #fff;
        color: #7a1c45;
        border-radius: 14px 14px 8px 8px;
        padding: 10px 14px;
        min-width: 0;
        width: 100%;
        font-size: 0.86rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        font-weight: 700;
        cursor: pointer;
        transition: transform 0.12s ease, box-shadow 0.12s ease, border-color 0.12s ease;
      }

      .folder-button[aria-pressed="true"] {
        border-color: var(--hot);
        color: var(--hot);
        box-shadow: 0 10px 24px var(--shadow);
        transform: translateY(-1px);
      }

      .admin-panel-hidden {
        display: none !important;
      }

      section {
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 22px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 16px 32px var(--shadow);
      }

      h2 {
        margin: 0 0 12px;
        font-size: 1rem;
        letter-spacing: 0.2em;
        text-transform: uppercase;
        color: var(--hot);
      }

      label {
        display: block;
        font-size: 0.88rem;
        text-transform: none;
        letter-spacing: 0.02em;
        margin-bottom: 6px;
        color: #6b173f;
        font-weight: 600;
      }

      input,
      select,
      button,
      textarea {
        font-family: var(--mono);
      }

      input,
      select,
      textarea {
        width: 100%;
        padding: 12px 14px;
        border-radius: 12px;
        border: 1px solid var(--line);
        background: #fff;
        color: #2a0d1a;
        font-size: 0.98rem;
      }

      select,
      option {
        color: #2a0d1a !important;
        background: #fff !important;
      }

      input[type="date"],
      input[type="time"],
      input[type="month"],
      input[type="datetime-local"],
      input[type="number"] {
        color: #2a0d1a !important;
        -webkit-text-fill-color: #2a0d1a !important;
        background: #fff !important;
        color-scheme: light;
        opacity: 1;
      }

      input[type="date"]::-webkit-datetime-edit,
      input[type="time"]::-webkit-datetime-edit,
      input[type="month"]::-webkit-datetime-edit,
      input[type="datetime-local"]::-webkit-datetime-edit {
        color: #2a0d1a;
      }

      input[type="date"]::-webkit-date-and-time-value,
      input[type="time"]::-webkit-date-and-time-value,
      input[type="month"]::-webkit-date-and-time-value,
      input[type="datetime-local"]::-webkit-date-and-time-value {
        color: #2a0d1a;
      }

      input[type="date"]::-webkit-calendar-picker-indicator,
      input[type="month"]::-webkit-calendar-picker-indicator,
      input[type="datetime-local"]::-webkit-calendar-picker-indicator {
        opacity: 1;
        filter: none;
      }

      .tour-row input[type="date"] {
        color: #111 !important;
        -webkit-text-fill-color: #111 !important;
        caret-color: #111;
        background: #fff !important;
        color-scheme: light;
      }

      .tour-row input[type="date"]::-webkit-datetime-edit,
      .tour-row input[type="date"]::-webkit-datetime-edit-text,
      .tour-row input[type="date"]::-webkit-datetime-edit-month-field,
      .tour-row input[type="date"]::-webkit-datetime-edit-day-field,
      .tour-row input[type="date"]::-webkit-datetime-edit-year-field {
        color: #111 !important;
        -webkit-text-fill-color: #111 !important;
      }

      .grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
      }

      .editor-list {
        display: grid;
        gap: 12px;
      }

      .editor-row {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr)) auto;
        gap: 12px;
        align-items: start;
      }

      .tour-row {
        grid-template-columns: repeat(2, minmax(0, 1fr)) auto;
        grid-template-areas:
          "start end remove"
          "city city remove";
        align-items: end;
      }

      .tour-row .tour-start {
        grid-area: start;
      }

      .tour-row .tour-end {
        grid-area: end;
      }

      .tour-row .tour-city {
        grid-area: city;
      }

      .tour-row .tour-remove {
        grid-area: remove;
        align-self: end;
      }

      .quick-add-grid {
        display: grid;
        grid-template-columns: 160px minmax(170px, 1fr) minmax(150px, 1fr) minmax(150px, 1fr);
        gap: 12px;
        align-items: end;
      }

      .quick-add-notes {
        grid-column: 1 / -1;
      }

      .editor-row.gallery {
        grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) minmax(140px, 180px) auto;
      }

      .gallery-preview {
        width: 100%;
        max-width: 180px;
        min-height: 120px;
        border-radius: 12px;
        border: 1px solid rgba(255, 0, 110, 0.2);
        overflow: hidden;
        background: #fff5fb;
        display: flex;
        align-items: center;
        justify-content: center;
      }

      .gallery-thumb {
        width: 100%;
        height: 100%;
        object-fit: cover;
      }

      .gallery-thumb.broken {
        object-fit: contain;
        opacity: 0.4;
      }

      .span-2 {
        grid-column: 1 / -1;
      }

      .row {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: center;
      }

      #recurringDays label {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.8rem;
        color: #5f173a;
      }

      .btn {
        padding: 10px 16px;
        border-radius: 999px;
        border: none;
        background: linear-gradient(135deg, var(--pink), var(--hot));
        color: #fff;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        font-size: 0.8rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
      }

      .btn.secondary {
        background: #fff;
        color: var(--hot);
        border: 1px solid var(--hot);
      }

      .btn.ghost {
        background: transparent;
        border: 1px dashed var(--hot);
        color: var(--hot);
      }

      .status {
        font-size: 0.85rem;
        color: #7a1c45;
      }

      .request-card {
        border: 1px solid var(--line);
        border-radius: 18px;
        padding: 16px;
        margin-bottom: 16px;
        background: #fffafb;
      }

      .request-card.flash-focus {
        border-color: rgba(255, 0, 110, 0.68);
        box-shadow: 0 0 0 3px rgba(255, 0, 110, 0.22);
      }

      .request-header {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: center;
        justify-content: space-between;
      }

      .request-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
      }

      .badge {
        padding: 6px 10px;
        border-radius: 999px;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.12em;
        border: 1px solid var(--line);
        background: #fff;
      }

      .badge.pending {
        color: #b53a6b;
      }

      .badge.accepted {
        color: #b8741a;
        border-color: rgba(184, 116, 26, 0.35);
        background: #fff3e3;
      }

      .badge.maybe {
        color: #6a3ab8;
        border-color: rgba(106, 58, 184, 0.34);
        background: #efe7ff;
      }

      .badge.declined,
      .badge.cancelled {
        color: #a33434;
      }

      .badge.paid {
        color: #1a7f4f;
        border-color: rgba(26, 127, 79, 0.35);
        background: #e6f6ed;
      }

      .badge.blacklisted {
        color: #fff;
        border-color: rgba(88, 0, 18, 0.65);
        background: linear-gradient(135deg, #7a0018, #3b0010);
      }

      .meta {
        font-size: 0.85rem;
        color: #5f173a;
        margin: 6px 0 0;
      }

      .actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 12px;
      }

      .status-action-row {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 12px;
        align-items: center;
      }

      .status-action-select {
        flex: 1 1 220px;
        min-width: 180px;
      }

      .status-action-apply {
        min-width: 84px;
      }

      .request-edit-panel {
        margin-top: 12px;
        padding: 12px;
        border: 1px solid var(--line);
        border-radius: 12px;
        background: #fff;
      }

      .request-edit-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 10px;
      }

      .request-edit-field {
        display: flex;
        flex-direction: column;
        gap: 6px;
      }

      .request-edit-field label {
        font-size: 0.78rem;
        color: #5f173a;
        letter-spacing: 0.04em;
        text-transform: uppercase;
      }

      .request-edit-field input,
      .request-edit-field select,
      .request-edit-field textarea {
        border: 1px solid rgba(255, 0, 110, 0.3);
        border-radius: 10px;
        padding: 8px 10px;
        font-size: 0.82rem;
        font-family: inherit;
      }

      .request-edit-field textarea {
        min-height: 78px;
        resize: vertical;
      }

      .hint {
        font-size: 0.8rem;
        color: #7a1c45;
      }

      .hidden {
        display: none;
      }

      .calendar {
        border: 1px solid var(--line);
        border-radius: 16px;
        background: #fffafb;
        padding: 12px;
        overflow: auto;
        max-height: 520px;
      }

      .calendar-grid {
        display: grid;
        gap: 4px;
        min-width: 720px;
      }

      .calendar-cell {
        padding: 6px 8px;
        border-radius: 8px;
        border: 1px solid rgba(255, 0, 110, 0.12);
        background: #fff;
        font-size: 0.72rem;
        text-align: center;
        color: #57122c;
      }

      .calendar-head {
        background: #ffe6f3;
        color: #b01d63;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
      }

      .calendar-time {
        background: #fff5fb;
        font-weight: 600;
      }

      .calendar-slot {
        cursor: pointer;
        transition: background 0.15s ease, transform 0.15s ease;
        min-height: 26px;
        position: relative;
        overflow: hidden;
        text-align: left;
        padding-right: 44px;
        padding-top: 14px;
      }

      .calendar-slot:hover {
        background: rgba(255, 0, 110, 0.08);
        transform: translateY(-1px);
      }

      .calendar-slot::before {
        content: attr(data-date-short);
        position: absolute;
        right: 6px;
        top: 2px;
        font-size: 0.54rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        color: rgba(86, 18, 45, 0.26);
        text-shadow: 0 1px 0 rgba(255, 255, 255, 0.68);
        pointer-events: none;
      }

      .calendar-slot::after {
        content: attr(data-time);
        position: absolute;
        right: 6px;
        bottom: 3px;
        font-size: 0.6rem;
        font-weight: 700;
        letter-spacing: 0.02em;
        color: rgba(86, 18, 45, 0.32);
        text-shadow: 0 1px 0 rgba(255, 255, 255, 0.72);
        pointer-events: none;
      }

      .slot-city-dot {
        position: absolute;
        left: 6px;
        top: 4px;
        width: 9px;
        height: 9px;
        border-radius: 999px;
        background: var(--city-color, rgba(180, 154, 177, 0.4));
        border: 1px solid rgba(86, 18, 45, 0.12);
        box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.55);
        pointer-events: none;
      }

      .calendar-head-city {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
      }

      .calendar-head-city::before {
        content: "";
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 999px;
        background: var(--city-color, rgba(180, 154, 177, 0.5));
        border: 1px solid rgba(86, 18, 45, 0.14);
        box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.5);
      }

      .calendar-slot.blocked {
        background: linear-gradient(135deg, rgba(255, 45, 147, 0.9), rgba(255, 0, 110, 0.9));
        color: #fff;
        border-color: rgba(255, 0, 110, 0.5);
      }

      .calendar-slot.blocked::after {
        color: rgba(255, 255, 255, 0.56);
        text-shadow: 0 1px 0 rgba(139, 0, 59, 0.34);
      }

      .calendar-slot.blocked::before {
        color: rgba(255, 255, 255, 0.46);
        text-shadow: 0 1px 0 rgba(139, 0, 59, 0.34);
      }

      .calendar-slot.recurring {
        background: #ffeef6;
        border-style: dashed;
      }

      .calendar-slot.booking {
        background: #ffe7c2;
        color: #6b3a00;
        border-color: rgba(184, 116, 26, 0.35);
        font-weight: 600;
      }

      .calendar-slot.booking::after {
        color: rgba(107, 58, 0, 0.45);
        text-shadow: 0 1px 0 rgba(255, 248, 233, 0.7);
      }

      .calendar-slot.booking::before {
        color: rgba(107, 58, 0, 0.36);
        text-shadow: 0 1px 0 rgba(255, 248, 233, 0.7);
      }

      .calendar-slot.booking.outcall {
        background: #dbe7ff;
        color: #23345a;
        border-color: rgba(35, 52, 90, 0.25);
      }

      .calendar-slot.booking.outcall::after {
        color: rgba(35, 52, 90, 0.42);
        text-shadow: 0 1px 0 rgba(240, 246, 255, 0.72);
      }

      .calendar-slot.booking.outcall::before {
        color: rgba(35, 52, 90, 0.34);
        text-shadow: 0 1px 0 rgba(240, 246, 255, 0.72);
      }

      .calendar-slot.booking.paid {
        box-shadow: inset 0 0 0 2px rgba(26, 127, 79, 0.35);
      }

      .calendar-slot.maybe {
        box-shadow: inset 0 0 0 1px rgba(106, 58, 184, 0.28);
      }

      .slot-maybe-count {
        position: absolute;
        left: 4px;
        bottom: 2px;
        min-width: 16px;
        height: 14px;
        border-radius: 999px;
        padding: 0 4px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.56rem;
        font-weight: 700;
        letter-spacing: 0.02em;
        color: #fff;
        background: linear-gradient(135deg, #7e4cff, #5f2dc6);
        border: 1px solid rgba(54, 17, 124, 0.35);
        pointer-events: none;
        box-shadow: 0 1px 0 rgba(255, 255, 255, 0.42);
      }

      .calendar-slot.slot-grouped {
        border-radius: 0;
      }

      .calendar-slot.slot-group-start {
        border-top-left-radius: 8px;
        border-top-right-radius: 8px;
      }

      .calendar-slot.slot-group-end {
        border-bottom-left-radius: 8px;
        border-bottom-right-radius: 8px;
      }

      .calendar-slot.slot-group-middle,
      .calendar-slot.slot-group-end {
        margin-top: -4px;
        border-top-color: transparent;
      }

      .calendar-slot.slot-group-middle::before,
      .calendar-slot.slot-group-middle::after,
      .calendar-slot.slot-group-end::before,
      .calendar-slot.slot-group-end::after {
        opacity: 0;
      }

      .calendar-legend {
        display: flex;
        gap: 12px;
        align-items: center;
        font-size: 0.8rem;
        color: #6b173f;
      }

      .calendar-controls {
        flex-wrap: wrap;
        gap: 12px;
      }

      .segmented {
        display: inline-flex;
        border: 1px solid var(--line);
        border-radius: 999px;
        overflow: hidden;
        background: #fff;
      }

      .seg-btn {
        border: none;
        padding: 8px 16px;
        background: transparent;
        font-size: 0.85rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #6b173f;
        cursor: pointer;
      }

      .seg-btn[aria-pressed="true"] {
        background: linear-gradient(135deg, #ff2d93, #ff006e);
        color: #fff;
      }

      .calendar-field {
        min-width: 200px;
      }

      .calendar-field[hidden] {
        display: none;
      }

      .calendar-grid.month {
        grid-template-columns: repeat(7, minmax(90px, 1fr));
      }

      .calendar-grid.month .month-cell {
        border: 1px solid rgba(255, 0, 110, 0.18);
        border-radius: 12px;
        padding: 10px;
        min-height: 90px;
        display: flex;
        flex-direction: column;
        gap: 6px;
      }

      .month-cell.muted {
        opacity: 0.35;
      }

      .month-day {
        font-weight: 700;
        color: #55122b;
      }

      .month-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
      }

      .month-badge {
        font-size: 0.7rem;
        padding: 2px 6px;
        border-radius: 999px;
        border: 1px solid rgba(255, 0, 110, 0.28);
        background: rgba(255, 0, 110, 0.08);
      }

      .month-badge.booking {
        background: #ffe7c2;
        border-color: rgba(184, 116, 26, 0.35);
      }

      .month-badge.paid {
        background: #e6f6ed;
        border-color: rgba(26, 127, 79, 0.35);
      }

      .month-badge.maybe {
        background: #efe7ff;
        border-color: rgba(106, 58, 184, 0.34);
      }

      .month-badge.blocked {
        background: linear-gradient(135deg, #ff2d93, #ff006e);
        border-color: rgba(255, 0, 110, 0.6);
        color: #fff;
      }

      .month-city-dots {
        margin-top: 4px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
      }

      .month-city-dot {
        width: 7px;
        height: 7px;
        border-radius: 999px;
        border: 1px solid rgba(86, 18, 45, 0.14);
        background: var(--city-color, rgba(180, 154, 177, 0.4));
      }

      .legend-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: rgba(255, 0, 110, 0.15);
        border: 1px solid rgba(255, 0, 110, 0.35);
      }

      .legend-dot.blocked {
        background: linear-gradient(135deg, #ff2d93, #ff006e);
        border-color: rgba(255, 0, 110, 0.6);
      }

      .legend-dot.booking {
        background: #ffe7c2;
        border-color: rgba(184, 116, 26, 0.35);
      }

      .legend-dot.outcall {
        background: #dbe7ff;
        border-color: rgba(35, 52, 90, 0.25);
      }

      .legend-dot.paid {
        background: #e6f6ed;
        border-color: rgba(26, 127, 79, 0.35);
      }

      .legend-dot.maybe {
        background: #efe7ff;
        border-color: rgba(106, 58, 184, 0.34);
      }

      .legend-dot.city {
        background: rgba(180, 154, 177, 0.45);
        border: 1px solid rgba(86, 18, 45, 0.18);
      }

      .city-wizard-card {
        border: 1px solid rgba(255, 0, 110, 0.24);
        border-radius: 20px;
        background: linear-gradient(180deg, #fff8fc 0%, #fff 100%);
        padding: 18px;
        box-shadow: 0 14px 26px rgba(255, 0, 110, 0.12);
      }

      .city-wizard-head {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 10px;
      }

      .city-wizard-title {
        margin: 0;
        color: #5f173a;
        font-size: 1.08rem;
        letter-spacing: 0.01em;
        text-transform: none;
      }

      .city-wizard-step {
        font-size: 0.78rem;
        letter-spacing: 0.04em;
        color: #fff;
        text-transform: uppercase;
        background: linear-gradient(135deg, #ff2d93, #ff006e);
        border-radius: 999px;
        padding: 6px 10px;
        font-weight: 700;
      }

      .city-wizard-date {
        color: #7a1c45;
        font-size: 0.9rem;
      }

      .city-wizard-question {
        font-size: 1rem;
        color: #5a1431;
        margin: 0 0 6px;
        font-weight: 700;
      }

      .city-wizard-zone {
        color: #7a1c45;
        font-size: 0.82rem;
        margin-top: 2px;
      }

      .city-wizard-body {
        display: grid;
        gap: 10px;
      }

      .city-wizard-row {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
      }

      .city-wizard-nav {
        display: flex;
        gap: 10px;
        margin-top: 8px;
      }

      .city-wizard-nav .btn {
        min-width: 110px;
      }

      .city-day-picks {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 6px;
      }

      .city-day-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 10px;
        border: 1px solid rgba(255, 0, 110, 0.28);
        border-radius: 999px;
        background: #fff;
        color: #5f173a;
        font-size: 0.82rem;
      }

      .city-toolbar {
        align-items: flex-end;
      }

      .city-toolbar .field {
        min-width: 220px;
      }

      .city-wizard-hidden {
        display: none !important;
      }

      .calendar-slot.template {
        background: #ffeef6;
        color: #7a1c45;
        border-style: dashed;
      }

      @media (max-width: 900px) {
        .grid {
          grid-template-columns: 1fr;
        }

        .header-top {
          flex-direction: column;
          align-items: stretch;
        }

        header {
          padding-right: 24px;
        }

        .header-actions {
          align-self: flex-end;
        }

        .tour-row {
          grid-template-columns: 1fr;
          grid-template-areas:
            "start"
            "end"
            "city"
            "remove";
          align-items: start;
        }

        .tour-row .tour-remove {
          justify-self: start;
        }

        .quick-add-grid {
          grid-template-columns: 1fr;
        }

        .city-wizard-row {
          grid-template-columns: 1fr;
        }

        .editor-row.gallery {
          grid-template-columns: 1fr;
        }

        .gallery-preview {
          max-width: 100%;
        }
      }

      @media (max-width: 640px) {
        body {
          overflow-x: hidden;
          -webkit-text-size-adjust: 100%;
        }

        header {
          padding: 44px 14px 14px;
        }

        main {
          padding: 0 14px 42px;
        }

        section {
          padding: 14px;
          border-radius: 16px;
        }

        .age-language .language-button {
          font-size: 11px;
          gap: 4px;
          padding: 1px 2px;
        }

        .account-language-switch {
          gap: 10px;
          margin-bottom: 12px;
        }

        .account-language-switch .language-button {
          padding: 3px 6px;
        }

        .header-actions {
          width: 100%;
          justify-content: flex-end;
        }

        .admin-menu-drawer {
          width: 100vw;
          max-width: 100vw;
        }

        .menu-inline-grid,
        .menu-list-row {
          grid-template-columns: 1fr;
        }

        .folder-switcher {
          gap: 8px;
        }

        .folder-button {
          border-radius: 12px;
          padding: 9px 12px;
          font-size: 0.76rem;
          letter-spacing: 0.05em;
        }

        .row {
          align-items: stretch;
        }

        .row .btn,
        .row .status {
          width: 100%;
        }

        .city-toolbar .field,
        .calendar-field {
          min-width: 0;
          width: 100%;
          flex: 1 1 100%;
        }

        .calendar-controls {
          align-items: stretch;
        }

        .segmented {
          width: 100%;
          display: grid;
          grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .seg-btn {
          width: 100%;
          padding: 8px 6px;
          font-size: 0.76rem;
        }

        .calendar {
          padding: 8px;
          -webkit-overflow-scrolling: touch;
        }

        .calendar-grid {
          min-width: 560px;
        }

        .calendar-grid.month {
          grid-template-columns: repeat(7, minmax(72px, 1fr));
        }

        .city-wizard-card {
          padding: 14px;
          border-radius: 16px;
        }

        .city-wizard-nav {
          flex-wrap: wrap;
        }

        .city-wizard-nav .btn {
          min-width: 0;
          flex: 1 1 calc(50% - 6px);
        }
      }

      @supports (-webkit-touch-callout: none) {
        input,
        select,
        textarea {
          font-size: 16px;
        }
      }
    </style>
  </head>
  <body>
    <div id="adminMenuBackdrop" class="admin-menu-backdrop" hidden></div>
    <header>
      <div class="header-top">
        <h1 id="adminMainTitle">BombaCLOUD!</h1>
        <div class="header-actions header-anchor">
          <button class="icon-menu-btn" id="notifToggleBtn" type="button" aria-label="Notifications">
            <span class="notif-icon" aria-hidden="true">&#128276;</span>
            <span id="notifUnreadCount" class="notif-badge hidden">0</span>
          </button>
          <div id="notifPanel" class="notif-panel hidden">
            <h3 class="notif-title">New bookings</h3>
            <div id="notifList" class="notif-list"></div>
          </div>
          <a class="btn secondary" id="openClientApp" href="../index.html" target="_blank" rel="noopener">Client app</a>
          <button class="icon-menu-btn" id="adminMenuToggleBtn" type="button" aria-label="Open admin menu">
            <span class="line-stack" aria-hidden="true"><span></span><span></span><span></span></span>
          </button>
        </div>
      </div>
      <p class="subtitle" id="adminSubtitle">Simple city-by-city setup. Fill each city card, save, then manage requests.</p>
    </header>

    <aside id="adminMenuDrawer" class="admin-menu-drawer" aria-hidden="true">
      <div class="admin-menu-head">
        <h2 class="admin-menu-title">Admin menu</h2>
        <button class="btn ghost" id="adminMenuCloseBtn" type="button">Close</button>
      </div>

      <div id="adminMenuSectionList" class="menu-section-list">
        <button class="menu-section-btn" type="button" data-menu-open="account">Account center</button>
        <button class="menu-section-btn" type="button" data-menu-open="schedule">Schedule</button>
        <button class="menu-section-btn" type="button" data-menu-open="touring">Touring</button>
        <button class="menu-section-btn" type="button" data-menu-open="services">Services</button>
        <button class="menu-section-btn" type="button" data-menu-open="photos">Photos</button>
        <?php if ($currentAdminIsEmployer): ?>
        <button class="menu-section-btn" type="button" data-menu-open="employees">Employees</button>
        <?php endif; ?>
      </div>

      <section class="menu-group hidden" data-menu-page="account" data-menu-title="Account center">
        <div class="menu-page-head">
          <button class="btn ghost" type="button" data-menu-back>Back</button>
          <h3>Account center</h3>
        </div>
        <div class="menu-inline-grid">
          <div class="field">
            <label for="accountPhoto">Upload profile pic</label>
            <input id="accountPhoto" type="file" accept="image/*" />
            <img id="accountPhotoPreview" class="menu-avatar-preview" alt="Profile preview" hidden />
          </div>
          <div class="field">
            <label for="accountName">Name</label>
            <input id="accountName" type="text" placeholder="Your name" />
          </div>
          <div class="field">
            <label for="accountEmail">Email</label>
            <input id="accountEmail" type="email" placeholder="you@email.com" />
          </div>
          <div class="field">
            <label for="accountPhone">Phone number</label>
            <input id="accountPhone" type="tel" placeholder="+1..." />
          </div>
          <div class="field">
            <label for="accountPassword">Change password</label>
            <input id="accountPassword" type="password" placeholder="New password" />
          </div>
          <div class="field">
            <label for="accountLanguage">Language preference</label>
            <select id="accountLanguage">
              <option value="en">English</option>
              <option value="fr">Francais</option>
            </select>
          </div>
          <div class="field">
            <label for="accountAccentColor">Color theme</label>
            <input id="accountAccentColor" type="color" value="#ff006e" />
          </div>
        </div>
        <div class="age-language account-language-switch" role="group" aria-label="language selector">
          <button type="button" class="language-button" data-language-choice="en" aria-pressed="false">
            <span class="language-flag" aria-hidden="true">
              <svg viewBox="0 0 24 16" role="presentation" focusable="false">
                <rect width="24" height="16" fill="#012169"></rect>
                <path
                  d="M0 0L9.5 6.5V0H14.5V6.5L24 0V3L15.5 8H24V8.5H15.5L24 13V16L14.5 9.5V16H9.5V9.5L0 16V13L8.5 8.5H0V8H8.5L0 3Z"
                  fill="#fff"
                ></path>
                <path
                  d="M0 0L10 6.5V0H14V6.5L24 0V2.5L13.5 8.5H24V9.5H13.5L24 15.5V16L14 9.5V16H10V9.5L0 16V13.5L10.5 9.5H0V8.5H10.5L0 2.5Z"
                  fill="#c8102e"
                  opacity="0.8"
                ></path>
                <path d="M9.5 0H14.5V6H24V10H14.5V16H9.5V10H0V6H9.5Z" fill="#fff"></path>
                <path d="M10.5 0H13.5V6H24V10H13.5V16H10.5V10H0V6H10.5Z" fill="#c8102e"></path>
              </svg>
            </span>
            <span class="language-code">EN</span>
          </button>
          <button type="button" class="language-button" data-language-choice="fr" aria-pressed="false">
            <span class="language-flag" aria-hidden="true">
              <svg viewBox="0 0 24 16" role="presentation" focusable="false">
                <rect width="24" height="16" fill="#fff"></rect>
                <rect width="8" height="16" fill="#002395"></rect>
                <rect width="8" height="16" x="16" fill="#ED2939"></rect>
              </svg>
            </span>
            <span class="language-code">FR</span>
          </button>
        </div>
        <div class="row account-center-actions">
          <button class="btn" id="saveAccountCenter" type="button">Save account center</button>
          <span class="status" id="accountCenterStatus"></span>
        </div>
      </section>

      <section class="menu-group hidden" data-menu-page="schedule" data-menu-title="Schedule">
        <div class="menu-page-head">
          <button class="btn ghost" type="button" data-menu-back>Back</button>
          <h3>Schedule</h3>
        </div>
        <div class="menu-inline-grid">
          <div class="field">
            <label><input id="menuWorkAllDay" type="checkbox" /> Working all day</label>
          </div>
          <div class="field">
            <label for="menuWorkStart">From time</label>
            <input id="menuWorkStart" type="time" value="10:00" />
          </div>
          <div class="field">
            <label for="menuWorkEnd">To time</label>
            <input id="menuWorkEnd" type="time" value="18:00" />
          </div>
        </div>
        <div class="field">
          <label>Which days</label>
          <div id="menuWorkDays" class="menu-day-choices"></div>
        </div>
        <div class="menu-inline-grid">
          <div class="field">
            <label><input id="menuBreakEnabled" type="checkbox" /> Allow breaks</label>
          </div>
          <div class="field">
            <label for="menuBreakStart">Break from</label>
            <input id="menuBreakStart" type="time" value="14:00" />
          </div>
          <div class="field">
            <label for="menuBreakEnd">Break to</label>
            <input id="menuBreakEnd" type="time" value="15:00" />
          </div>
        </div>
        <div class="field">
          <label>Break days</label>
          <div id="menuBreakDays" class="menu-day-choices"></div>
        </div>
        <div class="row">
          <button class="btn" id="menuScheduleApply" type="button">Apply schedule</button>
          <span class="status" id="menuScheduleStatus"></span>
        </div>
      </section>

      <section class="menu-group hidden" data-menu-page="touring" data-menu-title="Touring">
        <div class="menu-page-head">
          <button class="btn ghost" type="button" data-menu-back>Back</button>
          <h3>Touring</h3>
        </div>
        <div class="menu-inline-grid">
          <div class="field">
            <label for="menuTourCity">Add city</label>
            <input id="menuTourCity" type="text" placeholder="Toronto" />
          </div>
          <div class="field">
            <label for="menuTourStart">Which days start</label>
            <input id="menuTourStart" type="date" />
          </div>
          <div class="field">
            <label for="menuTourEnd">Which days end</label>
            <input id="menuTourEnd" type="date" />
          </div>
          <div class="field">
            <label for="menuTourFirstStart">First day starts at</label>
            <input id="menuTourFirstStart" type="time" value="10:00" />
          </div>
          <div class="field">
            <label for="menuTourLastEnd">Last day ends at</label>
            <input id="menuTourLastEnd" type="time" value="18:00" />
          </div>
        </div>
        <div class="row">
          <button class="btn" id="menuTourAddBtn" type="button">Add touring stop</button>
          <span class="status" id="menuTourStatus"></span>
        </div>
        <div class="row">
          <label><input id="menuAutoTemplateBlocks" type="checkbox" /> <span id="menuAutoTemplateBlocksLabel">Auto-block from city rules</span></label>
          <button class="btn ghost" id="menuClearAutoBlocks" type="button">Disable + clear auto blocks</button>
          <span class="status" id="menuAutoBlockStatus"></span>
        </div>
        <p class="hint" id="menuTourScheduleHint">Dates are inclusive. You can edit or remove rows before saving.</p>
        <div class="editor-list" id="menuTourScheduleList"></div>
        <div class="field">
          <label id="menuTourPartnersTitle">Partners</label>
          <p class="hint" id="menuTourPartnersHint">Add friend name and link shown in touring section.</p>
          <div class="editor-list" id="menuTourPartnersList"></div>
        </div>
        <div class="row">
          <button class="btn secondary" id="menuAddTourPartnerRow" type="button">Add partner</button>
          <button class="btn secondary" id="menuAddTourRow" type="button">Add stop</button>
          <button class="btn" id="menuSaveTourSchedule" type="button">Save tour schedule</button>
          <span class="status" id="menuTourScheduleStatus"></span>
        </div>
      </section>

      <section class="menu-group hidden" data-menu-page="services" data-menu-title="Services">
        <div class="menu-page-head">
          <button class="btn ghost" type="button" data-menu-back>Back</button>
          <h3>Services</h3>
        </div>
        <p class="hint">This saves admin service presets so you can organize prices quickly.</p>
        <div class="field">
          <label for="serviceNameInput">Name</label>
          <input id="serviceNameInput" type="text" placeholder="ex: Heidi Van Horny" />
        </div>
        <div class="field">
          <label>Price per duration</label>
          <div id="serviceDurationList" class="menu-list"></div>
          <button class="btn secondary" id="addServiceDuration" type="button">Add duration</button>
        </div>
        <div class="field">
          <label>Service package (comma separated) + price</label>
          <div id="servicePackageList" class="menu-list"></div>
          <button class="btn secondary" id="addServicePackage" type="button">Add package</button>
        </div>
        <div class="field">
          <label>Single add-ons</label>
          <div id="serviceAddonList" class="menu-list"></div>
          <button class="btn secondary" id="addServiceAddon" type="button">Add add-on</button>
        </div>
        <div class="row">
          <button class="btn" id="saveServicesConfig" type="button">Save services</button>
          <span class="status" id="servicesConfigStatus"></span>
        </div>
      </section>

      <section class="menu-group hidden" data-menu-page="photos" data-menu-title="Photos">
        <div class="menu-page-head">
          <button class="btn ghost" type="button" data-menu-back>Back</button>
          <h3>Photos</h3>
        </div>
        <div class="menu-inline-grid">
          <div class="field">
            <label for="photoDisplayMode">Display choice</label>
            <select id="photoDisplayMode">
              <option value="next">Next click</option>
              <option value="album">Album</option>
              <option value="carousel">Moving carousel</option>
            </select>
          </div>
          <div class="field">
            <label for="photoCarouselSeconds">Carousel seconds</label>
            <input id="photoCarouselSeconds" type="number" min="2" max="30" step="1" value="5" />
          </div>
        </div>
        <div class="field">
          <label>Photos list</label>
          <div class="editor-list" id="photoList"></div>
        </div>
        <div class="row">
          <button class="btn secondary" id="addPhotoRow" type="button">Add photo</button>
          <button class="btn" id="savePhotoConfig" type="button">Save photos</button>
          <span class="status" id="photoStatus"></span>
        </div>
      </section>
      <?php if ($currentAdminIsEmployer): ?>
      <section class="menu-group hidden" data-menu-page="employees" data-menu-title="Employees">
        <div class="menu-page-head">
          <button class="btn ghost" type="button" data-menu-back>Back</button>
          <h3>Employees</h3>
        </div>
        <p class="hint">Only employer can add/remove employee logins.</p>
        <div class="menu-inline-grid">
          <div class="field">
            <label for="employeeUsername">Employee username</label>
            <input id="employeeUsername" type="text" placeholder="employee01" />
          </div>
          <div class="field">
            <label for="employeePassword">Employee password</label>
            <input id="employeePassword" type="password" placeholder="Minimum 8 characters" />
          </div>
        </div>
        <div class="row">
          <button class="btn" id="addEmployeeBtn" type="button">Add employee</button>
          <span class="status" id="employeeStatus"></span>
        </div>
        <div id="employeeList" class="editor-list"></div>
      </section>
      <?php endif; ?>
    </aside>

    <main>
      <div class="folder-switcher" role="tablist" aria-label="Admin folders">
        <button class="folder-button" id="panelScheduleBtn" type="button" data-admin-panel="schedule" aria-pressed="true">
          Schedule
        </button>
        <button class="folder-button" id="panelClientsBtn" type="button" data-admin-panel="clients" aria-pressed="false">
          Clients
        </button>
      </div>

      <section data-admin-panel-group="schedule" id="calendarEditorSection">
        <h2>City calendar editor</h2>
        <p class="hint">After wizard Done, you can edit slots here and then switch to Clients.</p>
        <div class="grid" id="calendarMetaControls" hidden>
          <div class="field">
            <label for="tourCity">Current tour city</label>
            <input id="tourCity" type="text" readonly />
          </div>
          <div class="field" id="tourTimezoneField" hidden>
            <label for="tourTimezone">Tour timezone</label>
            <select id="tourTimezone"></select>
          </div>
          <div class="field">
            <label for="bufferMinutes">Default buffer (minutes)</label>
            <input id="bufferMinutes" type="number" min="0" max="240" step="5" />
          </div>
        </div>
        <div class="row calendar-controls">
          <div class="segmented" role="group" aria-label="Calendar view">
            <button class="seg-btn" data-calendar-view="week" aria-pressed="true" type="button">Week</button>
            <button class="seg-btn" data-calendar-view="day" aria-pressed="false" type="button">Day</button>
            <button class="seg-btn" data-calendar-view="month" aria-pressed="false" type="button">Month</button>
          </div>
          <div class="field calendar-field" data-calendar-field="week">
            <label for="calendarStart">Week start</label>
            <input id="calendarStart" type="date" />
          </div>
          <div class="field calendar-field" data-calendar-field="day" hidden>
            <label for="calendarDay">Day</label>
            <input id="calendarDay" type="date" />
          </div>
          <div class="field calendar-field" data-calendar-field="month" hidden>
            <label for="calendarMonth">Month</label>
            <input id="calendarMonth" type="month" />
          </div>
          <button class="btn secondary" id="calendarToday" type="button">Today</button>
        </div>
        <div class="calendar">
          <div class="calendar-grid" id="calendarGrid"></div>
        </div>
        <div class="calendar-legend">
          <span class="legend-dot blocked"></span> <span id="legendBlockedLabel">Blocked</span>
          <span class="legend-dot booking"></span> <span id="legendBookingLabel">Booking</span>
          <span class="legend-dot outcall"></span> <span id="legendOutcallLabel">Outcall</span>
          <span class="legend-dot paid"></span> <span id="legendPaidLabel">Paid</span>
          <span class="legend-dot maybe"></span> <span id="legendMaybeLabel">Maybe</span>
          <span class="legend-dot city"></span> <span id="legendCityLabel">City marker</span>
        </div>

        <div class="grid">
          <div class="field">
            <label for="blockedDate">Block date</label>
            <input id="blockedDate" type="date" />
          </div>
          <div class="field">
            <label for="blockedStart">Start</label>
            <input id="blockedStart" type="time" />
          </div>
          <div class="field">
            <label for="blockedEnd">End</label>
            <input id="blockedEnd" type="time" />
          </div>
          <div class="field span-2">
            <label for="blockedReason">Reason</label>
            <input id="blockedReason" type="text" placeholder="Optional reason" />
          </div>
        </div>
        <div class="row">
          <button class="btn secondary" id="addBlocked" type="button">Block slot</button>
          <button class="btn ghost" id="blockFullDay" type="button">Block full day</button>
          <span class="status" id="blockedStatus"></span>
        </div>
        <div class="grid">
          <div class="field">
            <label for="blockedFullRangeStart">Full-day range start</label>
            <input id="blockedFullRangeStart" type="date" />
          </div>
          <div class="field">
            <label for="blockedFullRangeEnd">Full-day range end</label>
            <input id="blockedFullRangeEnd" type="date" />
          </div>
        </div>
        <div class="row">
          <button class="btn ghost" id="blockFullRange" type="button">Block full range</button>
          <button class="btn ghost" id="toggleBlockedList" type="button">Show blocked slots</button>
        </div>
        <div id="blockedList" class="hidden"></div>

        <div class="grid">
          <div class="field span-2">
            <label>Recurring days</label>
            <div id="recurringDays"></div>
          </div>
          <div class="field">
            <label for="recurringAllDay">All day</label>
            <input id="recurringAllDay" type="checkbox" />
          </div>
          <div class="field">
            <label for="recurringStart">Recurring start</label>
            <input id="recurringStart" type="time" />
          </div>
          <div class="field">
            <label for="recurringEnd">Recurring end</label>
            <input id="recurringEnd" type="time" />
          </div>
          <div class="field span-2">
            <label for="recurringReason">Recurring reason</label>
            <input id="recurringReason" type="text" placeholder="Optional reason" />
          </div>
        </div>
        <div class="row">
          <button class="btn secondary" id="addRecurring" type="button">Add recurring block</button>
          <span class="status" id="recurringStatus"></span>
        </div>
        <div id="recurringList"></div>
      </section>

      <section data-admin-panel-group="schedule" class="legacy-admin-section">
        <h2 id="quickAddTitle">Quick add</h2>
        <p class="hint" id="quickAddHint">One add panel for future tour dates, plus optional full-day calendar blocks.</p>
        <div class="quick-add-grid">
          <div class="field">
            <label for="quickAddType" id="quickAddTypeLabel">Type</label>
            <select id="quickAddType">
              <option value="tour">Tour date (website + admin)</option>
              <option value="block">Block days (admin only)</option>
            </select>
          </div>
          <div class="field">
            <label for="quickAddCity" id="quickAddCityLabel">City</label>
            <input id="quickAddCity" type="text" placeholder="Toronto" />
          </div>
          <div class="field">
            <label for="quickAddStart" id="quickAddStartLabel">Start date</label>
            <input id="quickAddStart" type="date" />
          </div>
          <div class="field">
            <label for="quickAddEnd" id="quickAddEndLabel">End date</label>
            <input id="quickAddEnd" type="date" />
          </div>
          <div class="field quick-add-notes">
            <label for="quickAddNotes" id="quickAddNotesLabel">Notes</label>
            <input id="quickAddNotes" type="text" placeholder="Optional note" />
          </div>
        </div>
        <div class="row">
          <button class="btn" id="quickAddSubmit" type="button">Add</button>
          <span class="status" id="quickAddStatus"></span>
        </div>
      </section>

      <section data-admin-panel-group="schedule" class="legacy-admin-section">
        <h2 id="tourScheduleTitle">Tour schedule</h2>
        <div class="editor-list" id="tourScheduleList"></div>
        <p class="hint" id="tourScheduleHint">Dates are inclusive. Use YYYY-MM-DD. Overlapping city dates are allowed.</p>
        <div class="row">
          <button class="btn secondary" id="addTourRow" type="button">Add stop</button>
          <button class="btn" id="saveTourSchedule" type="button">Save tour schedule</button>
          <span class="status" id="tourScheduleStatus"></span>
        </div>
      </section>

      <section data-admin-panel-group="schedule" class="legacy-admin-section">
        <h2 id="cityWizardTitle">City schedule wizard</h2>
        <p class="hint" id="cityWizardHint">
          One card per touring city/date range. Fill question-by-question to auto-build each city schedule.
        </p>
        <div class="row city-toolbar">
          <span class="hint" id="cityWizardTimezoneHint"></span>
          <span class="status" id="availabilityStatus"></span>
        </div>
        <div class="editor-list" id="cityScheduleWizard"></div>
        <div class="row">
          <button class="btn ghost" id="clearCityTemplates" type="button">Clear template blocks</button>
          <button class="btn" id="saveAvailability" type="button">Save city schedule</button>
          <span class="status" id="cityScheduleStatus"></span>
        </div>
      </section>

      <section data-admin-panel-group="schedule" class="legacy-admin-section">
        <h2 id="gallerySectionTitle">Eye candy</h2>
        <div class="editor-list" id="galleryList"></div>
        <p class="hint" id="gallerySectionHint">Use full paths like /photos/heidi15.jpg and short alt text.</p>
        <div class="row">
          <button class="btn secondary" id="addGalleryRow" type="button">Add eye candy</button>
          <button class="btn" id="saveGallery" type="button">Save eye candy</button>
          <span class="status" id="galleryStatus"></span>
        </div>
      </section>

      <section data-admin-panel-group="clients">
        <h2 id="requestsSectionTitle">Requests</h2>
        <div class="row">
          <button class="btn secondary" id="refreshRequests">Refresh</button>
          <select id="statusFilter">
            <option value="all">All</option>
            <option value="pending">Pending</option>
            <option value="maybe">Maybe</option>
            <option value="accepted">Accepted</option>
            <option value="paid">Paid</option>
            <option value="blacklisted">Blacklisted</option>
            <option value="declined">Declined</option>
            <option value="cancelled">Cancelled</option>
          </select>
          <span class="status" id="requestsStatus"></span>
        </div>
        <div id="requestsList"></div>
        <div class="row">
          <a class="btn ghost" href="customers.php">Costumer directory</a>
        </div>
      </section>

    </main>

    <script>
      const ADMIN_KEY = <?php echo json_encode(ADMIN_API_KEY); ?>;
      const CURRENT_ADMIN_USER = <?php echo json_encode($currentAdminUser); ?>;
      const CURRENT_ADMIN_IS_EMPLOYER = <?php echo $currentAdminIsEmployer ? 'true' : 'false'; ?>;
      const DEFAULT_TIMEZONE = "America/Toronto";
      const TIMEZONES = [
        "America/Toronto",
        "America/New_York",
        "America/Chicago",
        "America/Denver",
        "America/Los_Angeles",
        "America/Vancouver",
        "America/Mexico_City",
        "America/Sao_Paulo",
        "Europe/London",
        "Europe/Paris",
        "Europe/Berlin",
        "Europe/Rome",
        "Europe/Madrid",
        "Europe/Amsterdam",
        "Europe/Zurich",
        "Europe/Stockholm",
        "Europe/Athens",
        "Europe/Moscow",
        "Africa/Cairo",
        "Africa/Johannesburg",
        "Asia/Dubai",
        "Asia/Jerusalem",
        "Asia/Kolkata",
        "Asia/Bangkok",
        "Asia/Singapore",
        "Asia/Shanghai",
        "Asia/Hong_Kong",
        "Asia/Tokyo",
        "Asia/Seoul",
        "Australia/Sydney",
        "Australia/Melbourne",
        "Pacific/Auckland",
      ];

      const tourCityInput = document.getElementById("tourCity");
      const tourTzSelect = document.getElementById("tourTimezone");
      const tourTimezoneField = document.getElementById("tourTimezoneField");
      const bufferInput = document.getElementById("bufferMinutes");
      const availabilityStatus = document.getElementById("availabilityStatus");
      const saveAvailabilityBtn = document.getElementById("saveAvailability");
      const calendarStart = document.getElementById("calendarStart");
      const calendarDay = document.getElementById("calendarDay");
      const calendarMonth = document.getElementById("calendarMonth");
      const calendarToday = document.getElementById("calendarToday");
      const calendarGrid = document.getElementById("calendarGrid");
      const calendarViewButtons = document.querySelectorAll("[data-calendar-view]");
      const calendarFields = document.querySelectorAll("[data-calendar-field]");
      const blockedDate = document.getElementById("blockedDate");
      const blockedStart = document.getElementById("blockedStart");
      const blockedEnd = document.getElementById("blockedEnd");
      const blockedReason = document.getElementById("blockedReason");
      const blockedFullRangeStart = document.getElementById("blockedFullRangeStart");
      const blockedFullRangeEnd = document.getElementById("blockedFullRangeEnd");
      const blockedStatus = document.getElementById("blockedStatus");
      const blockedList = document.getElementById("blockedList");
      const addBlockedBtn = document.getElementById("addBlocked");
      const blockFullDayBtn = document.getElementById("blockFullDay");
      const blockFullRangeBtn = document.getElementById("blockFullRange");
      const recurringDays = document.getElementById("recurringDays");
      const recurringAllDay = document.getElementById("recurringAllDay");
      const recurringStart = document.getElementById("recurringStart");
      const recurringEnd = document.getElementById("recurringEnd");
      const recurringReason = document.getElementById("recurringReason");
      const addRecurringBtn = document.getElementById("addRecurring");
      const recurringStatus = document.getElementById("recurringStatus");
      const recurringList = document.getElementById("recurringList");
      const toggleBlockedListBtn = document.getElementById("toggleBlockedList");
      const refreshBtn = document.getElementById("refreshRequests");
      const statusFilter = document.getElementById("statusFilter");
      const requestsList = document.getElementById("requestsList");
      const requestsStatus = document.getElementById("requestsStatus");
      const quickAddType = document.getElementById("quickAddType");
      const quickAddCity = document.getElementById("quickAddCity");
      const quickAddStart = document.getElementById("quickAddStart");
      const quickAddEnd = document.getElementById("quickAddEnd");
      const quickAddNotes = document.getElementById("quickAddNotes");
      const quickAddSubmitBtn = document.getElementById("quickAddSubmit");
      const quickAddStatus = document.getElementById("quickAddStatus");
      const tourScheduleList = document.getElementById("menuTourScheduleList") || document.getElementById("tourScheduleList");
      const tourPartnersList = document.getElementById("menuTourPartnersList");
      const tourScheduleStatus =
        document.getElementById("menuTourScheduleStatus") || document.getElementById("tourScheduleStatus");
      const addTourRowBtn = document.getElementById("menuAddTourRow") || document.getElementById("addTourRow");
      const addTourPartnerRowBtn = document.getElementById("menuAddTourPartnerRow");
      const saveTourScheduleBtn = document.getElementById("menuSaveTourSchedule") || document.getElementById("saveTourSchedule");
      const cityScheduleWizard = document.getElementById("cityScheduleWizard");
      const cityScheduleStatus = document.getElementById("cityScheduleStatus");
      const calendarEditorSection = document.getElementById("calendarEditorSection");
      const clearCityTemplatesBtn = document.getElementById("clearCityTemplates");
      const galleryList = document.getElementById("photoList") || document.getElementById("galleryList");
      const galleryStatus = document.getElementById("photoStatus") || document.getElementById("galleryStatus");
      const addGalleryRowBtn = document.getElementById("addPhotoRow") || document.getElementById("addGalleryRow");
      const saveGalleryBtn = document.getElementById("savePhotoConfig") || document.getElementById("saveGallery");
      const photoDisplayModeSelect = document.getElementById("photoDisplayMode");
      const photoCarouselSecondsInput = document.getElementById("photoCarouselSeconds");
      const adminMenuBackdrop = document.getElementById("adminMenuBackdrop");
      const adminMenuDrawer = document.getElementById("adminMenuDrawer");
      const adminMenuToggleBtn = document.getElementById("adminMenuToggleBtn");
      const adminMenuCloseBtn = document.getElementById("adminMenuCloseBtn");
      const adminMenuTitle = document.querySelector(".admin-menu-title");
      const adminMenuSectionList = document.getElementById("adminMenuSectionList");
      const adminMenuPages = document.querySelectorAll("[data-menu-page]");
      const adminMenuOpenButtons = document.querySelectorAll("[data-menu-open]");
      const adminMenuBackButtons = document.querySelectorAll("[data-menu-back]");
      const notifToggleBtn = document.getElementById("notifToggleBtn");
      const notifPanel = document.getElementById("notifPanel");
      const notifList = document.getElementById("notifList");
      const notifUnreadCount = document.getElementById("notifUnreadCount");
      const accountPhotoInput = document.getElementById("accountPhoto");
      const accountPhotoPreview = document.getElementById("accountPhotoPreview");
      const accountNameInput = document.getElementById("accountName");
      const accountEmailInput = document.getElementById("accountEmail");
      const accountPhoneInput = document.getElementById("accountPhone");
      const accountPasswordInput = document.getElementById("accountPassword");
      const accountLanguageSelect = document.getElementById("accountLanguage");
      const accountAccentColorInput = document.getElementById("accountAccentColor");
      const saveAccountCenterBtn = document.getElementById("saveAccountCenter");
      const accountCenterStatus = document.getElementById("accountCenterStatus");
      const employeeUsernameInput = document.getElementById("employeeUsername");
      const employeePasswordInput = document.getElementById("employeePassword");
      const addEmployeeBtn = document.getElementById("addEmployeeBtn");
      const employeeStatus = document.getElementById("employeeStatus");
      const employeeList = document.getElementById("employeeList");
      const menuWorkAllDay = document.getElementById("menuWorkAllDay");
      const menuWorkStart = document.getElementById("menuWorkStart");
      const menuWorkEnd = document.getElementById("menuWorkEnd");
      const menuWorkDays = document.getElementById("menuWorkDays");
      const menuBreakEnabled = document.getElementById("menuBreakEnabled");
      const menuBreakStart = document.getElementById("menuBreakStart");
      const menuBreakEnd = document.getElementById("menuBreakEnd");
      const menuBreakDays = document.getElementById("menuBreakDays");
      const menuScheduleApply = document.getElementById("menuScheduleApply");
      const menuScheduleStatus = document.getElementById("menuScheduleStatus");
      const menuTourCity = document.getElementById("menuTourCity");
      const menuTourStart = document.getElementById("menuTourStart");
      const menuTourEnd = document.getElementById("menuTourEnd");
      const menuTourFirstStart = document.getElementById("menuTourFirstStart");
      const menuTourLastEnd = document.getElementById("menuTourLastEnd");
      const menuTourAddBtn = document.getElementById("menuTourAddBtn");
      const menuTourStatus = document.getElementById("menuTourStatus");
      const menuAutoTemplateBlocks = document.getElementById("menuAutoTemplateBlocks");
      const menuClearAutoBlocks = document.getElementById("menuClearAutoBlocks");
      const menuAutoBlockStatus = document.getElementById("menuAutoBlockStatus");
      const serviceNameInput = document.getElementById("serviceNameInput");
      const serviceDurationList = document.getElementById("serviceDurationList");
      const servicePackageList = document.getElementById("servicePackageList");
      const serviceAddonList = document.getElementById("serviceAddonList");
      const addServiceDurationBtn = document.getElementById("addServiceDuration");
      const addServicePackageBtn = document.getElementById("addServicePackage");
      const addServiceAddonBtn = document.getElementById("addServiceAddon");
      const saveServicesConfigBtn = document.getElementById("saveServicesConfig");
      const servicesConfigStatus = document.getElementById("servicesConfigStatus");
      const languageButtons = document.querySelectorAll(".age-language [data-language-choice]");
      const panelButtons = document.querySelectorAll("[data-admin-panel]");
      const panelSections = document.querySelectorAll("[data-admin-panel-group]");
      const LANGUAGE_KEY = "hvh_inside_language";
      const PANEL_STORAGE_KEY = "hvh_admin_panel";
      const ACCOUNT_CENTER_KEY = "hvh_admin_account_center";
      const SCHEDULE_MENU_KEY = "hvh_admin_schedule_menu";
      const SERVICES_MENU_KEY = "hvh_admin_services_menu";
      const NOTIFICATIONS_READ_LOCAL_KEY = "hvh_admin_read_notifications";
      const SUPPORTED_LANGUAGES = ["en", "fr"];
      const DEFAULT_ACCENT_COLOR = "#ff006e";
      let currentLanguage = "en";
      const I18N = {
        en: {
          admin_title: "BombaCLOUD!",
          admin_subtitle: "Simple city-by-city setup. Fill each city card, save, then manage requests.",
          open_client_app: "Client app",
          panel_schedule: "Schedule",
          panel_clients: "Clients",
          tour_schedule_title: "Tour schedule",
          tour_schedule_hint: "Dates are inclusive. Use YYYY-MM-DD. Overlapping city dates are allowed.",
          add_stop: "Add stop",
          save_tour_schedule: "Save tour schedule",
          city_wizard_title: "City schedule wizard",
          city_wizard_hint:
            "One card per touring city/date range. Fill question-by-question to auto-build each city schedule.",
          city_wizard_timezone_hint: "",
          save_city_schedule: "Save city schedule",
          clear_template_blocks: "Clear template blocks",
          auto_template_blocks_label: "Auto-block from city rules",
          disable_clear_auto_blocks: "Disable + clear auto blocks",
          auto_blocks_enabled: "Auto blocks enabled.",
          auto_blocks_disabled: "Auto blocks disabled.",
          auto_blocks_cleared: "Auto blocks cleared.",
          eye_candy_title: "Eye candy",
          eye_candy_hint: "Use full paths like /photos/heidi15.jpg and a short photo name.",
          add_eye_candy: "Add eye candy",
          save_eye_candy: "Save eye candy",
          requests_title: "Requests",
          quick_add_title: "Quick add",
          quick_add_hint: "One add panel for future tour dates, plus optional full-day calendar blocks.",
          quick_add_type: "Type",
          quick_add_city: "City",
          quick_add_start: "Start date",
          quick_add_end: "End date",
          quick_add_notes: "Notes",
          quick_add_notes_placeholder: "Optional note",
          quick_add_submit: "Add",
          quick_add_type_tour: "Tour date (website + admin)",
          quick_add_type_block: "Block days (admin only)",
          tour_partners_title: "Partners",
          tour_partners_hint: "Add friend name and link shown in touring section.",
          add_partner: "Add partner",
          quick_add_missing_fields: "Type, city, start, and end are required.",
          quick_add_invalid_range: "End date must be after or equal to start date.",
          quick_add_blocked_saved: "Block range saved to calendar.",
          start_date: "Start date",
          end_date: "End date",
          city_field: "City",
          city_name_placeholder: "City name",
          photo_path: "Photo path",
          alt_text: "Photo name",
          short_description: "Short description",
          preview: "Preview",
          refresh: "Refresh",
          all: "All",
          pending: "Pending",
          maybe: "Maybe",
          accepted: "Accepted",
          paid: "Paid",
          blacklisted: "Blacklisted",
          declined: "Declined",
          cancelled: "Cancelled",
          costumer_directory: "Costumer directory",
          remove: "Remove",
          add_tour_first: "Add tour stops first, then city schedule cards appear here.",
          add_photo_min: "Add at least one eye candy photo.",
          add_entry_min: "Add at least one valid entry.",
          admin_key_required: "Admin key required.",
          city_schedule_saved: "City schedule saved.",
          city_schedules_saved: "City schedules saved.",
          failed_save_city_schedule: "Failed to save city schedule.",
          unsaved_changes: "Unsaved changes. Click Save city schedule.",
          templates_changed: "City templates changed. Click Save city schedule.",
          saving_city_schedule: "Saving city schedule...",
          city_updated_applied: "{city} updated and applied.",
          template_rules_cleared: "Template rules cleared.",
          template_rules_clearing: "Template rules cleared. Saving now...",
          failed_load_tour_schedule: "Failed to load tour schedule.",
          tour_schedule_saved: "Tour schedule saved.",
          tour_dates_changed: "Tour dates changed. Click Save city schedule to update city templates.",
          failed_save_tour_schedule: "Failed to save tour schedule.",
          failed_load_eye_candy: "Failed to load eye candy.",
          eye_candy_saved: "Eye candy saved.",
          failed_save_eye_candy: "Failed to save eye candy.",
          status_updated: "Status updated.",
          failed_update_status: "Failed to update status.",
          download_ready: "Download ready.",
          failed_download: "Failed to download.",
          no_requests_found: "No requests found.",
          failed_load_requests: "Failed to load requests.",
          unable_load_city_schedule: "Unable to load city schedule.",
          pick_valid_ready_time: "Pick a valid ready time.",
          buffer_invalid: "Buffer must be 0 or higher.",
          pick_sleep_times: "Pick sleep start and end time.",
          pick_sleep_days: "Select at least one day for sleep pattern.",
          pick_break_times: "Pick break start and end time.",
          pick_break_days: "Select at least one day for breaks.",
          pick_leave_day_end: "Pick leave-day end time.",
          tour_stop: "Tour stop",
          date_to: "to",
          q_ready_time: "What time will you be ready to work at?",
          q_buffer: "Do you need a buffer in between clients?",
          q_sleep: "Do you have a sleeping pattern?",
          q_breaks: "Do you take any breaks during your stay?",
          q_leave_time: "Until what time will you be taking costumers on your leaving day?",
          which_days: "Which days?",
          yes: "Yes",
          no: "No",
          back: "Back",
          next: "Next",
          done: "Done",
          timezone: "",
          not_set: "Not set",
          step: "Step {current} of {total}",
          follow_up: "Follow-up",
          no_city: "no city",
          follow_up_no: "no",
          follow_up_phone: "Future contact (phone)",
          follow_up_email: "Future contact (email)",
          follow_up_cities: "Future-contact cities",
          email_label: "Email",
          name_label: "Name",
          phone_label: "Phone",
          city_label: "City",
          date_label: "Date",
          time_label: "Time",
          hours_label: "Duration hours",
          type_label: "Type",
          outcall_address: "Outcall address",
          experience: "Experience",
          duration: "Duration",
          preferred: "Preferred",
          client_tz: "Client time",
          tour_tz: "Tour time",
          deposit: "Deposit",
          payment_status: "Payment status",
          payment_method: "Payment method",
          notes: "Notes",
          decline_reason: "Decline reason",
          reason: "Reason",
          blacklist_reason: "Blacklist reason",
          payment_details: "Payment details",
          created: "Created",
          updated: "Updated",
          email_sent: "Email sent",
          edit_history: "Edit history",
          no_history: "No history yet",
          unknown: "Unknown",
          action_accept: "Accept",
          action_maybe: "Maybe",
          action_blacklist: "Blacklist",
          action_mark_paid: "Mark paid",
          action_decline: "Decline",
          action_cancel: "Cancel",
          action_edit: "Edit",
          action_remove_grid: "Remove from grid",
          action_show_grid: "Show on grid",
          action_choose: "Choose option",
          action_ok: "OK",
          action_pick_first: "Pick an option first.",
          grid_hidden: "Booking removed from schedule grid.",
          grid_visible: "Booking shown on schedule grid.",
          save_changes: "Save changes",
          invalid_email: "Invalid email address.",
          invalid_phone: "Use phone with country code, for example +14389993539.",
          required_fields: "Please fill all required fields.",
          appointment_updated: "Appointment updated.",
          failed_update_appointment: "Failed to update appointment.",
          action_google_calendar: "Add to Google Calendar",
          action_samsung_calendar: "Samsung Calendar (.ics)",
          legend_blocked: "Blocked",
          legend_booking: "Booking",
          legend_outcall: "Outcall",
          legend_paid: "Paid",
          legend_maybe: "Maybe",
          legend_city: "City marker",
        },
        fr: {
          admin_title: "BombaCLOUD!",
          admin_subtitle: "Configuration simple ville par ville. Remplissez chaque carte, sauvegardez, puis gerez les demandes.",
          open_client_app: "Appli client",
          panel_schedule: "Planning",
          panel_clients: "Clients",
          tour_schedule_title: "Calendrier de tournee",
          tour_schedule_hint: "Les dates sont inclusives. Utilisez YYYY-MM-DD. Les chevauchements de villes sont autorises.",
          add_stop: "Ajouter une ville",
          save_tour_schedule: "Sauvegarder la tournee",
          city_wizard_title: "Assistant planning par ville",
          city_wizard_hint:
            "Une carte par ville/periode de tournee. Remplissez question par question pour generer le planning automatiquement.",
          city_wizard_timezone_hint: "",
          save_city_schedule: "Sauvegarder le planning ville",
          clear_template_blocks: "Effacer les blocs modele",
          auto_template_blocks_label: "Auto-blocage selon les regles ville",
          disable_clear_auto_blocks: "Desactiver + effacer auto blocs",
          auto_blocks_enabled: "Auto blocs actifs.",
          auto_blocks_disabled: "Auto blocs desactives.",
          auto_blocks_cleared: "Auto blocs effaces.",
          eye_candy_title: "Eye candy",
          eye_candy_hint: "Utilisez des chemins complets comme /photos/heidi15.jpg et un court nom de photo.",
          add_eye_candy: "Ajouter eye candy",
          save_eye_candy: "Sauvegarder eye candy",
          requests_title: "Demandes",
          quick_add_title: "Ajout rapide",
          quick_add_hint: "Un seul panneau pour ajouter les dates de tournee, ou bloquer rapidement des jours.",
          quick_add_type: "Type",
          quick_add_city: "Ville",
          quick_add_start: "Date de debut",
          quick_add_end: "Date de fin",
          quick_add_notes: "Notes",
          quick_add_notes_placeholder: "Note optionnelle",
          quick_add_submit: "Ajouter",
          quick_add_type_tour: "Date tournee (site + admin)",
          quick_add_type_block: "Bloquer des jours (admin)",
          tour_partners_title: "Partenaires",
          tour_partners_hint: "Ajoutez un nom ami et un lien dans la section tournee.",
          add_partner: "Ajouter partenaire",
          quick_add_missing_fields: "Type, ville, debut et fin sont obligatoires.",
          quick_add_invalid_range: "La date de fin doit etre apres ou egale au debut.",
          quick_add_blocked_saved: "Plage bloquee sauvegardee dans le calendrier.",
          start_date: "Date de debut",
          end_date: "Date de fin",
          city_field: "Ville",
          city_name_placeholder: "Nom de ville",
          photo_path: "Chemin photo",
          alt_text: "Nom de la photo",
          short_description: "Description courte",
          preview: "Apercu",
          refresh: "Actualiser",
          all: "Tous",
          pending: "En attente",
          maybe: "Peut-etre",
          accepted: "Acceptee",
          paid: "Payee",
          blacklisted: "Liste noire",
          declined: "Refusee",
          cancelled: "Annulee",
          costumer_directory: "Repertoire clients",
          remove: "Retirer",
          add_tour_first: "Ajoutez d'abord des etapes de tournee, puis les cartes ville apparaissent ici.",
          add_photo_min: "Ajoutez au moins une photo eye candy.",
          add_entry_min: "Ajoutez au moins une entree valide.",
          admin_key_required: "Cle admin requise.",
          city_schedule_saved: "Planning ville sauvegarde.",
          city_schedules_saved: "Plannings ville sauvegardes.",
          failed_save_city_schedule: "Echec sauvegarde planning ville.",
          unsaved_changes: "Changements non sauvegardes. Cliquez sur Sauvegarder le planning ville.",
          templates_changed: "Modeles ville modifies. Cliquez sur Sauvegarder le planning ville.",
          saving_city_schedule: "Sauvegarde du planning ville...",
          city_updated_applied: "{city} mis a jour et applique.",
          template_rules_cleared: "Regles modele effacees.",
          template_rules_clearing: "Regles modele effacees. Sauvegarde en cours...",
          failed_load_tour_schedule: "Echec chargement tournee.",
          tour_schedule_saved: "Tournee sauvegardee.",
          tour_dates_changed: "Dates de tournee modifiees. Sauvegardez le planning ville pour mettre a jour les modeles.",
          failed_save_tour_schedule: "Echec sauvegarde tournee.",
          failed_load_eye_candy: "Echec chargement eye candy.",
          eye_candy_saved: "Eye candy sauvegarde.",
          failed_save_eye_candy: "Echec sauvegarde eye candy.",
          status_updated: "Statut mis a jour.",
          failed_update_status: "Echec mise a jour statut.",
          download_ready: "Telechargement pret.",
          failed_download: "Echec telechargement.",
          no_requests_found: "Aucune demande trouvee.",
          failed_load_requests: "Echec chargement demandes.",
          unable_load_city_schedule: "Impossible de charger le planning ville.",
          pick_valid_ready_time: "Choisissez une heure de debut valide.",
          buffer_invalid: "Le buffer doit etre 0 ou plus.",
          pick_sleep_times: "Choisissez heure debut/fin sommeil.",
          pick_sleep_days: "Choisissez au moins un jour pour le sommeil.",
          pick_break_times: "Choisissez heure debut/fin pause.",
          pick_break_days: "Choisissez au moins un jour pour les pauses.",
          pick_leave_day_end: "Choisissez l'heure de fin du jour de depart.",
          tour_stop: "Etape tournee",
          date_to: "a",
          q_ready_time: "A quelle heure serez-vous prete a travailler?",
          q_buffer: "Avez-vous besoin d'un buffer entre les clients?",
          q_sleep: "Avez-vous un rythme de sommeil?",
          q_breaks: "Prenez-vous des pauses pendant votre sejour?",
          q_leave_time: "Jusqu'a quelle heure prenez-vous des clients le jour du depart?",
          which_days: "Quels jours?",
          yes: "Oui",
          no: "Non",
          back: "Retour",
          next: "Suivant",
          done: "Terminer",
          timezone: "",
          not_set: "Non defini",
          step: "Etape {current} sur {total}",
          follow_up: "Suivi",
          no_city: "aucune ville",
          follow_up_no: "non",
          follow_up_phone: "Contact futur (telephone)",
          follow_up_email: "Contact futur (email)",
          follow_up_cities: "Villes contact futur",
          email_label: "Email",
          name_label: "Nom",
          phone_label: "Telephone",
          city_label: "Ville",
          date_label: "Date",
          time_label: "Heure",
          hours_label: "Heures de duree",
          type_label: "Type",
          outcall_address: "Adresse outcall",
          experience: "Experience",
          duration: "Duree",
          preferred: "Souhaite",
          client_tz: "Heure client",
          tour_tz: "Heure tournee",
          deposit: "Acompte",
          payment_status: "Statut paiement",
          payment_method: "Methode paiement",
          notes: "Notes",
          decline_reason: "Raison du refus",
          reason: "Raison",
          blacklist_reason: "Raison liste noire",
          payment_details: "Details paiement",
          created: "Cree",
          updated: "Mis a jour",
          email_sent: "Email envoye",
          edit_history: "Historique",
          no_history: "Aucun historique",
          unknown: "Inconnu",
          action_accept: "Accepter",
          action_maybe: "Peut-etre",
          action_blacklist: "Liste noire",
          action_mark_paid: "Marquer paye",
          action_decline: "Refuser",
          action_cancel: "Annuler",
          action_edit: "Modifier",
          action_remove_grid: "Retirer du planning",
          action_show_grid: "Afficher sur planning",
          action_choose: "Choisir option",
          action_ok: "OK",
          action_pick_first: "Choisissez une option d'abord.",
          grid_hidden: "Reservation retiree du planning.",
          grid_visible: "Reservation affichee dans le planning.",
          save_changes: "Sauvegarder",
          invalid_email: "Adresse email invalide.",
          invalid_phone: "Utilisez un numero avec indicatif pays, ex: +14389993539.",
          required_fields: "Veuillez remplir les champs obligatoires.",
          appointment_updated: "Rendez-vous mis a jour.",
          failed_update_appointment: "Echec mise a jour du rendez-vous.",
          action_google_calendar: "Ajouter a Google Calendar",
          action_samsung_calendar: "Samsung Calendar (.ics)",
          legend_blocked: "Bloque",
          legend_booking: "Reservation",
          legend_outcall: "Outcall",
          legend_paid: "Paye",
          legend_maybe: "Peut-etre",
          legend_city: "Repere ville",
        },
      };
      const formatTemplate = (template, vars = {}) =>
        String(template || "").replace(/\{(\w+)\}/g, (_m, key) => (vars[key] ?? `{${key}}`));
      const t = (key, vars = {}) => {
        const pack = I18N[currentLanguage] || I18N.en;
        const fallback = I18N.en[key] || key;
        return formatTemplate(pack[key] || fallback, vars);
      };
      const statusLabel = (status) => t(String(status || "").toLowerCase());
      const getStoredLanguage = () => {
        try {
          const stored = window.localStorage.getItem(LANGUAGE_KEY);
          if (stored && SUPPORTED_LANGUAGES.includes(stored)) return stored;
        } catch (_error) {}
        return null;
      };
      const detectBrowserLanguage = () => {
        const langs = Array.isArray(navigator.languages) ? navigator.languages : [navigator.language || "en"];
        for (const lang of langs) {
          const short = String(lang || "").slice(0, 2).toLowerCase();
          if (SUPPORTED_LANGUAGES.includes(short)) return short;
        }
        return "en";
      };
      const setTextById = (id, text) => {
        const el = document.getElementById(id);
        if (el) el.textContent = text;
      };
      const setAdminPanel = (panel, persist = true) => {
        const allowed = ["schedule", "clients"];
        const safePanel = allowed.includes(panel) ? panel : "schedule";
        panelButtons.forEach((button) => {
          button.setAttribute("aria-pressed", button.dataset.adminPanel === safePanel ? "true" : "false");
        });
        panelSections.forEach((section) => {
          section.classList.toggle("admin-panel-hidden", section.dataset.adminPanelGroup !== safePanel);
        });
        if (persist) {
          try {
            window.localStorage.setItem(PANEL_STORAGE_KEY, safePanel);
          } catch (_error) {}
        }
      };
      const getStoredAdminPanel = () => {
        try {
          const stored = window.localStorage.getItem(PANEL_STORAGE_KEY);
          if (stored === "schedule" || stored === "clients") {
            return stored;
          }
        } catch (_error) {}
        return "schedule";
      };
      const DAY_OPTIONS = [
        { value: 0, label: "Sun" },
        { value: 1, label: "Mon" },
        { value: 2, label: "Tue" },
        { value: 3, label: "Wed" },
        { value: 4, label: "Thu" },
        { value: 5, label: "Fri" },
        { value: 6, label: "Sat" },
      ];

      const readStoredObject = (key, fallback = {}) => {
        try {
          const raw = window.localStorage.getItem(key);
          if (!raw) return fallback;
          const parsed = JSON.parse(raw);
          return parsed && typeof parsed === "object" ? parsed : fallback;
        } catch (_error) {
          return fallback;
        }
      };

      const writeStoredObject = (key, value) => {
        try {
          window.localStorage.setItem(key, JSON.stringify(value));
          return true;
        } catch (_error) {
          return false;
        }
      };

      const createDayChoices = (target) => {
        if (!target) return;
        target.innerHTML = DAY_OPTIONS.map(
          (day) =>
            `<label><input type="checkbox" value="${day.value}" /> ${day.label}</label>`
        ).join("");
      };

      const getDayChoices = (target) => {
        if (!target) return [];
        return Array.from(target.querySelectorAll('input[type="checkbox"]:checked'))
          .map((input) => Number(input.value))
          .filter((value) => !Number.isNaN(value))
          .sort((a, b) => a - b);
      };

      const setDayChoices = (target, values) => {
        if (!target) return;
        const allowed = new Set((Array.isArray(values) ? values : []).map((v) => Number(v)));
        target.querySelectorAll('input[type="checkbox"]').forEach((input) => {
          input.checked = allowed.has(Number(input.value));
        });
      };

      const closeAdminMenu = () => {
        if (!adminMenuDrawer || !adminMenuBackdrop) return;
        adminMenuDrawer.classList.remove("open");
        adminMenuDrawer.setAttribute("aria-hidden", "true");
        adminMenuBackdrop.hidden = true;
        showAdminMenuHome();
      };

      const showAdminMenuHome = () => {
        if (adminMenuSectionList) {
          adminMenuSectionList.classList.remove("hidden");
        }
        adminMenuPages.forEach((section) => section.classList.add("hidden"));
        if (adminMenuTitle) {
          adminMenuTitle.textContent = "Admin menu";
        }
      };

      const showAdminMenuPage = (pageKey) => {
        const key = String(pageKey || "").trim().toLowerCase();
        if (!key) return;
        if (adminMenuSectionList) {
          adminMenuSectionList.classList.add("hidden");
        }
        let selectedTitle = "Admin menu";
        adminMenuPages.forEach((section) => {
          const isMatch = String(section.getAttribute("data-menu-page") || "").toLowerCase() === key;
          section.classList.toggle("hidden", !isMatch);
          if (isMatch) {
            selectedTitle = String(section.getAttribute("data-menu-title") || selectedTitle);
          }
        });
        if (adminMenuTitle) {
          adminMenuTitle.textContent = selectedTitle;
        }
      };

      const openAdminMenu = () => {
        if (!adminMenuDrawer || !adminMenuBackdrop) return;
        adminMenuDrawer.classList.add("open");
        adminMenuDrawer.setAttribute("aria-hidden", "false");
        adminMenuBackdrop.hidden = false;
        showAdminMenuHome();
      };

      const readAccountCenter = () =>
        readStoredObject(ACCOUNT_CENTER_KEY, {
          profilePic: "",
          name: "",
          email: "",
          phone: "",
          language: "en",
          accentColor: DEFAULT_ACCENT_COLOR,
        });

      const normalizeHexColor = (value, fallback = DEFAULT_ACCENT_COLOR) => {
        const normalized = String(value || "").trim().toLowerCase();
        if (/^#[0-9a-f]{6}$/.test(normalized)) {
          return normalized;
        }
        return fallback;
      };

      const hexToRgb = (value) => {
        const hex = normalizeHexColor(value).slice(1);
        return {
          r: Number.parseInt(hex.slice(0, 2), 16),
          g: Number.parseInt(hex.slice(2, 4), 16),
          b: Number.parseInt(hex.slice(4, 6), 16),
        };
      };

      const rgbToHex = ({ r, g, b }) => {
        const toHex = (number) => Math.max(0, Math.min(255, Math.round(number))).toString(16).padStart(2, "0");
        return `#${toHex(r)}${toHex(g)}${toHex(b)}`;
      };

      const mixWithWhite = (rgb, ratio) => {
        const amount = Math.max(0, Math.min(1, Number(ratio) || 0));
        return {
          r: rgb.r + (255 - rgb.r) * amount,
          g: rgb.g + (255 - rgb.g) * amount,
          b: rgb.b + (255 - rgb.b) * amount,
        };
      };

      const rgbaString = (rgb, alpha) => {
        const opacity = Math.max(0, Math.min(1, Number(alpha) || 0));
        return `rgba(${Math.round(rgb.r)}, ${Math.round(rgb.g)}, ${Math.round(rgb.b)}, ${opacity})`;
      };

      const applyAccentTheme = (accentValue) => {
        const accent = normalizeHexColor(accentValue);
        const rgb = hexToRgb(accent);
        const pink = rgbToHex(mixWithWhite(rgb, 0.2));
        document.documentElement.style.setProperty("--hot", accent);
        document.documentElement.style.setProperty("--pink", pink);
        document.documentElement.style.setProperty("--line", rgbaString(rgb, 0.25));
        document.documentElement.style.setProperty("--shadow", rgbaString(rgb, 0.2));
      };

      const applyAccountCenterToUi = () => {
        const data = readAccountCenter();
        if (accountNameInput) accountNameInput.value = data.name || "";
        if (accountEmailInput) accountEmailInput.value = data.email || "";
        if (accountPhoneInput) accountPhoneInput.value = data.phone || "";
        if (accountLanguageSelect) accountLanguageSelect.value = data.language || "en";
        if (accountAccentColorInput) {
          accountAccentColorInput.value = normalizeHexColor(data.accentColor, DEFAULT_ACCENT_COLOR);
        }
        applyAccentTheme(data.accentColor || DEFAULT_ACCENT_COLOR);
        if (accountPhotoPreview) {
          if (data.profilePic) {
            accountPhotoPreview.src = data.profilePic;
            accountPhotoPreview.hidden = false;
          } else {
            accountPhotoPreview.hidden = true;
            accountPhotoPreview.removeAttribute("src");
          }
        }
      };

      const saveAccountCenter = () => {
        const current = readAccountCenter();
        const nextPassword = String(accountPasswordInput?.value || "").trim();
        const data = {
          ...current,
          name: String(accountNameInput?.value || "").trim(),
          email: String(accountEmailInput?.value || "").trim(),
          phone: String(accountPhoneInput?.value || "").trim(),
          language: String(accountLanguageSelect?.value || "en").trim().toLowerCase() === "fr" ? "fr" : "en",
          accentColor: normalizeHexColor(accountAccentColorInput?.value || current.accentColor || DEFAULT_ACCENT_COLOR),
        };
        const ok = writeStoredObject(ACCOUNT_CENTER_KEY, data);
        if (accountCenterStatus) {
          if (!ok) {
            accountCenterStatus.textContent = "Could not save account center.";
          } else if (nextPassword) {
            accountCenterStatus.textContent = "Profile saved. Password field captured for next auth step.";
          } else {
            accountCenterStatus.textContent = "Account center saved.";
          }
        }
        if (accountPasswordInput) accountPasswordInput.value = "";
        applyAccentTheme(data.accentColor);
        if (data.language && SUPPORTED_LANGUAGES.includes(data.language)) {
          applyLanguage(data.language, true);
        }
      };

      const readScheduleMenuConfig = () =>
        readStoredObject(SCHEDULE_MENU_KEY, {
          workAllDay: false,
          workStart: "10:00",
          workEnd: "18:00",
          workDays: [1, 2, 3, 4, 5],
          breakEnabled: false,
          breakStart: "14:00",
          breakEnd: "15:00",
          breakDays: [1, 2, 3, 4, 5],
        });

      const applyScheduleMenuConfigToUi = () => {
        const data = readScheduleMenuConfig();
        if (menuWorkAllDay) menuWorkAllDay.checked = !!data.workAllDay;
        if (menuWorkStart) menuWorkStart.value = data.workStart || "10:00";
        if (menuWorkEnd) menuWorkEnd.value = data.workEnd || "18:00";
        if (menuBreakEnabled) menuBreakEnabled.checked = !!data.breakEnabled;
        if (menuBreakStart) menuBreakStart.value = data.breakStart || "14:00";
        if (menuBreakEnd) menuBreakEnd.value = data.breakEnd || "15:00";
        setDayChoices(menuWorkDays, data.workDays);
        setDayChoices(menuBreakDays, data.breakDays);
      };

      const getDateKeysByWeekdays = (startKey, endKey, weekdays) => {
        const daySet = new Set((Array.isArray(weekdays) ? weekdays : []).map((v) => Number(v)));
        return getDateRangeKeys(startKey, endKey).filter((dateKey) => {
          const date = parseDateKey(dateKey);
          return date ? daySet.has(date.getUTCDay()) : false;
        });
      };

      const applyScheduleFromMenu = async () => {
        if (!menuScheduleStatus) return;
        menuScheduleStatus.textContent = "";
        const workAllDay = !!menuWorkAllDay?.checked;
        const workStart = String(menuWorkStart?.value || "").trim();
        const workEnd = String(menuWorkEnd?.value || "").trim();
        const workDays = getDayChoices(menuWorkDays);
        const breakEnabled = !!menuBreakEnabled?.checked;
        const breakStart = String(menuBreakStart?.value || "").trim();
        const breakEnd = String(menuBreakEnd?.value || "").trim();
        const breakDays = getDayChoices(menuBreakDays);

        if (!workDays.length) {
          menuScheduleStatus.textContent = "Pick at least one working day.";
          return;
        }
        if (!workAllDay) {
          const from = timeToMinutes(workStart);
          const to = timeToMinutes(workEnd);
          if (from === null || to === null || to <= from) {
            menuScheduleStatus.textContent = "Working end time must be after start time.";
            return;
          }
        }
        if (breakEnabled) {
          const from = timeToMinutes(breakStart);
          const to = timeToMinutes(breakEnd);
          if (from === null || to === null || to <= from) {
            menuScheduleStatus.textContent = "Break end time must be after break start time.";
            return;
          }
          if (!breakDays.length) {
            menuScheduleStatus.textContent = "Pick at least one break day.";
            return;
          }
        }

        const payload = {
          workAllDay,
          workStart: workAllDay ? "00:00" : workStart,
          workEnd: workAllDay ? "23:59" : workEnd,
          workDays,
          breakEnabled,
          breakStart,
          breakEnd,
          breakDays,
        };
        writeStoredObject(SCHEDULE_MENU_KEY, payload);

        citySchedules = citySchedules.map((schedule) =>
          normalizeCitySchedule({
            ...schedule,
            ready_start: payload.workStart,
            leave_day_end: payload.workEnd,
            has_break: breakEnabled,
            break_start: breakStart,
            break_end: breakEnd,
            break_days: breakEnabled ? getDateKeysByWeekdays(schedule.start, schedule.end, breakDays) : [],
          })
        );
        renderCityScheduleWizard();

        const offDays = DAY_OPTIONS.map((day) => day.value).filter((day) => !workDays.includes(day));
        const offDayBlocks = offDays.map((day) => ({
          days: [day],
          all_day: true,
          start: "",
          end: "",
          reason: "Off day",
        }));
        const breakBlocks = breakEnabled
          ? breakDays.map((day) => ({
              days: [day],
              all_day: false,
              start: breakStart,
              end: breakEnd,
              reason: "Break",
            }))
          : [];
        recurringBlocks = [...offDayBlocks, ...breakBlocks];
        renderRecurringList();
        renderCalendarView();
        queueAutoSave(t("saving_city_schedule"), { persist: true });
        await saveAvailability();
        menuScheduleStatus.textContent = "Schedule applied.";
      };

      const createMenuListRow = (container, firstPlaceholder, secondPlaceholder = "Price", firstValue = "", secondValue = "") => {
        if (!container) return null;
        const row = document.createElement("div");
        row.className = "menu-list-row";
        row.innerHTML = `
          <input type="text" data-first placeholder="${firstPlaceholder}" value="${String(firstValue || "")}" />
          <input type="number" data-second placeholder="${secondPlaceholder}" value="${String(secondValue || "")}" min="0" step="1" />
          <button class="btn ghost" type="button" data-remove-row>Remove</button>
        `;
        const removeBtn = row.querySelector("[data-remove-row]");
        if (removeBtn) {
          removeBtn.addEventListener("click", () => row.remove());
        }
        container.appendChild(row);
        return row;
      };

      const readMenuRows = (container) => {
        if (!container) return [];
        return Array.from(container.querySelectorAll(".menu-list-row"))
          .map((row) => {
            const first = String(row.querySelector("[data-first]")?.value || "").trim();
            const second = Number(row.querySelector("[data-second]")?.value || 0);
            return {
              first,
              second: Number.isFinite(second) ? second : 0,
            };
          })
          .filter((item) => item.first);
      };

      const readServicesConfig = () =>
        readStoredObject(SERVICES_MENU_KEY, {
          name: "",
          durations: [],
          packages: [],
          addons: [],
        });

      const applyServicesConfigToUi = () => {
        const data = readServicesConfig();
        if (serviceNameInput) serviceNameInput.value = String(data?.name || "");
        if (serviceDurationList) serviceDurationList.innerHTML = "";
        if (servicePackageList) servicePackageList.innerHTML = "";
        if (serviceAddonList) serviceAddonList.innerHTML = "";
        (Array.isArray(data.durations) ? data.durations : []).forEach((item) =>
          createMenuListRow(serviceDurationList, "Duration (ex: 1.5h)", "Price", item.first, item.second)
        );
        (Array.isArray(data.packages) ? data.packages : []).forEach((item) =>
          createMenuListRow(servicePackageList, "Package entries (comma)", "Price", item.first, item.second)
        );
        (Array.isArray(data.addons) ? data.addons : []).forEach((item) =>
          createMenuListRow(serviceAddonList, "Addon item", "Price", item.first, item.second)
        );
        if (!serviceDurationList?.children.length) {
          createMenuListRow(serviceDurationList, "Duration (ex: 1.5h)", "Price");
        }
        if (!servicePackageList?.children.length) {
          createMenuListRow(servicePackageList, "Package entries (comma)", "Price");
        }
        if (!serviceAddonList?.children.length) {
          createMenuListRow(serviceAddonList, "Addon item", "Price");
        }
      };

      const saveServicesConfig = () => {
        const data = {
          name: String(serviceNameInput?.value || "").trim(),
          durations: readMenuRows(serviceDurationList),
          packages: readMenuRows(servicePackageList),
          addons: readMenuRows(serviceAddonList),
        };
        const ok = writeStoredObject(SERVICES_MENU_KEY, data);
        if (servicesConfigStatus) {
          servicesConfigStatus.textContent = ok ? "Services saved." : "Could not save services.";
        }
      };

      let readNotificationIdsCache = new Set();
      let readNotificationIdsLoaded = false;

      const getReadNotificationIds = () => new Set(readNotificationIdsCache);

      const setReadNotificationIds = (set) => {
        const ids = Array.from(set).map((value) => String(value)).filter(Boolean).slice(-2000);
        readNotificationIdsCache = new Set(ids);
        writeStoredObject(NOTIFICATIONS_READ_LOCAL_KEY, { ids: ids.slice(-800) });
      };

      const loadNotificationReadState = async () => {
        if (readNotificationIdsLoaded) return;
        const key = getKey();
        const local = readStoredObject(NOTIFICATIONS_READ_LOCAL_KEY, { ids: [] });
        const localIds = Array.isArray(local.ids) ? local.ids.map((value) => String(value)).filter(Boolean) : [];
        if (!key) {
          readNotificationIdsCache = new Set(localIds);
          readNotificationIdsLoaded = true;
          return;
        }
        try {
          const response = await fetch("../api/admin/notifications-state.php", {
            headers: { ...headersWithKey() },
            cache: "no-store",
          });
          const data = await response.json().catch(() => ({}));
          const remoteIds = Array.isArray(data.read_ids) ? data.read_ids.map((value) => String(value)).filter(Boolean) : [];
          const merged = new Set([...localIds, ...remoteIds]);
          setReadNotificationIds(merged);
        } catch (_error) {
          readNotificationIdsCache = new Set(localIds);
        }
        readNotificationIdsLoaded = true;
      };

      const syncNotificationReadState = async () => {
        const key = getKey();
        if (!key) return;
        try {
          await fetch("../api/admin/notifications-state.php", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              ...headersWithKey(),
            },
            body: JSON.stringify({ read_ids: Array.from(readNotificationIdsCache) }),
          });
        } catch (_error) {}
      };

      const requestNotificationId = (item) => {
        const id = String(item?.id || "").trim();
        if (id) return id;
        return `${item?.email || ""}|${item?.preferred_date || ""}|${item?.preferred_time || ""}|${item?.created_at || ""}`;
      };

      const renderNotifications = (requests) => {
        if (!notifList || !notifUnreadCount) return;
        const active = (Array.isArray(requests) ? requests : [])
          .filter((item) => {
            const raw = String(item?.status || "pending").toLowerCase();
            return raw !== "declined" && raw !== "cancelled";
          })
          .sort((a, b) => String(b?.created_at || "").localeCompare(String(a?.created_at || "")));
        const readIds = getReadNotificationIds();
        const unread = active.filter((item) => !readIds.has(requestNotificationId(item)));
        notifUnreadCount.textContent = String(unread.length);
        notifUnreadCount.classList.toggle("hidden", unread.length === 0);
        if (!unread.length) {
          notifList.innerHTML = `<p class="notif-empty">No new notifications.</p>`;
          return;
        }
        notifList.innerHTML = "";
        unread.forEach((item) => {
          const id = requestNotificationId(item);
          const button = document.createElement("button");
          button.type = "button";
          button.className = "notif-item";
          button.innerHTML = `<strong>${item.name || t("unknown")}</strong><br /><small>${item.city || t("no_city")} &bull; ${item.preferred_date || ""} ${item.preferred_time || ""}</small>`;
          button.addEventListener("click", () => {
            const latestRead = getReadNotificationIds();
            latestRead.add(id);
            setReadNotificationIds(latestRead);
            syncNotificationReadState();
            setAdminPanel("clients", true);
            const cards = Array.from(requestsList.querySelectorAll("[data-request-id]"));
            const match = cards.find((card) => card.getAttribute("data-request-id") === String(item.id || ""));
            if (match) {
              match.scrollIntoView({ behavior: "smooth", block: "center" });
            }
            renderNotifications(active);
          });
          notifList.appendChild(button);
        });
      };

      const applyLanguage = async (lang, persist = true) => {
        currentLanguage = SUPPORTED_LANGUAGES.includes(lang) ? lang : "en";
        document.documentElement.setAttribute("lang", currentLanguage);
        if (persist) {
          try {
            window.localStorage.setItem(LANGUAGE_KEY, currentLanguage);
          } catch (_error) {}
        }

        document.title = t("admin_title");
        setTextById("adminMainTitle", t("admin_title"));
        setTextById("openClientApp", t("open_client_app"));
        setTextById("panelScheduleBtn", t("panel_schedule"));
        setTextById("panelClientsBtn", t("panel_clients"));
        setTextById("adminSubtitle", t("admin_subtitle"));
        setTextById("tourScheduleTitle", t("tour_schedule_title"));
        setTextById("tourScheduleHint", t("tour_schedule_hint"));
        setTextById("quickAddTitle", t("quick_add_title"));
        setTextById("quickAddHint", t("quick_add_hint"));
        setTextById("quickAddTypeLabel", t("quick_add_type"));
        setTextById("quickAddCityLabel", t("quick_add_city"));
        setTextById("quickAddStartLabel", t("quick_add_start"));
        setTextById("quickAddEndLabel", t("quick_add_end"));
        setTextById("quickAddNotesLabel", t("quick_add_notes"));
        setTextById("quickAddSubmit", t("quick_add_submit"));
        setTextById("addTourRow", t("add_stop"));
        setTextById("menuAddTourRow", t("add_stop"));
        setTextById("menuTourPartnersTitle", t("tour_partners_title"));
        setTextById("menuTourPartnersHint", t("tour_partners_hint"));
        setTextById("menuAddTourPartnerRow", t("add_partner"));
        setTextById("saveTourSchedule", t("save_tour_schedule"));
        setTextById("menuSaveTourSchedule", t("save_tour_schedule"));
        setTextById("cityWizardTitle", t("city_wizard_title"));
        setTextById("cityWizardHint", t("city_wizard_hint"));
        setTextById("cityWizardTimezoneHint", t("city_wizard_timezone_hint"));
        setTextById("menuTourScheduleHint", t("tour_schedule_hint"));
        setTextById("saveAvailability", t("save_city_schedule"));
        setTextById("clearCityTemplates", t("clear_template_blocks"));
        setTextById("menuAutoTemplateBlocksLabel", t("auto_template_blocks_label"));
        setTextById("menuClearAutoBlocks", t("disable_clear_auto_blocks"));
        setTextById("gallerySectionTitle", t("eye_candy_title"));
        setTextById("gallerySectionHint", t("eye_candy_hint"));
        setTextById("addGalleryRow", t("add_eye_candy"));
        setTextById("saveGallery", t("save_eye_candy"));
        setTextById("addPhotoRow", t("add_eye_candy"));
        setTextById("savePhotoConfig", t("save_eye_candy"));
        setTextById("requestsSectionTitle", t("requests_title"));
        setTextById("refreshRequests", t("refresh"));
        setTextById("legendBlockedLabel", t("legend_blocked"));
        setTextById("legendBookingLabel", t("legend_booking"));
        setTextById("legendOutcallLabel", t("legend_outcall"));
        setTextById("legendPaidLabel", t("legend_paid"));
        setTextById("legendMaybeLabel", t("legend_maybe"));
        setTextById("legendCityLabel", t("legend_city"));
        const customersLink = document.querySelector('a[href="customers.php"]');
        if (customersLink) customersLink.textContent = t("costumer_directory");

        const statusLabels = {
          all: t("all"),
          pending: t("pending"),
          maybe: t("maybe"),
          accepted: t("accepted"),
          paid: t("paid"),
          blacklisted: t("blacklisted"),
          declined: t("declined"),
          cancelled: t("cancelled"),
        };
        Object.entries(statusLabels).forEach(([value, label]) => {
          const option = document.querySelector(`#statusFilter option[value="${value}"]`);
          if (option) option.textContent = label;
        });
        const quickTypeTourOption = document.querySelector('#quickAddType option[value="tour"]');
        if (quickTypeTourOption) quickTypeTourOption.textContent = t("quick_add_type_tour");
        const quickTypeBlockOption = document.querySelector('#quickAddType option[value="block"]');
        if (quickTypeBlockOption) quickTypeBlockOption.textContent = t("quick_add_type_block");
        if (quickAddNotes) {
          quickAddNotes.placeholder = t("quick_add_notes_placeholder");
        }

        languageButtons.forEach((button) => {
          button.setAttribute("aria-pressed", button.dataset.languageChoice === currentLanguage ? "true" : "false");
        });
        if (accountLanguageSelect) {
          accountLanguageSelect.value = currentLanguage;
        }

        renderTourSchedule(touringStops);
        renderTourPartners(tourPartners);
        renderGallery(readGalleryFromUI().length ? readGalleryFromUI() : []);
        renderCityScheduleWizard();
        await loadRequests();
      };

      languageButtons.forEach((button) => {
        button.addEventListener("click", () => {
          applyLanguage(button.dataset.languageChoice, true);
        });
      });

      panelButtons.forEach((button) => {
        button.addEventListener("click", () => {
          setAdminPanel(button.dataset.adminPanel || "schedule", true);
        });
      });

      if (statusFilter) {
        statusFilter.value = "all";
      }

      if (adminMenuToggleBtn) {
        adminMenuToggleBtn.addEventListener("click", () => openAdminMenu());
      }
      if (adminMenuCloseBtn) {
        adminMenuCloseBtn.addEventListener("click", () => closeAdminMenu());
      }
      adminMenuOpenButtons.forEach((button) => {
        button.addEventListener("click", () => {
          showAdminMenuPage(button.getAttribute("data-menu-open"));
        });
      });
      adminMenuBackButtons.forEach((button) => {
        button.addEventListener("click", () => showAdminMenuHome());
      });
      if (adminMenuBackdrop) {
        adminMenuBackdrop.addEventListener("click", () => closeAdminMenu());
      }
      document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && adminMenuDrawer?.classList.contains("open")) {
          closeAdminMenu();
        }
      });

      if (notifToggleBtn && notifPanel) {
        notifToggleBtn.addEventListener("click", () => {
          notifPanel.classList.toggle("hidden");
        });
        document.addEventListener("click", (event) => {
          const target = event.target;
          if (
            target instanceof Node &&
            !notifPanel.contains(target) &&
            !notifToggleBtn.contains(target)
          ) {
            notifPanel.classList.add("hidden");
          }
        });
      }

      if (accountPhotoInput) {
        accountPhotoInput.addEventListener("change", () => {
          const file = accountPhotoInput.files && accountPhotoInput.files[0];
          if (!file) return;
          const reader = new FileReader();
          reader.onload = () => {
            const dataUrl = String(reader.result || "");
            if (accountPhotoPreview && dataUrl) {
              accountPhotoPreview.src = dataUrl;
              accountPhotoPreview.hidden = false;
            }
            const current = readAccountCenter();
            writeStoredObject(ACCOUNT_CENTER_KEY, { ...current, profilePic: dataUrl });
          };
          reader.readAsDataURL(file);
        });
      }
      if (saveAccountCenterBtn) {
        saveAccountCenterBtn.addEventListener("click", saveAccountCenter);
      }
      if (accountAccentColorInput) {
        accountAccentColorInput.addEventListener("input", () => {
          applyAccentTheme(accountAccentColorInput.value || DEFAULT_ACCENT_COLOR);
        });
      }
      if (accountLanguageSelect) {
        accountLanguageSelect.addEventListener("change", () => {
          const language = accountLanguageSelect.value === "fr" ? "fr" : "en";
          applyLanguage(language, true);
        });
      }

      if (addServiceDurationBtn) {
        addServiceDurationBtn.addEventListener("click", () => {
          createMenuListRow(serviceDurationList, "Duration (ex: 1.5h)", "Price");
        });
      }
      if (addServicePackageBtn) {
        addServicePackageBtn.addEventListener("click", () => {
          createMenuListRow(servicePackageList, "Package entries (comma)", "Price");
        });
      }
      if (addServiceAddonBtn) {
        addServiceAddonBtn.addEventListener("click", () => {
          createMenuListRow(serviceAddonList, "Addon item", "Price");
        });
      }
      if (saveServicesConfigBtn) {
        saveServicesConfigBtn.addEventListener("click", saveServicesConfig);
      }

      if (menuScheduleApply) {
        menuScheduleApply.addEventListener("click", () => {
          applyScheduleFromMenu();
        });
      }

      if (menuTourAddBtn) {
        menuTourAddBtn.addEventListener("click", async () => {
          if (!menuTourStatus) return;
          menuTourStatus.textContent = "";
          const city = String(menuTourCity?.value || "").trim();
          const start = normalizeUiDate(menuTourStart?.value || "");
          const end = normalizeUiDate(menuTourEnd?.value || "");
          const firstStart = String(menuTourFirstStart?.value || "00:00").trim() || "00:00";
          const lastEnd = String(menuTourLastEnd?.value || "23:59").trim() || "23:59";
          if (!city || !start || !end) {
            menuTourStatus.textContent = "City and date range are required.";
            return;
          }
          if (start > end) {
            menuTourStatus.textContent = "End date must be after start date.";
            return;
          }
          if (!isValidTime(firstStart) || !isValidTime(lastEnd)) {
            menuTourStatus.textContent = "Use valid start/end times.";
            return;
          }
          if (!touringStops.length) {
            await loadTourSchedule();
          }
          const current = normalizeTouringEntries(touringStops);
          const ok = await saveTouringEntries([...current, { type: "tour", city, start, end }], menuTourStatus);
          if (!ok) return;
          const scheduleId = makeScheduleId({ city, start, end });
          const index = citySchedules.findIndex((entry) => entry.id === scheduleId);
          const base = index >= 0 ? citySchedules[index] : normalizeCitySchedule({ city, start, end });
          const next = normalizeCitySchedule({
            ...base,
            city,
            start,
            end,
            ready_start: firstStart,
            leave_day_end: lastEnd,
          });
          if (index >= 0) {
            citySchedules[index] = next;
          } else {
            citySchedules.push(next);
          }
          renderCityScheduleWizard();
          applyCityTemplateBlocks({ announce: false });
          renderBlockedSlots();
          renderCalendarView();
          await saveAvailability();
          menuTourStatus.textContent = "Touring stop added.";
          if (menuTourCity) menuTourCity.value = "";
          if (menuTourStart) menuTourStart.value = "";
          if (menuTourEnd) menuTourEnd.value = "";
        });
      }

      const getKey = () => ADMIN_KEY;

      const headersWithKey = () => {
        const key = getKey();
        if (!key) return {};
        return { "X-Admin-Key": key };
      };

      const renderEmployeeList = (employees) => {
        if (!employeeList) return;
        const rows = Array.isArray(employees) ? employees : [];
        employeeList.innerHTML = "";
        if (!rows.length) {
          employeeList.innerHTML = '<p class="hint">No employees yet.</p>';
          return;
        }
        rows.forEach((entry) => {
          const username = String(entry?.username || "").trim();
          if (!username) return;
          const row = document.createElement("div");
          row.className = "menu-list-row";
          row.innerHTML = `
            <input type="text" value="${username}" readonly />
            <input type="text" value="${String(entry?.created_at || "").trim()}" readonly />
            <button class="btn ghost" type="button">Remove</button>
          `;
          const removeBtn = row.querySelector("button");
          if (removeBtn) {
            removeBtn.addEventListener("click", () => removeEmployee(username));
          }
          employeeList.appendChild(row);
        });
      };

      const loadEmployees = async () => {
        if (!CURRENT_ADMIN_IS_EMPLOYER || !employeeList) return;
        if (employeeStatus) employeeStatus.textContent = "";
        try {
          const response = await fetch("../api/admin/employees.php", {
            headers: { ...headersWithKey() },
            cache: "no-store",
          });
          const result = await response.json().catch(() => ({}));
          if (!response.ok || !result.ok) {
            throw new Error(result.error || `HTTP ${response.status}`);
          }
          renderEmployeeList(result.employees || []);
        } catch (error) {
          if (employeeStatus) {
            employeeStatus.textContent = `Failed to load employees${error?.message ? ` (${error.message})` : ""}.`;
          }
        }
      };

      const addEmployee = async () => {
        if (!CURRENT_ADMIN_IS_EMPLOYER || !employeeUsernameInput || !employeePasswordInput || !employeeStatus) return;
        const username = String(employeeUsernameInput.value || "").trim().toLowerCase();
        const password = String(employeePasswordInput.value || "");
        employeeStatus.textContent = "";
        if (!/^[a-z0-9._-]{3,40}$/i.test(username)) {
          employeeStatus.textContent = "Use 3-40 chars: letters, numbers, dot, dash, underscore.";
          return;
        }
        if (password.length < 8) {
          employeeStatus.textContent = "Password must be at least 8 characters.";
          return;
        }
        try {
          const response = await fetch("../api/admin/employees.php", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              ...headersWithKey(),
            },
            body: JSON.stringify({ action: "add", username, password }),
          });
          const result = await response.json().catch(() => ({}));
          if (!response.ok || !result.ok) {
            throw new Error(result.error || `HTTP ${response.status}`);
          }
          employeePasswordInput.value = "";
          employeeUsernameInput.value = "";
          employeeStatus.textContent = "Employee saved.";
          renderEmployeeList(result.employees || []);
        } catch (error) {
          employeeStatus.textContent = `Failed to save employee${error?.message ? ` (${error.message})` : ""}.`;
        }
      };

      const removeEmployee = async (username) => {
        if (!CURRENT_ADMIN_IS_EMPLOYER || !employeeStatus) return;
        const normalized = String(username || "").trim().toLowerCase();
        if (!normalized) return;
        employeeStatus.textContent = "";
        try {
          const response = await fetch("../api/admin/employees.php", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              ...headersWithKey(),
            },
            body: JSON.stringify({ action: "delete", username: normalized }),
          });
          const result = await response.json().catch(() => ({}));
          if (!response.ok || !result.ok) {
            throw new Error(result.error || `HTTP ${response.status}`);
          }
          employeeStatus.textContent = "Employee removed.";
          renderEmployeeList(result.employees || []);
        } catch (error) {
          employeeStatus.textContent = `Failed to remove employee${error?.message ? ` (${error.message})` : ""}.`;
        }
      };

      const populateTimezones = () => {
        if (!tourTzSelect) return;
        tourTzSelect.innerHTML = "";
        TIMEZONES.forEach((zone) => {
          const option = document.createElement("option");
          option.value = zone;
          option.textContent = zone.replace("_", " ");
          tourTzSelect.appendChild(option);
        });
      };

      const normalizeTimezone = (value) => {
        const tz = String(value || "").trim();
        if (tz && TIMEZONES.includes(tz)) {
          return tz;
        }
        return DEFAULT_TIMEZONE;
      };

      const applyTimezoneValue = (value) => {
        const tz = normalizeTimezone(value);
        if (tourTzSelect) {
          if (!Array.from(tourTzSelect.options).some((option) => option.value === tz)) {
            const option = document.createElement("option");
            option.value = tz;
            option.textContent = tz.replace("_", " ");
            tourTzSelect.appendChild(option);
          }
          tourTzSelect.value = tz;
        }
        return tz;
      };

      const getActiveTimezone = () => normalizeTimezone(tourTzSelect?.value);

      let blockedSlots = [];
      let maybeSlots = [];
      let requestSlots = [];
      let latestRequests = [];
      let hiddenBookingIds = new Set();
      let loadRequestsToken = 0;
      let recurringBlocks = [];
      let touringStops = [];
      let tourPartners = [];
      let citySchedules = [];
      let autoTemplateBlocksEnabled = false;
      let autoSaveTimer = null;
      let autoSaveInFlight = false;
      let autoSaveQueued = false;
      const SLOT_MINUTES = 30;
      const SLOT_TIMES = Array.from({ length: 48 }, (_, index) => {
        const total = index * SLOT_MINUTES;
        const hour = String(Math.floor(total / 60)).padStart(2, "0");
        const minute = String(total % 60).padStart(2, "0");
        return `${hour}:${minute}`;
      });

      const timeToMinutes = (timeValue) => {
        const [hour, minute] = String(timeValue || "").split(":").map((value) => Number(value));
        if (Number.isNaN(hour) || Number.isNaN(minute)) return null;
        return hour * 60 + minute;
      };

      const minutesToTime = (minutes) => {
        const hour = String(Math.floor(minutes / 60)).padStart(2, "0");
        const minute = String(minutes % 60).padStart(2, "0");
        return `${hour}:${minute}`;
      };

      const toDateKey = (date) => {
        const year = String(date.getUTCFullYear()).padStart(4, "0");
        const month = String(date.getUTCMonth() + 1).padStart(2, "0");
        const day = String(date.getUTCDate()).padStart(2, "0");
        return `${year}-${month}-${day}`;
      };

      const parseDateKey = (value) => {
        const parts = String(value || "").split("-").map((piece) => Number(piece));
        if (parts.length !== 3) return null;
        const [year, month, day] = parts;
        if (!year || !month || !day) return null;
        return new Date(Date.UTC(year, month - 1, day));
      };

      const CITY_TIMEZONE_MAP = {
        montreal: "America/Toronto",
        toronto: "America/Toronto",
        vancouver: "America/Vancouver",
        "london (uk)": "Europe/London",
        london: "Europe/London",
        berlin: "Europe/Berlin",
        paris: "Europe/Paris",
      };

      const CITY_MARKER_COLORS = [
        [255, 136, 189],
        [255, 176, 133],
        [149, 186, 255],
        [176, 220, 188],
        [206, 170, 245],
        [255, 215, 136],
        [132, 214, 226],
        [234, 171, 198],
      ];

      const normalizeCityName = (value) =>
        String(value || "")
          .trim()
          .toLowerCase()
          .replace(/\s+/g, " ");

      const getCityMarkerColor = (city, alpha = 0.42) => {
        const key = normalizeCityName(city);
        if (!key) return `rgba(180, 154, 177, ${alpha})`;
        let hash = 0;
        for (let i = 0; i < key.length; i += 1) {
          hash = (hash * 31 + key.charCodeAt(i)) % 2147483647;
        }
        const [r, g, b] = CITY_MARKER_COLORS[hash % CITY_MARKER_COLORS.length];
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
      };

      const makeScheduleId = (entry) =>
        `${String(entry?.start || "").trim()}|${String(entry?.end || "").trim()}|${normalizeCityName(entry?.city || "")}`;

      const getDefaultTimezoneForCity = (_city) => DEFAULT_TIMEZONE;

      const isValidTime = (value) => /^\d{2}:\d{2}$/.test(String(value || ""));
      const normalizeBufferMinutes = (value, fallback = 0) => {
        const fallbackNumber = Number(fallback);
        const safeFallback = Number.isFinite(fallbackNumber) ? fallbackNumber : 0;
        const parsed = Number(value);
        const normalized = Number.isFinite(parsed) ? parsed : safeFallback;
        return Math.max(0, Math.min(240, Math.round(normalized)));
      };

      const normalizeCitySchedule = (entry = {}) => {
        const city = String(entry.city || "").trim();
        const start = String(entry.start || "").trim();
        const end = String(entry.end || "").trim();
        const timezone = getDefaultTimezoneForCity(city);
        const dateKeys = (() => {
          const startDate = parseDateKey(start);
          const endDate = parseDateKey(end);
          if (!startDate || !endDate || startDate > endDate) {
            return [];
          }
          const list = [];
          const cursor = new Date(startDate.getTime());
          while (cursor <= endDate) {
            list.push(toDateKey(cursor));
            cursor.setUTCDate(cursor.getUTCDate() + 1);
          }
          return list;
        })();
        const validDateSet = new Set(dateKeys);
        const hasSleepDaysField = Array.isArray(entry.sleep_days);
        const hasBreakDaysField = Array.isArray(entry.break_days);
        const sleepDaysRaw = hasSleepDaysField ? entry.sleep_days : [];
        const breakDaysRaw = hasBreakDaysField ? entry.break_days : [];
        const bufferMinutes = normalizeBufferMinutes(entry.buffer_minutes, 0);
        const readyStart = isValidTime(entry.ready_start) ? String(entry.ready_start) : "00:00";
        const leaveDayEnd = isValidTime(entry.leave_day_end) ? String(entry.leave_day_end) : "23:59";
        const hasSleep = !!entry.has_sleep;
        const sleepDays = hasSleep
          ? Array.from(
              new Set(
                (Array.isArray(sleepDaysRaw) ? sleepDaysRaw : [])
                  .map((value) => String(value || "").trim())
                  .filter((value) => validDateSet.has(value))
              )
            )
          : [];
        const sleepStart = isValidTime(entry.sleep_start) ? String(entry.sleep_start) : "02:00";
        const sleepEnd = isValidTime(entry.sleep_end) ? String(entry.sleep_end) : "10:00";
        const hasBreak = !!entry.has_break;
        const breakDays = hasBreak
          ? Array.from(
              new Set(
                (Array.isArray(breakDaysRaw) ? breakDaysRaw : [])
                  .map((value) => String(value || "").trim())
                  .filter((value) => validDateSet.has(value))
              )
            )
          : [];
        const breakStart = isValidTime(entry.break_start) ? String(entry.break_start) : "16:00";
        const breakEnd = isValidTime(entry.break_end) ? String(entry.break_end) : "17:00";
        const step = Math.max(0, Math.min(4, Number(entry._step) || 0));
        const defaultSleepDays = hasSleep && !hasSleepDaysField ? dateKeys : sleepDays;
        const defaultBreakDays = hasBreak && !hasBreakDaysField ? dateKeys : breakDays;
        return {
          id: makeScheduleId({ city, start, end }),
          city,
          start,
          end,
          timezone,
          ready_start: readyStart,
          buffer_minutes: bufferMinutes,
          has_sleep: hasSleep,
          sleep_days: defaultSleepDays,
          sleep_start: sleepStart,
          sleep_end: sleepEnd,
          has_break: hasBreak,
          break_days: defaultBreakDays,
          break_start: breakStart,
          break_end: breakEnd,
          leave_day_end: leaveDayEnd,
          _step: step,
        };
      };

      const formatDateLabel = (dateKey) => {
        const date = parseDateKey(dateKey);
        if (!date) {
          return dateKey || "";
        }
        return new Intl.DateTimeFormat("en-US", {
          timeZone: "UTC",
          month: "short",
          day: "numeric",
          year: "numeric",
        }).format(date);
      };

      const getDateRangeKeys = (startKey, endKey) => {
        const startDate = parseDateKey(startKey);
        const endDate = parseDateKey(endKey);
        if (!startDate || !endDate || startDate > endDate) {
          return [];
        }
        const dates = [];
        const cursor = new Date(startDate.getTime());
        while (cursor <= endDate) {
          dates.push(toDateKey(cursor));
          cursor.setUTCDate(cursor.getUTCDate() + 1);
        }
        return dates;
      };

      const pushTemplateRange = (target, schedule, dateKey, startTime, endTime, reason) => {
        const startMinutes = timeToMinutes(startTime);
        const endMinutes = timeToMinutes(endTime);
        if (startMinutes === null || endMinutes === null || endMinutes <= startMinutes) {
          return;
        }
        for (let minutes = startMinutes; minutes < endMinutes; minutes += SLOT_MINUTES) {
          const next = Math.min(minutes + SLOT_MINUTES, endMinutes);
          target.push({
            date: dateKey,
            start: minutesToTime(minutes),
            end: next >= 1440 ? "23:59" : minutesToTime(next),
            reason,
            kind: "template",
            template_id: schedule.id,
            city: schedule.city,
          });
        }
      };

      const buildTemplateBlocksForSchedule = (schedule) => {
        const blocks = [];
        const dates = getDateRangeKeys(schedule.start, schedule.end);
        if (!dates.length) {
          return blocks;
        }
        const sleepDaySet = new Set(Array.isArray(schedule.sleep_days) ? schedule.sleep_days : []);
        const breakDaySet = new Set(Array.isArray(schedule.break_days) ? schedule.break_days : []);
        dates.forEach((dateKey, index) => {
          if (
            schedule.has_sleep &&
            sleepDaySet.has(dateKey) &&
            isValidTime(schedule.sleep_start) &&
            isValidTime(schedule.sleep_end)
          ) {
            const sleepStart = timeToMinutes(schedule.sleep_start);
            const sleepEnd = timeToMinutes(schedule.sleep_end);
            if (sleepStart !== null && sleepEnd !== null) {
              if (sleepStart < sleepEnd) {
                pushTemplateRange(blocks, schedule, dateKey, schedule.sleep_start, schedule.sleep_end, "Sleep");
              } else if (sleepStart > sleepEnd) {
                pushTemplateRange(blocks, schedule, dateKey, schedule.sleep_start, "23:59", "Sleep");
                const nextDate = dates[index + 1];
                if (nextDate) {
                  pushTemplateRange(blocks, schedule, nextDate, "00:00", schedule.sleep_end, "Sleep");
                }
              }
            }
          }
          if (
            schedule.has_break &&
            breakDaySet.has(dateKey) &&
            isValidTime(schedule.break_start) &&
            isValidTime(schedule.break_end)
          ) {
            const breakStart = timeToMinutes(schedule.break_start);
            const breakEnd = timeToMinutes(schedule.break_end);
            if (breakStart !== null && breakEnd !== null) {
              if (breakStart < breakEnd) {
                pushTemplateRange(blocks, schedule, dateKey, schedule.break_start, schedule.break_end, "Break");
              } else if (breakStart > breakEnd) {
                pushTemplateRange(blocks, schedule, dateKey, schedule.break_start, "23:59", "Break");
                const nextDate = dates[index + 1];
                if (nextDate) {
                  pushTemplateRange(blocks, schedule, nextDate, "00:00", schedule.break_end, "Break");
                }
              }
            }
          }
        });
        return blocks;
      };

      const getTemplateBlocks = () => {
        const generated = [];
        citySchedules.forEach((schedule) => {
          generated.push(...buildTemplateBlocksForSchedule(schedule));
        });
        return generated;
      };

      const clearTemplateSlotsFromBlocked = () => {
        blockedSlots = normalizeBlockedSlots(blockedSlots.filter((slot) => slot && slot.kind !== "template"));
      };

      const applyCityTemplateBlocks = ({ announce = true } = {}) => {
        const baseSlots = blockedSlots.filter((slot) => slot && slot.kind !== "template");
        const templateSlots = autoTemplateBlocksEnabled ? getTemplateBlocks() : [];
        blockedSlots = normalizeBlockedSlots([...baseSlots, ...templateSlots]);
        if (menuAutoTemplateBlocks) {
          menuAutoTemplateBlocks.checked = !!autoTemplateBlocksEnabled;
        }
        renderBlockedSlots();
        renderCalendarView();
        if (announce && cityScheduleStatus) {
          cityScheduleStatus.textContent = autoTemplateBlocksEnabled ? "City templates applied." : t("auto_blocks_disabled");
        }
      };

      const blockFullDayForDate = (dateKey, reason, city = "") => {
        blockedSlots = blockedSlots.filter(
          (slot) => !slot || slot.date !== dateKey || (slot.kind !== "manual" && slot.kind !== "template")
        );
        const endOfDayMinutes = 24 * 60;
        for (let minutes = 0; minutes < endOfDayMinutes; minutes += SLOT_MINUTES) {
          const next = Math.min(minutes + SLOT_MINUTES, endOfDayMinutes);
          const endTime = next === endOfDayMinutes ? "23:59" : minutesToTime(next);
          blockedSlots.push({
            date: dateKey,
            start: minutesToTime(minutes),
            end: endTime,
            reason,
            kind: "manual",
            city,
          });
        }
      };

      const normalizeBlockedSlots = (slots) => {
        const normalized = [];
        (Array.isArray(slots) ? slots : []).forEach((slot) => {
          if (!slot || !slot.date || !slot.start || !slot.end) return;
          const startMinutes = timeToMinutes(slot.start);
          const endMinutes = timeToMinutes(slot.end);
          if (startMinutes === null || endMinutes === null || endMinutes <= startMinutes) return;
          const kind = slot.kind || "manual";
          const bookingType = slot.booking_type || "";
          const bookingStatus = slot.booking_status || "";
          const label = slot.label || "";
          const templateId = slot.template_id || "";
          const city = slot.city || "";
          for (let minutes = startMinutes; minutes < endMinutes; minutes += SLOT_MINUTES) {
            normalized.push({
              date: slot.date,
              start: minutesToTime(minutes),
              end: minutesToTime(Math.min(minutes + SLOT_MINUTES, endMinutes)),
              reason: slot.reason || "",
              kind,
              booking_type: bookingType,
              booking_status: bookingStatus,
              label,
              booking_id: slot.booking_id || "",
              template_id: templateId,
              city,
            });
          }
        });
        return normalized;
      };

      const sanitizeHiddenBookingIds = (values) => {
        const cleaned = new Set();
        (Array.isArray(values) ? values : []).forEach((value) => {
          const id = String(value || "").trim();
          if (id) {
            cleaned.add(id);
          }
        });
        return cleaned;
      };

      const getDateKey = (date) => {
        const parts = new Intl.DateTimeFormat("en-CA", {
          timeZone: getActiveTimezone(),
          year: "numeric",
          month: "2-digit",
          day: "2-digit",
        }).formatToParts(date);
        const values = Object.fromEntries(parts.map((part) => [part.type, part.value]));
        return `${values.year}-${values.month}-${values.day}`;
      };

      const getWeekDates = (startKey) => {
        const [year, month, day] = startKey.split("-").map((value) => Number(value));
        const base = new Date(Date.UTC(year, month - 1, day));
        return Array.from({ length: 7 }, (_, index) => {
          const date = new Date(base.getTime() + index * 86400000);
          const y = String(date.getUTCFullYear()).padStart(4, "0");
          const m = String(date.getUTCMonth() + 1).padStart(2, "0");
          const d = String(date.getUTCDate()).padStart(2, "0");
          return `${y}-${m}-${d}`;
        });
      };

      const getCalendarAnchorDate = () => {
        if (calendarView === "day" && calendarDay?.value) {
          return calendarDay.value;
        }
        if (calendarView === "month" && calendarMonth?.value) {
          return `${calendarMonth.value}-01`;
        }
        if (calendarStart?.value) {
          return calendarStart.value;
        }
        return getDateKey(new Date());
      };

      const getTourCityForDate = (dateKey) => {
        if (!dateKey) return "";
        const match = touringStops.find(
          (entry) => entry && entry.start && entry.end && entry.city && entry.start <= dateKey && dateKey <= entry.end
        );
        return match ? String(match.city).trim() : "";
      };

      const syncTourCityFromCalendar = () => {
        if (!tourCityInput) return "";
        const dateKey = getCalendarAnchorDate();
        const derivedCity = getTourCityForDate(dateKey);
        const fallbackCity = citySchedules[0]?.city || "";
        tourCityInput.value = derivedCity || fallbackCity;
        return tourCityInput.value.trim();
      };

      const formatDayLabel = (dateKey) => {
        const [year, month, day] = dateKey.split("-").map((value) => Number(value));
        const labelDate = new Date(Date.UTC(year, month - 1, day, 12, 0));
        return new Intl.DateTimeFormat("en-US", {
          timeZone: getActiveTimezone(),
          weekday: "short",
          month: "short",
          day: "numeric",
        }).format(labelDate);
      };

      const formatSlotDateShort = (dateKey) => {
        const parts = String(dateKey || "").split("-");
        if (parts.length !== 3) return "";
        return `${parts[1]}/${parts[2]}`;
      };

      const getWeekdayIndex = (dateKey) => {
        const parts = dateKey.split("-").map((value) => Number(value));
        if (parts.length !== 3) return 0;
        const labelDate = new Date(Date.UTC(parts[0], parts[1] - 1, parts[2], 12, 0));
        const label = new Intl.DateTimeFormat("en-US", {
          timeZone: getActiveTimezone(),
          weekday: "short",
        }).format(labelDate);
        const map = { Sun: 0, Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 };
        return map[label] ?? 0;
      };

      const isRecurringSlot = (dateKey, timeValue) => {
        if (!Array.isArray(recurringBlocks) || !recurringBlocks.length) return false;
        const targetMinutes = timeToMinutes(timeValue);
        if (targetMinutes === null) return false;
        const weekdayIndex = getWeekdayIndex(dateKey);
        return recurringBlocks.some((block) => {
          if (!block) return false;
          const days = Array.isArray(block.days) ? block.days : [];
          if (!days.includes(weekdayIndex)) return false;
          if (block.all_day) return true;
          const startMinutes = timeToMinutes(block.start);
          const endMinutes = timeToMinutes(block.end);
          if (startMinutes === null || endMinutes === null) return false;
          return targetMinutes >= startMinutes && targetMinutes < endMinutes;
        });
      };

      const getSlotEntry = (dateKey, timeValue) => {
        const targetMinutes = timeToMinutes(timeValue);
        if (targetMinutes === null) return null;
        let bookingEntry = null;
        requestSlots.forEach((slot) => {
          if (!slot || slot.date !== dateKey || slot.kind !== "booking") return;
          const startMinutes = timeToMinutes(slot.start);
          const endMinutes = timeToMinutes(slot.end);
          if (startMinutes === null || endMinutes === null) return;
          if (targetMinutes >= startMinutes && targetMinutes < endMinutes) {
            bookingEntry = slot;
          }
        });
        if (bookingEntry) {
          return bookingEntry;
        }
        let entry = null;
        blockedSlots.forEach((slot) => {
          if (!slot || slot.date !== dateKey || slot.kind === "booking") return;
          const startMinutes = timeToMinutes(slot.start);
          const endMinutes = timeToMinutes(slot.end);
          if (startMinutes === null || endMinutes === null) return;
          if (targetMinutes >= startMinutes && targetMinutes < endMinutes) {
            entry = slot;
          }
        });
        return entry;
      };

      const getMaybeEntries = (dateKey, timeValue) => {
        const targetMinutes = timeToMinutes(timeValue);
        if (targetMinutes === null) return [];
        return maybeSlots.filter((slot) => {
          if (!slot || slot.date !== dateKey) return false;
          const startMinutes = timeToMinutes(slot.start);
          const endMinutes = timeToMinutes(slot.end);
          if (startMinutes === null || endMinutes === null) return false;
          return targetMinutes >= startMinutes && targetMinutes < endMinutes;
        });
      };

      const focusRequestCardById = (requestId) => {
        const normalizedId = String(requestId || "").trim();
        if (!normalizedId || !requestsList) return;
        setAdminPanel("clients", true);
        window.setTimeout(() => {
          const card = Array.from(requestsList.querySelectorAll("[data-request-id]")).find(
            (node) => String(node.getAttribute("data-request-id") || "").trim() === normalizedId
          );
          if (!card) return;
          card.classList.add("flash-focus");
          card.scrollIntoView({ behavior: "smooth", block: "center" });
          window.setTimeout(() => card.classList.remove("flash-focus"), 1400);
        }, 40);
      };

      const getBookingGroupId = (slot) => {
        if (!slot) return "";
        const bookingId = String(slot.booking_id || "").trim();
        if (bookingId) {
          return `id:${bookingId}`;
        }
        const date = String(slot.date || "").trim();
        const label = String(slot.label || "").trim().toLowerCase();
        const bookingType = String(slot.booking_type || "").trim().toLowerCase();
        if (!date || !label) {
          return "";
        }
        return `legacy:${date}|${label}|${bookingType}`;
      };

      const buildBookingStartMap = () => {
        const map = {};
        requestSlots.forEach((slot) => {
          if (!slot || slot.kind !== "booking") return;
          const key = getBookingGroupId(slot);
          if (!key) return;
          const value = `${slot.date} ${slot.start}`;
          if (!map[key] || value < map[key]) {
            map[key] = value;
          }
        });
        return map;
      };

      const getAdjacentSlotTime = (timeValue, direction = 1) => {
        const minutes = timeToMinutes(timeValue);
        if (minutes === null) return null;
        const nextMinutes = minutes + direction * SLOT_MINUTES;
        if (nextMinutes < 0 || nextMinutes >= 24 * 60) return null;
        return minutesToTime(nextMinutes);
      };

      const getSlotGroupKey = (slot) => {
        if (!slot) return "";
        const kind = String(slot.kind || "manual").trim();
        const city = normalizeCityName(slot.city || "");
        if (kind === "booking") {
          const bookingKey = getBookingGroupId(slot);
          return bookingKey ? `booking|${bookingKey}` : `booking|${slot.label || ""}`;
        }
        if (kind === "template") {
          return `template|${slot.template_id || slot.reason || ""}|${city}`;
        }
        return `${kind}|${slot.reason || ""}|${city}`;
      };

      const getSlotGroupFlags = (dateKey, timeValue, entry) => {
        if (!entry) return { hasPrevious: false, hasNext: false };
        const groupKey = getSlotGroupKey(entry);
        if (!groupKey) return { hasPrevious: false, hasNext: false };
        const previousTime = getAdjacentSlotTime(timeValue, -1);
        const nextTime = getAdjacentSlotTime(timeValue, 1);
        const previousEntry = previousTime ? getSlotEntry(dateKey, previousTime) : null;
        const nextEntry = nextTime ? getSlotEntry(dateKey, nextTime) : null;
        return {
          hasPrevious: !!previousEntry && getSlotGroupKey(previousEntry) === groupKey,
          hasNext: !!nextEntry && getSlotGroupKey(nextEntry) === groupKey,
        };
      };

      const normalizeRangeEndMinutes = (value) => {
        if (value === null) return null;
        if (value >= 1439) return 1440;
        return value;
      };

      const formatRangeTime = (minutes) => {
        if (!Number.isFinite(minutes)) return "";
        if (minutes >= 24 * 60) return "24:00";
        return minutesToTime(minutes);
      };

      const buildManualBlockGroups = () => {
        const manualEntries = blockedSlots
          .map((slot, index) => ({ slot, index }))
          .filter(({ slot }) => slot && slot.kind === "manual")
          .map(({ slot, index }) => {
            const startMinutes = timeToMinutes(slot.start);
            const endMinutes = normalizeRangeEndMinutes(timeToMinutes(slot.end));
            if (startMinutes === null || endMinutes === null || endMinutes <= startMinutes) {
              return null;
            }
            return {
              index,
              date: slot.date,
              reason: String(slot.reason || "").trim(),
              reasonKey: String(slot.reason || "").trim().toLowerCase(),
              city: String(slot.city || "").trim(),
              cityKey: normalizeCityName(slot.city || ""),
              startMinutes,
              endMinutes,
            };
          })
          .filter(Boolean)
          .sort((a, b) => {
            const dateCmp = String(a.date).localeCompare(String(b.date));
            if (dateCmp !== 0) return dateCmp;
            return a.startMinutes - b.startMinutes;
          });

        const groups = [];
        manualEntries.forEach((entry) => {
          const current = groups[groups.length - 1];
          if (
            current &&
            current.date === entry.date &&
            current.cityKey === entry.cityKey &&
            current.reasonKey === entry.reasonKey &&
            entry.startMinutes <= current.endMinutes
          ) {
            current.endMinutes = Math.max(current.endMinutes, entry.endMinutes);
            current.indexes.push(entry.index);
            return;
          }
          groups.push({
            date: entry.date,
            reason: entry.reason,
            reasonKey: entry.reasonKey,
            city: entry.city,
            cityKey: entry.cityKey,
            startMinutes: entry.startMinutes,
            endMinutes: entry.endMinutes,
            indexes: [entry.index],
          });
        });

        return groups;
      };

      let calendarView = "week";

      const setCalendarView = (view) => {
        calendarView = view;
        calendarViewButtons.forEach((btn) => {
          btn.setAttribute("aria-pressed", btn.dataset.calendarView === view ? "true" : "false");
        });
        calendarFields.forEach((field) => {
          field.hidden = field.dataset.calendarField !== view;
        });
        syncTourCityFromCalendar();
        renderCalendarView();
      };

      const renderTimeGrid = (dates) => {
        const bookingStartMap = buildBookingStartMap();
        const dateCityMap = Object.fromEntries(
          dates.map((dateKey) => [dateKey, getTourCityForDate(dateKey)])
        );
        calendarGrid.innerHTML = "";
        calendarGrid.classList.remove("month");
        calendarGrid.style.gridTemplateColumns = `90px repeat(${dates.length}, minmax(90px, 1fr))`;

        const headCell = document.createElement("div");
        headCell.className = "calendar-cell calendar-head";
        headCell.textContent = "Time";
        calendarGrid.appendChild(headCell);

        dates.forEach((dateKey) => {
          const cell = document.createElement("div");
          cell.className = "calendar-cell calendar-head";
          cell.dataset.date = dateKey;
          const city = dateCityMap[dateKey] || "";
          if (city) {
            const title = document.createElement("span");
            title.className = "calendar-head-city";
            title.style.setProperty("--city-color", getCityMarkerColor(city, 0.56));
            title.textContent = formatDayLabel(dateKey);
            cell.title = city;
            cell.appendChild(title);
          } else {
            cell.textContent = formatDayLabel(dateKey);
          }
          calendarGrid.appendChild(cell);
        });

        SLOT_TIMES.forEach((timeValue) => {
          const timeCell = document.createElement("div");
          timeCell.className = "calendar-cell calendar-time";
          timeCell.textContent = timeValue;
          calendarGrid.appendChild(timeCell);

          dates.forEach((dateKey) => {
            const slotButton = document.createElement("button");
            slotButton.type = "button";
            slotButton.className = "calendar-cell calendar-slot";
            slotButton.dataset.date = dateKey;
            slotButton.dataset.time = timeValue;
            slotButton.dataset.dateShort = formatSlotDateShort(dateKey);
            const dateCity = dateCityMap[dateKey] || "";
            const entry = getSlotEntry(dateKey, timeValue);
            const maybeEntries = getMaybeEntries(dateKey, timeValue);
            const maybePrimary = maybeEntries[0] || null;
            const slotCity = String(entry?.city || maybePrimary?.city || dateCity || "").trim();
            if (slotCity) {
              slotButton.dataset.city = slotCity;
              const cityDot = document.createElement("span");
              cityDot.className = "slot-city-dot";
              cityDot.style.setProperty("--city-color", getCityMarkerColor(slotCity, 0.48));
              slotButton.appendChild(cityDot);
            }
            if (entry) {
              const grouping = getSlotGroupFlags(dateKey, timeValue, entry);
              if (grouping.hasPrevious || grouping.hasNext) {
                slotButton.classList.add("slot-grouped");
                if (grouping.hasPrevious && grouping.hasNext) {
                  slotButton.classList.add("slot-group-middle");
                } else if (grouping.hasNext) {
                  slotButton.classList.add("slot-group-start");
                } else if (grouping.hasPrevious) {
                  slotButton.classList.add("slot-group-end");
                }
              }
              if (entry.kind === "booking") {
                slotButton.classList.add("booking");
                if (entry.booking_type === "outcall") {
                  slotButton.classList.add("outcall");
                }
                if (entry.booking_status === "paid") {
                  slotButton.classList.add("paid");
                }
                const key = getBookingGroupId(entry);
                const startKey = key ? bookingStartMap[key] : "";
                if (key && startKey === `${entry.date} ${entry.start}`) {
                  slotButton.textContent = entry.label || "Booked";
                }
                const titleLabel = entry.label ? `${entry.label} - ` : "";
                const citySuffix = slotCity ? ` - ${slotCity}` : "";
                slotButton.title = `${titleLabel}${entry.booking_type || "incall"} (${entry.booking_status || "paid"})${citySuffix}`;
              } else if (entry.kind === "template") {
                slotButton.classList.add("blocked");
                slotButton.classList.add("template");
                const citySuffix = entry.city ? ` (${entry.city})` : "";
                slotButton.title = (entry.reason || "Template block") + citySuffix;
              } else {
                slotButton.classList.add("blocked");
                const citySuffix = slotCity ? ` (${slotCity})` : "";
                slotButton.title = (entry.reason || "Blocked") + citySuffix;
              }
            } else if (isRecurringSlot(dateKey, timeValue)) {
              slotButton.classList.add("blocked");
              slotButton.classList.add("recurring");
              slotButton.title = "Recurring block";
            }
            if (maybeEntries.length) {
              slotButton.classList.add("maybe");
              const maybeBadge = document.createElement("span");
              maybeBadge.className = "slot-maybe-count";
              maybeBadge.textContent = maybeEntries.length > 1 ? `?${maybeEntries.length}` : "?";
              slotButton.appendChild(maybeBadge);
              const maybeNames = maybeEntries
                .map((slot) => String(slot.label || "").trim())
                .filter(Boolean)
                .slice(0, 4)
                .join(", ");
              const moreCount = maybeEntries.length > 4 ? ` +${maybeEntries.length - 4}` : "";
              const maybeTitle = `Maybe: ${maybeNames || t("unknown")}${moreCount}`;
              slotButton.title = slotButton.title ? `${slotButton.title} | ${maybeTitle}` : maybeTitle;
            }
            slotButton.addEventListener("click", () => {
              if (entry && entry.kind === "booking") {
                const bookingId = String(entry.booking_id || "").trim();
                if (bookingId) {
                  focusRequestCardById(bookingId);
                }
                return;
              }
              if (entry && entry.kind === "template") {
                return;
              }
              if (!entry && maybeEntries.length) {
                const firstMaybeId = String(maybeEntries[0]?.id || "").trim();
                if (firstMaybeId) {
                  focusRequestCardById(firstMaybeId);
                }
              }
              const start = timeValue;
              const end = minutesToTime(timeToMinutes(timeValue) + SLOT_MINUTES);
              const clickedStart = timeToMinutes(start);
              const clickedEnd = timeToMinutes(end);
              const hasOverlap =
                clickedStart !== null &&
                clickedEnd !== null &&
                blockedSlots.some((slot) => {
                  if (!slot || slot.date !== dateKey) return false;
                  if (slot.kind === "booking" || slot.kind === "template") return false;
                  const slotStart = timeToMinutes(slot.start);
                  const slotEnd = normalizeRangeEndMinutes(timeToMinutes(slot.end));
                  if (slotStart === null || slotEnd === null) return false;
                  return clickedStart < slotEnd && clickedEnd > slotStart;
                });
              if (hasOverlap && clickedStart !== null && clickedEnd !== null) {
                blockedSlots = blockedSlots.filter((slot) => {
                  if (!slot || slot.date !== dateKey) return true;
                  if (slot.kind === "booking" || slot.kind === "template") return true;
                  const slotStart = timeToMinutes(slot.start);
                  const slotEnd = normalizeRangeEndMinutes(timeToMinutes(slot.end));
                  if (slotStart === null || slotEnd === null) return true;
                  return !(clickedStart < slotEnd && clickedEnd > slotStart);
                });
              } else {
                blockedSlots.push({ date: dateKey, start, end, reason: "", kind: "manual", city: slotCity });
              }
              renderBlockedSlots();
              renderCalendarView();
              queueAutoSave(t("saving_city_schedule"), { persist: true });
            });
            slotButton.addEventListener("dblclick", (event) => {
              if (!entry || entry.kind !== "booking") return;
              const bookingId = String(entry.booking_id || "").trim();
              if (!bookingId) return;
              event.preventDefault();
              hiddenBookingIds.add(bookingId);
              requestSlots = buildConfirmedSlotsFromRequests(latestRequests);
              maybeSlots = buildMaybeSlotsFromRequests(latestRequests);
              renderCalendarView();
              requestsStatus.textContent = t("grid_hidden");
              queueAutoSave(t("saving_city_schedule"), { persist: true });
            });
            calendarGrid.appendChild(slotButton);
          });
        });
      };

      const renderWeekCalendar = () => {
        if (!calendarGrid || !calendarStart) return;
        const startKey = calendarStart.value || getDateKey(new Date());
        const dates = getWeekDates(startKey);
        renderTimeGrid(dates);
      };

      const renderDayCalendar = () => {
        if (!calendarGrid || !calendarDay) return;
        const dayKey = calendarDay.value || getDateKey(new Date());
        renderTimeGrid([dayKey]);
      };

      const getMonthGrid = (year, monthIndex) => {
        const first = new Date(Date.UTC(year, monthIndex, 1));
        const startDay = first.getUTCDay();
        const daysInMonth = new Date(Date.UTC(year, monthIndex + 1, 0)).getUTCDate();
        const totalCells = Math.ceil((startDay + daysInMonth) / 7) * 7;
        return Array.from({ length: totalCells }, (_, index) => {
          const day = index - startDay + 1;
          if (day < 1 || day > daysInMonth) return null;
          const month = String(monthIndex + 1).padStart(2, "0");
          const date = String(day).padStart(2, "0");
          return `${year}-${month}-${date}`;
        });
      };

      const getDaySummary = (dateKey) => {
        const bookingIds = new Set();
        const paidIds = new Set();
        const maybeIds = new Set();
        const cities = new Set();
        let hasManual = false;
        blockedSlots.forEach((slot) => {
          if (!slot || slot.date !== dateKey) return;
          const city = String(slot.city || "").trim();
          if (city) {
            cities.add(city);
          }
          if (slot.kind === "booking") return;
          hasManual = true;
        });
        requestSlots.forEach((slot) => {
          if (!slot || slot.date !== dateKey || slot.kind !== "booking") return;
          const key = slot.booking_id || `${slot.label}-${slot.start}`;
          bookingIds.add(key);
          if (slot.booking_status === "paid") {
            paidIds.add(key);
          }
          const city = String(slot.city || "").trim();
          if (city) {
            cities.add(city);
          }
        });
        maybeSlots.forEach((slot) => {
          if (!slot || slot.date !== dateKey) return;
          const key = String(slot.id || slot.label || `${slot.date}-${slot.start}`).trim();
          if (key) {
            maybeIds.add(key);
          }
          const city = String(slot.city || "").trim();
          if (city) {
            cities.add(city);
          }
        });
        const weekdayIndex = getWeekdayIndex(dateKey);
        const hasRecurring = recurringBlocks.some((block) => {
          if (!block) return false;
          const days = Array.isArray(block.days) ? block.days : [];
          if (!days.includes(weekdayIndex)) return false;
          if (block.all_day) return true;
          const startMinutes = timeToMinutes(block.start);
          const endMinutes = timeToMinutes(block.end);
          return startMinutes !== null && endMinutes !== null;
        });
        return {
          bookings: bookingIds.size,
          paid: paidIds.size,
          maybe: maybeIds.size,
          manual: hasManual,
          recurring: hasRecurring,
          cities: Array.from(cities),
        };
      };

      const renderMonthCalendar = () => {
        if (!calendarGrid || !calendarMonth) return;
        const monthValue = calendarMonth.value || getDateKey(new Date()).slice(0, 7);
        const [yearStr, monthStr] = monthValue.split("-");
        const year = Number(yearStr);
        const monthIndex = Number(monthStr) - 1;
        if (!year || Number.isNaN(monthIndex)) return;
        calendarGrid.innerHTML = "";
        calendarGrid.classList.add("month");

        const weekdays = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
        weekdays.forEach((label) => {
          const head = document.createElement("div");
          head.className = "calendar-cell calendar-head";
          head.textContent = label;
          calendarGrid.appendChild(head);
        });

        const cells = getMonthGrid(year, monthIndex);
        cells.forEach((dateKey) => {
          const cell = document.createElement("div");
          cell.className = "month-cell";
          if (!dateKey) {
            cell.classList.add("muted");
            calendarGrid.appendChild(cell);
            return;
          }
          const dayNumber = Number(dateKey.split("-")[2]);
          const dayLabel = document.createElement("div");
          dayLabel.className = "month-day";
          dayLabel.textContent = String(dayNumber);
          cell.appendChild(dayLabel);

          const summary = getDaySummary(dateKey);
          const badgeWrap = document.createElement("div");
          badgeWrap.className = "month-badges";
          if (summary.bookings > 0) {
            const badge = document.createElement("span");
            badge.className = "month-badge booking";
            badge.textContent = `Bookings ${summary.bookings}`;
            badgeWrap.appendChild(badge);
          }
          if (summary.paid > 0) {
            const badge = document.createElement("span");
            badge.className = "month-badge paid";
            badge.textContent = `Paid ${summary.paid}`;
            badgeWrap.appendChild(badge);
          }
          if (summary.maybe > 0) {
            const badge = document.createElement("span");
            badge.className = "month-badge maybe";
            badge.textContent = `${t("maybe")} ${summary.maybe}`;
            badgeWrap.appendChild(badge);
          }
          if (summary.manual || summary.recurring) {
            const badge = document.createElement("span");
            badge.className = "month-badge blocked";
            badge.textContent = "Blocked";
            badgeWrap.appendChild(badge);
          }
          if (badgeWrap.childElementCount > 0) {
            cell.appendChild(badgeWrap);
          }
          const city = getTourCityForDate(dateKey);
          const cityDots = Array.from(new Set([city, ...(summary.cities || [])].filter(Boolean))).slice(0, 3);
          if (cityDots.length) {
            const cityDotWrap = document.createElement("div");
            cityDotWrap.className = "month-city-dots";
            cityDots.forEach((name) => {
              const dot = document.createElement("span");
              dot.className = "month-city-dot";
              dot.style.setProperty("--city-color", getCityMarkerColor(name, 0.5));
              dot.title = name;
              cityDotWrap.appendChild(dot);
            });
            cell.appendChild(cityDotWrap);
          }
          cell.addEventListener("click", () => {
            if (calendarDay) {
              calendarDay.value = dateKey;
            }
            setCalendarView("day");
          });
          calendarGrid.appendChild(cell);
        });
      };

      const renderCalendarView = () => {
        syncTourCityFromCalendar();
        if (!calendarGrid) return;
        if (calendarView === "day") {
          renderDayCalendar();
          return;
        }
        if (calendarView === "month") {
          renderMonthCalendar();
          return;
        }
        renderWeekCalendar();
      };

      const renderBlockedSlots = () => {
        if (!blockedList) return;
        const manualGroups = buildManualBlockGroups();
        if (!manualGroups.length) {
          blockedList.textContent = "No manual blocked slots yet.";
          return;
        }
        blockedList.innerHTML = manualGroups
          .map((group, index) => {
            const reason = group.reason ? ` - ${group.reason}` : "";
            const city = group.city ? ` (${group.city})` : "";
            return `<div data-index="${index}">${group.date} ${formatRangeTime(group.startMinutes)}-${formatRangeTime(group.endMinutes)}${city}${reason} <button data-remove="${index}" class="btn ghost" type="button">${t("remove")}</button></div>`;
          })
          .join("");
        blockedList.querySelectorAll("button[data-remove]").forEach((btn) => {
          btn.addEventListener("click", () => {
            const idx = Number(btn.dataset.remove);
            if (Number.isNaN(idx)) return;
            const group = manualGroups[idx];
            if (!group) return;
            const removalSet = new Set(group.indexes);
            blockedSlots = blockedSlots.filter((_slot, i) => !removalSet.has(i));
            renderBlockedSlots();
            renderCalendarView();
            queueAutoSave(t("saving_city_schedule"), { persist: true });
          });
        });
      };

      const getSelectedRecurringDays = () => {
        if (!recurringDays) return [];
        return Array.from(recurringDays.querySelectorAll("input[type=\"checkbox\"]:checked"))
          .map((input) => Number(input.value))
          .filter((value) => !Number.isNaN(value));
      };

      const formatRecurringDays = (days) => {
        const labels = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
        return (Array.isArray(days) ? days : [])
          .map((day) => labels[day])
          .filter(Boolean)
          .join(", ");
      };

      const renderRecurringDayChoices = () => {
        if (!recurringDays) return;
        const labels = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
        recurringDays.innerHTML = labels
          .map(
            (label, index) =>
              `<label><input type="checkbox" value="${index}" /> ${label}</label>`
          )
          .join("");
      };

      const renderRecurringList = () => {
        if (!recurringList) return;
        if (!recurringBlocks.length) {
          recurringList.textContent = "No recurring blocks yet.";
          return;
        }
        recurringList.innerHTML = recurringBlocks
          .map((block, index) => {
            const daysLabel = formatRecurringDays(block.days);
            const timeLabel = block.all_day ? "All day" : `${block.start || ""}-${block.end || ""}`;
            const reason = block.reason ? ` - ${block.reason}` : "";
            return `<div data-index=\"${index}\">${daysLabel} | ${timeLabel}${reason} <button data-remove=\"${index}\" class=\"btn ghost\" type=\"button\">${t("remove")}</button></div>`;
          })
          .join("");
        recurringList.querySelectorAll("button[data-remove]").forEach((btn) => {
          btn.addEventListener("click", () => {
            const idx = Number(btn.dataset.remove);
            if (Number.isNaN(idx)) return;
            recurringBlocks = recurringBlocks.filter((_, i) => i !== idx);
            renderRecurringList();
            renderCalendarView();
            queueAutoSave(t("saving_city_schedule"), { persist: true });
          });
        });
      };

      const setBlockedListVisible = (visible) => {
        if (!blockedList || !toggleBlockedListBtn) return;
        blockedList.classList.toggle("hidden", !visible);
        toggleBlockedListBtn.textContent = visible ? "Hide blocked slots" : "Show blocked slots";
      };

      const loadAvailability = async () => {
        availabilityStatus.textContent = "";
        try {
          const response = await fetch("../api/availability.php", { cache: "no-store" });
          const payloadText = await response.text();
          let data = {};
          if (payloadText) {
            data = JSON.parse(payloadText);
          }
          if (!response.ok) {
            throw new Error(data.error || `HTTP ${response.status}`);
          }
          tourCityInput.value = data.tour_city || "";
          applyTimezoneValue(data.tour_timezone);
          bufferInput.value = String(normalizeBufferMinutes(data.buffer_minutes, 30));
          blockedSlots = normalizeBlockedSlots(data.blocked).filter((slot) => slot && slot.kind !== "booking");
          recurringBlocks = Array.isArray(data.recurring) ? data.recurring : [];
          hiddenBookingIds = sanitizeHiddenBookingIds(data.hidden_booking_ids);
          requestSlots = buildConfirmedSlotsFromRequests(latestRequests);
          maybeSlots = buildMaybeSlotsFromRequests(latestRequests);
          autoTemplateBlocksEnabled = !!data.auto_template_blocks;
          if (menuAutoTemplateBlocks) {
            menuAutoTemplateBlocks.checked = autoTemplateBlocksEnabled;
          }
          citySchedules = (Array.isArray(data.city_schedules) ? data.city_schedules : [])
            .map((entry) => normalizeCitySchedule(entry))
            .filter((entry) => entry.city && entry.start && entry.end);
          if (touringStops.length) {
            syncCitySchedulesWithTouring();
          } else {
            renderCityScheduleWizard();
            applyCityTemplateBlocks({ announce: false });
          }
          renderRecurringList();
          renderBlockedSlots();
          const todayKey = getDateKey(new Date());
          if (calendarStart) {
            calendarStart.value = todayKey;
          }
          if (calendarDay) {
            calendarDay.value = todayKey;
          }
          if (calendarMonth) {
            calendarMonth.value = todayKey.slice(0, 7);
          }
          renderCalendarView();
        } catch (error) {
          const message = error && error.message ? ` (${error.message})` : "";
          availabilityStatus.textContent = `${t("unable_load_city_schedule")}${message}`;
        }
      };

      const saveAvailability = async () => {
        availabilityStatus.textContent = "";
        const key = getKey();
        if (!key) {
          availabilityStatus.textContent = t("admin_key_required");
          return;
        }
        const defaultBufferMinutes = normalizeBufferMinutes(bufferInput?.value, 0);
        if (bufferInput) {
          bufferInput.value = String(defaultBufferMinutes);
        }
        citySchedules = citySchedules.map((schedule) =>
          normalizeCitySchedule({
            ...schedule,
            buffer_minutes: defaultBufferMinutes,
          })
        );
        applyCityTemplateBlocks({ announce: false });
        const cityPayload = readCitySchedulePayload();
        const firstCity = cityPayload[0] || null;
        const activeTourCity = syncTourCityFromCalendar();
        const payload = {
          tour_city: activeTourCity || firstCity?.city || "",
          tour_timezone: DEFAULT_TIMEZONE,
          buffer_minutes: defaultBufferMinutes,
          availability_mode: "open",
          auto_template_blocks: !!autoTemplateBlocksEnabled,
          hidden_booking_ids: Array.from(hiddenBookingIds),
          blocked: blockedSlots,
          recurring: recurringBlocks,
          city_schedules: cityPayload,
        };
        try {
          const response = await fetch("../api/admin/availability.php", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "X-Admin-Key": key,
            },
            body: JSON.stringify(payload),
          });
          const payloadText = await response.text();
          let result = {};
          if (payloadText) {
            result = JSON.parse(payloadText);
          }
          if (!response.ok) throw new Error(result.error || `HTTP ${response.status}`);
          availabilityStatus.textContent = t("city_schedule_saved");
          if (cityScheduleStatus) {
            cityScheduleStatus.textContent = t("city_schedules_saved");
          }
        } catch (error) {
          const message = error && error.message ? ` (${error.message})` : "";
          availabilityStatus.textContent = `${t("failed_save_city_schedule")}${message}`;
        }
      };

      const runAutoSave = async () => {
        if (autoSaveInFlight) {
          autoSaveQueued = true;
          return;
        }
        autoSaveInFlight = true;
        await saveAvailability();
        autoSaveInFlight = false;
        if (autoSaveQueued) {
          autoSaveQueued = false;
          await runAutoSave();
        }
      };

      const queueAutoSave = (message = t("unsaved_changes"), options = {}) => {
        availabilityStatus.textContent = message;
        const persist = !!options.persist;
        if (!persist) return;
        const delay = Number.isFinite(options.delay) ? Math.max(120, Number(options.delay)) : 350;
        if (autoSaveTimer) {
          window.clearTimeout(autoSaveTimer);
        }
        autoSaveTimer = window.setTimeout(() => {
          autoSaveTimer = null;
          runAutoSave();
        }, delay);
      };

      const resolveMediaSrc = (value) => {
        const raw = String(value || "").trim();
        if (!raw) return "";
        if (/^https?:\/\//i.test(raw) || raw.startsWith("/")) {
          return raw;
        }
        return `/${raw.replace(/^\/+/, "")}`;
      };

      const createField = (labelText, type, value = "", placeholder = "") => {
        const wrapper = document.createElement("div");
        wrapper.className = "field";
        const label = document.createElement("label");
        label.textContent = labelText;
        const input = document.createElement("input");
        input.type = type;
        input.value = value;
        if (placeholder) input.placeholder = placeholder;
        wrapper.appendChild(label);
        wrapper.appendChild(input);
        return { wrapper, input };
      };

      const createTourRow = (entry = {}) => {
        const row = document.createElement("div");
        row.className = "editor-row tour-row";
        row.dataset.tourRow = "1";
        row.dataset.tourType = String(entry.type || "tour")
          .trim()
          .toLowerCase() === "block"
          ? "block"
          : "tour";
        row.dataset.tourNotes = String(entry.notes || "").trim();
        const startField = createField(t("start_date"), "date", entry.start || "");
        startField.wrapper.classList.add("tour-start");
        const endField = createField(t("end_date"), "date", entry.end || "");
        endField.wrapper.classList.add("tour-end");
        const cityField = createField(t("city_field"), "text", entry.city || "", t("city_name_placeholder"));
        cityField.wrapper.classList.add("tour-city");
        const removeBtn = createActionButton(t("remove"), () => row.remove(), "btn ghost tour-remove");
        row.appendChild(startField.wrapper);
        row.appendChild(endField.wrapper);
        row.appendChild(cityField.wrapper);
        row.appendChild(removeBtn);
        return row;
      };

      const normalizePartnerLink = (raw) => {
        const value = String(raw || "").trim();
        if (!value) return "";
        if (/^https?:\/\//i.test(value) || /^mailto:/i.test(value)) {
          return value;
        }
        return `https://${value.replace(/^\/+/, "")}`;
      };

      const createTourPartnerRow = (entry = {}) => {
        const row = document.createElement("div");
        row.className = "editor-row";
        row.dataset.partnerRow = "1";
        const friendField = createField("Friend", "text", entry.friend || "", "Name");
        const linkField = createField("Link", "text", entry.link || "", "https://...");
        const removeBtn = createActionButton(t("remove"), () => row.remove(), "btn ghost");
        row.appendChild(friendField.wrapper);
        row.appendChild(linkField.wrapper);
        row.appendChild(removeBtn);
        return row;
      };

      const createGalleryRow = (item = {}) => {
        const row = document.createElement("div");
        row.className = "editor-row gallery";
        row.dataset.galleryRow = "1";
        const srcField = createField(t("photo_path"), "text", item.src || "", "/photos/heidi15.jpg");
        const altField = createField(t("alt_text"), "text", item.alt || "", t("short_description"));
        const previewWrap = document.createElement("div");
        previewWrap.className = "gallery-preview";
        const preview = document.createElement("img");
        preview.className = "gallery-thumb";
        preview.alt = item.alt || t("preview");
        preview.src = resolveMediaSrc(item.src || "");
        preview.addEventListener("error", () => {
          preview.classList.add("broken");
        });
        previewWrap.appendChild(preview);
        srcField.input.addEventListener("input", () => {
          preview.classList.remove("broken");
          preview.src = resolveMediaSrc(srcField.input.value.trim());
        });
        altField.input.addEventListener("input", () => {
          preview.alt = altField.input.value.trim() || t("preview");
        });
        const removeBtn = createActionButton(t("remove"), () => row.remove(), "btn ghost");
        row.appendChild(srcField.wrapper);
        row.appendChild(altField.wrapper);
        row.appendChild(previewWrap);
        row.appendChild(removeBtn);
        return row;
      };

      const renderTourSchedule = (entries) => {
        if (!tourScheduleList) return;
        tourScheduleList.innerHTML = "";
        const list = Array.isArray(entries) ? entries : [];
        if (!list.length) {
          tourScheduleList.appendChild(createTourRow());
          return;
        }
        list.forEach((entry) => {
          tourScheduleList.appendChild(createTourRow(entry));
        });
      };

      const renderTourPartners = (entries) => {
        if (!tourPartnersList) return;
        tourPartnersList.innerHTML = "";
        const list = Array.isArray(entries) ? entries : [];
        if (!list.length) {
          tourPartnersList.appendChild(createTourPartnerRow());
          return;
        }
        list.forEach((entry) => {
          tourPartnersList.appendChild(createTourPartnerRow(entry));
        });
      };

      const renderGallery = (items) => {
        if (!galleryList) return;
        galleryList.innerHTML = "";
        const list = Array.isArray(items) ? items : [];
        if (!list.length) {
          galleryList.appendChild(createGalleryRow());
          return;
        }
        list.forEach((item) => {
          galleryList.appendChild(createGalleryRow(item));
        });
      };

      const normalizeUiDate = (raw) => {
        const value = String(raw || "").trim();
        if (!value) return "";
        if (/^\d{4}-\d{2}-\d{2}$/.test(value)) return value;
        let match = value.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
        if (match) {
          const month = match[1].padStart(2, "0");
          const day = match[2].padStart(2, "0");
          const year = match[3];
          return `${year}-${month}-${day}`;
        }
        match = value.match(/^(\d{1,2})-(\d{1,2})-(\d{4})$/);
        if (match) {
          const month = match[1].padStart(2, "0");
          const day = match[2].padStart(2, "0");
          const year = match[3];
          return `${year}-${month}-${day}`;
        }
        match = value.match(/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/);
        if (match) {
          const year = match[1];
          const month = match[2].padStart(2, "0");
          const day = match[3].padStart(2, "0");
          return `${year}-${month}-${day}`;
        }
        return value;
      };

      const readTourScheduleFromUI = () => {
        if (!tourScheduleList) return [];
        return Array.from(tourScheduleList.querySelectorAll("[data-tour-row]"))
          .map((row) => {
            const start = normalizeUiDate(row.querySelector(".tour-start input")?.value || "");
            const end = normalizeUiDate(row.querySelector(".tour-end input")?.value || "");
            const city = row.querySelector(".tour-city input")?.value?.trim() || "";
            const type = String(row.dataset.tourType || "tour")
              .trim()
              .toLowerCase() === "block"
              ? "block"
              : "tour";
            const notes = String(row.dataset.tourNotes || "").trim();
            return { start, end, city, type, notes };
          })
          .filter((entry) => entry.start && entry.end && entry.city);
      };

      const readTourPartnersFromUI = () => {
        if (!tourPartnersList) return [];
        return Array.from(tourPartnersList.querySelectorAll("[data-partner-row]"))
          .map((row) => {
            const inputs = row.querySelectorAll("input");
            const friend = String(inputs[0]?.value || "").trim();
            const link = normalizePartnerLink(inputs[1]?.value || "");
            return { friend, link };
          })
          .filter((entry) => entry.friend && entry.link);
      };

      const normalizeTouringEntries = (entries) =>
        (Array.isArray(entries) ? entries : [])
          .map((entry) => ({
            start: String(entry?.start || "").trim(),
            end: String(entry?.end || "").trim(),
            city: String(entry?.city || "").trim(),
            type: String(entry?.type || "tour")
              .trim()
              .toLowerCase() === "block"
              ? "block"
              : "tour",
            notes: String(entry?.notes || "").trim(),
          }))
          .filter(
            (entry) =>
              /^\d{4}-\d{2}-\d{2}$/.test(entry.start) &&
              /^\d{4}-\d{2}-\d{2}$/.test(entry.end) &&
              entry.city &&
              entry.start <= entry.end
          )
          .sort((a, b) => (a.start + a.city).localeCompare(b.start + b.city));

      const normalizePartnerEntries = (entries) =>
        (Array.isArray(entries) ? entries : [])
          .map((entry) => ({
            friend: String(entry?.friend || "").trim(),
            link: normalizePartnerLink(entry?.link || ""),
          }))
          .filter((entry) => entry.friend && entry.link);

      const readCitySchedulePayload = () =>
        citySchedules.map((schedule) => ({
          city: schedule.city,
          start: schedule.start,
          end: schedule.end,
          timezone: schedule.timezone,
          ready_start: schedule.ready_start,
          buffer_minutes: Number(schedule.buffer_minutes || 0),
          has_sleep: !!schedule.has_sleep,
          sleep_days: Array.isArray(schedule.sleep_days) ? schedule.sleep_days : [],
          sleep_start: schedule.sleep_start,
          sleep_end: schedule.sleep_end,
          has_break: !!schedule.has_break,
          break_days: Array.isArray(schedule.break_days) ? schedule.break_days : [],
          break_start: schedule.break_start,
          break_end: schedule.break_end,
          leave_day_end: schedule.leave_day_end,
        }));

      const updateCitySchedule = (scheduleId, patch = {}, { applyTemplates = true } = {}) => {
        citySchedules = citySchedules.map((schedule) => {
          if (schedule.id !== scheduleId) {
            return schedule;
          }
          return normalizeCitySchedule({ ...schedule, ...patch });
        });
        if (applyTemplates) {
          applyCityTemplateBlocks({ announce: false });
        }
      };

      const getWizardValidationMessage = (schedule, step) => {
        if (step === 0) {
          if (!isValidTime(schedule.ready_start)) {
            return t("pick_valid_ready_time");
          }
          return "";
        }
        if (step === 1) {
          const buffer = Number(schedule.buffer_minutes);
          if (!Number.isFinite(buffer) || buffer < 0) {
            return t("buffer_invalid");
          }
          return "";
        }
        if (step === 2) {
          if (schedule.has_sleep) {
            if (!isValidTime(schedule.sleep_start) || !isValidTime(schedule.sleep_end)) {
              return t("pick_sleep_times");
            }
            if (!Array.isArray(schedule.sleep_days) || schedule.sleep_days.length < 1) {
              return t("pick_sleep_days");
            }
          }
          return "";
        }
        if (step === 3) {
          if (schedule.has_break) {
            if (!isValidTime(schedule.break_start) || !isValidTime(schedule.break_end)) {
              return t("pick_break_times");
            }
            if (!Array.isArray(schedule.break_days) || schedule.break_days.length < 1) {
              return t("pick_break_days");
            }
          }
          return "";
        }
        if (step === 4) {
          if (!isValidTime(schedule.leave_day_end)) {
            return t("pick_leave_day_end");
          }
          return "";
        }
        return "";
      };

      const createCityWizardCard = (schedule) => {
        const card = document.createElement("div");
        card.className = "city-wizard-card";
        const cardKey = schedule.id.replace(/[^a-z0-9]/gi, "_");
        const scheduleDates = getDateRangeKeys(schedule.start, schedule.end);
        const renderDayChoices = (fieldName, selectedDays) =>
          scheduleDates
            .map((dateKey) => {
              const checked = Array.isArray(selectedDays) && selectedDays.includes(dateKey) ? "checked" : "";
              return `<label class="city-day-chip"><input type="checkbox" data-day-field="${fieldName}" value="${dateKey}" ${checked} /> ${formatDateLabel(dateKey)}</label>`;
            })
            .join("");

        const header = document.createElement("div");
        header.className = "city-wizard-head";

        const titleWrap = document.createElement("div");
        const title = document.createElement("h3");
        title.className = "city-wizard-title";
        title.textContent = schedule.city || t("tour_stop");
        const dateLine = document.createElement("div");
        dateLine.className = "city-wizard-date";
        dateLine.textContent = `${formatDateLabel(schedule.start)} ${t("date_to")} ${formatDateLabel(schedule.end)}`;
        const timezoneLine = document.createElement("div");
        timezoneLine.className = "city-wizard-zone";
        timezoneLine.hidden = true;
        titleWrap.appendChild(title);
        titleWrap.appendChild(dateLine);
        titleWrap.appendChild(timezoneLine);

        const stepBadge = document.createElement("div");
        stepBadge.className = "city-wizard-step";
        header.appendChild(titleWrap);
        header.appendChild(stepBadge);

        const body = document.createElement("div");
        body.className = "city-wizard-body";

        const stepPanels = [0, 1, 2, 3, 4].map(() => {
          const panel = document.createElement("div");
          panel.className = "city-wizard-hidden";
          body.appendChild(panel);
          return panel;
        });

        const step1 = stepPanels[0];
        step1.innerHTML = `
          <p class="city-wizard-question">${t("q_ready_time")}</p>
          <input type="time" data-field="ready_start" value="${schedule.ready_start}" />
        `;

        const step2 = stepPanels[1];
        step2.innerHTML = `
          <p class="city-wizard-question">${t("q_buffer")}</p>
          <input type="number" min="0" max="240" step="5" data-field="buffer_minutes" value="${schedule.buffer_minutes}" />
        `;

        const step3 = stepPanels[2];
        step3.innerHTML = `
          <p class="city-wizard-question">${t("q_sleep")}</p>
          <div class="row">
            <label><input type="radio" name="sleep_${cardKey}" value="yes" ${schedule.has_sleep ? "checked" : ""} /> ${t("yes")}</label>
            <label><input type="radio" name="sleep_${cardKey}" value="no" ${schedule.has_sleep ? "" : "checked"} /> ${t("no")}</label>
          </div>
          <div class="city-wizard-row" data-sleep-fields>
            <input type="time" data-field="sleep_start" value="${schedule.sleep_start}" />
            <input type="time" data-field="sleep_end" value="${schedule.sleep_end}" />
          </div>
          <div data-sleep-day-fields>
            <label>${t("which_days")}</label>
            <div class="city-day-picks">
              ${renderDayChoices("sleep_days", schedule.sleep_days)}
            </div>
          </div>
        `;

        const step4 = stepPanels[3];
        step4.innerHTML = `
          <p class="city-wizard-question">${t("q_breaks")}</p>
          <div class="row">
            <label><input type="radio" name="break_${cardKey}" value="yes" ${schedule.has_break ? "checked" : ""} /> ${t("yes")}</label>
            <label><input type="radio" name="break_${cardKey}" value="no" ${schedule.has_break ? "" : "checked"} /> ${t("no")}</label>
          </div>
          <div class="city-wizard-row" data-break-fields>
            <input type="time" data-field="break_start" value="${schedule.break_start}" />
            <input type="time" data-field="break_end" value="${schedule.break_end}" />
          </div>
          <div data-break-day-fields>
            <label>${t("which_days")}</label>
            <div class="city-day-picks">
              ${renderDayChoices("break_days", schedule.break_days)}
            </div>
          </div>
        `;

        const step5 = stepPanels[4];
        step5.innerHTML = `
          <p class="city-wizard-question">${t("q_leave_time")}</p>
          <input type="time" data-field="leave_day_end" value="${schedule.leave_day_end}" />
        `;

        const nav = document.createElement("div");
        nav.className = "city-wizard-nav";
        const backBtn = createActionButton(t("back"), () => {}, "btn ghost");
        const nextBtn = createActionButton(t("next"), () => {}, "btn secondary");
        nav.appendChild(backBtn);
        nav.appendChild(nextBtn);
        body.appendChild(nav);

        const sleepFieldsWrap = step3.querySelector("[data-sleep-fields]");
        const sleepDayFieldsWrap = step3.querySelector("[data-sleep-day-fields]");
        const breakFieldsWrap = step4.querySelector("[data-break-fields]");
        const breakDayFieldsWrap = step4.querySelector("[data-break-day-fields]");

        const refreshSleepFields = () => {
          const sleepSelected = step3.querySelector(`input[name="sleep_${cardKey}"]:checked`);
          const hasSleep = sleepSelected?.value === "yes";
          sleepFieldsWrap.classList.toggle("city-wizard-hidden", !hasSleep);
          sleepDayFieldsWrap.classList.toggle("city-wizard-hidden", !hasSleep);
          const current = citySchedules.find((item) => item.id === schedule.id) || schedule;
          const sleepDays =
            hasSleep && Array.isArray(current.sleep_days) && current.sleep_days.length
              ? current.sleep_days
              : hasSleep
                ? scheduleDates.slice()
                : [];
          updateCitySchedule(schedule.id, { has_sleep: hasSleep, sleep_days: sleepDays }, { applyTemplates: false });
        };

        const refreshBreakFields = () => {
          const breakSelected = step4.querySelector(`input[name="break_${cardKey}"]:checked`);
          const hasBreak = breakSelected?.value === "yes";
          breakFieldsWrap.classList.toggle("city-wizard-hidden", !hasBreak);
          breakDayFieldsWrap.classList.toggle("city-wizard-hidden", !hasBreak);
          const current = citySchedules.find((item) => item.id === schedule.id) || schedule;
          const breakDays =
            hasBreak && Array.isArray(current.break_days) && current.break_days.length
              ? current.break_days
              : hasBreak
                ? scheduleDates.slice()
                : [];
          updateCitySchedule(schedule.id, { has_break: hasBreak, break_days: breakDays }, { applyTemplates: false });
        };

        const syncStepUi = () => {
          const current = citySchedules.find((item) => item.id === schedule.id) || schedule;
          const step = Number(current._step || 0);
          stepPanels.forEach((panel, index) => {
            panel.classList.toggle("city-wizard-hidden", index !== step);
          });
          timezoneLine.textContent = "";
          stepBadge.textContent = t("step", { current: step + 1, total: 5 });
          backBtn.disabled = step <= 0;
          nextBtn.textContent = step >= 4 ? t("done") : t("next");
          refreshSleepFields();
          refreshBreakFields();
        };

        const captureInputs = () => {
          const patch = {};
          card.querySelectorAll("[data-field]").forEach((input) => {
            const field = input.dataset.field;
            if (!field) return;
            if (input.type === "number") {
              patch[field] = Math.max(0, Math.min(240, Number(input.value || 0) || 0));
            } else {
              patch[field] = input.value;
            }
          });
          patch.sleep_days = Array.from(
            card.querySelectorAll('input[data-day-field="sleep_days"]:checked')
          ).map((input) => input.value);
          patch.break_days = Array.from(
            card.querySelectorAll('input[data-day-field="break_days"]:checked')
          ).map((input) => input.value);
          updateCitySchedule(schedule.id, patch, { applyTemplates: false });
        };

        card.querySelectorAll("[data-field]").forEach((input) => {
          input.addEventListener("input", () => {
            captureInputs();
            applyCityTemplateBlocks({ announce: false });
            queueAutoSave(t("templates_changed"));
          });
          input.addEventListener("change", () => {
            captureInputs();
            applyCityTemplateBlocks({ announce: false });
            queueAutoSave(t("templates_changed"));
          });
        });

        card.querySelectorAll("[data-day-field]").forEach((input) => {
          input.addEventListener("change", () => {
            captureInputs();
            applyCityTemplateBlocks({ announce: false });
            queueAutoSave(t("templates_changed"));
          });
        });

        step3.querySelectorAll(`input[name="sleep_${cardKey}"]`).forEach((input) => {
          input.addEventListener("change", () => {
            refreshSleepFields();
            captureInputs();
            applyCityTemplateBlocks({ announce: false });
            queueAutoSave(t("templates_changed"));
          });
        });
        step4.querySelectorAll(`input[name="break_${cardKey}"]`).forEach((input) => {
          input.addEventListener("change", () => {
            refreshBreakFields();
            captureInputs();
            applyCityTemplateBlocks({ announce: false });
            queueAutoSave(t("templates_changed"));
          });
        });

        backBtn.addEventListener("click", () => {
          const current = citySchedules.find((item) => item.id === schedule.id) || schedule;
          if (current._step <= 0) return;
          updateCitySchedule(schedule.id, { _step: current._step - 1 }, { applyTemplates: false });
          syncStepUi();
        });

        nextBtn.addEventListener("click", async () => {
          captureInputs();
          const current = citySchedules.find((item) => item.id === schedule.id) || schedule;
          const validationMessage = getWizardValidationMessage(current, current._step || 0);
          if (validationMessage) {
            if (cityScheduleStatus) {
              cityScheduleStatus.textContent = `${current.city}: ${validationMessage}`;
            }
            return;
          }
          if (current._step >= 4) {
            applyCityTemplateBlocks({ announce: false });
            queueAutoSave(t("saving_city_schedule"));
            await saveAvailability();
            renderCalendarView();
            setAdminPanel("schedule", true);
            if (calendarEditorSection) {
              calendarEditorSection.scrollIntoView({ behavior: "smooth", block: "start" });
            }
            if (cityScheduleStatus) {
              cityScheduleStatus.textContent = t("city_updated_applied", { city: current.city });
            }
            return;
          }
          updateCitySchedule(schedule.id, { _step: current._step + 1 }, { applyTemplates: false });
          syncStepUi();
        });

        card.appendChild(header);
        card.appendChild(body);
        syncStepUi();
        return card;
      };

      const renderCityScheduleWizard = () => {
        if (!cityScheduleWizard) return;
        cityScheduleWizard.innerHTML = "";
        if (!citySchedules.length) {
          cityScheduleWizard.innerHTML = `<p class="hint">${t("add_tour_first")}</p>`;
          return;
        }
        citySchedules.forEach((schedule) => {
          cityScheduleWizard.appendChild(createCityWizardCard(schedule));
        });
      };

      const syncCitySchedulesWithTouring = () => {
        const existingById = new Map(citySchedules.map((schedule) => [schedule.id, schedule]));
        citySchedules = touringStops.map((stop) => {
          const id = makeScheduleId(stop);
          const existing = existingById.get(id) || {};
          return normalizeCitySchedule({ ...stop, ...existing });
        });
        if (citySchedules.length) {
          const first = citySchedules[0];
          if (tourTzSelect) {
            applyTimezoneValue(first.timezone);
          }
          if (bufferInput) {
            bufferInput.value = String(normalizeBufferMinutes(first.buffer_minutes, 0));
          }
        }
        syncTourCityFromCalendar();
        renderCityScheduleWizard();
        applyCityTemplateBlocks({ announce: false });
      };

      const readGalleryFromUI = () => {
        if (!galleryList) return [];
        return Array.from(galleryList.querySelectorAll("[data-gallery-row]"))
          .map((row) => {
            const inputs = row.querySelectorAll("input");
            const src = inputs[0]?.value?.trim() || "";
            const alt = inputs[1]?.value?.trim() || "";
            return { src, alt };
          })
          .filter((item) => item.src);
      };

      const loadTourSchedule = async () => {
        if (!tourScheduleStatus) return;
        tourScheduleStatus.textContent = "";
        const key = getKey();
        if (!key) {
          tourScheduleStatus.textContent = t("admin_key_required");
          return;
        }
        try {
          const response = await fetch("../api/admin/tour-schedule.php", {
            headers: { ...headersWithKey() },
            cache: "no-store",
          });
          const payloadText = await response.text();
          let data = {};
          if (payloadText) {
            data = JSON.parse(payloadText);
          }
          if (!response.ok) throw new Error(data.error || `HTTP ${response.status}`);
          touringStops = normalizeTouringEntries(data.touring || []);
          tourPartners = normalizePartnerEntries(data.partners || []);
          renderTourSchedule(touringStops);
          renderTourPartners(tourPartners);
          syncCitySchedulesWithTouring();
        } catch (error) {
          const message = error && error.message ? ` (${error.message})` : "";
          tourScheduleStatus.textContent = `${t("failed_load_tour_schedule")}${message}`;
        }
      };

      const saveTouringEntries = async (entries, statusNode = tourScheduleStatus) => {
        if (statusNode) {
          statusNode.textContent = "";
        }
        const key = getKey();
        if (!key) {
          if (statusNode) {
            statusNode.textContent = t("admin_key_required");
          }
          return false;
        }
        const list = normalizeTouringEntries(entries);
        const partners = normalizePartnerEntries(readTourPartnersFromUI());
        if (!list.length) {
          if (statusNode) {
            statusNode.textContent = t("add_entry_min");
          }
          return false;
        }
        try {
          const response = await fetch("../api/admin/tour-schedule.php", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "X-Admin-Key": key,
            },
            body: JSON.stringify({ touring: list, partners }),
          });
          const payloadText = await response.text();
          let result = {};
          if (payloadText) {
            result = JSON.parse(payloadText);
          }
          if (!response.ok) throw new Error(result.error || `HTTP ${response.status}`);
          touringStops = normalizeTouringEntries(result.touring || list);
          tourPartners = normalizePartnerEntries(result.partners || partners);
          renderTourSchedule(touringStops);
          renderTourPartners(tourPartners);
          syncCitySchedulesWithTouring();
          if (statusNode) {
            statusNode.textContent = t("tour_schedule_saved");
          }
          availabilityStatus.textContent = t("saving_city_schedule");
          await saveAvailability();
          if (cityScheduleStatus) {
            cityScheduleStatus.textContent = t("city_schedules_saved");
          }
          return true;
        } catch (error) {
          const message = error && error.message ? ` (${error.message})` : "";
          if (statusNode) {
            statusNode.textContent = `${t("failed_save_tour_schedule")}${message}`;
          }
          return false;
        }
      };

      const saveTourSchedule = async () => {
        const entries = readTourScheduleFromUI();
        await saveTouringEntries(entries, tourScheduleStatus);
      };

      const clearQuickAddFields = () => {
        if (quickAddType) quickAddType.value = "tour";
        if (quickAddCity) quickAddCity.value = "";
        if (quickAddStart) quickAddStart.value = "";
        if (quickAddEnd) quickAddEnd.value = "";
        if (quickAddNotes) quickAddNotes.value = "";
      };

      const addQuickEntry = async () => {
        if (!quickAddStatus) return;
        quickAddStatus.textContent = "";
        const type = String(quickAddType?.value || "").trim().toLowerCase();
        const city = String(quickAddCity?.value || "").trim();
        const start = normalizeUiDate(quickAddStart?.value || "");
        const end = normalizeUiDate(quickAddEnd?.value || "");
        const notes = String(quickAddNotes?.value || "").trim();
        if (!type || !city || !start || !end) {
          quickAddStatus.textContent = t("quick_add_missing_fields");
          return;
        }
        if (start > end) {
          quickAddStatus.textContent = t("quick_add_invalid_range");
          return;
        }
        if (type === "block") {
          const startDate = parseDateKey(start);
          const endDate = parseDateKey(end);
          if (!startDate || !endDate || startDate > endDate) {
            quickAddStatus.textContent = t("quick_add_invalid_range");
            return;
          }
          const reason = notes || `Quick block (${city})`;
          const cursor = new Date(startDate.getTime());
          while (cursor <= endDate) {
            blockFullDayForDate(toDateKey(cursor), reason, city);
            cursor.setUTCDate(cursor.getUTCDate() + 1);
          }
          renderBlockedSlots();
          renderCalendarView();
          await saveAvailability();
          quickAddStatus.textContent = t("quick_add_blocked_saved");
          clearQuickAddFields();
          return;
        }
        const current = readTourScheduleFromUI();
        const ok = await saveTouringEntries(
          [...current, { type: "tour", city, start, end, notes }],
          quickAddStatus
        );
        if (ok) {
          clearQuickAddFields();
        }
      };

      const loadGallery = async () => {
        if (!galleryStatus) return;
        galleryStatus.textContent = "";
        const key = getKey();
        if (!key) {
          galleryStatus.textContent = t("admin_key_required");
          return;
        }
        try {
          const response = await fetch("../api/admin/gallery.php", {
            headers: { ...headersWithKey() },
            cache: "no-store",
          });
          const data = await response.json();
          if (!response.ok) throw new Error(data.error || "load");
          renderGallery(data.items || []);
          if (photoDisplayModeSelect) {
            photoDisplayModeSelect.value = ["next", "album", "carousel"].includes(String(data.display_mode || ""))
              ? String(data.display_mode)
              : "next";
          }
          if (photoCarouselSecondsInput) {
            const seconds = Number(data.carousel_seconds || 5);
            photoCarouselSecondsInput.value = Number.isFinite(seconds) ? String(Math.min(30, Math.max(2, seconds))) : "5";
          }
          updatePhotoModeUi();
        } catch (_error) {
          galleryStatus.textContent = t("failed_load_eye_candy");
        }
      };

      const saveGallery = async () => {
        if (!galleryStatus) return;
        galleryStatus.textContent = "";
        const key = getKey();
        if (!key) {
          galleryStatus.textContent = t("admin_key_required");
          return;
        }
        const items = readGalleryFromUI();
        if (!items.length) {
          galleryStatus.textContent = t("add_photo_min");
          return;
        }
        const displayMode = photoDisplayModeSelect?.value || "next";
        let carouselSeconds = Number(photoCarouselSecondsInput?.value || 5);
        if (!Number.isFinite(carouselSeconds)) carouselSeconds = 5;
        carouselSeconds = Math.min(30, Math.max(2, Math.round(carouselSeconds)));
        if (photoCarouselSecondsInput) {
          photoCarouselSecondsInput.value = String(carouselSeconds);
        }
        try {
          const response = await fetch("../api/admin/gallery.php", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "X-Admin-Key": key,
            },
            body: JSON.stringify({
              items,
              display_mode: displayMode,
              carousel_seconds: carouselSeconds,
            }),
          });
          const result = await response.json();
          if (!response.ok) throw new Error(result.error || "save");
          renderGallery(result.items || items);
          if (photoDisplayModeSelect) {
            photoDisplayModeSelect.value = result.display_mode || displayMode;
          }
          if (photoCarouselSecondsInput) {
            photoCarouselSecondsInput.value = String(
              Math.min(30, Math.max(2, Number(result.carousel_seconds || carouselSeconds)))
            );
          }
          updatePhotoModeUi();
          galleryStatus.textContent = t("eye_candy_saved");
        } catch (_error) {
          galleryStatus.textContent = t("failed_save_eye_candy");
        }
      };

      const updatePhotoModeUi = () => {
        if (!photoCarouselSecondsInput) return;
        const mode = String(photoDisplayModeSelect?.value || "next").trim().toLowerCase();
        photoCarouselSecondsInput.disabled = mode !== "carousel";
      };


      const createActionButton = (label, onClick, className = "btn ghost") => {
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = className;
        btn.textContent = label;
        btn.addEventListener("click", onClick);
        return btn;
      };

      const formatLine = (label, value) =>
        value ? `<div class="meta"><strong>${label}:</strong> ${value}</div>` : "";

      const formatArray = (list) => (Array.isArray(list) ? list.filter(Boolean).join(", ") : "");

      const escapeHtml = (value) =>
        String(value ?? "")
          .replace(/&/g, "&amp;")
          .replace(/</g, "&lt;")
          .replace(/>/g, "&gt;")
          .replace(/\"/g, "&quot;")
          .replace(/'/g, "&#39;");

      const formatHistory = (history) => {
        if (!Array.isArray(history) || !history.length) {
          return `<div class="meta"><strong>${t("edit_history")}:</strong> ${t("no_history")}</div>`;
        }
        const items = history
          .slice()
          .reverse()
          .map((entry) => {
            const at = entry?.at ? String(entry.at) : "";
            const summary = String(entry?.summary || entry?.action || "update");
            const source = entry?.source ? ` [${String(entry.source)}]` : "";
            return `<li>${escapeHtml(at)} - ${escapeHtml(summary)}${escapeHtml(source)}</li>`;
          })
          .join("");
        return `<div class="meta"><strong>${t("edit_history")}:</strong><ul style="margin:6px 0 0 16px; padding:0;">${items}</ul></div>`;
      };

      const formatPaymentMethod = (value) => {
        const normalized = String(value || "").toLowerCase();
        if (normalized === "etransfer" || normalized === "interac") return "Interac e-Transfer";
        if (normalized === "wise") return "Wise";
        if (normalized === "usdc") return "USDC";
        if (normalized === "ltc" || normalized === "litecoin") return "Litecoin";
        if (normalized === "btc") return "Bitcoin";
        if (normalized === "paypal") return "Interac e-Transfer";
        return value || "";
      };

      const isValidEmail = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/i.test(String(value || "").trim());

      const isValidInternationalPhone = (value) => {
        const raw = String(value || "").trim();
        if (!raw.startsWith("+")) return false;
        if (!/^\+[0-9\s().-]+$/.test(raw)) return false;
        const digits = raw.replace(/\D/g, "");
        if (digits.length < 8 || digits.length > 15) return false;
        return !digits.startsWith("0");
      };

      const createEditField = (labelText, type, value = "", options = {}) => {
        const wrap = document.createElement("div");
        wrap.className = "request-edit-field";
        if (options.full) {
          wrap.style.gridColumn = "1 / -1";
        }
        const label = document.createElement("label");
        label.textContent = labelText;
        const input =
          type === "textarea" ? document.createElement("textarea") : document.createElement(type === "select" ? "select" : "input");
        if (type !== "textarea" && type !== "select") {
          input.type = type;
        }
        input.value = String(value ?? "");
        if (options.placeholder) {
          input.placeholder = options.placeholder;
        }
        if (options.required) {
          input.required = true;
        }
        if (options.min !== undefined) {
          input.min = String(options.min);
        }
        if (options.max !== undefined) {
          input.max = String(options.max);
        }
        if (options.step !== undefined) {
          input.step = String(options.step);
        }
        if (Array.isArray(options.selectOptions) && input.tagName === "SELECT") {
          options.selectOptions.forEach((entry) => {
            const option = document.createElement("option");
            option.value = entry.value;
            option.textContent = entry.label;
            input.appendChild(option);
          });
        }
        wrap.appendChild(label);
        wrap.appendChild(input);
        return { wrap, input };
      };

      const createRequestEditPanel = (item) => {
        const panel = document.createElement("div");
        panel.className = "request-edit-panel hidden";

        const grid = document.createElement("div");
        grid.className = "request-edit-grid";

        const nameField = createEditField(t("name_label"), "text", item.name || "", { required: true });
        const emailField = createEditField(t("email_label"), "email", item.email || "", { required: true });
        const phoneField = createEditField(t("phone_label"), "tel", item.phone || "", {
          required: true,
          placeholder: "+14389993539",
        });
        const cityField = createEditField(t("city_label"), "text", item.city || "", { required: true });
        const typeField = createEditField(t("type_label"), "select", item.booking_type || "incall", {
          required: true,
          selectOptions: [
            { value: "incall", label: "incall" },
            { value: "outcall", label: "outcall" },
          ],
        });
        typeField.input.value = String(item.booking_type || "incall").toLowerCase() === "outcall" ? "outcall" : "incall";

        const outcallField = createEditField(t("outcall_address"), "text", item.outcall_address || "");
        const dateField = createEditField(t("date_label"), "date", item.preferred_date || "", { required: true });
        const timeField = createEditField(t("time_label"), "time", item.preferred_time || "", { required: true, step: 1800 });
        const durationLabelField = createEditField(t("duration"), "text", item.duration_label || "", { required: true });
        const durationHoursField = createEditField(t("hours_label"), "number", item.duration_hours || "", {
          required: true,
          min: 0.5,
          max: 24,
          step: 0.5,
        });
        const normalizeExperienceValue = (value) => {
          const normalized = String(value || "").trim().toLowerCase();
          if (normalized === "duo_gfe") return "gfe";
          if (["gfe", "pse", "filming", "social"].includes(normalized)) return normalized;
          return "gfe";
        };
        const experienceField = createEditField(t("experience"), "select", normalizeExperienceValue(item.experience), {
          required: true,
          selectOptions: [
            { value: "gfe", label: "GFE" },
            { value: "pse", label: "PSE" },
            { value: "filming", label: "Filming" },
            { value: "social", label: "Social introduction" },
          ],
        });
        experienceField.input.value = normalizeExperienceValue(item.experience);

        const notesField = createEditField(t("notes"), "textarea", item.notes || "", { full: true });

        [
          nameField,
          emailField,
          phoneField,
          cityField,
          typeField,
          outcallField,
          dateField,
          timeField,
          durationLabelField,
          durationHoursField,
          experienceField,
          notesField,
        ].forEach(({ wrap }) => grid.appendChild(wrap));

        panel.appendChild(grid);

        const row = document.createElement("div");
        row.className = "actions";
        const saveBtn = createActionButton(t("save_changes"), () => {}, "btn secondary");
        const cancelBtn = createActionButton(t("action_cancel"), () => {}, "btn ghost");
        const statusNode = document.createElement("span");
        statusNode.className = "status";

        const setOutcallVisibility = () => {
          const isOutcall = typeField.input.value === "outcall";
          outcallField.wrap.style.display = isOutcall ? "" : "none";
        };

        const buildPayload = () => ({
          id: item.id,
          name: nameField.input.value.trim(),
          email: emailField.input.value.trim(),
          phone: phoneField.input.value.trim(),
          city: cityField.input.value.trim(),
          booking_type: typeField.input.value,
          outcall_address: outcallField.input.value.trim(),
          experience: experienceField.input.value,
          duration_label: durationLabelField.input.value.trim(),
          duration_hours: String(durationHoursField.input.value || "").trim(),
          preferred_date: dateField.input.value,
          preferred_time: timeField.input.value,
          notes: notesField.input.value.trim(),
        });

        saveBtn.addEventListener("click", async () => {
          statusNode.textContent = "";
          const payload = buildPayload();
          if (
            !payload.name ||
            !payload.email ||
            !payload.phone ||
            !payload.city ||
            !payload.preferred_date ||
            !payload.preferred_time ||
            !payload.duration_label ||
            !payload.duration_hours
          ) {
            statusNode.textContent = t("required_fields");
            return;
          }
          if (!isValidEmail(payload.email)) {
            statusNode.textContent = t("invalid_email");
            return;
          }
          if (!isValidInternationalPhone(payload.phone)) {
            statusNode.textContent = t("invalid_phone");
            return;
          }
          if (payload.booking_type === "outcall" && !payload.outcall_address) {
            statusNode.textContent = t("required_fields");
            return;
          }
          const hours = Number(payload.duration_hours);
          if (!Number.isFinite(hours) || hours <= 0 || hours > 24) {
            statusNode.textContent = t("required_fields");
            return;
          }

          const key = getKey();
          if (!key) {
            statusNode.textContent = t("admin_key_required");
            return;
          }

          saveBtn.disabled = true;
          try {
            const response = await fetch("../api/admin/update-request.php", {
              method: "POST",
              headers: {
                "Content-Type": "application/json",
                "X-Admin-Key": key,
              },
              body: JSON.stringify(payload),
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) {
              const fieldError =
                result?.fields && typeof result.fields === "object" ? Object.values(result.fields)[0] : result?.error;
              throw new Error(fieldError || "update");
            }
            statusNode.textContent = t("appointment_updated");
            panel.classList.add("hidden");
            await loadRequests();
            await loadAvailability();
            setAdminPanel("schedule", true);
          } catch (error) {
            statusNode.textContent = `${t("failed_update_appointment")} ${error?.message ? `(${error.message})` : ""}`;
          } finally {
            saveBtn.disabled = false;
          }
        });

        cancelBtn.addEventListener("click", () => {
          panel.classList.add("hidden");
        });

        typeField.input.addEventListener("change", setOutcallVisibility);
        setOutcallVisibility();

        row.appendChild(saveBtn);
        row.appendChild(cancelBtn);
        row.appendChild(statusNode);
        panel.appendChild(row);

        return panel;
      };

      const formatCalendarStamp = (dateValue, timeValue, addMinutes = 0) => {
        const [year, month, day] = String(dateValue || "").split("-").map((value) => Number(value));
        const [hour, minute] = String(timeValue || "").split(":").map((value) => Number(value));
        if (!year || !month || !day || Number.isNaN(hour) || Number.isNaN(minute)) {
          return "";
        }
        const stamp = new Date(Date.UTC(year, month - 1, day, hour, minute));
        stamp.setUTCMinutes(stamp.getUTCMinutes() + addMinutes);
        const pad = (value) => String(value).padStart(2, "0");
        return `${stamp.getUTCFullYear()}${pad(stamp.getUTCMonth() + 1)}${pad(stamp.getUTCDate())}T${pad(
          stamp.getUTCHours()
        )}${pad(stamp.getUTCMinutes())}00`;
      };

      const buildGoogleCalendarUrl = (item) => {
        const dateValue = item.preferred_date || "";
        const timeValue = item.preferred_time || "";
        if (!dateValue || !timeValue) return "";
        const durationHours = Number(item.duration_hours || 0);
        const minutes = durationHours > 0 ? Math.round(durationHours * 60) : 60;
        const start = formatCalendarStamp(dateValue, timeValue, 0);
        const end = formatCalendarStamp(dateValue, timeValue, minutes);
        if (!start || !end) return "";
        const title = encodeURIComponent(`Booking: ${item.name || "Client"}`);
        const details = encodeURIComponent(`Booking request ${item.id || ""}`);
        const location = encodeURIComponent(item.city || "");
        const tz = encodeURIComponent(item.tour_timezone || "America/Toronto");
        return `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${title}&details=${details}&location=${location}&dates=${start}/${end}&ctz=${tz}`;
      };

      const buildIcsDownloadUrl = (item) => {
        const id = String(item?.id || "").trim();
        if (!id) return "";
        return `../api/calendar.php?id=${encodeURIComponent(id)}`;
      };

      const buildIcsTimestamp = (dateValue, timeValue, addMinutes = 0) => {
        const [year, month, day] = String(dateValue || "").split("-").map((value) => Number(value));
        const [hour, minute] = String(timeValue || "").split(":").map((value) => Number(value));
        if (!year || !month || !day || Number.isNaN(hour) || Number.isNaN(minute)) return "";
        const date = new Date(Date.UTC(year, month - 1, day, hour, minute));
        date.setUTCMinutes(date.getUTCMinutes() + addMinutes);
        const pad = (value) => String(value).padStart(2, "0");
        return `${date.getUTCFullYear()}${pad(date.getUTCMonth() + 1)}${pad(date.getUTCDate())}T${pad(
          date.getUTCHours()
        )}${pad(date.getUTCMinutes())}00Z`;
      };

      const downloadSamsungIcs = (item) => {
        const dateValue = String(item?.preferred_date || "");
        const timeValue = String(item?.preferred_time || "");
        if (!dateValue || !timeValue) return;
        const durationHours = Number(item?.duration_hours || 0);
        const durationMinutes = durationHours > 0 ? Math.round(durationHours * 60) : 60;
        const start = buildIcsTimestamp(dateValue, timeValue, 0);
        const end = buildIcsTimestamp(dateValue, timeValue, durationMinutes);
        if (!start || !end) return;

        const uid = `${item?.id || Date.now()}@bombacloud`;
        const summary = `Booking: ${item?.name || "Client"}`;
        const location = String(item?.city || "");
        const description = `Booking request ${item?.id || ""}`;
        const ics = [
          "BEGIN:VCALENDAR",
          "VERSION:2.0",
          "PRODID:-//BombaCLOUD//Booking//EN",
          "BEGIN:VEVENT",
          `UID:${uid}`,
          `DTSTAMP:${buildIcsTimestamp(dateValue, timeValue, 0)}`,
          `DTSTART:${start}`,
          `DTEND:${end}`,
          `SUMMARY:${summary.replace(/\n/g, " ")}`,
          `LOCATION:${location.replace(/\n/g, " ")}`,
          `DESCRIPTION:${description.replace(/\n/g, " ")}`,
          "END:VEVENT",
          "END:VCALENDAR",
          "",
        ].join("\r\n");

        const blob = new Blob([ics], { type: "text/calendar;charset=utf-8" });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement("a");
        const safeId = String(item?.id || "booking").replace(/[^a-zA-Z0-9_-]/g, "");
        link.href = url;
        link.download = `booking-${safeId}.ics`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
      };

      const normalizeStatus = (item) => {
        const rawStatus = String(item?.status || "pending").toLowerCase();
        const rawPayment = String(item?.payment_status || "").toLowerCase();
        const status = rawStatus === "paid" ? "accepted" : rawStatus;
        const paymentStatus = rawPayment || (rawStatus === "paid" ? "paid" : "");
        return { status, paymentStatus };
      };

      const buildConfirmedSlotsFromRequests = (requests) => {
        const slots = [];
        (Array.isArray(requests) ? requests : []).forEach((item) => {
          const bookingId = String(item?.id || "").trim();
          if (bookingId && hiddenBookingIds.has(bookingId)) return;
          const { status, paymentStatus } = normalizeStatus(item);
          const confirmed = status === "accepted" || paymentStatus === "paid";
          if (!confirmed) return;
          const date = String(item?.preferred_date || "").trim();
          const start = String(item?.preferred_time || "").trim();
          const durationHours = Number(item?.duration_hours || 0);
          if (!/^\d{4}-\d{2}-\d{2}$/.test(date) || !/^\d{2}:\d{2}$/.test(start)) return;
          if (!Number.isFinite(durationHours) || durationHours <= 0) return;
          const startMinutes = timeToMinutes(start);
          if (startMinutes === null) return;
          const totalMinutes = Math.max(SLOT_MINUTES, Math.round(durationHours * 60));
          const endMinutes = Math.min(24 * 60, startMinutes + totalMinutes);
          if (endMinutes <= startMinutes) return;
          const labelRaw = String(item?.name || "").trim();
          const label = labelRaw ? labelRaw.split(/\s+/)[0] : "Booking";
          const city = String(item?.city || "").trim();
          const bookingType = String(item?.booking_type || "").trim();
          const bookingStatus = paymentStatus === "paid" ? "paid" : "accepted";
          for (let minutes = startMinutes; minutes < endMinutes; minutes += SLOT_MINUTES) {
            slots.push({
              date,
              start: minutesToTime(minutes),
              end: minutesToTime(Math.min(minutes + SLOT_MINUTES, endMinutes)),
              kind: "booking",
              booking_id: bookingId,
              booking_status: bookingStatus,
              booking_type: bookingType,
              label,
              city,
            });
          }
        });
        return slots;
      };

      const buildMaybeSlotsFromRequests = (requests) => {
        const slots = [];
        (Array.isArray(requests) ? requests : []).forEach((item) => {
          const requestId = String(item?.id || "").trim();
          if (requestId && hiddenBookingIds.has(requestId)) return;
          const { status, paymentStatus } = normalizeStatus(item);
          const maybeLike = status === "maybe" || status === "pending";
          if (!maybeLike || paymentStatus === "paid") return;
          const date = String(item?.preferred_date || "").trim();
          const start = String(item?.preferred_time || "").trim();
          const durationHours = Number(item?.duration_hours || 0);
          if (!/^\d{4}-\d{2}-\d{2}$/.test(date) || !/^\d{2}:\d{2}$/.test(start)) return;
          if (!Number.isFinite(durationHours) || durationHours <= 0) return;
          const startMinutes = timeToMinutes(start);
          if (startMinutes === null) return;
          const totalMinutes = Math.max(SLOT_MINUTES, Math.round(durationHours * 60));
          const endMinutes = Math.min(24 * 60, startMinutes + totalMinutes);
          if (endMinutes <= startMinutes) return;
          const label = String(item?.name || "").trim();
          const city = String(item?.city || "").trim();
          for (let minutes = startMinutes; minutes < endMinutes; minutes += SLOT_MINUTES) {
            slots.push({
              date,
              start: minutesToTime(minutes),
              end: minutesToTime(Math.min(minutes + SLOT_MINUTES, endMinutes)),
              id: requestId,
              label: label || t("unknown"),
              city,
              status,
            });
          }
        });
        return slots;
      };

      const updateStatus = async (id, status, reason = "") => {
        const key = getKey();
        if (!key) {
          requestsStatus.textContent = t("admin_key_required");
          return;
        }
        requestsStatus.textContent = "";
        try {
          const response = await fetch("../api/admin/status.php", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "X-Admin-Key": key,
            },
            body: JSON.stringify({ id, status, reason }),
          });
          const result = await response.json();
          if (!response.ok) throw new Error(result.error || "status");
          requestsStatus.textContent = t("status_updated");
          await loadRequests();
          await loadAvailability();
        } catch (_error) {
          requestsStatus.textContent = t("failed_update_status");
        }
      };

      const getDownloadFilename = (response, fallback) => {
        const disposition = response.headers.get("Content-Disposition") || "";
        const match = disposition.match(/filename="?([^\";]+)"?/i);
        return match ? match[1] : fallback;
      };

      const downloadRequests = async (format) => {
        const key = getKey();
        if (!key) {
          requestsStatus.textContent = t("admin_key_required");
          return;
        }
        requestsStatus.textContent = "";
        try {
          const response = await fetch(`../api/admin/export.php?format=${encodeURIComponent(format)}`, {
            headers: { "X-Admin-Key": key },
          });
          if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.error || "download");
          }
          const blob = await response.blob();
          const filename = getDownloadFilename(response, `booking-requests.${format}`);
          const url = window.URL.createObjectURL(blob);
          const link = document.createElement("a");
          link.href = url;
          link.download = filename;
          document.body.appendChild(link);
          link.click();
          link.remove();
          window.URL.revokeObjectURL(url);
          requestsStatus.textContent = t("download_ready");
        } catch (_error) {
          requestsStatus.textContent = t("failed_download");
        }
      };

      const loadRequests = async () => {
        const requestToken = ++loadRequestsToken;
        requestsStatus.textContent = "";
        requestsList.innerHTML = "";
        maybeSlots = [];
        const key = getKey();
        if (!key) {
          requestsStatus.textContent = t("admin_key_required");
          requestSlots = [];
          renderCalendarView();
          return;
        }
        await loadNotificationReadState();
        try {
          const response = await fetch("../api/admin/requests.php", {
            headers: { ...headersWithKey() },
          });
          const data = await response.json();
          if (!response.ok) throw new Error(data.error || "load");
          if (requestToken !== loadRequestsToken) return;
          const sourceRequests = Array.isArray(data.requests) ? data.requests : [];
          const requests = [];
          const indexByAlias = new Map();
          const aliasKeys = (item) => {
            const id = String(item?.id || "").trim();
            const email = String(item?.email || "").trim().toLowerCase();
            const phone = String(item?.phone || "").replace(/\D+/g, "");
            const date = String(item?.preferred_date || "").trim();
            const time = String(item?.preferred_time || "").trim();
            const city = String(item?.city || "").trim().toLowerCase();
            const name = String(item?.name || "").trim().toLowerCase();
            const keys = [];
            if (id) keys.push(`id:${id}`);
            if (date && time) {
              if (email && phone) keys.push(`dt-ep:${date}|${time}|${email}|${phone}`);
              if (email) keys.push(`dt-e:${date}|${time}|${email}`);
              if (phone) keys.push(`dt-p:${date}|${time}|${phone}`);
              if (name) {
                keys.push(`dt-n:${date}|${time}|${name}`);
                if (city) keys.push(`dt-nc:${date}|${time}|${name}|${city}`);
              }
            }
            return Array.from(new Set(keys));
          };
          sourceRequests.forEach((item) => {
            if (!item || typeof item !== "object") return;
            let keys = aliasKeys(item);
            if (!keys.length) return;

            let matchIndex = null;
            for (const key of keys) {
              if (indexByAlias.has(key)) {
                matchIndex = indexByAlias.get(key);
                break;
              }
            }

            if (matchIndex === null || matchIndex === undefined) {
              requests.push(item);
              const idx = requests.length - 1;
              keys.forEach((key) => indexByAlias.set(key, idx));
              return;
            }

            const current = requests[matchIndex];
            const nextStamp = String(item.updated_at || item.created_at || "");
            const currentStamp = String(current?.updated_at || current?.created_at || "");
            const keepNext = nextStamp >= currentStamp;
            if (keepNext) {
              requests[matchIndex] = item;
              keys = aliasKeys(item);
            } else {
              keys = Array.from(new Set([...aliasKeys(current || {}), ...keys]));
            }
            keys.forEach((key) => indexByAlias.set(key, matchIndex));
          });
          if (requestToken !== loadRequestsToken) return;
          requestsList.innerHTML = "";
          latestRequests = requests;
          renderNotifications(requests);
          requestSlots = buildConfirmedSlotsFromRequests(requests);
          maybeSlots = buildMaybeSlotsFromRequests(requests);
          renderCalendarView();
          const filterValue = statusFilter.value;
          const filtered = requests.filter((item) => {
            const { status, paymentStatus } = normalizeStatus(item);
            if (filterValue === "all") {
              return true;
            }
            if (filterValue === "paid") {
              return paymentStatus === "paid";
            }
            if (filterValue === "accepted") {
              return status === "accepted";
            }
            return status === filterValue;
          });
          filtered
            .slice()
            .sort((a, b) => (b.created_at || "").localeCompare(a.created_at || ""))
            .forEach((item) => {
              const { status, paymentStatus } = normalizeStatus(item);
              const card = document.createElement("div");
              card.className = "request-card";
              card.setAttribute("data-request-id", String(item.id || ""));
              const badgeClass = `badge ${status}`;
              const followupCities = formatArray(item.followup_cities);
              const percentLabel = item.deposit_percent ? `${item.deposit_percent}%` : "";
              let depositLabel = "";
              if (item.deposit_amount !== undefined && item.deposit_amount !== null) {
                depositLabel = `${item.deposit_amount}${item.deposit_currency ? " " + item.deposit_currency : ""}`;
                if (percentLabel) {
                  depositLabel += ` (${percentLabel})`;
                }
              } else if (percentLabel) {
                depositLabel = percentLabel;
              }
              const followupChannelList = String(item.contact_channel || "")
                .toLowerCase()
                .split(",")
                .map((value) => value.trim())
                .filter(Boolean);
              const hasLegacyFollowup = String(item.contact_followup || "").toLowerCase() === "yes";
              const followupPhoneEnabled =
                String(item.contact_followup_phone || "").toLowerCase() === "yes" ||
                (hasLegacyFollowup && followupChannelList.includes("phone"));
              const followupEmailEnabled =
                String(item.contact_followup_email || "").toLowerCase() === "yes" ||
                (hasLegacyFollowup && followupChannelList.includes("email"));
              const followupPhoneLabel = followupPhoneEnabled ? t("yes") : t("no");
              const followupEmailLabel = followupEmailEnabled ? t("yes") : t("no");
              card.innerHTML = `
                <div class="request-header">
                  <div><strong>${item.name || t("unknown")}</strong></div>
                  <div class="request-badges">
                    <span class="${badgeClass}">${statusLabel(status)}</span>
                    ${paymentStatus === "paid" ? `<span class="badge paid">${t("paid")}</span>` : ""}
                  </div>
                </div>
                ${formatLine(t("email_label"), item.email)}
                ${formatLine(t("phone_label"), item.phone)}
                ${formatLine(t("city_label"), item.city)}
                ${formatLine(t("type_label"), item.booking_type)}
                ${formatLine(t("outcall_address"), item.outcall_address)}
                ${formatLine(t("experience"), item.experience)}
                ${formatLine(t("duration"), item.duration_label)}
                ${formatLine(t("preferred"), `${item.preferred_date || ""} ${item.preferred_time || ""}`)}
                ${formatLine(t("client_tz"), item.client_timezone)}
                ${formatLine(t("deposit"), depositLabel)}
                ${formatLine(t("payment_status"), paymentStatus === "paid" ? t("paid") : "")}
                ${formatLine(t("payment_method"), formatPaymentMethod(item.payment_method))}
                ${formatLine(t("notes"), item.notes)}
                <div class="meta"><strong>${t("decline_reason")}:</strong> <input class="decline-reason" type="text" placeholder="${t("reason")}" value="${item.decline_reason || ""}" /></div>
                ${formatLine(t("blacklist_reason"), item.blacklist_reason)}
                ${formatLine(t("follow_up_phone"), followupPhoneLabel)}
                ${formatLine(t("follow_up_email"), followupEmailLabel)}
                ${formatLine(t("follow_up_cities"), followupCities || t("no_city"))}
                ${formatLine(t("payment_details"), item.payment_link)}
                ${formatLine(t("created"), item.created_at)}
                ${formatLine(t("updated"), item.updated_at)}
                ${formatLine(t("email_sent"), item.payment_email_sent_at)}
                ${formatHistory(item.history)}
              `;
              const declineInput = card.querySelector(".decline-reason");
              const editPanel = createRequestEditPanel(item);
              const actions = document.createElement("div");
              actions.className = "actions";
              const statusActionRow = document.createElement("div");
              statusActionRow.className = "status-action-row";
              const statusSelect = document.createElement("select");
              statusSelect.className = "status-action-select";
              [
                ["", t("action_choose")],
                ["pending", t("pending")],
                ["maybe", t("maybe")],
                ["accepted", t("accepted")],
                ["paid", t("paid")],
                ["blacklisted", t("blacklisted")],
                ["declined", t("declined")],
                ["cancelled", t("cancelled")],
              ].forEach(([value, label]) => {
                const option = document.createElement("option");
                option.value = value;
                option.textContent = label;
                statusSelect.appendChild(option);
              });
              const currentStatus = paymentStatus === "paid" ? "paid" : status;
              statusSelect.value = currentStatus;
              const statusApplyBtn = createActionButton(
                t("action_ok"),
                () => {
                  const nextStatus = String(statusSelect.value || "").trim();
                  if (!nextStatus) {
                    requestsStatus.textContent = t("action_pick_first");
                    return;
                  }
                  if (nextStatus === currentStatus) {
                    requestsStatus.textContent = t("status_updated");
                    return;
                  }
                  updateStatus(item.id, nextStatus, declineInput ? declineInput.value.trim() : "");
                },
                "btn status-action-apply"
              );
              statusActionRow.appendChild(statusSelect);
              statusActionRow.appendChild(statusApplyBtn);
              actions.appendChild(statusActionRow);
              actions.appendChild(
                createActionButton(t("action_edit"), () => {
                  editPanel.classList.toggle("hidden");
                }, "btn ghost")
              );
              const requestId = String(item.id || "").trim();
              if (requestId) {
                const toggleGridVisibility = () => {
                  const isHidden = hiddenBookingIds.has(requestId);
                  if (isHidden) {
                    hiddenBookingIds.delete(requestId);
                    requestsStatus.textContent = t("grid_visible");
                  } else {
                    hiddenBookingIds.add(requestId);
                    requestsStatus.textContent = t("grid_hidden");
                  }
                  requestSlots = buildConfirmedSlotsFromRequests(latestRequests);
                  maybeSlots = buildMaybeSlotsFromRequests(latestRequests);
                  renderCalendarView();
                  queueAutoSave(t("saving_city_schedule"), { persist: true });
                  gridToggleBtn.textContent = hiddenBookingIds.has(requestId)
                    ? t("action_show_grid")
                    : t("action_remove_grid");
                };
                const gridToggleBtn = createActionButton(
                  hiddenBookingIds.has(requestId) ? t("action_show_grid") : t("action_remove_grid"),
                  toggleGridVisibility,
                  "btn ghost"
                );
                actions.appendChild(gridToggleBtn);
              }
              const calendarUrl = buildGoogleCalendarUrl(item);
              if (calendarUrl) {
                actions.appendChild(
                  createActionButton(t("action_google_calendar"), () => {
                    const popup = window.open(calendarUrl, "_blank", "noopener");
                    if (!popup) {
                      window.location.href = calendarUrl;
                    }
                  }, "btn ghost")
                );
              }
              const icsUrl = buildIcsDownloadUrl(item);
              if (icsUrl) {
                actions.appendChild(
                  createActionButton(t("action_samsung_calendar"), () => {
                    const popup = window.open(icsUrl, "_blank", "noopener");
                    if (!popup) {
                      window.location.href = icsUrl;
                    }
                  }, "btn ghost")
                );
              } else if (item.preferred_date && item.preferred_time) {
                actions.appendChild(
                  createActionButton(t("action_samsung_calendar"), () => {
                    downloadSamsungIcs(item);
                  }, "btn ghost")
                );
              }
              card.appendChild(actions);
              card.appendChild(editPanel);
              requestsList.appendChild(card);
            });
          if (!filtered.length) {
            requestsList.innerHTML = `<p class="hint">${t("no_requests_found")}</p>`;
          }
        } catch (_error) {
          latestRequests = [];
          requestSlots = [];
          maybeSlots = [];
          renderCalendarView();
          requestsStatus.textContent = t("failed_load_requests");
        }
      };

      saveAvailabilityBtn.addEventListener("click", saveAvailability);
      if (calendarToday) {
        calendarToday.addEventListener("click", () => {
          const todayKey = getDateKey(new Date());
          if (calendarView === "day" && calendarDay) {
            calendarDay.value = todayKey;
          } else if (calendarView === "month" && calendarMonth) {
            calendarMonth.value = todayKey.slice(0, 7);
          } else if (calendarStart) {
            calendarStart.value = todayKey;
          }
          renderCalendarView();
        });
      }
      if (calendarStart) {
        calendarStart.addEventListener("change", renderCalendarView);
      }
      if (calendarDay) {
        calendarDay.addEventListener("change", renderCalendarView);
      }
      if (calendarMonth) {
        calendarMonth.addEventListener("change", renderCalendarView);
      }
      calendarViewButtons.forEach((button) => {
        button.addEventListener("click", () => {
          setCalendarView(button.dataset.calendarView || "week");
        });
      });
      tourTzSelect.addEventListener("change", () => {
        const todayKey = getDateKey(new Date());
        if (calendarStart) {
          calendarStart.value = todayKey;
        }
        if (calendarDay) {
          calendarDay.value = todayKey;
        }
        if (calendarMonth) {
          calendarMonth.value = todayKey.slice(0, 7);
        }
        renderCalendarView();
      });
      if (bufferInput) {
        bufferInput.addEventListener("change", () => {
          const normalizedBuffer = normalizeBufferMinutes(bufferInput.value, 0);
          bufferInput.value = String(normalizedBuffer);
          citySchedules = citySchedules.map((schedule) =>
            normalizeCitySchedule({
              ...schedule,
              buffer_minutes: normalizedBuffer,
            })
          );
          if (cityScheduleWizard) {
            cityScheduleWizard.querySelectorAll('input[data-field="buffer_minutes"]').forEach((input) => {
              input.value = String(normalizedBuffer);
            });
          }
          applyCityTemplateBlocks({ announce: false });
          renderCalendarView();
          queueAutoSave(t("saving_city_schedule"), { persist: true });
        });
      }
      addBlockedBtn.addEventListener("click", () => {
        blockedStatus.textContent = "";
        const date = blockedDate.value;
        const start = blockedStart.value;
        const end = blockedEnd.value;
        if (!date || !start || !end) {
          blockedStatus.textContent = "Add date, start time, and end time.";
          return;
        }
        const startMinutes = timeToMinutes(start);
        const endMinutes = timeToMinutes(end);
        if (startMinutes === null || endMinutes === null || endMinutes <= startMinutes) {
          blockedStatus.textContent = "End time must be after start time.";
          return;
        }
        for (let minutes = startMinutes; minutes < endMinutes; minutes += SLOT_MINUTES) {
          blockedSlots.push({
            date,
            start: minutesToTime(minutes),
            end: minutesToTime(Math.min(minutes + SLOT_MINUTES, endMinutes)),
            reason: blockedReason.value.trim(),
            kind: "manual",
          });
        }
        blockedDate.value = "";
        blockedStart.value = "";
        blockedEnd.value = "";
        blockedReason.value = "";
        renderBlockedSlots();
        renderCalendarView();
        queueAutoSave(t("saving_city_schedule"), { persist: true });
      });
      blockFullDayBtn.addEventListener("click", () => {
        blockedStatus.textContent = "";
        const date = blockedDate.value;
        if (!date) {
          blockedStatus.textContent = "Add a date first.";
          return;
        }
        const reason = blockedReason.value.trim();
        blockFullDayForDate(date, reason);
        blockedDate.value = "";
        blockedStart.value = "";
        blockedEnd.value = "";
        blockedReason.value = "";
        blockedStatus.textContent = "Full day blocked.";
        renderBlockedSlots();
        renderCalendarView();
        queueAutoSave(t("saving_city_schedule"), { persist: true });
      });
      blockFullRangeBtn.addEventListener("click", () => {
        blockedStatus.textContent = "";
        const startValue = blockedFullRangeStart.value;
        const endValue = blockedFullRangeEnd.value;
        if (!startValue || !endValue) {
          blockedStatus.textContent = "Add a start and end date.";
          return;
        }
        const startDate = parseDateKey(startValue);
        const endDate = parseDateKey(endValue);
        if (!startDate || !endDate || startDate > endDate) {
          blockedStatus.textContent = "End date must be after start date.";
          return;
        }
        const reason = blockedReason.value.trim();
        const current = new Date(startDate.getTime());
        while (current <= endDate) {
          blockFullDayForDate(toDateKey(current), reason);
          current.setUTCDate(current.getUTCDate() + 1);
        }
        blockedFullRangeStart.value = "";
        blockedFullRangeEnd.value = "";
        blockedReason.value = "";
        blockedStatus.textContent = "Full-day range blocked.";
        renderBlockedSlots();
        renderCalendarView();
        queueAutoSave(t("saving_city_schedule"), { persist: true });
      });
      addRecurringBtn.addEventListener("click", () => {
        if (!recurringStatus) return;
        recurringStatus.textContent = "";
        const days = getSelectedRecurringDays();
        if (!days.length) {
          recurringStatus.textContent = "Pick at least one day.";
          return;
        }
        const allDay = recurringAllDay && recurringAllDay.checked;
        const start = recurringStart ? recurringStart.value : "";
        const end = recurringEnd ? recurringEnd.value : "";
        if (!allDay) {
          const startMinutes = timeToMinutes(start);
          const endMinutes = timeToMinutes(end);
          if (startMinutes === null || endMinutes === null || endMinutes <= startMinutes) {
            recurringStatus.textContent = "End time must be after start time.";
            return;
          }
        }
        recurringBlocks.push({
          days,
          all_day: !!allDay,
          start: allDay ? "" : start,
          end: allDay ? "" : end,
          reason: recurringReason ? recurringReason.value.trim() : "",
        });
        if (recurringDays) {
          recurringDays.querySelectorAll("input[type=\"checkbox\"]:checked").forEach((input) => {
            input.checked = false;
          });
        }
        if (recurringAllDay) recurringAllDay.checked = false;
        if (recurringStart) recurringStart.value = "";
        if (recurringEnd) recurringEnd.value = "";
        if (recurringReason) recurringReason.value = "";
        recurringStatus.textContent = "Recurring block added.";
        renderRecurringList();
        renderCalendarView();
        queueAutoSave(t("saving_city_schedule"), { persist: true });
      });

      if (addTourRowBtn) {
        addTourRowBtn.addEventListener("click", () => {
          if (!tourScheduleList) return;
          tourScheduleList.appendChild(createTourRow());
        });
      }
      if (addTourPartnerRowBtn) {
        addTourPartnerRowBtn.addEventListener("click", () => {
          if (!tourPartnersList) return;
          tourPartnersList.appendChild(createTourPartnerRow());
        });
      }
      if (quickAddSubmitBtn) {
        quickAddSubmitBtn.addEventListener("click", addQuickEntry);
      }
      if (quickAddStart && quickAddEnd) {
        quickAddStart.addEventListener("change", () => {
          if (!quickAddEnd.value) {
            quickAddEnd.value = quickAddStart.value;
          }
        });
      }
      if (quickAddNotes) {
        quickAddNotes.addEventListener("keydown", (event) => {
          if (event.key === "Enter") {
            event.preventDefault();
            addQuickEntry();
          }
        });
      }
      if (saveTourScheduleBtn) {
        saveTourScheduleBtn.addEventListener("click", saveTourSchedule);
      }
      if (menuAutoTemplateBlocks) {
        menuAutoTemplateBlocks.addEventListener("change", () => {
          autoTemplateBlocksEnabled = !!menuAutoTemplateBlocks.checked;
          applyCityTemplateBlocks({ announce: false });
          if (menuAutoBlockStatus) {
            menuAutoBlockStatus.textContent = autoTemplateBlocksEnabled ? t("auto_blocks_enabled") : t("auto_blocks_disabled");
          }
          queueAutoSave(t("saving_city_schedule"), { persist: true });
        });
      }
      if (menuClearAutoBlocks) {
        menuClearAutoBlocks.addEventListener("click", () => {
          autoTemplateBlocksEnabled = false;
          if (menuAutoTemplateBlocks) {
            menuAutoTemplateBlocks.checked = false;
          }
          clearTemplateSlotsFromBlocked();
          applyCityTemplateBlocks({ announce: false });
          if (menuAutoBlockStatus) {
            menuAutoBlockStatus.textContent = t("auto_blocks_cleared");
          }
          queueAutoSave(t("saving_city_schedule"), { persist: true });
        });
      }
      if (clearCityTemplatesBtn) {
        clearCityTemplatesBtn.addEventListener("click", async () => {
          citySchedules = citySchedules.map((schedule) =>
            normalizeCitySchedule({
              ...schedule,
              ready_start: "00:00",
              leave_day_end: "23:59",
              has_sleep: false,
              sleep_days: [],
              has_break: false,
              break_days: [],
            })
          );
          renderCityScheduleWizard();
          applyCityTemplateBlocks({ announce: false });
          clearTemplateSlotsFromBlocked();
          renderBlockedSlots();
          renderCalendarView();
          queueAutoSave(t("template_rules_clearing"));
          await saveAvailability();
          if (cityScheduleStatus) {
            cityScheduleStatus.textContent = t("template_rules_cleared");
          }
        });
      }
      if (addGalleryRowBtn) {
        addGalleryRowBtn.addEventListener("click", () => {
          if (!galleryList) return;
          galleryList.appendChild(createGalleryRow());
        });
      }
      if (saveGalleryBtn) {
        saveGalleryBtn.addEventListener("click", saveGallery);
      }
      if (addEmployeeBtn) {
        addEmployeeBtn.addEventListener("click", addEmployee);
      }
      if (employeePasswordInput) {
        employeePasswordInput.addEventListener("keydown", (event) => {
          if (event.key === "Enter") {
            event.preventDefault();
            addEmployee();
          }
        });
      }
      if (photoDisplayModeSelect) {
        photoDisplayModeSelect.addEventListener("change", updatePhotoModeUi);
      }

      if (toggleBlockedListBtn) {
        setBlockedListVisible(false);
        toggleBlockedListBtn.addEventListener("click", () => {
          const isHidden = blockedList.classList.contains("hidden");
          setBlockedListVisible(isHidden);
        });
      }
      refreshBtn.addEventListener("click", loadRequests);
      statusFilter.addEventListener("change", loadRequests);
      // Keep requests stable while reviewing/editing. Use Refresh button when needed.

      const initialLanguage = getStoredLanguage() || detectBrowserLanguage();
      currentLanguage = initialLanguage;
      document.documentElement.setAttribute("lang", currentLanguage);
      setAdminPanel(getStoredAdminPanel(), false);
      createDayChoices(menuWorkDays);
      createDayChoices(menuBreakDays);
      applyAccountCenterToUi();
      applyScheduleMenuConfigToUi();
      applyServicesConfigToUi();
      updatePhotoModeUi();
      closeAdminMenu();
      if (notifPanel) {
        notifPanel.classList.add("hidden");
      }

      setCalendarView("week");
      populateTimezones();
      applyTimezoneValue(DEFAULT_TIMEZONE);
      if (tourTimezoneField) {
        tourTimezoneField.hidden = true;
      }
      renderRecurringDayChoices();
      loadAvailability();
      loadTourSchedule();
      loadGallery();
      loadRequests();
      loadEmployees();
      applyLanguage(initialLanguage, true);
    </script>
  </body>
</html>
