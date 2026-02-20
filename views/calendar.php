<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar - Seismo</title>
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
                    Calendar
                </span>
                <span class="top-bar-subtitle">Upcoming events &amp; parliament</span>
            </div>
            <div class="top-bar-actions">
                <a href="?action=refresh_calendar" class="top-bar-btn" title="Refresh calendar events"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/></svg></a>
                <button type="button" class="top-bar-btn" id="menuToggle" title="Menu"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
            </div>
        </div>

        <nav class="nav-drawer" id="navDrawer">
            <a href="?action=index" class="nav-link">Feed</a>
            <a href="?action=magnitu" class="nav-link">Magnitu</a>
            <a href="?action=feeds" class="nav-link">RSS</a>
            <a href="?action=calendar" class="nav-link active" style="background-color: #d4edda; color: #000000;">Calendar</a>
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

        <!-- Source + Type Filters -->
        <form method="get" action="" id="calendar-filter-form">
            <input type="hidden" name="action" value="calendar">
            <input type="hidden" name="sources_submitted" value="1">
            <?php if ($showPast): ?>
                <input type="hidden" name="show_past" value="1">
            <?php endif; ?>
            <div class="tag-filter-section" style="margin-bottom: 16px;">
                <div class="tag-filter-list">
                    <?php
                        $calendarPills = [
                            ['key' => 'parliament_ch', 'label' => '🇨🇭 Parlament CH'],
                        ];
                        foreach ($calendarPills as $pill):
                            if (!in_array($pill['key'], $enabledSources)) continue;
                            $isActive = in_array($pill['key'], $activeSources);
                    ?>
                    <label class="tag-filter-pill<?= $isActive ? ' tag-filter-pill-active' : '' ?>"<?= $isActive ? ' style="background-color: #d4edda;"' : '' ?>>
                        <input type="checkbox" name="sources[]" value="<?= $pill['key'] ?>" <?= $isActive ? 'checked' : '' ?> onchange="this.form.submit()">
                        <span><?= $pill['label'] ?></span>
                    </label>
                    <?php endforeach; ?>

                    <?php if (!empty($eventTypes)): ?>
                        <?php foreach ($eventTypes as $et):
                            $etSelected = ($eventType === $et);
                        ?>
                        <label class="tag-filter-pill<?= $etSelected ? ' tag-filter-pill-active' : '' ?>"<?= $etSelected ? ' style="background-color: #e2e3f1;"' : '' ?>>
                            <input type="radio" name="event_type" value="<?= htmlspecialchars($et) ?>" <?= $etSelected ? 'checked' : '' ?> onchange="this.form.submit()">
                            <span><?= htmlspecialchars(getCalendarEventTypeLabel($et)) ?></span>
                        </label>
                        <?php endforeach; ?>
                        <?php if ($eventType !== ''): ?>
                        <label class="tag-filter-pill">
                            <input type="radio" name="event_type" value="" onchange="this.form.submit()">
                            <span>All types</span>
                        </label>
                        <?php endif; ?>
                    <?php endif; ?>

                    <label class="tag-filter-pill<?= $showPast ? ' tag-filter-pill-active' : '' ?>">
                        <input type="checkbox" name="show_past" value="1" <?= $showPast ? 'checked' : '' ?> onchange="this.form.submit()">
                        <span>Show past</span>
                    </label>
                </div>
            </div>
        </form>

        <div class="latest-entries-section">
            <div class="section-title-row">
                <h2 class="section-title">
                    <?php if ($lastCalendarRefreshDate): ?>
                        Refreshed: <?= htmlspecialchars($lastCalendarRefreshDate) ?>
                    <?php else: ?>
                        Refreshed: Never
                    <?php endif; ?>
                    <?php if (!empty($scoreMap)): ?>
                        <span class="magnitu-coverage">&middot; <?= count($scoreMap) ?> scored</span>
                    <?php endif; ?>
                </h2>
                <button class="btn btn-secondary entry-expand-all-btn">expand all &#9660;</button>
            </div>

            <?php if (empty($calendarEvents)): ?>
                <div class="empty-state">
                    <p>No calendar events yet. Click the refresh button to fetch upcoming events from the Swiss Parliament.</p>
                </div>
            <?php else: ?>
                <?php
                    $today = date('Y-m-d');
                    $currentGroup = null;
                ?>
                <?php foreach ($calendarEvents as $event): ?>
                    <?php
                        $eventDate = $event['event_date'] ?? null;
                        $eventEndDate = $event['event_end_date'] ?? null;
                        $daysUntil = $eventDate ? (int)((strtotime($eventDate) - strtotime($today)) / 86400) : null;

                        // Group header by date
                        $groupKey = $eventDate ?: 'undated';
                        if ($groupKey !== $currentGroup):
                            $currentGroup = $groupKey;
                            if ($eventDate) {
                                $dateObj = new DateTime($eventDate);
                                $groupLabel = $dateObj->format('l, d. F Y');
                                if ($daysUntil === 0) $groupLabel .= ' (today)';
                                elseif ($daysUntil === 1) $groupLabel .= ' (tomorrow)';
                                elseif ($daysUntil > 1 && $daysUntil <= 7) $groupLabel .= " (in {$daysUntil} days)";
                                elseif ($daysUntil < 0) $groupLabel .= ' (' . abs($daysUntil) . ' days ago)';
                            } else {
                                $groupLabel = 'Date unknown';
                            }
                    ?>
                    <div style="margin: 20px 0 8px; padding: 4px 0; border-bottom: 2px solid #000; font-weight: 700; font-size: 0.95em;">
                        <?= htmlspecialchars($groupLabel) ?>
                    </div>
                    <?php endif; ?>

                    <?php
                        $entryScore = $scoreMap[$event['id']] ?? null;
                        $relevanceScore = $entryScore ? (float)$entryScore['relevance_score'] : null;
                        $predictedLabel = $entryScore['predicted_label'] ?? null;
                        $scoreBadgeClass = '';
                        if ($predictedLabel === 'investigation_lead') $scoreBadgeClass = 'magnitu-badge-investigation';
                        elseif ($predictedLabel === 'important') $scoreBadgeClass = 'magnitu-badge-important';
                        elseif ($predictedLabel === 'background') $scoreBadgeClass = 'magnitu-badge-background';
                        elseif ($predictedLabel === 'noise') $scoreBadgeClass = 'magnitu-badge-noise';

                        $typeLabel = getCalendarEventTypeLabel($event['event_type'] ?? '');
                        $councilLabel = getCouncilLabel($event['council'] ?? '');
                        $statusLabel = ucfirst($event['status'] ?? 'scheduled');

                        $description = strip_tags($event['description'] ?? '');
                        $contentPreview = mb_substr($description, 0, 300);
                        if (mb_strlen($description) > 300) $contentPreview .= '...';
                        $hasMore = mb_strlen($description) > 300;

                        $metadata = $event['metadata'] ? json_decode($event['metadata'], true) : [];
                        $businessNumber = $metadata['business_number'] ?? '';
                        $author = $metadata['author'] ?? '';
                    ?>
                    <div class="entry-card">
                        <div class="entry-header">
                            <span class="entry-tag" style="background-color: #d4edda;">
                                <?= htmlspecialchars($typeLabel) ?>
                            </span>
                            <?php if ($councilLabel): ?>
                                <span class="entry-tag" style="background-color: #e2e3f1;">
                                    <?= htmlspecialchars($councilLabel) ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($event['status'] !== 'scheduled'): ?>
                                <span class="entry-tag" style="background-color: <?= $event['status'] === 'completed' ? '#f5f5f5' : ($event['status'] === 'cancelled' ? '#ffcccc' : '#fff3cd') ?>;">
                                    <?= htmlspecialchars($statusLabel) ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($relevanceScore !== null): ?>
                                <span class="magnitu-badge <?= $scoreBadgeClass ?>" title="<?= htmlspecialchars($predictedLabel ?? '') ?> (<?= round($relevanceScore * 100) ?>%)"><?= round($relevanceScore * 100) ?></span>
                            <?php endif; ?>
                        </div>
                        <h3 class="entry-title">
                            <?php if (!empty($event['url'])): ?>
                                <a href="<?= htmlspecialchars($event['url']) ?>" target="_blank" rel="noopener">
                                    <?= htmlspecialchars($event['title']) ?>
                                </a>
                            <?php else: ?>
                                <?= htmlspecialchars($event['title']) ?>
                            <?php endif; ?>
                        </h3>
                        <?php if (!empty($contentPreview)): ?>
                            <div class="entry-content">
                                <div class="entry-preview"><?= nl2br(htmlspecialchars($contentPreview)) ?></div>
                                <?php if ($hasMore): ?>
                                    <div class="entry-full" style="display: none;"><?= nl2br(htmlspecialchars($description)) ?></div>
                                    <button class="entry-expand-btn">more &#9660;</button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <div class="entry-actions">
                            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                <?php if ($businessNumber): ?>
                                    <span style="font-family: monospace; font-size: 0.85em;"><?= htmlspecialchars($businessNumber) ?></span>
                                <?php endif; ?>
                                <?php if ($author): ?>
                                    <span style="font-size: 0.85em; color: #666;"><?= htmlspecialchars($author) ?></span>
                                <?php endif; ?>
                                <span class="entry-tag" style="font-size: 0.8em; background-color: #f0f0f0;">
                                    <?= htmlspecialchars(getCalendarSourceLabel($event['source'])) ?>
                                </span>
                                <?php if (!empty($event['url'])): ?>
                                    <a href="<?= htmlspecialchars($event['url']) ?>" target="_blank" rel="noopener" class="entry-link">parlament.ch &rarr;</a>
                                <?php endif; ?>
                            </div>
                            <?php if ($eventDate): ?>
                                <span class="entry-date">
                                    <?= date('d.m.Y', strtotime($eventDate)) ?>
                                    <?php if ($eventEndDate && $eventEndDate !== $eventDate): ?>
                                        &ndash; <?= date('d.m.Y', strtotime($eventEndDate)) ?>
                                    <?php endif; ?>
                                </span>
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

        // Expand/collapse individual entries
        document.querySelectorAll('.entry-expand-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var card = btn.closest('.entry-content');
                var preview = card.querySelector('.entry-preview');
                var full = card.querySelector('.entry-full');
                if (full.style.display === 'none') {
                    preview.style.display = 'none';
                    full.style.display = 'block';
                    btn.innerHTML = 'less &#9650;';
                } else {
                    preview.style.display = 'block';
                    full.style.display = 'none';
                    btn.innerHTML = 'more &#9660;';
                }
            });
        });

        // Expand all
        var expandAllBtn = document.querySelector('.entry-expand-all-btn');
        if (expandAllBtn) {
            var allExpanded = false;
            expandAllBtn.addEventListener('click', function() {
                allExpanded = !allExpanded;
                document.querySelectorAll('.entry-content').forEach(function(card) {
                    var preview = card.querySelector('.entry-preview');
                    var full = card.querySelector('.entry-full');
                    var btn = card.querySelector('.entry-expand-btn');
                    if (!full) return;
                    if (allExpanded) {
                        preview.style.display = 'none';
                        full.style.display = 'block';
                        if (btn) btn.innerHTML = 'less &#9650;';
                    } else {
                        preview.style.display = 'block';
                        full.style.display = 'none';
                        if (btn) btn.innerHTML = 'more &#9660;';
                    }
                });
                expandAllBtn.innerHTML = allExpanded ? 'collapse all &#9650;' : 'expand all &#9660;';
            });
        }
    })();
    </script>
</body>
</html>
