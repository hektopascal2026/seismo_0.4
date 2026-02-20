<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lex - Seismo</title>
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
                    Lex
                </span>
                <span class="top-bar-subtitle">EU, Swiss &amp; German legislation</span>
            </div>
            <div class="top-bar-actions">
                <a href="?action=refresh_all&from=lex" class="top-bar-btn" title="Refresh all sources"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/></svg></a>
                <button type="button" class="top-bar-btn" id="menuToggle" title="Menu"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
            </div>
        </div>

        <nav class="nav-drawer" id="navDrawer">
            <a href="?action=index" class="nav-link">Feed</a>
            <a href="?action=magnitu" class="nav-link">Magnitu</a>
            <a href="?action=feeds" class="nav-link">RSS</a>
            <a href="?action=calendar" class="nav-link">Calendar</a>
            <a href="?action=lex" class="nav-link active" style="background-color: #f5f562; color: #000000;">Lex</a>
            <a href="?action=jus" class="nav-link">Jus</a>
            <a href="?action=mail" class="nav-link">Mail</a>
            <a href="?action=substack" class="nav-link">Substack</a>
            <a href="?action=scraper" class="nav-link">Scraper</a>
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
        <form method="get" action="" id="lex-filter-form">
            <input type="hidden" name="action" value="lex">
            <input type="hidden" name="sources_submitted" value="1">
            <div class="tag-filter-section" style="margin-bottom: 16px;">
                <div class="tag-filter-list">
                    <?php
                        $lexPagePills = [
                            ['key' => 'eu', 'label' => '🇪🇺 EU'],
                            ['key' => 'ch', 'label' => '🇨🇭 Switzerland'],
                            ['key' => 'de', 'label' => '🇩🇪 Germany'],
                        ];
                        foreach ($lexPagePills as $pill):
                            if (!in_array($pill['key'], $enabledLexSources)) continue;
                            $isActive = in_array($pill['key'], $activeSources);
                    ?>
                    <label class="tag-filter-pill<?= $isActive ? ' tag-filter-pill-active' : '' ?>"<?= $isActive ? ' style="background-color: #f5f562;"' : '' ?>>
                        <input type="checkbox" name="sources[]" value="<?= $pill['key'] ?>" <?= $isActive ? 'checked' : '' ?> onchange="this.form.submit()">
                        <span><?= $pill['label'] ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </form>

        <div class="latest-entries-section">
            <div class="section-title-row">
                <h2 class="section-title">
                    <?php
                        $refreshParts = [];
                        if (!empty($lastLexRefreshDateEu)) $refreshParts[] = '🇪🇺 ' . $lastLexRefreshDateEu;
                        if (!empty($lastLexRefreshDateCh)) $refreshParts[] = '🇨🇭 ' . $lastLexRefreshDateCh;
                        if (!empty($lastLexRefreshDateDe)) $refreshParts[] = '🇩🇪 ' . $lastLexRefreshDateDe;
                        if (!empty($refreshParts)):
                    ?>
                        Refreshed: <?= implode(' · ', $refreshParts) ?>
                    <?php else: ?>
                        Refreshed: Never
                    <?php endif; ?>
                </h2>
            </div>

            <?php if (empty($lexItems)): ?>
                <div class="empty-state">
                    <p>No legislation fetched yet. Click <strong>Refresh EU</strong> or <strong>Refresh CH</strong> to query the legislation databases.</p>
                </div>
            <?php else: ?>
                <?php
                    // Check if multiple sources are active (merged view)
                    $activeCount = (int)in_array('eu', $activeSources) + (int)in_array('ch', $activeSources) + (int)in_array('de', $activeSources);
                    $showSourceTag = ($activeCount > 1);
                ?>
                <?php foreach ($lexItems as $item): ?>
                    <?php
                        $source = $item['source'] ?? 'eu';
                        if ($source === 'de') {
                            $sourceEmoji = '🇩🇪';
                            $sourceLabel = 'DE';
                            $linkLabel = 'recht.bund.de →';
                        } elseif ($source === 'ch') {
                            $sourceEmoji = '🇨🇭';
                            $sourceLabel = 'CH';
                            $linkLabel = 'Fedlex →';
                        } else {
                            $sourceEmoji = '🇪🇺';
                            $sourceLabel = 'EU';
                            $linkLabel = 'EUR-Lex →';
                        }
                        $docType = htmlspecialchars($item['document_type'] ?? 'Legislation');
                        $itemUrl = htmlspecialchars($item['eurlex_url'] ?? '#');
                    ?>
                    <div class="entry-card">
                        <div class="entry-header">
                            <?php if ($showSourceTag): ?>
                                <span class="entry-tag" style="background-color: #f5f562; border-color: #000000;">
                                    <?= $sourceEmoji ?> <?= $sourceLabel ?>
                                </span>
                            <?php endif; ?>
                            <span class="entry-tag" style="background-color: #f5f5f5;">
                                <?= $docType ?>
                            </span>
                        </div>
                        <h3 class="entry-title">
                            <a href="<?= $itemUrl ?>" target="_blank" rel="noopener">
                                <?= htmlspecialchars($item['title']) ?>
                            </a>
                        </h3>
                        <div class="entry-actions">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span style="font-family: monospace;"><?= htmlspecialchars($item['celex']) ?></span>
                                <a href="<?= $itemUrl ?>" target="_blank" rel="noopener" class="entry-link"><?= $linkLabel ?></a>
                            </div>
                            <?php if ($item['document_date']): ?>
                                <span class="entry-date"><?= date('d.m.Y', strtotime($item['document_date'])) ?></span>
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
