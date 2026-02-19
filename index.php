<?php
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('display_errors', '0');

session_start();

require_once 'config.php';
require_once 'vendor/autoload.php';
require_once 'controllers/magnitu.php';
require_once 'controllers/lex_jus.php';
require_once 'controllers/scraper.php';
require_once 'controllers/mail.php';
require_once 'controllers/rss.php';

use SimplePie\SimplePie;

// Initialize database tables
initDatabase();

$action = $_GET['action'] ?? 'index';
$pdo = getDbConnection();

// Release session lock early for read-only pages (prevents blocking concurrent requests).
// The hosting provider rate-limits concurrent HTTP requests per IP (~5); holding the
// PHP session file lock causes overlapping requests to queue, easily hitting that limit.
$readOnlyActions = ['index', 'feeds', 'view_feed', 'lex', 'jus', 'mail', 'substack', 'magnitu', 'settings', 'about', 'beta', 'styleguide',
                    'api_tags', 'api_substack_tags', 'api_email_tags', 'api_all_tags', 'api_items', 'api_stats',
                    'download_rss_config', 'download_substack_config', 'download_lex_config',
                    'magnitu_entries', 'magnitu_status'];
if (in_array($action, $readOnlyActions)) {
    // Consume flash messages from session, write the cleaned state, then release the lock
    $flashSuccess = $_SESSION['success'] ?? null;
    $flashError   = $_SESSION['error']   ?? null;
    unset($_SESSION['success'], $_SESSION['error']);
    session_write_close();
    // Restore in-memory so views can still render them (won't persist since session is closed)
    if ($flashSuccess !== null) $_SESSION['success'] = $flashSuccess;
    if ($flashError   !== null) $_SESSION['error']   = $flashError;
}

