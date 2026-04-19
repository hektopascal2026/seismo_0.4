<?php
/**
 * Dashboard Controller
 *
 * Renders the main index page: aggregates items from all sources
 * (RSS, Substack, Email, Lex, Scraper), applies tag filters,
 * handles search, merges Magnitu scores, and sorts the timeline.
 */

function handleToggleFavourite($pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ?action=index');
        exit;
    }

    $entryType = trim((string)($_POST['entry_type'] ?? ''));
    $entryId = (int)($_POST['entry_id'] ?? 0);
    $allowedEntryTypes = ['feed_item', 'email', 'lex_item', 'calendar_event'];

    if (!in_array($entryType, $allowedEntryTypes, true) || $entryId <= 0) {
        $_SESSION['error'] = 'Invalid favourite request.';
    } else {
        toggleEntryFavourite($pdo, $entryType, $entryId);
    }

    $returnQuery = trim((string)($_POST['return_query'] ?? ''));
    $queryParams = [];
    if ($returnQuery !== '') {
        parse_str(ltrim($returnQuery, '?'), $queryParams);
    }
    $queryParams['action'] = 'index';
    unset($queryParams['entry_type'], $queryParams['entry_id']);

    $redirectUrl = '?' . http_build_query($queryParams);
    header('Location: ' . $redirectUrl);
    exit;
}

/**
 * Build the same timeline and filter state as the main Feed for the current request (GET).
 * Used by handleDashboard and by the Magnitu page so both stay in sync when q / tag filters apply.
 *
 * @param int|null $timelineItemCap After merge and date sort, keep at most this many rows (default: 30, or 200 when searching). Pass a higher cap for Magnitu so a 7-day window is not empty.
 * @return array<string, mixed>
 */
