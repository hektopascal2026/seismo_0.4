<?php
/**
 * Magnitu / ML Scoring Controller
 *
 * Handles the Magnitu page, AI views, Magnitu settings actions,
 * and the Bearer-authenticated API endpoints that the companion
 * Magnitu app uses to sync scores, recipes, and labels.
 */

// ---------------------------------------------------------------------------
// Pages
// ---------------------------------------------------------------------------

function handleMagnituPage($pdo) {
    $investigationByDay = [];
    $cutoffDate = strtotime('-7 days');

    try {
        $scoredStmt = $pdo->query("
            SELECT entry_type, entry_id, relevance_score, predicted_label, explanation, score_source, model_version
            FROM entry_scores 
            WHERE predicted_label = 'investigation_lead'
            ORDER BY relevance_score DESC
            LIMIT 500
        ");
        $scoredEntries = $scoredStmt->fetchAll();

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
                    $st = $entryData['source_type'] ?? 'rss';
                    if ($st === 'substack') $entryType = 'substack';
                    elseif ($st === 'scraper') $entryType = 'scraper';
                    else $entryType = 'feed';
                    $dateValue = $entryData['published_date'] ?? $entryData['cached_at'] ?? null;
                }
            } elseif ($scored['entry_type'] === 'email') {
                $emailTableName = getEmailTableName($pdo);
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
            } elseif ($scored['entry_type'] === 'calendar_event') {
                $stmt = $pdo->prepare("SELECT * FROM calendar_events WHERE id = ?");
                $stmt->execute([$scored['entry_id']]);
                $entryData = $stmt->fetch();
                if ($entryData) {
                    $entryType = 'calendar';
                    $dateValue = $entryData['event_date'] ?? $entryData['created_at'] ?? null;
                }
            }

            if (!$entryData) continue;

            $ts = $dateValue ? strtotime($dateValue) : 0;
            if ($ts < $cutoffDate) continue;

            $dayKey = $ts > 0 ? date('Y-m-d', $ts) : '0000-00-00';
            $investigationByDay[$dayKey][] = [
                'type' => $entryType,
                'date' => $ts,
                'data' => $entryData,
                'score' => $scored,
            ];
        }

        krsort($investigationByDay);

        foreach ($investigationByDay as &$dayItems) {
            usort($dayItems, function($a, $b) {
                return (float)$b['score']['relevance_score'] <=> (float)$a['score']['relevance_score'];
            });
        }
        unset($dayItems);

    } catch (PDOException $e) {
        // entry_scores table might not exist yet
    }

    $magnituAlertThreshold = (float)(getMagnituConfig($pdo, 'alert_threshold') ?? 0.75);
    $totalScored = 0;
    try {
        $totalScored = (int)$pdo->query("SELECT COUNT(*) FROM entry_scores")->fetchColumn();
    } catch (PDOException $e) {}
    $magnituModelName = getMagnituConfig($pdo, 'model_name');
    $magnituModelVersion = getMagnituConfig($pdo, 'model_version');

    include 'views/magnitu.php';
}

