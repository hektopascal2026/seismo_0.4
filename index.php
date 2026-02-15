<?php
session_start();

require_once 'config.php';
require_once 'vendor/autoload.php';

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
        $tagsStmt = $pdo->query("SELECT DISTINCT category FROM feeds WHERE category IS NOT NULL AND category != '' AND disabled = 0 AND (source_type = 'rss' OR source_type IS NULL) ORDER BY category");
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
            $selectedLexSources = ['eu', 'ch', 'de', 'ch_bger', 'ch_bge']; // all active by default
        }
        
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
        
        // Fetch Lex items (EU + CH legislation), filtered by selected lex sources
        $lexItems = [];
        try {
            if (!empty($selectedLexSources)) {
                $lexPlaceholders = implode(',', array_fill(0, count($selectedLexSources), '?'));
                $lexStmt = $pdo->prepare("
                    SELECT * FROM lex_items
                    WHERE source IN ($lexPlaceholders)
                    ORDER BY document_date DESC
                    LIMIT 30
                ");
                $lexStmt->execute($selectedLexSources);
                $lexItems = $lexStmt->fetchAll();
            } elseif (!$tagsSubmitted) {
                // First visit: show all
                $lexStmt = $pdo->query("
                    SELECT * FROM lex_items
                    ORDER BY document_date DESC
                    LIMIT 30
                ");
                $lexItems = $lexStmt->fetchAll();
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
        // Magnitu page: show entries labeled as investigation_lead and important
        $investigationItems = [];
        $importantItems = [];
        
        try {
            // Get all scored entries with investigation_lead or important labels
            $scoredStmt = $pdo->query("
                SELECT entry_type, entry_id, relevance_score, predicted_label, explanation, score_source, model_version
                FROM entry_scores 
                WHERE predicted_label IN ('investigation_lead', 'important')
                ORDER BY predicted_label ASC, relevance_score DESC
                LIMIT 500
            ");
            $scoredEntries = $scoredStmt->fetchAll();
            
            // Resolve each scored entry to its full data from the source table
            foreach ($scoredEntries as $scored) {
                $entryData = null;
                $entryType = null;
                $dateValue = null;
                
                if ($scored['entry_type'] === 'feed_item') {
                    $stmt = $pdo->prepare("
                        SELECT fi.*, f.title as feed_title, f.category as feed_category, f.source_type
                        FROM feed_items fi
                        JOIN feeds f ON fi.feed_id = f.id
                        WHERE fi.id = ?
                    ");
                    $stmt->execute([$scored['entry_id']]);
                    $entryData = $stmt->fetch();
                    if ($entryData) {
                        $entryType = ($entryData['source_type'] === 'substack') ? 'substack' : 'feed';
                        $dateValue = $entryData['published_date'] ?? $entryData['cached_at'] ?? null;
                    }
                } elseif ($scored['entry_type'] === 'email') {
                    // Resolve email table dynamically (fetched_emails or emails)
                    if (!isset($emailTableName)) {
                        $emailTableName = 'emails';
                        $eTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                        foreach ($eTables as $eT) {
                            if (strtolower($eT) === 'fetched_emails') { $emailTableName = $eT; break; }
                        }
                    }
                    $stmt = $pdo->prepare("SELECT * FROM `$emailTableName` WHERE id = ?");
                    $stmt->execute([$scored['entry_id']]);
                    $entryData = $stmt->fetch();
                    if ($entryData) {
                        $entryType = 'email';
                        $dateValue = $entryData['date_received'] ?? $entryData['date_utc'] ?? $entryData['created_at'] ?? null;
                    }
                } elseif ($scored['entry_type'] === 'lex_item') {
                    $stmt = $pdo->prepare("SELECT * FROM lex_items WHERE id = ?");
                    $stmt->execute([$scored['entry_id']]);
                    $entryData = $stmt->fetch();
                    if ($entryData) {
                        $entryType = 'lex';
                        $dateValue = $entryData['document_date'] ?? $entryData['created_at'] ?? null;
                    }
                }
                
                if (!$entryData) continue;
                
                $item = [
                    'type' => $entryType,
                    'date' => $dateValue ? strtotime($dateValue) : 0,
                    'data' => $entryData,
                    'score' => $scored,
                ];
                
                if ($scored['predicted_label'] === 'investigation_lead') {
                    $investigationItems[] = $item;
                } else {
                    $importantItems[] = $item;
                }
            }
            
            // Sort each group chronologically (newest first)
            usort($investigationItems, function($a, $b) { return $b['date'] - $a['date']; });
            usort($importantItems, function($a, $b) { return $b['date'] - $a['date']; });
            
        } catch (PDOException $e) {
            // entry_scores table might not exist yet
        }
        
        // Stats
        $magnituAlertThreshold = (float)(getMagnituConfig($pdo, 'alert_threshold') ?? 0.75);
        $totalScored = 0;
        try {
            $totalScored = (int)$pdo->query("SELECT COUNT(*) FROM entry_scores")->fetchColumn();
        } catch (PDOException $e) {}
        $magnituModelName = getMagnituConfig($pdo, 'model_name');
        $magnituModelVersion = getMagnituConfig($pdo, 'model_version');
        
        include 'views/magnitu.php';
        break;

        case 'ai_view_unified':
    // 1. Fetch RSS Items
    $latestItemsStmt = $pdo->query("
        SELECT fi.*, f.title as feed_title, f.category as feed_category 
        FROM feed_items fi
        JOIN feeds f ON fi.feed_id = f.id
        WHERE f.disabled = 0
        ORDER BY fi.published_date DESC
        LIMIT 50
    ");
    $latestItems = $latestItemsStmt->fetchAll();

    // 2. Fetch Emails
    $emails = getEmailsForIndex($pdo, 50, []);

    // 3. Fetch Lex items
    $lexItems = [];
    try {
        $lexItemsStmt = $pdo->query("
            SELECT *
            FROM lex_items
            ORDER BY document_date DESC
            LIMIT 50
        ");
        $lexItems = $lexItemsStmt->fetchAll();
    } catch (PDOException $e) {
        // lex_items table might not exist yet
        $lexItems = [];
    }

    // 4. Merge into unified list
    $allItems = [];
    foreach ($latestItems as $item) {
        $date = $item['published_date'] ?? $item['cached_at'] ?? 0;
        $allItems[] = [
            'source'  => $item['feed_title'],
            'date'    => strtotime($date),
            'title'   => $item['title'],
            'content' => strip_tags($item['content'] ?: $item['description']),
            'link'    => $item['link']
        ];
    }

    foreach ($emails as $email) {
        $date = $email['date_received'] ?? $email['date_utc'] ?? $email['created_at'] ?? 0;
        $from = ($email['from_name'] ?: $email['from_email']) ?: 'Unknown';
        $allItems[] = [
            'source'  => "EMAIL: $from",
            'date'    => strtotime($date),
            'title'   => $email['subject'] ?: '(No Subject)',
            'content' => strip_tags($email['text_body'] ?: $email['html_body'] ?: ''),
            'link'    => '#'
        ];
    }

    foreach ($lexItems as $lexItem) {
        $date = $lexItem['document_date'] ?? $lexItem['created_at'] ?? 0;
        $source = strtoupper((string)($lexItem['source'] ?? 'eu'));
        $docType = trim((string)($lexItem['document_type'] ?? 'Legislation'));
        $celex = trim((string)($lexItem['celex'] ?? ''));
        $workUri = trim((string)($lexItem['work_uri'] ?? ''));

        $contentParts = [];
        if ($docType !== '') $contentParts[] = 'Type: ' . $docType;
        if ($celex !== '') $contentParts[] = 'ID: ' . $celex;
        if ($workUri !== '') $contentParts[] = 'URI: ' . $workUri;

        $allItems[] = [
            'source'  => 'LEX: ' . $source,
            'date'    => $date ? strtotime($date) : 0,
            'title'   => $lexItem['title'] ?: '(No Title)',
            'content' => !empty($contentParts) ? implode("\n", $contentParts) : '',
            'link'    => $lexItem['eurlex_url'] ?: '#'
        ];
    }

    // 5. Sort chronologically (Newest First)
    usort($allItems, function($a, $b) {
        return $b['date'] - $a['date'];
    });

    include 'views/ai_view_unified.php';
    break;

    case 'ai_view':
        // Find the right table name (matches your system's logic)
        $allTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $tableName = 'fetched_emails'; // default
        foreach ($allTables as $table) {
            if (strtolower($table) === 'fetched_emails') { $tableName = $table; break; }
            if (strtolower($table) === 'emails') { $tableName = $table; }
        }

        // Fetch emails
        $stmt = $pdo->query("SELECT * FROM `$tableName` ORDER BY id DESC LIMIT 100");
        $emails = $stmt->fetchAll();

        // Load the specialized AI view
        include 'views/ai_view.php';
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
        
        // Get table name from session if available (set by refresh function)
        $tableName = $_SESSION['email_table_name'] ?? 'emails';
        
        try {
            // Check what tables exist
            $allTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            
            // Try to find the emails table (case-insensitive)
            // Priority: fetched_emails (cronjob default), then emails, then any table with mail/email
            $foundTable = null;
            foreach ($allTables as $table) {
                if (strtolower($table) === 'fetched_emails') {
                    $foundTable = $table;
                    break;
                }
            }
            
            if (!$foundTable) {
                foreach ($allTables as $table) {
                    if (strtolower($table) === 'emails' || strtolower($table) === 'email') {
                        $foundTable = $table;
                        break;
                    }
                }
            }
            
            // If not found, look for any table with 'mail' or 'email' in the name
            if (!$foundTable) {
                foreach ($allTables as $table) {
                    if (stripos($table, 'mail') !== false || stripos($table, 'email') !== false) {
                        $foundTable = $table;
                        break;
                    }
                }
            }
            
            if (!$foundTable) {
                $mailTableError = "No emails table found. Available tables: " . implode(', ', $allTables);
            } else {
                $tableName = $foundTable; // Use the actual table name (case-sensitive)
                
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
                        $orderBy = "created_at DESC";
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
                        
                        // Determine ORDER BY column
                        $orderBy = 'id DESC'; // Default
                        foreach (['created_at', 'date_utc', 'date_received', 'date_sent', 'id'] as $orderCol) {
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
            // Find email table
            $allTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            $emailTableName = null;
            
            foreach ($allTables as $table) {
                if (strtolower($table) === 'fetched_emails') {
                    $emailTableName = $table;
                    break;
                }
            }
            
            if (!$emailTableName) {
                foreach ($allTables as $table) {
                    if (strtolower($table) === 'emails' || strtolower($table) === 'email') {
                        $emailTableName = $table;
                        break;
                    }
                }
            }
            
            if (!$emailTableName) {
                foreach ($allTables as $table) {
                    if (stripos($table, 'mail') !== false || stripos($table, 'email') !== false) {
                        $emailTableName = $table;
                        break;
                    }
                }
            }
            
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
    
    case 'save_magnitu_config':
        // Save Magnitu settings from settings page
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $threshold = max(0.0, min(1.0, (float)($_POST['alert_threshold'] ?? 0.75)));
            $sortByRelevance = isset($_POST['sort_by_relevance']) ? '1' : '0';
            
            setMagnituConfig($pdo, 'alert_threshold', (string)$threshold);
            setMagnituConfig($pdo, 'sort_by_relevance', $sortByRelevance);
            
            $_SESSION['success'] = 'Magnitu settings saved.';
        }
        header('Location: ' . getBasePath() . '/index.php?action=settings&tab=magnitu');
        exit;
    
    case 'regenerate_magnitu_key':
        // Generate a new API key
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $newKey = bin2hex(random_bytes(16));
            setMagnituConfig($pdo, 'api_key', $newKey);
            $_SESSION['success'] = 'New Magnitu API key generated.';
        }
        header('Location: ' . getBasePath() . '/index.php?action=settings&tab=magnitu');
        exit;
    
    case 'clear_magnitu_scores':
        // Clear all scores (reset)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $pdo->exec("DELETE FROM entry_scores");
            setMagnituConfig($pdo, 'recipe_json', '');
            setMagnituConfig($pdo, 'recipe_version', '0');
            setMagnituConfig($pdo, 'last_sync_at', '');
            $_SESSION['success'] = 'All Magnitu scores and recipe cleared.';
        }
        header('Location: ' . getBasePath() . '/index.php?action=settings&tab=magnitu');
        exit;
        
    case 'api_email_tags':
        header('Content-Type: application/json');
        $stmt = $pdo->query("SELECT DISTINCT tag FROM sender_tags WHERE tag IS NOT NULL AND tag != '' AND tag != 'unclassified' AND removed_at IS NULL ORDER BY tag");
        $tags = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode($tags);
        break;
        
    case 'lex':
        // Show Lex entries page — EU, CH + DE legislation
        $lexItems = [];
        $lastLexRefreshDate = null;
        
        // Determine active sources from query params (default: all active)
        $sourcesSubmitted = isset($_GET['sources_submitted']);
        if ($sourcesSubmitted) {
            $activeSources = isset($_GET['sources']) ? (array)$_GET['sources'] : [];
        } else {
            $activeSources = ['eu', 'ch', 'de']; // All active by default
        }
        
        try {
            if (!empty($activeSources)) {
                $placeholders = implode(',', array_fill(0, count($activeSources), '?'));
                $stmt = $pdo->prepare("SELECT * FROM lex_items WHERE source IN ($placeholders) ORDER BY document_date DESC LIMIT 50");
                $stmt->execute($activeSources);
                $lexItems = $stmt->fetchAll();
            }
            
            // Get last refresh dates per source
            $lastRefreshEuStmt = $pdo->query("SELECT MAX(fetched_at) as last_refresh FROM lex_items WHERE source = 'eu'");
            $lastRefreshEuRow = $lastRefreshEuStmt->fetch();
            $lastLexRefreshDateEu = ($lastRefreshEuRow && $lastRefreshEuRow['last_refresh']) 
                ? date('d.m.Y H:i', strtotime($lastRefreshEuRow['last_refresh'])) : null;
            
            $lastRefreshChStmt = $pdo->query("SELECT MAX(fetched_at) as last_refresh FROM lex_items WHERE source = 'ch'");
            $lastRefreshChRow = $lastRefreshChStmt->fetch();
            $lastLexRefreshDateCh = ($lastRefreshChRow && $lastRefreshChRow['last_refresh']) 
                ? date('d.m.Y H:i', strtotime($lastRefreshChRow['last_refresh'])) : null;
            
            $lastRefreshDeStmt = $pdo->query("SELECT MAX(fetched_at) as last_refresh FROM lex_items WHERE source = 'de'");
            $lastRefreshDeRow = $lastRefreshDeStmt->fetch();
            $lastLexRefreshDateDe = ($lastRefreshDeRow && $lastRefreshDeRow['last_refresh']) 
                ? date('d.m.Y H:i', strtotime($lastRefreshDeRow['last_refresh'])) : null;
            
            // Combined last refresh
            $lastRefreshStmt = $pdo->query("SELECT MAX(fetched_at) as last_refresh FROM lex_items");
            $lastRefreshRow = $lastRefreshStmt->fetch();
            if ($lastRefreshRow && $lastRefreshRow['last_refresh']) {
                $lastLexRefreshDate = date('d.m.Y H:i', strtotime($lastRefreshRow['last_refresh']));
            }
        } catch (PDOException $e) {
            // Table might not exist yet on first load
        }
        
        include 'views/lex.php';
        break;
    
    case 'refresh_all_lex':
        // Refresh Lex items from both EU CELLAR and Fedlex SPARQL endpoints
        $lexCfg = getLexConfig();
        $messages = [];
        $errors = [];
        
        if ($lexCfg['eu']['enabled'] ?? true) {
            try {
                $countEu = refreshLexItems($pdo);
                $messages[] = "🇪🇺 $countEu items from EUR-Lex";
            } catch (Exception $e) {
                $errors[] = '🇪🇺 EU: ' . $e->getMessage();
            }
        } else {
            $messages[] = '🇪🇺 EU skipped (disabled)';
        }
        
        if ($lexCfg['ch']['enabled'] ?? true) {
            try {
                $countCh = refreshFedlexItems($pdo);
                $messages[] = "🇨🇭 $countCh items from Fedlex";
            } catch (Exception $e) {
                $errors[] = '🇨🇭 CH: ' . $e->getMessage();
            }
        } else {
            $messages[] = '🇨🇭 CH skipped (disabled)';
        }
        
        if ($lexCfg['de']['enabled'] ?? true) {
            try {
                $countDe = refreshRechtBundItems($pdo);
                $messages[] = "🇩🇪 $countDe items from recht.bund.de";
            } catch (Exception $e) {
                $errors[] = '🇩🇪 DE: ' . $e->getMessage();
            }
        } else {
            $messages[] = '🇩🇪 DE skipped (disabled)';
        }
        
        if (!empty($messages)) {
            $_SESSION['success'] = 'Lex refreshed: ' . implode(', ', $messages) . '.';
        }
        if (!empty($errors)) {
            $_SESSION['error'] = 'Errors: ' . implode(' | ', $errors);
        }
        
        header('Location: ?action=lex');
        exit;
    
    case 'jus':
        // Show JUS entries page — Swiss case law from entscheidsuche.ch
        $jusItems = [];
        
        // Determine active sources from query params (default: all active)
        $sourcesSubmitted = isset($_GET['sources_submitted']);
        if ($sourcesSubmitted) {
            $activeJusSources = isset($_GET['sources']) ? (array)$_GET['sources'] : [];
        } else {
            $activeJusSources = ['ch_bger', 'ch_bge']; // All active by default
        }
        
        try {
            if (!empty($activeJusSources)) {
                $placeholders = implode(',', array_fill(0, count($activeJusSources), '?'));
                $stmt = $pdo->prepare("SELECT * FROM lex_items WHERE source IN ($placeholders) ORDER BY document_date DESC LIMIT 50");
                $stmt->execute($activeJusSources);
                $jusItems = $stmt->fetchAll();
            }
            
            // Get last refresh dates per source
            $lastRefreshBgerStmt = $pdo->query("SELECT MAX(fetched_at) as last_refresh FROM lex_items WHERE source = 'ch_bger'");
            $lastRefreshBgerRow = $lastRefreshBgerStmt->fetch();
            $lastJusRefreshDateBger = ($lastRefreshBgerRow && $lastRefreshBgerRow['last_refresh']) 
                ? date('d.m.Y H:i', strtotime($lastRefreshBgerRow['last_refresh'])) : null;
            
            $lastRefreshBgeStmt = $pdo->query("SELECT MAX(fetched_at) as last_refresh FROM lex_items WHERE source = 'ch_bge'");
            $lastRefreshBgeRow = $lastRefreshBgeStmt->fetch();
            $lastJusRefreshDateBge = ($lastRefreshBgeRow && $lastRefreshBgeRow['last_refresh']) 
                ? date('d.m.Y H:i', strtotime($lastRefreshBgeRow['last_refresh'])) : null;
        } catch (PDOException $e) {
            // Table might not exist yet on first load
        }
        
        include 'views/jus.php';
        break;
    
    case 'refresh_all_jus':
        // Refresh JUS items from entscheidsuche.ch (BGer + BGE)
        $lexCfg = getLexConfig();
        $messages = [];
        $errors = [];
        
        if ($lexCfg['ch_bger']['enabled'] ?? false) {
            try {
                $countBger = refreshJusItems($pdo, 'CH_BGer');
                $messages[] = "⚖️ $countBger items from BGer";
            } catch (Exception $e) {
                $errors[] = '⚖️ BGer: ' . $e->getMessage();
            }
        } else {
            $messages[] = '⚖️ BGer skipped (disabled)';
        }
        
        if ($lexCfg['ch_bge']['enabled'] ?? false) {
            try {
                $countBge = refreshJusItems($pdo, 'CH_BGE');
                $messages[] = "⚖️ $countBge items from BGE";
            } catch (Exception $e) {
                $errors[] = '⚖️ BGE: ' . $e->getMessage();
            }
        } else {
            $messages[] = '⚖️ BGE skipped (disabled)';
        }
        
        if (!empty($messages)) {
            $_SESSION['success'] = 'JUS refreshed: ' . implode(', ', $messages) . '.';
        }
        if (!empty($errors)) {
            $_SESSION['error'] = 'Errors: ' . implode(' | ', $errors);
        }
        
        header('Location: ?action=jus');
        exit;
    
    case 'save_lex_config':
        // Save Lex config from the settings form
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $config = getLexConfig();
            
            // EU settings
            $config['eu']['enabled']        = isset($_POST['eu_enabled']);
            $config['eu']['language']       = trim($_POST['eu_language'] ?? 'ENG');
            $config['eu']['lookback_days']  = max(1, (int)($_POST['eu_lookback_days'] ?? 90));
            $config['eu']['limit']          = max(1, (int)($_POST['eu_limit'] ?? 100));
            $config['eu']['document_class'] = trim($_POST['eu_document_class'] ?? 'cdm:legislation_secondary');
            $config['eu']['notes']          = trim($_POST['eu_notes'] ?? '');
            
            // CH settings
            $config['ch']['enabled']       = isset($_POST['ch_enabled']);
            $config['ch']['language']      = trim($_POST['ch_language'] ?? 'DEU');
            $config['ch']['lookback_days'] = max(1, (int)($_POST['ch_lookback_days'] ?? 90));
            $config['ch']['limit']         = max(1, (int)($_POST['ch_limit'] ?? 100));
            $config['ch']['notes']         = trim($_POST['ch_notes'] ?? '');
            
            // CH resource types — parse comma-separated IDs
            $rtRaw = trim($_POST['ch_resource_types'] ?? '');
            if (!empty($rtRaw)) {
                $ids = array_filter(array_map('intval', preg_split('/[\s,]+/', $rtRaw)));
                $existingTypes = [];
                foreach (($config['ch']['resource_types'] ?? []) as $rt) {
                    $existingTypes[(int)$rt['id']] = $rt['label'] ?? '';
                }
                $newTypes = [];
                foreach ($ids as $id) {
                    $newTypes[] = ['id' => $id, 'label' => $existingTypes[$id] ?? 'Type ' . $id];
                }
                $config['ch']['resource_types'] = $newTypes;
            }
            
            // DE settings
            $config['de']['enabled']       = isset($_POST['de_enabled']);
            $config['de']['lookback_days'] = max(1, (int)($_POST['de_lookback_days'] ?? 90));
            $config['de']['limit']         = max(1, (int)($_POST['de_limit'] ?? 100));
            $config['de']['notes']         = trim($_POST['de_notes'] ?? '');
            
            // JUS: CH_BGer settings
            $config['ch_bger']['enabled']       = isset($_POST['ch_bger_enabled']);
            $config['ch_bger']['lookback_days'] = max(1, (int)($_POST['ch_bger_lookback_days'] ?? 90));
            $config['ch_bger']['limit']         = max(1, (int)($_POST['ch_bger_limit'] ?? 100));
            $config['ch_bger']['notes']         = trim($_POST['ch_bger_notes'] ?? '');
            
            // JUS: CH_BGE settings
            $config['ch_bge']['enabled']       = isset($_POST['ch_bge_enabled']);
            $config['ch_bge']['lookback_days'] = max(1, (int)($_POST['ch_bge_lookback_days'] ?? 90));
            $config['ch_bge']['limit']         = max(1, (int)($_POST['ch_bge_limit'] ?? 50));
            $config['ch_bge']['notes']         = trim($_POST['ch_bge_notes'] ?? '');
            
            if (saveLexConfig($config)) {
                $_SESSION['success'] = 'Lex configuration saved.';
            } else {
                $_SESSION['error'] = 'Failed to save Lex configuration.';
            }
        }
        header('Location: ' . getBasePath() . '/index.php?action=settings&tab=lex');
        exit;
    
    case 'upload_lex_config':
        // Upload a JSON config file to replace the current config
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['lex_config_file'])) {
            $file = $_FILES['lex_config_file'];
            if ($file['error'] === UPLOAD_ERR_OK && $file['size'] > 0) {
                $content = file_get_contents($file['tmp_name']);
                $parsed = json_decode($content, true);
                if ($parsed !== null && (isset($parsed['eu']) || isset($parsed['ch']) || isset($parsed['de']) || isset($parsed['ch_bger']) || isset($parsed['ch_bge']))) {
                    if (saveLexConfig($parsed)) {
                        $_SESSION['success'] = 'Lex config file uploaded and applied.';
                    } else {
                        $_SESSION['error'] = 'Failed to write uploaded config.';
                    }
                } else {
                    $_SESSION['error'] = 'Invalid JSON config file. Must contain "eu", "ch", and/or "de" keys.';
                }
            } else {
                $_SESSION['error'] = 'No file uploaded or upload error.';
            }
        }
        header('Location: ' . getBasePath() . '/index.php?action=settings&tab=lex');
        exit;
    
    case 'download_lex_config':
        // Download the current config as a JSON file
        $config = getLexConfig();
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="lex_config.json"');
        echo json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    
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
            
            // Find the correct email table (fetched_emails or emails)
            $emailTable = 'emails';
            $allTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($allTables as $t) {
                if (strtolower($t) === 'fetched_emails') { $emailTable = $t; break; }
                if (strtolower($t) === 'emails') { $emailTable = $t; }
            }
            $stats['emails'] = $pdo->query("SELECT COUNT(*) FROM `$emailTable`")->fetchColumn();
            
            $stats['lex_eu'] = $pdo->query("SELECT COUNT(*) FROM lex_items WHERE source = 'eu'")->fetchColumn();
            $stats['lex_ch'] = $pdo->query("SELECT COUNT(*) FROM lex_items WHERE source = 'ch'")->fetchColumn();
            $stats['lex_de'] = $pdo->query("SELECT COUNT(*) FROM lex_items WHERE source = 'de'")->fetchColumn();
            $stats['jus_bger'] = $pdo->query("SELECT COUNT(*) FROM lex_items WHERE source = 'ch_bger'")->fetchColumn();
            $stats['jus_bge'] = $pdo->query("SELECT COUNT(*) FROM lex_items WHERE source = 'ch_bge'")->fetchColumn();
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
        // GET /index.php?action=magnitu_entries&since=2026-01-01T00:00:00Z&type=all
        // Returns entries for Magnitu to fetch and label
        header('Content-Type: application/json');
        if (!validateMagnituApiKey($pdo)) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid API key']);
            break;
        }
        
        $since = $_GET['since'] ?? null;
        $type = $_GET['type'] ?? 'all';
        $limit = min((int)($_GET['limit'] ?? 500), 2000);
        
        $entries = [];
        
        // Feed items (RSS + Substack)
        if ($type === 'all' || $type === 'feed_item') {
            $sql = "SELECT fi.id, fi.title, fi.description, fi.content, fi.link, fi.author,
                           fi.published_date, f.title as feed_title, f.category as feed_category,
                           f.source_type
                    FROM feed_items fi
                    JOIN feeds f ON fi.feed_id = f.id
                    WHERE f.disabled = 0";
            $params = [];
            if ($since) {
                $sql .= " AND fi.published_date >= ?";
                $params[] = $since;
            }
            $sql .= " ORDER BY fi.published_date DESC LIMIT " . (int)$limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            foreach ($stmt->fetchAll() as $row) {
                $entries[] = [
                    'entry_type' => 'feed_item',
                    'entry_id' => (int)$row['id'],
                    'title' => $row['title'],
                    'description' => strip_tags($row['description'] ?? ''),
                    'content' => strip_tags($row['content'] ?? ''),
                    'link' => $row['link'],
                    'author' => $row['author'],
                    'published_date' => $row['published_date'],
                    'source_name' => $row['feed_title'],
                    'source_category' => $row['feed_category'],
                    'source_type' => $row['source_type'] ?? 'rss',
                ];
            }
        }
        
        // Emails
        if ($type === 'all' || $type === 'email') {
            $emailTable = 'emails';
            try {
                $allTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($allTables as $t) {
                    if (strtolower($t) === 'fetched_emails') { $emailTable = $t; break; }
                }
            } catch (PDOException $e) {}
            
            try {
                $cols = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$emailTable'")->fetchAll(PDO::FETCH_COLUMN);
                $fromEmailCol = in_array('from_email', $cols) ? 'from_email' : (in_array('from_addr', $cols) ? 'from_addr' : 'from_email');
                $fromNameCol = in_array('from_name', $cols) ? 'from_name' : "''" ;
                $textBodyCol = in_array('text_body', $cols) ? 'text_body' : (in_array('body_text', $cols) ? 'body_text' : 'text_body');
                $htmlBodyCol = in_array('html_body', $cols) ? 'html_body' : (in_array('body_html', $cols) ? 'body_html' : 'html_body');
                $dateCol = in_array('date_received', $cols) ? 'date_received' : (in_array('date_utc', $cols) ? 'date_utc' : 'created_at');
                
                $sql = "SELECT e.id, e.subject, e.$fromEmailCol as from_email, $fromNameCol as from_name,
                               e.$textBodyCol as text_body, e.$htmlBodyCol as html_body, e.$dateCol as entry_date,
                               COALESCE(st.tag, 'unclassified') as sender_tag
                        FROM `$emailTable` e
                        LEFT JOIN sender_tags st ON e.$fromEmailCol = st.from_email AND (st.removed_at IS NULL) AND st.disabled = 0";
                $params = [];
                if ($since) {
                    $sql .= " WHERE e.$dateCol >= ?";
                    $params[] = $since;
                }
                $sql .= " ORDER BY e.$dateCol DESC LIMIT " . (int)$limit;
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                foreach ($stmt->fetchAll() as $row) {
                    $body = $row['text_body'] ?: strip_tags($row['html_body'] ?? '');
                    $entries[] = [
                        'entry_type' => 'email',
                        'entry_id' => (int)$row['id'],
                        'title' => $row['subject'] ?: '(No subject)',
                        'description' => mb_substr(trim(preg_replace('/\s+/', ' ', $body)), 0, 500),
                        'content' => $body,
                        'link' => '',
                        'author' => $row['from_name'] ?: $row['from_email'],
                        'published_date' => $row['entry_date'],
                        'source_name' => $row['from_name'] ?: $row['from_email'],
                        'source_category' => $row['sender_tag'],
                        'source_type' => 'email',
                    ];
                }
            } catch (PDOException $e) {
                // Email table might not exist or have different schema
            }
        }
        
        // Lex items
        if ($type === 'all' || $type === 'lex_item') {
            try {
                $sql = "SELECT id, celex, title, document_date, document_type, eurlex_url, source
                        FROM lex_items";
                $params = [];
                if ($since) {
                    $sql .= " WHERE document_date >= ?";
                    $params[] = $since;
                }
                $sql .= " ORDER BY document_date DESC LIMIT " . (int)$limit;
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                foreach ($stmt->fetchAll() as $row) {
                    $entries[] = [
                        'entry_type' => 'lex_item',
                        'entry_id' => (int)$row['id'],
                        'title' => $row['title'],
                        'description' => ($row['document_type'] ?? '') . ' | ' . ($row['celex'] ?? ''),
                        'content' => $row['title'],
                        'link' => $row['eurlex_url'] ?? '',
                        'author' => '',
                        'published_date' => $row['document_date'],
                        'source_name' => match($row['source'] ?? 'eu') {
                            'ch' => 'Fedlex',
                            'de' => 'recht.bund.de',
                            'ch_bger' => 'Bundesgericht',
                            'ch_bge' => 'BGE Leitentscheide',
                            default => 'EUR-Lex',
                        },
                        'source_category' => $row['document_type'] ?? 'Legislation',
                        'source_type' => 'lex_' . ($row['source'] ?? 'eu'),
                    ];
                }
            } catch (PDOException $e) {
                // lex_items table might not exist yet
            }
        }
        
        echo json_encode([
            'entries' => $entries,
            'total' => count($entries),
            'since' => $since,
            'type' => $type,
        ]);
        break;
    
    case 'magnitu_scores':
        // POST /index.php?action=magnitu_scores
        // Receive batch of scores from Magnitu
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'POST required']);
            break;
        }
        if (!validateMagnituApiKey($pdo)) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid API key']);
            break;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['scores'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON body, expected {scores: [...]}']);
            break;
        }
        
        $scores = $input['scores'];
        $modelVersion = (int)($input['model_version'] ?? 0);
        $inserted = 0;
        $updated = 0;
        
        $upsertStmt = $pdo->prepare("
            INSERT INTO entry_scores (entry_type, entry_id, relevance_score, predicted_label, explanation, score_source, model_version)
            VALUES (?, ?, ?, ?, ?, 'magnitu', ?)
            ON DUPLICATE KEY UPDATE
                relevance_score = VALUES(relevance_score),
                predicted_label = VALUES(predicted_label),
                explanation = VALUES(explanation),
                score_source = 'magnitu',
                model_version = VALUES(model_version)
        ");
        
        foreach ($scores as $score) {
            $entryType = $score['entry_type'] ?? '';
            $entryId = (int)($score['entry_id'] ?? 0);
            $relevanceScore = (float)($score['relevance_score'] ?? 0);
            $predictedLabel = $score['predicted_label'] ?? null;
            $explanation = isset($score['explanation']) ? json_encode($score['explanation']) : null;
            
            if (!in_array($entryType, ['feed_item', 'email', 'lex_item']) || $entryId <= 0) continue;
            
            $upsertStmt->execute([$entryType, $entryId, $relevanceScore, $predictedLabel, $explanation, $modelVersion]);
            if ($upsertStmt->rowCount() === 1) $inserted++;
            else $updated++;
        }
        
        // Update last sync timestamp
        setMagnituConfig($pdo, 'last_sync_at', date('Y-m-d H:i:s'));
        
        // Store model metadata if provided
        $modelMeta = $input['model_meta'] ?? null;
        if ($modelMeta && is_array($modelMeta)) {
            if (!empty($modelMeta['model_name'])) {
                setMagnituConfig($pdo, 'model_name', $modelMeta['model_name']);
            }
            if (isset($modelMeta['model_description'])) {
                setMagnituConfig($pdo, 'model_description', $modelMeta['model_description']);
            }
            if (!empty($modelMeta['model_version'])) {
                setMagnituConfig($pdo, 'model_version', (string)$modelMeta['model_version']);
            }
            if (!empty($modelMeta['model_trained_at'])) {
                setMagnituConfig($pdo, 'model_trained_at', $modelMeta['model_trained_at']);
            }
        }
        
        echo json_encode([
            'success' => true,
            'inserted' => $inserted,
            'updated' => $updated,
            'total' => count($scores),
        ]);
        break;
    
    case 'magnitu_recipe':
        // GET  — return current recipe
        // POST — receive new recipe from Magnitu
        header('Content-Type: application/json');
        if (!validateMagnituApiKey($pdo)) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid API key']);
            break;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input || !isset($input['keywords'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid recipe JSON']);
                break;
            }
            
            setMagnituConfig($pdo, 'recipe_json', json_encode($input));
            setMagnituConfig($pdo, 'recipe_version', (string)($input['version'] ?? ((int)getMagnituConfig($pdo, 'recipe_version') + 1)));
            setMagnituConfig($pdo, 'last_sync_at', date('Y-m-d H:i:s'));
            
            // Re-score all unscored entries (or entries with recipe-based scores) using new recipe
            magnituRescore($pdo, $input);
            
            echo json_encode(['success' => true, 'recipe_version' => getMagnituConfig($pdo, 'recipe_version')]);
        } else {
            $recipe = getMagnituConfig($pdo, 'recipe_json');
            echo $recipe ?: json_encode(null);
        }
        break;
    
    case 'magnitu_status':
        // GET — return status for Magnitu to check connectivity and state
        header('Content-Type: application/json');
        if (!validateMagnituApiKey($pdo)) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid API key']);
            break;
        }
        
        $totalFeedItems = $pdo->query("SELECT COUNT(*) FROM feed_items")->fetchColumn();
        $totalEmails = 0;
        try {
            $emailTable = 'emails';
            $allTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($allTables as $t) {
                if (strtolower($t) === 'fetched_emails') { $emailTable = $t; break; }
            }
            $totalEmails = $pdo->query("SELECT COUNT(*) FROM `$emailTable`")->fetchColumn();
        } catch (PDOException $e) {}
        $totalLex = 0;
        try { $totalLex = $pdo->query("SELECT COUNT(*) FROM lex_items")->fetchColumn(); } catch (PDOException $e) {}
        
        $scoredCount = $pdo->query("SELECT COUNT(*) FROM entry_scores")->fetchColumn();
        $magnituScored = $pdo->query("SELECT COUNT(*) FROM entry_scores WHERE score_source = 'magnitu'")->fetchColumn();
        $recipeScored = $pdo->query("SELECT COUNT(*) FROM entry_scores WHERE score_source = 'recipe'")->fetchColumn();
        
        echo json_encode([
            'status' => 'ok',
            'version' => '0.4',
            'entries' => [
                'feed_items' => (int)$totalFeedItems,
                'emails' => (int)$totalEmails,
                'lex_items' => (int)$totalLex,
                'total' => (int)$totalFeedItems + (int)$totalEmails + (int)$totalLex,
            ],
            'scores' => [
                'total' => (int)$scoredCount,
                'magnitu' => (int)$magnituScored,
                'recipe' => (int)$recipeScored,
            ],
            'recipe_version' => (int)getMagnituConfig($pdo, 'recipe_version'),
            'alert_threshold' => (float)getMagnituConfig($pdo, 'alert_threshold'),
            'last_sync_at' => getMagnituConfig($pdo, 'last_sync_at') ?: null,
        ]);
        break;
    
    case 'magnitu_labels':
        // GET  — pull all labels
        // POST — push labels from Magnitu
        header('Content-Type: application/json');
        if (!validateMagnituApiKey($pdo)) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid API key']);
            break;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Receive labels from Magnitu
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input || !isset($input['labels'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid JSON body, expected {labels: [...]}']);
                break;
            }
            
            $upsertStmt = $pdo->prepare("
                INSERT INTO magnitu_labels (entry_type, entry_id, label, labeled_at)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    label = VALUES(label),
                    labeled_at = VALUES(labeled_at)
            ");
            
            $inserted = 0;
            $updated = 0;
            foreach ($input['labels'] as $lbl) {
                $entryType = $lbl['entry_type'] ?? '';
                $entryId = (int)($lbl['entry_id'] ?? 0);
                $label = $lbl['label'] ?? '';
                $labeledAt = $lbl['labeled_at'] ?? date('Y-m-d H:i:s');
                
                if (!in_array($entryType, ['feed_item', 'email', 'lex_item']) || $entryId <= 0 || $label === '') continue;
                
                $upsertStmt->execute([$entryType, $entryId, $label, $labeledAt]);
                if ($upsertStmt->rowCount() === 1) $inserted++;
                else $updated++;
            }
            
            echo json_encode([
                'success' => true,
                'inserted' => $inserted,
                'updated' => $updated,
                'total' => count($input['labels']),
            ]);
        } else {
            // Return all labels
            try {
                $stmt = $pdo->query("SELECT entry_type, entry_id, label, labeled_at FROM magnitu_labels ORDER BY labeled_at DESC");
                $labels = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $labels = [];
            }
            
            echo json_encode([
                'labels' => $labels,
                'total' => count($labels),
            ]);
        }
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
    
    if (!$url) {
        $_SESSION['error'] = 'Please provide a Substack URL';
        header('Location: ?action=substack');
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
        header('Location: ?action=substack');
        return;
    }
    
    // Check if feed already exists
    $stmt = $pdo->prepare("SELECT id FROM feeds WHERE url = ?");
    $stmt->execute([$feedUrl]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = 'This Substack is already subscribed';
        header('Location: ?action=substack');
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
    header('Location: ?action=substack');
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
        
        // Find email table
        $allTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $tableName = null;
        
        foreach ($allTables as $table) {
            if (strtolower($table) === 'fetched_emails') {
                $tableName = $table;
                break;
            }
        }
        
        if (!$tableName) {
            foreach ($allTables as $table) {
                if (strtolower($table) === 'emails' || strtolower($table) === 'email') {
                    $tableName = $table;
                    break;
                }
            }
        }
        
        if (!$tableName) {
            foreach ($allTables as $table) {
                if (stripos($table, 'mail') !== false || stripos($table, 'email') !== false) {
                    $tableName = $table;
                    break;
                }
            }
        }
        
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
                $orderBy = "created_at DESC";
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
                foreach (['created_at', 'date_utc', 'date_received', 'date_sent', 'id'] as $orderCol) {
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
        
        // Find email table
        $allTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $tableName = null;
        
        foreach ($allTables as $table) {
            if (strtolower($table) === 'fetched_emails') {
                $tableName = $table;
                break;
            }
        }
        
        if (!$tableName) {
            foreach ($allTables as $table) {
                if (strtolower($table) === 'emails' || strtolower($table) === 'email') {
                    $tableName = $table;
                    break;
                }
            }
        }
        
        if (!$tableName) {
            foreach ($allTables as $table) {
                if (stripos($table, 'mail') !== false || stripos($table, 'email') !== false) {
                    $tableName = $table;
                    break;
                }
            }
        }
        
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
            
            $stmt = $pdo->prepare("
                SELECT $selectClause
                FROM `$tableName`
                WHERE $finalWhereClause
                ORDER BY created_at DESC, date_received DESC, id DESC
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
        // Find the email table (same logic as in mail case)
        $allTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $tableName = null;
        
        foreach ($allTables as $table) {
            if (strtolower($table) === 'fetched_emails') {
                $tableName = $table;
                break;
            }
        }
        
        if (!$tableName) {
            foreach ($allTables as $table) {
                if (strtolower($table) === 'emails' || strtolower($table) === 'email') {
                    $tableName = $table;
                    break;
                }
            }
        }
        
        if (!$tableName) {
            foreach ($allTables as $table) {
                if (stripos($table, 'mail') !== false || stripos($table, 'email') !== false) {
                    $tableName = $table;
                    break;
                }
            }
        }
        
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

/**
 * Refresh Lex items from the EU CELLAR SPARQL endpoint.
 * Queries for recent finalized secondary legislation (regulations, directives, decisions).
 */
function refreshLexItems($pdo) {
    $config = getLexConfig();
    $euCfg = $config['eu'] ?? [];
    
    $lookback = (int)($euCfg['lookback_days'] ?? 90);
    $sinceDate = date('Y-m-d', strtotime("-{$lookback} days"));
    $lang = $euCfg['language'] ?? 'ENG';
    $limit = (int)($euCfg['limit'] ?? 100);
    $docClass = $euCfg['document_class'] ?? 'cdm:legislation_secondary';
    $endpoint = $euCfg['endpoint'] ?? 'https://publications.europa.eu/webapi/rdf/sparql';
    
    $sparqlQuery = '
        PREFIX cdm: <http://publications.europa.eu/ontology/cdm#>
        PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>
        
        SELECT DISTINCT ?work ?celex ?title ?date
        WHERE {
            ?work a ' . $docClass . ' .
            ?work cdm:work_date_document ?date .
            ?work cdm:resource_legal_id_celex ?celex .
            ?expr cdm:expression_belongs_to_work ?work .
            ?expr cdm:expression_uses_language <http://publications.europa.eu/resource/authority/language/' . $lang . '> .
            ?expr cdm:expression_title ?title .
            FILTER(?date >= "' . $sinceDate . '"^^xsd:date)
        }
        ORDER BY DESC(?date)
        LIMIT ' . $limit . '
    ';
    
    $sparql = new \EasyRdf\Sparql\Client($endpoint);
    $results = $sparql->query($sparqlQuery);
    
    $count = 0;
    $insertStmt = $pdo->prepare("
        INSERT INTO lex_items (celex, title, document_date, document_type, eurlex_url, work_uri, source)
        VALUES (?, ?, ?, ?, ?, ?, 'eu')
        ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            document_date = VALUES(document_date),
            document_type = VALUES(document_type),
            eurlex_url = VALUES(eurlex_url),
            work_uri = VALUES(work_uri),
            source = 'eu',
            fetched_at = NOW()
    ");
    
    foreach ($results as $row) {
        $celex   = (string) $row->celex;
        $title   = (string) $row->title;
        $date    = (string) $row->date;
        $workUri = (string) $row->work;
        
        $docType  = parseCelexType($celex);
        $eurlexUrl = 'https://eur-lex.europa.eu/legal-content/EN/TXT/?uri=CELEX:' . urlencode($celex);
        
        $insertStmt->execute([$celex, $title, $date, $docType, $eurlexUrl, $workUri]);
        $count++;
    }
    
    return $count;
}

/**
 * Parse the document type from a CELEX number.
 * Format: sector(1) + year(4) + type letter + sequential number
 * Sector 3 = secondary legislation. Type: R=Regulation, L=Directive, D=Decision.
 */
function parseCelexType($celex) {
    if (strlen($celex) < 6) return 'Other';
    $typeChar = strtoupper(substr($celex, 5, 1));
    switch ($typeChar) {
        case 'R': return 'Regulation';
        case 'L': return 'Directive';
        case 'D': return 'Decision';
        default:  return 'Other';
    }
}

/**
 * Refresh Lex items from the Fedlex SPARQL endpoint (Swiss federal legislation).
 * Queries for recent Acts (Bundesgesetze, Verordnungen, Bundesbeschlüsse, etc.).
 */
function refreshFedlexItems($pdo) {
    $config = getLexConfig();
    $chCfg = $config['ch'] ?? [];
    
    $lookback = (int)($chCfg['lookback_days'] ?? 90);
    $sinceDate = date('Y-m-d', strtotime("-{$lookback} days"));
    $lang = $chCfg['language'] ?? 'DEU';
    $limit = (int)($chCfg['limit'] ?? 100);
    $endpoint = $chCfg['endpoint'] ?? 'https://fedlex.data.admin.ch/sparqlendpoint';
    
    // Build resource-type filter from config
    $resourceTypes = $chCfg['resource_types'] ?? [];
    $typeIds = array_map(function($rt) {
        return is_array($rt) ? (int)$rt['id'] : (int)$rt;
    }, $resourceTypes);
    
    if (empty($typeIds)) {
        $typeIds = [21, 22, 29, 26, 27, 28, 8, 9, 10, 31, 32];
    }
    
    $typeFilter = implode(', ', array_map(function($n) {
        return '<https://fedlex.data.admin.ch/vocabulary/resource-type/' . $n . '>';
    }, $typeIds));
    
    $sparqlQuery = '
        PREFIX jolux: <http://data.legilux.public.lu/resource/ontology/jolux#>
        PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>
        
        SELECT DISTINCT ?act ?title ?pubDate ?typeDoc
        WHERE {
            ?act a jolux:Act .
            ?act jolux:publicationDate ?pubDate .
            ?act jolux:typeDocument ?typeDoc .
            ?act jolux:isRealizedBy ?expr .
            ?expr jolux:title ?title .
            ?expr jolux:language <http://publications.europa.eu/resource/authority/language/' . $lang . '> .
            FILTER(?typeDoc IN (' . $typeFilter . '))
            FILTER(?pubDate >= "' . $sinceDate . '"^^xsd:date && ?pubDate <= "' . date('Y-m-d', strtotime('+1 year')) . '"^^xsd:date)
        }
        ORDER BY DESC(?pubDate)
        LIMIT ' . $limit . '
    ';
    
    $sparql = new \EasyRdf\Sparql\Client($endpoint);
    $results = $sparql->query($sparqlQuery);
    
    $count = 0;
    $insertStmt = $pdo->prepare("
        INSERT INTO lex_items (celex, title, document_date, document_type, eurlex_url, work_uri, source)
        VALUES (?, ?, ?, ?, ?, ?, 'ch')
        ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            document_date = VALUES(document_date),
            document_type = VALUES(document_type),
            eurlex_url = VALUES(eurlex_url),
            work_uri = VALUES(work_uri),
            source = 'ch',
            fetched_at = NOW()
    ");
    
    foreach ($results as $row) {
        $actUri  = (string) $row->act;
        $title   = (string) $row->title;
        $dateDoc = (string) $row->pubDate;
        $typeDoc = (string) $row->typeDoc;
        
        // Use the ELI path as the unique identifier (e.g. "eli/oc/2025/123")
        $eliId = str_replace('https://fedlex.data.admin.ch/', '', $actUri);
        
        $docType = parseFedlexType($typeDoc);
        
        // Build the browsable Fedlex URL (German version)
        $fedlexUrl = 'https://www.fedlex.admin.ch/' . $eliId . '/de';
        
        $insertStmt->execute([$eliId, $title, $dateDoc, $docType, $fedlexUrl, $actUri]);
        $count++;
    }
    
    return $count;
}

/**
 * Parse the document type from a Fedlex resource-type URI.
 * E.g. https://fedlex.data.admin.ch/vocabulary/resource-type/21 → "Bundesgesetz"
 */
function parseFedlexType($typeUri) {
    $map = [
        '21' => 'Bundesgesetz',
        '22' => 'Dringl. Bundesgesetz',
        '29' => 'Verordnung BR',
        '26' => 'Departementsverordnung',
        '27' => 'Amtsverordnung',
        '28' => 'Verordnung BV',
        '8'  => 'Bundesbeschluss',
        '9'  => 'Bundesbeschluss',
        '10' => 'Bundesbeschluss',
        '31' => 'Bilateral Treaty',
        '32' => 'Multilateral Treaty',
    ];
    
    if (preg_match('/resource-type\/(\d+)$/', $typeUri, $m)) {
        return $map[$m[1]] ?? 'Other';
    }
    return 'Other';
}

/**
 * Refresh German legislation from the recht.bund.de Bundesgesetzblatt RSS feed.
 * Returns the number of new/updated items.
 */
function refreshRechtBundItems($pdo) {
    $config = getLexConfig();
    $deCfg = $config['de'] ?? [];
    
    $lookback = (int)($deCfg['lookback_days'] ?? 90);
    $sinceDate = date('Y-m-d', strtotime("-{$lookback} days"));
    $limit = (int)($deCfg['limit'] ?? 100);
    $feedUrl = $deCfg['feed_url'] ?? 'https://www.recht.bund.de/rss/feeds/rss_bgbl-1-2.xml?nn=211452';
    
    // Fetch RSS feed — recht.bund.de requires a load-balancer cookie (AL_LB-S).
    // The first request sets the cookie via a 303 redirect. We use cURL with a
    // cookie jar so the redirect-follow picks it up automatically.
    $cookieFile = tempnam(sys_get_temp_dir(), 'recht_');
    $curlOpts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_USERAGENT => 'Seismo/0.4 (legislation monitor)',
        CURLOPT_SSL_VERIFYPEER => true,
    ];
    
    // Step 1: hit the homepage to establish the session cookie
    $ch = curl_init();
    curl_setopt_array($ch, $curlOpts + [
        CURLOPT_URL => 'https://www.recht.bund.de/de/home/home_node.html',
        CURLOPT_TIMEOUT => 15,
    ]);
    curl_exec($ch);
    curl_close($ch);
    
    // Step 2: fetch the RSS feed with the session cookie
    $ch = curl_init();
    curl_setopt_array($ch, $curlOpts + [
        CURLOPT_URL => $feedUrl,
    ]);
    $xmlContent = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    @unlink($cookieFile);
    
    if ($xmlContent === false || empty($xmlContent)) {
        throw new Exception("Failed to fetch RSS feed from recht.bund.de: " . ($curlError ?: "empty response, HTTP $httpCode"));
    }
    if (strpos($xmlContent, '<?xml') === false && strpos($xmlContent, '<rss') === false) {
        throw new Exception("Failed to fetch RSS feed from recht.bund.de (HTTP $httpCode, response is not XML)");
    }
    
    // Suppress XML warnings and parse
    libxml_use_internal_errors(true);
    $rss = simplexml_load_string($xmlContent);
    libxml_clear_errors();
    
    if ($rss === false) {
        throw new Exception("Failed to parse RSS feed from recht.bund.de");
    }
    
    // Navigate to items — RSS 2.0 format: rss > channel > item
    $items = [];
    if (isset($rss->channel->item)) {
        $items = $rss->channel->item;
    }
    
    $count = 0;
    $stmt = $pdo->prepare("
        INSERT INTO lex_items (celex, title, document_date, document_type, eurlex_url, work_uri, source, fetched_at)
        VALUES (?, ?, ?, ?, ?, ?, 'de', NOW())
        ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            document_type = VALUES(document_type),
            eurlex_url = VALUES(eurlex_url),
            fetched_at = NOW()
    ");
    
    foreach ($items as $item) {
        if ($count >= $limit) break;
        
        $title = trim((string)($item->title ?? ''));
        $link = trim((string)($item->link ?? ''));
        $pubDate = trim((string)($item->pubDate ?? ''));
        $description = trim((string)($item->description ?? ''));
        
        if (empty($title) || empty($link)) continue;
        
        // Parse publication date
        $docDate = null;
        if (!empty($pubDate)) {
            $ts = strtotime($pubDate);
            if ($ts !== false) {
                $docDate = date('Y-m-d', $ts);
                // Skip items older than lookback window
                if ($docDate < $sinceDate) continue;
            }
        }
        
        // Extract ELI identifier from the permalink URL
        // Example: https://www.recht.bund.de/eli/bund/BGBl-1/2026/42 → BGBl-1/2026/42
        $eliId = $link;
        if (preg_match('#/eli/bund/(.+?)/?$#', $link, $m)) {
            $eliId = $m[1];
        } elseif (preg_match('#recht\.bund\.de/(.+?)/?$#', $link, $m)) {
            $eliId = $m[1];
        }
        
        // Parse document type — prefer <meta:typ> from the feed, fall back to title parsing
        $meta = $item->children('http://recht.bund.de/rss/meta');
        $metaType = isset($meta->typ) ? trim((string)$meta->typ) : '';
        $docType = !empty($metaType) ? $metaType : parseRechtBundType($title);
        
        $stmt->execute([
            $eliId,       // celex (unique ID)
            $title,       // title
            $docDate,     // document_date
            $docType,     // document_type
            $link,        // eurlex_url (stores the permalink)
            $link,        // work_uri
        ]);
        
        $count++;
    }
    
    return $count;
}

/**
 * Parse the document type from a Bundesgesetzblatt title.
 * Typical patterns: "Gesetz zur ...", "Verordnung über ...", "Bekanntmachung ..."
 */
function parseRechtBundType($title) {
    $title = trim($title);
    $patterns = [
        '/^(Gesetz)\b/i' => 'Gesetz',
        '/^(Verordnung)\b/i' => 'Verordnung',
        '/^(Bekanntmachung)\b/i' => 'Bekanntmachung',
        '/^(Beschluss)\b/i' => 'Beschluss',
        '/^(Anordnung)\b/i' => 'Anordnung',
        '/^(Richtlinie)\b/i' => 'Richtlinie',
        '/^(Satzung)\b/i' => 'Satzung',
        '/Änderungsgesetz/i' => 'Änderungsgesetz',
        '/Haushaltsgesetz/i' => 'Haushaltsgesetz',
    ];
    
    foreach ($patterns as $regex => $type) {
        if (preg_match($regex, $title)) {
            return $type;
        }
    }
    
    // Fallback: check for common keywords anywhere in the title
    if (stripos($title, 'gesetz') !== false) return 'Gesetz';
    if (stripos($title, 'verordnung') !== false) return 'Verordnung';
    
    return 'BGBl';
}

/**
 * Refresh JUS items (Swiss case law) from entscheidsuche.ch.
 * Uses the index manifest for incremental sync, then fetches individual decision JSONs.
 * Supports both CH_BGer (Bundesgericht) and CH_BGE (Leitentscheide).
 *
 * @param PDO $pdo
 * @param string $spider 'CH_BGer' or 'CH_BGE'
 * @return int Number of items upserted
 */
function refreshJusItems($pdo, $spider = 'CH_BGer') {
    $sourceKey = ($spider === 'CH_BGE') ? 'ch_bge' : 'ch_bger';
    $config = getLexConfig();
    $cfg = $config[$sourceKey] ?? [];
    if (!($cfg['enabled'] ?? false)) return 0;
    
    $baseUrl = rtrim($cfg['base_url'] ?? 'https://entscheidsuche.ch', '/');
    $lookback = (int)($cfg['lookback_days'] ?? 90);
    $limit = (int)($cfg['limit'] ?? 100);
    $cutoffDate = date('Y-m-d', strtotime("-{$lookback} days"));
    
    // 1. Fetch the latest index manifest
    $indexUrl = "{$baseUrl}/docs/Index/{$spider}/last";
    $ch = curl_init($indexUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Seismo/0.4 (case-law-monitor)',
    ]);
    $indexJson = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || empty($indexJson)) {
        throw new Exception("Failed to fetch index manifest from {$indexUrl} (HTTP {$httpCode})");
    }
    
    $index = json_decode($indexJson, true);
    if (!$index || !isset($index['actions'])) {
        // Empty actions means no changes — not an error
        if (isset($index['spider'])) return 0;
        throw new Exception("Invalid index manifest from {$indexUrl}");
    }
    
    $actions = $index['actions'];
    if (empty($actions)) return 0;
    
    // 2. Filter to new/update actions only, pre-filter by date from filename
    $filesToFetch = [];
    foreach ($actions as $filePath => $action) {
        if ($action === 'delete') continue;
        
        // Extract date from filename: CH_BGer_007_7B-835-2025_2025-09-18.json → 2025-09-18
        if (preg_match('/_(\d{4}-\d{2}-\d{2})\.json$/', $filePath, $m)) {
            $fileDate = $m[1];
            if ($fileDate < $cutoffDate) continue; // Skip old decisions
        }
        
        $filesToFetch[] = $filePath;
        if (count($filesToFetch) >= $limit) break;
    }
    
    if (empty($filesToFetch)) return 0;
    
    // 3. Fetch each decision JSON and upsert into lex_items
    $upsert = $pdo->prepare("
        INSERT INTO lex_items (celex, title, document_date, document_type, eurlex_url, work_uri, source)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            document_date = VALUES(document_date),
            document_type = VALUES(document_type),
            eurlex_url = VALUES(eurlex_url),
            work_uri = VALUES(work_uri),
            fetched_at = CURRENT_TIMESTAMP
    ");
    
    $count = 0;
    $mh = curl_multi_init();
    $handles = [];
    
    // Batch fetch in groups of 10 for efficiency
    $batches = array_chunk($filesToFetch, 10);
    foreach ($batches as $batch) {
        $handles = [];
        foreach ($batch as $filePath) {
            $url = "{$baseUrl}/docs/{$filePath}";
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_USERAGENT => 'Seismo/0.4 (case-law-monitor)',
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[] = ['handle' => $ch, 'path' => $filePath];
        }
        
        // Execute batch
        $running = null;
        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh);
        } while ($running > 0);
        
        // Process results
        foreach ($handles as $h) {
            $ch = $h['handle'];
            $filePath = $h['path'];
            $body = curl_multi_getcontent($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
            
            if ($code !== 200 || empty($body)) continue;
            
            $decision = json_decode($body, true);
            if (!$decision) continue;
            
            // Extract slug from path: CH_BGer/CH_BGer_007_7B-835-2025_2025-09-18.json → CH_BGer_007_7B-835-2025_2025-09-18
            $slug = pathinfo(basename($filePath), PATHINFO_FILENAME);
            
            // Filter by date again (from JSON Datum field for accuracy)
            $datum = $decision['Datum'] ?? null;
            if ($datum && $datum < $cutoffDate) continue;
            
            // Map fields
            $signatur = $decision['Signatur'] ?? '';
            
            // Title: Kopfzeile[0].Text (fallback to Num)
            $title = '';
            if (!empty($decision['Kopfzeile'][0]['Text'])) {
                $title = $decision['Kopfzeile'][0]['Text'];
            } elseif (!empty($decision['Abstract'][0]['Text'])) {
                $title = $decision['Abstract'][0]['Text'];
            }
            if (empty($title)) {
                $nums = $decision['Num'] ?? [];
                $title = is_array($nums) ? ($nums[0] ?? $slug) : ($nums ?: $slug);
            }
            
            // Document type: chamber label from Signatur
            $documentType = getJusChamberLabel($signatur);
            
            // URL: prefer official HTML URL, fallback to entscheidsuche.ch viewer
            $eurlex_url = '';
            if (!empty($decision['HTML']['URL'])) {
                $eurlex_url = $decision['HTML']['URL'];
            } else {
                $eurlex_url = "{$baseUrl}/view/{$slug}";
            }
            
            // Work URI: direct JSON file URL
            $work_uri = "{$baseUrl}/docs/{$filePath}";
            
            $upsert->execute([
                $slug,          // celex
                $title,         // title
                $datum,         // document_date
                $documentType,  // document_type
                $eurlex_url,    // eurlex_url
                $work_uri,      // work_uri
                $sourceKey,     // source: ch_bger or ch_bge
            ]);
            $count++;
        }
    }
    
    curl_multi_close($mh);
    return $count;
}

function refreshEmails($pdo) {
    // This function triggers a refresh/reload of emails from the database
    // The actual loading happens in the 'mail' case
    // We just need to ensure the table exists and is accessible
    try {
        // First, let's check what tables exist (for debugging)
        $allTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $tableNames = implode(', ', $allTables);
        
        // Check for fetched_emails (cronjob default), then emails, then any email-related table
        $tableName = null;
        foreach ($allTables as $table) {
            if (strtolower($table) === 'fetched_emails') {
                $tableName = $table;
                break;
            }
        }
        
        if (!$tableName) {
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'emails'");
            if ($tableCheck->rowCount() > 0) {
                $tableName = 'emails';
            }
        }
        
        if (!$tableName) {
            foreach ($allTables as $table) {
                if (stripos($table, 'mail') !== false || stripos($table, 'email') !== false) {
                    $tableName = $table;
                    break;
                }
            }
        }
        
        if (!$tableName) {
            $_SESSION['error'] = "No emails table found. Available tables: $tableNames";
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

/**
 * Re-score entries that don't have a Magnitu (full model) score using the recipe.
 * Called when a new recipe is uploaded.
 */
function magnituRescore($pdo, $recipeData) {
    if (empty($recipeData) || empty($recipeData['keywords'])) return;
    
    // Score feed_items that don't have a magnitu score (batched for stability)
    $stmt = $pdo->query("
        SELECT fi.id, fi.title, fi.description, fi.content, f.source_type
        FROM feed_items fi
        JOIN feeds f ON fi.feed_id = f.id
        WHERE f.disabled = 0
          AND NOT EXISTS (
              SELECT 1 FROM entry_scores es 
              WHERE es.entry_type = 'feed_item' AND es.entry_id = fi.id AND es.score_source = 'magnitu'
          )
        LIMIT 500
    ");
    $upsert = $pdo->prepare("
        INSERT INTO entry_scores (entry_type, entry_id, relevance_score, predicted_label, explanation, score_source, model_version)
        VALUES ('feed_item', ?, ?, ?, ?, 'recipe', ?)
        ON DUPLICATE KEY UPDATE
            relevance_score = VALUES(relevance_score),
            predicted_label = VALUES(predicted_label),
            explanation = VALUES(explanation),
            score_source = IF(score_source = 'magnitu', score_source, 'recipe'),
            model_version = IF(score_source = 'magnitu', model_version, VALUES(model_version))
    ");
    $version = (int)($recipeData['version'] ?? 0);
    
    foreach ($stmt->fetchAll() as $row) {
        $sourceType = ($row['source_type'] === 'substack') ? 'substack' : 'rss';
        $result = scoreEntryWithRecipe($recipeData, $row['title'] ?? '', ($row['content'] ?: $row['description']) ?? '', $sourceType);
        if ($result) {
            $upsert->execute([
                $row['id'],
                $result['relevance_score'],
                $result['predicted_label'],
                json_encode($result['explanation']),
                $version,
            ]);
        }
    }
    
    // Score lex_items
    try {
        $stmt = $pdo->query("
            SELECT li.id, li.title, li.document_type, li.source
            FROM lex_items li
            WHERE NOT EXISTS (
                SELECT 1 FROM entry_scores es 
                WHERE es.entry_type = 'lex_item' AND es.entry_id = li.id AND es.score_source = 'magnitu'
            )
            LIMIT 500
        ");
        foreach ($stmt->fetchAll() as $row) {
            $sourceType = 'lex_' . ($row['source'] ?? 'eu');
            $result = scoreEntryWithRecipe($recipeData, $row['title'] ?? '', ($row['document_type'] ?? ''), $sourceType);
            if ($result) {
                $upsert = $pdo->prepare("
                    INSERT INTO entry_scores (entry_type, entry_id, relevance_score, predicted_label, explanation, score_source, model_version)
                    VALUES ('lex_item', ?, ?, ?, ?, 'recipe', ?)
                    ON DUPLICATE KEY UPDATE
                        relevance_score = VALUES(relevance_score),
                        predicted_label = VALUES(predicted_label),
                        explanation = VALUES(explanation),
                        score_source = IF(score_source = 'magnitu', score_source, 'recipe'),
                        model_version = IF(score_source = 'magnitu', model_version, VALUES(model_version))
                ");
                $upsert->execute([
                    $row['id'],
                    $result['relevance_score'],
                    $result['predicted_label'],
                    json_encode($result['explanation']),
                    $version,
                ]);
            }
        }
    } catch (PDOException $e) {
        // lex_items might not exist
    }
    
    // Score emails
    try {
        $emailTable = 'emails';
        $allTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($allTables as $t) {
            if (strtolower($t) === 'fetched_emails') { $emailTable = $t; break; }
        }
        $cols = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$emailTable'")->fetchAll(PDO::FETCH_COLUMN);
        $textBodyCol = in_array('text_body', $cols) ? 'text_body' : (in_array('body_text', $cols) ? 'body_text' : 'text_body');
        $htmlBodyCol = in_array('html_body', $cols) ? 'html_body' : (in_array('body_html', $cols) ? 'body_html' : 'html_body');
        
        $stmt = $pdo->query("
            SELECT e.id, e.subject, e.$textBodyCol as text_body, e.$htmlBodyCol as html_body
            FROM `$emailTable` e
            WHERE NOT EXISTS (
                SELECT 1 FROM entry_scores es 
                WHERE es.entry_type = 'email' AND es.entry_id = e.id AND es.score_source = 'magnitu'
            )
            LIMIT 500
        ");
        $upsertEmail = $pdo->prepare("
            INSERT INTO entry_scores (entry_type, entry_id, relevance_score, predicted_label, explanation, score_source, model_version)
            VALUES ('email', ?, ?, ?, ?, 'recipe', ?)
            ON DUPLICATE KEY UPDATE
                relevance_score = VALUES(relevance_score),
                predicted_label = VALUES(predicted_label),
                explanation = VALUES(explanation),
                score_source = IF(score_source = 'magnitu', score_source, 'recipe'),
                model_version = IF(score_source = 'magnitu', model_version, VALUES(model_version))
        ");
        foreach ($stmt->fetchAll() as $row) {
            $body = $row['text_body'] ?: strip_tags($row['html_body'] ?? '');
            $result = scoreEntryWithRecipe($recipeData, $row['subject'] ?? '', $body, 'email');
            if ($result) {
                $upsertEmail->execute([
                    $row['id'],
                    $result['relevance_score'],
                    $result['predicted_label'],
                    json_encode($result['explanation']),
                    $version,
                ]);
            }
        }
    } catch (PDOException $e) {
        // Email table might not exist
    }
}
