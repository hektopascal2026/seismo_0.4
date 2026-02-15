<?php
declare(strict_types=1);

// seismo_scraper.php
//
// Cron-safe web scraper that fetches configured URLs, extracts readable
// content via DOMDocument heuristics, and stores results into the Seismo
// database (feeds + feed_items tables with source_type = 'scraper').
//
// Scraper URLs are managed in the Seismo UI (Settings > Script) and read
// directly from the scraper_configs table — no re-download needed when
// URLs change.
//
// Setup:
// - Copy config.php.example to config.php and set DB credentials.
// - Upload this folder to your hosting.
//
// Run (cron):
//   0 */6 * * * /usr/bin/php /path/to/seismo_scraper.php

// CLI-only guard
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Forbidden\n";
    exit(1);
}

// Load config
$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    fwrite(STDERR, "Missing config.php. Copy config.php.example to config.php and configure it.\n");
    exit(1);
}
$config = require $configPath;

// -----------------------
// Logging
// -----------------------
function log_msg(array $config, string $level, string $msg): void
{
    static $levels = ['debug' => 10, 'info' => 20, 'warn' => 30, 'error' => 40];
    $min = $config['logging']['level'] ?? 'info';
    if (($levels[$level] ?? 100) < ($levels[$min] ?? 20)) {
        return;
    }

    $line = sprintf("[%s] %s: %s\n", date('c'), strtoupper($level), $msg);
    $target = $config['logging']['target'] ?? 'stdout';
    if ($target === 'stdout') {
        fwrite(STDOUT, $line);
    } else {
        @file_put_contents($target, $line, FILE_APPEND);
    }
}

