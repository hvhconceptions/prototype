<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

require_admin();

const ADMIN_CHAT_FILE = DATA_DIR . '/admin_chat.json';
const ADMIN_CHAT_MAX_MESSAGES = 300;
const ADMIN_CHAT_MAX_MESSAGE_LENGTH = 1000;

function admin_chat_basic_auth_user(): string
{
    $user = $_SERVER['PHP_AUTH_USER'] ?? '';
    if ($user !== '') {
        return strtolower(trim((string) $user));
    }
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($header === '' && isset($_SERVER['Authorization'])) {
        $header = (string) $_SERVER['Authorization'];
    }
    if ($header !== '' && stripos($header, 'basic ') === 0) {
        $decoded = base64_decode(substr($header, 6), true);
        if ($decoded !== false) {
            $parts = explode(':', $decoded, 2);
            return strtolower(trim((string) ($parts[0] ?? '')));
        }
    }
    return 'admin';
}

function read_admin_chat_messages(): array
{
    $store = read_json_file(ADMIN_CHAT_FILE, ['messages' => []]);
    $messages = $store['messages'] ?? [];
    if (!is_array($messages)) {
        return [];
    }
    $clean = [];
    foreach ($messages as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $id = trim((string) ($entry['id'] ?? ''));
        $user = trim((string) ($entry['user'] ?? ''));
        $message = trim((string) ($entry['message'] ?? ''));
        $createdAt = trim((string) ($entry['created_at'] ?? ''));
        if ($message === '') {
            continue;
        }
        if ($id === '') {
            $id = sha1($user . '|' . $message . '|' . $createdAt);
        }
        if ($user === '') {
            $user = 'admin';
        }
        if ($createdAt === '') {
            $createdAt = gmdate('c');
        }
        $clean[] = [
            'id' => $id,
            'user' => $user,
            'message' => substr($message, 0, ADMIN_CHAT_MAX_MESSAGE_LENGTH),
            'created_at' => $createdAt,
        ];
    }
    if (count($clean) > ADMIN_CHAT_MAX_MESSAGES) {
        $clean = array_slice($clean, -ADMIN_CHAT_MAX_MESSAGES);
    }
    return $clean;
}

function write_admin_chat_messages(array $messages): void
{
    if (count($messages) > ADMIN_CHAT_MAX_MESSAGES) {
        $messages = array_slice($messages, -ADMIN_CHAT_MAX_MESSAGES);
    }
    write_json_file(ADMIN_CHAT_FILE, [
        'messages' => array_values($messages),
        'updated_at' => gmdate('c'),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    json_response(['ok' => true, 'messages' => read_admin_chat_messages()]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$payload = get_request_body();
$message = trim((string) ($payload['message'] ?? ''));
if ($message === '') {
    json_response(['error' => 'Message is required'], 422);
}
$message = substr($message, 0, ADMIN_CHAT_MAX_MESSAGE_LENGTH);
$user = admin_chat_basic_auth_user();
if ($user === '') {
    $user = 'admin';
}

$messages = read_admin_chat_messages();
$messages[] = [
    'id' => bin2hex(random_bytes(10)),
    'user' => $user,
    'message' => $message,
    'created_at' => gmdate('c'),
];
write_admin_chat_messages($messages);

json_response(['ok' => true, 'messages' => $messages]);
