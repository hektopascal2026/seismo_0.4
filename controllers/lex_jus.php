<?php
/**
 * Lex & Jus Controller
 *
 * Handles all legislation (EU / CH / DE) and Swiss case-law (Jus) pages,
 * refresh actions, settings management, and the underlying data fetchers.
 */

// ---------------------------------------------------------------------------
// Pages
// ---------------------------------------------------------------------------

function handleLexPage($pdo) {
    $lexItems = [];
    $lastLexRefreshDate = null;
    $lexCfg = getLexConfig();
    $enabledLexSources = array_values(array_filter(
        ['eu', 'ch', 'de', 'parl_mm'],
        function($s) use ($lexCfg) { return !empty($lexCfg[$s]['enabled']); }
    ));
    
    // Determine active sources from query params (default: all enabled)
    $sourcesSubmitted = isset($_GET['sources_submitted']);
    if ($sourcesSubmitted) {
        $activeSources = isset($_GET['sources']) ? (array)$_GET['sources'] : [];
    } else {
        $activeSources = $enabledLexSources;
    }
    // Strip disabled sources from user selection
    $activeSources = array_values(array_intersect($activeSources, $enabledLexSources));
    
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
        
        $lastRefreshParlStmt = $pdo->query("SELECT MAX(fetched_at) as last_refresh FROM lex_items WHERE source = 'parl_mm'");
        $lastRefreshParlRow = $lastRefreshParlStmt->fetch();
        $lastLexRefreshDateParl = ($lastRefreshParlRow && $lastRefreshParlRow['last_refresh']) 
            ? date('d.m.Y H:i', strtotime($lastRefreshParlRow['last_refresh'])) : null;
        
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
}

