<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['error' => 'Method not allowed'], 405);
}

$requestedLimit = isset($_GET['limit']) ? (int) $_GET['limit'] : 25;
$limit = max(1, min($requestedLimit, 200));

$logPath = EMAIL_LOG_FILE;
if (!file_exists($logPath)) {
    json_response([
        'ok' => true,
        'count' => 0,
        'failures' => [],
    ]);
}

$lines = @file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (!is_array($lines)) {
    json_response([
        'ok' => true,
        'count' => 0,
        'failures' => [],
    ]);
}

$failures = [];
for ($index = count($lines) - 1; $index >= 0; $index--) {
    if (count($failures) >= $limit) {
        break;
    }

    $line = trim((string) $lines[$index]);
    if ($line === '' || stripos($line, ' | FAIL | ') === false) {
        continue;
    }

    $parts = array_map(static function ($value): string {
        return trim((string) $value);
    }, explode('|', $line));

    $entry = [
        'timestamp' => $parts[0] ?? '',
        'type' => strtolower((string) ($parts[1] ?? '')),
        'status' => strtolower((string) ($parts[2] ?? '')),
        'to' => '',
        'subject' => '',
        'detail' => '',
        'raw' => $line,
    ];

    for ($partIndex = 3; $partIndex < count($parts); $partIndex++) {
        $part = (string) $parts[$partIndex];
        if ($part === '' || strpos($part, '=') === false) {
            continue;
        }
        [$key, $value] = explode('=', $part, 2);
        $key = strtolower(trim($key));
        $value = trim($value);
        if ($key === 'to') {
            $entry['to'] = $value;
        } elseif ($key === 'subject') {
            $entry['subject'] = $value;
        } elseif ($key === 'detail') {
            $entry['detail'] = $value;
        }
    }

    $failures[] = $entry;
}

json_response([
    'ok' => true,
    'count' => count($failures),
    'failures' => $failures,
]);
