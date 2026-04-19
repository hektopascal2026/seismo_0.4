<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($feed['title']) ?> - Seismo</title>
    <link rel="stylesheet" href="<?= getBasePath() ?>/assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="top-bar">
            <div class="top-bar-left">
                <span class="top-bar-title">
                    <a href="?action=index">
                        <svg class="logo-icon logo-icon-large" viewBox="0 0 24 16" xmlns="http://www.w3.org/2000/svg">
                            <rect width="24" height="16" fill="#FFFFC5"/>
                            <path d="M0,8 L4,12 L6,4 L10,10 L14,2 L18,8 L20,6 L24,8" stroke="#000000" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </a>
                    <?= htmlspecialchars($feed['title']) ?>
                </span>
            </div>
            <div class="top-bar-actions">
                <a href="?action=refresh_all&from=view_feed&id=<?= $feed['id'] ?>" class="top-bar-btn" title="Refresh all sources"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/></svg></a>
                <button type="button" class="top-bar-btn" id="menuToggle" title="Menu"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
            </div>
        </div>

        <?php $navActive = 'feeds'; include __DIR__ . '/partials/nav.php'; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="message message-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <div class="feed-header">
            <div class="feed-info">
                <?php if ($feed['description']): ?>
                    <p class="feed-description"><?= htmlspecialchars($feed['description']) ?></p>
                <?php endif; ?>
                <p class="feed-meta-small">
                    <a href="<?= htmlspecialchars($feed['link'] ?: $feed['url']) ?>" target="_blank" class="feed-link"><?= htmlspecialchars($feed['url']) ?></a>
                    <?php if ($feed['last_fetched']): ?>
                        | Last updated: <?= date('M j, Y g:i A', strtotime($feed['last_fetched'])) ?>
                    <?php endif; ?>
                </p>
            </div>
            <div class="feed-actions-top">
                <a href="?action=refresh_feed&id=<?= $feed['id'] ?>" class="btn btn-primary">Refresh Feed</a>
                <a href="?action=feeds" class="btn btn-secondary">Back to RSS</a>
            </div>
        </div>

        <?php if ($needsRefresh): ?>
            <div class="message message-info">
                This feed may be outdated. Click "Refresh Feed" to update.
            </div>
        <?php endif; ?>

        <div class="items-list">
            <?php if (empty($items)): ?>
                <div class="empty-state">
                    <p>No items found in this feed. Try refreshing the feed.</p>
                </div>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <?php
                        $itemUrl = seismo_feed_item_resolved_link($item);
                        $bodyHtml = trim((string) ($item['content'] ?: $item['description']));
                    ?>
                    <article class="entry-card">
                        <div class="entry-header">
                            <?php if ($item['author']): ?>
                                <span class="entry-tag" style="background-color: #add8e6;"><?= htmlspecialchars($item['author']) ?></span>
                            <?php else: ?>
                                <span class="entry-tag" style="background-color: #add8e6;"><?= htmlspecialchars($feed['title']) ?></span>
                            <?php endif; ?>
                        </div>
                        <h3 class="entry-title">
                            <?php if ($itemUrl !== ''): ?>
                                <a href="<?= htmlspecialchars($itemUrl) ?>" target="_blank" rel="noopener">
                                    <?= htmlspecialchars($item['title']) ?>
                                </a>
                            <?php else: ?>
                                <?= htmlspecialchars($item['title']) ?>
                            <?php endif; ?>
                        </h3>
                        <?php if ($bodyHtml !== ''): ?>
                            <div class="entry-content">
                                <?= $item['content'] ? $item['content'] : strip_tags($item['description'], '<p><a><strong><em><br>') ?>
                                <?php if ($itemUrl !== ''): ?>
                                    <a href="<?= htmlspecialchars($itemUrl) ?>" target="_blank" rel="noopener" class="entry-link" style="margin-left: 4px;">Read more →</a>
                                <?php endif; ?>
                            </div>
                        <?php elseif ($itemUrl !== '' && !empty($item['title'])): ?>
                            <div class="entry-content">
                                <p><?= htmlspecialchars($item['title']) ?></p>
                                <a href="<?= htmlspecialchars($itemUrl) ?>" target="_blank" rel="noopener" class="entry-link" style="margin-left: 4px;">Read more →</a>
                            </div>
                        <?php elseif ($itemUrl === ''): ?>
                            <p class="entry-meta-muted" style="font-size: 12px; margin: 4px 0 0 0; color: #555;">No URL in this feed entry (source item is incomplete).</p>
                        <?php endif; ?>
                        <div class="entry-actions">
                            <?php if ($item['published_date']): ?>
                                <span class="entry-date"><?= date('d.m.Y H:i', strtotime($item['published_date'])) ?></span>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    (function() {
        var menuBtn = document.getElementById('menuToggle');
        var navDrawer = document.getElementById('navDrawer');
        menuBtn.addEventListener('click', function() {
            navDrawer.classList.toggle('open');
            menuBtn.classList.toggle('active');
        });
    })();
    </script>
</body>
</html>