function handleJusPage($pdo) {
    $jusItems = [];
    $lexCfg = getLexConfig();
    $enabledJusSources = array_values(array_filter(
        ['ch_bger', 'ch_bge', 'ch_bvger'],
        function($s) use ($lexCfg) { return !empty($lexCfg[$s]['enabled']); }
    ));
    
    // Determine active sources from query params (default: all enabled)
    $sourcesSubmitted = isset($_GET['sources_submitted']);
    if ($sourcesSubmitted) {
        $activeJusSources = isset($_GET['sources']) ? (array)$_GET['sources'] : [];
    } else {
        $activeJusSources = $enabledJusSources;
    }
    // Strip disabled sources from user selection
    $activeJusSources = array_values(array_intersect($activeJusSources, $enabledJusSources));
    
    try {
        if (!empty($activeJusSources)) {
            $placeholders = implode(',', array_fill(0, count($activeJusSources), '?'));
            $stmt = $pdo->prepare("SELECT * FROM lex_items WHERE source IN ($placeholders) ORDER BY document_date DESC LIMIT 200");
            $stmt->execute($activeJusSources);
            $jusItems = filterJusBannedWords($stmt->fetchAll());
            $jusItems = array_slice($jusItems, 0, 50);
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
        
        $lastRefreshBvgerStmt = $pdo->query("SELECT MAX(fetched_at) as last_refresh FROM lex_items WHERE source = 'ch_bvger'");
        $lastRefreshBvgerRow = $lastRefreshBvgerStmt->fetch();
        $lastJusRefreshDateBvger = ($lastRefreshBvgerRow && $lastRefreshBvgerRow['last_refresh']) 
            ? date('d.m.Y H:i', strtotime($lastRefreshBvgerRow['last_refresh'])) : null;
    } catch (PDOException $e) {
        // Table might not exist yet on first load
    }
    
    include 'views/jus.php';
}

// ---------------------------------------------------------------------------
// Refresh actions
// ---------------------------------------------------------------------------

function handleRefreshAllLex($pdo) {
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
    
    if ($lexCfg['parl_mm']['enabled'] ?? false) {
        try {
            $countParl = refreshParlMmItems($pdo);
            $messages[] = "🏛 $countParl items from parlament.ch";
        } catch (Exception $e) {
            $errors[] = '🏛 Parl MM: ' . $e->getMessage();
        }
    } else {
        $messages[] = '🏛 Parl MM skipped (disabled)';
    }
    
    if (!empty($messages)) {
        $_SESSION['success'] = 'Lex refreshed: ' . implode(', ', $messages) . '.';
    }
    if (!empty($errors)) {
        $_SESSION['error'] = 'Errors: ' . implode(' | ', $errors);
    }
    
    header('Location: ?action=lex');
    exit;
}

function handleRefreshAllJus($pdo) {
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
    
    if ($lexCfg['ch_bvger']['enabled'] ?? false) {
        try {
            $countBvger = refreshJusItems($pdo, 'CH_BVGer');
            $messages[] = "⚖️ $countBvger items from BVGer";
        } catch (Exception $e) {
            $errors[] = '⚖️ BVGer: ' . $e->getMessage();
        }
    } else {
        $messages[] = '⚖️ BVGer skipped (disabled)';
    }
    
    if (!empty($messages)) {
        $_SESSION['success'] = 'JUS refreshed: ' . implode(', ', $messages) . '.';
    }
    if (!empty($errors)) {
        $_SESSION['error'] = 'Errors: ' . implode(' | ', $errors);
    }
    
    header('Location: ?action=jus');
    exit;
}

// ---------------------------------------------------------------------------
// Settings actions
// ---------------------------------------------------------------------------

function handleSaveLexConfig($pdo) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $config = getLexConfig();
        $isEnabled = function($field, $default = false) {
            if (!array_key_exists($field, $_POST)) return (bool)$default;
            $raw = $_POST[$field];
            if (is_array($raw)) return !empty($raw);
            $value = strtolower(trim((string)$raw));
            return in_array($value, ['1', 'true', 'yes', 'on'], true);
        };
        $isAutoSave = isset($_POST['autosave']) && $_POST['autosave'] === '1';
        
        // EU settings
        $config['eu']['enabled']        = $isEnabled('eu_enabled', $config['eu']['enabled'] ?? true);
        $config['eu']['language']       = trim($_POST['eu_language'] ?? 'ENG');
        $config['eu']['lookback_days']  = max(1, (int)($_POST['eu_lookback_days'] ?? 90));
        $config['eu']['limit']          = max(1, (int)($_POST['eu_limit'] ?? 100));
        $config['eu']['document_class'] = trim($_POST['eu_document_class'] ?? 'cdm:legislation_secondary');
        $config['eu']['notes']          = trim($_POST['eu_notes'] ?? '');
        
        // CH settings
        $config['ch']['enabled']       = $isEnabled('ch_enabled', $config['ch']['enabled'] ?? true);
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
        $config['de']['enabled']       = $isEnabled('de_enabled', $config['de']['enabled'] ?? true);
        $config['de']['lookback_days'] = max(1, (int)($_POST['de_lookback_days'] ?? 90));
        $config['de']['limit']         = max(1, (int)($_POST['de_limit'] ?? 100));
        $config['de']['notes']         = trim($_POST['de_notes'] ?? '');
        
        // JUS: CH_BGer settings
        $config['ch_bger']['enabled']       = $isEnabled('ch_bger_enabled', $config['ch_bger']['enabled'] ?? false);
        $config['ch_bger']['lookback_days'] = max(1, (int)($_POST['ch_bger_lookback_days'] ?? 90));
        $config['ch_bger']['limit']         = max(1, (int)($_POST['ch_bger_limit'] ?? 100));
        $config['ch_bger']['notes']         = trim($_POST['ch_bger_notes'] ?? '');
        
        // JUS: CH_BGE settings
        $config['ch_bge']['enabled']       = $isEnabled('ch_bge_enabled', $config['ch_bge']['enabled'] ?? false);
        $config['ch_bge']['lookback_days'] = max(1, (int)($_POST['ch_bge_lookback_days'] ?? 90));
        $config['ch_bge']['limit']         = max(1, (int)($_POST['ch_bge_limit'] ?? 50));
        $config['ch_bge']['notes']         = trim($_POST['ch_bge_notes'] ?? '');
        
        // JUS: CH_BVGer settings
        $config['ch_bvger']['enabled']       = $isEnabled('ch_bvger_enabled', $config['ch_bvger']['enabled'] ?? false);
        $config['ch_bvger']['lookback_days'] = max(1, (int)($_POST['ch_bvger_lookback_days'] ?? 90));
        $config['ch_bvger']['limit']         = max(1, (int)($_POST['ch_bvger_limit'] ?? 100));
        $config['ch_bvger']['notes']         = trim($_POST['ch_bvger_notes'] ?? '');
        
        // Parl MM settings
        $config['parl_mm']['enabled']       = $isEnabled('parl_mm_enabled', $config['parl_mm']['enabled'] ?? false);
        $config['parl_mm']['language']      = trim($_POST['parl_mm_language'] ?? 'de');
        $config['parl_mm']['lookback_days'] = max(1, (int)($_POST['parl_mm_lookback_days'] ?? 90));
        $config['parl_mm']['limit']         = max(1, (int)($_POST['parl_mm_limit'] ?? 50));
        $config['parl_mm']['notes']         = trim($_POST['parl_mm_notes'] ?? '');
        
        // JUS: Banned words
        $rawBanned = trim($_POST['jus_banned_words'] ?? '');
        $config['jus_banned_words'] = array_values(array_filter(
            array_map('trim', preg_split('/\r?\n/', $rawBanned)),
            'strlen'
        ));
        
        if (saveLexConfig($config)) {
            if (!$isAutoSave) {
                $_SESSION['success'] = 'Lex configuration saved.';
            }
        } else {
            $_SESSION['error'] = 'Failed to save Lex configuration.';
        }
    }
    header('Location: ' . getBasePath() . '/index.php?action=settings&tab=lex');
    exit;
}

