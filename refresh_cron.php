<?php
/**
 * Seismo Background Refresh (CLI cronjob)
 *
 * Runs the same refresh cycle as the web UI "Refresh All" button:
 * feeds (parallel), emails, lex/jus sources, Magnitu rescoring.
 *
 * Setup:  star/15 * * * * /usr/bin/php /path/to/refresh_cron.php
 *          (replace "star" with *)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('CLI only');
}

set_time_limit(300);

$startTime = microtime(true);
$scriptDir = __DIR__;

function clog(string $level, string $msg): void {
    $ts = date('c');
    echo "[{$ts}] {$level}: {$msg}\n";
}

clog('INFO', 'Starting Seismo refresh...');

require $scriptDir . '/config.php';
require $scriptDir . '/vendor/autoload.php';
require $scriptDir . '/controllers/magnitu.php';
require $scriptDir . '/controllers/lex_jus.php';
require $scriptDir . '/controllers/scraper.php';
require $scriptDir . '/controllers/mail.php';
require $scriptDir . '/controllers/rss.php';
require $scriptDir . '/controllers/dashboard.php';

initDatabase();
$pdo = getDbConnection();

$hasErrors = false;

setMagnituConfig($pdo, 'last_refresh_at', (string)time());

// --- Feeds (parallel curl_multi) ---
try {
    [$refreshed, $skipped, $failed, $failedNames] = refreshAllFeeds($pdo);
    $msg = "{$refreshed} feeds refreshed";
    if ($skipped > 0) $msg .= ", {$skipped} tripped";
    if ($failed > 0) { $msg .= ", {$failed} failed"; $hasErrors = true; }
    clog('INFO', $msg);
    if (!empty($failedNames)) {
        foreach ($failedNames as $fn) clog('WARN', "  Failed: $fn");
    }
} catch (\Exception $e) {
    clog('ERROR', 'Feeds: ' . $e->getMessage());
    $hasErrors = true;
}

// --- Emails (DB check only) ---
try {
    refreshEmails($pdo);
    clog('INFO', 'Emails refreshed');
} catch (\Exception $e) {
    clog('ERROR', 'Emails: ' . $e->getMessage());
    $hasErrors = true;
}

// --- Lex / Jus sources (with circuit breaker) ---
$lexCfg = getLexConfig();

$lexSources = [
    ['key' => 'eu',      'enabled' => $lexCfg['eu']['enabled'] ?? true,        'label' => 'EU',    'fn' => function($pdo) { return refreshLexItems($pdo); }],
    ['key' => 'ch',      'enabled' => $lexCfg['ch']['enabled'] ?? true,        'label' => 'CH',    'fn' => function($pdo) { return refreshFedlexItems($pdo); }],
    ['key' => 'de',      'enabled' => $lexCfg['de']['enabled'] ?? true,        'label' => 'DE',    'fn' => function($pdo) { return refreshRechtBundItems($pdo); }],
    ['key' => 'ch_bger', 'enabled' => $lexCfg['ch_bger']['enabled'] ?? false,  'label' => 'BGer',  'fn' => function($pdo) { return refreshJusItems($pdo, 'CH_BGer'); }],
    ['key' => 'ch_bge',  'enabled' => $lexCfg['ch_bge']['enabled'] ?? false,   'label' => 'BGE',   'fn' => function($pdo) { return refreshJusItems($pdo, 'CH_BGE'); }],
    ['key' => 'ch_bvger','enabled' => $lexCfg['ch_bvger']['enabled'] ?? false,  'label' => 'BVGer', 'fn' => function($pdo) { return refreshJusItems($pdo, 'CH_BVGer'); }],
];

foreach ($lexSources as $src) {
    if (!$src['enabled']) continue;
    $failKey = 'lex_' . $src['key'] . '_failures';
    if (isSourceTripped($pdo, $failKey)) {
        clog('WARN', $src['label'] . ': skipped (circuit breaker tripped)');
        continue;
    }
    try {
        $count = ($src['fn'])($pdo);
        resetSourceFailure($pdo, $failKey);
        clog('INFO', "{$count} {$src['label']} items");
    } catch (\Exception $e) {
        recordSourceFailure($pdo, $failKey);
        clog('ERROR', $src['label'] . ': ' . $e->getMessage());
        $hasErrors = true;
    }
}

// --- Magnitu rescoring ---
try {
    $recipeJson = getMagnituConfig($pdo, 'recipe_json');
    if ($recipeJson) {
        $recipeData = json_decode($recipeJson, true);
        if ($recipeData && !empty($recipeData['keywords'])) {
            magnituRescore($pdo, $recipeData);
            clog('INFO', 'Scores updated');
        }
    }
} catch (\Exception $e) {
    clog('ERROR', 'Scoring: ' . $e->getMessage());
    $hasErrors = true;
}

$elapsed = round(microtime(true) - $startTime, 1);
clog('INFO', "Done in {$elapsed}s");

exit($hasErrors ? 1 : 0);
