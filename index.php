<?php
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('display_errors', '0');

session_start();

require_once 'config.php';
require_once 'vendor/autoload.php';
require_once 'controllers/magnitu.php';
require_once 'controllers/lex_jus.php';

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
        // Show mail page
        // Get latest emails (if table exists)
        $emails = [];
        $mailTableError = null;
        $lastMailRefreshDate = null;
        $showAll = isset($_GET['show_all']) || isset($_SESSION['email_refresh_count']);
        $limit = $showAll ? 500 : 50; // Show more emails when refreshed, capped for stability
        
        // Get all unique email tags (excluding unclassified and removed senders)
        $emailTagsStmt = $pdo->query("SELECT DISTINCT tag FROM sender_tags WHERE tag IS NOT NULL AND tag != '' AND tag != 'unclassified' AND removed_at IS NULL ORDER BY tag");
        $emailTags = $emailTagsStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Get selected email tag filter
        $selectedEmailTag = $_GET['email_tag'] ?? null;
        
        // Get disabled sender emails (including removed senders)
        $disabledStmt = $pdo->query("SELECT from_email FROM sender_tags WHERE disabled = 1 OR removed_at IS NOT NULL");
        $disabledEmails = $disabledStmt->fetchAll(PDO::FETCH_COLUMN);
        
        $tableName = getEmailTableName($pdo);
        
        try {
            $foundTable = $tableName;
            
            if (!$foundTable) {
                $mailTableError = "No emails table found.";
            } else {
                
                // Refreshed: last time the fetch script added an email (latest created_at or similar timestamp column)
                // Try different possible timestamp column names (including cronjob's date_utc)
                $timestampColumns = ['created_at', 'date_utc', 'date_received', 'date_sent', 'timestamp', 'created'];
                $lastRefreshDate = null;
                
                foreach ($timestampColumns as $col) {
                    try {
                        $lastMailRefreshStmt = $pdo->query("SELECT MAX(`$col`) AS last_refresh FROM `$tableName` WHERE `$col` IS NOT NULL");
                        $lastMailRefreshResult = $lastMailRefreshStmt->fetch();
                        if ($lastMailRefreshResult && $lastMailRefreshResult['last_refresh']) {
                            $lastRefreshDate = $lastMailRefreshResult['last_refresh'];
                            break;
                        }
                    } catch (PDOException $e) {
                        // Column doesn't exist, try next
                        continue;
                    }
                }
                
                if ($lastRefreshDate) {
                    $lastMailRefreshDate = date('d.m.Y H:i', strtotime($lastRefreshDate));
                }

                // Get count of emails for debugging
                $countStmt = $pdo->query("SELECT COUNT(*) as count FROM `$tableName`");
                $countResult = $countStmt->fetch();
                $emailCount = $countResult['count'] ?? 0;

                // Get column names to understand the structure
                $descStmt = $pdo->query("DESCRIBE `$tableName`");
                $tableColumns = $descStmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Try to get emails - map cronjob columns to expected columns
                try {
                    // Check if this is the cronjob table structure (has from_addr, body_text, body_html, date_utc)
                    $isCronjobTable = in_array('from_addr', $tableColumns) && 
                                     (in_array('body_text', $tableColumns) || in_array('body_html', $tableColumns));
                    
                    if ($isCronjobTable) {
                        // Use cronjob column names and map them
                        $selectClause = "
                            id,
                            subject,
                            from_addr as from_email,
                            from_addr as from_name,
                            date_utc as date_received,
                            date_utc as date_sent,
                            body_text as text_body,
                            body_html as html_body,
                            created_at
                        ";
                        $orderBy = "date_utc DESC";
                    } else {
                        // Try standard column names
                        $selectColumns = [];
                        $columnMap = [
                            'id' => 'id',
                            'subject' => 'subject',
                            'from_email' => 'from_email',
                            'from_name' => 'from_name',
                            'created_at' => 'created_at',
                            'date_received' => 'date_received',
                            'date_sent' => 'date_sent',
                            'text_body' => 'text_body',
                            'html_body' => 'html_body'
                        ];
                        
                        foreach ($columnMap as $expected => $actual) {
                            if (in_array($actual, $tableColumns)) {
                                $selectColumns[] = "`$actual` as `$expected`";
                            }
                        }
                        
                        if (empty($selectColumns)) {
                            // Fallback to SELECT *
                            $selectClause = '*';
                        } else {
                            $selectClause = implode(', ', $selectColumns);
                        }
                        
                        // Determine ORDER BY column — prefer actual email date over insertion timestamp
                        $orderBy = 'id DESC'; // Default
                        foreach (['date_received', 'date_utc', 'date_sent', 'created_at', 'id'] as $orderCol) {
                            if (in_array($orderCol, $tableColumns)) {
                                $orderBy = "`$orderCol` DESC";
                                break;
                            }
                        }
                    }
                    
                    // Build WHERE clause to exclude disabled senders and filter by tag if selected
                    $whereClause = "1=1";
                    $params = [];
                    
                    // Exclude disabled senders
                    if (!empty($disabledEmails)) {
                        $placeholders = implode(',', array_fill(0, count($disabledEmails), '?'));
                        // Handle both from_email and from_addr columns
                        if ($isCronjobTable) {
                            $whereClause = "from_addr NOT IN ($placeholders)";
                        } else {
                            $whereClause = "from_email NOT IN ($placeholders)";
                        }
                        $params = $disabledEmails;
                    }
                    
                    // Filter by email tag if selected
                    if ($selectedEmailTag) {
                        $tagStmt = $pdo->prepare("SELECT from_email FROM sender_tags WHERE tag = ? AND removed_at IS NULL");
                        $tagStmt->execute([$selectedEmailTag]);
                        $taggedEmails = $tagStmt->fetchAll(PDO::FETCH_COLUMN);
                        
                        if (!empty($taggedEmails)) {
                            $tagPlaceholders = implode(',', array_fill(0, count($taggedEmails), '?'));
                            if ($isCronjobTable) {
                                // Always append with AND to avoid malformed "1=1from_addr" when no previous conditions
                                $whereClause .= " AND from_addr IN ($tagPlaceholders)";
                            } else {
                                $whereClause .= " AND from_email IN ($tagPlaceholders)";
                            }
                            $params = array_merge($params, $taggedEmails);
                        } else {
                            // No emails with this tag, return empty
                            $emails = [];
                            break;
                        }
                    }
                    
                    $sql = "SELECT $selectClause FROM `$tableName` WHERE $whereClause ORDER BY $orderBy LIMIT $limit";
                    if (!empty($params)) {
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                    } else {
                        $stmt = $pdo->query($sql);
                    }
                    $emails = $stmt->fetchAll();
                    
                    // Post-process emails to parse from_addr if needed
                    foreach ($emails as &$email) {
                        // If from_email and from_name are the same (both from_addr), parse it
                        if (isset($email['from_email']) && isset($email['from_name']) && 
                            $email['from_email'] === $email['from_name'] && 
                            !empty($email['from_email'])) {
                            $fromAddr = $email['from_email'];
                            // Parse "Name" <email@domain.com> or just email@domain.com
                            if (preg_match('/^"([^"]+)"\s*<(.+)>$/', $fromAddr, $matches)) {
                                $email['from_name'] = $matches[1];
                                $email['from_email'] = $matches[2];
                            } elseif (preg_match('/^(.+)\s*<(.+)>$/', $fromAddr, $matches)) {
                                $email['from_name'] = trim($matches[1]);
                                $email['from_email'] = $matches[2];
                            } elseif (preg_match('/^(.+@.+)$/', $fromAddr)) {
                                // Just email, no name
                                $email['from_email'] = $fromAddr;
                                $email['from_name'] = '';
                            }
                        }
                    }
                    unset($email); // Break reference
                    attachSenderTags($pdo, $emails);
                    
                    // Sort emails chronologically by date
                    usort($emails, function($a, $b) {
                        $dateA = $a['date_received'] ?? $a['date_utc'] ?? $a['created_at'] ?? $a['date_sent'] ?? '';
                        $dateB = $b['date_received'] ?? $b['date_utc'] ?? $b['created_at'] ?? $b['date_sent'] ?? '';
                        $timeA = $dateA ? strtotime($dateA) : 0;
                        $timeB = $dateB ? strtotime($dateB) : 0;
                        return $timeB - $timeA; // Newest first
                    });
                } catch (PDOException $e) {
                    // If that fails, try SELECT *
                    try {
                        $stmt = $pdo->query("SELECT * FROM `$tableName` LIMIT $limit");
                        $emails = $stmt->fetchAll();
                        $mailTableError = "Warning: Using SELECT * query. Table columns: " . implode(', ', $tableColumns) . ". Original error: " . $e->getMessage();
                    } catch (PDOException $e2) {
                        $mailTableError = "Query error: " . $e2->getMessage() . ". Table: $tableName, Columns: " . implode(', ', $tableColumns);
                        $emails = [];
                    }
                }
                
                // Debug: if count > 0 but emails array is empty, there might be a column mismatch
                if ($emailCount > 0 && empty($emails)) {
                    $mailTableError = "Found $emailCount email(s) in table '$tableName' but query returned no results. Table columns: " . implode(', ', $tableColumns);
                } elseif ($emailCount > 0 && count($emails) > 0) {
                    // Success - clear any previous errors
                    if (isset($_SESSION['email_refresh_count'])) {
                        unset($_SESSION['email_refresh_count']);
                    }
                }
            }
        } catch (PDOException $e) {
            // Table might not exist yet on some installations or there's a query error
            $mailTableError = "Database error: " . $e->getMessage();
        }

        // Get last code change date (use modification time of index.php)
        $lastChangeDate = date('d.m.Y', filemtime(__FILE__));
        
        include 'views/mail.php';
        break;
    
    case 'substack':
        // Show Substack entries page
        $selectedSubstackCategory = $_GET['category'] ?? null;
        
        // Get all unique categories from Substack feeds
        $substackCategoriesStmt = $pdo->query("SELECT DISTINCT category FROM feeds WHERE source_type = 'substack' AND category IS NOT NULL AND category != '' ORDER BY category");
        $substackCategories = $substackCategoriesStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Get Substack entries (filtered by category if selected)
        if ($selectedSubstackCategory) {
            $stmt = $pdo->prepare("
                SELECT fi.*, f.title as feed_title, f.category as feed_category
                FROM feed_items fi
                JOIN feeds f ON fi.feed_id = f.id
                WHERE f.source_type = 'substack' AND f.disabled = 0 AND f.category = ?
                ORDER BY fi.published_date DESC, fi.cached_at DESC
                LIMIT 50
            ");
            $stmt->execute([$selectedSubstackCategory]);
        } else {
            $stmt = $pdo->query("
                SELECT fi.*, f.title as feed_title, f.category as feed_category
                FROM feed_items fi
                JOIN feeds f ON fi.feed_id = f.id
                WHERE f.source_type = 'substack' AND f.disabled = 0
                ORDER BY fi.published_date DESC, fi.cached_at DESC
                LIMIT 50
            ");
        }
        $substackItems = $stmt->fetchAll();
        
        // Get last refresh date for substack feeds
        $lastRefreshStmt = $pdo->query("SELECT MAX(last_fetched) as last_refresh FROM feeds WHERE source_type = 'substack' AND last_fetched IS NOT NULL");
        $lastRefreshRow = $lastRefreshStmt->fetch();
        $lastSubstackRefreshDate = $lastRefreshRow['last_refresh'] ? date('d.m.Y H:i', strtotime($lastRefreshRow['last_refresh'])) : null;
        
        include 'views/substack.php';
        break;
    
    case 'add_substack':
        handleAddSubstack($pdo);
        break;
    
    case 'refresh_all_substacks':
        // Refresh only substack feeds
        $stmt = $pdo->query("SELECT id FROM feeds WHERE source_type = 'substack' ORDER BY id");
        $substackFeeds = $stmt->fetchAll();
        foreach ($substackFeeds as $feed) {
            refreshFeed($pdo, $feed['id']);
        }
        $_SESSION['success'] = 'All Substack feeds refreshed successfully';
        header('Location: ?action=substack');
        exit;
        
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
        $feedId = (int)($_GET['id'] ?? 0);
        viewFeed($pdo, $feedId);
        break;
        
    case 'refresh_feed':
        $feedId = (int)($_GET['id'] ?? 0);
        refreshFeed($pdo, $feedId);
        header('Location: ?action=view_feed&id=' . $feedId);
        exit;
        
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
        refreshAllFeeds($pdo);
        $currentAction = $_GET['from'] ?? 'index';
        $redirectUrl = '?action=' . $currentAction;
        if ($currentAction === 'view_feed' && isset($_GET['id'])) {
            $redirectUrl .= '&id=' . (int)$_GET['id'];
        } elseif ($currentAction === 'feeds' && isset($_GET['category'])) {
            $redirectUrl .= '&category=' . urlencode($_GET['category']);
        }
        $_SESSION['success'] = 'All feeds refreshed successfully';
        header('Location: ' . $redirectUrl);
        exit;
        
    case 'api_feeds':
        header('Content-Type: application/json');
        $stmt = $pdo->query("SELECT * FROM feeds ORDER BY created_at DESC LIMIT 1000");
        echo json_encode($stmt->fetchAll());
        break;
        
    case 'api_items':
        header('Content-Type: application/json');
        $feedId = (int)($_GET['feed_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM feed_items WHERE feed_id = ? ORDER BY published_date DESC LIMIT 50");
        $stmt->execute([$feedId]);
        echo json_encode($stmt->fetchAll());
        break;
        
    case 'api_tags':
        header('Content-Type: application/json');
        $stmt = $pdo->query("SELECT DISTINCT category FROM feeds WHERE category IS NOT NULL AND category != '' AND (source_type = 'rss' OR source_type IS NULL) ORDER BY category");
        $tags = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode($tags);
        break;
    
    case 'api_substack_tags':
        header('Content-Type: application/json');
        $stmt = $pdo->query("SELECT DISTINCT category FROM feeds WHERE category IS NOT NULL AND category != '' AND source_type = 'substack' ORDER BY category");
        $tags = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode($tags);
        break;
    
    case 'api_all_tags':
        // Combined endpoint: returns all tag lists in one response (avoids concurrent requests)
        session_write_close();
        header('Content-Type: application/json');
        $rssTags = $pdo->query("SELECT DISTINCT category FROM feeds WHERE category IS NOT NULL AND category != '' AND (source_type = 'rss' OR source_type IS NULL) ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
        $substackTags = $pdo->query("SELECT DISTINCT category FROM feeds WHERE category IS NOT NULL AND category != '' AND source_type = 'substack' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
        $emailTags = $pdo->query("SELECT DISTINCT tag FROM sender_tags WHERE tag IS NOT NULL AND tag != '' AND tag != 'unclassified' AND removed_at IS NULL ORDER BY tag")->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(['rss' => $rssTags, 'substack' => $substackTags, 'email' => $emailTags]);
        break;
        
    case 'update_feed_tag':
        handleUpdateFeedTag($pdo);
        break;
        
    case 'refresh_emails':
        refreshEmails($pdo);
        $currentAction = $_GET['from'] ?? 'mail';
        $redirectUrl = '?action=' . $currentAction . '&show_all=1';
        // Success message is set in refreshEmails() function
        header('Location: ' . $redirectUrl);
        exit;
        
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
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['scraper_name'] ?? '');
            $url = trim($_POST['scraper_url'] ?? '');
            $linkPattern = trim($_POST['scraper_link_pattern'] ?? '');
            $dateSelector = trim($_POST['scraper_date_selector'] ?? '');
            if (!empty($name) && !empty($url)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO scraper_configs (name, url, link_pattern, date_selector) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$name, $url, $linkPattern ?: null, $dateSelector ?: null]);
                    $_SESSION['success'] = "Scraper \"$name\" added.";
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                        $_SESSION['error'] = 'This URL is already configured.';
                    } else {
                        $_SESSION['error'] = 'Failed to add scraper: ' . $e->getMessage();
                    }
                }
            } else {
                $_SESSION['error'] = 'Name and URL are required.';
            }
        }
        header('Location: ?action=settings&tab=script');
        exit;
    
    case 'update_scraper':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = (int)($_POST['scraper_id'] ?? 0);
            $linkPattern = trim($_POST['scraper_link_pattern'] ?? '');
            $dateSelector = trim($_POST['scraper_date_selector'] ?? '');
            if ($id > 0) {
                $pdo->prepare("UPDATE scraper_configs SET link_pattern = ?, date_selector = ? WHERE id = ?")
                    ->execute([$linkPattern ?: null, $dateSelector ?: null, $id]);
                $_SESSION['success'] = 'Scraper updated.';
            }
        }
        header('Location: ?action=settings&tab=script');
        exit;
    
    case 'toggle_scraper':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = (int)($_POST['scraper_id'] ?? 0);
            if ($id > 0) {
                $pdo->prepare("UPDATE scraper_configs SET disabled = NOT disabled WHERE id = ?")->execute([$id]);
                // Also toggle the corresponding feeds rows so entries are hidden from display
                $scStmt = $pdo->prepare("SELECT name, url, disabled FROM scraper_configs WHERE id = ?");
                $scStmt->execute([$id]);
                $sc = $scStmt->fetch();
                if ($sc) {
                    $newDisabled = (int)$sc['disabled'];
                    $pdo->prepare("UPDATE feeds SET disabled = ?, source_type = 'scraper' WHERE (url = ? OR title = ?) AND (source_type = 'scraper' OR source_type IS NULL OR category = 'scraper')")
                        ->execute([$newDisabled, $sc['url'], $sc['name']]);
                }
            }
        }
        header('Location: ?action=settings&tab=script');
        exit;
    
    case 'remove_scraper':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = (int)($_POST['scraper_id'] ?? 0);
            if ($id > 0) {
                $pdo->prepare("DELETE FROM scraper_configs WHERE id = ?")->execute([$id]);
                $_SESSION['success'] = 'Scraper removed.';
            }
        }
        header('Location: ?action=settings&tab=script');
        exit;
    
    case 'hide_scraper_item':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $itemId = (int)($_POST['item_id'] ?? 0);
            if ($itemId > 0) {
                $pdo->prepare("UPDATE feed_items SET hidden = 1 WHERE id = ?")->execute([$itemId]);
                $_SESSION['success'] = 'Entry hidden.';
            }
        }
        header('Location: ?action=scraper');
        exit;
    
    case 'delete_all_scraper_items':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Hard-delete all feed_items from all scraper feeds
            $count = 0;
            try {
                $stmt = $pdo->query("SELECT id FROM feeds WHERE source_type = 'scraper'");
                $scraperFeedIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                if (!empty($scraperFeedIds)) {
                    $ph = implode(',', array_fill(0, count($scraperFeedIds), '?'));
                    $del = $pdo->prepare("DELETE FROM feed_items WHERE feed_id IN ($ph)");
                    $del->execute($scraperFeedIds);
                    $count = $del->rowCount();
                }
            } catch (PDOException $e) {}
            $_SESSION['success'] = "Deleted {$count} scraped entries.";
        }
        header('Location: ?action=settings&tab=script');
        exit;
    
    case 'rescrape_source':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $feedId = (int)($_POST['feed_id'] ?? 0);
            if ($feedId > 0) {
                // Hard-delete all feed_items for this scraper feed so the cronjob re-fetches them
                $del = $pdo->prepare("DELETE FROM feed_items WHERE feed_id = ?");
                $del->execute([$feedId]);
                $count = $del->rowCount();
                // Also delete items from any other feeds with the same name (grouped scraper feeds)
                $nameStmt = $pdo->prepare("SELECT title FROM feeds WHERE id = ? AND source_type = 'scraper'");
                $nameStmt->execute([$feedId]);
                $feedName = $nameStmt->fetchColumn();
                if ($feedName) {
                    $siblingStmt = $pdo->prepare("SELECT id FROM feeds WHERE title = ? AND source_type = 'scraper' AND id != ?");
                    $siblingStmt->execute([$feedName, $feedId]);
                    foreach ($siblingStmt->fetchAll(PDO::FETCH_COLUMN) as $sibId) {
                        $del2 = $pdo->prepare("DELETE FROM feed_items WHERE feed_id = ?");
                        $del2->execute([$sibId]);
                        $count += $del2->rowCount();
                    }
                }
                $_SESSION['success'] = "Deleted {$count} entries for \"{$feedName}\". They will be re-scraped on the next cronjob run.";
            }
        }
        header('Location: ?action=scraper');
        exit;
    
    case 'download_scraper_config':
        // Generate config.php for the scraper script (DB credentials only)
        // The scraper script itself lives in fetcher/scraper/seismo_scraper.php
        // and reads URLs from the scraper_configs table at runtime.
        
        // Parse host and port from DB_HOST (e.g. "localhost:3306")
        $hostParts = explode(':', DB_HOST, 2);
        $cfgHost = $hostParts[0];
        $cfgPort = isset($hostParts[1]) ? (int)$hostParts[1] : 3306;
        
        $configFile = "<?php\n";
        $configFile .= "/**\n";
        $configFile .= " * Scraper configuration — generated by Seismo.\n";
        $configFile .= " * Place this file next to seismo_scraper.php.\n";
        $configFile .= " */\n\n";
        $configFile .= "return [\n";
        $configFile .= "    'db' => [\n";
        $configFile .= "        'host'     => " . var_export($cfgHost, true) . ",\n";
        $configFile .= "        'port'     => " . var_export($cfgPort, true) . ",\n";
        $configFile .= "        'database' => " . var_export(DB_NAME, true) . ",\n";
        $configFile .= "        'username' => " . var_export(DB_USER, true) . ",\n";
        $configFile .= "        'password' => " . var_export(DB_PASS, true) . ",\n";
        $configFile .= "        'charset'  => 'utf8mb4',\n";
        $configFile .= "    ],\n";
        $configFile .= "    'scraping' => [\n";
        $configFile .= "        'min_delay' => 3,\n";
        $configFile .= "        'max_delay' => 8,\n";
        $configFile .= "    ],\n";
        $configFile .= "    'logging' => [\n";
        $configFile .= "        'target' => 'stdout',\n";
        $configFile .= "        'level'  => 'info',\n";
        $configFile .= "    ],\n";
        $configFile .= "];\n";
        
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="config.php"');
        header('Content-Length: ' . strlen($configFile));
        echo $configFile;
        exit;

    case 'save_mail_config':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $mailFields = [
                'mail_imap_mailbox'    => trim($_POST['mail_imap_mailbox'] ?? ''),
                'mail_imap_username'   => trim($_POST['mail_imap_username'] ?? ''),
                'mail_imap_password'   => $_POST['mail_imap_password'] ?? '',
                'mail_max_messages'    => (string)max(1, (int)($_POST['mail_max_messages'] ?? 50)),
                'mail_search_criteria' => trim($_POST['mail_search_criteria'] ?? 'UNSEEN'),
                'mail_mark_seen'       => isset($_POST['mail_mark_seen']) ? '1' : '0',
                'mail_db_table'        => trim($_POST['mail_db_table'] ?? 'fetched_emails') ?: 'fetched_emails',
            ];
            foreach ($mailFields as $key => $value) {
                setMagnituConfig($pdo, $key, $value);
            }
            $_SESSION['success'] = 'Mail configuration saved.';
        }
        header('Location: ' . getBasePath() . '/index.php?action=settings&tab=script');
        exit;

    case 'download_mail_config':
        $hostParts = explode(':', DB_HOST, 2);
        $cfgHost = $hostParts[0];
        $cfgPort = isset($hostParts[1]) ? (int)$hostParts[1] : 3306;

        $imapMailbox  = getMagnituConfig($pdo, 'mail_imap_mailbox') ?: '{imap.example.com:993/imap/ssl}INBOX';
        $imapUsername = getMagnituConfig($pdo, 'mail_imap_username') ?: '';
        $imapPassword = getMagnituConfig($pdo, 'mail_imap_password') ?: '';
        $maxMessages  = getMagnituConfig($pdo, 'mail_max_messages') ?: '50';
        $searchCrit   = getMagnituConfig($pdo, 'mail_search_criteria') ?: 'UNSEEN';
        $markSeen     = getMagnituConfig($pdo, 'mail_mark_seen') ?? '1';
        $dbTable      = getMagnituConfig($pdo, 'mail_db_table') ?: 'fetched_emails';

        $configFile  = "<?php\n";
        $configFile .= "/**\n";
        $configFile .= " * Mail fetcher configuration — generated by Seismo.\n";
        $configFile .= " * Place this file next to fetch_mail.php.\n";
        $configFile .= " */\n\n";
        $configFile .= "return [\n";
        $configFile .= "    'imap' => [\n";
        $configFile .= "        'mailbox'              => " . var_export($imapMailbox, true) . ",\n";
        $configFile .= "        'username'             => " . var_export($imapUsername, true) . ",\n";
        $configFile .= "        'password'             => " . var_export($imapPassword, true) . ",\n";
        $configFile .= "        'max_messages_per_run' => " . var_export((int)$maxMessages, true) . ",\n";
        $configFile .= "        'search_criteria'      => " . var_export($searchCrit, true) . ",\n";
        $configFile .= "        'mark_seen'            => " . ($markSeen === '1' ? 'true' : 'false') . ",\n";
        $configFile .= "    ],\n";
        $configFile .= "    'db' => [\n";
        $configFile .= "        'host'     => " . var_export($cfgHost, true) . ",\n";
        $configFile .= "        'port'     => " . var_export($cfgPort, true) . ",\n";
        $configFile .= "        'database' => " . var_export(DB_NAME, true) . ",\n";
        $configFile .= "        'username' => " . var_export(DB_USER, true) . ",\n";
        $configFile .= "        'password' => " . var_export(DB_PASS, true) . ",\n";
        $configFile .= "        'charset'  => 'utf8mb4',\n";
        $configFile .= "        'table'    => " . var_export($dbTable, true) . ",\n";
        $configFile .= "    ],\n";
        $configFile .= "    'logging' => [\n";
        $configFile .= "        'target' => 'stdout',\n";
        $configFile .= "        'level'  => 'info',\n";
        $configFile .= "    ],\n";
        $configFile .= "];\n";

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="config.php"');
        header('Content-Length: ' . strlen($configFile));
        echo $configFile;
        exit;

    case 'download_mail_script':
        $scriptPath = __DIR__ . '/fetcher/mail/fetch_mail.php';
        if (!file_exists($scriptPath)) {
            $_SESSION['error'] = 'Mail script not found.';
            header('Location: ' . getBasePath() . '/index.php?action=settings&tab=script');
            exit;
        }
        $scriptContent = file_get_contents($scriptPath);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="fetch_mail.php"');
        header('Content-Length: ' . strlen($scriptContent));
        echo $scriptContent;
        exit;

    case 'download_scraper_script':
        $scriptPath = __DIR__ . '/fetcher/scraper/seismo_scraper.php';
        if (!file_exists($scriptPath)) {
            $_SESSION['error'] = 'Scraper script not found.';
            header('Location: ' . getBasePath() . '/index.php?action=settings&tab=script');
            exit;
        }
        $scriptContent = file_get_contents($scriptPath);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="seismo_scraper.php"');
        header('Content-Length: ' . strlen($scriptContent));
        echo $scriptContent;
        exit;
    
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
        header('Content-Type: application/json');
        $stmt = $pdo->query("SELECT DISTINCT tag FROM sender_tags WHERE tag IS NOT NULL AND tag != '' AND tag != 'unclassified' AND removed_at IS NULL ORDER BY tag");
        $tags = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode($tags);
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
        // Show scraped web page entries
        $scraperItems = [];
        $scraperSources = []; // for filter pills
        try {
            // Get all scraper feeds for pills, grouped by name to avoid duplicates
            $scraperFeedsStmt = $pdo->query("
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
            ");
            $allScraperFeeds = $scraperFeedsStmt->fetchAll();
            $allScraperIds = array_column($allScraperFeeds, 'id');
            
            // Group by name for pill display (one pill per scraper name)
            $scraperSources = [];
            $nameToIds = [];
            foreach ($allScraperFeeds as $sf) {
                $n = $sf['name'];
                if (!isset($nameToIds[$n])) {
                    $nameToIds[$n] = [];
                    $scraperSources[] = ['id' => $sf['id'], 'name' => $n];
                }
                $nameToIds[$n][] = $sf['id'];
            }
            
            // Determine active sources from query params
            $sourcesSubmitted = isset($_GET['sources_submitted']);
            if ($sourcesSubmitted) {
                $selectedPillIds = isset($_GET['sources']) ? array_map('intval', (array)$_GET['sources']) : [];
            } else {
                $selectedPillIds = array_column($scraperSources, 'id');
            }
            // Expand pill IDs to include all feed IDs for that name
            $activeScraperIds = [];
            foreach ($scraperSources as $src) {
                if (in_array($src['id'], $selectedPillIds)) {
                    $activeScraperIds = array_merge($activeScraperIds, $nameToIds[$src['name']]);
                }
            }
            $activeScraperIds = array_values(array_unique(array_intersect($activeScraperIds, $allScraperIds)));
            
            if (!empty($activeScraperIds)) {
                $placeholders = implode(',', array_fill(0, count($activeScraperIds), '?'));
                $stmt = $pdo->prepare("
                    SELECT fi.*, f.title as feed_name, f.url as source_url
                    FROM feed_items fi
                    JOIN feeds f ON fi.feed_id = f.id
                    WHERE f.id IN ($placeholders) AND fi.hidden = 0
                    ORDER BY fi.published_date DESC
                    LIMIT 50
                ");
                $stmt->execute($activeScraperIds);
                $scraperItems = $stmt->fetchAll();
            }
        } catch (PDOException $e) {
            // Table might not exist yet
        }
        
        // Load Magnitu scores for scraper items
        $scraperScoreMap = [];
        try {
            $ids = array_column($scraperItems, 'id');
            if (!empty($ids)) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $sStmt = $pdo->prepare("SELECT entry_id, relevance_score, predicted_label, explanation FROM entry_scores WHERE entry_type = 'feed_item' AND entry_id IN ($ph)");
                $sStmt->execute($ids);
                foreach ($sStmt->fetchAll() as $s) {
                    $scraperScoreMap[(int)$s['entry_id']] = $s;
                }
            }
        } catch (PDOException $e) {}
        
        // Magnitu sort preference
        $magnituSortByRelevance = (bool)(getMagnituConfig($pdo, 'sort_by_relevance') ?? 0);
        if ($magnituSortByRelevance && !empty($scraperScoreMap)) {
            usort($scraperItems, function($a, $b) use ($scraperScoreMap) {
                $scoreA = isset($scraperScoreMap[$a['id']]) ? (float)$scraperScoreMap[$a['id']]['relevance_score'] : -1;
                $scoreB = isset($scraperScoreMap[$b['id']]) ? (float)$scraperScoreMap[$b['id']]['relevance_score'] : -1;
                if ($scoreA == $scoreB) return strtotime($b['published_date'] ?? '0') - strtotime($a['published_date'] ?? '0');
                return $scoreB <=> $scoreA;
            });
        }
        
        include 'views/scraper.php';
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

function handleAddFeed($pdo) {
    $url = filter_input(INPUT_POST, 'url', FILTER_SANITIZE_URL);
    $from = $_POST['from'] ?? $_GET['from'] ?? 'feeds';
    $redirectUrl = $from === 'settings' ? getBasePath() . '/index.php?action=settings&tab=basic' : '?action=feeds';
    
    if (!$url) {
        $_SESSION['error'] = 'Please provide a valid URL';
        header('Location: ' . $redirectUrl);
        return;
    }
    
    // Parse feed to validate and get info
    $feed = new \SimplePie\SimplePie();
    $feed->set_feed_url($url);
    $feed->enable_cache(false);
    $feed->init();
    $feed->handle_content_type();
    
    if ($feed->error()) {
        $_SESSION['error'] = 'Error parsing feed: ' . $feed->error();
        header('Location: ' . $redirectUrl);
        return;
    }
    
    // Check if feed already exists
    $stmt = $pdo->prepare("SELECT id FROM feeds WHERE url = ?");
    $stmt->execute([$url]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = 'Feed already exists';
        header('Location: ' . $redirectUrl);
        return;
    }
    
    // Insert feed with default category "unsortiert"
    $stmt = $pdo->prepare("INSERT INTO feeds (url, title, description, link, category) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $url,
        $feed->get_title() ?: 'Untitled Feed',
        $feed->get_description() ?: '',
        $feed->get_link() ?: $url,
        'unsortiert'
    ]);
    
    $feedId = $pdo->lastInsertId();
    
    // Fetch and cache items
    cacheFeedItems($pdo, $feedId, $feed);
    
    $_SESSION['success'] = 'Feed added successfully';
    header('Location: ' . $redirectUrl);
    exit;
}

