<?php
/**
 * Dashboard Controller
 *
 * Renders the main index page: aggregates items from all sources
 * (RSS, Substack, Email, Lex, Scraper), applies tag filters,
 * handles search, merges Magnitu scores, and sorts the timeline.
 */

function handleDashboard($pdo) {
    $searchQuery = trim($_GET['q'] ?? '');

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
    
    $emailTagsStmt = $pdo->query("SELECT DISTINCT tag FROM sender_tags WHERE tag IS NOT NULL AND tag != '' AND tag != 'unclassified' AND removed_at IS NULL AND disabled = 0 ORDER BY tag");
    $emailTags = $emailTagsStmt->fetchAll(PDO::FETCH_COLUMN);
    
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
            ['eu', 'ch', 'de', 'ch_bger', 'ch_bge', 'ch_bvger'],
            function($s) use ($lexCfg) { return !empty($lexCfg[$s]['enabled']); }
        ));
    }
    
    $lexCfg = $lexCfg ?? getLexConfig();
    $enabledLexSources = [];
    foreach (['eu', 'ch', 'de', 'ch_bger', 'ch_bge', 'ch_bvger'] as $s) {
        if (!empty($lexCfg[$s]['enabled'])) $enabledLexSources[] = $s;
    }
    $selectedLexSources = array_values(array_intersect($selectedLexSources, $enabledLexSources));
    
    if (!empty($searchQuery)) {
        $latestItems = searchFeedItems($pdo, $searchQuery, 100, $selectedTags);
        $searchEmails = searchEmails($pdo, $searchQuery, 100, $selectedEmailTags);
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
                LIMIT 30
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
                LIMIT 30
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
            $emails = getEmailsForIndex($pdo, 30, $selectedEmailTags);
        } elseif (!$tagsSubmitted) {
            $emails = getEmailsForIndex($pdo, 30, []);
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
            LIMIT 30
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
            LIMIT 30
        ");
        $substackItems = $substackItemsStmt->fetchAll();
    } else {
        $substackItems = [];
    }
    
    // Scraper items
    $scraperItemsForFeed = [];
    $scraperFeedsForIndex = [];
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
                LIMIT 30
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
                LIMIT 100
            ");
            $lexStmt->execute($selectedLexSources);
            $lexItems = array_slice(filterJusBannedWords($lexStmt->fetchAll()), 0, 30);
        } elseif (!$tagsSubmitted) {
            $lexStmt = $pdo->query("
                SELECT * FROM lex_items
                ORDER BY document_date DESC
                LIMIT 100
            ");
            $lexItems = array_slice(filterJusBannedWords($lexStmt->fetchAll()), 0, 30);
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
    
    $magnituSortByRelevance = (bool)(getMagnituConfig($pdo, 'sort_by_relevance') ?? 0);
    $magnituAlertThreshold = (float)(getMagnituConfig($pdo, 'alert_threshold') ?? 0.75);
    if (isset($_GET['sort'])) {
        $magnituSortByRelevance = ($_GET['sort'] === 'relevance');
    }
    $hasMagnituScores = !empty($scoreMap);
    
    // Merge all items into unified timeline
    $allItems = [];
    
    foreach ($latestItems as $item) {
        $dateValue = $item['published_date'] ?? $item['cached_at'] ?? null;
        $scoreKey = 'feed_item:' . $item['id'];
        $allItems[] = [
            'type' => 'feed',
            'date' => $dateValue ? strtotime($dateValue) : 0,
            'data' => $item,
            'score' => $scoreMap[$scoreKey] ?? null,
        ];
    }
    
    foreach ($substackItems as $item) {
        $dateValue = $item['published_date'] ?? $item['cached_at'] ?? null;
        $scoreKey = 'feed_item:' . $item['id'];
        $allItems[] = [
            'type' => 'substack',
            'date' => $dateValue ? strtotime($dateValue) : 0,
            'data' => $item,
            'score' => $scoreMap[$scoreKey] ?? null,
        ];
    }
    
    foreach ($emails as $email) {
        $dateValue = $email['date_received'] ?? $email['date_utc'] ?? $email['created_at'] ?? $email['date_sent'] ?? null;
        $scoreKey = 'email:' . $email['id'];
        $allItems[] = [
            'type' => 'email',
            'date' => $dateValue ? strtotime($dateValue) : 0,
            'data' => $email,
            'score' => $scoreMap[$scoreKey] ?? null,
        ];
    }
    
    foreach ($lexItems as $lexItem) {
        $dateValue = $lexItem['document_date'] ?? $lexItem['created_at'] ?? null;
        $scoreKey = 'lex_item:' . $lexItem['id'];
        $allItems[] = [
            'type' => 'lex',
            'date' => $dateValue ? strtotime($dateValue) : 0,
            'data' => $lexItem,
            'score' => $scoreMap[$scoreKey] ?? null,
        ];
    }
    
    foreach ($scraperItemsForFeed as $item) {
        $dateValue = $item['published_date'] ?? $item['cached_at'] ?? null;
        $scoreKey = 'feed_item:' . $item['id'];
        $allItems[] = [
            'type' => 'scraper',
            'date' => $dateValue ? strtotime($dateValue) : 0,
            'data' => $item,
            'score' => $scoreMap[$scoreKey] ?? null,
        ];
    }
    
    if ($magnituSortByRelevance && $hasMagnituScores && empty($searchQuery)) {
        usort($allItems, function($a, $b) {
            $scoreA = $a['score']['relevance_score'] ?? -1;
            $scoreB = $b['score']['relevance_score'] ?? -1;
            if ($scoreA == $scoreB) return $b['date'] - $a['date'];
            return $scoreB <=> $scoreA;
        });
    } else {
        usort($allItems, function($a, $b) {
            return $b['date'] - $a['date'];
        });
    }
    
    $limit = !empty($searchQuery) ? 200 : 30;
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
    
    include 'views/index.php';
}
