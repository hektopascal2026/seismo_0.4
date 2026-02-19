<?php
/**
 * RSS & Substack Controller
 *
 * Handles feed pages, adding/removing/toggling feeds,
 * refreshing, caching items, tag management, search, and API endpoints.
 */

// ---------------------------------------------------------------------------
// Pages
// ---------------------------------------------------------------------------

function handleSubstackPage($pdo) {
    $selectedSubstackCategory = $_GET['category'] ?? null;
    
    $substackCategoriesStmt = $pdo->query("SELECT DISTINCT category FROM feeds WHERE source_type = 'substack' AND category IS NOT NULL AND category != '' ORDER BY category");
    $substackCategories = $substackCategoriesStmt->fetchAll(PDO::FETCH_COLUMN);
    
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
    
    $lastRefreshStmt = $pdo->query("SELECT MAX(last_fetched) as last_refresh FROM feeds WHERE source_type = 'substack' AND last_fetched IS NOT NULL");
    $lastRefreshRow = $lastRefreshStmt->fetch();
    $lastSubstackRefreshDate = $lastRefreshRow['last_refresh'] ? date('d.m.Y H:i', strtotime($lastRefreshRow['last_refresh'])) : null;
    
    include 'views/substack.php';
}

function handleRefreshAllSubstacks($pdo) {
    $stmt = $pdo->query("SELECT id FROM feeds WHERE source_type = 'substack' ORDER BY id");
    $substackFeeds = $stmt->fetchAll();
    foreach ($substackFeeds as $feed) {
        refreshFeed($pdo, $feed['id']);
    }
    $_SESSION['success'] = 'All Substack feeds refreshed successfully';
    header('Location: ?action=substack');
    exit;
}

// ---------------------------------------------------------------------------
// Feed CRUD
// ---------------------------------------------------------------------------

function handleAddFeed($pdo) {
    $url = filter_input(INPUT_POST, 'url', FILTER_SANITIZE_URL);
    $from = $_POST['from'] ?? $_GET['from'] ?? 'feeds';
    $redirectUrl = $from === 'settings' ? getBasePath() . '/index.php?action=settings&tab=basic' : '?action=feeds';
    
    if (!$url) {
        $_SESSION['error'] = 'Please provide a valid URL';
        header('Location: ' . $redirectUrl);
        return;
    }
    
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
    
    $stmt = $pdo->prepare("SELECT id FROM feeds WHERE url = ?");
    $stmt->execute([$url]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = 'Feed already exists';
        header('Location: ' . $redirectUrl);
        return;
    }
    
    $stmt = $pdo->prepare("INSERT INTO feeds (url, title, description, link, category) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $url,
        $feed->get_title() ?: 'Untitled Feed',
        $feed->get_description() ?: '',
        $feed->get_link() ?: $url,
        'unsortiert'
    ]);
    
    $feedId = $pdo->lastInsertId();
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
    
    if (!preg_match('#^https?://#', $url)) {
        $url = 'https://' . $url;
    }
    
    $url = rtrim($url, '/');
    $url = preg_replace('#/feed$#', '', $url);
    $feedUrl = $url . '/feed';
    
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
    
    $stmt = $pdo->prepare("SELECT id FROM feeds WHERE url = ?");
    $stmt->execute([$feedUrl]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = 'This Substack is already subscribed';
        header('Location: ' . $redirectUrl);
        return;
    }
    
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
    
    $stmt = $pdo->prepare("SELECT disabled FROM feeds WHERE id = ?");
    $stmt->execute([$feedId]);
    $feed = $stmt->fetch();
    
    if (!$feed) {
        $_SESSION['error'] = 'Feed not found';
        $redirectUrl = $from === 'settings' ? getBasePath() . '/index.php?action=settings&tab=basic' : '?action=feeds';
        header('Location: ' . $redirectUrl);
        return;
    }
    
    $newStatus = $feed['disabled'] ? 0 : 1;
    $updateStmt = $pdo->prepare("UPDATE feeds SET disabled = ? WHERE id = ?");
    $updateStmt->execute([$newStatus, $feedId]);
    
    $statusText = $newStatus ? 'disabled' : 'enabled';
    $_SESSION['success'] = 'Feed ' . $statusText . ' successfully';
    $redirectUrl = $from === 'settings' ? getBasePath() . '/index.php?action=settings&tab=basic' : '?action=feeds';
    header('Location: ' . $redirectUrl);
    exit;
}

// ---------------------------------------------------------------------------
// View & Refresh
// ---------------------------------------------------------------------------