function handleUploadLexConfig($pdo) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['lex_config_file'])) {
        $file = $_FILES['lex_config_file'];
        if ($file['error'] === UPLOAD_ERR_OK && $file['size'] > 0) {
            $content = file_get_contents($file['tmp_name']);
            $parsed = json_decode($content, true);
            if ($parsed !== null && (isset($parsed['eu']) || isset($parsed['ch']) || isset($parsed['de']) || isset($parsed['parl_mm']) || isset($parsed['ch_bger']) || isset($parsed['ch_bge']) || isset($parsed['ch_bvger']))) {
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
}

function handleDownloadLexConfig($pdo) {
    $config = getLexConfig();
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="lex_config.json"');
    echo json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ---------------------------------------------------------------------------
// Data fetchers — EU (EUR-Lex SPARQL)
// ---------------------------------------------------------------------------

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

// ---------------------------------------------------------------------------
// Data fetchers — CH (Fedlex SPARQL)
// ---------------------------------------------------------------------------

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

// ---------------------------------------------------------------------------
// Data fetchers — DE (recht.bund.de RSS)
// ---------------------------------------------------------------------------

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

// ---------------------------------------------------------------------------
// Data fetchers — JUS (entscheidsuche.ch)
// ---------------------------------------------------------------------------

/**
 * Fetch and decode a JSON payload from a URL.
 *
 * @return array|null
 */
function fetchJsonFromUrl($url, $timeout = 30) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Seismo/0.4 (case-law-monitor)',
    ]);
    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || $body === false || $body === '') {
        return null;
    }
    
    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : null;
}

/**
 * Discover a recent complete index manifest and return its actions.
 * Used as a bootstrap fallback when `/last` contains no actionable entries.
 */
