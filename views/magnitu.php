<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Magnitu – Seismo</title>
    <link rel="stylesheet" href="<?= getBasePath() ?>/assets/css/style.css">
</head>
<body>
    <div class="container">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="top-bar-left">
                <span class="top-bar-title">
                    <a href="<?= htmlspecialchars(seismo_nav_url_for_action('index')) ?>">
                        <svg class="logo-icon logo-icon-large" viewBox="0 0 24 16" xmlns="http://www.w3.org/2000/svg">
                            <rect width="24" height="16" fill="#FFFFC5"/>
                            <path d="M0,8 L4,12 L6,4 L10,10 L14,2 L18,8 L20,6 L24,8" stroke="#000000" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </a>
                    Magnitu
                </span>
                <span class="top-bar-subtitle">Relevance scoring</span>
            </div>
            <div class="top-bar-actions">
                <a href="?action=refresh_all&from=magnitu" class="top-bar-btn" title="Refresh all sources"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/></svg></a>
                <button type="button" class="top-bar-btn" id="menuToggle" title="Menu"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
            </div>
        </div>

        <!-- Navigation Drawer -->
        <nav class="nav-drawer" id="navDrawer">
            <a href="?action=index" class="nav-link">Feed</a>
            <a href="?action=magnitu" class="nav-link active">Magnitu</a>
            <a href="?action=feeds" class="nav-link">RSS</a>
            <a href="?action=calendar" class="nav-link">Calendar</a>
            <a href="?action=lex" class="nav-link">Lex</a>
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

        <?php if (!empty($magnituModelName)): ?>
        <div style="font-size: 12px; margin-bottom: 12px; color: #000000;">
            <strong><?= htmlspecialchars($magnituModelName) ?></strong><?php if (!empty($magnituModelVersion)): ?> <span style="font-size: 11px; font-weight: 600; padding: 1px 6px; border: 2px solid #000000; background: #FF6B6B;">v<?= htmlspecialchars($magnituModelVersion) ?></span><?php endif; ?>
        </div>
        <?php endif; ?>

        <p style="font-size: 12px; margin: 0 0 16px 0; color: #000000; max-width: 52rem;">Same list as the main Feed for your current URL (search and tag filters included), limited to the last 7 days with scores <code>investigation_lead</code> or <code>important</code>. Use the Feed link above to keep filters when switching pages.</p>

        <div class="latest-entries-section">
            <div class="section-title-row">
                <h2 class="section-title">Magnitu highlights<?= !empty($magnituFeedItems) ? ' <span style="font-weight: 400; font-size: 13px;">(' . count($magnituFeedItems) . ')</span>' : '' ?></h2>
                <button class="btn btn-secondary entry-expand-all-btn">expand all &#9660;</button>
            </div>
            <?php if (!empty($magnituFeedItems)): ?>
                <?php
                    $allItems = $magnituFeedItems;
                    $searchQuery = '';
                    $returnQuery = $_SERVER['QUERY_STRING'] ?? 'action=magnitu';
                    $showFavourites = false;
                    $showDaySeparators = true;
                    include __DIR__ . '/partials/dashboard_entry_loop.php';
                ?>
            <?php else: ?>
                <div class="empty-state">No entries in the current Feed view match <code>investigation_lead</code> or <code>important</code> within the last 7 days. Adjust filters on the Feed or train Magnitu and push scores to see results here.</div>
            <?php endif; ?>
        </div>

    </div>

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

        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.entry-expand-all-btn');
            if (!btn) return;
            var section = btn.closest('.latest-entries-section');
            if (!section) return;
            var isExpanded = btn.dataset.expanded === 'true';
            section.querySelectorAll('.entry-card').forEach(function(card) {
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
    <script>
    // Top bar toggle
    (function() {
        var menuBtn = document.getElementById('menuToggle');
        var navDrawer = document.getElementById('navDrawer');
        menuBtn.addEventListener('click', function() {
            var isOpen = navDrawer.classList.toggle('open');
            menuBtn.classList.toggle('active', isOpen);
        });
    })();
    </script>
</body>
</html>
