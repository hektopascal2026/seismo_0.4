<?php
/**
 * Email subscriptions — first-class newsletter sources (domain + per-address overrides).
 */

function esSubscriptionsTable(): string {
    return entryTable('email_subscriptions');
}

/**
 * @return array{email: string, name: string, domain: string}
 */
function esNormalizeFromField(string $raw): array {
    $raw = trim($raw);
    $email = '';
    $name = '';
    if (preg_match('/^"([^"]+)"\s*<(.+)>$/', $raw, $m)) {
        $name = $m[1];
        $email = strtolower(trim($m[2]));
    } elseif (preg_match('/^(.+)\s*<(.+)>$/', $raw, $m)) {
        $name = trim($m[1]);
        $email = strtolower(trim($m[2]));
    } elseif (preg_match('/^(.+@.+)$/', $raw)) {
        $email = strtolower($raw);
        $name = '';
    } else {
        $email = strtolower($raw);
    }
    $domain = '';
    if ($email !== '' && strpos($email, '@') !== false) {
        $domain = strtolower(substr(strrchr($email, '@'), 1));
    }
    return ['email' => $email, 'name' => $name, 'domain' => $domain];
}

/**
 * @return array{url: ?string, mailto: ?string, one_click: bool}
 */
function esParseListUnsubscribe(?string $rawHeaders): array {
    $out = ['url' => null, 'mailto' => null, 'one_click' => false];
    if ($rawHeaders === null || $rawHeaders === '') {
        return $out;
    }
    $norm = str_replace("\r\n", "\n", $rawHeaders);
    if (preg_match('/^[ \t]*List-Unsubscribe-Post:\s*(.+)$/mi', $norm, $mp)) {
        if (stripos(trim($mp[1]), 'List-Unsubscribe=One-Click') !== false) {
            $out['one_click'] = true;
        }
    }
    $lines = explode("\n", $norm);
    $lu = '';
    $luStart = -1;
    foreach ($lines as $i => $line) {
        if (preg_match('/^List-Unsubscribe:\s*(.*)$/i', $line, $m)) {
            $lu = trim($m[1]);
            $luStart = $i;
            break;
        }
    }
    if ($luStart < 0) {
        return $out;
    }
    $buf = $lu;
    for ($j = $luStart + 1; $j < count($lines); $j++) {
        $line = $lines[$j];
        if ($line !== '' && ($line[0] === ' ' || $line[0] === "\t")) {
            $buf .= ' ' . trim($line);
        } else {
            break;
        }
    }
    if (preg_match_all('/<([^>]+)>/', $buf, $matches)) {
        foreach ($matches[1] as $token) {
            $token = trim($token);
            if (stripos($token, 'mailto:') === 0) {
                if ($out['mailto'] === null) {
                    $out['mailto'] = $token;
                }
            } elseif (preg_match('#^https?://#i', $token)) {
                if ($out['url'] === null) {
                    $out['url'] = $token;
                }
            }
        }
    }
    return $out;
}

/**
 * @return array<string, mixed>|null
 */