function handleAddSubstack($pdo) {
    $url = trim(filter_input(INPUT_POST, 'url', FILTER_SANITIZE_URL) ?? '');
    $from = $_POST['from'] ?? 'substack';
    $redirectUrl = $from === 'settings' ? getBasePath() . '/index.php?action=settings&tab=basic' : '?action=substack';

    if (!$url) {
        $_SESSION['error'] = 'Please provide a Substack URL';
        header('Location: ' . $redirectUrl);
        return;
    }
    
    // Normalize URL: accept "name.substack.com" or "https://name.substack.com" etc.
    if (!preg_match('#^https?://#', $url)) {
        $url = 'https://' . $url;
    }
    
    // Strip trailing slashes and /feed suffix if user pasted that
    $url = rtrim($url, '/');
    $url = preg_replace('#/feed$#', '', $url);
    
    // Build the RSS feed URL
    $feedUrl = $url . '/feed';
    
    // Parse feed to validate and get info
    $feed = new \SimplePie\SimplePie();
    $feed->set_feed_url($feedUrl);
    $feed->enable_cache(false);
    $feed->init();
    $feed->handle_content_type();
    
    if ($feed->error()) {
        $_SESSION['error'] = 'Could not load Substack feed. Make sure the URL is correct (e.g. https://example.substack.com).';
        header('Location: ' . $redirectUrl);
        return;
    }
    
    // Check if feed already exists
    $stmt = $pdo->prepare("SELECT id FROM feeds WHERE url = ?");
    $stmt->execute([$feedUrl]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = 'This Substack is already subscribed';
        header('Location: ' . $redirectUrl);
        return;
    }
    
    // Insert feed as substack type — default tag is the newsletter title
    $feedTitle = $feed->get_title() ?: 'Untitled Substack';
    $stmt = $pdo->prepare("INSERT INTO feeds (url, source_type, title, description, link, category) VALUES (?, 'substack', ?, ?, ?, ?)");
    $stmt->execute([
        $feedUrl,
        $feedTitle,
        $feed->get_description() ?: '',
        $feed->get_link() ?: $url,
        $feedTitle
    ]);
    
    $feedId = $pdo->lastInsertId();
    
    // Fetch and cache items
    cacheFeedItems($pdo, $feedId, $feed);
    
    $_SESSION['success'] = 'Substack added successfully: ' . ($feed->get_title() ?: $url);
    header('Location: ' . $redirectUrl);
    exit;
}

