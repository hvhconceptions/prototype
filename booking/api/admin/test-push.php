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

$ok = send_push_to_tokens($tokens, $title !== '' ? $title : 'Test push', $body, $data);

json_response([
    'ok' => $ok,
    'sent' => $ok ? count($tokens) : 0,
    'tokens' => count($tokens),
    'mode' => $hasV1 ? 'v1' : 'legacy',
]);
