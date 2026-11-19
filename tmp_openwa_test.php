<?php
require __DIR__ . '/vendor/autoload.php';

function getEnvValue($key) {
    $path = __DIR__ . '/.env';
    if (!file_exists($path)) return null;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        $k = trim($k);
        $v = trim($v, "\"'");
        if ($k === $key) return $v;
    }
    return null;
}

$base = getEnvValue('OPENWA_BASE_URL') ?: getEnvValue('OPENWA_BASE');
$session = getEnvValue('OPENWA_SESSION_ID') ?: getEnvValue('OPENWA_SESSION');
$key = getEnvValue('OPENWA_API_KEY') ?: getEnvValue('OPENWA_KEY');

if (!$base || !$session) {
    echo "OPENWA_BASE_URL or OPENWA_SESSION_ID not found in .env\n";
    exit(1);
}

$client = new \GuzzleHttp\Client(['http_errors' => false]);

$out = '';
$outAppend = function($s) use (&$out) { $out .= $s . "\n"; };

$outAppend("SESSION STATUS REQUEST:");
try {
    $res = $client->get(rtrim($base, '/') . '/api/sessions/' . $session, [
        'headers' => [
            'Accept' => 'application/json',
            'X-API-Key' => $key,
        ],
        'timeout' => 10,
    ]);
    $outAppend("HTTP/" . $res->getStatusCode());
    $outAppend((string)$res->getBody());
    $outAppend("");
} catch (Exception $e) {
    $outAppend("Session request error: " . $e->getMessage());
    $outAppend("");
}

$outAppend("SEND TEST MESSAGE REQUEST:");
    // Try multiple payload variants to see which one the server accepts
    $variants = [
        ['chatId' => '966561624285@c.us', 'text' => 'Test from POS - chatId @c.us'],
        ['chatId' => '966561624285@s.whatsapp.net', 'text' => 'Test from POS - chatId @s.whatsapp.net'],
        ['to' => '966561624285', 'text' => 'Test from POS - to numeric'],
        ['to' => '+966561624285', 'text' => 'Test from POS - to with plus'],
        ['chatId' => '966561624285@c.us', 'text' => 'Test from POS - with mentions', 'mentions' => ['628123456789@c.us']],
    ];

    foreach ($variants as $i => $payload) {
    $outAppend("--- Variant " . ($i+1) . " payload:" . json_encode($payload));
        try {
            $res = $client->post(rtrim($base, '/') . '/api/sessions/' . $session . '/messages/send-text', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'X-API-Key' => $key,
                ],
                'json' => $payload,
                'timeout' => 10,
            ]);

            $outAppend("HTTP/" . $res->getStatusCode());
            $outAppend((string)$res->getBody());
            $outAppend("");
        } catch (Exception $e) {
            $outAppend("Send request error: " . $e->getMessage());
            $outAppend("");
        }
    }

    // write results to file for reliable retrieval
    file_put_contents(__DIR__ . '/tmp_openwa_results.txt', $out);
    echo "WROTE: tmp_openwa_results.txt\n";
