<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

require_admin();

$format = strtolower((string) ($_GET['format'] ?? 'json'));
$requestsPath = DATA_DIR . '/requests.json';
$store = read_json_file($requestsPath, ['requests' => []]);
$requests = $store['requests'] ?? [];
if (!is_array($requests)) {
    $requests = [];
}
$declinedStore = read_json_file(DATA_DIR . '/declined.json', ['requests' => []]);
$declinedRequests = $declinedStore['requests'] ?? [];
if (!is_array($declinedRequests)) {
    $declinedRequests = [];
}

$allRequests = array_merge($requests, $declinedRequests);
$byId = [];
foreach ($allRequests as $request) {
    if (!is_array($request)) {
        continue;
    }
    $id = (string) ($request['id'] ?? '');
    if ($id === '') {
        $byId[] = $request;
        continue;
    }
    $byId[$id] = $request;
}
$requests = array_values($byId);

$timestamp = gmdate('Ymd_His');

if ($format === 'csv') {
    $columns = [
        'id',
        'status',
        'created_at',
        'updated_at',
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
        'client_timezone',
        'tour_timezone',
        'buffer_minutes',
        'notes',
        'contact_followup',
        'contact_channel',
        'followup_cities',
        'payment_method',
        'deposit_amount',
        'deposit_currency',
        'payment_link',
        'payment_email_sent_at',
    ];

    $output = fopen('php://output', 'wb');
    if ($output === false) {
        json_response(['error' => 'Failed to open output stream'], 500);
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="booking-requests-' . $timestamp . '.csv"');

    fputcsv($output, $columns);
    foreach ($requests as $request) {
        $row = [];
        foreach ($columns as $column) {
            $value = $request[$column] ?? '';
            if (is_array($value)) {
                $value = implode(', ', array_map('strval', $value));
            } elseif (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif ($value === null) {
                $value = '';
            } else {
                $value = (string) $value;
            }
            $row[] = $value;
        }
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}

if ($format !== 'json') {
    json_response(['error' => 'Invalid format'], 400);
}

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="booking-requests-' . $timestamp . '.json"');
echo json_encode(['requests' => $requests], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
exit;