function handleDeleteFeed($pdo) {
    $feedId = (int)($_GET['id'] ?? 0);
    $from = $_GET['from'] ?? 'feeds';
    
    $stmt = $pdo->prepare("DELETE FROM feeds WHERE id = ?");
    $stmt->execute([$feedId]);
    
    $_SESSION['success'] = 'Feed deleted successfully';
    $redirectUrl = $from === 'settings' ? getBasePath() . '/index.php?action=settings&tab=basic' : '?action=feeds';
    header('Location: ' . $redirectUrl);
    exit;
}

function handleToggleFeed($pdo) {
    $feedId = (int)($_GET['id'] ?? 0);
    $from = $_GET['from'] ?? 'feeds';
    
    // Get current disabled status
    $stmt = $pdo->prepare("SELECT disabled FROM feeds WHERE id = ?");
    $stmt->execute([$feedId]);
    $feed = $stmt->fetch();
    
    if (!$feed) {
        $_SESSION['error'] = 'Feed not found';
        $redirectUrl = $from === 'settings' ? getBasePath() . '/index.php?action=settings&tab=basic' : '?action=feeds';
        header('Location: ' . $redirectUrl);
        return;
    }
    
    // Toggle disabled status
    $newStatus = $feed['disabled'] ? 0 : 1;
    $updateStmt = $pdo->prepare("UPDATE feeds SET disabled = ? WHERE id = ?");
    $updateStmt->execute([$newStatus, $feedId]);
    
    $statusText = $newStatus ? 'disabled' : 'enabled';
    $_SESSION['success'] = 'Feed ' . $statusText . ' successfully';
    $redirectUrl = $from === 'settings' ? getBasePath() . '/index.php?action=settings&tab=basic' : '?action=feeds';
    header('Location: ' . $redirectUrl);
    exit;
}

