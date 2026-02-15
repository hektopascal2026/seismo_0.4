<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scraper - Seismo</title>
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
                    Scraper
                </span>
                <span class="top-bar-subtitle">Web pages</span>
            </div>
            <div class="top-bar-actions">
                <button type="button" class="top-bar-btn" id="menuToggle" title="Menu"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
            </div>
        </div>

        <nav class="nav-drawer" id="navDrawer">
            <a href="?action=index" class="nav-link">Feed</a>
            <a href="?action=magnitu" class="nav-link">Magnitu</a>
            <a href="?action=feeds" class="nav-link">RSS</a>
            <a href="?action=lex" class="nav-link">Lex</a>
            <a href="?action=jus" class="nav-link">Jus</a>
            <a href="?action=mail" class="nav-link">Mail</a>
            <a href="?action=substack" class="nav-link">Substack</a>
            <a href="?action=scraper" class="nav-link active" style="background-color: #FFDBBB; color: #000000;">Scraper</a>
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

        <!-- Source Filter Tags -->
        <?php if (!empty($scraperSources)): ?>
        <form method="get" action="" id="scraper-filter-form">
            <input type="hidden" name="action" value="scraper">
            <input type="hidden" name="sources_submitted" value="1">
            <div class="tag-filter-section" style="margin-bottom: 16px;">
                <div class="tag-filter-list">
                    <?php foreach ($scraperSources as $src): ?>
                    <?php $isActive = in_array($src['id'], $activeScraperIds); ?>
                    <label class="tag-filter-pill<?= $isActive ? ' tag-filter-pill-active' : '' ?>"<?= $isActive ? ' style="background-color: #FFDBBB;"' : '' ?>>
                        <input type="checkbox" name="sources[]" value="<?= $src['id'] ?>" <?= $isActive ? 'checked' : '' ?> onchange="this.form.submit()">
                        <span>🌐 <?= htmlspecialchars($src['name']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </form>
        <?php endif; ?>

        <div class="latest-entries-section">
            <div class="section-title-row">
                <h2 class="section-title">Scraped entries</h2>
            </div>

            <?php if (empty($scraperItems)): ?>
                <div class="empty-state">
                    <p>No scraped entries yet. Configure URLs in <a href="?action=settings&tab=script">Settings &rarr; Script</a>, download the scraper script, and set up a cronjob on your hoster.</p>
                </div>
            <?php else: ?>
                <?php
                    $showSourceTag = count($activeScraperIds) > 1;
                ?>
                <?php foreach ($scraperItems as $item): ?>
                    <?php
                        $entryScore = $scraperScoreMap[$item['id']] ?? null;
                        $relevanceScore = $entryScore ? (float)$entryScore['relevance_score'] : null;
                        $predictedLabel = $entryScore['predicted_label'] ?? null;
                        $scoreBadgeClass = '';
                        if ($predictedLabel === 'investigation_lead') $scoreBadgeClass = 'magnitu-badge-investigation';
                        elseif ($predictedLabel === 'important') $scoreBadgeClass = 'magnitu-badge-important';
                        elseif ($predictedLabel === 'background') $scoreBadgeClass = 'magnitu-badge-background';
                        elseif ($predictedLabel === 'noise') $scoreBadgeClass = 'magnitu-badge-noise';
                    ?>
                    <div class="entry-card">
                        <div class="entry-header">
                            <?php if ($showSourceTag): ?>
                                <span class="entry-tag" style="background-color: #FFDBBB; border-color: #000000;">
                                    🌐 <?= htmlspecialchars($item['feed_name']) ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($relevanceScore !== null): ?>
                                <span class="magnitu-badge <?= $scoreBadgeClass ?>" title="<?= htmlspecialchars($predictedLabel ?? '') ?> (<?= round($relevanceScore * 100) ?>%)"><?= round($relevanceScore * 100) ?></span>
                            <?php endif; ?>
                        </div>
                        <h3 class="entry-title">
                            <a href="<?= htmlspecialchars($item['link'] ?? '#') ?>" target="_blank" rel="noopener">
                                <?= htmlspecialchars($item['title']) ?>
                            </a>
                        </h3>
                        <?php if (!empty($item['content'])): ?>
                            <p class="entry-description"><?= htmlspecialchars(mb_strimwidth(strip_tags($item['content']), 0, 300, '...')) ?></p>
                        <?php endif; ?>
                        <div class="entry-actions">
                            <a href="<?= htmlspecialchars($item['link'] ?? '#') ?>" target="_blank" rel="noopener" class="entry-link">Open page &rarr;</a>
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
</body>
</html>
