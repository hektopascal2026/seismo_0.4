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
                    Seismo
                </span>
                <span class="top-bar-subtitle">ein Prototyp von hektopascal.org | v0.3.2</span>
            </div>
            <div class="top-bar-actions">
                <a href="?action=refresh_all&from=index" class="top-bar-btn" title="Refresh all sources"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/></svg></a>
                <button type="button" class="top-bar-btn" id="searchToggle" title="Search"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="16.5" y1="16.5" x2="21" y2="21"/></svg></button>
                <button type="button" class="top-bar-btn" id="menuToggle" title="Menu"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
            </div>
        </div>

        <!-- Navigation Drawer -->
        <nav class="nav-drawer" id="navDrawer">
            <a href="?action=index" class="nav-link active">Feed</a>
            <a href="?action=magnitu" class="nav-link">Magnitu</a>
            <a href="?action=feeds" class="nav-link">RSS</a>
            <a href="?action=lex" class="nav-link">Lex</a>
            <a href="?action=mail" class="nav-link">Mail</a>
            <a href="?action=substack" class="nav-link">Substack</a>
            <a href="?action=settings" class="nav-link">Settings</a>
            <a href="?action=about" class="nav-link">About</a>
            <a href="?action=beta" class="nav-link">Beta</a>
        </nav>

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

                <?php if (!empty($tags) || !empty($emailTags) || !empty($substackTags) || !empty($selectedLexSources)): ?>
                    <div class="tag-filter-section">
                        <div class="tag-filter-list">
                            <a href="?action=index" class="tag-filter-pill" style="text-decoration: none;">
                                <span>Clear</span>
                            </a>
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
                                $lexEuSelected = !empty($selectedLexSources) && in_array('eu', $selectedLexSources, true);
                                $lexChSelected = !empty($selectedLexSources) && in_array('ch', $selectedLexSources, true);
                            ?>
                            <label class="tag-filter-pill<?= $lexEuSelected ? ' tag-filter-pill-active' : '' ?>"<?= $lexEuSelected ? ' style="background-color: #f5f562;"' : '' ?>>
                                <input type="checkbox" name="lex_sources[]" value="eu" <?= $lexEuSelected ? 'checked' : '' ?> onchange="this.form.submit()">
                                <span>🇪🇺 EU Lex</span>
                            </label>
                            <label class="tag-filter-pill<?= $lexChSelected ? ' tag-filter-pill-active' : '' ?>"<?= $lexChSelected ? ' style="background-color: #f5f562;"' : '' ?>>
                                <input type="checkbox" name="lex_sources[]" value="ch" <?= $lexChSelected ? 'checked' : '' ?> onchange="this.form.submit()">
                                <span>🇨🇭 CH Lex</span>
                            </label>
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
                    <button class="btn btn-secondary entry-expand-all-btn">&#9660; expand all</button>
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
                        <?php if (!empty($hasMagnituScores)): ?>
                            <?php if ($magnituSortByRelevance): ?>
                                <a href="?action=index&sort=date&tags_submitted=1<?= !empty($selectedTags) ? '&' . http_build_query(['tags' => $selectedTags]) : '' ?>" class="btn btn-secondary" style="font-size: 12px; padding: 4px 10px; background:#FF6B6B;" title="Currently sorted by relevance. Click for chronological.">Sort: Relevance</a>
                            <?php else: ?>
                                <a href="?action=index&sort=relevance&tags_submitted=1<?= !empty($selectedTags) ? '&' . http_build_query(['tags' => $selectedTags]) : '' ?>" class="btn btn-secondary" style="font-size: 12px; padding: 4px 10px;" title="Currently chronological. Click to sort by relevance.">Sort: Date</a>
                            <?php endif; ?>
                        <?php endif; ?>
                        <button class="btn btn-secondary entry-expand-all-btn">&#9660; expand all</button>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($allItems)): ?>
                <?php foreach ($allItems as $itemWrapper): ?>
                    <?php
                        // Magnitu score data for this entry (badge only, no explanation on index)
                        $entryScore = $itemWrapper['score'] ?? null;
                        $relevanceScore = $entryScore ? (float)$entryScore['relevance_score'] : null;
                        $predictedLabel = $entryScore['predicted_label'] ?? null;
                        $scoreBadgeClass = '';
                        if ($predictedLabel === 'investigation_lead') $scoreBadgeClass = 'magnitu-badge-investigation';
                        elseif ($predictedLabel === 'important') $scoreBadgeClass = 'magnitu-badge-important';
                        elseif ($predictedLabel === 'background') $scoreBadgeClass = 'magnitu-badge-background';
                        elseif ($predictedLabel === 'noise') $scoreBadgeClass = 'magnitu-badge-noise';
                    ?>
                    <?php if ($itemWrapper['type'] === 'feed' || $itemWrapper['type'] === 'substack'): ?>
                        <?php $item = $itemWrapper['data']; ?>
                        <?php
                            $fullContent = strip_tags($item['content'] ?: $item['description']);
                            $contentPreview = mb_substr($fullContent, 0, 200);
                            if (mb_strlen($fullContent) > 200) $contentPreview .= '...';
                            $hasMore = mb_strlen($fullContent) > 200;
                            $feedTagColor = ($itemWrapper['type'] === 'substack') ? 'background-color: #C5B4D1;' : 'background-color: #add8e6;';
                        ?>
                        <div class="entry-card">
                            <div class="entry-header">
                                <?php if (!empty($item['feed_category']) && $item['feed_category'] !== 'unsortiert'): ?>
                                    <span class="entry-tag" style="<?= $feedTagColor ?>"><?= htmlspecialchars($item['feed_category']) ?></span>
                                <?php endif; ?>
                                <?php if ($relevanceScore !== null): ?>
                                    <span class="magnitu-badge <?= $scoreBadgeClass ?>" title="<?= htmlspecialchars($predictedLabel ?? '') ?> (<?= round($relevanceScore * 100) ?>%)"><?= round($relevanceScore * 100) ?></span>
                                <?php endif; ?>
                            </div>
                            <h3 class="entry-title">
                                <a href="<?= htmlspecialchars($item['link']) ?>" target="_blank" rel="noopener">
                                    <?php if (!empty($searchQuery)): ?>
                                        <?= highlightSearchTerm($item['title'], $searchQuery) ?>
                                    <?php else: ?>
                                        <?= htmlspecialchars($item['title']) ?>
                                    <?php endif; ?>
                                </a>
                            </h3>
                            <?php if ($item['description'] || $item['content']): ?>
                                <div class="entry-content entry-preview">
                                    <?php 
                                        if (!empty($searchQuery)) {
                                            echo highlightSearchTerm($contentPreview, $searchQuery);
                                        } else {
                                            echo htmlspecialchars($contentPreview);
                                        }
                                    ?>
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
                    <?php elseif ($itemWrapper['type'] === 'lex'): ?>
                        <?php $lexItem = $itemWrapper['data']; ?>
                        <?php
                            $lexSource = $lexItem['source'] ?? 'eu';
                            $lexIsEu = ($lexSource === 'eu');
                            $lexSourceEmoji = $lexIsEu ? '🇪🇺' : '🇨🇭';
                            $lexSourceLabel = $lexIsEu ? 'EU' : 'CH';
                            $lexDocType = $lexItem['document_type'] ?? 'Legislation';
                            $lexUrl = $lexItem['eurlex_url'] ?? '#';
                            $lexDate = $lexItem['document_date'] ? date('d.m.Y', strtotime($lexItem['document_date'])) : '';
                        ?>
                        <div class="entry-card">
                            <div class="entry-header">
                                <span class="entry-tag" style="background-color: #f5f562; border-color: #000000;"><?= $lexSourceEmoji ?> <?= $lexSourceLabel ?></span>
                                <span class="entry-tag" style="background-color: #f5f5f5;"><?= htmlspecialchars($lexDocType) ?></span>
                                <?php if ($relevanceScore !== null): ?>
                                    <span class="magnitu-badge <?= $scoreBadgeClass ?>" title="<?= htmlspecialchars($predictedLabel ?? '') ?> (<?= round($relevanceScore * 100) ?>%)"><?= round($relevanceScore * 100) ?></span>
                                <?php endif; ?>
                            </div>
                            <h3 class="entry-title">
                                <a href="<?= htmlspecialchars($lexUrl) ?>" target="_blank" rel="noopener">
                                    <?php if (!empty($searchQuery)): ?>
                                        <?= highlightSearchTerm($lexItem['title'], $searchQuery) ?>
                                    <?php else: ?>
                                        <?= htmlspecialchars($lexItem['title']) ?>
                                    <?php endif; ?>
                                </a>
                            </h3>
                            <div class="entry-actions">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span style="font-family: monospace;"><?= htmlspecialchars($lexItem['celex'] ?? '') ?></span>
                                    <a href="<?= htmlspecialchars($lexUrl) ?>" target="_blank" rel="noopener" class="entry-link"><?= $lexIsEu ? 'EUR-Lex →' : 'Fedlex →' ?></a>
                                </div>
                                <?php if ($lexDate): ?>
                                    <span class="entry-date"><?= $lexDate ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php $email = $itemWrapper['data']; ?>
                        <?php
                            $dateValue = $email['date_received'] ?? $email['date_utc'] ?? $email['created_at'] ?? $email['date_sent'] ?? null;
                            $createdAt = $dateValue ? date('d.m.Y H:i', strtotime($dateValue)) : '';
                            
                            $fromName = trim((string)($email['from_name'] ?? ''));
                            $fromEmail = trim((string)($email['from_email'] ?? ''));
                            $fromDisplay = $fromName !== '' ? $fromName : ($fromEmail !== '' ? $fromEmail : 'Unknown sender');

                            $subject = trim((string)($email['subject'] ?? ''));
                            if ($subject === '') $subject = '(No subject)';

                            $body = (string)($email['text_body'] ?? '');
                            if ($body === '') {
                                $body = strip_tags((string)($email['html_body'] ?? ''));
                            }
                            $body = trim(preg_replace('/\s+/', ' ', $body ?? ''));
                            $bodyPreview = mb_substr($body, 0, 200);
                            if (mb_strlen($body) > 200) $bodyPreview .= '...';
                            $hasMore = mb_strlen($body) > 200;
                        ?>
                        <div class="entry-card">
                            <div class="entry-header">
                                <?php if (!empty($email['sender_tag']) && $email['sender_tag'] !== 'unclassified'): ?>
                                    <span class="entry-tag" style="background-color: #FFDBBB;"><?= htmlspecialchars($email['sender_tag']) ?></span>
                                <?php endif; ?>
                                <?php if ($relevanceScore !== null): ?>
                                    <span class="magnitu-badge <?= $scoreBadgeClass ?>" title="<?= htmlspecialchars($predictedLabel ?? '') ?> (<?= round($relevanceScore * 100) ?>%)"><?= round($relevanceScore * 100) ?></span>
                                <?php endif; ?>
                            </div>
                            <h3 class="entry-title">
                                <?php if (!empty($searchQuery)): ?>
                                    <?= highlightSearchTerm($subject, $searchQuery) ?>
                                <?php else: ?>
                                    <?= htmlspecialchars($subject) ?>
                                <?php endif; ?>
                            </h3>
                            <div class="entry-content entry-preview">
                                <?php 
                                    if (!empty($searchQuery)) {
                                        echo highlightSearchTerm($bodyPreview, $searchQuery);
                                    } else {
                                        echo htmlspecialchars($bodyPreview);
                                    }
                                ?>
                            </div>
                            <div class="entry-full-content" style="display:none"><?= htmlspecialchars($body) ?></div>
                            <div class="entry-actions">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <?php if ($hasMore): ?>
                                        <button class="btn btn-secondary entry-expand-btn">&#9660; expand</button>
                                    <?php endif; ?>
                                </div>
                                <?php if ($createdAt): ?>
                                    <span class="entry-date"><?= htmlspecialchars($createdAt) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
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
    <a href="?action=refresh_all&from=index" class="floating-refresh-btn" title="Refresh all sources">Refresh</a>

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
</body>
</html>
