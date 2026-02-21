<?php
/**
 * Database Configuration
 * Credentials are loaded from config.local.php (gitignored).
 * Copy config.local.php.example to config.local.php and fill in your values.
 */
$localConfig = __DIR__ . '/config.local.php';
if (file_exists($localConfig)) {
    require $localConfig;
} else {
    http_response_code(503);
    die('Missing config.local.php — copy config.local.php.example and fill in your database credentials.');
}

/**
 * Application Settings
 */
define('CACHE_DURATION', 3600); // Cache feeds for 1 hour (in seconds)

/**
 * Database Connection
 */
function getDbConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            error_log("Seismo DB connection failed: " . $e->getMessage());
            http_response_code(503);
            echo '<!DOCTYPE html><html><head><title>Service Unavailable</title></head><body style="font-family:sans-serif;text-align:center;padding:60px 20px;"><h1>Service Unavailable</h1><p>Database connection failed. Please try again later.</p></body></html>';
            exit;
        }
    }
    
    return $pdo;
}

/**
 * Current schema version — bump this when DDL changes are made
 */
define('SCHEMA_VERSION', 12);

/**
 * Initialize database tables
 */
function initDatabase() {
    $pdo = getDbConnection();
    
    // Schema version guard: skip DDL if already up to date
    try {
        $vStmt = $pdo->query("SELECT config_value FROM magnitu_config WHERE config_key = 'schema_version'");
        $currentVersion = $vStmt ? $vStmt->fetchColumn() : false;
        if ($currentVersion !== false && (int)$currentVersion >= SCHEMA_VERSION) {
            return; // Schema is up to date, skip DDL
        }
    } catch (PDOException $e) {
        // Table doesn't exist yet — continue with full init
    }
    
    // Create feeds table
    $pdo->exec("CREATE TABLE IF NOT EXISTS feeds (
        id INT AUTO_INCREMENT PRIMARY KEY,
        url VARCHAR(500) NOT NULL UNIQUE,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        link VARCHAR(500),
        category VARCHAR(100) DEFAULT NULL,
        last_fetched DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_url (url),
        INDEX idx_category (category),
        INDEX idx_last_fetched (last_fetched)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Add category column if it doesn't exist (for existing installations)
    try {
        $pdo->exec("ALTER TABLE feeds ADD COLUMN category VARCHAR(100) DEFAULT NULL AFTER link");
    } catch (PDOException $e) {
        // Column might already exist, ignore error
        if (strpos($e->getMessage(), 'Duplicate column name') === false) {
            // Re-throw if it's a different error
            throw $e;
        }
    }
    
    // Add category index if it doesn't exist
    try {
        $pdo->exec("CREATE INDEX idx_category ON feeds(category)");
    } catch (PDOException $e) {
        // Index might already exist, ignore error
    }
    
    // Add disabled column if it doesn't exist (for existing installations)
    try {
        $pdo->exec("ALTER TABLE feeds ADD COLUMN disabled TINYINT(1) DEFAULT 0 AFTER category");
    } catch (PDOException $e) {
        // Column might already exist, ignore error
        if (strpos($e->getMessage(), 'Duplicate column name') === false) {
            // Re-throw if it's a different error
            throw $e;
        }
    }
    
    // Add disabled index if it doesn't exist
    try {
        $pdo->exec("CREATE INDEX idx_disabled ON feeds(disabled)");
    } catch (PDOException $e) {
        // Index might already exist, ignore error
    }
    
    // Circuit breaker columns for feed error tracking
    try {
        $pdo->exec("ALTER TABLE feeds ADD COLUMN consecutive_failures INT NOT NULL DEFAULT 0");
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') === false) throw $e;
    }
    try {
        $pdo->exec("ALTER TABLE feeds ADD COLUMN last_error TEXT DEFAULT NULL");
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') === false) throw $e;
    }
    try {
        $pdo->exec("ALTER TABLE feeds ADD COLUMN last_error_at DATETIME DEFAULT NULL");
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') === false) throw $e;
    }

    // Add source_type column if it doesn't exist (for Substack support)
    try {
        $pdo->exec("ALTER TABLE feeds ADD COLUMN source_type VARCHAR(20) DEFAULT 'rss' AFTER url");
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') === false) {
            throw $e;
        }
    }
    
    // Fix Substack feeds that still have the generic "substack" category — set to their title
    $pdo->exec("UPDATE feeds SET category = title WHERE source_type = 'substack' AND category = 'substack'");
    
    // Create feed_items table
    $pdo->exec("CREATE TABLE IF NOT EXISTS feed_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        feed_id INT NOT NULL,
        guid VARCHAR(500) NOT NULL,
        title VARCHAR(500) NOT NULL,
        link VARCHAR(500),
        description TEXT,
        content TEXT,
        author VARCHAR(255),
        published_date DATETIME,
        cached_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_feed_id (feed_id),
        INDEX idx_guid (guid(255)),
        INDEX idx_published (published_date),
        UNIQUE KEY unique_feed_guid (feed_id, guid(255)),
        FOREIGN KEY (feed_id) REFERENCES feeds(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Create emails table
    $pdo->exec("CREATE TABLE IF NOT EXISTS emails (
        id INT AUTO_INCREMENT PRIMARY KEY,
        subject VARCHAR(500) DEFAULT NULL,
        from_email VARCHAR(255) DEFAULT NULL,
        from_name VARCHAR(255) DEFAULT NULL,
        text_body TEXT,
        html_body TEXT,
        date_received DATETIME DEFAULT NULL,
        date_sent DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_created_at (created_at),
        INDEX idx_from_email (from_email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Add missing columns to emails table if they don't exist (for existing installations)
    try {
        // Check which columns exist
        $existingColumns = [];
        $columnCheck = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'emails'");
        foreach ($columnCheck->fetchAll(PDO::FETCH_COLUMN) as $col) {
            $existingColumns[] = $col;
        }
        
        $emailColumns = [
            'subject' => "ALTER TABLE emails ADD COLUMN subject VARCHAR(500) DEFAULT NULL AFTER id",
            'from_email' => "ALTER TABLE emails ADD COLUMN from_email VARCHAR(255) DEFAULT NULL AFTER subject",
            'from_name' => "ALTER TABLE emails ADD COLUMN from_name VARCHAR(255) DEFAULT NULL AFTER from_email",
            'text_body' => "ALTER TABLE emails ADD COLUMN text_body TEXT AFTER from_name",
            'html_body' => "ALTER TABLE emails ADD COLUMN html_body TEXT AFTER text_body",
            'date_received' => "ALTER TABLE emails ADD COLUMN date_received DATETIME DEFAULT NULL AFTER html_body",
            'date_sent' => "ALTER TABLE emails ADD COLUMN date_sent DATETIME DEFAULT NULL AFTER date_received",
            'created_at' => "ALTER TABLE emails ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER date_sent"
        ];
        
        foreach ($emailColumns as $column => $sql) {
            if (!in_array($column, $existingColumns)) {
                try {
                    $pdo->exec($sql);
                } catch (PDOException $e) {
                    // Ignore if column already exists or other non-critical errors
                    if (strpos($e->getMessage(), 'Duplicate column name') === false) {
                        // Log but don't fail for other errors
                    }
                }
            }
        }
    } catch (PDOException $e) {
        // If INFORMATION_SCHEMA query fails, try to add columns anyway (will fail gracefully if they exist)
        // This is a fallback for older MySQL versions or permission issues
    }
    
    // Create sender_tags table for managing email sender tags
    $pdo->exec("CREATE TABLE IF NOT EXISTS sender_tags (
        id INT AUTO_INCREMENT PRIMARY KEY,
        from_email VARCHAR(255) NOT NULL UNIQUE,
        tag VARCHAR(100) DEFAULT NULL,
        disabled TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_from_email (from_email),
        INDEX idx_tag (tag),
        INDEX idx_disabled (disabled)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Add disabled column if it doesn't exist (for existing installations)
    try {
        $pdo->exec("ALTER TABLE sender_tags ADD COLUMN disabled TINYINT(1) DEFAULT 0 AFTER tag");
    } catch (PDOException $e) {
        // Column might already exist, ignore error
        if (strpos($e->getMessage(), 'Duplicate column name') === false) {
            throw $e;
        }
    }
    
    // Add removed_at column if it doesn't exist (for existing installations)
    try {
        $pdo->exec("ALTER TABLE sender_tags ADD COLUMN removed_at DATETIME DEFAULT NULL AFTER disabled");
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') === false) {
            throw $e;
        }
    }
    
    // Create lex_items table for EU + CH legislation tracking + JUS (case law)
    $pdo->exec("CREATE TABLE IF NOT EXISTS lex_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        celex VARCHAR(100) NOT NULL UNIQUE,
        title TEXT,
        document_date DATE DEFAULT NULL,
        document_type VARCHAR(100) DEFAULT NULL,
        eurlex_url VARCHAR(500) DEFAULT NULL,
        work_uri VARCHAR(500) DEFAULT NULL,
        source VARCHAR(20) DEFAULT 'eu',
        fetched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_celex (celex),
        INDEX idx_document_date (document_date),
        INDEX idx_document_type (document_type),
        INDEX idx_source (source)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Add source column to lex_items if it doesn't exist (for existing installations)
    try {
        $pdo->exec("ALTER TABLE lex_items ADD COLUMN source VARCHAR(20) DEFAULT 'eu' AFTER work_uri");
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') === false) {
            throw $e;
        }
    }
    
    // Widen source column to VARCHAR(20) for JUS sources (ch_bger, ch_bge, etc.)
    try {
        $pdo->exec("ALTER TABLE lex_items MODIFY COLUMN source VARCHAR(20) DEFAULT 'eu'");
    } catch (PDOException $e) {
        // Ignore if it fails
    }
    
    // Add source index if it doesn't exist
    try {
        $pdo->exec("CREATE INDEX idx_source ON lex_items(source)");
    } catch (PDOException $e) {
        // Index might already exist, ignore
    }
    
    // Widen celex column to support longer Fedlex ELI identifiers and SharePoint page slugs
    try {
        $pdo->exec("ALTER TABLE lex_items MODIFY COLUMN celex VARCHAR(255) NOT NULL");
    } catch (PDOException $e) {
        // Ignore if it fails
    }
    
    // Create calendar_events table for upcoming events (parliament sessions, publications, etc.)
    $pdo->exec("CREATE TABLE IF NOT EXISTS calendar_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        source VARCHAR(50) NOT NULL,
        external_id VARCHAR(255) DEFAULT NULL,
        title TEXT,
        description TEXT,
        content TEXT,
        event_date DATE DEFAULT NULL,
        event_end_date DATE DEFAULT NULL,
        event_type VARCHAR(50) DEFAULT NULL,
        status VARCHAR(30) DEFAULT 'scheduled',
        council VARCHAR(10) DEFAULT NULL,
        url VARCHAR(500) DEFAULT NULL,
        metadata JSON DEFAULT NULL,
        fetched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_source_ext (source, external_id),
        INDEX idx_source (source),
        INDEX idx_event_date (event_date),
        INDEX idx_event_type (event_type),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Create entry_scores table for Magnitu ML predictions
    $pdo->exec("CREATE TABLE IF NOT EXISTS entry_scores (
        id INT AUTO_INCREMENT PRIMARY KEY,
        entry_type ENUM('feed_item', 'email', 'lex_item') NOT NULL,
        entry_id INT NOT NULL,
        relevance_score FLOAT DEFAULT 0.0,
        predicted_label VARCHAR(50) DEFAULT NULL,
        explanation JSON DEFAULT NULL,
        score_source ENUM('magnitu', 'recipe') DEFAULT 'recipe',
        model_version INT DEFAULT 0,
        scored_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_entry (entry_type, entry_id),
        INDEX idx_entry_type_id (entry_type, entry_id),
        INDEX idx_relevance (relevance_score),
        INDEX idx_predicted_label (predicted_label)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Create magnitu_labels table for syncing user labels across Magnitu instances
    $pdo->exec("CREATE TABLE IF NOT EXISTS magnitu_labels (
        id INT AUTO_INCREMENT PRIMARY KEY,
        entry_type ENUM('feed_item', 'email', 'lex_item') NOT NULL,
        entry_id INT NOT NULL,
        label VARCHAR(50) NOT NULL,
        labeled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_label (entry_type, entry_id),
        INDEX idx_entry (entry_type, entry_id),
        INDEX idx_label (label)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Create magnitu_config table for scoring recipe and connection settings
    $pdo->exec("CREATE TABLE IF NOT EXISTS magnitu_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        config_key VARCHAR(100) NOT NULL UNIQUE,
        config_value MEDIUMTEXT DEFAULT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_key (config_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Widen entry_type ENUM on entry_scores and magnitu_labels to include calendar_event
    try {
        $pdo->exec("ALTER TABLE entry_scores MODIFY COLUMN entry_type ENUM('feed_item', 'email', 'lex_item', 'calendar_event') NOT NULL");
    } catch (PDOException $e) {
        // Already widened or other non-critical error
    }
    try {
        $pdo->exec("ALTER TABLE magnitu_labels MODIFY COLUMN entry_type ENUM('feed_item', 'email', 'lex_item', 'calendar_event') NOT NULL");
    } catch (PDOException $e) {
        // Already widened or other non-critical error
    }

    // Add content_hash column to feed_items for scraper deduplication
    try {
        $pdo->exec("ALTER TABLE feed_items ADD COLUMN content_hash VARCHAR(32) DEFAULT NULL");
    } catch (PDOException $e) {
        // Column already exists
    }
    
    // Add hidden column to feed_items for soft-delete (scraper items etc.)
    try {
        $pdo->exec("ALTER TABLE feed_items ADD COLUMN hidden TINYINT(1) NOT NULL DEFAULT 0");
    } catch (PDOException $e) {
        // Column already exists
    }

    // Add reasoning column to magnitu_labels for annotation context
    try {
        $pdo->exec("ALTER TABLE magnitu_labels ADD COLUMN reasoning TEXT DEFAULT NULL AFTER label");
    } catch (PDOException $e) {
        // Column already exists
    }

    // Create scraper_configs table for web page scraper targets
    $pdo->exec("CREATE TABLE IF NOT EXISTS scraper_configs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        url VARCHAR(500) NOT NULL UNIQUE,
        link_pattern VARCHAR(500) DEFAULT NULL,
        date_selector VARCHAR(500) DEFAULT NULL,
        category VARCHAR(100) DEFAULT 'scraper',
        disabled TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_url (url),
        INDEX idx_disabled (disabled)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Add link_pattern column if it doesn't exist (for existing installations)
    try {
        $pdo->exec("ALTER TABLE scraper_configs ADD COLUMN link_pattern VARCHAR(500) DEFAULT NULL AFTER url");
    } catch (PDOException $e) {
        // Column already exists
    }
    
    // Add date_selector column for extracting publication dates from scraped pages
    try {
        $pdo->exec("ALTER TABLE scraper_configs ADD COLUMN date_selector VARCHAR(500) DEFAULT NULL AFTER link_pattern");
    } catch (PDOException $e) {
        // Column already exists
    }

    // Add missing indexes for commonly queried columns
    $missingIndexes = [
        "CREATE INDEX idx_source_type ON feeds(source_type)",
        "CREATE INDEX idx_removed_at ON sender_tags(removed_at)",
        "CREATE INDEX idx_score_source ON entry_scores(score_source)",
        "CREATE INDEX idx_date_received ON emails(date_received)",
    ];
    foreach ($missingIndexes as $indexSql) {
        try {
            $pdo->exec($indexSql);
        } catch (PDOException $e) {
            // Index already exists — ignore
        }
    }
    
    // Seed default Magnitu config values (ignore if already exist)
    $magnituDefaults = [
        ['api_key', bin2hex(random_bytes(16))],
        ['alert_threshold', '0.75'],
        ['sort_by_relevance', '0'],
        ['recipe_json', ''],
        ['recipe_version', '0'],
        ['last_sync_at', ''],
    ];
    $insertConfig = $pdo->prepare("INSERT IGNORE INTO magnitu_config (config_key, config_value) VALUES (?, ?)");
    foreach ($magnituDefaults as $row) {
        $insertConfig->execute($row);
    }
    
    // Seed default mail fetcher config values (ignore if already exist)
    $mailDefaults = [
        ['mail_imap_mailbox', '{imap.example.com:993/imap/ssl}INBOX'],
        ['mail_imap_username', ''],
        ['mail_imap_password', ''],
        ['mail_max_messages', '50'],
        ['mail_search_criteria', 'UNSEEN'],
        ['mail_mark_seen', '1'],
        ['mail_db_table', 'fetched_emails'],
    ];
    foreach ($mailDefaults as $row) {
        $insertConfig->execute($row);
    }
    
    // Clear stale failure counters on scraper feeds (they should never be RSS-refreshed)
    try {
        $pdo->exec("UPDATE feeds SET consecutive_failures = 0, last_error = NULL, last_error_at = NULL WHERE source_type = 'scraper' AND consecutive_failures > 0");
    } catch (PDOException $e) {}

    // Mark schema as up to date so DDL is skipped on subsequent requests
    $pdo->prepare("INSERT INTO magnitu_config (config_key, config_value) VALUES ('schema_version', ?)
        ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)")
        ->execute([(string)SCHEMA_VERSION]);
}

/**
 * Resolve the email table name. Checks for fetched_emails (cronjob default),
 * then emails/email, then any table containing "mail" or "email".
 * Result is cached per request so SHOW TABLES only runs once.
 */
function getEmailTableName($pdo) {
    static $cached = null;
    if ($cached !== null) return $cached;

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $t) {
        if (strtolower($t) === 'fetched_emails') return $cached = $t;
    }
    foreach ($tables as $t) {
        if (strtolower($t) === 'emails' || strtolower($t) === 'email') return $cached = $t;
    }
    foreach ($tables as $t) {
        if (stripos($t, 'mail') !== false || stripos($t, 'email') !== false) return $cached = $t;
    }
    return $cached = 'fetched_emails';
}

/**
 * Magnitu config helpers
 */
function getMagnituConfig($pdo, $key) {
    $stmt = $pdo->prepare("SELECT config_value FROM magnitu_config WHERE config_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['config_value'] : null;
}

function setMagnituConfig($pdo, $key, $value) {
    $stmt = $pdo->prepare("INSERT INTO magnitu_config (config_key, config_value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)");
    $stmt->execute([$key, $value]);
}

function getAllMagnituConfig($pdo) {
    $stmt = $pdo->query("SELECT config_key, config_value FROM magnitu_config");
    $config = [];
    foreach ($stmt->fetchAll() as $row) {
        $config[$row['config_key']] = $row['config_value'];
    }
    return $config;
}

/**
 * Validate Magnitu API key from request headers or query parameter.
 * Returns true if valid, false otherwise.
 * Supports multiple header sources for CGI/FastCGI hosting compatibility.
 */
function validateMagnituApiKey($pdo) {
    $apiKey = getMagnituConfig($pdo, 'api_key');
    if (empty($apiKey)) return false;
    
    // Try multiple sources for the Authorization header (CGI/FastCGI compatibility)
    $authHeader = '';
    
    // 1. Standard getallheaders() (works with Apache module)
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }
    
    // 2. CGI/FastCGI: check $_SERVER superglobal fallbacks
    if (empty($authHeader) && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    }
    if (empty($authHeader) && !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
    
    // Validate Bearer token if found
    if (!empty($authHeader) && preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
        return hash_equals($apiKey, $matches[1]);
    }
    
    // Fallback: check query parameter or POST body
    $queryKey = $_GET['api_key'] ?? $_POST['api_key'] ?? '';
    if (!empty($queryKey)) {
        return hash_equals($apiKey, $queryKey);
    }
    
    return false;
}

/**
 * Score a single entry using the current recipe JSON.
 * Returns [relevance_score, predicted_label, explanation] or null if no recipe.
 */
function scoreEntryWithRecipe($recipeData, $title, $content, $sourceType = '') {
    if (empty($recipeData) || empty($recipeData['keywords'])) {
        return null;
    }
    
    $classes = $recipeData['classes'] ?? ['investigation_lead', 'important', 'background', 'noise'];
    $classWeights = $recipeData['class_weights'] ?? [1.0, 0.66, 0.33, 0.0];
    $keywords = $recipeData['keywords'] ?? [];
    $sourceWeights = $recipeData['source_weights'] ?? [];
    
    // Combine and normalize text
    $text = mb_strtolower(trim($title . ' ' . $content));
    // Simple tokenization: split on non-alphanumeric
    $words = preg_split('/[^a-zA-ZäöüàéèêïôùûçÄÖÜÀÉÈÊÏÔÙÛÇß0-9]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
    
    // Build bigrams
    $tokens = $words;
    for ($i = 0; $i < count($words) - 1; $i++) {
        $tokens[] = $words[$i] . ' ' . $words[$i + 1];
    }
    
    // Calculate raw scores per class
    $classScores = array_fill_keys($classes, 0.0);
    $topFeatures = [];
    
    foreach ($tokens as $token) {
        if (isset($keywords[$token])) {
            foreach ($keywords[$token] as $class => $weight) {
                if (isset($classScores[$class])) {
                    $classScores[$class] += $weight;
                    // Track feature contributions
                    if (!isset($topFeatures[$token])) {
                        $topFeatures[$token] = ['feature' => $token, 'weight' => 0, 'class' => $class];
                    }
                    $topFeatures[$token]['weight'] += $weight;
                }
            }
        }
    }
    
    // Add source weights
    if (!empty($sourceType) && isset($sourceWeights[$sourceType])) {
        foreach ($sourceWeights[$sourceType] as $class => $weight) {
            if (isset($classScores[$class])) {
                $classScores[$class] += $weight;
            }
        }
    }
    
    // Softmax to get probabilities
    $maxScore = max($classScores) ?: 0;
    $expScores = [];
    $expSum = 0;
    foreach ($classes as $class) {
        $exp = exp(($classScores[$class] ?? 0) - $maxScore);
        $expScores[$class] = $exp;
        $expSum += $exp;
    }
    
    $probabilities = [];
    foreach ($classes as $class) {
        $probabilities[$class] = $expSum > 0 ? $expScores[$class] / $expSum : (1.0 / count($classes));
    }
    
    // Composite relevance score
    $relevanceScore = 0.0;
    foreach ($classes as $i => $class) {
        $relevanceScore += $probabilities[$class] * ($classWeights[$i] ?? 0);
    }
    
    // Find predicted label (highest probability)
    $predictedLabel = $classes[0];
    $maxProb = 0;
    foreach ($probabilities as $class => $prob) {
        if ($prob > $maxProb) {
            $maxProb = $prob;
            $predictedLabel = $class;
        }
    }
    
    // Build explanation: top 5 contributing features
    usort($topFeatures, function($a, $b) {
        return abs($b['weight']) <=> abs($a['weight']);
    });
    $explanation = array_slice(array_values($topFeatures), 0, 5);
    foreach ($explanation as &$feat) {
        $feat['direction'] = $feat['weight'] >= 0 ? 'positive' : 'negative';
        $feat['weight'] = round($feat['weight'], 3);
    }
    unset($feat);
    
    return [
        'relevance_score' => round($relevanceScore, 4),
        'predicted_label' => $predictedLabel,
        'explanation' => [
            'top_features' => $explanation,
            'confidence' => round($maxProb, 3),
            'prediction' => $predictedLabel,
        ],
    ];
}

/**
 * Get base URL path for assets
 */
function getBasePath() {
    $path = dirname($_SERVER['PHP_SELF']);
    return $path === '/' ? '' : $path;
}

/**
 * Lex config file path
 */
define('LEX_CONFIG_PATH', __DIR__ . '/lex_config.json');

/**
 * Get the current Lex config (EU + CH SPARQL parameters).
 * Returns the parsed JSON config, or a sensible default if the file doesn't exist.
 */
function getLexConfig() {
    if (file_exists(LEX_CONFIG_PATH)) {
        $json = file_get_contents(LEX_CONFIG_PATH);
        $config = json_decode($json, true);
        if ($config !== null) {
            // Fix legacy DE feed URL that included wrong path segment
            if (isset($config['de']['feed_url']) && 
                strpos($config['de']['feed_url'], '/de/serviceseiten/rss/rss/feeds/') !== false) {
                $config['de']['feed_url'] = str_replace(
                    '/de/serviceseiten/rss/rss/feeds/',
                    '/rss/feeds/',
                    $config['de']['feed_url']
                );
            }
            return $config;
        }
    }
    // Fallback defaults
    return [
        'eu' => [
            'enabled' => true,
            'endpoint' => 'https://publications.europa.eu/webapi/rdf/sparql',
            'language' => 'ENG',
            'lookback_days' => 90,
            'limit' => 100,
            'document_class' => 'cdm:legislation_secondary',
            'notes' => '',
        ],
        'ch' => [
            'enabled' => true,
            'endpoint' => 'https://fedlex.data.admin.ch/sparqlendpoint',
            'language' => 'DEU',
            'lookback_days' => 90,
            'limit' => 100,
            'resource_types' => [
                ['id' => 21, 'label' => 'Bundesgesetz'],
                ['id' => 22, 'label' => 'Dringliches Bundesgesetz'],
                ['id' => 29, 'label' => 'Verordnung des Bundesrates'],
                ['id' => 26, 'label' => 'Departementsverordnung'],
                ['id' => 27, 'label' => 'Amtsverordnung'],
                ['id' => 28, 'label' => 'Verordnung der Bundesversammlung'],
                ['id' => 8,  'label' => 'Einfacher Bundesbeschluss (andere)'],
                ['id' => 9,  'label' => 'Bundesbeschluss (fakultatives Referendum)'],
                ['id' => 10, 'label' => 'Bundesbeschluss (obligatorisches Referendum)'],
                ['id' => 31, 'label' => 'Internationaler Rechtstext bilateral'],
                ['id' => 32, 'label' => 'Internationaler Rechtstext multilateral'],
            ],
            'notes' => '',
        ],
        'de' => [
            'enabled' => true,
            'feed_url' => 'https://www.recht.bund.de/rss/feeds/rss_bgbl-1-2.xml?nn=211452',
            'lookback_days' => 90,
            'limit' => 100,
            'notes' => '',
        ],
        'ch_bger' => [
            'enabled' => true,
            'base_url' => 'https://entscheidsuche.ch',
            'lookback_days' => 90,
            'limit' => 100,
            'notes' => '',
        ],
        'ch_bge' => [
            'enabled' => false,
            'base_url' => 'https://entscheidsuche.ch',
            'lookback_days' => 90,
            'limit' => 50,
            'notes' => '',
        ],
        'ch_bvger' => [
            'enabled' => true,
            'base_url' => 'https://entscheidsuche.ch',
            'lookback_days' => 90,
            'limit' => 100,
            'notes' => '',
        ],
        'parl_mm' => [
            'enabled' => false,
            'api_base' => 'https://www.parlament.ch/press-releases/_api/web/lists/getByTitle(\'Pages\')/items',
            'language' => 'de',
            'lookback_days' => 90,
            'limit' => 50,
            'notes' => '',
        ],
    ];
}

/**
 * Get JUS banned words from the lex config.
 * Returns an array of lowercase words/phrases to filter out.
 */
function getJusBannedWords() {
    $config = getLexConfig();
    $words = $config['jus_banned_words'] ?? [];
    if (!is_array($words)) return [];
    return array_values(array_filter(array_map(function($w) {
        return mb_strtolower(trim($w));
    }, $words), 'strlen'));
}

/**
 * Filter out JUS items whose title contains any banned word (case-insensitive).
 * Works on arrays of lex_items rows. Non-JUS sources are passed through.
 */
function filterJusBannedWords(array $items, ?array $bannedWords = null) {
    if ($bannedWords === null) $bannedWords = getJusBannedWords();
    if (empty($bannedWords)) return $items;
    $jusSources = ['ch_bger', 'ch_bge', 'ch_bvger'];
    return array_values(array_filter($items, function($item) use ($bannedWords, $jusSources) {
        $source = $item['source'] ?? 'eu';
        if (!in_array($source, $jusSources)) return true;
        $title = mb_strtolower($item['title'] ?? '');
        foreach ($bannedWords as $word) {
            if (mb_strpos($title, $word) !== false) return false;
        }
        return true;
    }));
}

/**
 * Map a BGer/BGE Signatur code to a human-readable chamber label.
 * Falls back to the raw signatur if unknown.
 */
function getJusChamberLabel($signatur) {
    static $map = [
        'CH_BGer_001' => 'I. öffentlich-rechtliche Abteilung',
        'CH_BGer_002' => 'II. öffentlich-rechtliche Abteilung',
        'CH_BGer_004' => 'I. zivilrechtliche Abteilung',
        'CH_BGer_005' => 'II. zivilrechtliche Abteilung',
        'CH_BGer_006' => 'Strafrechtliche Abteilung',
        'CH_BGer_007' => 'Beschwerdekammer Strafrecht',
        'CH_BGer_008' => 'I. sozialrechtliche Abteilung',
        'CH_BGer_009' => 'II. sozialrechtliche Abteilung',
        'CH_BGer_012' => 'Vereinigte Abteilungen',
        'CH_BGE_001' => 'I. öffentlich-rechtliche Abteilung',
        'CH_BGE_002' => 'II. öffentlich-rechtliche Abteilung',
        'CH_BGE_004' => 'I. zivilrechtliche Abteilung',
        'CH_BGE_005' => 'II. zivilrechtliche Abteilung',
        'CH_BGE_006' => 'Strafrechtliche Abteilung',
        'CH_BGE_007' => 'Beschwerdekammer Strafrecht',
        'CH_BGE_008' => 'I. sozialrechtliche Abteilung',
        'CH_BGE_009' => 'II. sozialrechtliche Abteilung',
        'CH_BGE_012' => 'Vereinigte Abteilungen',
        'CH_BGE_999' => 'Nicht publiziert',
        'CH_BVGE_001' => 'Bundesverwaltungsgericht',
    ];
    return $map[$signatur] ?? $signatur;
}

/**
 * Calendar config file path
 */
define('CALENDAR_CONFIG_PATH', __DIR__ . '/calendar_config.json');

/**
 * Get the current calendar config (parliament + other event sources).
 * Returns the parsed JSON config, or sensible defaults if the file doesn't exist.
 */
function getCalendarConfig() {
    if (file_exists(CALENDAR_CONFIG_PATH)) {
        $json = file_get_contents(CALENDAR_CONFIG_PATH);
        $config = json_decode($json, true);
        if ($config !== null) return $config;
    }
    return [
        'parliament_ch' => [
            'enabled' => true,
            'api_base' => 'https://ws.parlament.ch/odata.svc',
            'language' => 'DE',
            'lookforward_days' => 90,
            'lookback_days' => 7,
            'limit' => 100,
            'business_types' => [
                1 => 'Geschaeft des Bundesrates',
                3 => 'Standesinitiative',
                4 => 'Parlamentarische Initiative',
                5 => 'Motion',
                6 => 'Postulat',
                8 => 'Interpellation',
                12 => 'Einfache Anfrage',
            ],
            'notes' => '',
        ],
    ];
}

/**
 * Save a calendar config array to disk as JSON.
 */
function saveCalendarConfig($config) {
    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return file_put_contents(CALENDAR_CONFIG_PATH, $json) !== false;
}

/**
 * Save a Lex config array to disk as JSON.
 */
function saveLexConfig($config) {
    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return file_put_contents(LEX_CONFIG_PATH, $json) !== false;
}

/**
 * Export feeds of a given source type as a JSON-serialisable array.
 * Each entry contains url, title, category, disabled.
 */
function exportFeeds($pdo, $sourceType = 'rss') {
    if ($sourceType === 'rss') {
        $stmt = $pdo->query("SELECT url, title, description, link, category, disabled FROM feeds WHERE source_type = 'rss' OR source_type IS NULL ORDER BY created_at");
    } else {
        $stmt = $pdo->prepare("SELECT url, title, description, link, category, disabled FROM feeds WHERE source_type = ? ORDER BY created_at");
        $stmt->execute([$sourceType]);
    }
    $feeds = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Cast disabled to bool for cleaner JSON
    foreach ($feeds as &$f) {
        $f['disabled'] = (bool)$f['disabled'];
    }
    unset($f);
    return $feeds;
}

/**
 * Import feeds from a parsed JSON array.
 * Each entry must have at least a "url" key.
 * Existing feeds (by URL) are updated; new feeds are inserted.
 * Returns [int $created, int $updated].
 */
function importFeeds($pdo, array $feeds, $sourceType = 'rss') {
    $created = 0;
    $updated = 0;

    foreach ($feeds as $f) {
        $url = trim($f['url'] ?? '');
        if (empty($url)) continue;

        $title    = trim($f['title'] ?? 'Untitled');
        $desc     = trim($f['description'] ?? '');
        $link     = trim($f['link'] ?? $url);
        $category = trim($f['category'] ?? ($sourceType === 'rss' ? 'unsortiert' : $title));
        $disabled = !empty($f['disabled']) ? 1 : 0;

        // Check if feed already exists
        $check = $pdo->prepare("SELECT id FROM feeds WHERE url = ?");
        $check->execute([$url]);
        $existing = $check->fetch();

        if ($existing) {
            // Update existing feed
            $upd = $pdo->prepare("UPDATE feeds SET title = ?, description = ?, link = ?, category = ?, disabled = ? WHERE id = ?");
            $upd->execute([$title, $desc, $link, $category, $disabled, $existing['id']]);
            $updated++;
        } else {
            // Insert new feed
            $stValue = ($sourceType === 'rss') ? null : $sourceType;
            $ins = $pdo->prepare("INSERT INTO feeds (url, source_type, title, description, link, category, disabled) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([$url, $stValue, $title, $desc, $link, $category, $disabled]);
            $created++;
        }
    }

    return [$created, $updated];
}
