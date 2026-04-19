<?php

declare(strict_types=1);

session_start();

require dirname(__DIR__) . '/bootstrap.php';

$flashOk = $_SESSION['srf_success'] ?? null;
$flashErr = $_SESSION['srf_error'] ?? null;
unset($_SESSION['srf_success'], $_SESSION['srf_error']);

/**
 * @return string HTML-safe text with <mark> around first match of query
 */
function srf_highlight(string $text, string $q): string
{
    $e = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $q = trim($q);
    if ($q === '') {
        return $e;
    }
    $pattern = '/' . preg_quote($q, '/') . '/iu';
    return preg_replace($pattern, '<mark class="search-highlight">$0</mark>', $e) ?? $e;
}

$dbOk = srf_configured();
$pdo = null;
$schemaOk = false;
$dbError = null;

if ($dbOk) {
    try {
        $pdo = srf_pdo();
        $chk = $pdo->query("SHOW TABLES LIKE 'srf_items'");
        $schemaOk = $chk && $chk->fetch() !== false;
    } catch (Throwable $e) {
        $dbError = $e->getMessage();
    }
}

$action = $_GET['action'] ?? 'list';
$settingsSessKey = 'srf_settings_ok';

if ($action === 'settings') {
    if (!$dbOk || !$pdo || !$schemaOk) {
        header('Content-Type: text/html; charset=UTF-8');
        ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings — SRF Monitor</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(srf_assets_href(), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<div class="container">
    <p class="message message-error">Database not ready. Configure <code>srf/config.local.php</code> and import <code>srf/sql/schema.sql</code>.</p>
    <p><a href="index.php" class="about-link">Back to list</a></p>
</div>
</body>
</html>
        <?php
        exit;
    }
    if (SRF_WEB_SYNC_SECRET === '') {
        header('Content-Type: text/html; charset=UTF-8');
        ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings — SRF Monitor</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(srf_assets_href(), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<div class="container">
    <p class="message message-error">Set <code>SRF_WEB_SYNC_SECRET</code> in <code>srf/config.local.php</code> (same value you use for Sync now).</p>
    <p><a href="index.php" class="about-link">Back to list</a></p>
</div>
</body>
</html>
        <?php
        exit;
    }
    if (isset($_GET['logout'])) {
        unset($_SESSION[$settingsSessKey]);
        header('Location: index.php?action=settings');
        exit;
    }
    $repoSt = new ItemRepository($pdo);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $do = (string) ($_POST['settings_do'] ?? '');
        if ($do === 'login') {
            $p = (string) ($_POST['sync_secret'] ?? '');
            if (hash_equals(SRF_WEB_SYNC_SECRET, $p)) {
                $_SESSION[$settingsSessKey] = 1;
                header('Location: index.php?action=settings');
                exit;
            }
            $flashErr = trim(($flashErr ?? '') . ' Invalid secret.');
        } elseif ($do === 'save') {
            if (empty($_SESSION[$settingsSessKey])) {
                $flashErr = 'Session expired. Log in again.';
            } else {
                [$filters, $errs] = SubtitleFilterSettings::parseFromRequest($_POST);
                if ($errs !== []) {
                    $flashErr = implode(' ', $errs);
                } else {
                    $repoSt->stateSet(SubtitleFilterSettings::STATE_KEY, json_encode($filters, JSON_UNESCAPED_UNICODE));
                    $_SESSION['srf_success'] = 'Subtitle filters saved to the database.';
                    header('Location: index.php?action=settings');
                    exit;
                }
            }
        } elseif ($do === 'reset') {
            if (empty($_SESSION[$settingsSessKey])) {
                $flashErr = 'Session expired. Log in again.';
            } else {
                $repoSt->stateDelete(SubtitleFilterSettings::STATE_KEY);
                $_SESSION['srf_success'] = 'Database override removed. Values from config.json apply again.';
                header('Location: index.php?action=settings');
                exit;
            }
        }
    }
    $flashOk = $_SESSION['srf_success'] ?? $flashOk;
    $flashErr = $_SESSION['srf_error'] ?? $flashErr;
    unset($_SESSION['srf_success'], $_SESSION['srf_error']);

    $authed = !empty($_SESSION[$settingsSessKey]);
    $dbOverrideRaw = $repoSt->stateGet(SubtitleFilterSettings::STATE_KEY);
    $usingDbOverride = $dbOverrideRaw !== null && $dbOverrideRaw !== '';
    $effective = srf_effective_config($pdo);
    $fil = $effective['subtitle_filters'] ?? [];
    if (!is_array($fil)) {
        $fil = [];
    }
    $saveFailed = $_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['settings_do'] ?? '') === 'save' && $flashErr !== null && $flashErr !== '';
    if ($authed && $saveFailed) {
        $form = [
            'allow_show_ids' => (string) ($_POST['allow_show_ids'] ?? ''),
            'allow_channel_ids' => (string) ($_POST['allow_channel_ids'] ?? ''),
            'title_must_match_regex' => (string) ($_POST['title_must_match_regex'] ?? ''),
            'title_must_not_match_regex' => (string) ($_POST['title_must_not_match_regex'] ?? ''),
        ];
    } elseif ($authed) {
        $form = SubtitleFilterSettings::toForm($fil);
    } else {
        $form = ['allow_show_ids' => '', 'allow_channel_ids' => '', 'title_must_match_regex' => '', 'title_must_not_match_regex' => ''];
    }

    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subtitle filters — SRF Monitor</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(srf_assets_href(), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<div class="container">
    <div class="top-bar">
        <div class="top-bar-left">
            <span class="top-bar-title">SRF Monitor</span>
            <span class="top-bar-subtitle">Subtitle fetch filters</span>
        </div>
        <div class="top-bar-actions">
            <a href="index.php" class="top-bar-btn" title="List" style="text-decoration:none;color:inherit;display:inline-flex;align-items:center;padding:8px;">List</a>
        </div>
    </div>

    <?php if ($flashOk): ?>
        <div class="message message-success"><?= htmlspecialchars($flashOk, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($flashErr): ?>
        <div class="message message-error"><?= htmlspecialchars($flashErr, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="latest-entries-section">
        <p style="max-width:720px;line-height:1.5;">
            Control which episodes trigger the Play Subtitles API. Rules apply to the combined text of title, description, show name, and channel name.
            If both allowlists are filled, a row must match <strong>show</strong> or <strong>channel</strong>. Empty fields mean no restriction for that rule.
        </p>
        <p style="max-width:720px;font-size:13px;color:#555;">
            <?php if ($usingDbOverride): ?>
                <strong>Active source:</strong> filters stored in the database (they replace <code>subtitle_filters</code> in <code>config.json</code> for CLI and web sync).
            <?php else: ?>
                <strong>Active source:</strong> <code>config.json</code> only (no database override). Save here to store overrides in the database.
            <?php endif; ?>
        </p>

        <?php if (!$authed): ?>
            <h2 class="section-title" style="margin-top:20px;">Sign in</h2>
            <p style="font-size:13px;">Use the same secret as <strong>Sync now</strong> (<code>SRF_WEB_SYNC_SECRET</code>).</p>
            <form method="post" action="index.php?action=settings" style="max-width:420px;margin-top:12px;">
                <input type="hidden" name="settings_do" value="login">
                <label for="sync_secret" style="display:block;font-weight:600;margin-bottom:6px;">Secret</label>
                <input type="password" name="sync_secret" id="sync_secret" class="search-input" required autocomplete="current-password" style="width:100%;box-sizing:border-box;margin-bottom:12px;">
                <button type="submit" class="btn btn-primary">Continue</button>
            </form>
        <?php else: ?>
            <p style="margin:12px 0;"><a href="index.php?action=settings&amp;logout=1" class="btn btn-secondary">Log out</a></p>
            <form method="post" action="index.php?action=settings" style="max-width:720px;">
                <input type="hidden" name="settings_do" value="save">
                <h2 class="section-title" style="margin-top:8px;">Allowlists</h2>
                <label for="allow_show_ids" style="display:block;font-weight:600;margin:12px 0 6px;">Show IDs (one per line or comma-separated)</label>
                <textarea name="allow_show_ids" id="allow_show_ids" class="search-input" rows="4" style="width:100%;box-sizing:border-box;font-family:inherit;"><?= htmlspecialchars($form['allow_show_ids'], ENT_QUOTES, 'UTF-8') ?></textarea>
                <label for="allow_channel_ids" style="display:block;font-weight:600;margin:12px 0 6px;">Channel IDs (one per line or comma-separated)</label>
                <textarea name="allow_channel_ids" id="allow_channel_ids" class="search-input" rows="4" style="width:100%;box-sizing:border-box;font-family:inherit;"><?= htmlspecialchars($form['allow_channel_ids'], ENT_QUOTES, 'UTF-8') ?></textarea>

                <h2 class="section-title" style="margin-top:24px;">Regex (PCRE)</h2>
                <label for="title_must_match_regex" style="display:block;font-weight:600;margin:12px 0 6px;">Must match (episode must match this pattern)</label>
                <input type="text" name="title_must_match_regex" id="title_must_match_regex" class="search-input" value="<?= htmlspecialchars($form['title_must_match_regex'], ENT_QUOTES, 'UTF-8') ?>" style="width:100%;box-sizing:border-box;font-family:monospace;font-size:12px;">
                <label for="title_must_not_match_regex" style="display:block;font-weight:600;margin:12px 0 6px;">Must not match (exclude if pattern matches)</label>
                <input type="text" name="title_must_not_match_regex" id="title_must_not_match_regex" class="search-input" value="<?= htmlspecialchars($form['title_must_not_match_regex'], ENT_QUOTES, 'UTF-8') ?>" style="width:100%;box-sizing:border-box;font-family:monospace;font-size:12px;">

                <div style="margin-top:20px;display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
                    <button type="submit" class="btn btn-primary">Save filters</button>
                </div>
            </form>
            <form method="post" action="index.php?action=settings" style="margin-top:24px;" onsubmit="return confirm('Remove database override and use config.json only?');">
                <input type="hidden" name="settings_do" value="reset">
                <button type="submit" class="btn btn-secondary">Reset to config.json only</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
    <?php
    exit;
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;
$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

$items = [];
$total = 0;
$lastSync = null;

if ($pdo && $schemaOk) {
    $repo = new ItemRepository($pdo);
    $lastSync = $repo->stateGet('last_list_sync');
    try {
        $items = $repo->search($q !== '' ? $q : null, $perPage, $offset);
        $total = $repo->countSearch($q !== '' ? $q : null);
    } catch (Throwable $e) {
        $dbError = $e->getMessage();
    }
}

if ($action === 'sync' && $pdo && $schemaOk) {
    $secret = SRF_WEB_SYNC_SECRET;
    $provided = (string) ($_GET['secret'] ?? '');
    if ($secret === '' || !hash_equals($secret, $provided)) {
        $_SESSION['srf_error'] = 'Sync disabled or invalid secret (set SRF_WEB_SYNC_SECRET in config.local.php). Use CLI: srf/bin/fetch.php';
        header('Location: index.php');
        exit;
    }
    if (!srf_srg_configured()) {
        $_SESSION['srf_error'] = 'Configure SRG_CONSUMER_KEY and SRG_CONSUMER_SECRET in config.local.php.';
        header('Location: index.php');
        exit;
    }
    $fetcher = new FetchService(new ItemRepository($pdo), srf_effective_config($pdo));
    $result = $fetcher->run(true, 20, 150);
    $skipF = (int) ($result['subtitles_skipped_filter'] ?? 0);
    $msg = sprintf(
        'Sync: %d episodes indexed, %d subtitle texts updated (%d API tries, %d with no caption payload%s).',
        $result['episodes_seen'],
        $result['subtitles_fetched'],
        $result['subtitles_attempted'] ?? 0,
        $result['subtitles_no_text'] ?? 0,
        $skipF > 0 ? ', ' . $skipF . ' skipped by subtitle_filters' : ''
    );
    if ($result['errors'] !== []) {
        $msg .= ' Warnings: ' . implode(' | ', array_slice($result['errors'], 0, 3));
    }
    $_SESSION['srf_success'] = $msg;
    header('Location: index.php');
    exit;
}

$totalPages = max(1, (int) ceil($total / $perPage));

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SRF Monitor</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(srf_assets_href(), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<div class="container">
    <div class="top-bar">
        <div class="top-bar-left">
            <span class="top-bar-title">SRF Monitor</span>
            <span class="top-bar-subtitle">Broadcast text (SRG API + subtitles) · standalone</span>
        </div>
        <div class="top-bar-actions">
            <button type="button" class="top-bar-btn" id="searchToggle" title="Search">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="7"/><line x1="16.5" y1="16.5" x2="21" y2="21"/></svg>
            </button>
            <button type="button" class="top-bar-btn" id="menuToggle" title="Menu">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
        </div>
    </div>

    <nav class="nav-drawer" id="navDrawer">
        <span class="nav-link active" style="cursor:default;">SRF Monitor</span>
        <a href="index.php?action=settings" class="nav-link">Subtitle filters</a>
        <a href="../../index.php" class="nav-link">Seismo</a>
    </nav>

    <div class="search-drawer" id="searchDrawer">
        <form method="get" class="search-form" action="index.php">
            <input type="search" name="q" placeholder="Search title, description, subtitles…" class="search-input" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" style="min-width:0;">
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if ($q !== ''): ?>
                <a href="index.php" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($flashOk): ?>
        <div class="message message-success"><?= htmlspecialchars($flashOk, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($flashErr): ?>
        <div class="message message-error"><?= htmlspecialchars($flashErr, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="latest-entries-section">
        <div class="section-title-row">
            <h2 class="section-title">
                <?php if (!$dbOk): ?>
                    Database not configured
                <?php elseif (!$schemaOk): ?>
                    Schema missing — import <code>srf/sql/schema.sql</code>
                <?php else: ?>
                    <?= (int) $total ?> entr<?= $total === 1 ? 'y' : 'ies' ?>
                    <?php if ($lastSync): ?> · last list sync <?= htmlspecialchars($lastSync, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                <?php endif; ?>
            </h2>
            <?php if ($schemaOk && srf_srg_configured() && SRF_WEB_SYNC_SECRET !== ''): ?>
                <a class="btn btn-secondary" href="index.php?action=sync&amp;secret=<?= urlencode(SRF_WEB_SYNC_SECRET) ?>">Sync now</a>
            <?php endif; ?>
        </div>

        <?php if ($dbError): ?>
            <div class="message message-error"><?= htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if (!$dbOk): ?>
            <div class="empty-state">
                <p>Copy <code>srf/config.local.php.example</code> to <code>srf/config.local.php</code> and set <code>SRF_DB_*</code>.</p>
            </div>
        <?php elseif (!$schemaOk): ?>
            <div class="empty-state">
                <p>Run: <code>mysql -u … -p <?= htmlspecialchars(SRF_DB_NAME, ENT_QUOTES, 'UTF-8') ?> &lt; srf/sql/schema.sql</code></p>
            </div>
        <?php elseif (empty($items)): ?>
            <div class="empty-state">
                <p>No items yet. Configure SRG keys in <code>config.local.php</code>, verify URLs in <code>config.json</code> against the <a href="https://developer.srgssr.ch/" class="about-link" target="_blank" rel="noopener">SRG developer portal</a>, then run:</p>
                <p><code>php <?= htmlspecialchars(dirname(__DIR__) . '/bin/fetch.php', ENT_QUOTES, 'UTF-8') ?></code></p>
            </div>
        <?php else: ?>
            <button type="button" class="btn btn-secondary entry-expand-all-btn" style="margin-bottom:12px;">expand all &#9660;</button>
            <?php foreach ($items as $item): ?>
                <?php
                $title = (string) ($item['title'] ?? 'Untitled');
                $desc = (string) ($item['description'] ?? '');
                $sub = (string) ($item['subtitle_text'] ?? '');
                $preview = $sub !== '' ? mb_substr($sub, 0, 280) : mb_substr($desc, 0, 280);
                if (mb_strlen($sub) > 280 || ($sub === '' && mb_strlen($desc) > 280)) {
                    $preview .= '…';
                }
                $fullText = $sub !== '' ? $sub : $desc;
                $hasMore = mb_strlen($fullText) > 280;
                $link = (string) ($item['permalink'] ?? '');
                $pub = $item['published_at'] ?? null;
                ?>
                <div class="entry-card">
                    <div class="entry-header">
                        <span class="entry-tag" style="background-color:#FFFFC5;"><?= htmlspecialchars((string) ($item['bu'] ?? 'srf'), ENT_QUOTES, 'UTF-8') ?></span>
                        <?php if (!empty($item['subtitles_available'])): ?>
                            <span class="entry-tag" style="background-color:#add8e6;">subtitles</span>
                        <?php endif; ?>
                        <?php if (!empty($item['subtitle_lang'])): ?>
                            <span class="entry-tag"><?= htmlspecialchars((string) $item['subtitle_lang'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </div>
                    <h3 class="entry-title">
                        <?php if ($link !== ''): ?>
                            <a href="<?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= $q !== '' ? srf_highlight($title, $q) : htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></a>
                        <?php else: ?>
                            <?= $q !== '' ? srf_highlight($title, $q) : htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                    </h3>
                    <?php if ($preview !== ''): ?>
                        <div class="entry-content entry-preview">
                            <?= $q !== '' ? srf_highlight($preview, $q) : htmlspecialchars($preview, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <div class="entry-full-content" style="display:none">
                            <?= $q !== '' ? srf_highlight($fullText, $q) : htmlspecialchars($fullText, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>
                    <div class="entry-meta" style="font-size:12px;color:#666;margin-top:8px;">
                        <code><?= htmlspecialchars((string) ($item['urn'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code>
                    </div>
                    <div class="entry-actions">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <?php if ($hasMore): ?>
                                <button type="button" class="btn btn-secondary entry-expand-btn">expand &#9660;</button>
                            <?php endif; ?>
                        </div>
                        <?php if ($pub): ?>
                            <span class="entry-date"><?= htmlspecialchars(date('d.m.Y H:i', strtotime((string) $pub)), ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if ($totalPages > 1): ?>
                <div class="category-filter-section" style="margin-top:20px;">
                    <div class="category-filter">
                        <?php if ($page > 1): ?>
                            <a class="category-btn" href="?page=<?= $page - 1 ?><?= $q !== '' ? '&amp;q=' . urlencode($q) : '' ?>">← Prev</a>
                        <?php endif; ?>
                        <span class="category-btn" style="cursor:default;">Page <?= $page ?> / <?= $totalPages ?></span>
                        <?php if ($page < $totalPages): ?>
                            <a class="category-btn" href="?page=<?= $page + 1 ?><?= $q !== '' ? '&amp;q=' . urlencode($q) : '' ?>">Next →</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
(function() {
    var menuBtn = document.getElementById('menuToggle');
    var navDrawer = document.getElementById('navDrawer');
    if (menuBtn && navDrawer) {
        menuBtn.addEventListener('click', function() {
            navDrawer.classList.toggle('open');
            menuBtn.classList.toggle('active');
        });
    }
    var searchBtn = document.getElementById('searchToggle');
    var searchDrawer = document.getElementById('searchDrawer');
    if (searchBtn && searchDrawer) {
        searchBtn.addEventListener('click', function() {
            searchDrawer.classList.toggle('open');
        });
    }
})();
(function() {
    function collapseEntry(card, btn) {
        var preview = card.querySelector('.entry-preview');
        var full = card.querySelector('.entry-full-content');
        if (!preview || !full) return;
        full.style.display = 'none';
        preview.style.display = '';
        if (btn) btn.textContent = 'expand \u25BC';
    }
    function expandEntry(card, btn) {
        var preview = card.querySelector('.entry-preview');
        var full = card.querySelector('.entry-full-content');
        if (!preview || !full) return;
        preview.style.display = 'none';
        full.style.display = 'block';
        if (btn) btn.textContent = 'collapse \u25B2';
    }
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.entry-expand-btn');
        if (!btn) return;
        var card = btn.closest('.entry-card');
        var full = card.querySelector('.entry-full-content');
        if (!full) return;
        if (full.style.display === 'block') collapseEntry(card, btn);
        else expandEntry(card, btn);
    });
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.entry-expand-all-btn');
        if (!btn) return;
        var isExpanded = btn.dataset.expanded === 'true';
        document.querySelectorAll('.entry-card').forEach(function(card) {
            var entryBtn = card.querySelector('.entry-expand-btn');
            if (isExpanded) collapseEntry(card, entryBtn);
            else expandEntry(card, entryBtn);
        });
        btn.dataset.expanded = !isExpanded;
        btn.textContent = !isExpanded ? 'collapse all \u25B2' : 'expand all \u25BC';
    });
})();
</script>
</body>
</html>
