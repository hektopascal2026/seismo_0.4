<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Substack - Seismo</title>
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
                    Substack
                </span>
                <span class="top-bar-subtitle">Substack newsletters</span>
            </div>
            <div class="top-bar-actions">
                <a href="?action=refresh_all&from=substack" class="top-bar-btn" title="Refresh all sources"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/></svg></a>
                <button type="button" class="top-bar-btn" id="menuToggle" title="Menu"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
            </div>
        </div>

        <?php $navActive = 'substack'; include __DIR__ . '/partials/nav.php'; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="message message-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="message message-error"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (!empty($substackCategories)): ?>
        <div class="category-filter-section">
            <div class="category-filter">
                <a href="?action=substack"
                   class="category-btn <?= !$selectedSubstackCategory ? 'active' : '' ?>"
                   <?= !$selectedSubstackCategory ? 'style="background-color: #C5B4D1;"' : '' ?>>
                    All
                </a>
                <?php foreach ($substackCategories as $category): ?>
                    <a href="?action=substack&category=<?= urlencode($category) ?>"
                       class="category-btn <?= $selectedSubstackCategory === $category ? 'active' : '' ?>"
                       <?= $selectedSubstackCategory === $category ? 'style="background-color: #C5B4D1;"' : '' ?>>
                        <?= htmlspecialchars($category) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="latest-entries-section">
            <div class="section-title-row">
                <h2 class="section-title">
                    <?php if ($lastSubstackRefreshDate): ?>
                        Refreshed: <?= htmlspecialchars($lastSubstackRefreshDate) ?>
                    <?php else: ?>
                        Refreshed: Never
                    <?php endif; ?>
                </h2>
                <button class="btn btn-secondary entry-expand-all-btn">expand all &#9660;</button>
            </div>

            <?php if (empty($substackItems)): ?>
                <div class="empty-state">
                    <?php if ($selectedSubstackCategory): ?>
                        <p>No entries found in "<?= htmlspecialchars($selectedSubstackCategory) ?>". <a href="?action=substack">View all entries</a></p>
                    <?php else: ?>
                        <p>No Substack posts yet. Subscribe to a newsletter above to see posts here.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($substackItems as $item): ?>
                    <?php
                        $itemUrl = seismo_feed_item_resolved_link($item);
                        $fullContent = trim(strip_tags((string) ($item['content'] ?: $item['description'])));
                        if ($fullContent === '' && $itemUrl !== '' && !empty($item['title'])) {
                            $fullContent = trim((string) $item['title']);
                        }
                        $contentPreview = mb_substr($fullContent, 0, 200);
                        if (mb_strlen($fullContent) > 200) {
                            $contentPreview .= '...';
                        }
                        $hasMore = mb_strlen($fullContent) > 200;
                    ?>
                    <div class="entry-card">
                        <div class="entry-header">
                            <?php if (!empty($item['feed_category']) && $item['feed_category'] !== 'unsortiert'): ?>
                                <span class="entry-tag" style="background-color: #C5B4D1;"><?= htmlspecialchars($item['feed_category']) ?></span>
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
                        <?php if ($fullContent !== ''): ?>
                            <div class="entry-content entry-preview">
                                <?= htmlspecialchars($contentPreview) ?>
                                <?php if ($itemUrl !== ''): ?>
                                    <a href="<?= htmlspecialchars($itemUrl) ?>" target="_blank" rel="noopener" class="entry-link" style="margin-left: 4px;">Read more →</a>
                                <?php endif; ?>
                            </div>
                            <div class="entry-full-content" style="display:none"><?= htmlspecialchars($fullContent) ?></div>
                        <?php endif; ?>
                        <?php if ($itemUrl === ''): ?>
                            <p class="entry-meta-muted" style="font-size: 12px; margin: 4px 0 0 0; color: #555;">No URL in this feed entry (source item is incomplete).</p>
                        <?php endif; ?>
                        <div class="entry-actions">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <?php if ($hasMore): ?>
                                    <button class="btn btn-secondary entry-expand-btn">expand &#9660;</button>
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
            btn.textContent = !isExpanded ? 'collapse all \u25B2' : 'expand all \u25BC';
        });
    })();
    </script>
</body>
</html>
