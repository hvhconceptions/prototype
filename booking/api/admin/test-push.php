<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$payload = get_request_body();
$title = trim((string) ($payload['title'] ?? 'Test push'));
$body = trim((string) ($payload['body'] ?? 'This is a test notification.'));
$token = trim((string) ($payload['token'] ?? ''));
$data = $payload['data'] ?? [];
if (!is_array($data)) {
    $data = [];
}

$hasV1 = get_fcm_project_id() !== '' && get_fcm_service_account() !== null;
$hasLegacy = FCM_SERVER_KEY !== '';
if (!$hasV1 && !$hasLegacy) {
    json_response(['error' => 'FCM not configured'], 500);
}

$tokens = $token !== '' ? [$token] : get_push_token_strings();
if (!$tokens) {
    json_response(['error' => 'No push tokens found'], 404);
}

$pushTitle = $title !== '' ? $title : 'Test push';
$details = [];
$ok = false;

if ($hasV1) {
    foreach ($tokens as $item) {
        $message = [
            'token' => $item,
            'notification' => [
                'title' => $pushTitle,
                'body' => $body,
            ],
            'android' => [
                'priority' => 'HIGH',
                'notification' => [
                    'channel_id' => 'booking_alerts',
                    'sound' => 'default',
                ],
            ],
            'data' => $data,
        ];
        $result = send_fcm_v1_message($message);
        if (!empty($result['ok'])) {
            $ok = true;
        }
        $details[] = [
            'token_suffix' => substr($item, -12),
            'ok' => !empty($result['ok']),
            'status' => (int) ($result['status'] ?? 0),
            'invalid_token' => !empty($result['invalid_token']),
            'error' => (string) ($result['error'] ?? ''),
        ];
    }
} else {
    $ok = send_push_to_tokens($tokens, $pushTitle, $body, $data);
}

json_response([
    'ok' => $ok,
    'sent' => $ok ? count($tokens) : 0,
    'tokens' => count($tokens),
    'mode' => $hasV1 ? 'v1' : 'legacy',
    'details' => $details,
]);
