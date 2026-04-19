<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seismo</title>
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
                    <?= htmlspecialchars(seismoBrandTitle()) ?>
                </span>
                <?php if (!isSatellite()): ?>
                <span class="top-bar-subtitle">ein Prototyp von hektopascal.org | v0.4</span>
                <?php endif; ?>
            </div>
            <div class="top-bar-actions">
                <?php $refreshFrom = 'index'; $refreshStyle = 'icon'; include __DIR__ . '/partials/refresh_btn.php'; ?>
                <button type="button" class="top-bar-btn" id="searchToggle" title="Search"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="16.5" y1="16.5" x2="21" y2="21"/></svg></button>
                <button type="button" class="top-bar-btn" id="menuToggle" title="Menu"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
            </div>
        </div>

        <!-- Navigation Drawer -->
        <?php $navActive = 'index'; include __DIR__ . '/partials/nav.php'; ?>

        <!-- Search Drawer -->
        <div class="search-drawer" id="searchDrawer">
            <form method="GET" class="search-form">
                <input type="hidden" name="action" value="index">
                <input type="hidden" name="tags_submitted" value="1">
                <input type="search" name="q" placeholder="Search entries..." class="search-input" value="<?= htmlspecialchars($searchQuery ?? '') ?>" style="min-width: 0;">
                <button type="submit" class="btn btn-primary">Search</button>
                <?php if (!empty($searchQuery) || !empty($selectedTags) || !empty($selectedEmailTags)): ?>
                    <a href="?action=index" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="message message-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="message message-error"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Tag Filters -->
        <div class="search-section">
            <form method="GET">
                <input type="hidden" name="action" value="index">
                <input type="hidden" name="tags_submitted" value="1">
                <?php if (!empty($searchQuery)): ?>
                    <input type="hidden" name="q" value="<?= htmlspecialchars($searchQuery) ?>">
                <?php endif; ?>

                <?php
                    $lexPills = [
                        ['key' => 'eu',      'emoji' => '🇪🇺', 'label' => 'EU Lex'],
                        ['key' => 'ch',      'emoji' => '🇨🇭', 'label' => 'CH Lex'],
                        ['key' => 'de',      'emoji' => '🇩🇪', 'label' => 'DE Lex'],
                        ['key' => 'fr',      'emoji' => '🇫🇷', 'label' => 'FR Lex'],
                        ['key' => 'ch_bger', 'emoji' => '⚖️', 'label' => 'BGer'],
                        ['key' => 'ch_bge',  'emoji' => '⚖️', 'label' => 'BGE'],
                        ['key' => 'ch_bvger','emoji' => '⚖️', 'label' => 'BVGer'],
                        ['key' => 'parl_mm', 'emoji' => '🏛', 'label' => 'Parl MM'],
                    ];
                ?>
                <?php if (!empty($tags) || !empty($emailTags) || !empty($substackTags) || !empty($selectedLexSources)): ?>
                    <?php
                        $totalPills = count($tags) + count($emailTags) + count($substackTags ?? []);
                        foreach ($lexPills as $_lp) { if (in_array($_lp['key'], $enabledLexSources ?? [])) $totalPills++; }
                        $totalPills += count($scraperFeedsForIndex ?? []);
                        if (!empty($calendarEnabled)) $totalPills++;
                        $checkedPills = count($selectedTags ?? []) + count($selectedEmailTags ?? []) + count($selectedSubstackTags ?? []) + count($selectedLexSources ?? []) + count($selectedScraperPills ?? []) + (!empty($selectedCalendar) ? 1 : 0);
                        $allSelected = ($checkedPills >= $totalPills && $totalPills > 0);
                    ?>
                    <div class="tag-filter-section">
                        <div class="tag-filter-list">
                            <button type="button" class="tag-filter-pill" style="cursor: pointer;" id="toggleAllPills">
                                <span><?= $allSelected ? 'None' : 'All' ?></span>
                            </button>
                            <?php foreach ($tags as $tag): ?>
                                <?php $isSelected = !empty($selectedTags) && in_array($tag, $selectedTags, true); ?>
                                <label class="tag-filter-pill<?= $isSelected ? ' tag-filter-pill-active' : '' ?>"<?= $isSelected ? ' style="background-color: #add8e6;"' : '' ?>>
                                    <input type="checkbox" name="tags[]" value="<?= htmlspecialchars($tag) ?>" <?= $isSelected ? 'checked' : '' ?> onchange="this.form.submit()">
                                    <span><?= htmlspecialchars($tag) ?></span>
                                </label>
                            <?php endforeach; ?>
                            <?php foreach ($emailTags as $tag): ?>
                                <?php $isSelected = !empty($selectedEmailTags) && in_array($tag, $selectedEmailTags, true); ?>
                                <label class="tag-filter-pill<?= $isSelected ? ' tag-filter-pill-active' : '' ?>"<?= $isSelected ? ' style="background-color: #FFDBBB;"' : '' ?>>
                                    <input type="checkbox" name="email_tags[]" value="<?= htmlspecialchars($tag) ?>" <?= $isSelected ? 'checked' : '' ?> onchange="this.form.submit()">
                                    <span><?= htmlspecialchars($tag) ?></span>
                                </label>
                            <?php endforeach; ?>
                            <?php foreach ($substackTags as $tag): ?>
                                <?php $isSelected = !empty($selectedSubstackTags) && in_array($tag, $selectedSubstackTags, true); ?>
                                <label class="tag-filter-pill<?= $isSelected ? ' tag-filter-pill-active' : '' ?>"<?= $isSelected ? ' style="background-color: #C5B4D1;"' : '' ?>>
                                    <input type="checkbox" name="substack_tags[]" value="<?= htmlspecialchars($tag) ?>" <?= $isSelected ? 'checked' : '' ?> onchange="this.form.submit()">
                                    <span><?= htmlspecialchars($tag) ?></span>
                                </label>
                            <?php endforeach; ?>
                            <?php
                                foreach ($lexPills as $pill):
                                    if (!in_array($pill['key'], $enabledLexSources)) continue;
                                    $isSelected = !empty($selectedLexSources) && in_array($pill['key'], $selectedLexSources, true);
                            ?>
                            <label class="tag-filter-pill<?= $isSelected ? ' tag-filter-pill-active' : '' ?>"<?= $isSelected ? ' style="background-color: #f5f562;"' : '' ?>>
                                <input type="checkbox" name="lex_sources[]" value="<?= $pill['key'] ?>" <?= $isSelected ? 'checked' : '' ?> onchange="this.form.submit()">
                                <span><?= $pill['emoji'] ?> <?= $pill['label'] ?></span>
                            </label>
                            <?php endforeach; ?>
                            <?php if (!empty($scraperFeedsForIndex)): ?>
                            <?php foreach ($scraperFeedsForIndex as $scPill):
                                $scSelected = in_array($scPill['id'], $selectedScraperPills ?? []);
                            ?>
                            <label class="tag-filter-pill<?= $scSelected ? ' tag-filter-pill-active' : '' ?>"<?= $scSelected ? ' style="background-color: #FFDBBB;"' : '' ?>>
                                <input type="checkbox" name="scraper_sources[]" value="<?= $scPill['id'] ?>" <?= $scSelected ? 'checked' : '' ?> onchange="this.form.submit()">
                                <span>🌐 <?= htmlspecialchars($scPill['name']) ?></span>
                            </label>
                            <?php endforeach; ?>
                            <?php endif; ?>
                            <?php if (!empty($calendarEnabled)): ?>
                            <label class="tag-filter-pill<?= !empty($selectedCalendar) ? ' tag-filter-pill-active' : '' ?>"<?= !empty($selectedCalendar) ? ' style="background-color: #d4edda;"' : '' ?>>
                                <input type="checkbox" name="calendar_enabled" value="1" <?= !empty($selectedCalendar) ? 'checked' : '' ?> onchange="this.form.submit()">
                                <span>Leg</span>
                            </label>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- Latest Entries from All Feeds / Search Results -->
        <div class="latest-entries-section">
            <?php if (!empty($searchQuery)): ?>
                <div class="section-title-row">
                    <h2 class="section-title">
                        Search Results<?= $searchResultsCount !== null ? ' (' . $searchResultsCount . ')' : '' ?>
                        <span style="font-weight: 400;">for "<?= htmlspecialchars($searchQuery) ?>"</span>
                    </h2>
                    <button class="btn btn-secondary entry-expand-all-btn">expand all &#9660;</button>
                </div>
            <?php else: ?>
                <div class="section-title-row">
                    <h2 class="section-title">
                        <?php if ($lastRefreshDate): ?>
                            Refreshed: <?= htmlspecialchars($lastRefreshDate) ?>
                        <?php else: ?>
                            Refreshed: Never
                        <?php endif; ?>
                        <?php if (!empty($hasMagnituScores)): ?>
                            <span class="magnitu-coverage">&middot; <?= $scoredCount ?> of <?= count($allItems) ?> scored</span>
                        <?php endif; ?>
                    </h2>
                    <div style="display: flex; gap: 6px; align-items: center;">
                        <button onclick="toggleView('newest')" class="btn btn-secondary<?= $currentView === 'newest' ? ' active' : '' ?>" title="Show newest entries">Newest</button>
                        <button onclick="toggleView('favourites')" class="btn btn-secondary<?= $currentView === 'favourites' ? ' active' : '' ?>" title="Show favourite entries only">Favourites</button>
                        <button class="btn btn-secondary entry-expand-all-btn">expand all &#9660;</button>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($allItems)): ?>
                <?php
                    $returnQuery = $_SERVER['QUERY_STRING'] ?? 'action=index';
                    $showFavourites = true;
                    include __DIR__ . '/partials/dashboard_entry_loop.php';
                ?>
            <?php else: ?>
                <div class="empty-state">
                    <?php if (!empty($searchQuery)): ?>
                        <p>No results found for "<?= htmlspecialchars($searchQuery) ?>". Try a different search term.</p>
                    <?php else: ?>
                        <p>No entries available yet. Add feeds to see entries here.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Floating Refresh Button -->
    <?php $refreshFrom = 'index'; $refreshStyle = 'floating'; include __DIR__ . '/partials/refresh_btn.php'; ?>

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
    <script>
    // Top bar toggles
    (function() {
        var menuBtn = document.getElementById('menuToggle');
        var navDrawer = document.getElementById('navDrawer');
        var searchBtn = document.getElementById('searchToggle');
        var searchDrawer = document.getElementById('searchDrawer');

        menuBtn.addEventListener('click', function() {
            var isOpen = navDrawer.classList.toggle('open');
            menuBtn.classList.toggle('active', isOpen);
            if (isOpen) { searchDrawer.classList.remove('open'); searchBtn.classList.remove('active'); }
        });
        searchBtn.addEventListener('click', function() {
            var isOpen = searchDrawer.classList.toggle('open');
            searchBtn.classList.toggle('active', isOpen);
            if (isOpen) {
                navDrawer.classList.remove('open'); menuBtn.classList.remove('active');
                searchDrawer.querySelector('input[type="search"]').focus();
            }
        });
    })();
    </script>
    <script>
    // All/None toggle for filter pills
    (function() {
        var btn = document.getElementById('toggleAllPills');
        if (!btn) return;
        var form = btn.closest('form');
        if (!form) return;
        btn.addEventListener('click', function() {
            var checkboxes = form.querySelectorAll('.tag-filter-list input[type="checkbox"]');
            var allChecked = Array.prototype.every.call(checkboxes, function(cb) { return cb.checked; });
            checkboxes.forEach(function(cb) { cb.checked = !allChecked; });
            form.submit();
        });
    })();
    </script>
    <script>
    function toggleView(viewValue) {
        var url = new URL(window.location);
        url.searchParams.set('action', 'index');
        url.searchParams.set('view', viewValue);
        window.location = url.toString();
    }
    </script>
</body>
</html>
