<?php
/**
 * Scraper Controller
 *
 * Handles the web scraper page, CRUD actions for scraper configs,
 * script/config downloads, and entry management (hide, delete, rescrape).
 */

// ---------------------------------------------------------------------------
// Page
// ---------------------------------------------------------------------------

function handleScraperPage($pdo) {
    $scraperItems = [];
    $scraperSources = [];
    try {
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
        
        $sourcesSubmitted = isset($_GET['sources_submitted']);
        if ($sourcesSubmitted) {
            $selectedPillIds = isset($_GET['sources']) ? array_map('intval', (array)$_GET['sources']) : [];
        } else {
            $selectedPillIds = array_column($scraperSources, 'id');
        }
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
}

// ---------------------------------------------------------------------------
// CRUD actions
// ---------------------------------------------------------------------------

function handleAddScraper($pdo) {
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
}

function handleUpdateScraper($pdo) {
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
}

function handleToggleScraper($pdo) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)($_POST['scraper_id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("UPDATE scraper_configs SET disabled = NOT disabled WHERE id = ?")->execute([$id]);
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
}

function handleRemoveScraper($pdo) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)($_POST['scraper_id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM scraper_configs WHERE id = ?")->execute([$id]);
            $_SESSION['success'] = 'Scraper removed.';
        }
    }
    header('Location: ?action=settings&tab=script');
    exit;
}

// ---------------------------------------------------------------------------
// Entry management
// ---------------------------------------------------------------------------

function handleHideScraperItem($pdo) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        if ($itemId > 0) {
            $pdo->prepare("UPDATE feed_items SET hidden = 1 WHERE id = ?")->execute([$itemId]);
            $_SESSION['success'] = 'Entry hidden.';
        }
    }
    header('Location: ?action=scraper');
    exit;
}

function handleDeleteAllScraperItems($pdo) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
}

function handleRescrapeSource($pdo) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $feedId = (int)($_POST['feed_id'] ?? 0);
        if ($feedId > 0) {
            $del = $pdo->prepare("DELETE FROM feed_items WHERE feed_id = ?");
            $del->execute([$feedId]);
            $count = $del->rowCount();
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
}

// ---------------------------------------------------------------------------
// Script & config downloads
// ---------------------------------------------------------------------------

function handleDownloadScraperConfig($pdo) {
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
}

function handleDownloadScraperScript($pdo) {
    $scriptPath = __DIR__ . '/../fetcher/scraper/seismo_scraper.php';
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
}