function handleAiView($pdo) {
    $aiSources = isset($_GET['sources']) ? (array)$_GET['sources'] : ['rss', 'substack', 'email', 'lex', 'parl_mm', 'jus', 'scraper', 'calendar'];
    $aiSince = $_GET['since'] ?? '7d';
    $aiLabels = isset($_GET['labels']) ? (array)$_GET['labels'] : ['investigation_lead', 'important', 'background', 'noise', 'unscored'];
    $aiMinScore = isset($_GET['min_score']) && $_GET['min_score'] !== '' ? (int)$_GET['min_score'] : null;
    $aiKeywords = trim($_GET['keywords'] ?? '');
    $aiLimit = min(max((int)($_GET['limit'] ?? 100), 1), 1000);

    $sinceMap = [
        '24h' => '-1 day', '3d' => '-3 days', '7d' => '-7 days',
        '30d' => '-30 days', '90d' => '-90 days', 'all' => null,
    ];
    $sinceDate = isset($sinceMap[$aiSince]) && $sinceMap[$aiSince] !== null
        ? date('Y-m-d H:i:s', strtotime($sinceMap[$aiSince]))
        : null;

    $aiScoreMap = [];
    try {
        $scoreStmt = $pdo->query("SELECT entry_type, entry_id, relevance_score, predicted_label FROM entry_scores");
        foreach ($scoreStmt->fetchAll() as $s) {
            $aiScoreMap[$s['entry_type'] . ':' . $s['entry_id']] = $s;
        }
    } catch (PDOException $e) {}

    $perSourceLimit = (int)ceil($aiLimit / max(count($aiSources), 1));
    $allItems = [];

    if (in_array('rss', $aiSources)) {
        try {
            $sql = "SELECT fi.*, f.title as feed_title, f.category as feed_category
                    FROM feed_items fi
                    JOIN feeds f ON fi.feed_id = f.id
                    WHERE f.disabled = 0 AND (f.source_type = 'rss' OR f.source_type IS NULL) AND fi.hidden = 0";
            $params = [];
            if ($sinceDate) { $sql .= " AND fi.published_date >= ?"; $params[] = $sinceDate; }
            $sql .= " ORDER BY fi.published_date DESC LIMIT " . $perSourceLimit;
            $stmt = $pdo->prepare($sql); $stmt->execute($params);
            foreach ($stmt->fetchAll() as $item) {
                $date = $item['published_date'] ?? $item['cached_at'] ?? 0;
                $scoreKey = 'feed_item:' . $item['id'];
                $score = $aiScoreMap[$scoreKey] ?? null;
                $allItems[] = [
                    'source' => 'RSS: ' . $item['feed_title'],
                    'source_type' => 'rss',
                    'date' => strtotime($date),
                    'title' => $item['title'],
                    'content' => strip_tags($item['content'] ?: $item['description']),
                    'link' => $item['link'],
                    'score' => $score ? (float)$score['relevance_score'] : null,
                    'label' => $score['predicted_label'] ?? null,
                ];
            }
        } catch (PDOException $e) {}
    }

    if (in_array('substack', $aiSources)) {
        try {
            $sql = "SELECT fi.*, f.title as feed_title
                    FROM feed_items fi
                    JOIN feeds f ON fi.feed_id = f.id
                    WHERE f.disabled = 0 AND f.source_type = 'substack' AND fi.hidden = 0";
            $params = [];
            if ($sinceDate) { $sql .= " AND fi.published_date >= ?"; $params[] = $sinceDate; }
            $sql .= " ORDER BY fi.published_date DESC LIMIT " . $perSourceLimit;
            $stmt = $pdo->prepare($sql); $stmt->execute($params);
            foreach ($stmt->fetchAll() as $item) {
                $date = $item['published_date'] ?? $item['cached_at'] ?? 0;
                $scoreKey = 'feed_item:' . $item['id'];
                $score = $aiScoreMap[$scoreKey] ?? null;
                $allItems[] = [
                    'source' => 'SUBSTACK: ' . $item['feed_title'],
                    'source_type' => 'substack',
                    'date' => strtotime($date),
                    'title' => $item['title'],
                    'content' => strip_tags($item['content'] ?: $item['description']),
                    'link' => $item['link'],
                    'score' => $score ? (float)$score['relevance_score'] : null,
                    'label' => $score['predicted_label'] ?? null,
                ];
            }
        } catch (PDOException $e) {}
    }

    if (in_array('email', $aiSources)) {
        $emails = getEmailsForIndex($pdo, $perSourceLimit, []);
        foreach ($emails as $email) {
            $date = $email['date_received'] ?? $email['date_utc'] ?? $email['created_at'] ?? 0;
            $ts = $date ? strtotime($date) : 0;
            if ($sinceDate && $ts < strtotime($sinceDate)) continue;
            $from = ($email['from_name'] ?: $email['from_email']) ?: 'Unknown';
            $scoreKey = 'email:' . $email['id'];
            $score = $aiScoreMap[$scoreKey] ?? null;
            $allItems[] = [
                'source' => "EMAIL: $from",
                'source_type' => 'email',
                'date' => $ts,
                'title' => $email['subject'] ?: '(No Subject)',
                'content' => strip_tags($email['text_body'] ?: $email['html_body'] ?: ''),
                'link' => '#',
                'score' => $score ? (float)$score['relevance_score'] : null,
                'label' => $score['predicted_label'] ?? null,
            ];
        }
    }

    if (in_array('lex', $aiSources)) {
        try {
            $sql = "SELECT * FROM lex_items WHERE source IN ('eu','ch','de')";
            $params = [];
            if ($sinceDate) { $sql .= " AND (document_date >= ? OR created_at >= ?)"; $params[] = $sinceDate; $params[] = $sinceDate; }
            $sql .= " ORDER BY document_date DESC LIMIT " . $perSourceLimit;
            $stmt = $pdo->prepare($sql); $stmt->execute($params);
            foreach ($stmt->fetchAll() as $lexItem) {
                $date = $lexItem['document_date'] ?? $lexItem['created_at'] ?? 0;
                $src = strtoupper((string)($lexItem['source'] ?? 'eu'));
                $docType = trim((string)($lexItem['document_type'] ?? 'Legislation'));
                $celex = trim((string)($lexItem['celex'] ?? ''));
                $desc = trim((string)($lexItem['description'] ?? ''));
                $contentParts = [];
                if ($docType !== '') $contentParts[] = 'Type: ' . $docType;
                if ($celex !== '') $contentParts[] = 'ID: ' . $celex;
                if ($desc !== '') $contentParts[] = $desc;
                $scoreKey = 'lex_item:' . $lexItem['id'];
                $score = $aiScoreMap[$scoreKey] ?? null;
                $allItems[] = [
                    'source' => 'LEX: ' . $src,
                    'source_type' => 'lex',
                    'date' => $date ? strtotime($date) : 0,
                    'title' => $lexItem['title'] ?: '(No Title)',
                    'content' => !empty($contentParts) ? implode("\n", $contentParts) : '',
                    'link' => $lexItem['eurlex_url'] ?: '#',
                    'score' => $score ? (float)$score['relevance_score'] : null,
                    'label' => $score['predicted_label'] ?? null,
                ];
            }
        } catch (PDOException $e) {}
    }

    if (in_array('parl_mm', $aiSources)) {
        try {
            $sql = "SELECT * FROM lex_items WHERE source = 'parl_mm'";
            $params = [];
            if ($sinceDate) { $sql .= " AND (document_date >= ? OR created_at >= ?)"; $params[] = $sinceDate; $params[] = $sinceDate; }
            $sql .= " ORDER BY document_date DESC LIMIT " . $perSourceLimit;
            $stmt = $pdo->prepare($sql); $stmt->execute($params);
            foreach ($stmt->fetchAll() as $lexItem) {
                $date = $lexItem['document_date'] ?? $lexItem['created_at'] ?? 0;
                $commission = trim((string)($lexItem['document_type'] ?? ''));
                $desc = trim((string)($lexItem['description'] ?? ''));
                $scoreKey = 'lex_item:' . $lexItem['id'];
                $score = $aiScoreMap[$scoreKey] ?? null;
                $allItems[] = [
                    'source' => 'PARL MM' . ($commission ? ': ' . $commission : ''),
                    'source_type' => 'parl_mm',
                    'date' => $date ? strtotime($date) : 0,
                    'title' => $lexItem['title'] ?: '(No Title)',
                    'content' => $desc,
                    'link' => $lexItem['eurlex_url'] ?: '#',
                    'score' => $score ? (float)$score['relevance_score'] : null,
                    'label' => $score['predicted_label'] ?? null,
                ];
            }
        } catch (PDOException $e) {}
    }

    if (in_array('jus', $aiSources)) {
        try {
            $sql = "SELECT * FROM lex_items WHERE source IN ('ch_bger','ch_bge','ch_bvger')";
            $params = [];
            if ($sinceDate) { $sql .= " AND (document_date >= ? OR created_at >= ?)"; $params[] = $sinceDate; $params[] = $sinceDate; }
            $sql .= " ORDER BY document_date DESC LIMIT " . $perSourceLimit;
            $stmt = $pdo->prepare($sql); $stmt->execute($params);
            foreach ($stmt->fetchAll() as $lexItem) {
                $date = $lexItem['document_date'] ?? $lexItem['created_at'] ?? 0;
                $src = strtoupper(str_replace('ch_', '', (string)($lexItem['source'] ?? '')));
                $scoreKey = 'lex_item:' . $lexItem['id'];
                $score = $aiScoreMap[$scoreKey] ?? null;
                $allItems[] = [
                    'source' => 'JUS: ' . $src,
                    'source_type' => 'jus',
                    'date' => $date ? strtotime($date) : 0,
                    'title' => $lexItem['title'] ?: '(No Title)',
                    'content' => trim((string)($lexItem['document_type'] ?? '')),
                    'link' => $lexItem['eurlex_url'] ?: '#',
                    'score' => $score ? (float)$score['relevance_score'] : null,
                    'label' => $score['predicted_label'] ?? null,
                ];
            }
        } catch (PDOException $e) {}
    }

    if (in_array('scraper', $aiSources)) {
        try {
            $sql = "SELECT fi.*, f.title as feed_title
                    FROM feed_items fi
                    JOIN feeds f ON fi.feed_id = f.id
                    WHERE f.disabled = 0 AND f.source_type = 'scraper' AND fi.hidden = 0";
            $params = [];
            if ($sinceDate) { $sql .= " AND fi.published_date >= ?"; $params[] = $sinceDate; }
            $sql .= " ORDER BY fi.published_date DESC LIMIT " . $perSourceLimit;
            $stmt = $pdo->prepare($sql); $stmt->execute($params);
            foreach ($stmt->fetchAll() as $item) {
                $date = $item['published_date'] ?? $item['cached_at'] ?? 0;
                $scoreKey = 'feed_item:' . $item['id'];
                $score = $aiScoreMap[$scoreKey] ?? null;
                $allItems[] = [
                    'source' => 'SCRAPER: ' . $item['feed_title'],
                    'source_type' => 'scraper',
                    'date' => strtotime($date),
                    'title' => $item['title'],
                    'content' => strip_tags($item['content'] ?: ''),
                    'link' => $item['link'],
                    'score' => $score ? (float)$score['relevance_score'] : null,
                    'label' => $score['predicted_label'] ?? null,
                ];
            }
        } catch (PDOException $e) {}
    }

    if (in_array('calendar', $aiSources)) {
        try {
            $sql = "SELECT * FROM calendar_events WHERE 1=1";
            $params = [];
            if ($sinceDate) {
                $sql .= " AND (event_date >= ? OR created_at >= ?)";
                $params[] = $sinceDate;
                $params[] = $sinceDate;
            }
            $sql .= " ORDER BY event_date ASC LIMIT " . $perSourceLimit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            foreach ($stmt->fetchAll() as $event) {
                $date = $event['event_date'] ?? $event['created_at'] ?? 0;
                $scoreKey = 'calendar_event:' . $event['id'];
                $score = $aiScoreMap[$scoreKey] ?? null;
                $allItems[] = [
                    'source' => 'CALENDAR: ' . getCalendarSourceLabel($event['source']),
                    'source_type' => 'calendar',
                    'date' => $date ? strtotime($date) : 0,
                    'title' => $event['title'] ?: '(No Title)',
                    'content' => strip_tags($event['content'] ?: $event['description'] ?: ''),
                    'link' => $event['url'] ?: '#',
                    'score' => $score ? (float)$score['relevance_score'] : null,
                    'label' => $score['predicted_label'] ?? null,
                ];
            }
        } catch (PDOException $e) {}
    }

    $includeUnscored = in_array('unscored', $aiLabels);
    $allowedLabels = array_diff($aiLabels, ['unscored']);
    $allItems = array_filter($allItems, function($item) use ($allowedLabels, $includeUnscored) {
        if ($item['label'] === null) return $includeUnscored;
        return in_array($item['label'], $allowedLabels);
    });

    if ($aiMinScore !== null) {
        $minScoreFloat = $aiMinScore / 100.0;
        $allItems = array_filter($allItems, function($item) use ($minScoreFloat) {
            if ($item['score'] === null) return true;
            return $item['score'] >= $minScoreFloat;
        });
    }

    $keywordList = [];
    if (!empty($aiKeywords)) {
        $keywordList = array_map('trim', explode(',', mb_strtolower($aiKeywords)));
        $keywordList = array_filter($keywordList, 'strlen');
    }

    $allItems = array_values($allItems);
    foreach ($allItems as &$item) {
        $item['priority'] = false;
        if (!empty($keywordList)) {
            $haystack = mb_strtolower($item['title'] . ' ' . $item['content']);
            foreach ($keywordList as $kw) {
                if (mb_strpos($haystack, $kw) !== false) {
                    $item['priority'] = true;
                    break;
                }
            }
        }
    }
    unset($item);

    usort($allItems, function($a, $b) {
        if ($a['priority'] !== $b['priority']) return $b['priority'] <=> $a['priority'];
        return $b['date'] - $a['date'];
    });

    $allItems = array_slice($allItems, 0, $aiLimit);

    $aiFilterSummary = [
        'sources' => $aiSources,
        'since' => $aiSince,
        'labels' => $aiLabels,
        'min_score' => $aiMinScore,
        'keywords' => $aiKeywords,
        'limit' => $aiLimit,
        'total' => count($allItems),
    ];

    include 'views/ai_view.php';
}