// -----------------------
// DB
// -----------------------
function db_connect(array $config): PDO
{
    $db = $config['db'];

    if (empty($db['password']) || $db['password'] === 'CHANGE_ME') {
        throw new RuntimeException("Database password not configured. Please set 'password' in config.php");
    }

    $dsn = sprintf(
        "mysql:host=%s;port=%d;dbname=%s;charset=%s",
        $db['host'],
        (int) $db['port'],
        $db['database'],
        $db['charset'] ?? 'utf8mb4'
    );

    $pdo = new PDO($dsn, $db['username'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

// -----------------------
// Content extraction
// -----------------------
function extractReadableContent(string $html): array
{
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();

    // Extract <title>
    $titleNodes = $dom->getElementsByTagName('title');
    $title = $titleNodes->length > 0 ? trim($titleNodes->item(0)->textContent) : '';

    // Remove unwanted tags
    foreach (['script', 'style', 'nav', 'header', 'footer', 'aside', 'noscript', 'iframe'] as $tag) {
        $nodes = $dom->getElementsByTagName($tag);
        $toRemove = [];
        for ($i = 0; $i < $nodes->length; $i++) {
            $toRemove[] = $nodes->item($i);
        }
        foreach ($toRemove as $node) {
            $node->parentNode->removeChild($node);
        }
    }

    // Find the largest text-bearing block
    $body = $dom->getElementsByTagName('body')->item(0);
    if (!$body) {
        return ['title' => $title, 'content' => ''];
    }

    $bestText = '';
    $bestLen = 0;
    $candidates = ['article', 'main', 'div', 'section'];
    foreach ($candidates as $tagName) {
        $elements = $dom->getElementsByTagName($tagName);
        for ($i = 0; $i < $elements->length; $i++) {
            $text = trim($elements->item($i)->textContent);
            $len = mb_strlen($text);
            if ($len > $bestLen) {
                $bestLen = $len;
                $bestText = $text;
            }
        }
        if ($bestLen > 200) break;
    }

    if ($bestLen < 50) {
        $bestText = trim($body->textContent);
    }

    // Normalize whitespace
    $bestText = preg_replace('/[ \t]+/', ' ', $bestText);
    $bestText = preg_replace('/\n{3,}/', "\n\n", $bestText);

    return ['title' => $title, 'content' => trim($bestText)];
}

// -----------------------
// Link extraction
// -----------------------
function extractLinks(string $html, string $baseUrl, string $pattern): array
{
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();

    $parsedBase = parse_url($baseUrl);
    $baseScheme = ($parsedBase['scheme'] ?? 'https') . '://';
    $baseHost = $parsedBase['host'] ?? '';
    $baseOrigin = $baseScheme . $baseHost;

    $links = $dom->getElementsByTagName('a');
    $urls = [];
    $seen = [];

    for ($i = 0; $i < $links->length; $i++) {
        $href = trim($links->item($i)->getAttribute('href'));
        if (empty($href) || $href[0] === '#') continue;

        // Resolve relative URLs
        if (strpos($href, '//') === 0) {
            $href = ($parsedBase['scheme'] ?? 'https') . ':' . $href;
        } elseif ($href[0] === '/') {
            $href = $baseOrigin . $href;
        } elseif (strpos($href, 'http') !== 0) {
            $href = rtrim($baseUrl, '/') . '/' . $href;
        }

        // Only follow links on the same host
        $parsedLink = parse_url($href);
        if (($parsedLink['host'] ?? '') !== $baseHost) continue;

        // Match against the pattern (simple substring match)
        if (strpos($href, $pattern) === false) continue;

        // Skip the listing page itself
        if (rtrim($href, '/') === rtrim($baseUrl, '/')) continue;

        // Deduplicate
        $canonical = strtok($href, '#');
        if (isset($seen[$canonical])) continue;
        $seen[$canonical] = true;

        $urls[] = $canonical;
    }

    return $urls;
}

// -----------------------
// Date extraction
// -----------------------

/**
 * Convert a simple CSS selector to an XPath expression.
 * Supports: tag, .class, tag.class, tag[attr], tag[attr="val"],
 * meta[property="..."], #id, and combinations.
 */
function cssToXPath(string $css): string
{
    $css = trim($css);
    if (empty($css)) return '';

    // Already XPath? (starts with / or //)
    if ($css[0] === '/') return $css;

    // tag[attr="value"] or tag[attr]
    if (preg_match('/^([a-z][a-z0-9]*)\[([a-z_-]+)(?:=["\']?([^"\']*)["\']?)?\]$/i', $css, $m)) {
        $tag = $m[1];
        $attr = $m[2];
        $val = $m[3] ?? null;
        if ($val !== null) {
            return "//{$tag}[@{$attr}='{$val}']";
        }
        return "//{$tag}[@{$attr}]";
    }

    // [attr="value"] (no tag)
    if (preg_match('/^\[([a-z_-]+)(?:=["\']?([^"\']*)["\']?)?\]$/i', $css, $m)) {
        $attr = $m[1];
        $val = $m[2] ?? null;
        if ($val !== null) {
            return "//*[@{$attr}='{$val}']";
        }
        return "//*[@{$attr}]";
    }

    // #id
    if (preg_match('/^#([a-z0-9_-]+)$/i', $css, $m)) {
        return "//*[@id='{$m[1]}']";
    }

    // tag.class or .class
    if (preg_match('/^([a-z][a-z0-9]*)\.([a-z0-9_-]+)$/i', $css, $m)) {
        return "//{$m[1]}[contains(concat(' ',normalize-space(@class),' '),' {$m[2]} ')]";
    }
    if (preg_match('/^\.([a-z0-9_-]+)$/i', $css, $m)) {
        return "//*[contains(concat(' ',normalize-space(@class),' '),' {$m[1]} ')]";
    }

    // Plain tag name
    if (preg_match('/^[a-z][a-z0-9]*$/i', $css)) {
        return "//{$css}";
    }

    return '';
}

/**
 * Extract a date from HTML using the given CSS selector.
 * Tries datetime/content attributes first, then text content.
 * Returns a MySQL-formatted date string or null.
 */
function extractDate(string $html, string $selector): ?string
{
    $xpath = cssToXPath($selector);
    if (empty($xpath)) return null;

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();

    $xp = new DOMXPath($dom);
    $nodes = $xp->query($xpath);
    if (!$nodes || $nodes->length === 0) return null;

    $node = $nodes->item(0);

    // Try common date-bearing attributes first
    foreach (['datetime', 'content', 'data-date', 'data-datetime'] as $attr) {
        $val = trim($node->getAttribute($attr));
        if (!empty($val)) {
            $ts = strtotime($val);
            if ($ts !== false && $ts > 0) {
                return date('Y-m-d H:i:s', $ts);
            }
        }
    }

    // Fall back to text content
    $text = trim($node->textContent);
    if (!empty($text)) {
        $ts = strtotime($text);
        if ($ts !== false && $ts > 0) {
            return date('Y-m-d H:i:s', $ts);
        }
        // Try common European date formats: "15. Februar 2026", "15.02.2026"
        $cleaned = preg_replace('/\s+/', ' ', $text);
        // German month names
        $months = ['Januar'=>1,'Februar'=>2,'März'=>3,'April'=>4,'Mai'=>5,'Juni'=>6,
                   'Juli'=>7,'August'=>8,'September'=>9,'Oktober'=>10,'November'=>11,'Dezember'=>12,
                   'Janvier'=>1,'Février'=>2,'Mars'=>3,'Avril'=>4,'Juin'=>6,'Juillet'=>7,
                   'Août'=>8,'Septembre'=>9,'Octobre'=>10,'Novembre'=>11,'Décembre'=>12];
        foreach ($months as $mName => $mNum) {
            if (stripos($cleaned, $mName) !== false) {
                if (preg_match('/(\d{1,2})\.?\s*' . preg_quote($mName, '/') . '\s*(\d{4})/i', $cleaned, $dm)) {
                    $ts = mktime(0, 0, 0, $mNum, (int)$dm[1], (int)$dm[2]);
                    if ($ts !== false) return date('Y-m-d H:i:s', $ts);
                }
            }
        }
        // dd.mm.yyyy
        if (preg_match('/(\d{1,2})\.(\d{1,2})\.(\d{4})/', $cleaned, $dm)) {
            $ts = mktime(0, 0, 0, (int)$dm[2], (int)$dm[1], (int)$dm[3]);
            if ($ts !== false) return date('Y-m-d H:i:s', $ts);
        }
    }

    return null;
}

// -----------------------
// HTTP fetch (polite)
// -----------------------
function fetchUrl(string $url, array $config): array
{
    static $userAgents = [
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:124.0) Gecko/20100101 Firefox/124.0',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
    ];

    $ua = $userAgents[array_rand($userAgents)];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT      => $ua,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_ENCODING       => '',
        CURLOPT_HTTPHEADER     => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: de-CH,de;q=0.9,en;q=0.8',
            'Cache-Control: no-cache',
            'DNT: 1',
        ],
    ]);
    $html = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    return ['html' => $html, 'http_code' => $httpCode, 'error' => $err];
}