function esResolveSubscriptionRow(PDO $pdo, string $fromEmailNormalized): ?array {
    if ($fromEmailNormalized === '') {
        return null;
    }
    $t = esSubscriptionsTable();
    $stmt = $pdo->prepare("SELECT * FROM $t WHERE match_type = 'email' AND match_value = ? AND removed_at IS NULL LIMIT 1");
    $stmt->execute([$fromEmailNormalized]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return $row;
    }
    $domain = strtolower(substr(strrchr($fromEmailNormalized, '@'), 1));
    if ($domain === '' || $domain === false) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT * FROM $t WHERE match_type = 'domain' AND match_value = ? AND removed_at IS NULL LIMIT 1");
    $stmt->execute([$domain]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function esResolveSubscriptionId(PDO $pdo, string $fromEmailNormalized): ?int {
    $row = esResolveSubscriptionRow($pdo, $fromEmailNormalized);
    return $row ? (int)$row['id'] : null;
}

/**
 * One-time import from sender_tags into email_subscriptions (match_type=email).
 */
function esMigrateSenderTagsOnce(PDO $pdo): void {
    if (getMagnituConfig($pdo, 'email_subs_migrated_from_sender_tags') === '1') {
        return;
    }
    $t = esSubscriptionsTable();
    try {
        $stmt = $pdo->query("SELECT from_email, tag, disabled, removed_at FROM " . entryTable('sender_tags'));
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (PDOException $e) {
        setMagnituConfig($pdo, 'email_subs_migrated_from_sender_tags', '1');
        return;
    }
    $ins = $pdo->prepare("INSERT INTO $t (match_type, match_value, display_name, category, disabled, auto_detected, removed_at, first_seen_at, last_seen_at, item_count)
        VALUES ('email', ?, ?, ?, ?, 0, ?, NULL, NULL, 0)
        ON DUPLICATE KEY UPDATE
            display_name = VALUES(display_name),
            category = COALESCE(NULLIF(VALUES(category), ''), category),
            disabled = VALUES(disabled),
            removed_at = VALUES(removed_at)");
    foreach ($rows as $r) {
        $fe = strtolower(trim($r['from_email'] ?? ''));
        if ($fe === '') {
            continue;
        }
        $tag = trim($r['tag'] ?? '');
        if ($tag === '' || $tag === 'unclassified') {
            $tag = 'unsortiert';
        }
        $disp = $fe;
        if (strpos($fe, '@') !== false) {
            $disp = substr($fe, 0, strpos($fe, '@'));
        }
        $rem = !empty($r['removed_at']) ? $r['removed_at'] : null;
        $ins->execute([
            $fe,
            $disp,
            $tag,
            (int)($r['disabled'] ?? 0),
            $rem,
        ]);
    }
    setMagnituConfig($pdo, 'email_subs_migrated_from_sender_tags', '1');
}

/**
 * @return array{exact: string[], domains: string[]}
 */
function esGetBlockedSubscriptionLists(PDO $pdo): array {
    $exact = [];
    $domains = [];
    try {
        $t = esSubscriptionsTable();
        $stmt = $pdo->query("SELECT match_type, match_value FROM $t WHERE (disabled = 1 OR removed_at IS NOT NULL)");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['match_type'] === 'email') {
                $exact[] = strtolower(trim($row['match_value']));
            } else {
                $domains[] = strtolower(trim($row['match_value']));
            }
        }
    } catch (PDOException $e) {
        // table missing on first boot
    }
    return ['exact' => array_unique($exact), 'domains' => array_unique($domains)];
}

function esShouldHideEmail(PDO $pdo, string $normalizedFromEmail, array $blockedSubs, array $legacyDisabledEmails): bool {
    $ne = strtolower(trim($normalizedFromEmail));
    if ($ne === '') {
        return false;
    }
    foreach ($legacyDisabledEmails as $d) {
        if (strtolower(trim($d)) === $ne) {
            return true;
        }
    }
    foreach ($blockedSubs['exact'] as $ex) {
        if ($ex === $ne) {
            return true;
        }
    }
    $dom = '';
    if (strpos($ne, '@') !== false) {
        $dom = strtolower(substr(strrchr($ne, '@'), 1));
    }
    foreach ($blockedSubs['domains'] as $bd) {
        if ($bd !== '' && $dom === $bd) {
            return true;
        }
    }
    return false;
}

/**
 * @param array{email: string, name: string, domain: string} $norm
 */
function esAutoDetectSubscription(PDO $pdo, array $norm, ?string $rawHeaders, ?string $dateUtc): int {
    $t = esSubscriptionsTable();
    $email = $norm['email'];
    $domain = $norm['domain'];
    if ($email === '' || $domain === '') {
        return 0;
    }
    $existing = esResolveSubscriptionRow($pdo, $email);
    $unsub = esParseListUnsubscribe($rawHeaders);
    $seenAt = $dateUtc ?: date('Y-m-d H:i:s');

    if ($existing) {
        $id = (int)$existing['id'];
        $upd = ['last_seen_at' => $seenAt];
        if (empty($existing['unsubscribe_url']) && $unsub['url']) {
            $upd['unsubscribe_url'] = $unsub['url'];
        }
        if (empty($existing['unsubscribe_mailto']) && $unsub['mailto']) {
            $upd['unsubscribe_mailto'] = $unsub['mailto'];
        }
        if (!empty($unsub['one_click'])) {
            $upd['unsubscribe_one_click'] = 1;
        }
        $sets = [];
        $params = [];
        foreach ($upd as $k => $v) {
            $sets[] = "`$k` = ?";
            $params[] = $v;
        }
        $params[] = $id;
        $pdo->prepare("UPDATE $t SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);
        return $id;
    }

    $disp = $norm['name'] !== '' ? $norm['name'] : $domain;
    $cat = 'unsortiert';
    $ins = $pdo->prepare("INSERT INTO $t (match_type, match_value, display_name, category, disabled, auto_detected, unsubscribe_url, unsubscribe_mailto, unsubscribe_one_click, first_seen_at, last_seen_at, item_count)
        VALUES ('domain', ?, ?, ?, 0, 1, ?, ?, ?, ?, ?, 1)");
    $ins->execute([
        $domain,
        $disp,
        $cat,
        $unsub['url'],
        $unsub['mailto'],
        !empty($unsub['one_click']) ? 1 : 0,
        $seenAt,
        $seenAt,
    ]);
    return (int)$pdo->lastInsertId();
}

function esRecomputeStats(PDO $pdo, ?int $subId = null): void {
    $tableName = getEmailTableName($pdo);
    if (!$tableName) {
        return;
    }
    $descStmt = $pdo->query("DESCRIBE `$tableName`");
    $cols = $descStmt ? $descStmt->fetchAll(PDO::FETCH_COLUMN) : [];
    $fromCol = in_array('from_email', $cols) ? 'from_email' : (in_array('from_addr', $cols) ? 'from_addr' : null);
    $dateCol = in_array('date_utc', $cols) ? 'date_utc' : (in_array('date_received', $cols) ? 'date_received' : (in_array('created_at', $cols) ? 'created_at' : null));
    if (!$fromCol || !$dateCol) {
        return;
    }
    $t = esSubscriptionsTable();
    $stmt = $pdo->query("SELECT id, match_type, match_value FROM $t WHERE removed_at IS NULL");
    $subs = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $sqlAll = "SELECT `$fromCol` as `from_raw`, MIN(`$dateCol`) as first_d, MAX(`$dateCol`) as last_d, COUNT(*) as cnt FROM `$tableName` GROUP BY `$fromCol`";
    try {
        $agg = $pdo->query($sqlAll)->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return;
    }
    $byEmail = [];
    $byDomain = [];
    foreach ($agg as $a) {
        $n = esNormalizeFromField((string)$a['from_raw']);
        if ($n['email'] !== '') {
            $byEmail[$n['email']] = $a;
            if ($n['domain'] !== '') {
                if (!isset($byDomain[$n['domain']])) {
                    $byDomain[$n['domain']] = ['cnt' => 0, 'first_d' => null, 'last_d' => null];
                }
                $byDomain[$n['domain']]['cnt'] += (int)$a['cnt'];
                $fd = $a['first_d'];
                $ld = $a['last_d'];
                if ($fd && ($byDomain[$n['domain']]['first_d'] === null || strtotime($fd) < strtotime($byDomain[$n['domain']]['first_d']))) {
                    $byDomain[$n['domain']]['first_d'] = $fd;
                }
                if ($ld && ($byDomain[$n['domain']]['last_d'] === null || strtotime($ld) > strtotime($byDomain[$n['domain']]['last_d']))) {
                    $byDomain[$n['domain']]['last_d'] = $ld;
                }
            }
        }
    }
    $upd = $pdo->prepare("UPDATE $t SET item_count = ?, first_seen_at = ?, last_seen_at = ? WHERE id = ?");
    foreach ($subs as $s) {
        if ($subId !== null && (int)$s['id'] !== $subId) {
            continue;
        }
        $mv = strtolower(trim($s['match_value']));
        if ($s['match_type'] === 'email') {
            $a = $byEmail[$mv] ?? null;
            if ($a) {
                $upd->execute([(int)$a['cnt'], $a['first_d'], $a['last_d'], (int)$s['id']]);
            } else {
                $upd->execute([0, null, null, (int)$s['id']]);
            }
        } else {
            $a = $byDomain[$mv] ?? null;
            if ($a) {
                $upd->execute([(int)$a['cnt'], $a['first_d'], $a['last_d'], (int)$s['id']]);
            } else {
                $upd->execute([0, null, null, (int)$s['id']]);
            }
        }
    }
}

function esSyncNewEmails(PDO $pdo, int $limit = 500): int {
    esMigrateSenderTagsOnce($pdo);
    $tableName = getEmailTableName($pdo);
    if (!$tableName) {
        return 0;
    }
    $lastId = (int)(getMagnituConfig($pdo, 'email_subs_last_synced_id') ?: 0);
    $descStmt = $pdo->query("DESCRIBE `$tableName`");
    $cols = $descStmt ? $descStmt->fetchAll(PDO::FETCH_COLUMN) : [];
    $fromCol = in_array('from_email', $cols) ? 'from_email' : (in_array('from_addr', $cols) ? 'from_addr' : null);
    $dateCol = in_array('date_utc', $cols) ? 'date_utc' : (in_array('date_received', $cols) ? 'date_received' : (in_array('created_at', $cols) ? 'created_at' : null));
    $rawCol = in_array('raw_headers', $cols) ? 'raw_headers' : null;
    if (!$fromCol || !$dateCol) {
        return 0;
    }
    $rawSelect = $rawCol ? "`$rawCol`" : 'NULL';
    $sql = "SELECT id, `$fromCol` as from_raw, `$dateCol` as date_raw, $rawSelect as raw_headers FROM `$tableName` WHERE id > ? ORDER BY id ASC LIMIT " . (int)$limit;
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$lastId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $maxId = $lastId;
    $processed = 0;
    foreach ($rows as $row) {
        $id = (int)$row['id'];
        if ($id > $maxId) {
            $maxId = $id;
        }
        $norm = esNormalizeFromField((string)$row['from_raw']);
        if ($norm['email'] === '') {
            continue;
        }
        esAutoDetectSubscription($pdo, $norm, $row['raw_headers'] ?? null, $row['date_raw'] ?? null);
        $processed++;
    }
    if ($maxId > $lastId) {
        setMagnituConfig($pdo, 'email_subs_last_synced_id', (string)$maxId);
    }
    esRecomputeStats($pdo);
    return $processed;
}

/**
 * Merge subscription category into $email['sender_tag'] (email wins over domain via esResolveSubscriptionRow).
 */
/**
 * Keep email_subscriptions in sync when legacy sender_tags handlers run.
 */
function esUpsertEmailSubscriptionFromSender(PDO $pdo, string $fromEmail, string $tag): void {
    $fe = strtolower(trim($fromEmail));
    if ($fe === '' || strpos($fe, '@') === false) {
        return;
    }
    $t = esSubscriptionsTable();
    $cat = trim($tag) !== '' && $tag !== 'unclassified' ? trim($tag) : 'unsortiert';
    $disp = strpos($fe, '@') !== false ? substr($fe, 0, strpos($fe, '@')) : $fe;
    $stmt = $pdo->prepare("INSERT INTO $t (match_type, match_value, display_name, category, disabled, auto_detected, removed_at)
        VALUES ('email', ?, ?, ?, 0, 0, NULL)
        ON DUPLICATE KEY UPDATE category = VALUES(category), removed_at = NULL");
    $stmt->execute([$fe, $disp, $cat]);
}

function esMirrorSenderToggle(PDO $pdo, string $fromEmail, int $disabled): void {
    $fe = strtolower(trim($fromEmail));
    if ($fe === '') {
        return;
    }
    $t = esSubscriptionsTable();
    $pdo->prepare("UPDATE $t SET disabled = ? WHERE match_type = 'email' AND match_value = ?")->execute([$disabled, $fe]);
}

function esMirrorSenderDelete(PDO $pdo, string $fromEmail): void {
    $fe = strtolower(trim($fromEmail));
    if ($fe === '') {
        return;
    }
    $t = esSubscriptionsTable();
    $pdo->prepare("UPDATE $t SET removed_at = NOW(), disabled = 1 WHERE match_type = 'email' AND match_value = ?")->execute([$fe]);
}

/**
 * Distinct filter labels for mail page (subscription categories + legacy sender tags).
 * @return string[]
 */
function esMailFilterTags(PDO $pdo): array {
    $out = [];
    try {
        $t = esSubscriptionsTable();
        $stmt = $pdo->query("SELECT DISTINCT category FROM $t WHERE removed_at IS NULL AND category IS NOT NULL AND category != '' AND category != 'unsortiert' ORDER BY category");
        if ($stmt) {
            $out = array_merge($out, $stmt->fetchAll(PDO::FETCH_COLUMN));
        }
    } catch (PDOException $e) {
        // ignore
    }
    try {
        $stmt = $pdo->query("SELECT DISTINCT tag FROM " . entryTable('sender_tags') . " WHERE tag IS NOT NULL AND tag != '' AND tag != 'unclassified' AND removed_at IS NULL ORDER BY tag");
        if ($stmt) {
            $out = array_merge($out, $stmt->fetchAll(PDO::FETCH_COLUMN));
        }
    } catch (PDOException $e) {
        // ignore
    }
    return array_values(array_unique(array_filter($out)));
}

/**
 * @param string[] $selectedTags
 */
function esEmailMatchesAnyFilterTags(PDO $pdo, array $email, array $selectedTags): bool {
    if (empty($selectedTags)) {
        return true;
    }
    foreach ($selectedTags as $t) {
        if ($t !== '' && esEmailMatchesFilterTag($pdo, $email, $t)) {
            return true;
        }
    }
    return false;
}

/**
 * Whether an email row matches the selected filter tag (subscription category or legacy tag).
 */
function esEmailMatchesFilterTag(PDO $pdo, array $email, ?string $selectedTag): bool {
    if ($selectedTag === null || $selectedTag === '') {
        return true;
    }
    $from = strtolower(trim($email['from_email'] ?? ''));
    if ($from === '') {
        return false;
    }
    $st = $email['sender_tag'] ?? null;
    if ($st !== null && trim((string)$st) === $selectedTag) {
        return true;
    }
    $row = esResolveSubscriptionRow($pdo, $from);
    if ($row && empty($row['removed_at']) && (int)$row['disabled'] === 0) {
        $c = trim($row['category'] ?? '');
        if ($c === $selectedTag) {
            return true;
        }
    }
    return false;
}

function esAttachSubscription(PDO $pdo, array &$emails): void {
    if (empty($emails)) {
        return;
    }
    foreach ($emails as &$email) {
        $from = $email['from_email'] ?? '';
        if (isset($email['from_name']) && $email['from_email'] === $email['from_name'] && $from !== '') {
            $n = esNormalizeFromField($from);
            $from = $n['email'];
        }
        $row = esResolveSubscriptionRow($pdo, strtolower(trim($from)));
        if ($row && empty($row['removed_at']) && (int)$row['disabled'] === 0) {
            $cat = trim($row['category'] ?? '');
            if ($cat !== '') {
                $email['sender_tag'] = $cat;
                $email['subscription_id'] = (int)$row['id'];
            }
        }
    }
    unset($email);
}

// ---------------------------------------------------------------------------
// HTTP handlers
// ---------------------------------------------------------------------------

/**
 * Load all data needed to render the Mail subscriptions panel.
 * Reads $_GET['show_removed'] and $_GET['category'] (same contract as before).
 * Returned as an associative array so both the Mail page and any legacy
 * standalone renderer can use it.
 *
 * @return array{showRemoved:bool,selectedCategory:?string,categories:array,subscriptions:array,totalActive:int,disabledCount:int,removedCount:int}
 */
function esLoadSubscriptionsPageData(PDO $pdo): array {
    $showRemoved = !empty($_GET['show_removed']);
    $selectedCategory = isset($_GET['category']) ? trim((string)$_GET['category']) : null;
    if ($selectedCategory === '') { $selectedCategory = null; }

    $t = esSubscriptionsTable();
    $stmt = $pdo->query("SELECT DISTINCT category FROM $t WHERE category IS NOT NULL AND category != '' AND removed_at IS NULL ORDER BY category");
    $categories = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];

    $where = $showRemoved ? 'removed_at IS NOT NULL' : 'removed_at IS NULL';
    $params = [];
    if ($selectedCategory && !$showRemoved) {
        $where .= ' AND category = ?';
        $params[] = $selectedCategory;
    }
    $sql = "SELECT * FROM $t WHERE $where ORDER BY last_seen_at DESC, display_name ASC";
    if ($params) {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $subscriptions = $st->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $subscriptions = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    $totalActive   = (int)$pdo->query("SELECT COUNT(*) FROM $t WHERE removed_at IS NULL")->fetchColumn();
    $disabledCount = (int)$pdo->query("SELECT COUNT(*) FROM $t WHERE removed_at IS NULL AND disabled = 1")->fetchColumn();
    $removedCount  = (int)$pdo->query("SELECT COUNT(*) FROM $t WHERE removed_at IS NOT NULL")->fetchColumn();

    return [
        'showRemoved'      => $showRemoved,
        'selectedCategory' => $selectedCategory,
        'categories'       => $categories,
        'subscriptions'    => $subscriptions,
        'totalActive'      => $totalActive,
        'disabledCount'    => $disabledCount,
        'removedCount'     => $removedCount,
    ];
}

/**
 * Legacy URL handler. Subscriptions management now lives on the Mail tab
 * under ?action=mail&view=subscriptions. Preserve query params so bookmarks
 * and any still-in-flight links keep working.
 */
function handleEmailSubscriptionsPage(PDO $pdo) {
    $q = ['action' => 'mail', 'view' => 'subscriptions'];
    if (!empty($_GET['show_removed'])) { $q['show_removed'] = 1; }
    if (!empty($_GET['category']))     { $q['category']     = (string)$_GET['category']; }
    header('Location: ' . getBasePath() . '/index.php?' . http_build_query($q));
    exit;
}

function handleAddEmailSubscription(PDO $pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ' . getBasePath() . '/index.php?action=mail&view=subscriptions');
        exit;
    }
    $matchType = ($_POST['match_type'] ?? '') === 'email' ? 'email' : 'domain';
    $matchValue = strtolower(trim($_POST['match_value'] ?? ''));
    $displayName = trim($_POST['display_name'] ?? '');
    $category = trim($_POST['category'] ?? '') ?: 'unsortiert';
    if ($matchValue === '') {
        $_SESSION['error'] = 'Match value is required.';
        header('Location: ' . getBasePath() . '/index.php?action=mail&view=subscriptions');
        exit;
    }
    if ($matchType === 'email' && strpos($matchValue, '@') === false) {
        $_SESSION['error'] = 'Enter a full email address for a specific sender.';
        header('Location: ' . getBasePath() . '/index.php?action=mail&view=subscriptions');
        exit;
    }
    if ($matchType === 'domain') {
        $matchValue = preg_replace('#^@#', '', $matchValue);
        if (strpos($matchValue, '@') !== false) {
            $_SESSION['error'] = 'Use a domain only (e.g. example.com), not an email.';
            header('Location: ' . getBasePath() . '/index.php?action=mail&view=subscriptions');
            exit;
        }
    }
    if ($displayName === '') {
        $displayName = $matchValue;
    }
    $t = esSubscriptionsTable();
    try {
        $ins = $pdo->prepare("INSERT INTO $t (match_type, match_value, display_name, category, disabled, auto_detected, removed_at)
            VALUES (?, ?, ?, ?, 0, 0, NULL)
            ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), category = VALUES(category), removed_at = NULL, disabled = 0");
        $ins->execute([$matchType, $matchValue, $displayName, $category]);
        $_SESSION['success'] = 'Subscription saved.';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Could not save: ' . $e->getMessage();
    }
    header('Location: ' . getBasePath() . '/index.php?action=mail&view=subscriptions');
    exit;
}

function handleEditEmailSubscription(PDO $pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ' . getBasePath() . '/index.php?action=mail&view=subscriptions');
        exit;
    }
    $id = (int)($_POST['id'] ?? 0);
    $displayName = trim($_POST['display_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    if ($id <= 0) {
        header('Location: ' . getBasePath() . '/index.php?action=mail&view=subscriptions');
        exit;
    }
    $t = esSubscriptionsTable();
    $pdo->prepare("UPDATE $t SET display_name = ?, category = ? WHERE id = ?")->execute([
        $displayName !== '' ? $displayName : 'Subscription',
        $category !== '' ? $category : null,
        $id,
    ]);
    $_SESSION['success'] = 'Subscription updated.';
    header('Location: ' . getBasePath() . '/index.php?action=mail&view=subscriptions');
    exit;
}

function handleToggleEmailSubscription(PDO $pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ' . getBasePath() . '/index.php?action=mail&view=subscriptions');
        exit;
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        header('Location: ' . getBasePath() . '/index.php?action=mail&view=subscriptions');
        exit;
    }
    $t = esSubscriptionsTable();
    $stmt = $pdo->prepare("SELECT disabled FROM $t WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        header('Location: ' . getBasePath() . '/index.php?action=mail&view=subscriptions');
        exit;
    }
    $new = (int)$row['disabled'] ? 0 : 1;
    $pdo->prepare("UPDATE $t SET disabled = ? WHERE id = ?")->execute([$new, $id]);
    $_SESSION['success'] = $new ? 'Subscription paused.' : 'Subscription resumed.';
    header('Location: ' . getBasePath() . '/index.php?action=mail&view=subscriptions');
    exit;
}

function handleDeleteEmailSubscription(PDO $pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ' . getBasePath() . '/index.php?action=mail&view=subscriptions');
        exit;
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        header('Location: ' . getBasePath() . '/index.php?action=mail&view=subscriptions');
        exit;
    }
    $t = esSubscriptionsTable();
    $pdo->prepare("UPDATE $t SET removed_at = NOW(), disabled = 1 WHERE id = ?")->execute([$id]);
    $_SESSION['success'] = 'Subscription removed. Future mail from this source will be hidden.';
    header('Location: ' . getBasePath() . '/index.php?action=mail&view=subscriptions');
    exit;
}

function handleRestoreEmailSubscription(PDO $pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ' . getBasePath() . '/index.php?action=mail&view=subscriptions');
        exit;
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        header('Location: ' . getBasePath() . '/index.php?action=mail&view=subscriptions');
        exit;
    }
    $t = esSubscriptionsTable();
    $pdo->prepare("UPDATE $t SET removed_at = NULL, disabled = 0 WHERE id = ?")->execute([$id]);
    $_SESSION['success'] = 'Subscription restored.';
    header('Location: ' . getBasePath() . '/index.php?action=mail&view=subscriptions&show_removed=1');
    exit;
}

function handleRenameEmailSubscriptionCategory(PDO $pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ' . getBasePath() . '/index.php?action=mail&view=subscriptions');
        exit;
    }
    $old = trim($_POST['old_category'] ?? '');
    $new = trim($_POST['new_category'] ?? '');
    if ($old === '' || $new === '' || $old === $new) {
        $_SESSION['error'] = 'Invalid rename.';
        header('Location: ' . getBasePath() . '/index.php?action=mail&view=subscriptions');
        exit;
    }
    $t = esSubscriptionsTable();
    $pdo->prepare("UPDATE $t SET category = ? WHERE category = ? AND removed_at IS NULL")->execute([$new, $old]);
    $_SESSION['success'] = 'Category renamed.';
    header('Location: ' . getBasePath() . '/index.php?action=mail&view=subscriptions');
    exit;
}