// ---------------------------------------------------------------------------
// Settings actions
// ---------------------------------------------------------------------------

function handleSaveMagnituConfig($pdo) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $threshold = max(0.0, min(1.0, (float)($_POST['alert_threshold'] ?? 0.75)));
        $sortByRelevance = isset($_POST['sort_by_relevance']) ? '1' : '0';

        setMagnituConfig($pdo, 'alert_threshold', (string)$threshold);
        setMagnituConfig($pdo, 'sort_by_relevance', $sortByRelevance);

        $_SESSION['success'] = 'Magnitu settings saved.';
    }
    header('Location: ' . getBasePath() . '/index.php?action=settings&tab=magnitu');
    exit;
}

function handleRegenerateMagnituKey($pdo) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $newKey = bin2hex(random_bytes(16));
        setMagnituConfig($pdo, 'api_key', $newKey);
        $_SESSION['success'] = 'New Magnitu API key generated.';
    }
    header('Location: ' . getBasePath() . '/index.php?action=settings&tab=magnitu');
    exit;
}

function handleClearMagnituScores($pdo) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pdo->exec("DELETE FROM entry_scores");
        setMagnituConfig($pdo, 'recipe_json', '');
        setMagnituConfig($pdo, 'recipe_version', '0');
        setMagnituConfig($pdo, 'last_sync_at', '');
        $_SESSION['success'] = 'All Magnitu scores and recipe cleared.';
    }
    header('Location: ' . getBasePath() . '/index.php?action=settings&tab=magnitu');
    exit;
}

