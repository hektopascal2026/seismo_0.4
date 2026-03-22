<?php

declare(strict_types=1);

define('SRF_ROOT', __DIR__);

$local = SRF_ROOT . '/config.local.php';
if (is_readable($local)) {
    require $local;
}

if (!defined('SRF_DB_HOST')) {
    define('SRF_DB_HOST', 'localhost:3306');
}
if (!defined('SRF_DB_NAME')) {
    define('SRF_DB_NAME', '');
}
if (!defined('SRF_DB_USER')) {
    define('SRF_DB_USER', '');
}
if (!defined('SRF_DB_PASS')) {
    define('SRF_DB_PASS', '');
}
if (!defined('SRG_CONSUMER_KEY')) {
    define('SRG_CONSUMER_KEY', '');
}
if (!defined('SRG_CONSUMER_SECRET')) {
    define('SRG_CONSUMER_SECRET', '');
}
if (!defined('SRG_API_KEY')) {
    define('SRG_API_KEY', '');
}
if (!defined('SRF_WEB_SYNC_SECRET')) {
    define('SRF_WEB_SYNC_SECRET', '');
}

/**
 * @return array<string, mixed>
 */
function srf_load_json_config(): array
{
    $path = SRF_ROOT . '/config.json';
    if (!is_readable($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return [];
    }
    $out = [];
    foreach ($data as $k => $v) {
        if (is_string($k) && $k !== '' && $k[0] === '_') {
            continue;
        }
        $out[$k] = $v;
    }
    return $out;
}

function srf_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $host = SRF_DB_HOST;
    $port = null;
    if (preg_match('/^(.+):(\d+)$/', $host, $m)) {
        $host = $m[1];
        $port = (int) $m[2];
    }
    $dsn = 'mysql:host=' . $host . ($port ? ';port=' . $port : '') . ';dbname=' . SRF_DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, SRF_DB_USER, SRF_DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}

function srf_configured(): bool
{
    return SRF_DB_NAME !== '' && SRF_DB_USER !== '';
}

function srf_srg_configured(): bool
{
    return SRG_CONSUMER_KEY !== '' && SRG_CONSUMER_SECRET !== '';
}

/** Web path to Seismo assets (from /srf/public/). */
function srf_assets_href(): string
{
    return '../../assets/css/style.css';
}

/**
 * Defaults merged with config.json (endpoints may be overridden per SRG portal).
 *
 * @return array<string, mixed>
 */
function srf_merged_config(): array
{
    return array_merge(
        [
            'bu' => 'srf',
            'page_size' => 30,
            'oauth_token_url' => 'https://srgssr-prod.apigee.net/oauth/v1/accesstoken?grant_type=client_credentials',
            'video_latest_episodes_url' => 'https://api.srgssr.ch/videometadata/v2/latest_episodes',
            'video_episodes_by_date_url' => 'https://api.srgssr.ch/videometadata/v2/episodes_by_date/{day}',
            'use_episodes_by_date' => false,
            'subtitle_lookup_url_template' => 'https://api.srgssr.ch/srgssr-play-subtitles/v2/subtitles?episode={urn}',
        ],
        srf_load_json_config()
    );
}