function handleRebuildEmailSubscriptions(PDO $pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ' . getBasePath() . '/index.php?action=mail&view=subscriptions');
        exit;
    }
    setMagnituConfig($pdo, 'email_subs_last_synced_id', '0');
    $n = esSyncNewEmails($pdo, 5000);
    $_SESSION['success'] = "Full sync processed {$n} new row(s). Stats rebuilt.";
    header('Location: ' . getBasePath() . '/index.php?action=mail&view=subscriptions');
    exit;
}

function esUnsubscribeUrlAllowedForSubscription(array $sub, string $url): bool {
    if (!preg_match('#^https://#i', $url)) {
        return false;
    }
    $p = parse_url($url, PHP_URL_HOST);
    if (!$p) {
        return false;
    }
    $host = strtolower($p);
    $mv = strtolower(trim($sub['match_value'] ?? ''));
    if ($sub['match_type'] === 'domain') {
        return $host === $mv || (strlen($host) > strlen($mv) + 1 && substr($host, -strlen($mv) - 1) === '.' . $mv);
    }
    if (strpos($mv, '@') !== false) {
        $dom = strtolower(substr(strrchr($mv, '@'), 1));
        return $host === $dom || (strlen($host) > strlen($dom) + 1 && substr($host, -strlen($dom) - 1) === '.' . $dom);
    }
    return false;
}

