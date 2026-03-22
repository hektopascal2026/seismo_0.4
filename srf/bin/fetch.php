#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Fetch SRF/SRG episode list via Video API and optionally pull subtitle text (Play Subtitles API).
 *
 * Cron example (at minute 0 and 30):
 *   0,30 * * * * /usr/bin/php /path/to/seismo_0.4/srf/bin/fetch.php >> /var/log/srf-fetch.log 2>&1
 *
 * Options:
 *   --no-subtitles     Only index episode metadata from the Video API
 *   --subtitle-batch=N Max subtitle downloads this run (default 25)
 *   --delay-ms=N       Pause between subtitle HTTP requests (default 150)
 */

$root = dirname(__DIR__);
require $root . '/bootstrap.php';

$longopts = ['no-subtitles', 'subtitle-batch:', 'delay-ms:'];
$opts = function_exists('getopt') ? getopt('', $longopts) : false;
if ($opts === false) {
    $opts = [];
}

$fetchSubtitles = !isset($opts['no-subtitles']);
$subtitleBatch = isset($opts['subtitle-batch']) ? max(0, (int) $opts['subtitle-batch']) : 25;
$delayMs = isset($opts['delay-ms']) ? max(0, (int) $opts['delay-ms']) : 150;

if (!srf_configured()) {
    fwrite(STDERR, "Configure srf/config.local.php (SRF_DB_*).\n");
    exit(1);
}

if (!srf_srg_configured()) {
    fwrite(STDERR, "Configure SRG_CONSUMER_KEY and SRG_CONSUMER_SECRET in srf/config.local.php.\n");
    exit(1);
}

try {
    $pdo = srf_pdo();
    $repo = new ItemRepository($pdo);
    $service = new FetchService($repo, srf_merged_config());
    $result = $service->run($fetchSubtitles, $subtitleBatch, $delayMs);
    echo date('c'),
        ' episodes=', $result['episodes_seen'],
        ' subtitles=', $result['subtitles_fetched'],
        ' subtitle_tries=', $result['subtitles_attempted'] ?? 0,
        "\n";
    foreach ($result['errors'] as $err) {
        fwrite(STDERR, $err . "\n");
    }
    exit($result['errors'] !== [] ? 2 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