// ---------------------------------------------------------------------------
// Magnitu API endpoints (Bearer-authenticated)
// ---------------------------------------------------------------------------

function handleMagnituEntries($pdo) {
    header('Content-Type: application/json');
    if (!validateMagnituApiKey($pdo)) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid API key']);
        return;
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
        $emailTable = getEmailTableName($pdo);

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
            $sql = "SELECT id, celex, title, description, document_date, document_type, eurlex_url, source
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
                    'description' => !empty($row['description']) ? $row['description'] : (($row['document_type'] ?? '') . ' | ' . ($row['celex'] ?? '')),
                    'content' => !empty($row['description']) ? $row['description'] : $row['title'],
                    'link' => $row['eurlex_url'] ?? '',
                    'author' => '',
                    'published_date' => $row['document_date'],
                    'source_name' => match($row['source'] ?? 'eu') {
                        'ch' => 'Fedlex',
                        'de' => 'recht.bund.de',
                        'ch_bger' => 'Bundesgericht',
                        'ch_bge' => 'BGE Leitentscheide',
                        'ch_bvger' => 'Bundesverwaltungsgericht',
                        'parl_mm' => 'Parlament CH',
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

    // Calendar events are excluded from the Magnitu API for now.
    // They are scored internally via recipe but not exported for ML training.

    echo json_encode([
        'entries' => $entries,
        'total' => count($entries),
        'since' => $since,
        'type' => $type,
    ]);
}

function handleMagnituScores($pdo) {
    header('Content-Type: application/json');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'POST required']);
        return;
    }
    if (!validateMagnituApiKey($pdo)) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid API key']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['scores'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON body, expected {scores: [...]}']);
        return;
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

    $errors = 0;
    foreach ($scores as $score) {
        $entryType = $score['entry_type'] ?? '';
        $entryId = (int)($score['entry_id'] ?? 0);
        $relevanceScore = (float)($score['relevance_score'] ?? 0);
        $predictedLabel = $score['predicted_label'] ?? null;
        $explanation = isset($score['explanation']) ? json_encode($score['explanation']) : null;

        if (!in_array($entryType, ['feed_item', 'email', 'lex_item']) || $entryId <= 0) continue;

        try {
            $upsertStmt->execute([$entryType, $entryId, $relevanceScore, $predictedLabel, $explanation, $modelVersion]);
            if ($upsertStmt->rowCount() === 1) $inserted++;
            else $updated++;
        } catch (PDOException $e) {
            $errors++;
        }
    }

    setMagnituConfig($pdo, 'last_sync_at', date('Y-m-d H:i:s'));

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
        'errors' => $errors,
        'total' => count($scores),
    ]);
}

