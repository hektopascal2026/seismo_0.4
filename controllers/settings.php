<?php
/**
 * Settings Controller
 *
 * Renders the settings page with all tabs — current order:
 *   General, RSS, Mail, Scraper, Lex / Jus, Leg, Magnitu, LLM, Satellite,
 *   Diagnostics, Styleguide.
 *
 * Also hosts the static pages About + Styleguide. The former Beta page is
 * retired; handleBetaPage() redirects to Settings > LLM.
 *
 * Legacy tab slugs (basic, script, calendar, satellites, feed_diagnostics)
 * are normalized at the top of handleSettingsPage() so old bookmarks keep
 * working.
 */

function handleSettingsPage($pdo) {
    $settingsTab = $_GET['tab'] ?? 'general';

    // Legacy tab-slug compatibility. Old links/bookmarks using the pre-0.4.5
    // slugs keep working; the pill nav and all new code use the new slugs.
    $legacyTabMap = [
        'basic'             => 'general', // RSS moved to ?action=feeds&view=feeds; Substack lives in tab=rss
        'script'            => 'mail',    // Mail half of the old combined tab; scraper callers override below
        'calendar'          => 'leg',
        'satellites'        => 'satellite',
        'feed_diagnostics'  => 'diagnostics',
    ];
    if (isset($legacyTabMap[$settingsTab])) {
        $settingsTab = $legacyTabMap[$settingsTab];
    }

    $feedDiagnosticsData = null;
    if ($settingsTab === 'diagnostics') {
        if (FEED_DIAGNOSTIC_KEY !== '' && (string)($_GET['key'] ?? '') !== FEED_DIAGNOSTIC_KEY) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo "403 Forbidden — set FEED_DIAGNOSTIC_KEY in config.local.php and pass the same value as ?key= (e.g. ?action=settings&tab=diagnostics&key=…)\n";
            exit;
        }
        set_time_limit(600);
        $fdFormat = strtolower((string)($_GET['format'] ?? 'html'));
        if ($fdFormat === 'text' || $fdFormat === 'txt' || $fdFormat === 'plain') {
            $t0 = microtime(true);
            $fdDiag = runFeedDiagnostics($pdo);
            header('Content-Type: text/plain; charset=utf-8');
            header('X-Robots-Tag: noindex, nofollow');
            echo formatFeedDiagnosticsReport($fdDiag, microtime(true) - $t0);
            exit;
        }
    }

    $feedsStmt = $pdo->query("SELECT * FROM feeds WHERE source_type = 'rss' OR source_type IS NULL ORDER BY created_at DESC");
    $allFeeds = $feedsStmt->fetchAll();
    
    $substackFeedsStmt = $pdo->query("SELECT * FROM feeds WHERE source_type = 'substack' ORDER BY created_at DESC");
    $substackFeeds = $substackFeedsStmt->fetchAll();
    
    $tagsStmt = $pdo->query("SELECT DISTINCT category FROM feeds WHERE category IS NOT NULL AND category != '' AND (source_type = 'rss' OR source_type IS NULL) ORDER BY category");
    $allTags = $tagsStmt->fetchAll(PDO::FETCH_COLUMN);
    
    $substackTagsStmt = $pdo->query("SELECT DISTINCT category FROM feeds WHERE source_type = 'substack' AND category IS NOT NULL AND category != '' ORDER BY category");
    $allSubstackTags = $substackTagsStmt->fetchAll(PDO::FETCH_COLUMN);
    
    $allEmailTags = esMailFilterTags($pdo);
    
    $senderTags = [];
    try {
        $emailTableName = getEmailTableName($pdo);
        
        if ($emailTableName) {
            $descStmt = $pdo->query("DESCRIBE `$emailTableName`");
            $tableColumns = $descStmt->fetchAll(PDO::FETCH_COLUMN);
            
            $hasFromEmail = in_array('from_email', $tableColumns);
            $hasFromAddr = in_array('from_addr', $tableColumns);
            $hasFromName = in_array('from_name', $tableColumns);
            
            if ($hasFromEmail && $hasFromName) {
                $sendersStmt = $pdo->query("
                    SELECT DISTINCT 
                        from_email as email,
                        COALESCE(from_name, '') as name
                    FROM `$emailTableName`
                    WHERE from_email IS NOT NULL AND from_email != ''
                    ORDER BY from_email
                ");
            } elseif ($hasFromAddr) {
                $sendersStmt = $pdo->query("
                    SELECT DISTINCT 
                        from_addr as email,
                        '' as name
                    FROM `$emailTableName`
                    WHERE from_addr IS NOT NULL AND from_addr != ''
                    ORDER BY from_addr
                ");
            } else {
                $sendersStmt = null;
            }
            
            if ($sendersStmt) {
                $senders = $sendersStmt->fetchAll();
            } else {
                $senders = [];
            }
            
            $emailCol = $hasFromEmail ? 'from_email' : ($hasFromAddr ? 'from_addr' : null);
            $hasDateReceived = in_array('date_received', $tableColumns);
            $hasCreatedAt = in_array('created_at', $tableColumns);
            
            foreach ($senders as $sender) {
                $email = $sender['email'];
                $tagStmt = $pdo->prepare("SELECT tag, disabled, removed_at FROM sender_tags WHERE from_email = ?");
                $tagStmt->execute([$email]);
                $tagResult = $tagStmt->fetch();
                
                if (!$tagResult) {
                    $insertStmt = $pdo->prepare("INSERT INTO sender_tags (from_email, tag, disabled) VALUES (?, 'unclassified', 0)");
                    $insertStmt->execute([$email]);
                    $tagResult = ['tag' => 'unclassified', 'disabled' => 0, 'removed_at' => null];
                } elseif ($tagResult['removed_at'] && $emailCol) {
                    $dateCond = [];
                    if ($hasDateReceived) $dateCond[] = "date_received > ?";
                    if ($hasCreatedAt) $dateCond[] = "created_at > ?";
                    
                    if (!empty($dateCond)) {
                        $dateWhere = '(' . implode(' OR ', $dateCond) . ')';
                        $newerStmt = $pdo->prepare("
                            SELECT 1 FROM `$emailTableName`
                            WHERE `$emailCol` = ? AND $dateWhere
                            LIMIT 1
                        ");
                        $removedAt = $tagResult['removed_at'];
                        $params = [$email];
                        if ($hasDateReceived) $params[] = $removedAt;
                        if ($hasCreatedAt) $params[] = $removedAt;
                        $newerStmt->execute($params);
                        
                        if ($newerStmt->fetch()) {
                            $reactivateStmt = $pdo->prepare("UPDATE sender_tags SET removed_at = NULL WHERE from_email = ?");
                            $reactivateStmt->execute([$email]);
                            $tagResult['removed_at'] = null;
                        }
                    }
                }
                
                if (empty($tagResult['removed_at'])) {
                    $senderTags[] = [
                        'email' => $email,
                        'name' => $sender['name'],
                        'tag' => $tagResult['tag'],
                        'disabled' => (bool)$tagResult['disabled']
                    ];
                }
            }
        }
    } catch (PDOException $e) {}
    
    $lexConfig = getLexConfig();

    $calendarConfig = getCalendarConfig();

    // Calendar event stats
    $calendarStats = ['total' => 0, 'upcoming' => 0, 'sources' => []];
    try {
        $calendarStats['total'] = (int)$pdo->query("SELECT COUNT(*) FROM calendar_events")->fetchColumn();
        $calendarStats['upcoming'] = (int)$pdo->query("SELECT COUNT(*) FROM calendar_events WHERE event_date >= CURDATE()")->fetchColumn();
        $srcStmt = $pdo->query("SELECT source, COUNT(*) as cnt FROM calendar_events GROUP BY source");
        foreach ($srcStmt->fetchAll() as $r) {
            $calendarStats['sources'][$r['source']] = (int)$r['cnt'];
        }
    } catch (PDOException $e) {}

    $magnituConfig = getAllMagnituConfig($pdo);
    $magnituScoreStats = ['total' => 0, 'magnitu' => 0, 'recipe' => 0];
    try {
        $magnituScoreStats['total'] = (int)$pdo->query("SELECT COUNT(*) FROM entry_scores")->fetchColumn();
        $magnituScoreStats['magnitu'] = (int)$pdo->query("SELECT COUNT(*) FROM entry_scores WHERE score_source = 'magnitu'")->fetchColumn();
        $magnituScoreStats['recipe'] = (int)$pdo->query("SELECT COUNT(*) FROM entry_scores WHERE score_source = 'recipe'")->fetchColumn();
    } catch (PDOException $e) {}

    $emailScoreStats = ['total' => 0, 'scored' => 0, 'unscored' => 0, 'coverage_pct' => 0];
    try {
        $emailTableName = getEmailTableName($pdo);
        $emailScoreStats['total'] = (int)$pdo->query("SELECT COUNT(*) FROM `$emailTableName`")->fetchColumn();
        $emailScoreStats['scored'] = (int)$pdo->query("SELECT COUNT(*) FROM entry_scores WHERE entry_type = 'email'")->fetchColumn();
        $emailScoreStats['unscored'] = max(0, $emailScoreStats['total'] - $emailScoreStats['scored']);
        if ($emailScoreStats['total'] > 0) {
            $emailScoreStats['coverage_pct'] = (int)round(($emailScoreStats['scored'] / $emailScoreStats['total']) * 100);
        }
    } catch (PDOException $e) {}
    
    $scraperConfigs = [];
    try {
        $scraperConfigs = $pdo->query("SELECT * FROM scraper_configs ORDER BY created_at DESC")->fetchAll();
    } catch (PDOException $e) {}
    
    $mailConfig = [
        'imap_mailbox'    => getMagnituConfig($pdo, 'mail_imap_mailbox') ?: '',
        'imap_username'   => getMagnituConfig($pdo, 'mail_imap_username') ?: '',
        'imap_password'   => getMagnituConfig($pdo, 'mail_imap_password') ?: '',
        'max_messages'    => getMagnituConfig($pdo, 'mail_max_messages') ?: '50',
        'search_criteria' => getMagnituConfig($pdo, 'mail_search_criteria') ?: 'UNSEEN',
        'mark_seen'       => getMagnituConfig($pdo, 'mail_mark_seen') ?? '1',
        'db_table'        => getMagnituConfig($pdo, 'mail_db_table') ?: 'fetched_emails',
    ];
    $mailConfigured = !empty($mailConfig['imap_username']) && !empty($mailConfig['imap_password']);
    
    // Tripped feeds (circuit breaker: 3+ consecutive failures)
    $trippedFeeds = [];
    try {
        $trippedFeeds = $pdo->query("SELECT id, title, url, consecutive_failures, last_error, last_error_at FROM feeds WHERE consecutive_failures >= 3 AND (source_type IS NULL OR source_type != 'scraper') ORDER BY last_error_at DESC")->fetchAll();
    } catch (PDOException $e) {}

    // Tripped lex/jus sources
    $trippedLexSources = [];
    foreach (['eu', 'ch', 'de', 'ch_bger', 'ch_bge', 'ch_bvger'] as $src) {
        $failKey = 'lex_' . $src . '_failures';
        $val = getMagnituConfig($pdo, $failKey);
        if ($val !== null && (int)$val >= 3) {
            $trippedLexSources[] = $src;
        }
    }

    // Tripped calendar sources
    $trippedCalendarSources = [];
    foreach (array_keys($calendarConfig) as $src) {
        $failKey = 'calendar_' . $src . '_failures';
        $val = getMagnituConfig($pdo, $failKey);
        if ($val !== null && (int)$val >= 3) {
            $trippedCalendarSources[] = $src;
        }
    }

    if ($settingsTab === 'diagnostics') {
        $fdT0 = microtime(true);
        $feedDiagnosticsData = runFeedDiagnostics($pdo);
        $fdElapsed = microtime(true) - $fdT0;
        $feedDiagnosticsData['_elapsed'] = $fdElapsed;
        $feedDiagnosticsData['_report'] = formatFeedDiagnosticsReport($feedDiagnosticsData, $fdElapsed);
        header('X-Robots-Tag: noindex, nofollow');
    }

    // Satellites registry (mothership-only; Settings → Satellites tab uses these).
    $satellitesRegistry = isSatellite() ? [] : getSatellitesRegistry($pdo);
    $satellitesMothershipUrl = detectMothershipUrl();
    $satellitesMothershipDb = detectMothershipDbName($pdo);
    $satellitesRemoteRefreshKeyConfigured = (getRemoteRefreshKey() !== '');
    $satellitesSuggestedRefreshKey = getMagnituConfig($pdo, 'satellites_suggested_refresh_key') ?: '';
    $satellitesHighlightSlug = isset($_GET['highlight']) ? (string)$_GET['highlight'] : '';

    include 'views/settings.php';
}

// ---------------------------------------------------------------------------
// Static pages
// ---------------------------------------------------------------------------

function handleAboutPage($pdo) {
    $stats = [];
    try {
        $stats['feeds'] = $pdo->query("SELECT COUNT(*) FROM feeds WHERE source_type = 'rss' OR source_type IS NULL")->fetchColumn();
        $stats['feed_items'] = $pdo->query("SELECT COUNT(*) FROM feed_items")->fetchColumn();
        
        $emailTable = getEmailTableName($pdo);
        $stats['emails'] = $pdo->query("SELECT COUNT(*) FROM `$emailTable`")->fetchColumn();
        
        $stats['lex_eu'] = $pdo->query("SELECT COUNT(*) FROM lex_items WHERE source = 'eu'")->fetchColumn();
        $stats['lex_ch'] = $pdo->query("SELECT COUNT(*) FROM lex_items WHERE source = 'ch'")->fetchColumn();
        $stats['lex_de'] = $pdo->query("SELECT COUNT(*) FROM lex_items WHERE source = 'de'")->fetchColumn();
        $stats['lex_fr'] = $pdo->query("SELECT COUNT(*) FROM lex_items WHERE source = 'fr'")->fetchColumn();
        $stats['lex_parl_mm'] = $pdo->query("SELECT COUNT(*) FROM lex_items WHERE source = 'parl_mm'")->fetchColumn();
        $stats['jus_bger'] = $pdo->query("SELECT COUNT(*) FROM lex_items WHERE source = 'ch_bger'")->fetchColumn();
        $stats['jus_bge'] = $pdo->query("SELECT COUNT(*) FROM lex_items WHERE source = 'ch_bge'")->fetchColumn();
        $stats['jus_bvger'] = $pdo->query("SELECT COUNT(*) FROM lex_items WHERE source = 'ch_bvger'")->fetchColumn();
        $stats['scraper_configs'] = $pdo->query("SELECT COUNT(*) FROM scraper_configs")->fetchColumn();
        $stats['scraper_items'] = $pdo->query("SELECT COUNT(*) FROM feed_items fi JOIN feeds f ON fi.feed_id = f.id WHERE f.source_type = 'scraper'")->fetchColumn();
        $stats['calendar'] = $pdo->query("SELECT COUNT(*) FROM calendar_events")->fetchColumn();
    } catch (PDOException $e) {}
    $lastChangeDate = date('d.m.Y', filemtime(__DIR__ . '/../index.php'));
    include 'views/about.php';
}

function handleBetaPage() {
    // Beta is retired — the AI View Generator now lives in Settings > LLM.
    // Redirect any old bookmarks / links straight there.
    header('Location: ' . getBasePath() . '/index.php?action=settings&tab=llm');
    exit;
}

function handleStyleguidePage() {
    $lastChangeDate = date('d.m.Y', filemtime(__DIR__ . '/../index.php'));
    include 'views/styleguide.php';
}