function handleUnsubscribeEmailSubscription(PDO $pdo) {
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($id <= 0) {
        $_SESSION['error'] = 'Invalid subscription.';
        header('Location: ' . getBasePath() . '/index.php?action=mail&view=subscriptions');
        exit;
    }
    $t = esSubscriptionsTable();
    $stmt = $pdo->prepare("SELECT * FROM $t WHERE id = ?");
    $stmt->execute([$id]);
    $sub = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$sub || !empty($sub['removed_at'])) {
        $_SESSION['error'] = 'Subscription not found.';
        header('Location: ' . getBasePath() . '/index.php?action=mail&view=subscriptions');
        exit;
    }

    $doPost = ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm_one_click'] ?? '') === '1');

    if (!empty($sub['unsubscribe_one_click']) && !empty($sub['unsubscribe_url']) && $doPost) {
        $url = $sub['unsubscribe_url'];
        if (!esUnsubscribeUrlAllowedForSubscription($sub, $url)) {
            $_SESSION['error'] = 'Unsubscribe URL is not on the expected domain; open it manually.';
            header('Location: ' . getBasePath() . '/index.php?action=mail&view=subscriptions');
            exit;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => 'List-Unsubscribe=One-Click',
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
        ]);
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 200 && $code < 300) {
            $pdo->prepare("UPDATE $t SET disabled = 1 WHERE id = ?")->execute([$id]);
            $_SESSION['success'] = 'Unsubscribe request sent. Subscription paused in Seismo.';
        } else {
            $_SESSION['error'] = 'Provider returned HTTP ' . $code . '. Use the link below to unsubscribe manually.';
        }
        header('Location: ' . getBasePath() . '/index.php?action=mail&view=subscriptions');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['mark_unsubscribed'] ?? '') === '1') {
        $pdo->prepare("UPDATE $t SET disabled = 1 WHERE id = ?")->execute([$id]);
        $_SESSION['success'] = 'Marked as unsubscribed (paused in Seismo).';
        header('Location: ' . getBasePath() . '/index.php?action=mail&view=subscriptions');
        exit;
    }

    include __DIR__ . '/../views/mail_unsubscribe_confirm.php';
}