function fetchJusBootstrapActions($baseUrl, $spider, $maxFilesToScan = 30) {
    $indexDirUrl = "{$baseUrl}/docs/Index/{$spider}/";
    $ch = curl_init($indexDirUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Seismo/0.4 (case-law-monitor)',
    ]);
    $listingHtml = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || $listingHtml === false || $listingHtml === '') {
        return [];
    }
    
    $pattern = '#/docs/Index/' . preg_quote($spider, '#') . '/(Index_[^"]+\.json)#';
    if (!preg_match_all($pattern, $listingHtml, $matches) || empty($matches[1])) {
        return [];
    }
    
    $files = array_values(array_unique(array_map('rawurldecode', $matches[1])));

    // Fast path: complete manifests are usually much larger than daily incremental files.
    $largePattern = '#/docs/Index/' . preg_quote($spider, '#') . '/(Index_[^"]+\.json)"[^\n]*?<td data-sort="\d+">[^<]*</td><td data-sort="(\d+)"#';
    if (preg_match_all($largePattern, $listingHtml, $largeMatches, PREG_SET_ORDER)) {
        $largeCandidates = [];
        foreach ($largeMatches as $m) {
            $fileName = rawurldecode($m[1]);
            $fileSize = (int)$m[2];
            if ($fileSize >= 200000) {
                $largeCandidates[] = $fileName;
            }
        }
        if (!empty($largeCandidates)) {
            $latestLargeFile = end($largeCandidates);
            $manifest = fetchJsonFromUrl($indexDirUrl . rawurlencode($latestLargeFile), 30);
            if ($manifest && !empty($manifest['actions']) && is_array($manifest['actions'])) {
                return $manifest['actions'];
            }
        }
    }

    $fallbackActions = [];
    $checked = 0;
    
    for ($i = count($files) - 1; $i >= 0 && $checked < $maxFilesToScan; $i--, $checked++) {
        $fileName = $files[$i];
        $manifestUrl = $indexDirUrl . rawurlencode($fileName);
        $manifest = fetchJsonFromUrl($manifestUrl, 30);
        if (!$manifest || empty($manifest['actions']) || !is_array($manifest['actions'])) {
            continue;
        }
        
        // Keep the newest non-empty actions as a fallback.
        if (empty($fallbackActions)) {
            $fallbackActions = $manifest['actions'];
        }
        
        if (strtolower((string)($manifest['jobtyp'] ?? '')) === 'komplett') {
            return $manifest['actions'];
        }
    }
    
    return $fallbackActions;
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
    $sourceKey = match($spider) {
        'CH_BGE' => 'ch_bge',
        'CH_BVGer' => 'ch_bvger',
        default => 'ch_bger',
    };
    $config = getLexConfig();
    $cfg = $config[$sourceKey] ?? [];
    if (!($cfg['enabled'] ?? false)) return 0;
    
    $baseUrl = rtrim($cfg['base_url'] ?? 'https://entscheidsuche.ch', '/');
    $lookback = (int)($cfg['lookback_days'] ?? 90);
    $limit = (int)($cfg['limit'] ?? 100);
    $cutoffDate = date('Y-m-d', strtotime("-{$lookback} days"));
    
    $existingStmt = $pdo->prepare("SELECT COUNT(*) FROM lex_items WHERE source = ?");
    $existingStmt->execute([$sourceKey]);
    $sourceHasEntries = ((int)$existingStmt->fetchColumn() > 0);
    
    // 1. Fetch the latest index manifest
    $indexUrl = "{$baseUrl}/docs/Index/{$spider}/last";
    $index = fetchJsonFromUrl($indexUrl, 30);
    if (!$index) {
        throw new Exception("Failed to fetch index manifest from {$indexUrl}");
    }
    
    if (!isset($index['actions'])) {
        // Empty actions means no changes — not an error
        if (isset($index['spider'])) return 0;
        throw new Exception("Invalid index manifest from {$indexUrl}");
    }
    
    $actions = is_array($index['actions']) ? $index['actions'] : [];
    $usedBootstrapActions = false;
    if (empty($actions) && !$sourceHasEntries) {
        // Bootstrap empty datasets from a recent complete manifest.
        $actions = fetchJusBootstrapActions($baseUrl, $spider);
        $usedBootstrapActions = !empty($actions);
    }
    if (empty($actions)) return 0;
    
    // 2. Filter to new/update actions only, pre-filter by date from filename
    $collectFiles = function($enforceCutoff) use ($actions, $cutoffDate, $limit) {
        $files = [];
        foreach ($actions as $filePath => $action) {
            if ($action === 'delete') continue;
            
            // Extract date from filename: CH_BGer_007_7B-835-2025_2025-09-18.json → 2025-09-18
            if ($enforceCutoff && preg_match('/_(\d{4}-\d{2}-\d{2})\.json$/', $filePath, $m)) {
                $fileDate = $m[1];
                if ($fileDate < $cutoffDate) continue; // Skip old decisions
            }
            
            $files[] = $filePath;
            if (count($files) >= $limit) break;
        }
        return $files;
    };
    
    // BGE publication can lag significantly; on first sync we seed entries first, then enforce lookback on later runs.
    $disableDateCutoffForFirstSync = (!$sourceHasEntries && ($sourceKey === 'ch_bge' || $sourceKey === 'ch_bvger' || $usedBootstrapActions));
    $enforceDateCutoff = !$disableDateCutoffForFirstSync;
    $filesToFetch = $collectFiles($enforceDateCutoff);
    if (empty($filesToFetch) && !$sourceHasEntries) {
        // First sync fallback: allow older decisions so the source is not permanently empty.
        $filesToFetch = $collectFiles(false);
        $enforceDateCutoff = false;
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
            if ($enforceDateCutoff && $datum && $datum < $cutoffDate) continue;
            
            // Map fields
            $signatur = $decision['Signatur'] ?? '';
            
            // Title: prefer Abstract (case topic), fall back to Num, then Kopfzeile
            $abstract = $decision['Abstract'][0]['Text'] ?? '';
            $nums = $decision['Num'] ?? [];
            $caseNum = is_array($nums) ? ($nums[0] ?? '') : ($nums ?: '');
            $kopfzeile = $decision['Kopfzeile'][0]['Text'] ?? '';
            
            if (!empty($abstract) && !empty($caseNum)) {
                $title = $caseNum . ' — ' . $abstract;
            } elseif (!empty($abstract)) {
                $title = $abstract;
            } elseif (!empty($caseNum)) {
                $title = $caseNum;
            } elseif (!empty($kopfzeile)) {
                $title = $kopfzeile;
            } else {
                $title = $slug;
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
                $sourceKey,     // source: ch_bger, ch_bge, or ch_bvger
            ]);
            $count++;
        }
    }
    
    curl_multi_close($mh);
    return $count;
}

