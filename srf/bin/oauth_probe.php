#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Prints OAuth attempt results (HTTP code + body snippet). Does not print secrets.
 * Run on your host (cron “run once” or local): php srf/bin/oauth_probe.php
 */

$root = dirname(__DIR__);
require $root . '/bootstrap.php';

if (!srf_srg_configured()) {
    fwrite(STDERR, "Set SRG_CONSUMER_KEY and SRG_CONSUMER_SECRET in srf/config.local.php first.\n");
    exit(1);
}

$key = trim(SRG_CONSUMER_KEY);
$sec = trim(SRG_CONSUMER_SECRET);
if (strncmp($key, "\xEF\xBB\xBF", 3) === 0) {
    $key = trim(substr($key, 3));
}
if (strncmp($sec, "\xEF\xBB\xBF", 3) === 0) {
    $sec = trim(substr($sec, 3));
}
$apiKey = SRG_API_KEY !== '' ? trim(SRG_API_KEY) : '';

$cfg = srf_merged_config();
$configured = $cfg['oauth_token_url'] ?? 'https://srgssr-prod.apigee.net/oauth/v1/accesstoken?grant_type=client_credentials';
$p = parse_url($configured);
$fromCfg = (is_array($p) && !empty($p['host']))
    ? (($p['scheme'] ?? 'https') . '://' . $p['host'] . ($p['path'] ?? '/oauth/v1/accesstoken'))
    : '';
$bases = array_values(array_unique(array_filter([
    $fromCfg,
    'https://srgssr-prod.apigee.net/oauth/v1/accesstoken',
    'https://api.srgssr.ch/oauth/v1/accesstoken',
])));

$basic = base64_encode($key . ':' . $sec);
$headersBasic = [
    'Authorization' => 'Basic ' . $basic,
    'Cache-Control' => 'no-cache',
];

echo "SRG OAuth probe (key len=" . strlen($key) . ", secret len=" . strlen($sec) . ", apikey=" . ($apiKey !== '' ? 'yes' : 'no') . ")\n\n";

$n = 0;
foreach ($bases as $base) {
    $uForm = $base . ($apiKey !== '' ? '?apikey=' . rawurlencode($apiKey) : '');
    $n++;
    $r = SrfHttp::postRaw(
        $uForm,
        'grant_type=client_credentials',
        $headersBasic + ['Content-Type' => 'application/x-www-form-urlencoded'],
        false
    );
    echo "#{$n} POST form+Basic {$uForm}\n    HTTP {$r['code']} " . snippet($r['body']) . "\n";

    $uQuery = $base . '?grant_type=client_credentials' . ($apiKey !== '' ? '&apikey=' . rawurlencode($apiKey) : '');
    $n++;
    $r = SrfHttp::postEmpty($uQuery, $headersBasic, false);
    echo "#{$n} POST empty+query+Basic …/accesstoken?grant_type=…\n    HTTP {$r['code']} " . snippet($r['body']) . "\n";

    if ($apiKey !== '') {
        $n++;
        $r = SrfHttp::postRaw(
            $base,
            'grant_type=client_credentials',
            $headersBasic + [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'x-api-key' => $apiKey,
            ],
            false
        );
        echo "#{$n} POST form+Basic+x-api-key {$base}\n    HTTP {$r['code']} " . snippet($r['body']) . "\n";
    }

    $uBody = $base . ($apiKey !== '' ? '?apikey=' . rawurlencode($apiKey) : '');
    $bodyCreds = http_build_query([
        'grant_type' => 'client_credentials',
        'client_id' => $key,
        'client_secret' => $sec,
    ], '', '&', PHP_QUERY_RFC3986);
    $n++;
    $r = SrfHttp::postRaw($uBody, $bodyCreds, [
        'Content-Type' => 'application/x-www-form-urlencoded',
        'Cache-Control' => 'no-cache',
    ], false);
    echo "#{$n} POST client_id+client_secret in body (no Basic) {$uBody}\n    HTTP {$r['code']} " . snippet($r['body']) . "\n";

    echo "\n";
}

echo "If every line is HTTP 401 with empty access_token, SRG is rejecting this app’s credentials or the app is not allowed to use OAuth.\n";

function snippet(string $body): string
{
    $body = preg_replace('/"access_token"\s*:\s*"[^"]*"/', '"access_token":"(redacted)"', $body) ?? $body;
    $one = preg_replace("/\s+/", ' ', $body);
    return mb_substr($one, 0, 220) . (mb_strlen($one) > 220 ? '…' : '');
}