switch ($action) {
    case 'index':
        // Show main page with entries only (no feeds section)
        $searchQuery = trim($_GET['q'] ?? '');

        // Get all unique tags (categories) from enabled RSS feeds only (not Substack)
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
        
        // Get all unique email tags (excluding unclassified and disabled senders)
        $emailTagsStmt = $pdo->query("SELECT DISTINCT tag FROM sender_tags WHERE tag IS NOT NULL AND tag != '' AND tag != 'unclassified' AND removed_at IS NULL AND disabled = 0 ORDER BY tag");
        $emailTags = $emailTagsStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Get all unique Substack tags from enabled feeds
        $substackTagsStmt = $pdo->query("SELECT DISTINCT category FROM feeds WHERE source_type = 'substack' AND disabled = 0 AND category IS NOT NULL AND category != '' ORDER BY category");
        $substackTags = $substackTagsStmt->fetchAll(PDO::FETCH_COLUMN);

        // Tag filter: selected tags from query (multi-select)
        // On first visit (no form submitted), auto-select all tags except "unsortiert"
        $tagsSubmitted = isset($_GET['tags_submitted']);
        if ($tagsSubmitted) {
            $selectedTags = isset($_GET['tags']) ? array_values(array_filter((array)$_GET['tags'], 'strlen')) : [];
            $selectedEmailTags = isset($_GET['email_tags']) ? array_values(array_filter((array)$_GET['email_tags'], 'strlen')) : [];
            $selectedSubstackTags = isset($_GET['substack_tags']) ? array_values(array_filter((array)$_GET['substack_tags'], 'strlen')) : [];
            $selectedLexSources = isset($_GET['lex_sources']) ? array_values(array_filter((array)$_GET['lex_sources'], 'strlen')) : [];
        } else {
            // First visit: auto-select all tags except "unsortiert"
            $selectedTags = array_values(array_filter($tags, function($t) { return $t !== 'unsortiert'; }));
            $selectedEmailTags = array_values(array_filter($emailTags, function($t) { return $t !== 'unsortiert' && $t !== 'unclassified'; }));
            $selectedSubstackTags = $substackTags; // select all by default
            $lexCfg = getLexConfig();
            $selectedLexSources = array_values(array_filter(
                ['eu', 'ch', 'de', 'ch_bger', 'ch_bge', 'ch_bvger'],
                function($s) use ($lexCfg) { return !empty($lexCfg[$s]['enabled']); }
            ));
        }
        
        // Load enabled lex sources for pill rendering
        $lexCfg = $lexCfg ?? getLexConfig();
        $enabledLexSources = [];
        foreach (['eu', 'ch', 'de', 'ch_bger', 'ch_bge', 'ch_bvger'] as $s) {
            if (!empty($lexCfg[$s]['enabled'])) $enabledLexSources[] = $s;
        }
        // Strip any disabled sources from user selection
        $selectedLexSources = array_values(array_intersect($selectedLexSources, $enabledLexSources));
        
        // If search query exists, show search results instead of latest items
        if (!empty($searchQuery)) {
            $latestItems = searchFeedItems($pdo, $searchQuery, 100, $selectedTags);
            $searchEmails = searchEmails($pdo, $searchQuery, 100, $selectedEmailTags);
            $searchResultsCount = count($latestItems) + count($searchEmails);
        } else {
            // Get latest 30 items from enabled feeds only, filtered by tags
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
                    LIMIT 30
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($selectedTags);
                $latestItems = $stmt->fetchAll();
            } elseif (!$tagsSubmitted) {
                // First visit with no form submission: show all
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
                    LIMIT 30
                ");
                $latestItems = $latestItemsStmt->fetchAll();
            } else {
                // User explicitly deselected all tags: show nothing
                $latestItems = [];
            }
            $searchResultsCount = null;
        }
        
        // Get emails and merge with feed items
        if (!empty($searchQuery)) {
            $emails = $searchEmails;
        } else {
            if (!empty($selectedEmailTags)) {
                $emails = getEmailsForIndex($pdo, 30, $selectedEmailTags);
            } elseif (!$tagsSubmitted) {
                // First visit: show all emails
                $emails = getEmailsForIndex($pdo, 30, []);
            } else {
                // User explicitly deselected all email tags: show nothing
                $emails = [];
            }
        }
        
        // Fetch Substack items for the main timeline, filtered by selected Substack tags
        if (!empty($selectedSubstackTags)) {
            $placeholders = implode(',', array_fill(0, count($selectedSubstackTags), '?'));
            $substackItemsStmt = $pdo->prepare("
                SELECT fi.*, f.title as feed_title, f.category as feed_category
                FROM feed_items fi
                JOIN feeds f ON fi.feed_id = f.id
                WHERE f.source_type = 'substack' AND f.disabled = 0
                  AND f.category IN ($placeholders)
                ORDER BY fi.published_date DESC, fi.cached_at DESC
                LIMIT 30
            ");
            $substackItemsStmt->execute($selectedSubstackTags);
            $substackItems = $substackItemsStmt->fetchAll();
        } elseif (!$tagsSubmitted) {
            // First visit: show all
            $substackItemsStmt = $pdo->query("
                SELECT fi.*, f.title as feed_title, f.category as feed_category
                FROM feed_items fi
                JOIN feeds f ON fi.feed_id = f.id
                WHERE f.source_type = 'substack' AND f.disabled = 0
                ORDER BY fi.published_date DESC, fi.cached_at DESC
                LIMIT 30
            ");
            $substackItems = $substackItemsStmt->fetchAll();
        } else {
            // User explicitly deselected all: show nothing
            $substackItems = [];
        }
        
        // Fetch Scraper sources and items for the main timeline
        $scraperItemsForFeed = [];
        $scraperFeedsForIndex = []; // grouped by name for pills
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
            
            // Determine selected scraper sources from query params
            if ($tagsSubmitted) {
                $selectedScraperPills = isset($_GET['scraper_sources']) ? array_map('intval', (array)$_GET['scraper_sources']) : [];
            } else {
                $selectedScraperPills = array_column($scraperFeedsForIndex, 'id');
            }
            // Expand pill IDs to all feed IDs for that name
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
                    LIMIT 30
                ");
                $scraperStmt->execute($activeScraperFeedIds);
                $scraperItemsForFeed = $scraperStmt->fetchAll();
            }
        } catch (PDOException $e) {}
        
        // Fetch Lex items (EU + CH legislation), filtered by selected lex sources
        $lexItems = [];
        try {
            if (!empty($selectedLexSources)) {
                $lexPlaceholders = implode(',', array_fill(0, count($selectedLexSources), '?'));
                $lexStmt = $pdo->prepare("
                    SELECT * FROM lex_items
                    WHERE source IN ($lexPlaceholders)
                    ORDER BY document_date DESC
                    LIMIT 100
                ");
                $lexStmt->execute($selectedLexSources);
                $lexItems = array_slice(filterJusBannedWords($lexStmt->fetchAll()), 0, 30);
            } elseif (!$tagsSubmitted) {
                // First visit: show all
                $lexStmt = $pdo->query("
                    SELECT * FROM lex_items
                    ORDER BY document_date DESC
                    LIMIT 100
                ");
                $lexItems = array_slice(filterJusBannedWords($lexStmt->fetchAll()), 0, 30);
            }
            // else: user explicitly deselected all lex sources → $lexItems stays empty
        } catch (PDOException $e) {
            // lex_items table might not exist yet — silently skip
            $lexItems = [];
        }
        
        // Load Magnitu scores into a lookup map
        $scoreMap = []; // keyed by "type:id"
        try {
            $scoreStmt = $pdo->query("SELECT entry_type, entry_id, relevance_score, predicted_label, explanation, score_source FROM entry_scores");
            foreach ($scoreStmt->fetchAll() as $s) {
                $scoreMap[$s['entry_type'] . ':' . $s['entry_id']] = $s;
            }
        } catch (PDOException $e) {
            // entry_scores table might not exist yet
        }
        
        // Magnitu config: sort preference and alert threshold
        $magnituSortByRelevance = (bool)(getMagnituConfig($pdo, 'sort_by_relevance') ?? 0);
        $magnituAlertThreshold = (float)(getMagnituConfig($pdo, 'alert_threshold') ?? 0.75);
        // Allow user to toggle sort via query param
        if (isset($_GET['sort'])) {
            $magnituSortByRelevance = ($_GET['sort'] === 'relevance');
        }
        $hasMagnituScores = !empty($scoreMap);
        
        // Merge and sort by date
        $allItems = [];
        
        // Add feed items (RSS)
        foreach ($latestItems as $item) {
            $dateValue = $item['published_date'] ?? $item['cached_at'] ?? null;
            $scoreKey = 'feed_item:' . $item['id'];
            $score = $scoreMap[$scoreKey] ?? null;
            $allItems[] = [
                'type' => 'feed',
                'date' => $dateValue ? strtotime($dateValue) : 0,
                'data' => $item,
                'score' => $score,
            ];
        }
        
        // Add Substack items
        foreach ($substackItems as $item) {
            $dateValue = $item['published_date'] ?? $item['cached_at'] ?? null;
            $scoreKey = 'feed_item:' . $item['id'];
            $score = $scoreMap[$scoreKey] ?? null;
            $allItems[] = [
                'type' => 'substack',
                'date' => $dateValue ? strtotime($dateValue) : 0,
                'data' => $item,
                'score' => $score,
            ];
        }
        
        // Add emails
        foreach ($emails as $email) {
            $dateValue = $email['date_received'] ?? $email['date_utc'] ?? $email['created_at'] ?? $email['date_sent'] ?? null;
            $scoreKey = 'email:' . $email['id'];
            $score = $scoreMap[$scoreKey] ?? null;
            $allItems[] = [
                'type' => 'email',
                'date' => $dateValue ? strtotime($dateValue) : 0,
                'data' => $email,
                'score' => $score,
            ];
        }
        
        // Add Lex items (EU + CH legislation)
        foreach ($lexItems as $lexItem) {
            $dateValue = $lexItem['document_date'] ?? $lexItem['created_at'] ?? null;
            $scoreKey = 'lex_item:' . $lexItem['id'];
            $score = $scoreMap[$scoreKey] ?? null;
            $allItems[] = [
                'type' => 'lex',
                'date' => $dateValue ? strtotime($dateValue) : 0,
                'data' => $lexItem,
                'score' => $score,
            ];
        }
        
        // Add Scraper items
        foreach ($scraperItemsForFeed as $item) {
            $dateValue = $item['published_date'] ?? $item['cached_at'] ?? null;
            $scoreKey = 'feed_item:' . $item['id'];
            $score = $scoreMap[$scoreKey] ?? null;
            $allItems[] = [
                'type' => 'scraper',
                'date' => $dateValue ? strtotime($dateValue) : 0,
                'data' => $item,
                'score' => $score,
            ];
        }
        
        // Sort: by relevance if enabled and scores exist, otherwise by date
        if ($magnituSortByRelevance && $hasMagnituScores && empty($searchQuery)) {
            usort($allItems, function($a, $b) {
                $scoreA = $a['score']['relevance_score'] ?? -1;
                $scoreB = $b['score']['relevance_score'] ?? -1;
                if ($scoreA == $scoreB) return $b['date'] - $a['date']; // tie-break by date
                return $scoreB <=> $scoreA;
            });
        } else {
            usort($allItems, function($a, $b) {
                return $b['date'] - $a['date'];
            });
        }
        
        // Limit to 30 items total (or more for search)
        $limit = !empty($searchQuery) ? 200 : 30;
        $allItems = array_slice($allItems, 0, $limit);
        
        // Score coverage stats for display
        $scoredCount = count(array_filter($allItems, function($i) { return $i['score'] !== null; }));
        $totalScored = count($scoreMap);
        
        // Get last feed refresh date/time
        $lastRefreshStmt = $pdo->query("SELECT MAX(last_fetched) as last_refresh FROM feeds WHERE last_fetched IS NOT NULL");
        $lastRefreshResult = $lastRefreshStmt->fetch();
        $lastRefreshDate = null;
        if ($lastRefreshResult && $lastRefreshResult['last_refresh']) {
            $lastRefreshDate = date('d.m.Y H:i', strtotime($lastRefreshResult['last_refresh']));
        }
        
        // Get last code change date (use modification time of index.php)
        $lastChangeDate = date('d.m.Y', filemtime(__FILE__));
        
        include 'views/index.php';
        break;

    case 'magnitu':
        handleMagnituPage($pdo);
        break;

    case 'ai_view_unified':
        handleAiViewUnified($pdo);
        break;

    case 'ai_view':
        handleAiView($pdo);
        break;
        
    case 'feeds':
        // Show RSS entries page
        $selectedCategory = $_GET['category'] ?? null;
        
        // Set default category "unsortiert" for feeds without category
        $pdo->exec("UPDATE feeds SET category = 'unsortiert' WHERE (category IS NULL OR category = '') AND (source_type = 'rss' OR source_type IS NULL)");
        
        // Get all unique categories (RSS feeds only)
        $categoriesStmt = $pdo->query("SELECT DISTINCT category FROM feeds WHERE category IS NOT NULL AND category != '' AND (source_type = 'rss' OR source_type IS NULL) ORDER BY category");
        $categories = $categoriesStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Get RSS entries (from enabled RSS feeds only, filtered by category if selected)
        if ($selectedCategory) {
            $stmt = $pdo->prepare("
                SELECT fi.*, f.title as feed_title, f.category as feed_category
                FROM feed_items fi
                JOIN feeds f ON fi.feed_id = f.id
                WHERE f.disabled = 0 AND (f.source_type = 'rss' OR f.source_type IS NULL) AND f.category = ?
                ORDER BY fi.published_date DESC, fi.cached_at DESC
                LIMIT 50
            ");
            $stmt->execute([$selectedCategory]);
        } else {
            $stmt = $pdo->query("
                SELECT fi.*, f.title as feed_title, f.category as feed_category
                FROM feed_items fi
                JOIN feeds f ON fi.feed_id = f.id
                WHERE f.disabled = 0 AND (f.source_type = 'rss' OR f.source_type IS NULL)
                ORDER BY fi.published_date DESC, fi.cached_at DESC
                LIMIT 50
            ");
        }
        $rssItems = $stmt->fetchAll();
        
        // Get last feed refresh date/time (RSS only)
        $lastRefreshStmt = $pdo->query("SELECT MAX(last_fetched) as last_refresh FROM feeds WHERE (source_type = 'rss' OR source_type IS NULL) AND last_fetched IS NOT NULL");
        $lastRefreshRow = $lastRefreshStmt->fetch();
        $lastRssRefreshDate = $lastRefreshRow['last_refresh'] ? date('d.m.Y H:i', strtotime($lastRefreshRow['last_refresh'])) : null;
        
        include 'views/feeds.php';
        break;
        
    case 'mail':
        handleMailPage($pdo);
        break;
    
    case 'substack':
        handleSubstackPage($pdo);
        break;
    
    case 'add_substack':
        handleAddSubstack($pdo);
        break;
    
    case 'refresh_all_substacks':
        handleRefreshAllSubstacks($pdo);
        break;
        
    case 'add_feed':
        handleAddFeed($pdo);
        break;
        
    case 'delete_feed':
        handleDeleteFeed($pdo);
        break;
        
    case 'toggle_feed':
        handleToggleFeed($pdo);
        break;
        
    case 'view_feed':
        handleViewFeed($pdo);
        break;
        
    case 'refresh_feed':
        handleRefreshFeed($pdo);
        break;
        
    case 'refresh_all':
        // Cooldown: prevent rapid re-triggering (60s minimum between refreshes)
        $lastRefreshAt = getMagnituConfig($pdo, 'last_refresh_at');
        if ($lastRefreshAt && (time() - (int)$lastRefreshAt) < 60) {
            $remaining = 60 - (time() - (int)$lastRefreshAt);
            $_SESSION['error'] = "Please wait {$remaining}s before refreshing again.";
            $currentAction = $_GET['from'] ?? 'index';
            header('Location: ?action=' . $currentAction);
            exit;
        }
        setMagnituConfig($pdo, 'last_refresh_at', (string)time());
        
        // Global refresh: feeds (RSS + Substack) + emails + lex
        $results = [];
        
        // 1. Refresh all feeds (RSS + Substack)
        try {
            refreshAllFeeds($pdo);
            $results[] = 'Feeds refreshed';
        } catch (Exception $e) {
            $results[] = 'Feeds: ' . $e->getMessage();
        }
        
        // 2. Refresh emails
        try {
            refreshEmails($pdo);
            $results[] = 'Emails refreshed';
        } catch (Exception $e) {
            $results[] = 'Emails: ' . $e->getMessage();
        }
        
        // 3. Refresh lex items (EU + CH)
        $lexCfg = getLexConfig();
        if ($lexCfg['eu']['enabled'] ?? true) {
            try {
                $countEu = refreshLexItems($pdo);
                $results[] = "🇪🇺 $countEu lex items";
            } catch (Exception $e) {
                $results[] = '🇪🇺 EU: ' . $e->getMessage();
            }
        }
        if ($lexCfg['ch']['enabled'] ?? true) {
            try {
                $countCh = refreshFedlexItems($pdo);
                $results[] = "🇨🇭 $countCh lex items";
            } catch (Exception $e) {
                $results[] = '🇨🇭 CH: ' . $e->getMessage();
            }
        }
        if ($lexCfg['de']['enabled'] ?? true) {
            try {
                $countDe = refreshRechtBundItems($pdo);
                $results[] = "🇩🇪 $countDe lex items";
            } catch (Exception $e) {
                $results[] = '🇩🇪 DE: ' . $e->getMessage();
            }
        }
        
        // 3b. Refresh JUS items (Swiss case law)
        if ($lexCfg['ch_bger']['enabled'] ?? false) {
            try {
                $countBger = refreshJusItems($pdo, 'CH_BGer');
                $results[] = "⚖️ $countBger BGer items";
            } catch (Exception $e) {
                $results[] = '⚖️ BGer: ' . $e->getMessage();
            }
        }
        if ($lexCfg['ch_bge']['enabled'] ?? false) {
            try {
                $countBge = refreshJusItems($pdo, 'CH_BGE');
                $results[] = "⚖️ $countBge BGE items";
            } catch (Exception $e) {
                $results[] = '⚖️ BGE: ' . $e->getMessage();
            }
        }
        if ($lexCfg['ch_bvger']['enabled'] ?? false) {
            try {
                $countBvger = refreshJusItems($pdo, 'CH_BVGer');
                $results[] = "⚖️ $countBvger BVGer items";
            } catch (Exception $e) {
                $results[] = '⚖️ BVGer: ' . $e->getMessage();
            }
        }
        
        // 4. Magnitu recipe scoring for new entries
        try {
            $recipeJson = getMagnituConfig($pdo, 'recipe_json');
            if ($recipeJson) {
                $recipeData = json_decode($recipeJson, true);
                if ($recipeData && !empty($recipeData['keywords'])) {
                    magnituRescore($pdo, $recipeData);
                    $results[] = 'Scores updated';
                }
            }
        } catch (Exception $e) {
            $results[] = 'Scoring: ' . $e->getMessage();
        }
        
        $_SESSION['success'] = implode(' · ', $results);
        $currentAction = $_GET['from'] ?? 'index';
        $redirectUrl = '?action=' . $currentAction;
        if ($currentAction === 'view_feed' && isset($_GET['id'])) {
            $redirectUrl .= '&id=' . (int)$_GET['id'];
        }
        header('Location: ' . $redirectUrl);
        exit;
    
    case 'refresh_all_feeds':
        handleRefreshAllFeeds($pdo);
        break;
        
    case 'api_feeds':
        handleApiFeeds($pdo);
        break;
        
    case 'api_items':
        handleApiItems($pdo);
        break;
        
    case 'api_tags':
        handleApiTags($pdo);
        break;
    
    case 'api_substack_tags':
        handleApiSubstackTags($pdo);
        break;
    
    case 'api_all_tags':
        handleApiAllTags($pdo);
        break;
        
    case 'update_feed_tag':
        handleUpdateFeedTag($pdo);
        break;
        
    case 'refresh_emails':
        handleRefreshEmails($pdo);
        break;
        
    case 'delete_email':
        handleDeleteEmail($pdo);
        break;
        
    case 'settings':
        // Show settings page
        $pdo = getDbConnection();
        $settingsTab = $_GET['tab'] ?? 'basic';
        
        // Get all RSS feeds for RSS section
        $feedsStmt = $pdo->query("SELECT * FROM feeds WHERE source_type = 'rss' OR source_type IS NULL ORDER BY created_at DESC");
        $allFeeds = $feedsStmt->fetchAll();
        
        // Get Substack feeds for Substack section
        $substackFeedsStmt = $pdo->query("SELECT * FROM feeds WHERE source_type = 'substack' ORDER BY created_at DESC");
        $substackFeeds = $substackFeedsStmt->fetchAll();
        
        // Get all unique tags from RSS feeds
        $tagsStmt = $pdo->query("SELECT DISTINCT category FROM feeds WHERE category IS NOT NULL AND category != '' AND (source_type = 'rss' OR source_type IS NULL) ORDER BY category");
        $allTags = $tagsStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Get all unique Substack tags
        $substackTagsStmt = $pdo->query("SELECT DISTINCT category FROM feeds WHERE source_type = 'substack' AND category IS NOT NULL AND category != '' ORDER BY category");
        $allSubstackTags = $substackTagsStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Get all unique email tags (excluding unclassified and removed senders)
        $emailTagsStmt = $pdo->query("SELECT DISTINCT tag FROM sender_tags WHERE tag IS NOT NULL AND tag != '' AND tag != 'unclassified' AND removed_at IS NULL ORDER BY tag");
        $allEmailTags = $emailTagsStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Get all unique senders and their tags for Mail section
        $senderTags = [];
        try {
            $emailTableName = getEmailTableName($pdo);
            
            if ($emailTableName) {
                // Get column names to determine which columns exist
                $descStmt = $pdo->query("DESCRIBE `$emailTableName`");
                $tableColumns = $descStmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Determine which columns to use
                $hasFromEmail = in_array('from_email', $tableColumns);
                $hasFromAddr = in_array('from_addr', $tableColumns);
                $hasFromName = in_array('from_name', $tableColumns);
                
                // Build query based on available columns
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
                
                // Determine which email column and date columns to use for "newer email" checks
                $emailCol = $hasFromEmail ? 'from_email' : ($hasFromAddr ? 'from_addr' : null);
                $hasDateReceived = in_array('date_received', $tableColumns);
                $hasCreatedAt = in_array('created_at', $tableColumns);
                
                // Auto-tag new senders, re-activate removed senders only if newer emails exist
                foreach ($senders as $sender) {
                    $email = $sender['email'];
                    $tagStmt = $pdo->prepare("SELECT tag, disabled, removed_at FROM sender_tags WHERE from_email = ?");
                    $tagStmt->execute([$email]);
                    $tagResult = $tagStmt->fetch();
                    
                    if (!$tagResult) {
                        // Genuinely new sender — auto-tag with "unclassified"
                        $insertStmt = $pdo->prepare("INSERT INTO sender_tags (from_email, tag, disabled) VALUES (?, 'unclassified', 0)");
                        $insertStmt->execute([$email]);
                        $tagResult = ['tag' => 'unclassified', 'disabled' => 0, 'removed_at' => null];
                    } elseif ($tagResult['removed_at'] && $emailCol) {
                        // Sender was removed — check if a newer email has arrived since removal
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
                                // New email arrived after removal — re-activate
                                $reactivateStmt = $pdo->prepare("UPDATE sender_tags SET removed_at = NULL WHERE from_email = ?");
                                $reactivateStmt->execute([$email]);
                                $tagResult['removed_at'] = null;
                            }
                        }
                    }
                    
                    // Only show senders that are not removed
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
        } catch (PDOException $e) {
            // Error getting senders
        }
        
        // Load Lex config for the Lex settings section
        $lexConfig = getLexConfig();
        
        // Load Magnitu config for the Magnitu settings section
        $magnituConfig = getAllMagnituConfig($pdo);
        $magnituScoreStats = ['total' => 0, 'magnitu' => 0, 'recipe' => 0];
        try {
            $magnituScoreStats['total'] = (int)$pdo->query("SELECT COUNT(*) FROM entry_scores")->fetchColumn();
            $magnituScoreStats['magnitu'] = (int)$pdo->query("SELECT COUNT(*) FROM entry_scores WHERE score_source = 'magnitu'")->fetchColumn();
            $magnituScoreStats['recipe'] = (int)$pdo->query("SELECT COUNT(*) FROM entry_scores WHERE score_source = 'recipe'")->fetchColumn();
        } catch (PDOException $e) {}
        
        // Load scraper configs for Script tab
        $scraperConfigs = [];
        try {
            $scraperConfigs = $pdo->query("SELECT * FROM scraper_configs ORDER BY created_at DESC")->fetchAll();
        } catch (PDOException $e) {}
        
        // Load mail fetcher config for Script tab
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
        
        include 'views/settings.php';
        break;
        
    case 'update_sender_tag':
        handleUpdateSenderTag($pdo);
        break;
        
    case 'toggle_sender':
        handleToggleSender($pdo);
        break;
        
    case 'delete_sender':
        handleDeleteSender($pdo);
        break;
        
    case 'rename_tag':
        handleRenameTag($pdo);
        break;
        
    case 'rename_substack_tag':
        handleRenameSubstackTag($pdo);
        break;
    
    case 'rename_email_tag':
        handleRenameEmailTag($pdo);
        break;
    
    case 'add_scraper':
        handleAddScraper($pdo);
        break;
    
    case 'update_scraper':
        handleUpdateScraper($pdo);
        break;
    
    case 'toggle_scraper':
        handleToggleScraper($pdo);
        break;
    
    case 'remove_scraper':
        handleRemoveScraper($pdo);
        break;
    
    case 'hide_scraper_item':
        handleHideScraperItem($pdo);
        break;
    
    case 'delete_all_scraper_items':
        handleDeleteAllScraperItems($pdo);
        break;
    
    case 'rescrape_source':
        handleRescrapeSource($pdo);
        break;
    
    case 'download_scraper_config':
        handleDownloadScraperConfig($pdo);
        break;

    case 'save_mail_config':
        handleSaveMailConfig($pdo);
        break;

    case 'download_mail_config':
        handleDownloadMailConfig($pdo);
        break;

    case 'download_mail_script':
        handleDownloadMailScript($pdo);
        break;

    case 'download_scraper_script':
        handleDownloadScraperScript($pdo);
        break;
    
    case 'save_magnitu_config':
        handleSaveMagnituConfig($pdo);
        break;

    case 'regenerate_magnitu_key':
        handleRegenerateMagnituKey($pdo);
        break;

    case 'clear_magnitu_scores':
        handleClearMagnituScores($pdo);
        break;
        
    case 'api_email_tags':
        handleApiEmailTags($pdo);
        break;
        
    case 'lex':
        handleLexPage($pdo);
        break;
    
    case 'refresh_all_lex':
        handleRefreshAllLex($pdo);
        break;
    
    case 'jus':
        handleJusPage($pdo);
        break;
    
    case 'scraper':
        handleScraperPage($pdo);
        break;
    
    case 'refresh_all_jus':
        handleRefreshAllJus($pdo);
        break;
    
    case 'save_lex_config':
        handleSaveLexConfig($pdo);
        break;
    
    case 'upload_lex_config':
        handleUploadLexConfig($pdo);
        break;
    
    case 'download_lex_config':
        handleDownloadLexConfig($pdo);
        break;
    
    case 'download_rss_config':
        $feeds = exportFeeds($pdo, 'rss');
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="rss_feeds.json"');
        echo json_encode($feeds, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    
    case 'upload_rss_config':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['rss_config_file'])) {
            $file = $_FILES['rss_config_file'];
            if ($file['error'] === UPLOAD_ERR_OK && $file['size'] > 0) {
                $content = file_get_contents($file['tmp_name']);
                $parsed = json_decode($content, true);
                if (is_array($parsed) && !empty($parsed)) {
                    try {
                        [$created, $updated] = importFeeds($pdo, $parsed, 'rss');
                        $_SESSION['success'] = "RSS config imported: $created new, $updated updated.";
                    } catch (Exception $e) {
                        $_SESSION['error'] = 'Import error: ' . $e->getMessage();
                    }
                } else {
                    $_SESSION['error'] = 'Invalid JSON file. Expected an array of feed objects.';
                }
            } else {
                $_SESSION['error'] = 'No file uploaded or upload error.';
            }
        }
        header('Location: ' . getBasePath() . '/index.php?action=settings&tab=basic');
        exit;
    
    case 'download_substack_config':
        $feeds = exportFeeds($pdo, 'substack');
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="substack_feeds.json"');
        echo json_encode($feeds, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    
    case 'upload_substack_config':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['substack_config_file'])) {
            $file = $_FILES['substack_config_file'];
            if ($file['error'] === UPLOAD_ERR_OK && $file['size'] > 0) {
                $content = file_get_contents($file['tmp_name']);
                $parsed = json_decode($content, true);
                if (is_array($parsed) && !empty($parsed)) {
                    try {
                        [$created, $updated] = importFeeds($pdo, $parsed, 'substack');
                        $_SESSION['success'] = "Substack config imported: $created new, $updated updated.";
                    } catch (Exception $e) {
                        $_SESSION['error'] = 'Import error: ' . $e->getMessage();
                    }
                } else {
                    $_SESSION['error'] = 'Invalid JSON file. Expected an array of feed objects.';
                }
            } else {
                $_SESSION['error'] = 'No file uploaded or upload error.';
            }
        }
        header('Location: ' . getBasePath() . '/index.php?action=settings&tab=basic');
        exit;
    
    case 'about':
        // About page with stats
        $stats = [];
        try {
            $stats['feeds'] = $pdo->query("SELECT COUNT(*) FROM feeds WHERE source_type = 'rss' OR source_type IS NULL")->fetchColumn();
            $stats['feed_items'] = $pdo->query("SELECT COUNT(*) FROM feed_items")->fetchColumn();
            
            $emailTable = getEmailTableName($pdo);
            $stats['emails'] = $pdo->query("SELECT COUNT(*) FROM `$emailTable`")->fetchColumn();
            
            $stats['lex_eu'] = $pdo->query("SELECT COUNT(*) FROM lex_items WHERE source = 'eu'")->fetchColumn();
            $stats['lex_ch'] = $pdo->query("SELECT COUNT(*) FROM lex_items WHERE source = 'ch'")->fetchColumn();
            $stats['lex_de'] = $pdo->query("SELECT COUNT(*) FROM lex_items WHERE source = 'de'")->fetchColumn();
            $stats['jus_bger'] = $pdo->query("SELECT COUNT(*) FROM lex_items WHERE source = 'ch_bger'")->fetchColumn();
            $stats['jus_bge'] = $pdo->query("SELECT COUNT(*) FROM lex_items WHERE source = 'ch_bge'")->fetchColumn();
            $stats['jus_bvger'] = $pdo->query("SELECT COUNT(*) FROM lex_items WHERE source = 'ch_bvger'")->fetchColumn();
            $stats['scraper_configs'] = $pdo->query("SELECT COUNT(*) FROM scraper_configs")->fetchColumn();
            $stats['scraper_items'] = $pdo->query("SELECT COUNT(*) FROM feed_items fi JOIN feeds f ON fi.feed_id = f.id WHERE f.source_type = 'scraper'")->fetchColumn();
        } catch (PDOException $e) {
            // Tables might not exist yet
        }
        $lastChangeDate = date('d.m.Y', filemtime(__FILE__));
        include 'views/about.php';
        break;

    case 'beta':
        // Beta page with links to experimental views
        $lastChangeDate = date('d.m.Y', filemtime(__FILE__));
        include 'views/beta.php';
        break;

    case 'styleguide':
        // Get last code change date (use modification time of index.php)
        $lastChangeDate = date('d.m.Y', filemtime(__FILE__));
        include 'views/styleguide.php';
        break;

    // ═══════════════════════════════════════════════════════════════
    // Magnitu API endpoints
    // All require Bearer token authentication via API key
    // ═══════════════════════════════════════════════════════════════
    
    case 'magnitu_entries':
        handleMagnituEntries($pdo);
        break;

    case 'magnitu_scores':
        handleMagnituScores($pdo);
        break;

    case 'magnitu_recipe':
        handleMagnituRecipe($pdo);
        break;

    case 'magnitu_status':
        handleMagnituStatus($pdo);
        break;

    case 'magnitu_labels':
        handleMagnituLabels($pdo);
        break;
    
    default:
        header('Location: ?action=index');
        exit;
}