function viewFeed($pdo, $feedId) {
    // Get feed info
    $stmt = $pdo->prepare("SELECT * FROM feeds WHERE id = ?");
    $stmt->execute([$feedId]);
    $feed = $stmt->fetch();
    
    if (!$feed) {
        header('Location: ?action=index');
        return;
    }
    
    // Get cached items
    $stmt = $pdo->prepare("SELECT * FROM feed_items WHERE feed_id = ? ORDER BY published_date DESC LIMIT 100");
    $stmt->execute([$feedId]);
    $items = $stmt->fetchAll();
    
    // Check if feed needs refresh
    $needsRefresh = false;
    if ($feed['last_fetched'] === null || 
        (time() - strtotime($feed['last_fetched'])) > CACHE_DURATION) {
        $needsRefresh = true;
    }
    
    include 'views/feed.php';
}

function refreshFeed($pdo, $feedId) {
    $stmt = $pdo->prepare("SELECT * FROM feeds WHERE id = ?");
    $stmt->execute([$feedId]);
    $feed = $stmt->fetch();
    
    if (!$feed) {
        return;
    }
    
    // Parse feed
    $simplepie = new \SimplePie\SimplePie();
    $simplepie->set_feed_url($feed['url']);
    $simplepie->enable_cache(false);
    $simplepie->init();
    $simplepie->handle_content_type();
    
    if (!$simplepie->error()) {
        // Update feed info
        $updateStmt = $pdo->prepare("UPDATE feeds SET title = ?, description = ?, link = ?, last_fetched = NOW() WHERE id = ?");
        $updateStmt->execute([
            $simplepie->get_title() ?: $feed['title'],
            $simplepie->get_description() ?: $feed['description'],
            $simplepie->get_link() ?: $feed['link'],
            $feedId
        ]);
        
        // Cache items
        cacheFeedItems($pdo, $feedId, $simplepie);
    }
}

