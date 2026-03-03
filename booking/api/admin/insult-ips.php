<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';
require_admin();

$path = DATA_DIR . '/insult_events.json';
$store = read_json_file($path, ['events' => []]);
$events = $store['events'] ?? [];
if (!is_array($events)) {
    $events = [];
}

$rows = [];
foreach ($events as $event) {
    if (!is_array($event)) {
        continue;
    }
    $ip = trim((string) ($event['ip'] ?? ''));
    if ($ip === '') {
        continue;
    }
    $createdAt = trim((string) ($event['created_at'] ?? ''));
    if (!isset($rows[$ip])) {
        $rows[$ip] = [
            'ip' => $ip,
            'count' => 0,
            'first_seen' => $createdAt,
            'last_seen' => $createdAt,
            'last_text' => trim((string) ($event['text'] ?? '')),
            'last_context' => trim((string) ($event['context'] ?? '')),
            'last_page' => trim((string) ($event['page'] ?? '')),
            'last_user_agent' => trim((string) ($event['user_agent'] ?? '')),
        ];
    }

    $rows[$ip]['count'] += 1;
    if ($createdAt !== '') {
        if ($rows[$ip]['first_seen'] === '' || strcmp($createdAt, (string) $rows[$ip]['first_seen']) < 0) {
            $rows[$ip]['first_seen'] = $createdAt;
        }
        if ($rows[$ip]['last_seen'] === '' || strcmp($createdAt, (string) $rows[$ip]['last_seen']) > 0) {
            $rows[$ip]['last_seen'] = $createdAt;
        }
    }
    $rows[$ip]['last_text'] = trim((string) ($event['text'] ?? ''));
    $rows[$ip]['last_context'] = trim((string) ($event['context'] ?? ''));
    $rows[$ip]['last_page'] = trim((string) ($event['page'] ?? ''));
    $rows[$ip]['last_user_agent'] = trim((string) ($event['user_agent'] ?? ''));
}

$list = array_values($rows);
usort($list, static function (array $a, array $b): int {
    $aSeen = (string) ($a['last_seen'] ?? '');
    $bSeen = (string) ($b['last_seen'] ?? '');
    if ($aSeen === $bSeen) {
        return ((int) ($b['count'] ?? 0)) <=> ((int) ($a['count'] ?? 0));
    }
    return strcmp($bSeen, $aSeen);
});

json_response([
    'ok' => true,
    'count' => count($list),
    'ips' => $list,
]);