function viewFeed($pdo, $feedId) {
    $stmt = $pdo->prepare("SELECT * FROM feeds WHERE id = ?");
    $stmt->execute([$feedId]);
    $feed = $stmt->fetch();
    
    if (!$feed) {
        header('Location: ?action=index');
        return;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM feed_items WHERE feed_id = ? ORDER BY published_date DESC LIMIT 100");
    $stmt->execute([$feedId]);
    $items = $stmt->fetchAll();
    
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
    
    $simplepie = new \SimplePie\SimplePie();
    $simplepie->set_feed_url($feed['url']);
    $simplepie->enable_cache(false);
    $simplepie->init();
    $simplepie->handle_content_type();
    
    if (!$simplepie->error()) {
        $updateStmt = $pdo->prepare("UPDATE feeds SET title = ?, description = ?, link = ?, last_fetched = NOW() WHERE id = ?");
        $updateStmt->execute([
            $simplepie->get_title() ?: $feed['title'],
            $simplepie->get_description() ?: $feed['description'],
            $simplepie->get_link() ?: $feed['link'],
            $feedId
        ]);
        
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
    $stmt = $pdo->query("SELECT id FROM feeds ORDER BY id");
    $feeds = $stmt->fetchAll();
    
    foreach ($feeds as $feed) {
        refreshFeed($pdo, $feed['id']);
    }
}

function handleRefreshAllFeeds($pdo) {
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
}

function handleRefreshFeed($pdo) {
    $feedId = (int)($_GET['id'] ?? 0);
    refreshFeed($pdo, $feedId);
    header('Location: ?action=view_feed&id=' . $feedId);
    exit;
}

function handleViewFeed($pdo) {
    $feedId = (int)($_GET['id'] ?? 0);
    viewFeed($pdo, $feedId);
}

// ---------------------------------------------------------------------------
// Tag management
// ---------------------------------------------------------------------------

function handleUpdateFeedTag($pdo) {
    header('Content-Type: application/json');
    
    $feedId = (int)($_POST['feed_id'] ?? 0);
    $tag = trim($_POST['tag'] ?? '');
    
    if (!$feedId) {
        echo json_encode(['success' => false, 'error' => 'Invalid feed ID']);
        return;
    }
    
    if (empty($tag)) {
        echo json_encode(['success' => false, 'error' => 'Tag cannot be empty']);
        return;
    }
    
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
    
    $stmt = $pdo->prepare("UPDATE feeds SET category = ? WHERE category = ? AND source_type = 'substack'");
    $stmt->execute([$newTag, $oldTag]);
    
    $affectedRows = $stmt->rowCount();
    
    echo json_encode(['success' => true, 'affected' => $affectedRows]);
}

// ---------------------------------------------------------------------------
// Search & API
// ---------------------------------------------------------------------------

function searchFeedItems($pdo, $query, $limit = 100, $selectedTags = []) {
    $searchTerm = '%' . $query . '%';
    
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
    
    $escapedText = htmlspecialchars($text);
    $escapedQuery = preg_quote($searchQuery, '/');
    
    $highlighted = preg_replace(
        '/' . $escapedQuery . '/i',
        '<mark class="search-highlight">$0</mark>',
        $escapedText
    );
    
    return $highlighted;
}

function handleApiFeeds($pdo) {
    header('Content-Type: application/json');
    $stmt = $pdo->query("SELECT * FROM feeds ORDER BY created_at DESC LIMIT 1000");
    echo json_encode($stmt->fetchAll());
}

function handleApiItems($pdo) {
    header('Content-Type: application/json');
    $feedId = (int)($_GET['feed_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM feed_items WHERE feed_id = ? ORDER BY published_date DESC LIMIT 50");
    $stmt->execute([$feedId]);
    echo json_encode($stmt->fetchAll());
}

function handleApiTags($pdo) {
    header('Content-Type: application/json');
    $stmt = $pdo->query("SELECT DISTINCT category FROM feeds WHERE category IS NOT NULL AND category != '' AND (source_type = 'rss' OR source_type IS NULL) ORDER BY category");
    $tags = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode($tags);
}

function handleApiSubstackTags($pdo) {
    header('Content-Type: application/json');
    $stmt = $pdo->query("SELECT DISTINCT category FROM feeds WHERE category IS NOT NULL AND category != '' AND source_type = 'substack' ORDER BY category");
    $tags = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode($tags);
}

function handleApiAllTags($pdo) {
    session_write_close();
    header('Content-Type: application/json');
    $rssTags = $pdo->query("SELECT DISTINCT category FROM feeds WHERE category IS NOT NULL AND category != '' AND (source_type = 'rss' OR source_type IS NULL) ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
    $substackTags = $pdo->query("SELECT DISTINCT category FROM feeds WHERE category IS NOT NULL AND category != '' AND source_type = 'substack' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
    $emailTags = $pdo->query("SELECT DISTINCT tag FROM sender_tags WHERE tag IS NOT NULL AND tag != '' AND tag != 'unclassified' AND removed_at IS NULL ORDER BY tag")->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode(['rss' => $rssTags, 'substack' => $substackTags, 'email' => $emailTags]);
}
