<?php
/**
 * Calendar Events Controller
 *
 * Handles the calendar page, refresh actions, and fetchers for
 * upcoming events (Swiss Parliament sessions, publications, etc.).
 *
 * Data sources:
 *  - parliament_ch: Swiss Parliament OData API (ws.parlament.ch)
 *    Fetches upcoming business items (motions, interpellations, etc.)
 *    scheduled for debate in Nationalrat / Ständerat sessions.
 */

// ---------------------------------------------------------------------------
// Page
// ---------------------------------------------------------------------------

function handleCalendarPage($pdo) {
    $calendarCfg = getCalendarConfig();

    $enabledSources = [];
    foreach ($calendarCfg as $key => $src) {
        if (!empty($src['enabled'])) $enabledSources[] = $key;
    }

    $sourcesSubmitted = isset($_GET['sources_submitted']);
    if ($sourcesSubmitted) {
        $activeSources = isset($_GET['sources']) ? (array)$_GET['sources'] : [];
    } else {
        $activeSources = $enabledSources;
    }
    $activeSources = array_values(array_intersect($activeSources, $enabledSources));

    $showPast = !empty($_GET['show_past']);
    $eventType = $_GET['event_type'] ?? '';

    $calendarEvents = [];
    try {
        if (!empty($activeSources)) {
            $placeholders = implode(',', array_fill(0, count($activeSources), '?'));
            $params = $activeSources;

            $sql = "SELECT * FROM calendar_events WHERE source IN ($placeholders)";
            if (!$showPast) {
                $sql .= " AND (event_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) OR event_date IS NULL)";
            }
            if ($eventType !== '') {
                $sql .= " AND event_type = ?";
                $params[] = $eventType;
            }
            $sql .= " ORDER BY event_date DESC LIMIT 100";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $calendarEvents = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        // Table might not exist yet
    }

    // Collect distinct event types for filter
    $eventTypes = [];
    try {
        $stmt = $pdo->query("SELECT DISTINCT event_type FROM calendar_events WHERE event_type IS NOT NULL ORDER BY event_type");
        $eventTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {}

    // Last refresh date
    $lastCalendarRefreshDate = null;
    try {
        $row = $pdo->query("SELECT MAX(fetched_at) as lr FROM calendar_events")->fetch();
        if ($row && $row['lr']) {
            $lastCalendarRefreshDate = date('d.m.Y H:i', strtotime($row['lr']));
        }
    } catch (PDOException $e) {}

    // Magnitu scores for calendar events
    $scoreMap = [];
    try {
        $stmt = $pdo->query("SELECT entry_id, relevance_score, predicted_label, explanation, score_source FROM entry_scores WHERE entry_type = 'calendar_event'");
        foreach ($stmt->fetchAll() as $s) {
            $scoreMap[$s['entry_id']] = $s;
        }
    } catch (PDOException $e) {}

    $magnituAlertThreshold = (float)(getMagnituConfig($pdo, 'alert_threshold') ?? 0.75);

    include 'views/calendar.php';
}

// ---------------------------------------------------------------------------
// Refresh actions
// ---------------------------------------------------------------------------

function handleRefreshCalendar($pdo) {
    set_time_limit(120);

    $calendarCfg = getCalendarConfig();
    $results = [];

    if (!empty($calendarCfg['parliament_ch']['enabled'])) {
        $failKey = 'calendar_parliament_ch_failures';
        if (isSourceTripped($pdo, $failKey)) {
            $results[] = 'Parliament CH: skipped (tripped)';
        } else {
            try {
                $count = refreshParliamentChEvents($pdo);
                resetSourceFailure($pdo, $failKey);
                $results[] = "{$count} Parliament CH events";
            } catch (\Exception $e) {
                recordSourceFailure($pdo, $failKey);
                $results[] = 'Parliament CH: ' . $e->getMessage();
            }
        }
    }

    // Score new calendar events with recipe
    try {
        $recipeJson = getMagnituConfig($pdo, 'recipe_json');
        if ($recipeJson) {
            $recipeData = json_decode($recipeJson, true);
            if ($recipeData && !empty($recipeData['keywords'])) {
                rescoreCalendarEvents($pdo, $recipeData);
            }
        }
    } catch (\Exception $e) {
        $results[] = 'Scoring: ' . $e->getMessage();
    }

    if (empty($results)) {
        $results[] = 'No calendar sources enabled';
    }

    $_SESSION['success'] = implode(' · ', $results);
    header('Location: ?action=calendar');
    exit;
}

// ---------------------------------------------------------------------------
// Swiss Parliament OData Fetcher
// ---------------------------------------------------------------------------

/**
 * Fetch upcoming business items from the Swiss Parliament OData API.
 * Returns the number of events inserted/updated.
 *
 * The API at ws.parlament.ch/odata.svc provides:
 *  - /Business — all parliamentary business (motions, interpellations, etc.)
 *  - /Session — session periods
 *  - /BusinessRole — roles (rapporteurs, etc.)
 *
 * We fetch Business items that have a recent or future submission/modified date,
 * focusing on items likely to appear in upcoming sessions.
 */
function refreshParliamentChEvents($pdo) {
    $cfg = getCalendarConfig()['parliament_ch'] ?? [];
    $apiBase = rtrim($cfg['api_base'] ?? 'https://ws.parlament.ch/odata.svc', '/');
    $lang = $cfg['language'] ?? 'DE';
    $lookforwardDays = (int)($cfg['lookforward_days'] ?? 90);
    $lookbackDays = (int)($cfg['lookback_days'] ?? 7);
    $limit = min((int)($cfg['limit'] ?? 100), 500);

    $sinceDate = date('Y-m-d', strtotime("-{$lookbackDays} days"));
    $untilDate = date('Y-m-d', strtotime("+{$lookforwardDays} days"));

    // Fetch recent/upcoming business items
    // Filter: modified recently OR submitted recently, with a language filter
    $filter = "Language eq '{$lang}' and Modified ge datetime'{$sinceDate}T00:00:00'";
    $url = $apiBase . '/Business'
         . '?$filter=' . rawurlencode($filter)
         . '&$orderby=' . rawurlencode('SubmissionDate desc')
         . '&$top=' . $limit
         . '&$format=json';

    $ctx = stream_context_create([
        'http' => [
            'timeout' => 30,
            'header' => "Accept: application/json\r\n",
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents($url, false, $ctx);
    if ($response === false) {
        throw new \RuntimeException('Failed to connect to parlament.ch API');
    }

    $httpCode = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $httpCode = (int)$m[1];
    }
    if ($httpCode >= 400) {
        throw new \RuntimeException("parlament.ch API returned HTTP {$httpCode}");
    }

    $data = json_decode($response, true);
    $results = $data['d']['results'] ?? $data['d'] ?? $data['value'] ?? [];
    if (!is_array($results)) {
        $results = [];
    }

    $upsert = $pdo->prepare("
        INSERT INTO calendar_events (source, external_id, title, description, content, event_date, event_type, status, council, url, metadata)
        VALUES ('parliament_ch', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            description = VALUES(description),
            content = VALUES(content),
            event_date = VALUES(event_date),
            event_type = VALUES(event_type),
            status = VALUES(status),
            council = VALUES(council),
            url = VALUES(url),
            metadata = VALUES(metadata),
            fetched_at = CURRENT_TIMESTAMP
    ");

    $businessTypes = $cfg['business_types'] ?? [];
    $count = 0;

    foreach ($results as $item) {
        $id = $item['ID'] ?? $item['Id'] ?? null;
        if (!$id) continue;

        $externalId = (string)$id;
        $title = trim($item['Title'] ?? $item['BusinessShortNumber'] ?? '');
        if (empty($title)) continue;

        $rawDesc = $item['InitialSituation'] ?? $item['Description'] ?? '';
        $description = trim(strip_tags($rawDesc));
        $rawContent = $item['SubmittedText'] ?? $item['MotionText'] ?? $item['ReasonText'] ?? $rawDesc;
        $content = trim(strip_tags($rawContent));

        // Parse OData date format /Date(timestamp)/ or ISO string
        $eventDate = parseODataDate($item['SubmissionDate'] ?? null);
        $businessTypeId = $item['BusinessType'] ?? $item['BusinessTypeId'] ?? null;
        $eventType = $businessTypes[$businessTypeId] ?? ($item['BusinessTypeName'] ?? 'Geschaeft');

        // Map status
        $statusId = $item['BusinessStatus'] ?? $item['BusinessStatusId'] ?? null;
        $statusText = $item['BusinessStatusText'] ?? '';
        $status = mapParliamentStatus($statusId, $statusText);

        $councilId = $item['SubmissionCouncil'] ?? $item['SubmissionCouncilId'] ?? $item['FirstCouncil1'] ?? null;
        $council = match((int)($councilId ?? 0)) {
            1 => 'NR',
            2 => 'SR',
            default => null,
        };

        $itemUrl = "https://www.parlament.ch/de/ratsbetrieb/suche-curia-vista/geschaeft?AffairId={$externalId}";

        $metadata = json_encode([
            'business_number' => $item['BusinessShortNumber'] ?? null,
            'business_type_id' => $businessTypeId,
            'status_id' => $statusId,
            'status_text' => $statusText,
            'submission_council_id' => $councilId,
            'author' => $item['SubmittedBy'] ?? null,
            'responsible_department' => $item['TagNames'] ?? null,
        ]);

        $upsert->execute([
            $externalId,
            mb_substr($title, 0, 65535),
            mb_substr($description, 0, 65535),
            mb_substr($content, 0, 65535),
            $eventDate,
            $eventType,
            $status,
            $council,
            $itemUrl,
            $metadata,
        ]);
        $count++;
    }

    // Also fetch upcoming session dates
    $count += refreshParliamentChSessions($pdo, $cfg);

    return $count;
}

/**
 * Fetch upcoming session periods from the Swiss Parliament API.
 */
function refreshParliamentChSessions($pdo, $cfg) {
    $apiBase = rtrim($cfg['api_base'] ?? 'https://ws.parlament.ch/odata.svc', '/');
    $lang = $cfg['language'] ?? 'DE';
    $lookforwardDays = (int)($cfg['lookforward_days'] ?? 90);

    $today = date('Y-m-d');
    $untilDate = date('Y-m-d', strtotime("+{$lookforwardDays} days"));

    $filter = "Language eq '{$lang}' and EndDate ge datetime'{$today}T00:00:00'";
    $url = $apiBase . '/Session'
         . '?$filter=' . rawurlencode($filter)
         . '&$orderby=' . rawurlencode('StartDate asc')
         . '&$top=20'
         . '&$format=json';

    $ctx = stream_context_create([
        'http' => [
            'timeout' => 15,
            'header' => "Accept: application/json\r\n",
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents($url, false, $ctx);
    if ($response === false) return 0;

    $data = json_decode($response, true);
    $results = $data['d']['results'] ?? $data['d'] ?? $data['value'] ?? [];
    if (!is_array($results)) return 0;

    $upsert = $pdo->prepare("
        INSERT INTO calendar_events (source, external_id, title, description, content, event_date, event_end_date, event_type, status, council, url, metadata)
        VALUES ('parliament_ch', ?, ?, ?, ?, ?, ?, 'session', 'scheduled', ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            description = VALUES(description),
            event_date = VALUES(event_date),
            event_end_date = VALUES(event_end_date),
            council = VALUES(council),
            url = VALUES(url),
            metadata = VALUES(metadata),
            fetched_at = CURRENT_TIMESTAMP
    ");

    $count = 0;
    foreach ($results as $item) {
        $id = $item['ID'] ?? $item['Id'] ?? null;
        if (!$id) continue;

        $externalId = 'session_' . $id;
        $title = trim($item['SessionName'] ?? $item['Title'] ?? 'Session');
        $description = trim($item['Description'] ?? '');

        $startDate = parseODataDate($item['StartDate'] ?? null);
        $endDate = parseODataDate($item['EndDate'] ?? null);

        $councilId = $item['Council'] ?? $item['CouncilId'] ?? null;
        $council = match((int)$councilId) {
            1 => 'NR',
            2 => 'SR',
            default => null,
        };

        $sessionUrl = 'https://www.parlament.ch/de/ratsbetrieb/sessionen';

        $metadata = json_encode([
            'council_id' => $councilId,
            'session_type' => $item['Type'] ?? null,
        ]);

        $upsert->execute([
            $externalId,
            $title,
            $description,
            $title,
            $startDate,
            $endDate,
            $council,
            $sessionUrl,
            $metadata,
        ]);
        $count++;
    }

    return $count;
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Parse an OData date value. Handles /Date(timestamp)/ format and ISO strings.
 */
function parseODataDate($value) {
    if ($value === null || $value === '') return null;

    if (is_string($value) && preg_match('#/Date\((\d+)(?:[+-]\d+)?\)/#', $value, $m)) {
        return date('Y-m-d', (int)($m[1] / 1000));
    }

    $ts = strtotime($value);
    return $ts ? date('Y-m-d', $ts) : null;
}

/**
 * Map a Parliament business status ID/text to a simplified status.
 */
function mapParliamentStatus($statusId, $statusText = '') {
    $lower = mb_strtolower($statusText);
    if (str_contains($lower, 'erledigt') || str_contains($lower, 'abgeschlossen')) return 'completed';
    if (str_contains($lower, 'zurückgezogen')) return 'cancelled';
    if (str_contains($lower, 'hängig') || str_contains($lower, 'im rat')) return 'scheduled';
    if (str_contains($lower, 'verschoben')) return 'postponed';
    return 'scheduled';
}

/**
 * Friendly label for a calendar source key.
 */
function getCalendarSourceLabel($source) {
    return match($source) {
        'parliament_ch' => 'Parlament CH',
        default => ucfirst(str_replace('_', ' ', $source)),
    };
}

/**
 * Friendly label for an event type.
 */
function getCalendarEventTypeLabel($type) {
    return match($type) {
        'session' => 'Session',
        'Motion' => 'Motion',
        'Postulat' => 'Postulat',
        'Interpellation', 'Dringliche Interpellation' => 'Interpellation',
        'Einfache Anfrage', 'Dringliche Einfache Anfrage' => 'Anfrage',
        'Parlamentarische Initiative' => 'Parl. Initiative',
        'Standesinitiative' => 'Standesinitiative',
        'Geschaeft des Bundesrates', 'Geschäft des Bundesrates' => 'Bundesratsgeschäft',
        'Geschaeft des Parlaments', 'Geschäft des Parlaments' => 'Parlamentsgeschäft',
        'Petition' => 'Petition',
        'Empfehlung' => 'Empfehlung',
        'Fragestunde. Frage' => 'Fragestunde',
        default => $type ?: 'Event',
    };
}

/**
 * Format a council code as a readable label.
 */
function getCouncilLabel($code) {
    return match($code) {
        'NR' => 'Nationalrat',
        'SR' => 'Ständerat',
        'BR' => 'Bundesrat',
        default => $code ?? '',
    };
}

// ---------------------------------------------------------------------------
// Scoring
// ---------------------------------------------------------------------------

/**
 * Score unscored calendar events with the current recipe.
 */
function rescoreCalendarEvents($pdo, $recipeData) {
    if (empty($recipeData) || empty($recipeData['keywords'])) return;

    try {
        $stmt = $pdo->query("
            SELECT ce.id, ce.title, ce.description, ce.content, ce.source, ce.event_type
            FROM calendar_events ce
            WHERE NOT EXISTS (
                SELECT 1 FROM entry_scores es
                WHERE es.entry_type = 'calendar_event' AND es.entry_id = ce.id AND es.score_source = 'magnitu'
            )
            LIMIT 500
        ");

        $upsert = $pdo->prepare("
            INSERT INTO entry_scores (entry_type, entry_id, relevance_score, predicted_label, explanation, score_source, model_version)
            VALUES ('calendar_event', ?, ?, ?, ?, 'recipe', ?)
            ON DUPLICATE KEY UPDATE
                relevance_score = VALUES(relevance_score),
                predicted_label = VALUES(predicted_label),
                explanation = VALUES(explanation),
                score_source = IF(score_source = 'magnitu', score_source, 'recipe'),
                model_version = IF(score_source = 'magnitu', model_version, VALUES(model_version))
        ");

        $version = (int)($recipeData['version'] ?? 0);

        foreach ($stmt->fetchAll() as $row) {
            $text = ($row['content'] ?: $row['description']) ?? '';
            $sourceType = 'calendar_' . ($row['source'] ?? 'unknown');
            $result = scoreEntryWithRecipe($recipeData, $row['title'] ?? '', $text, $sourceType);
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
    } catch (PDOException $e) {
        // calendar_events table might not exist yet
    }
}

// ---------------------------------------------------------------------------
// Settings actions
// ---------------------------------------------------------------------------

/**
 * Save calendar config from the settings form.
 */
function handleSaveCalendarConfig($pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ?action=settings&tab=calendar');
        exit;
    }

    $config = getCalendarConfig();

    // Parliament CH
    $config['parliament_ch']['enabled'] = ($_POST['parliament_ch_enabled'] ?? '0') === '1';
    $config['parliament_ch']['language'] = $_POST['parliament_ch_language'] ?? 'DE';
    $config['parliament_ch']['limit'] = max(10, min(500, (int)($_POST['parliament_ch_limit'] ?? 100)));
    $config['parliament_ch']['lookforward_days'] = max(7, min(365, (int)($_POST['parliament_ch_lookforward_days'] ?? 90)));
    $config['parliament_ch']['lookback_days'] = max(1, min(90, (int)($_POST['parliament_ch_lookback_days'] ?? 7)));
    $config['parliament_ch']['notes'] = trim($_POST['parliament_ch_notes'] ?? '');

    // Business types: rebuild from checked checkboxes
    $defaultBusinessTypes = [
        1 => 'Geschaeft des Bundesrates',
        3 => 'Standesinitiative',
        4 => 'Parlamentarische Initiative',
        5 => 'Motion',
        6 => 'Postulat',
        8 => 'Interpellation',
        12 => 'Einfache Anfrage',
    ];
    $selectedTypes = $_POST['parliament_ch_business_types'] ?? [];
    $businessTypes = [];
    foreach ($selectedTypes as $typeId) {
        $typeId = (int)$typeId;
        if (isset($defaultBusinessTypes[$typeId])) {
            $businessTypes[$typeId] = $defaultBusinessTypes[$typeId];
        }
    }
    $config['parliament_ch']['business_types'] = $businessTypes;

    saveCalendarConfig($config);

    $_SESSION['success'] = 'Calendar settings saved.';
    header('Location: ?action=settings&tab=calendar');
    exit;
}

/**
 * Download the calendar_config.json file.
 */
function handleDownloadCalendarConfig($pdo) {
    $config = getCalendarConfig();
    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="calendar_config.json"');
    header('Content-Length: ' . strlen($json));
    echo $json;
    exit;
}

/**
 * Upload and apply a calendar_config.json file.
 */
function handleUploadCalendarConfig($pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['calendar_config_file']['tmp_name'])) {
        $_SESSION['error'] = 'No file uploaded.';
        header('Location: ?action=settings&tab=calendar');
        exit;
    }

    $json = file_get_contents($_FILES['calendar_config_file']['tmp_name']);
    $config = json_decode($json, true);
    if ($config === null) {
        $_SESSION['error'] = 'Invalid JSON file.';
        header('Location: ?action=settings&tab=calendar');
        exit;
    }

    saveCalendarConfig($config);
    $_SESSION['success'] = 'Calendar config uploaded and applied.';
    header('Location: ?action=settings&tab=calendar');
    exit;
}

/**
 * Clear all calendar events from the database.
 */
function handleClearCalendarEvents($pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ?action=settings&tab=calendar');
        exit;
    }

    try {
        $pdo->exec("DELETE FROM entry_scores WHERE entry_type = 'calendar_event'");
        $pdo->exec("DELETE FROM calendar_events");
        $_SESSION['success'] = 'All calendar events cleared.';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to clear calendar events: ' . $e->getMessage();
    }

    header('Location: ?action=settings&tab=calendar');
    exit;
}
