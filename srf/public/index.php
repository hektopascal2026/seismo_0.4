<?php

declare(strict_types=1);

session_start();

require dirname(__DIR__) . '/bootstrap.php';

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
    $fetcher = new FetchService(new ItemRepository($pdo), srf_merged_config());
    $result = $fetcher->run(true, 20, 150);
    $msg = sprintf(
        'Sync: %d episodes indexed, %d subtitle texts updated (%d subtitle API tries).',
        $result['episodes_seen'],
        $result['subtitles_fetched'],
        $result['subtitles_attempted'] ?? 0
    );
    if ($result['errors'] !== []) {
        $msg .= ' Warnings: ' . implode(' | ', array_slice($result['errors'], 0, 3));
    }
    $_SESSION['srf_success'] = $msg;
    header('Location: index.php');
    exit;
}

$flashOk = $_SESSION['srf_success'] ?? null;
$flashErr = $_SESSION['srf_error'] ?? null;
unset($_SESSION['srf_success'], $_SESSION['srf_error']);

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