// ---------------------------------------------------------------------------
// Data fetchers — Parl MM (parlament.ch press releases via SharePoint API)
// ---------------------------------------------------------------------------

/**
 * Refresh parliamentary press releases from parlament.ch.
 * Uses the SharePoint REST API to query the Pages list for recent press releases.
 */
function refreshParlMmItems($pdo) {
    $config = getLexConfig();
    $cfg = $config['parl_mm'] ?? [];
    if (!($cfg['enabled'] ?? false)) return 0;

    $lookback = (int)($cfg['lookback_days'] ?? 90);
    $limit    = (int)($cfg['limit'] ?? 50);
    $lang     = $cfg['language'] ?? 'de';
    $apiBase  = $cfg['api_base']
        ?? "https://www.parlament.ch/press-releases/_api/web/lists/getByTitle('Pages')/items";

    $sinceDate = date('Y-m-d\TH:i:s\Z', strtotime("-{$lookback} days"));

    $langField = 'Title_' . $lang;
    $contentField = 'Content_' . $lang;

    $select = "Title,{$langField},{$contentField},FileRef,Created,ArticleStartDate,ContentType/Name";
    $filter = "Created ge datetime'{$sinceDate}'";
    $orderBy = 'Created desc';

    $url = $apiBase
        . '?$top=' . $limit
        . '&$orderby=' . rawurlencode($orderBy)
        . '&$filter=' . rawurlencode($filter)
        . '&$select=' . rawurlencode($select)
        . '&$expand=ContentType';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => ['Accept: application/json;odata=verbose'],
        CURLOPT_USERAGENT      => 'Seismo/0.4 (parliament-monitor)',
    ]);
    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($body === false || empty($body)) {
        throw new Exception("Failed to fetch parlament.ch press releases: " . ($curlError ?: "empty response, HTTP {$httpCode}"));
    }
    if ($httpCode !== 200) {
        throw new Exception("parlament.ch API returned HTTP {$httpCode}");
    }

    $data = json_decode($body, true);
    if (!$data) {
        throw new Exception("Failed to parse parlament.ch JSON response");
    }

    // Handle both OData v3 (d.results) and v4 (value) response formats
    $items = $data['value'] ?? $data['d']['results'] ?? [];
    if (empty($items)) return 0;

    $upsert = $pdo->prepare("
        INSERT INTO lex_items (celex, title, document_date, document_type, eurlex_url, work_uri, source)
        VALUES (?, ?, ?, ?, ?, ?, 'parl_mm')
        ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            document_date = VALUES(document_date),
            document_type = VALUES(document_type),
            eurlex_url = VALUES(eurlex_url),
            work_uri = VALUES(work_uri),
            source = 'parl_mm',
            fetched_at = CURRENT_TIMESTAMP
    ");

    $count = 0;
    foreach ($items as $item) {
        $slug = $item['Title'] ?? '';
        if (empty($slug)) continue;

        $title = $item[$langField] ?? $slug;
        $fileRef = $item['FileRef'] ?? '';

        // Parse date: prefer ArticleStartDate, fall back to Created
        $rawDate = $item['ArticleStartDate'] ?? $item['Created'] ?? null;
        $docDate = null;
        if ($rawDate) {
            $ts = strtotime($rawDate);
            if ($ts !== false) $docDate = date('Y-m-d', $ts);
        }

        $contentType = $item['ContentType']['Name'] ?? 'Press Release';
        $pageUrl = 'https://www.parlament.ch' . $fileRef;
        $parlLabel = parseParlMmCommission($slug);

        // Strip HTML from content field for a plain-text summary
        $rawContent = $item[$contentField] ?? '';
        $plainContent = '';
        if (!empty($rawContent)) {
            $plainContent = trim(strip_tags(html_entity_decode($rawContent, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        }

        $upsert->execute([
            'parl_mm:' . $slug,
            $title,
            $docDate,
            $parlLabel,
            $pageUrl,
            $plainContent ?: $pageUrl,
            // source = 'parl_mm' is hard-coded in the SQL
        ]);
        $count++;
    }

    return $count;
}

/**
 * Extract the commission abbreviation from a press-release slug.
 * Slugs follow the pattern: mm-{commission}-{council}-{date}
 * e.g. "mm-fk-n-2026-02-20" → "FK-N", "mm-sgk-s-2026-02-20" → "SGK-S"
 */
function parseParlMmCommission($slug) {
    if (preg_match('/^mm-([a-z]+)-([nsr])-\d{4}/i', $slug, $m)) {
        return strtoupper($m[1]) . '-' . strtoupper($m[2]);
    }
    if (preg_match('/^mm-([a-z]+)-\d{4}/i', $slug, $m)) {
        return strtoupper($m[1]);
    }
    if (str_starts_with($slug, 'info-')) {
        return 'Info';
    }
    return 'Medienmitteilung';
}