function cacheFeedItems($pdo, $feedId, $simplepie) {
    $stmt = $pdo->prepare("INSERT INTO feed_items (feed_id, guid, title, link, description, content, author, published_date) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                          ON DUPLICATE KEY UPDATE 
                          title = VALUES(title),
                          link = VALUES(link),
                          description = VALUES(description),
                          content = VALUES(content),
                          author = VALUES(author),
                          published_date = VALUES(published_date),
                          cached_at = NOW()");
    
    foreach ($simplepie->get_items() as $item) {
        $guid = $item->get_id() ?: md5($item->get_link());
        $published = $item->get_date('Y-m-d H:i:s') ?: date('Y-m-d H:i:s');
        
        $stmt->execute([
            $feedId,
            $guid,
            $item->get_title() ?: 'Untitled',
            $item->get_link() ?: '',
            $item->get_description() ?: '',
            $item->get_content() ?: '',
            $item->get_author() ? $item->get_author()->get_name() : '',
            $published
        ]);
    }
}

function refreshAllFeeds($pdo) {
    // Get all feeds
    $stmt = $pdo->query("SELECT id FROM feeds ORDER BY id");
    $feeds = $stmt->fetchAll();
    
    // Refresh each feed
    foreach ($feeds as $feed) {
        refreshFeed($pdo, $feed['id']);
    }
}

function getEmailsForIndex($pdo, $limit = 30, $selectedEmailTags = []) {
    $emails = [];
    
    try {
        // Get disabled or removed sender emails
        $disabledStmt = $pdo->query("SELECT from_email FROM sender_tags WHERE disabled = 1 OR removed_at IS NOT NULL");
        $disabledEmails = $disabledStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Get emails by selected tags if any (only from active senders)
        $taggedEmails = [];
        if (!empty($selectedEmailTags)) {
            $tagPlaceholders = implode(',', array_fill(0, count($selectedEmailTags), '?'));
            $tagStmt = $pdo->prepare("SELECT from_email FROM sender_tags WHERE tag IN ($tagPlaceholders) AND removed_at IS NULL");
            $tagStmt->execute($selectedEmailTags);
            $taggedEmails = $tagStmt->fetchAll(PDO::FETCH_COLUMN);
        }
        
        $tableName = getEmailTableName($pdo);
        
        if ($tableName) {
            // Get column names
            $descStmt = $pdo->query("DESCRIBE `$tableName`");
            $tableColumns = $descStmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Check if this is the cronjob table structure
            $isCronjobTable = in_array('from_addr', $tableColumns) && 
                             (in_array('body_text', $tableColumns) || in_array('body_html', $tableColumns));
            
            if ($isCronjobTable) {
                $selectClause = "
                    id,
                    subject,
                    from_addr as from_email,
                    from_addr as from_name,
                    date_utc as date_received,
                    date_utc as date_sent,
                    body_text as text_body,
                    body_html as html_body,
                    created_at
                ";
                $orderBy = "date_utc DESC";
            } else {
                $selectColumns = [];
                $columnMap = [
                    'id' => 'id',
                    'subject' => 'subject',
                    'from_email' => 'from_email',
                    'from_name' => 'from_name',
                    'created_at' => 'created_at',
                    'date_received' => 'date_received',
                    'date_sent' => 'date_sent',
                    'text_body' => 'text_body',
                    'html_body' => 'html_body'
                ];
                
                foreach ($columnMap as $expected => $actual) {
                    if (in_array($actual, $tableColumns)) {
                        $selectColumns[] = "`$actual` as `$expected`";
                    }
                }
                
                if (empty($selectColumns)) {
                    $selectClause = '*';
                } else {
                    $selectClause = implode(', ', $selectColumns);
                }
                
                $orderBy = 'id DESC';
                foreach (['date_received', 'date_utc', 'date_sent', 'created_at', 'id'] as $orderCol) {
                    if (in_array($orderCol, $tableColumns)) {
                        $orderBy = "`$orderCol` DESC";
                        break;
                    }
                }
            }
            
            // Build WHERE clause to exclude disabled senders and filter by tags
            $whereClause = "1=1";
            $params = [];
            
            // Exclude disabled senders
            if (!empty($disabledEmails)) {
                $placeholders = implode(',', array_fill(0, count($disabledEmails), '?'));
                // Handle both from_email and from_addr columns
                if ($isCronjobTable) {
                    $whereClause = "from_addr NOT IN ($placeholders)";
                } else {
                    $whereClause = "from_email NOT IN ($placeholders)";
                }
                $params = $disabledEmails;
            }
            
            // Filter by email tags if selected
            if (!empty($selectedEmailTags) && !empty($taggedEmails)) {
                $tagPlaceholders = implode(',', array_fill(0, count($taggedEmails), '?'));
                if ($isCronjobTable) {
                    // Always append with AND to avoid malformed "1=1from_addr" when no previous conditions
                    $whereClause .= " AND from_addr IN ($tagPlaceholders)";
                } else {
                    $whereClause .= " AND from_email IN ($tagPlaceholders)";
                }
                $params = array_merge($params, $taggedEmails);
            } elseif (!empty($selectedEmailTags) && empty($taggedEmails)) {
                // No emails with selected tags, return empty
                return [];
            }
            
            $sql = "SELECT $selectClause FROM `$tableName` WHERE $whereClause ORDER BY $orderBy LIMIT $limit";
            if (!empty($params)) {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            } else {
                $stmt = $pdo->query($sql);
            }
            $emails = $stmt->fetchAll();
            
            // Post-process emails to parse from_addr if needed
            foreach ($emails as &$email) {
                if (isset($email['from_email']) && isset($email['from_name']) && 
                    $email['from_email'] === $email['from_name'] && 
                    !empty($email['from_email'])) {
                    $fromAddr = $email['from_email'];
                    if (preg_match('/^"([^"]+)"\s*<(.+)>$/', $fromAddr, $matches)) {
                        $email['from_name'] = $matches[1];
                        $email['from_email'] = $matches[2];
                    } elseif (preg_match('/^(.+)\s*<(.+)>$/', $fromAddr, $matches)) {
                        $email['from_name'] = trim($matches[1]);
                        $email['from_email'] = $matches[2];
                    } elseif (preg_match('/^(.+@.+)$/', $fromAddr)) {
                        $email['from_email'] = $fromAddr;
                        $email['from_name'] = '';
                    }
                }
            }
            unset($email);
            attachSenderTags($pdo, $emails);
        }
    } catch (PDOException $e) {
        // Error getting emails, return empty array
    }
    
    return $emails;
}

function searchEmails($pdo, $query, $limit = 100, $selectedEmailTags = []) {
    $emails = [];
    $searchTerm = '%' . $query . '%';
    
    try {
        // Get disabled or removed sender emails
        $disabledStmt = $pdo->query("SELECT from_email FROM sender_tags WHERE disabled = 1 OR removed_at IS NOT NULL");
        $disabledEmails = $disabledStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Get emails by selected tags if any (only from active senders)
        $taggedEmails = [];
        if (!empty($selectedEmailTags)) {
            $tagPlaceholders = implode(',', array_fill(0, count($selectedEmailTags), '?'));
            $tagStmt = $pdo->prepare("SELECT from_email FROM sender_tags WHERE tag IN ($tagPlaceholders) AND removed_at IS NULL");
            $tagStmt->execute($selectedEmailTags);
            $taggedEmails = $tagStmt->fetchAll(PDO::FETCH_COLUMN);
        }
        
        $tableName = getEmailTableName($pdo);
        
        if ($tableName) {
            // Get column names
            $descStmt = $pdo->query("DESCRIBE `$tableName`");
            $tableColumns = $descStmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Check if this is the cronjob table structure
            $isCronjobTable = in_array('from_addr', $tableColumns) && 
                             (in_array('body_text', $tableColumns) || in_array('body_html', $tableColumns));
            
            if ($isCronjobTable) {
                $selectClause = "
                    id,
                    subject,
                    from_addr as from_email,
                    from_addr as from_name,
                    date_utc as date_received,
                    date_utc as date_sent,
                    body_text as text_body,
                    body_html as html_body,
                    created_at
                ";
                $whereClause = "(subject LIKE ? OR body_text LIKE ? OR body_html LIKE ? OR from_addr LIKE ?)";
                $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
            } else {
                $selectColumns = [];
                $whereColumns = [];
                $columnMap = [
                    'id' => 'id',
                    'subject' => 'subject',
                    'from_email' => 'from_email',
                    'from_name' => 'from_name',
                    'created_at' => 'created_at',
                    'date_received' => 'date_received',
                    'date_sent' => 'date_sent',
                    'text_body' => 'text_body',
                    'html_body' => 'html_body'
                ];
                
                foreach ($columnMap as $expected => $actual) {
                    if (in_array($actual, $tableColumns)) {
                        $selectColumns[] = "`$actual` as `$expected`";
                        if (in_array($actual, ['subject', 'from_email', 'from_name', 'text_body', 'html_body'])) {
                            $whereColumns[] = "`$actual` LIKE ?";
                        }
                    }
                }
                
                if (empty($selectColumns)) {
                    $selectClause = '*';
                    $whereClause = "1=1";
                    $params = [];
                } else {
                    $selectClause = implode(', ', $selectColumns);
                    $whereClause = '(' . implode(' OR ', $whereColumns) . ')';
                    $params = array_fill(0, count($whereColumns), $searchTerm);
                }
            }
            
            // Build WHERE clause to exclude disabled senders and filter by tags
            $whereParts = [$whereClause];
            $whereParams = $params;
            
            // Exclude disabled senders
            if (!empty($disabledEmails)) {
                $placeholders = implode(',', array_fill(0, count($disabledEmails), '?'));
                if ($isCronjobTable) {
                    $whereParts[] = "from_addr NOT IN ($placeholders)";
                } else {
                    $whereParts[] = "from_email NOT IN ($placeholders)";
                }
                $whereParams = array_merge($whereParams, $disabledEmails);
            }
            
            // Filter by email tags if selected
            if (!empty($selectedEmailTags) && !empty($taggedEmails)) {
                $tagPlaceholders = implode(',', array_fill(0, count($taggedEmails), '?'));
                if ($isCronjobTable) {
                    $whereParts[] = "from_addr IN ($tagPlaceholders)";
                } else {
                    $whereParts[] = "from_email IN ($tagPlaceholders)";
                }
                $whereParams = array_merge($whereParams, $taggedEmails);
            } elseif (!empty($selectedEmailTags) && empty($taggedEmails)) {
                // No emails with selected tags, return empty
                return [];
            }
            
            $finalWhereClause = implode(' AND ', $whereParts);
            
            if ($isCronjobTable) {
                $searchOrderBy = "date_utc DESC, id DESC";
            } else {
                $searchOrderBy = 'id DESC';
                foreach (['date_received', 'date_utc', 'date_sent', 'created_at', 'id'] as $sOrdCol) {
                    if (in_array($sOrdCol, $tableColumns)) {
                        $searchOrderBy = "`$sOrdCol` DESC";
                        break;
                    }
                }
            }
            
            $stmt = $pdo->prepare("
                SELECT $selectClause
                FROM `$tableName`
                WHERE $finalWhereClause
                ORDER BY $searchOrderBy
                LIMIT $limit
            ");
            $stmt->execute($whereParams);
            $emails = $stmt->fetchAll();
            
            // Post-process emails to parse from_addr if needed
            foreach ($emails as &$email) {
                if (isset($email['from_email']) && isset($email['from_name']) && 
                    $email['from_email'] === $email['from_name'] && 
                    !empty($email['from_email'])) {
                    $fromAddr = $email['from_email'];
                    if (preg_match('/^"([^"]+)"\s*<(.+)>$/', $fromAddr, $matches)) {
                        $email['from_name'] = $matches[1];
                        $email['from_email'] = $matches[2];
                    } elseif (preg_match('/^(.+)\s*<(.+)>$/', $fromAddr, $matches)) {
                        $email['from_name'] = trim($matches[1]);
                        $email['from_email'] = $matches[2];
                    } elseif (preg_match('/^(.+@.+)$/', $fromAddr)) {
                        $email['from_email'] = $fromAddr;
                        $email['from_name'] = '';
                    }
                }
            }
            unset($email);
            attachSenderTags($pdo, $emails);
        }
    } catch (PDOException $e) {
        // Error searching emails, return empty array
    }
    
    return $emails;
}

function attachSenderTags($pdo, &$emails) {
    if (empty($emails)) return;
    // Build lookup map: from_email → tag
    // Handles both raw "Name" <email> format and plain email format
    try {
        $tagMapStmt = $pdo->query("SELECT from_email, tag FROM sender_tags WHERE removed_at IS NULL AND tag IS NOT NULL AND tag != ''");
        $tagMap = [];
        while ($row = $tagMapStmt->fetch()) {
            $raw = strtolower(trim($row['from_email']));
            $tagMap[$raw] = $row['tag'];
            // Also extract just the email address for matching after from_addr parsing
            if (preg_match('/<([^>]+)>/', $raw, $m)) {
                $tagMap[strtolower(trim($m[1]))] = $row['tag'];
            }
        }
        foreach ($emails as &$email) {
            $addr = strtolower(trim($email['from_email'] ?? ''));
            $email['sender_tag'] = $tagMap[$addr] ?? null;
        }
        unset($email);
    } catch (PDOException $e) {
        // sender_tags table might not exist
    }
}

function searchFeedItems($pdo, $query, $limit = 100, $selectedTags = []) {
    // Prepare search term with wildcards
    $searchTerm = '%' . $query . '%';
    
    // Base SQL: search in title, description, and content (only from enabled feeds)
    $sql = "
        SELECT fi.*, f.title as feed_title, f.category as feed_category 
        FROM feed_items fi
        JOIN feeds f ON fi.feed_id = f.id
        WHERE f.disabled = 0
          AND (fi.title LIKE ? 
           OR fi.description LIKE ? 
           OR fi.content LIKE ?)
    ";
    $params = [$searchTerm, $searchTerm, $searchTerm];
    
    // Optional tag filter
    if (!empty($selectedTags)) {
        $placeholders = implode(',', array_fill(0, count($selectedTags), '?'));
        $sql .= " AND f.category IN ($placeholders)";
        $params = array_merge($params, $selectedTags);
    }
    
    $sql .= " ORDER BY fi.published_date DESC, fi.cached_at DESC LIMIT ?";
    $params[] = $limit;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function highlightSearchTerm($text, $searchQuery) {
    if (empty($searchQuery) || empty($text)) {
        return htmlspecialchars($text);
    }
    
    // Escape the text first for safe HTML output
    $escapedText = htmlspecialchars($text);
    
    // Escape the search query for use in regex (to handle special regex characters)
    $escapedQuery = preg_quote($searchQuery, '/');
    
    // Case-insensitive highlight - replace matches with highlighted version
    $highlighted = preg_replace(
        '/' . $escapedQuery . '/i',
        '<mark class="search-highlight">$0</mark>',
        $escapedText
    );
    
    return $highlighted;
}

function handleUpdateFeedTag($pdo) {
    header('Content-Type: application/json');
    
    $feedId = (int)($_POST['feed_id'] ?? 0);
    $tag = trim($_POST['tag'] ?? '');
    
    if (!$feedId) {
        echo json_encode(['success' => false, 'error' => 'Invalid feed ID']);
        return;
    }
    
    // Validate tag - cannot be empty
    if (empty($tag)) {
        echo json_encode(['success' => false, 'error' => 'Tag cannot be empty']);
        return;
    }
    
    // Update feed tag
    $stmt = $pdo->prepare("UPDATE feeds SET category = ? WHERE id = ?");
    $stmt->execute([$tag, $feedId]);
    
    echo json_encode(['success' => true, 'tag' => $tag]);
}

function handleRenameTag($pdo) {
    header('Content-Type: application/json');
    
    $oldTag = trim($_POST['old_tag'] ?? '');
    $newTag = trim($_POST['new_tag'] ?? '');
    
    if (empty($oldTag) || empty($newTag)) {
        echo json_encode(['success' => false, 'error' => 'Both old and new tag names are required']);
        return;
    }
    
    if ($oldTag === $newTag) {
        echo json_encode(['success' => false, 'error' => 'New tag name must be different from old tag name']);
        return;
    }
    
    // Update RSS feeds only (not substack) with the old tag to the new tag
    $stmt = $pdo->prepare("UPDATE feeds SET category = ? WHERE category = ? AND (source_type = 'rss' OR source_type IS NULL)");
    $stmt->execute([$newTag, $oldTag]);
    
    $affectedRows = $stmt->rowCount();
    
    echo json_encode(['success' => true, 'affected' => $affectedRows]);
}

function handleRenameSubstackTag($pdo) {
    header('Content-Type: application/json');
    
    $oldTag = trim($_POST['old_tag'] ?? '');
    $newTag = trim($_POST['new_tag'] ?? '');
    
    if (empty($oldTag) || empty($newTag)) {
        echo json_encode(['success' => false, 'error' => 'Both old and new tag names are required']);
        return;
    }
    
    if ($oldTag === $newTag) {
        echo json_encode(['success' => false, 'error' => 'New tag name must be different from old tag name']);
        return;
    }
    
    // Update substack feeds only with the old tag to the new tag
    $stmt = $pdo->prepare("UPDATE feeds SET category = ? WHERE category = ? AND source_type = 'substack'");
    $stmt->execute([$newTag, $oldTag]);
    
    $affectedRows = $stmt->rowCount();
    
    echo json_encode(['success' => true, 'affected' => $affectedRows]);
}

function handleRenameEmailTag($pdo) {
    header('Content-Type: application/json');
    
    $oldTag = trim($_POST['old_tag'] ?? '');
    $newTag = trim($_POST['new_tag'] ?? '');
    
    if (empty($oldTag) || empty($newTag)) {
        echo json_encode(['success' => false, 'error' => 'Both old and new tag names are required']);
        return;
    }
    
    if ($oldTag === $newTag) {
        echo json_encode(['success' => false, 'error' => 'New tag name must be different from old tag name']);
        return;
    }
    
    // Update all sender_tags with the old tag to the new tag
    $stmt = $pdo->prepare("UPDATE sender_tags SET tag = ? WHERE tag = ?");
    $stmt->execute([$newTag, $oldTag]);
    
    $affectedRows = $stmt->rowCount();
    
    echo json_encode(['success' => true, 'affected' => $affectedRows]);
}

function handleUpdateSenderTag($pdo) {
    header('Content-Type: application/json');
    
    $fromEmail = trim($_POST['from_email'] ?? '');
    $tag = trim($_POST['tag'] ?? '');
    
    if (empty($fromEmail)) {
        echo json_encode(['success' => false, 'error' => 'Invalid sender email']);
        return;
    }
    
    // Insert or update sender tag (preserve disabled status)
    $stmt = $pdo->prepare("INSERT INTO sender_tags (from_email, tag, disabled) VALUES (?, ?, 0) ON DUPLICATE KEY UPDATE tag = ?");
    $stmt->execute([$fromEmail, $tag, $tag]);
    
    echo json_encode(['success' => true, 'tag' => $tag]);
}

function handleToggleSender($pdo) {
    $fromEmail = trim($_POST['email'] ?? $_GET['email'] ?? '');
    $from = $_POST['from'] ?? $_GET['from'] ?? 'settings';
    
    if (empty($fromEmail)) {
        $_SESSION['error'] = 'Invalid sender email';
        header('Location: ' . getBasePath() . '/index.php?action=settings&tab=script');
        return;
    }
    
    // Get current disabled status
    $stmt = $pdo->prepare("SELECT disabled FROM sender_tags WHERE from_email = ?");
    $stmt->execute([$fromEmail]);
    $result = $stmt->fetch();
    
    if (!$result) {
        // If sender doesn't exist in sender_tags, create it
        $newStatus = 1; // Disable
        $stmt = $pdo->prepare("INSERT INTO sender_tags (from_email, tag, disabled) VALUES (?, 'unclassified', ?)");
        $stmt->execute([$fromEmail, $newStatus]);
    } else {
        // Toggle disabled status
        $newStatus = $result['disabled'] ? 0 : 1;
        $updateStmt = $pdo->prepare("UPDATE sender_tags SET disabled = ? WHERE from_email = ?");
        $updateStmt->execute([$newStatus, $fromEmail]);
    }
    
    $statusText = $newStatus ? 'disabled' : 'enabled';
    $_SESSION['success'] = 'Sender ' . $statusText . ' successfully';
    header('Location: ' . getBasePath() . '/index.php?action=settings&tab=script');
    exit;
}

function handleDeleteSender($pdo) {
    $fromEmail = trim($_POST['email'] ?? $_GET['email'] ?? '');
    $from = $_POST['from'] ?? $_GET['from'] ?? 'settings';
    
    if (empty($fromEmail)) {
        $_SESSION['error'] = 'Invalid sender email';
        header('Location: ' . getBasePath() . '/index.php?action=settings&tab=script');
        return;
    }
    
    // Mark sender as removed (don't delete — keeps record so auto-tag won't re-add them).
    // They reappear only when a new email arrives after the removal timestamp.
    $stmt = $pdo->prepare("UPDATE sender_tags SET removed_at = NOW(), tag = 'unclassified' WHERE from_email = ?");
    $stmt->execute([$fromEmail]);
    
    $_SESSION['success'] = "Sender removed from Seismo.\nFuture emails from this address will be tagged as \"unsortiert\" until you reassign them.\nTo stop receiving these emails, you need to manually unsubscribe from the sender's press releases.";
    header('Location: ' . getBasePath() . '/index.php?action=settings&tab=script');
    exit;
}

function handleDeleteEmail($pdo) {
    $emailId = (int)($_GET['id'] ?? 0);
    $confirm = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';
    
    if (!$emailId) {
        $_SESSION['error'] = 'Invalid email ID';
        header('Location: ?action=mail');
        return;
    }
    
    // Require confirmation parameter (prevents accidental deletions from direct URL access)
    if (!$confirm) {
        $_SESSION['error'] = 'Deletion requires confirmation';
        header('Location: ?action=mail');
        return;
    }
    
    try {
        $tableName = getEmailTableName($pdo);
        
        if (!$tableName) {
            $_SESSION['error'] = 'Email table not found';
            header('Location: ?action=mail');
            return;
        }
        
        // Verify email exists before deleting
        $checkStmt = $pdo->prepare("SELECT id FROM `$tableName` WHERE id = ?");
        $checkStmt->execute([$emailId]);
        if (!$checkStmt->fetch()) {
            $_SESSION['error'] = 'Email not found';
            header('Location: ?action=mail');
            return;
        }
        
        // Safe delete using prepared statement
        $deleteStmt = $pdo->prepare("DELETE FROM `$tableName` WHERE id = ?");
        $deleteStmt->execute([$emailId]);
        
        $_SESSION['success'] = 'Email deleted successfully';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error deleting email: ' . $e->getMessage();
    }
    
    header('Location: ?action=mail');
    exit;
}

function refreshEmails($pdo) {
    try {
        $tableName = getEmailTableName($pdo);
        
        if (!$tableName) {
            $_SESSION['error'] = "No emails table found.";
            return;
        }
        
        // Get count of emails from the actual table
        try {
            $countStmt = $pdo->query("SELECT COUNT(*) as count FROM `$tableName`");
            $countResult = $countStmt->fetch();
            $emailCount = $countResult['count'] ?? 0;
            
            // Get column names to see the structure
            $descStmt = $pdo->query("DESCRIBE `$tableName`");
            $columns = $descStmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Store info in session
            $_SESSION['email_refresh_count'] = $emailCount;
            $_SESSION['email_table_name'] = $tableName;
            $_SESSION['email_table_columns'] = $columns;
            
            if ($emailCount > 0) {
                $_SESSION['success'] = "Emails refreshed successfully. Found $emailCount email(s) in table '$tableName'.";
            } else {
                $_SESSION['success'] = "Emails refreshed. Table '$tableName' exists but contains 0 emails. Available tables: $tableNames";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error querying table '$tableName': " . $e->getMessage() . ". Available tables: $tableNames";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error refreshing emails: ' . $e->getMessage();
    }
}

