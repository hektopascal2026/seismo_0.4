<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mail - Seismo</title>
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
                    Mail
                </span>
                <span class="top-bar-subtitle">Mail management</span>
            </div>
            <div class="top-bar-actions">
                <a href="?action=refresh_emails&from=mail" class="top-bar-btn" title="Refresh emails"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/></svg></a>
                <button type="button" class="top-bar-btn" id="menuToggle" title="Menu"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
            </div>
        </div>

        <nav class="nav-drawer" id="navDrawer">
            <a href="?action=index" class="nav-link">Feed</a>
            <a href="?action=magnitu" class="nav-link">Magnitu</a>
            <a href="?action=feeds" class="nav-link">RSS</a>
            <a href="?action=lex" class="nav-link">Lex</a>
            <a href="?action=mail" class="nav-link active" style="background-color: #FFDBBB; color: #000000;">Mail</a>
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

        <?php if (!empty($emailTags) || isset($selectedEmailTag)): ?>
        <div class="category-filter-section">
            <div class="category-filter">
                <a href="?action=mail"
                   class="category-btn <?= !$selectedEmailTag ? 'active' : '' ?>"
                   <?= !$selectedEmailTag ? 'style="background-color: #FFDBBB;"' : '' ?>>
                    All
                </a>
                <?php foreach ($emailTags as $tag): ?>
                    <a href="?action=mail&email_tag=<?= urlencode($tag) ?>"
                       class="category-btn <?= $selectedEmailTag === $tag ? 'active' : '' ?>"
                       <?= $selectedEmailTag === $tag ? 'style="background-color: #FFDBBB;"' : '' ?>>
                        <?= htmlspecialchars($tag) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="latest-entries-section">
            <div class="section-title-row">
                <h2 class="section-title">
                    <?php if (!empty($lastMailRefreshDate)): ?>
                        Refreshed: <?= htmlspecialchars($lastMailRefreshDate) ?>
                    <?php else: ?>
                        Refreshed: Never
                    <?php endif; ?>
                </h2>
                <button class="btn btn-secondary entry-expand-all-btn">&#9660; expand all</button>
            </div>

            <?php if (!empty($mailTableError)): ?>
                <div class="message message-error">
                    <strong>Error:</strong> <?= htmlspecialchars($mailTableError) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($emails)): ?>
                <?php foreach ($emails as $email): ?>
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
                        $bodyPreview = mb_substr($body, 0, 400);
                        if (mb_strlen($body) > 400) $bodyPreview .= '...';
                        $hasMore = mb_strlen($body) > 400;
                    ?>

                    <div class="entry-card">
                        <div class="entry-header">
                            <?php if (!empty($email['sender_tag']) && $email['sender_tag'] !== 'unclassified'): ?>
                                <span class="entry-tag" style="background-color: #FFDBBB;"><?= htmlspecialchars($email['sender_tag']) ?></span>
                            <?php endif; ?>
                        </div>
                        <h3 class="entry-title"><?= htmlspecialchars($subject) ?></h3>
                        <div class="entry-content entry-preview"><?= htmlspecialchars($bodyPreview) ?></div>
                        <div class="entry-full-content" style="display:none"><?= htmlspecialchars($body) ?></div>
                        <div class="entry-actions">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <?php if ($hasMore): ?>
                                    <button class="btn btn-secondary entry-expand-btn">&#9660; expand</button>
                                <?php endif; ?>
                                <?php if (isset($email['id'])): ?>
                                    <a href="?action=delete_email&id=<?= (int)$email['id'] ?>&confirm=yes" 
                                       class="btn btn-danger" 
                                       onclick="return confirm('Are you sure you want to delete this email? This action cannot be undone.');"
                                       >
                                        Delete Email
                                    </a>
                                <?php endif; ?>
                            </div>
                            <?php if ($createdAt): ?>
                                <span class="entry-date"><?= htmlspecialchars($createdAt) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <p>No emails yet.</p>
                </div>
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
