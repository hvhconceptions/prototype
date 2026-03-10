<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    exit('Method Not Allowed');
}

$sourceUrl = 'https://raw.githubusercontent.com/hvhconceptions/prototype/main/app-debug.apk';
$filename = 'HVH-android.apk';

header('Content-Type: application/vnd.android.package-archive');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (function_exists('curl_init')) {
    $ch = curl_init($sourceUrl);
    curl_setopt_array($ch, [
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_HEADER => false,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 300,
        CURLOPT_USERAGENT => 'HVH-APK-Proxy/1.0',
        CURLOPT_WRITEFUNCTION => static function ($curl, $data) {
            echo $data;
            return strlen($data);
        },
    ]);

    curl_exec($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $hasError = curl_errno($ch) !== 0;
    curl_close($ch);

    if ($hasError || $statusCode >= 400) {
        if (!headers_sent()) {
            http_response_code(502);
            header('Content-Type: text/plain; charset=UTF-8');
        }
        exit('Unable to download APK right now. Please try again.');
    }
    exit;
}

$context = stream_context_create([
    'http' => [
        'timeout' => 300,
        'follow_location' => 1,
        'user_agent' => 'HVH-APK-Proxy/1.0',
    ],
]);

$in = @fopen($sourceUrl, 'rb', false, $context);
if ($in === false) {
    if (!headers_sent()) {
        http_response_code(502);
        header('Content-Type: text/plain; charset=UTF-8');
    }
    exit('Unable to download APK right now. Please try again.');
}

while (!feof($in)) {
    echo fread($in, 8192);
}
fclose($in);