function handleMagnituRecipe($pdo) {
    header('Content-Type: application/json');
    if (!validateMagnituApiKey($pdo)) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid API key']);
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['keywords'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid recipe JSON']);
            return;
        }

        setMagnituConfig($pdo, 'recipe_json', json_encode($input));
        setMagnituConfig($pdo, 'recipe_version', (string)($input['version'] ?? ((int)getMagnituConfig($pdo, 'recipe_version') + 1)));
        setMagnituConfig($pdo, 'last_sync_at', date('Y-m-d H:i:s'));

        magnituRescore($pdo, $input);

        echo json_encode(['success' => true, 'recipe_version' => getMagnituConfig($pdo, 'recipe_version')]);
    } else {
        $recipe = getMagnituConfig($pdo, 'recipe_json');
        echo $recipe ?: json_encode(null);
    }
}

function handleMagnituStatus($pdo) {
    header('Content-Type: application/json');
    if (!validateMagnituApiKey($pdo)) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid API key']);
        return;
    }

    $totalFeedItems = $pdo->query("SELECT COUNT(*) FROM feed_items")->fetchColumn();
    $totalEmails = 0;
    try {
        $emailTable = getEmailTableName($pdo);
        $totalEmails = $pdo->query("SELECT COUNT(*) FROM `$emailTable`")->fetchColumn();
    } catch (PDOException $e) {}
    $totalLex = 0;
    try { $totalLex = $pdo->query("SELECT COUNT(*) FROM lex_items")->fetchColumn(); } catch (PDOException $e) {}
    // Calendar events are excluded from Magnitu status for now (scored internally only).

    $scoredCount = $pdo->query("SELECT COUNT(*) FROM entry_scores WHERE entry_type != 'calendar_event'")->fetchColumn();
    $magnituScored = $pdo->query("SELECT COUNT(*) FROM entry_scores WHERE score_source = 'magnitu' AND entry_type != 'calendar_event'")->fetchColumn();
    $recipeScored = $pdo->query("SELECT COUNT(*) FROM entry_scores WHERE score_source = 'recipe' AND entry_type != 'calendar_event'")->fetchColumn();

    echo json_encode([
        'status' => 'ok',
        'version' => '0.5.0',
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
}

function handleMagnituLabels($pdo) {
    header('Content-Type: application/json');
    if (!validateMagnituApiKey($pdo)) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid API key']);
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['labels'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON body, expected {labels: [...]}']);
            return;
        }

        try {
            $upsertStmt = $pdo->prepare("
                INSERT INTO magnitu_labels (entry_type, entry_id, label, reasoning, labeled_at)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    label = VALUES(label),
                    reasoning = VALUES(reasoning),
                    labeled_at = VALUES(labeled_at)
            ");
        } catch (PDOException $e) {
            // reasoning column may be missing — add it and retry
            $pdo->exec("ALTER TABLE magnitu_labels ADD COLUMN reasoning TEXT DEFAULT NULL AFTER label");
            $upsertStmt = $pdo->prepare("
                INSERT INTO magnitu_labels (entry_type, entry_id, label, reasoning, labeled_at)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    label = VALUES(label),
                    reasoning = VALUES(reasoning),
                    labeled_at = VALUES(labeled_at)
            ");
        }

        $inserted = 0;
        $updated = 0;
        $errors = 0;
        foreach ($input['labels'] as $lbl) {
            $entryType = $lbl['entry_type'] ?? '';
            $entryId = (int)($lbl['entry_id'] ?? 0);
            $label = $lbl['label'] ?? '';
            $reasoning = $lbl['reasoning'] ?? '';
            $labeledAt = $lbl['labeled_at'] ?? date('Y-m-d H:i:s');
            $labeledAt = preg_replace('/T/', ' ', $labeledAt);
            $labeledAt = preg_replace('/\.\d+Z?$|Z$/', '', $labeledAt);

            if (!in_array($entryType, ['feed_item', 'email', 'lex_item']) || $entryId <= 0 || $label === '') continue;

            try {
                $upsertStmt->execute([$entryType, $entryId, $label, $reasoning, $labeledAt]);
                if ($upsertStmt->rowCount() === 1) $inserted++;
                else $updated++;
            } catch (PDOException $e) {
                $errors++;
            }
        }

        echo json_encode([
            'success' => true,
            'inserted' => $inserted,
            'updated' => $updated,
            'errors' => $errors,
            'total' => count($input['labels']),
        ]);
    } else {
        try {
            $stmt = $pdo->query("SELECT entry_type, entry_id, label, reasoning, labeled_at FROM magnitu_labels ORDER BY labeled_at DESC");
            $labels = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $labels = [];
        }

        echo json_encode([
            'labels' => $labels,
            'total' => count($labels),
        ]);
    }
}

// ---------------------------------------------------------------------------
// Scoring
// ---------------------------------------------------------------------------

function magnituRescore($pdo, $recipeData) {
    if (empty($recipeData) || empty($recipeData['keywords'])) return;

    // Score feed_items that don't have a magnitu score
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
        $st = $row['source_type'] ?? 'rss';
        $sourceType = in_array($st, ['substack', 'scraper']) ? $st : 'rss';
        $result = scoreEntryWithRecipe($recipeData, $row['title'] ?? '', ($row['content'] ?: $row['description']) ?? '', $sourceType);
        if ($result) {
            try {
                $upsert->execute([
                    $row['id'],
                    $result['relevance_score'],
                    $result['predicted_label'],
                    json_encode($result['explanation']),
                    $version,
                ]);
            } catch (PDOException $e) {}
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
        $emailTable = getEmailTableName($pdo);
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

    // Score calendar_events
    rescoreCalendarEvents($pdo, $recipeData);
}