function buildDashboardIndexData(PDO $pdo, ?int $timelineItemCap = null): array {
    $searchQuery = trim($_GET['q'] ?? '');
    $currentView = (isset($_GET['view']) && $_GET['view'] === 'favourites') ? 'favourites' : 'newest';

    // Wider per-source windows when building a deep timeline (Magnitu): default Feed stays at 30.
    $magnituWidePool = ($timelineItemCap !== null);
    $srcFeedLimit = $magnituWidePool ? 150 : 30;
    $lexSqlLimit = $magnituWidePool ? 400 : 100;
    $lexSliceLimit = $magnituWidePool ? 120 : 30;
    $calendarFetchLimit = $magnituWidePool ? 60 : 15;
    $searchIndexLimit = $magnituWidePool ? 400 : 100;

    $tagsStmt = $pdo->query("
        SELECT DISTINCT f.category
        FROM feeds f
        WHERE f.category IS NOT NULL
          AND f.category != ''
          AND f.disabled = 0
          AND (f.source_type = 'rss' OR f.source_type IS NULL)
          AND NOT EXISTS (
              SELECT 1
              FROM scraper_configs sc
              WHERE sc.url = f.url
          )
        ORDER BY f.category
    ");
    $tags = $tagsStmt->fetchAll(PDO::FETCH_COLUMN);
    
    $emailTags = esMailFilterTags($pdo);
    
    $substackTagsStmt = $pdo->query("SELECT DISTINCT category FROM feeds WHERE source_type = 'substack' AND disabled = 0 AND category IS NOT NULL AND category != '' ORDER BY category");
    $substackTags = $substackTagsStmt->fetchAll(PDO::FETCH_COLUMN);

    $tagsSubmitted = isset($_GET['tags_submitted']);
    if ($tagsSubmitted) {
        $selectedTags = isset($_GET['tags']) ? array_values(array_filter((array)$_GET['tags'], 'strlen')) : [];
        $selectedEmailTags = isset($_GET['email_tags']) ? array_values(array_filter((array)$_GET['email_tags'], 'strlen')) : [];
        $selectedSubstackTags = isset($_GET['substack_tags']) ? array_values(array_filter((array)$_GET['substack_tags'], 'strlen')) : [];
        $selectedLexSources = isset($_GET['lex_sources']) ? array_values(array_filter((array)$_GET['lex_sources'], 'strlen')) : [];
    } else {
        $selectedTags = array_values(array_filter($tags, function($t) { return $t !== 'unsortiert'; }));
        $selectedEmailTags = array_values(array_filter($emailTags, function($t) { return $t !== 'unsortiert' && $t !== 'unclassified'; }));
        $selectedSubstackTags = $substackTags;
        $lexCfg = getLexConfig();
        $selectedLexSources = array_values(array_filter(
            ['eu', 'ch', 'de', 'fr', 'ch_bger', 'ch_bge', 'ch_bvger', 'parl_mm'],
            function($s) use ($lexCfg) { return !empty($lexCfg[$s]['enabled']); }
        ));
    }
    
    $lexCfg = $lexCfg ?? getLexConfig();
    $enabledLexSources = [];
    foreach (['eu', 'ch', 'de', 'fr', 'ch_bger', 'ch_bge', 'ch_bvger', 'parl_mm'] as $s) {
        if (!empty($lexCfg[$s]['enabled'])) $enabledLexSources[] = $s;
    }
    $selectedLexSources = array_values(array_intersect($selectedLexSources, $enabledLexSources));
    
    if (!empty($searchQuery)) {
        $latestItems = searchFeedItems($pdo, $searchQuery, $searchIndexLimit, $selectedTags);
        $searchEmails = searchEmails($pdo, $searchQuery, $searchIndexLimit, $selectedEmailTags);
        $searchResultsCount = count($latestItems) + count($searchEmails);
    } else {
        if (!empty($selectedTags)) {
            $placeholders = implode(',', array_fill(0, count($selectedTags), '?'));
            $sql = "
                SELECT fi.*, f.title as feed_title, f.category as feed_category 
                FROM feed_items fi
                JOIN feeds f ON fi.feed_id = f.id
                WHERE f.disabled = 0
                  AND (f.source_type = 'rss' OR f.source_type IS NULL)
                  AND NOT EXISTS (
                      SELECT 1
                      FROM scraper_configs sc
                      WHERE sc.url = f.url
                  )
                  AND f.category IN ($placeholders)
                ORDER BY fi.published_date DESC, fi.cached_at DESC
                LIMIT " . (int)$srcFeedLimit . "
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($selectedTags);
            $latestItems = $stmt->fetchAll();
        } elseif (!$tagsSubmitted) {
            $latestItemsStmt = $pdo->query("
                SELECT fi.*, f.title as feed_title, f.category as feed_category 
                FROM feed_items fi
                JOIN feeds f ON fi.feed_id = f.id
                WHERE f.disabled = 0
                  AND (f.source_type = 'rss' OR f.source_type IS NULL)
                  AND NOT EXISTS (
                      SELECT 1
                      FROM scraper_configs sc
                      WHERE sc.url = f.url
                  )
                ORDER BY fi.published_date DESC, fi.cached_at DESC
                LIMIT " . (int)$srcFeedLimit . "
            ");
            $latestItems = $latestItemsStmt->fetchAll();
        } else {
            $latestItems = [];
        }
        $searchResultsCount = null;
    }
    
    if (!empty($searchQuery)) {
        $emails = $searchEmails;
    } else {
        if (!empty($selectedEmailTags)) {
            $emails = getEmailsForIndex($pdo, $srcFeedLimit, $selectedEmailTags);
        } elseif (!$tagsSubmitted) {
            $emails = getEmailsForIndex($pdo, $srcFeedLimit, []);
        } else {
            $emails = [];
        }
    }
    
    if (!empty($selectedSubstackTags)) {
        $placeholders = implode(',', array_fill(0, count($selectedSubstackTags), '?'));
        $substackItemsStmt = $pdo->prepare("
            SELECT fi.*, f.title as feed_title, f.category as feed_category
            FROM feed_items fi
            JOIN feeds f ON fi.feed_id = f.id
            WHERE f.source_type = 'substack' AND f.disabled = 0
              AND f.category IN ($placeholders)
            ORDER BY fi.published_date DESC, fi.cached_at DESC
            LIMIT " . (int)$srcFeedLimit . "
        ");
        $substackItemsStmt->execute($selectedSubstackTags);
        $substackItems = $substackItemsStmt->fetchAll();
    } elseif (!$tagsSubmitted) {
        $substackItemsStmt = $pdo->query("
            SELECT fi.*, f.title as feed_title, f.category as feed_category
            FROM feed_items fi
            JOIN feeds f ON fi.feed_id = f.id
            WHERE f.source_type = 'substack' AND f.disabled = 0
            ORDER BY fi.published_date DESC, fi.cached_at DESC
            LIMIT " . (int)$srcFeedLimit . "
        ");
        $substackItems = $substackItemsStmt->fetchAll();
    } else {
        $substackItems = [];
    }
    
    // Scraper items
    $scraperItemsForFeed = [];
    $scraperFeedsForIndex = [];
    $selectedScraperPills = [];
    try {
        $allScraperFeedsIdx = $pdo->query("
            SELECT f.id, f.title AS name
            FROM feeds f
            WHERE (f.source_type = 'scraper' OR f.category = 'scraper')
              AND EXISTS (
                  SELECT 1
                  FROM scraper_configs sc
                  WHERE sc.disabled = 0
                    AND (sc.url = f.url OR sc.name = f.title)
              )
            ORDER BY f.title
        ")->fetchAll();
        $scraperNameToIds = [];
        foreach ($allScraperFeedsIdx as $sf) {
            $n = $sf['name'];
            if (!isset($scraperNameToIds[$n])) {
                $scraperNameToIds[$n] = [];
                $scraperFeedsForIndex[] = ['id' => $sf['id'], 'name' => $n];
            }
            $scraperNameToIds[$n][] = $sf['id'];
        }
        
        if ($tagsSubmitted) {
            $selectedScraperPills = isset($_GET['scraper_sources']) ? array_map('intval', (array)$_GET['scraper_sources']) : [];
        } else {
            $selectedScraperPills = array_column($scraperFeedsForIndex, 'id');
        }
        $activeScraperFeedIds = [];
        foreach ($scraperFeedsForIndex as $src) {
            if (in_array($src['id'], $selectedScraperPills)) {
                $activeScraperFeedIds = array_merge($activeScraperFeedIds, $scraperNameToIds[$src['name']]);
            }
        }
        $activeScraperFeedIds = array_values(array_unique($activeScraperFeedIds));
        
        if (!empty($activeScraperFeedIds)) {
            $ph = implode(',', array_fill(0, count($activeScraperFeedIds), '?'));
            $scraperStmt = $pdo->prepare("
                SELECT fi.*, f.title as feed_name, f.url as source_url
                FROM feed_items fi
                JOIN feeds f ON fi.feed_id = f.id
                WHERE f.id IN ($ph) AND fi.hidden = 0
                ORDER BY fi.published_date DESC
                LIMIT " . (int)$srcFeedLimit . "
            ");
            $scraperStmt->execute($activeScraperFeedIds);
            $scraperItemsForFeed = $scraperStmt->fetchAll();
        }
    } catch (PDOException $e) {}
    
    // Lex items
    $lexItems = [];
    try {
        if (!empty($selectedLexSources)) {
            $lexPlaceholders = implode(',', array_fill(0, count($selectedLexSources), '?'));
            $lexStmt = $pdo->prepare("
                SELECT * FROM lex_items
                WHERE source IN ($lexPlaceholders)
                ORDER BY document_date DESC
                LIMIT " . (int)$lexSqlLimit . "
            ");
            $lexStmt->execute($selectedLexSources);
            $lexItems = array_slice(filterJusBannedWords($lexStmt->fetchAll()), 0, $lexSliceLimit);
        } elseif (!$tagsSubmitted) {
            $lexStmt = $pdo->query("
                SELECT * FROM lex_items
                ORDER BY document_date DESC
                LIMIT " . (int)$lexSqlLimit . "
            ");
            $lexItems = array_slice(filterJusBannedWords($lexStmt->fetchAll()), 0, $lexSliceLimit);
        }
    } catch (PDOException $e) {
        $lexItems = [];
    }
    
    // Magnitu scores
    $scoreMap = [];
    try {
        $scoreStmt = $pdo->query("SELECT entry_type, entry_id, relevance_score, predicted_label, explanation, score_source FROM entry_scores");
        foreach ($scoreStmt->fetchAll() as $s) {
            $scoreMap[$s['entry_type'] . ':' . $s['entry_id']] = $s;
        }
    } catch (PDOException $e) {}

    // Favourites map
    $favouritesMap = [];
    try {
        $favouritesMap = getEntryFavouritesMap($pdo);
    } catch (PDOException $e) {}
    
    $hasMagnituScores = !empty($scoreMap);
    
    // Calendar events (upcoming, shown on dashboard when calendar sources are enabled)
    $calendarCfg = getCalendarConfig();
    $calendarEnabled = false;
    foreach ($calendarCfg as $src) {
        if (!empty($src['enabled'])) { $calendarEnabled = true; break; }
    }
    $selectedCalendar = $tagsSubmitted ? isset($_GET['calendar_enabled']) : $calendarEnabled;

    // Merge all items into unified timeline
    $allItems = [];

    if ($currentView === 'favourites') {
        $favouriteRows = [];
        try {
            $favouriteRows = $pdo->query("SELECT entry_type, entry_id FROM entry_favourites")->fetchAll();
        } catch (PDOException $e) {}

        $favouriteIdsByType = [
            'feed_item' => [],
            'email' => [],
            'lex_item' => [],
            'calendar_event' => [],
        ];
        foreach ($favouriteRows as $row) {
            $type = $row['entry_type'] ?? '';
            $id = (int)($row['entry_id'] ?? 0);
            if ($id > 0 && isset($favouriteIdsByType[$type])) {
                $favouriteIdsByType[$type][] = $id;
            }
        }
        foreach ($favouriteIdsByType as $type => $ids) {
            $favouriteIdsByType[$type] = array_values(array_unique($ids));
        }

        if (!empty($favouriteIdsByType['feed_item'])) {
            $ph = implode(',', array_fill(0, count($favouriteIdsByType['feed_item']), '?'));
            $feedStmt = $pdo->prepare("
                SELECT fi.*, f.title AS feed_title, f.category AS feed_category, f.source_type, f.url AS source_url
                FROM feed_items fi
                JOIN feeds f ON fi.feed_id = f.id
                WHERE fi.id IN ($ph) AND fi.hidden = 0
            ");
            $feedStmt->execute($favouriteIdsByType['feed_item']);
            foreach ($feedStmt->fetchAll() as $item) {
                $entryKey = 'feed_item:' . $item['id'];
                $sourceType = $item['source_type'] ?? '';
                $wrapperType = 'feed';
                if ($sourceType === 'substack') {
                    $wrapperType = 'substack';
                } elseif ($sourceType === 'scraper' || ($item['feed_category'] ?? '') === 'scraper') {
                    $wrapperType = 'scraper';
                }
                $dateValue = $item['published_date'] ?? $item['cached_at'] ?? null;
                $allItems[] = [
                    'type' => $wrapperType,
                    'date' => $dateValue ? strtotime($dateValue) : 0,
                    'data' => $item,
                    'score' => $scoreMap[$entryKey] ?? null,
                    'entry_type' => 'feed_item',
                    'entry_id' => (int)$item['id'],
                    'is_favourite' => true,
                ];
            }
        }

        if (!empty($favouriteIdsByType['email'])) {
            $emailTable = getEmailTableName($pdo);
            $ph = implode(',', array_fill(0, count($favouriteIdsByType['email']), '?'));
            $emailStmt = $pdo->prepare("SELECT * FROM `{$emailTable}` WHERE id IN ($ph)");
            $emailStmt->execute($favouriteIdsByType['email']);
            foreach ($emailStmt->fetchAll() as $email) {
                $entryKey = 'email:' . $email['id'];
                $dateValue = $email['date_received'] ?? $email['date_utc'] ?? $email['created_at'] ?? $email['date_sent'] ?? null;
                $allItems[] = [
                    'type' => 'email',
                    'date' => $dateValue ? strtotime($dateValue) : 0,
                    'data' => $email,
                    'score' => $scoreMap[$entryKey] ?? null,
                    'entry_type' => 'email',
                    'entry_id' => (int)$email['id'],
                    'is_favourite' => true,
                ];
            }
        }

        if (!empty($favouriteIdsByType['lex_item'])) {
            $ph = implode(',', array_fill(0, count($favouriteIdsByType['lex_item']), '?'));
            $lexStmt = $pdo->prepare("SELECT * FROM lex_items WHERE id IN ($ph)");
            $lexStmt->execute($favouriteIdsByType['lex_item']);
            foreach ($lexStmt->fetchAll() as $lexItem) {
                $entryKey = 'lex_item:' . $lexItem['id'];
                $dateValue = $lexItem['document_date'] ?? $lexItem['created_at'] ?? null;
                $allItems[] = [
                    'type' => 'lex',
                    'date' => $dateValue ? strtotime($dateValue) : 0,
                    'data' => $lexItem,
                    'score' => $scoreMap[$entryKey] ?? null,
                    'entry_type' => 'lex_item',
                    'entry_id' => (int)$lexItem['id'],
                    'is_favourite' => true,
                ];
            }
        }

        if (!empty($favouriteIdsByType['calendar_event'])) {
            $ph = implode(',', array_fill(0, count($favouriteIdsByType['calendar_event']), '?'));
            $calStmt = $pdo->prepare("SELECT * FROM calendar_events WHERE id IN ($ph)");
            $calStmt->execute($favouriteIdsByType['calendar_event']);
            foreach ($calStmt->fetchAll() as $calEvent) {
                $entryKey = 'calendar_event:' . $calEvent['id'];
                $dateValue = $calEvent['event_date'] ?? $calEvent['created_at'] ?? null;
                $allItems[] = [
                    'type' => 'calendar',
                    'date' => $dateValue ? strtotime($dateValue) : 0,
                    'data' => $calEvent,
                    'score' => $scoreMap[$entryKey] ?? null,
                    'entry_type' => 'calendar_event',
                    'entry_id' => (int)$calEvent['id'],
                    'is_favourite' => true,
                ];
            }
        }
    } else {
        foreach ($latestItems as $item) {
            $dateValue = $item['published_date'] ?? $item['cached_at'] ?? null;
            $entryKey = 'feed_item:' . $item['id'];
            $allItems[] = [
                'type' => 'feed',
                'date' => $dateValue ? strtotime($dateValue) : 0,
                'data' => $item,
                'score' => $scoreMap[$entryKey] ?? null,
                'entry_type' => 'feed_item',
                'entry_id' => (int)$item['id'],
                'is_favourite' => !empty($favouritesMap[$entryKey]),
            ];
        }
        
        foreach ($substackItems as $item) {
            $dateValue = $item['published_date'] ?? $item['cached_at'] ?? null;
            $entryKey = 'feed_item:' . $item['id'];
            $allItems[] = [
                'type' => 'substack',
                'date' => $dateValue ? strtotime($dateValue) : 0,
                'data' => $item,
                'score' => $scoreMap[$entryKey] ?? null,
                'entry_type' => 'feed_item',
                'entry_id' => (int)$item['id'],
                'is_favourite' => !empty($favouritesMap[$entryKey]),
            ];
        }
        
        foreach ($emails as $email) {
            $dateValue = $email['date_received'] ?? $email['date_utc'] ?? $email['created_at'] ?? $email['date_sent'] ?? null;
            $entryKey = 'email:' . $email['id'];
            $allItems[] = [
                'type' => 'email',
                'date' => $dateValue ? strtotime($dateValue) : 0,
                'data' => $email,
                'score' => $scoreMap[$entryKey] ?? null,
                'entry_type' => 'email',
                'entry_id' => (int)$email['id'],
                'is_favourite' => !empty($favouritesMap[$entryKey]),
            ];
        }
        
        foreach ($lexItems as $lexItem) {
            $dateValue = $lexItem['document_date'] ?? $lexItem['created_at'] ?? null;
            $entryKey = 'lex_item:' . $lexItem['id'];
            $allItems[] = [
                'type' => 'lex',
                'date' => $dateValue ? strtotime($dateValue) : 0,
                'data' => $lexItem,
                'score' => $scoreMap[$entryKey] ?? null,
                'entry_type' => 'lex_item',
                'entry_id' => (int)$lexItem['id'],
                'is_favourite' => !empty($favouritesMap[$entryKey]),
            ];
        }
        
        foreach ($scraperItemsForFeed as $item) {
            $dateValue = $item['published_date'] ?? $item['cached_at'] ?? null;
            $entryKey = 'feed_item:' . $item['id'];
            $allItems[] = [
                'type' => 'scraper',
                'date' => $dateValue ? strtotime($dateValue) : 0,
                'data' => $item,
                'score' => $scoreMap[$entryKey] ?? null,
                'entry_type' => 'feed_item',
                'entry_id' => (int)$item['id'],
                'is_favourite' => !empty($favouritesMap[$entryKey]),
            ];
        }

        $calendarEventsForIndex = [];
        if ($selectedCalendar && $calendarEnabled) {
            try {
                $calStmt = $pdo->query("
                    SELECT * FROM calendar_events
                    WHERE event_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) OR event_date IS NULL
                    ORDER BY event_date DESC
                    LIMIT " . (int)$calendarFetchLimit . "
                ");
                $calendarEventsForIndex = $calStmt->fetchAll();
            } catch (PDOException $e) {}
        }

        foreach ($calendarEventsForIndex as $calEvent) {
            $dateValue = $calEvent['event_date'] ?? $calEvent['created_at'] ?? null;
            $entryKey = 'calendar_event:' . $calEvent['id'];
            $allItems[] = [
                'type' => 'calendar',
                'date' => $dateValue ? strtotime($dateValue) : 0,
                'data' => $calEvent,
                'score' => $scoreMap[$entryKey] ?? null,
                'entry_type' => 'calendar_event',
                'entry_id' => (int)$calEvent['id'],
                'is_favourite' => !empty($favouritesMap[$entryKey]),
            ];
        }
    }

    usort($allItems, function($a, $b) {
        return $b['date'] - $a['date'];
    });

    if ($timelineItemCap !== null) {
        $limit = max(1, min((int)$timelineItemCap, 2000));
    } else {
        $limit = !empty($searchQuery) ? 200 : 30;
    }
    $allItems = array_slice($allItems, 0, $limit);
    
    $scoredCount = count(array_filter($allItems, function($i) { return $i['score'] !== null; }));
    $totalScored = count($scoreMap);
    
    $lastRefreshStmt = $pdo->query("SELECT MAX(last_fetched) as last_refresh FROM feeds WHERE last_fetched IS NOT NULL");
    $lastRefreshResult = $lastRefreshStmt->fetch();
    $lastRefreshDate = null;
    if ($lastRefreshResult && $lastRefreshResult['last_refresh']) {
        $lastRefreshDate = date('d.m.Y H:i', strtotime($lastRefreshResult['last_refresh']));
    }
    
    $lastChangeDate = date('d.m.Y', filemtime(__DIR__ . '/../index.php'));

    return [
        'searchQuery' => $searchQuery,
        'currentView' => $currentView,
        'tags' => $tags,
        'emailTags' => $emailTags,
        'substackTags' => $substackTags,
        'tagsSubmitted' => $tagsSubmitted,
        'selectedTags' => $selectedTags,
        'selectedEmailTags' => $selectedEmailTags,
        'selectedSubstackTags' => $selectedSubstackTags,
        'selectedLexSources' => $selectedLexSources,
        'enabledLexSources' => $enabledLexSources,
        'selectedScraperPills' => $selectedScraperPills,
        'scraperFeedsForIndex' => $scraperFeedsForIndex,
        'calendarEnabled' => $calendarEnabled,
        'selectedCalendar' => $selectedCalendar,
        'allItems' => $allItems,
        'searchResultsCount' => $searchResultsCount,
        'hasMagnituScores' => $hasMagnituScores,
        'scoredCount' => $scoredCount,
        'totalScored' => $totalScored,
        'lastRefreshDate' => $lastRefreshDate,
        'lastChangeDate' => $lastChangeDate,
    ];
}

function handleDashboard($pdo) {
    if (isSatellite()) {
        // Satellite timelines default to Magnitu's high-signal labels only
        // (investigation_lead + important) — the entire purpose of a satellite
        // is to surface what its topic profile considers relevant.
        $data = buildDashboardIndexData($pdo, 800);
        $data['allItems'] = array_values(array_filter($data['allItems'], function ($row) {
            $sc = $row['score'] ?? null;
            if (!is_array($sc)) {
                return false;
            }
            $label = $sc['predicted_label'] ?? '';
            return $label === 'investigation_lead' || $label === 'important';
        }));
        extract($data, EXTR_SKIP);
        include 'views/index.php';
        return;
    }
    extract(buildDashboardIndexData($pdo), EXTR_SKIP);
    include 'views/index.php';
}

/**
 * German day label for Magnitu date separators (Heute, Gestern, …).
 */
function seismo_magnitu_day_heading(int $unixTs): string {
    if ($unixTs <= 0) {
        return '';
    }
    $todayStart = strtotime('today');
    $itemDayStart = strtotime(date('Y-m-d', $unixTs) . ' 00:00:00');
    $diffDays = (int)(($todayStart - $itemDayStart) / 86400);
    if ($diffDays === 0) {
        return 'Heute';
    }
    if ($diffDays === 1) {
        return 'Gestern';
    }
    if ($diffDays === 2) {
        return 'Vorgestern';
    }
    if ($diffDays >= 3 && $diffDays <= 6) {
        return 'Heute -' . $diffDays;
    }
    return date('d.m.Y', $unixTs);
}

// ---------------------------------------------------------------------------
// Global Refresh (orchestrates all controllers)
// ---------------------------------------------------------------------------

/**
 * Check/increment a circuit breaker stored in magnitu_config.
 * Returns true if the source is tripped (>= $threshold consecutive failures).
 */
function isSourceTripped($pdo, $key, $threshold = 3) {
    $val = getMagnituConfig($pdo, $key);
    return $val !== null && (int)$val >= $threshold;
}

function recordSourceFailure($pdo, $key) {
    $current = (int)(getMagnituConfig($pdo, $key) ?? 0);
    setMagnituConfig($pdo, $key, (string)($current + 1));
}

function resetSourceFailure($pdo, $key) {
    setMagnituConfig($pdo, $key, '0');
}

/**
 * Runs the full refresh pipeline (feeds, emails, lex/jus, calendar, Magnitu rescore)
 * without any session/redirect side effects. Shared by the interactive
 * handleRefreshAll (web UI) and handleRefreshAllRemote (satellite → mothership).
 *
 * @return array{0: string[], 1: bool} [messages, hasErrors]
 */
function refreshAllSources(PDO $pdo): array {
    $results = [];
    $hasErrors = false;

    try {
        [$refreshed, $skipped, $feedFailed, $failedNames] = refreshAllFeeds($pdo);
        $msg = "{$refreshed} feeds refreshed";
        if ($skipped > 0) $msg .= " ({$skipped} tripped)";
        if ($feedFailed > 0) { $msg .= " ({$feedFailed} failed)"; $hasErrors = true; }
        $results[] = $msg;
        if (!empty($failedNames)) {
            $results[] = 'Failed: ' . implode(', ', $failedNames);
        }
    } catch (\Exception $e) {
        $results[] = 'Feeds: ' . $e->getMessage();
        $hasErrors = true;
    }

    try {
        refreshEmails($pdo);
        $results[] = 'Emails refreshed';
    } catch (\Exception $e) {
        $results[] = 'Emails: ' . $e->getMessage();
        $hasErrors = true;
    }

    $lexCfg = getLexConfig();

    $lexSources = [
        ['key' => 'eu',      'enabled' => $lexCfg['eu']['enabled'] ?? true,  'emoji' => '🇪🇺', 'label' => 'EU',    'fn' => function($pdo) { return refreshLexItems($pdo); }],
        ['key' => 'ch',      'enabled' => $lexCfg['ch']['enabled'] ?? true,  'emoji' => '🇨🇭', 'label' => 'CH',    'fn' => function($pdo) { return refreshFedlexItems($pdo); }],
        ['key' => 'de',      'enabled' => $lexCfg['de']['enabled'] ?? true,  'emoji' => '🇩🇪', 'label' => 'DE',    'fn' => function($pdo) { return refreshRechtBundItems($pdo); }],
        ['key' => 'ch_bger', 'enabled' => $lexCfg['ch_bger']['enabled'] ?? false, 'emoji' => '⚖️', 'label' => 'BGer',  'fn' => function($pdo) { return refreshJusItems($pdo, 'CH_BGer'); }],
        ['key' => 'ch_bge',  'enabled' => $lexCfg['ch_bge']['enabled'] ?? false,  'emoji' => '⚖️', 'label' => 'BGE',   'fn' => function($pdo) { return refreshJusItems($pdo, 'CH_BGE'); }],
        ['key' => 'ch_bvger','enabled' => $lexCfg['ch_bvger']['enabled'] ?? false, 'emoji' => '⚖️', 'label' => 'BVGer', 'fn' => function($pdo) { return refreshJusItems($pdo, 'CH_BVGer'); }],
        ['key' => 'parl_mm', 'enabled' => $lexCfg['parl_mm']['enabled'] ?? false, 'emoji' => '🏛', 'label' => 'Parl MM', 'fn' => function($pdo) { return refreshParlMmItems($pdo); }],
        ['key' => 'fr',      'enabled' => $lexCfg['fr']['enabled'] ?? false,      'emoji' => '🇫🇷', 'label' => 'FR',      'fn' => function($pdo) { return refreshLegifranceItems($pdo); }],
    ];

    foreach ($lexSources as $src) {
        if (!$src['enabled']) continue;
        $failKey = 'lex_' . $src['key'] . '_failures';
        if (isSourceTripped($pdo, $failKey)) {
            $results[] = $src['emoji'] . ' ' . $src['label'] . ': skipped (tripped)';
            continue;
        }
        try {
            $count = ($src['fn'])($pdo);
            resetSourceFailure($pdo, $failKey);
            $results[] = $src['emoji'] . " {$count} " . $src['label'] . " items";
        } catch (\Exception $e) {
            recordSourceFailure($pdo, $failKey);
            $results[] = $src['emoji'] . ' ' . $src['label'] . ': ' . $e->getMessage();
            $hasErrors = true;
        }
    }

    // Calendar events
    $calendarCfg = getCalendarConfig();
    if (!empty($calendarCfg['parliament_ch']['enabled'])) {
        $failKey = 'calendar_parliament_ch_failures';
        if (isSourceTripped($pdo, $failKey)) {
            $results[] = 'Calendar: skipped (tripped)';
        } else {
            try {
                $count = refreshParliamentChEvents($pdo);
                resetSourceFailure($pdo, $failKey);
                $results[] = "{$count} calendar events";
            } catch (\Exception $e) {
                recordSourceFailure($pdo, $failKey);
                $results[] = 'Calendar: ' . $e->getMessage();
                $hasErrors = true;
            }
        }
    }

    try {
        $recipeJson = getMagnituConfig($pdo, 'recipe_json');
        if ($recipeJson) {
            $recipeData = json_decode($recipeJson, true);
            if ($recipeData && !empty($recipeData['keywords'])) {
                magnituRescore($pdo, $recipeData);
                $results[] = 'Scores updated';
            }
        }
    } catch (\Exception $e) {
        $results[] = 'Scoring: ' . $e->getMessage();
        $hasErrors = true;
    }

    return [$results, $hasErrors];
}

function handleRefreshAll($pdo) {
    set_time_limit(300);

    $lastRefreshAt = getMagnituConfig($pdo, 'last_refresh_at');
    if ($lastRefreshAt && (time() - (int)$lastRefreshAt) < 60) {
        $remaining = 60 - (time() - (int)$lastRefreshAt);
        $_SESSION['error'] = "Please wait {$remaining}s before refreshing again.";
        $currentAction = $_GET['from'] ?? 'index';
        header('Location: ?action=' . $currentAction);
        exit;
    }
    setMagnituConfig($pdo, 'last_refresh_at', (string)time());

    [$results, ] = refreshAllSources($pdo);

    $_SESSION['success'] = implode(' · ', $results);
    $currentAction = $_GET['from'] ?? 'index';
    $redirectUrl = '?action=' . $currentAction;
    if ($currentAction === 'view_feed' && isset($_GET['id'])) {
        $redirectUrl .= '&id=' . (int)$_GET['id'];
    }
    header('Location: ' . $redirectUrl);
    exit;
}

/**
 * Satellite-callable refresh endpoint. Validates a shared secret via
 * `?key=<SEISMO_REMOTE_REFRESH_KEY>`; on a mothership where that constant is
 * unset/empty, the endpoint returns 404 (not advertised).
 *
 * Returns JSON {ok, messages[], elapsed_ms}. Does not use session storage so
 * it's safe for cross-origin calls from a satellite's public page.
 */
function handleRefreshAllRemote($pdo) {
    header('Content-Type: application/json; charset=utf-8');

    $expected = defined('SEISMO_REMOTE_REFRESH_KEY') ? (string)SEISMO_REMOTE_REFRESH_KEY : '';
    if ($expected === '') {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'remote refresh disabled']);
        return;
    }

    // Satellites should never serve this — they have no fetchers.
    if (isSatellite()) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'this instance is a satellite; call the mothership']);
        return;
    }

    $provided = (string)($_GET['key'] ?? $_POST['key'] ?? '');
    if (!hash_equals($expected, $provided)) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'invalid key']);
        return;
    }

    set_time_limit(300);

    // Soft rate-limit (same 60s window as the interactive button) to prevent
    // satellites spamming the mothership.
    $lastRefreshAt = getMagnituConfig($pdo, 'last_refresh_at');
    if ($lastRefreshAt && (time() - (int)$lastRefreshAt) < 60) {
        $remaining = 60 - (time() - (int)$lastRefreshAt);
        http_response_code(429);
        echo json_encode([
            'ok' => false,
            'error' => "rate limited, retry in {$remaining}s",
            'retry_after' => $remaining,
        ]);
        return;
    }
    setMagnituConfig($pdo, 'last_refresh_at', (string)time());

    $startedAt = microtime(true);
    [$results, $hasErrors] = refreshAllSources($pdo);
    $elapsedMs = (int)round((microtime(true) - $startedAt) * 1000);

    echo json_encode([
        'ok' => !$hasErrors,
        'messages' => $results,
        'elapsed_ms' => $elapsedMs,
    ]);
}

function handleDownloadRefreshConfig($pdo) {
    $seismoUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
               . '://' . $_SERVER['HTTP_HOST']
               . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

    $cfg  = "<?php\n";
    $cfg .= "/**\n";
    $cfg .= " * Refresh cronjob configuration — generated by Seismo.\n";
    $cfg .= " * Place this file next to refresh_cron.php.\n";
    $cfg .= " */\n\n";
    $cfg .= "return [\n";
    $cfg .= "    'seismo_path' => " . var_export(rtrim(dirname($_SERVER['SCRIPT_FILENAME']), '/'), true) . ",\n";
    $cfg .= "];\n";

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="config.php"');
    header('Content-Length: ' . strlen($cfg));
    echo $cfg;
    exit;
}

function handleDownloadRefreshScript($pdo) {
    $scriptPath = __DIR__ . '/../refresh_cron.php';
    if (!file_exists($scriptPath)) {
        $_SESSION['error'] = 'Refresh script not found.';
        header('Location: ' . getBasePath() . '/index.php?action=settings&tab=scraper');
        exit;
    }
    $content = file_get_contents($scriptPath);
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="refresh_cron.php"');
    header('Content-Length: ' . strlen($content));
    echo $content;
    exit;
}
