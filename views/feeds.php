<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RSS - Seismo</title>
    <link rel="stylesheet" href="<?= getBasePath() ?>/assets/css/style.css">
</head>
<body>
    <div class="container">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="top-bar-left">
                <span class="top-bar-title">
                    <a href="?action=index">
                        <svg class="logo-icon logo-icon-large" viewBox="0 0 24 16" xmlns="http://www.w3.org/2000/svg">
                            <rect width="24" height="16" fill="#FFFFC5"/>
                            <path d="M0,8 L4,12 L6,4 L10,10 L14,2 L18,8 L20,6 L24,8" stroke="#000000" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </a>
                    RSS
                </span>
                <span class="top-bar-subtitle">RSS entries</span>
            </div>
            <div class="top-bar-actions">
                <a href="?action=refresh_all_feeds&from=feeds<?= $selectedCategory ? '&category=' . urlencode($selectedCategory) : '' ?>" class="top-bar-btn" title="Refresh RSS feeds"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/></svg></a>
                <button type="button" class="top-bar-btn" id="menuToggle" title="Menu"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
            </div>
        </div>

        <nav class="nav-drawer" id="navDrawer">
            <a href="?action=index" class="nav-link">Feed</a>
            <a href="?action=magnitu" class="nav-link">Magnitu</a>
            <a href="?action=feeds" class="nav-link active" style="background-color: #add8e6; color: #000000;">RSS</a>
            <a href="?action=lex" class="nav-link">Lex</a>
            <a href="?action=mail" class="nav-link">Mail</a>
            <a href="?action=substack" class="nav-link">Substack</a>
            <a href="?action=settings" class="nav-link">Settings</a>
            <a href="?action=about" class="nav-link">About</a>
            <a href="?action=beta" class="nav-link">Beta</a>
        </nav>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="message message-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="message message-error"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (!empty($categories) || isset($selectedCategory)): ?>
        <div class="category-filter-section">
            <div class="category-filter">
                <a href="?action=feeds"
                   class="category-btn <?= !$selectedCategory ? 'active' : '' ?>"
                   <?= !$selectedCategory ? 'style="background-color: #add8e6;"' : '' ?>>
                    All
                </a>
                <?php foreach ($categories as $category): ?>
                    <a href="?action=feeds&category=<?= urlencode($category) ?>"
                       class="category-btn <?= $selectedCategory === $category ? 'active' : '' ?>"
                       <?= $selectedCategory === $category ? 'style="background-color: #add8e6;"' : '' ?>>
                        <?= htmlspecialchars($category) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="latest-entries-section">
            <div class="section-title-row">
                <h2 class="section-title">
                    <?php if ($lastRssRefreshDate): ?>
                        Refreshed: <?= htmlspecialchars($lastRssRefreshDate) ?>
                    <?php else: ?>
                        Refreshed: Never
                    <?php endif; ?>
                </h2>
                <button class="btn btn-secondary entry-expand-all-btn">&#9660; expand all</button>
            </div>

            <?php if (empty($rssItems)): ?>
                <div class="empty-state">
                    <?php if ($selectedCategory): ?>
                        <p>No entries found in category "<?= htmlspecialchars($selectedCategory) ?>". <a href="?action=feeds">View all entries</a></p>
                    <?php else: ?>
                        <p>No RSS entries yet. Add feeds above to see entries here.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($rssItems as $item): ?>
                    <?php
                        $fullContent = strip_tags($item['content'] ?: $item['description']);
                        $contentPreview = mb_substr($fullContent, 0, 200);
                        if (mb_strlen($fullContent) > 200) $contentPreview .= '...';
                        $hasMore = mb_strlen($fullContent) > 200;
                    ?>
                    <div class="entry-card">
                        <div class="entry-header">
                            <?php if (!empty($item['feed_category']) && $item['feed_category'] !== 'unsortiert'): ?>
                                <span class="entry-tag" style="background-color: #add8e6;"><?= htmlspecialchars($item['feed_category']) ?></span>
                            <?php endif; ?>
                        </div>
                        <h3 class="entry-title">
                            <a href="<?= htmlspecialchars($item['link']) ?>" target="_blank" rel="noopener">
                                <?= htmlspecialchars($item['title']) ?>
                            </a>
                        </h3>
                        <?php if ($item['description'] || $item['content']): ?>
                            <div class="entry-content entry-preview">
                                <?= htmlspecialchars($contentPreview) ?>
                                <?php if ($item['link']): ?>
                                    <a href="<?= htmlspecialchars($item['link']) ?>" target="_blank" rel="noopener" class="entry-link" style="margin-left: 4px;">Read more →</a>
                                <?php endif; ?>
                            </div>
                            <div class="entry-full-content" style="display:none"><?= htmlspecialchars($fullContent) ?></div>
                        <?php endif; ?>
                        <div class="entry-actions">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <?php if ($hasMore): ?>
                                    <button class="btn btn-secondary entry-expand-btn">&#9660; expand</button>
                                <?php endif; ?>
                            </div>
                            <?php if ($item['published_date']): ?>
                                <span class="entry-date"><?= date('d.m.Y H:i', strtotime($item['published_date'])) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
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
    <script>
    (function() {
        function collapseEntry(card, btn) {
            var preview = card.querySelector('.entry-preview');
            var full = card.querySelector('.entry-full-content');
            if (!preview || !full) return;
            full.style.display = 'none';
            preview.style.display = '';
            if (btn) btn.textContent = '\u25BC expand';
        }

        function expandEntry(card, btn) {
            var preview = card.querySelector('.entry-preview');
            var full = card.querySelector('.entry-full-content');
            if (!preview || !full) return;
            preview.style.display = 'none';
            full.style.display = 'block';
            if (btn) btn.textContent = '\u25B2 collapse';
        }

        // Per-entry toggle
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.entry-expand-btn');
            if (!btn) return;
            var card = btn.closest('.entry-card');
            var full = card.querySelector('.entry-full-content');
            if (!full) return;
            if (full.style.display === 'block') {
                collapseEntry(card, btn);
            } else {
                expandEntry(card, btn);
            }
        });

        // Global toggle
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.entry-expand-all-btn');
            if (!btn) return;
            var isExpanded = btn.dataset.expanded === 'true';
            document.querySelectorAll('.entry-card').forEach(function(card) {
                var entryBtn = card.querySelector('.entry-expand-btn');
                if (isExpanded) {
                    collapseEntry(card, entryBtn);
                } else {
                    expandEntry(card, entryBtn);
                }
            });
            btn.dataset.expanded = !isExpanded;
            btn.textContent = !isExpanded ? '\u25B2 collapse all' : '\u25BC expand all';
        });
    })();
    </script>
</body>
</html>
