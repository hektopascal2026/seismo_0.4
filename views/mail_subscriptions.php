<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mail subscriptions - Seismo</title>
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
                    Mail subscriptions
                </span>
                <span class="top-bar-subtitle"><?= (int)$totalActive ?> active<?= $disabledCount > 0 ? ', ' . (int)$disabledCount . ' paused' : '' ?></span>
            </div>
            <div class="top-bar-actions">
                <button type="button" class="top-bar-btn" id="menuToggle" title="Menu"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
            </div>
        </div>

        <nav class="nav-drawer" id="navDrawer">
            <a href="?action=index" class="nav-link">Feed</a>
            <a href="?action=magnitu" class="nav-link">Magnitu</a>
            <a href="?action=feeds" class="nav-link">RSS</a>
            <a href="?action=calendar" class="nav-link">Calendar</a>
            <a href="?action=lex" class="nav-link">Lex</a>
            <a href="?action=jus" class="nav-link">Jus</a>
            <a href="?action=mail" class="nav-link">Mail</a>
            <a href="?action=mail_subscriptions" class="nav-link active" style="background-color: #FFDBBB; color: #000000;">Subscriptions</a>
            <a href="?action=substack" class="nav-link">Substack</a>
            <a href="?action=scraper" class="nav-link">Scraper</a>
            <a href="?action=settings" class="nav-link">Settings</a>
            <a href="?action=about" class="nav-link">About</a>
        </nav>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="message message-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="message message-error"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <p style="margin: 1rem 0;">
            <a href="?action=mail" class="about-link">← Back to Mail</a>
        </p>

        <form method="post" action="<?= getBasePath() ?>/index.php?action=rebuild_email_subscriptions" style="margin-bottom: 1rem;">
            <button type="submit" class="btn btn-secondary">Full resync from mailbox</button>
            <span class="text-muted" style="font-size: 0.9rem; margin-left: 0.5rem;">Re-scan stored messages and rebuild counts (safe to run anytime).</span>
        </form>

        <?php if (!empty($categories) || $selectedCategory): ?>
        <div class="category-filter-section">
            <div class="category-filter">
                <a href="?action=mail_subscriptions<?= $showRemoved ? '&show_removed=1' : '' ?>"
                   class="category-btn <?= !$selectedCategory ? 'active' : '' ?>"
                   <?= !$selectedCategory ? 'style="background-color: #FFDBBB;"' : '' ?>>All</a>
                <?php foreach ($categories as $cat): ?>
                    <a href="?action=mail_subscriptions&category=<?= urlencode($cat) ?><?= $showRemoved ? '&show_removed=1' : '' ?>"
                       class="category-btn <?= $selectedCategory === $cat ? 'active' : '' ?>"
                       <?= $selectedCategory === $cat ? 'style="background-color: #FFDBBB;"' : '' ?>><?= htmlspecialchars($cat) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <p>
            <a href="?action=mail_subscriptions<?= $showRemoved ? '' : '&show_removed=1' ?>" class="about-link"><?= $showRemoved ? 'Hide removed' : 'Show removed' ?></a>
        </p>

        <div class="latest-entries-section">
            <h2 class="section-title">Add subscription</h2>
            <form method="post" action="<?= getBasePath() ?>/index.php?action=add_email_subscription" class="form-row" style="display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: flex-end; margin-bottom: 2rem;">
                <div>
                    <label for="match_type">Match</label><br>
                    <select name="match_type" id="match_type">
                        <option value="domain">Domain (e.g. newsletter.example.com)</option>
                        <option value="email">Exact email address</option>
                    </select>
                </div>
                <div>
                    <label for="match_value">Value</label><br>
                    <input type="text" name="match_value" id="match_value" placeholder="example.com or name@example.com" style="min-width: 220px;">
                </div>
                <div>
                    <label for="display_name">Display name</label><br>
                    <input type="text" name="display_name" id="display_name" placeholder="Optional">
                </div>
                <div>
                    <label for="category">Category</label><br>
                    <input type="text" name="category" id="category" placeholder="unsortiert" value="unsortiert">
                </div>
                <div>
                    <button type="submit" class="btn">Add</button>
                </div>
            </form>

            <h2 class="section-title"><?= $showRemoved ? 'Removed' : 'Subscriptions' ?></h2>

            <?php if (empty($subscriptions)): ?>
                <div class="empty-state"><p>No subscriptions yet. Incoming mail will create domain entries automatically, or add one above.</p></div>
            <?php else: ?>
                <table class="data-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="text-align: left;">Source</th>
                            <th style="text-align: left;">Category</th>
                            <th style="text-align: left;">Last seen</th>
                            <th style="text-align: right;">Items</th>
                            <th style="text-align: left;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subscriptions as $s): ?>
                            <?php
                                $ls = $s['last_seen_at'] ? date('d.m.Y H:i', strtotime($s['last_seen_at'])) : '—';
                                $matcher = $s['match_type'] === 'domain' ? '@' . htmlspecialchars($s['match_value']) : htmlspecialchars($s['match_value']);
                            ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($s['display_name']) ?></strong><br>
                                    <small style="opacity: 0.85;"><?= $matcher ?></small>
                                    <?php if (!empty($s['unsubscribe_one_click'])): ?><span class="entry-tag" style="background:#e0e0e0;">1-click</span><?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($showRemoved): ?>
                                        <?= htmlspecialchars($s['category'] ?? '—') ?>
                                    <?php else: ?>
                                        <form method="post" action="<?= getBasePath() ?>/index.php?action=edit_email_subscription" style="display:inline;">
                                            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                            <input type="text" name="display_name" value="<?= htmlspecialchars($s['display_name']) ?>" style="max-width:120px;">
                                            <input type="text" name="category" value="<?= htmlspecialchars($s['category'] ?? '') ?>" placeholder="category" style="max-width:100px;">
                                            <button type="submit" class="btn btn-secondary" style="padding: 0.2rem 0.5rem;">Save</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($ls) ?></td>
                                <td style="text-align: right;"><?= (int)$s['item_count'] ?></td>
                                <td>
                                    <?php if (!$showRemoved): ?>
                                        <?php if (empty($s['removed_at'])): ?>
                                            <form method="post" action="<?= getBasePath() ?>/index.php?action=toggle_email_subscription" style="display:inline;">
                                                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                                <button type="submit" class="btn btn-secondary" style="padding: 0.2rem 0.5rem;"><?= (int)$s['disabled'] ? 'Resume' : 'Pause' ?></button>
                                            </form>
                                            <?php if (!empty($s['unsubscribe_url']) || !empty($s['unsubscribe_mailto'])): ?>
                                                <a href="<?= getBasePath() ?>/index.php?action=unsubscribe_email_subscription&id=<?= (int)$s['id'] ?>" class="btn btn-secondary" style="padding: 0.2rem 0.5rem; text-decoration: none; display: inline-block;">Unsubscribe</a>
                                            <?php endif; ?>
                                            <form method="post" action="<?= getBasePath() ?>/index.php?action=delete_email_subscription" style="display:inline;" onsubmit="return confirm('Remove this subscription? Future mail from this source will be hidden.');">
                                                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                                <button type="submit" class="btn btn-secondary" style="padding: 0.2rem 0.5rem;">Remove</button>
                                            </form>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <form method="post" action="<?= getBasePath() ?>/index.php?action=restore_email_subscription">
                                            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                            <button type="submit" class="btn btn-secondary" style="padding: 0.2rem 0.5rem;">Restore</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <script>
    document.getElementById('menuToggle')?.addEventListener('click', function() {
        document.getElementById('navDrawer')?.classList.toggle('open');
    });
    </script>
</body>
</html>
