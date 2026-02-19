<?php
declare(strict_types=1);

// fetch_mail.php
//
// Cron-safe IMAP fetcher that parses MIME messages with zbateson/mail-mime-parser
// and stores them into MySQL via PDO.
//
// IMAP credentials are configured in the Seismo UI (Settings > Script) and
// baked into the downloaded config.php.
//
// Setup:
// - Download config.php from Seismo (Settings > Script > Mail section),
//   or copy config.php.example and fill in credentials manually.
// - Run: composer install (to get vendor/)
// - Upload this folder to your hosting.
//
// Run (cron):
//   */15 * * * * /usr/bin/php /path/to/fetch_mail.php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Forbidden\n";
    exit(1);
}

$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    fwrite(STDERR, "Missing config.php. Copy config.php.example to config.php and configure it.\n");
    exit(1);
}
$config = require $configPath;

$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    fwrite(STDERR, "Missing vendor/autoload.php. Run 'composer install' and upload vendor/.\n");
    exit(1);
}
require $autoloadPath;

use ZBateson\MailMimeParser\Message;

// ---------------------------------------------------------------------------
// Logging
// ---------------------------------------------------------------------------
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

// ---------------------------------------------------------------------------
// Database
// ---------------------------------------------------------------------------
function db_connect(array $config): PDO
{
    $db = $config['db'];

    if (empty($db['password']) || $db['password'] === 'CHANGE_ME') {
        throw new RuntimeException("Database password not configured. Set 'password' in config.php.");
    }

    $dsn = sprintf(
        "mysql:host=%s;port=%d;dbname=%s;charset=%s",
        $db['host'],
        (int)($db['port'] ?? 3306),
        $db['database'],
        $db['charset'] ?? 'utf8mb4'
    );

    $pdo = new PDO($dsn, $db['username'], $db['password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec("SET NAMES " . $pdo->quote($db['charset'] ?? 'utf8mb4', PDO::PARAM_STR));
    return $pdo;
}

function ensure_table(PDO $pdo, string $table): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `$table` (
            `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `imap_uid`   BIGINT UNSIGNED NOT NULL,
            `message_id` VARCHAR(255) NULL,
            `from_addr`  TEXT NULL,
            `to_addr`    TEXT NULL,
            `cc_addr`    TEXT NULL,
            `subject`    TEXT NULL,
            `date_utc`   DATETIME NULL,
            `body_text`  LONGTEXT NULL,
            `body_html`  LONGTEXT NULL,
            `raw_headers` LONGTEXT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_imap_uid` (`imap_uid`),
            KEY `idx_message_id` (`message_id`(190))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function already_exists(PDO $pdo, string $table, int $uid): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM `$table` WHERE imap_uid = :uid LIMIT 1");
    $stmt->execute(['uid' => $uid]);
    return (bool)$stmt->fetchColumn();
}

function insert_email(PDO $pdo, string $table, array $row): void
{
    $cols  = array_keys($row);
    $place = array_map(fn($c) => ':' . $c, $cols);
    $sql   = "INSERT INTO `$table` (" . implode(',', array_map(fn($c) => "`$c`", $cols)) . ")
              VALUES (" . implode(',', $place) . ")";
    $pdo->prepare($sql)->execute($row);
}

// ---------------------------------------------------------------------------
// IMAP helpers
// ---------------------------------------------------------------------------
function imap_open_or_fail(array $config)
{
    $imap = $config['imap'];

    if (empty($imap['password']) || $imap['password'] === 'CHANGE_ME') {
        throw new RuntimeException("IMAP password not configured. Set 'password' in config.php.");
    }

    $stream = @imap_open($imap['mailbox'], $imap['username'], $imap['password']);
    if ($stream === false) {
        throw new RuntimeException("IMAP connection failed: " . (imap_last_error() ?: 'unknown error'));
    }
    return $stream;
}

function decode_mime_header(?string $value): ?string
{
    if ($value === null || $value === '') return $value;
    $decoded = @iconv_mime_decode($value, 0, 'UTF-8');
    return $decoded !== false ? $decoded : $value;
}

function join_addresses(?array $addrs): ?string
{
    if (empty($addrs)) return null;
    $out = [];
    foreach ($addrs as $a) {
        $email = (!empty($a->mailbox) && !empty($a->host)) ? $a->mailbox . '@' . $a->host : '';
        $name  = isset($a->personal) ? decode_mime_header($a->personal) : null;
        if ($name && $email) {
            $out[] = sprintf('"%s" <%s>', str_replace('"', '\"', $name), $email);
        } elseif ($email) {
            $out[] = $email;
        } elseif ($name) {
            $out[] = $name;
        }
    }
    return $out ? implode(', ', $out) : null;
}

function parse_message(int $msgNo, $imapStream): array
{
    $raw = imap_fetchheader($imapStream, $msgNo) . "\r\n" . imap_body($imapStream, $msgNo, FT_PEEK);

    /** @var Message $message */
    $message  = Message::from($raw, false);
    $bodyText = $message->getTextContent();
    $bodyHtml = $message->getHtmlContent();

    // Derive plain text from HTML if absent
    if ($bodyText === null || trim((string)$bodyText) === '') {
        if ($bodyHtml !== null) {
            $clean    = preg_replace('/<(style|script)\b[^>]*>.*<\/\\1>/is', '', (string)$bodyHtml);
            $bodyText = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($clean))));
        }
    }

    $headers   = imap_headerinfo($imapStream, $msgNo);
    $messageId = $message->getHeaderValue('Message-ID') ?: null;
    $subject   = $message->getHeaderValue('Subject') ?: null;

    $dateUtc = null;
    $dateRaw = $message->getHeaderValue('Date') ?: null;
    if ($dateRaw) {
        $ts = strtotime($dateRaw);
        if ($ts !== false) $dateUtc = gmdate('Y-m-d H:i:s', $ts);
    }

    return [
        'message_id'  => $messageId ? trim($messageId) : null,
        'from_addr'   => join_addresses($headers->from ?? null),
        'to_addr'     => join_addresses($headers->to ?? null),
        'cc_addr'     => join_addresses($headers->cc ?? null),
        'subject'     => $subject ? decode_mime_header($subject) : null,
        'date_utc'    => $dateUtc,
        'body_text'   => $bodyText !== null ? (string)$bodyText : null,
        'body_html'   => $bodyHtml !== null ? (string)$bodyHtml : null,
        'raw_headers'  => imap_fetchheader($imapStream, $msgNo),
    ];
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------
try {
    log_msg($config, 'info', 'Starting mail fetch...');

    $pdo   = db_connect($config);
    $table = $config['db']['table'] ?? 'fetched_emails';
    ensure_table($pdo, $table);

    $imapStream = imap_open_or_fail($config);

    $criteria = $config['imap']['search_criteria'] ?? 'UNSEEN';
    $msgNos   = @imap_search($imapStream, $criteria);
    if ($msgNos === false || empty($msgNos)) {
        log_msg($config, 'info', "No messages matched criteria: {$criteria}");
        imap_close($imapStream);
        exit(0);
    }

    sort($msgNos);

    $max       = (int)($config['imap']['max_messages_per_run'] ?? 50);
    $processed = 0;
    $inserted  = 0;
    $skipped   = 0;

    foreach ($msgNos as $msgNo) {
        if ($processed >= $max) break;

        $uid = (int)imap_uid($imapStream, (int)$msgNo);
        if ($uid <= 0) {
            log_msg($config, 'warn', "Skipping msgNo={$msgNo} (could not get UID)");
            $processed++;
            continue;
        }

        if (already_exists($pdo, $table, $uid)) {
            $skipped++;
            $processed++;
            continue;
        }

        $row = parse_message((int)$msgNo, $imapStream);
        $row['imap_uid'] = $uid;

        insert_email($pdo, $table, $row);
        $inserted++;

        if (!empty($config['imap']['mark_seen'])) {
            @imap_setflag_full($imapStream, (string)$msgNo, "\\Seen");
        }

        $processed++;
    }

    imap_close($imapStream);
    log_msg($config, 'info', "Done. processed={$processed} inserted={$inserted} skipped_existing={$skipped}");
    exit(0);
} catch (Throwable $e) {
    log_msg($config, 'error', $e->getMessage());
    exit(1);
}