// -----------------------
// Main
// -----------------------
try {
    log_msg($config, 'info', 'Starting scraper...');

    $pdo = db_connect($config);

    // Read enabled scrapers from the database
    $scrapers = $pdo->query("SELECT name, url, link_pattern, date_selector FROM scraper_configs WHERE disabled = 0 ORDER BY id")->fetchAll();

    if (empty($scrapers)) {
        log_msg($config, 'info', 'No enabled scrapers configured. Add URLs in Seismo Settings > Script.');
        exit(0);
    }

    log_msg($config, 'info', sprintf('Found %d enabled scraper(s)', count($scrapers)));

    // Polite scraping settings
    $minDelay = (float) ($config['scraping']['min_delay'] ?? 3);
    $maxDelay = (float) ($config['scraping']['max_delay'] ?? 8);
    $maxLinksPerScraper = (int) ($config['scraping']['max_links'] ?? 20);

    $inserted = 0;
    $updated = 0;
    $skipped = 0;
    $errors = 0;
    $fetchCount = 0;

    foreach ($scrapers as $scraper) {
        $name = $scraper['name'];
        $baseUrl = $scraper['url'];
        $linkPattern = $scraper['link_pattern'] ?? null;
        $dateSelector = $scraper['date_selector'] ?? null;

        // Ensure a feeds row exists for this scraper source
        $feedStmt = $pdo->prepare("SELECT id FROM feeds WHERE url = ? AND source_type = 'scraper'");
        $feedStmt->execute([$baseUrl]);
        $feedId = $feedStmt->fetchColumn();

        if (!$feedId) {
            $ins = $pdo->prepare("INSERT INTO feeds (title, url, link, source_type, category) VALUES (?, ?, ?, 'scraper', 'scraper')");
            $ins->execute([$name, $baseUrl, $baseUrl]);
            $feedId = $pdo->lastInsertId();
            log_msg($config, 'info', "[{$name}] Created feed #{$feedId}");
        }

        // Polite delay between fetches
        if ($fetchCount > 0) {
            $delay = rand((int)($minDelay * 10), (int)($maxDelay * 10)) / 10;
            log_msg($config, 'debug', "Sleeping {$delay}s...");
            usleep((int)($delay * 1000000));
        }
        $fetchCount++;

        log_msg($config, 'info', "[{$name}] Fetching: {$baseUrl}");

        $result = fetchUrl($baseUrl, $config);

        if ($result['html'] === false || $result['http_code'] >= 400) {
            log_msg($config, 'warn', "[{$name}] HTTP {$result['http_code']}, error: {$result['error']}");
            $errors++;
            continue;
        }

        // Determine which URLs to scrape
        if (!empty($linkPattern)) {
            // Link-following mode: extract article links from the listing page
            $articleUrls = extractLinks($result['html'], $baseUrl, $linkPattern);
            if (empty($articleUrls)) {
                log_msg($config, 'warn', "[{$name}] No links matching pattern '{$linkPattern}' found.");
                $errors++;
                continue;
            }
            $articleUrls = array_slice($articleUrls, 0, $maxLinksPerScraper);
            log_msg($config, 'info', "[{$name}] Found " . count($articleUrls) . " article link(s)");
        } else {
            // Single-page mode: scrape the page itself
            $articleUrls = [$baseUrl];
        }

        foreach ($articleUrls as $articleUrl) {
            // For link-following mode, fetch each article page (listing page already fetched)
            if ($articleUrl !== $baseUrl) {
                $delay = rand((int)($minDelay * 10), (int)($maxDelay * 10)) / 10;
                log_msg($config, 'debug', "Sleeping {$delay}s...");
                usleep((int)($delay * 1000000));
                $fetchCount++;

                log_msg($config, 'info', "[{$name}] Fetching article: {$articleUrl}");
                $result = fetchUrl($articleUrl, $config);

                if ($result['html'] === false || $result['http_code'] >= 400) {
                    log_msg($config, 'warn', "[{$name}] HTTP {$result['http_code']}, error: {$result['error']}");
                    $errors++;
                    continue;
                }
            }

            $extracted = extractReadableContent($result['html']);
            $title = !empty($extracted['title']) ? $extracted['title'] : $name;
            $content = $extracted['content'];

            if (empty($content)) {
                log_msg($config, 'warn', "[{$name}] No content extracted from {$articleUrl}, skipping.");
                $errors++;
                continue;
            }

            $contentHash = md5($content);

            // Extract publication date if a selector is configured
            $pubDate = null;
            if (!empty($dateSelector)) {
                $pubDate = extractDate($result['html'], $dateSelector);
                if ($pubDate) {
                    log_msg($config, 'debug', "[{$name}] Extracted date: {$pubDate}");
                } else {
                    log_msg($config, 'debug', "[{$name}] Date selector '{$dateSelector}' matched nothing, using NOW()");
                }
            }

            // Check for existing entry by guid (= article URL)
            $existStmt = $pdo->prepare("SELECT id, content_hash FROM feed_items WHERE guid = ? AND feed_id = ?");
            $existStmt->execute([$articleUrl, $feedId]);
            $existing = $existStmt->fetch();

            if ($existing) {
                if ($existing['content_hash'] === $contentHash) {
                    log_msg($config, 'debug', "[{$name}] Content unchanged: {$articleUrl}");
                    $skipped++;
                    continue;
                }
                $upd = $pdo->prepare("UPDATE feed_items SET title = ?, content = ?, content_hash = ?, published_date = ? WHERE id = ?");
                $upd->execute([$title, $content, $contentHash, $pubDate ?? date('Y-m-d H:i:s'), $existing['id']]);
                log_msg($config, 'info', "[{$name}] Updated item #{$existing['id']}");
                $updated++;
            } else {
                $ins = $pdo->prepare("INSERT INTO feed_items (feed_id, title, content, link, guid, content_hash, published_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $ins->execute([$feedId, $title, $content, $articleUrl, $articleUrl, $contentHash, $pubDate ?? date('Y-m-d H:i:s')]);
                log_msg($config, 'info', "[{$name}] New item #" . $pdo->lastInsertId());
                $inserted++;
            }
        }
    }

    log_msg($config, 'info', "Done. inserted={$inserted} updated={$updated} skipped={$skipped} errors={$errors} fetches={$fetchCount}");
    exit(0);
} catch (Throwable $e) {
    log_msg($config, 'error', $e->getMessage());
    exit(1);
}
