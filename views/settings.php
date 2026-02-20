<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Seismo</title>
    <link rel="stylesheet" href="<?= getBasePath() ?>/assets/css/style.css">
    <style>
        .settings-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #000000;
        }
        
        .settings-section:last-child {
            border-bottom: none;
        }
        
        .settings-section h2 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 16px;
            color: #000000;
        }
        
        .settings-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .settings-item {
            border: 1px solid #000000;
            padding: 12px 16px;
            background-color: #ffffff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .settings-item-info {
            flex: 1;
            min-width: 200px;
        }
        
        .settings-item-title {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 4px;
            color: #000000;
        }
        
        .settings-item-meta {
            font-size: 12px;
            color: #000000;
            margin-bottom: 4px;
        }
        
        .settings-item-tag {
            display: inline-block;
            padding: 4px 12px;
            background-color: #f5f5f5;
            border: 1px solid #000000;
            font-size: 12px;
            font-weight: 600;
            margin-top: 8px;
        }
        
        .settings-item-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .tag-input-wrapper {
            position: relative;
            display: inline-flex;
            align-items: center;
        }
        
        .tag-input {
            padding: 6px 12px;
            border: 2px solid #000000;
            background-color: #ffffff;
            color: #000000;
            font-size: 14px;
            font-family: inherit;
            font-weight: 500;
            width: 150px;
            transition: all 0.3s ease;
        }
        
        .tag-input:focus {
            outline: none;
            background-color: #fafafa;
            box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.1);
        }
        
        .tag-input.tag-saving {
            border-color: #666666;
            background-color: #f5f5f5;
        }
        
        .tag-input.tag-saved {
            border-color: #00aa00;
            background-color: #f0fff0;
        }
        
    </style>
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
                    Settings
                </span>
                <span class="top-bar-subtitle">Manage sources and tags</span>
            </div>
            <div class="top-bar-actions">
                <a href="?action=refresh_all&amp;from=settings" class="top-bar-btn" title="Refresh all sources"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/></svg></a>
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
            <a href="?action=substack" class="nav-link">Substack</a>
            <a href="?action=scraper" class="nav-link">Scraper</a>
            <a href="?action=settings" class="nav-link active">Settings</a>
            <a href="?action=about" class="nav-link">About</a>
            <a href="?action=beta" class="nav-link">Beta</a>
        </nav>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="message message-success"><?= nl2br(htmlspecialchars($_SESSION['success'])) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="message message-error"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Settings Tab Bar -->
        <div class="tag-filter-list" style="margin-bottom: 16px;">
            <a href="?action=settings&amp;tab=basic" class="tag-filter-pill" style="text-decoration: none;<?= $settingsTab === 'basic' ? ' background-color: #add8e6;' : '' ?>">Basic</a>
            <a href="?action=settings&amp;tab=script" class="tag-filter-pill" style="text-decoration: none;<?= $settingsTab === 'script' ? ' background-color: #FFDBBB;' : '' ?>">Script</a>
            <a href="?action=settings&amp;tab=lex" class="tag-filter-pill" style="text-decoration: none;<?= $settingsTab === 'lex' ? ' background-color: #f5f562;' : '' ?>">Lex</a>
            <a href="?action=settings&amp;tab=magnitu" class="tag-filter-pill" style="text-decoration: none;<?= $settingsTab === 'magnitu' ? ' background-color: #FF6B6B;' : '' ?>">Magnitu</a>
        </div>

        <?php if (!empty($trippedFeeds) || !empty($trippedLexSources)): ?>
        <div style="background: #fff3cd; border: 2px solid #000; padding: 10px 14px; margin-bottom: 16px; font-size: 13px;">
            <strong>Circuit Breaker</strong> — Some sources have been automatically paused after 3+ consecutive failures.
            A manual refresh of an individual feed will reset its counter.
            <?php if (!empty($trippedFeeds)): ?>
                <div style="margin-top: 6px;">
                    <?php foreach ($trippedFeeds as $tf): ?>
                        <div style="margin: 4px 0;">
                            <strong><?= htmlspecialchars($tf['title']) ?></strong>
                            <span style="color: #666;">(<?= (int)$tf['consecutive_failures'] ?> failures)</span>
                            <?php if ($tf['last_error']): ?>
                                — <code style="font-size: 11px;"><?= htmlspecialchars(mb_substr($tf['last_error'], 0, 120)) ?></code>
                            <?php endif; ?>
                            <a href="?action=refresh_feed&id=<?= $tf['id'] ?>" style="margin-left: 4px; font-size: 11px;">retry</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($trippedLexSources)): ?>
                <div style="margin-top: 6px;">
                    Lex/Jus sources tripped: <strong><?= htmlspecialchars(implode(', ', $trippedLexSources)) ?></strong>
                    — will auto-retry on next successful refresh cycle.
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($settingsTab === 'magnitu'): ?>
        <p style="font-size: 12px; margin-bottom: 16px;">ML-powered relevance scoring. Connect to your Magnitu instance and manage scoring settings.</p>

        <!-- Magnitu Section -->
        <section class="settings-section" id="magnitu-settings">
            <h2 style="background-color: #FF6B6B; padding: 8px 14px; display: inline-block;">Magnitu</h2>

            <!-- Connection Info -->
            <div style="margin-bottom: 24px; padding: 16px; border: 2px solid #000000; background: #fafafa;">
                <div style="margin-bottom: 16px;">
                    <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">API Key</label>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <input type="text" id="magnituApiKey" 
                               value="<?= htmlspecialchars($magnituConfig['api_key'] ?? '') ?>" 
                               readonly
                               style="flex: 1; padding: 6px 10px; border: 2px solid #000000; font-family: monospace; font-size: 12px; background: #f5f5f5; cursor: pointer;"
                               onclick="this.select(); document.execCommand('copy'); this.style.borderColor='#00aa00'; setTimeout(()=>this.style.borderColor='#000000', 1500);"
                               title="Click to copy">
                        <form method="POST" action="<?= getBasePath() ?>/index.php?action=regenerate_magnitu_key" style="margin: 0;">
                            <button type="submit" class="btn" onclick="return confirm('Regenerate API key? Magnitu will need the new key.');">Regenerate</button>
                        </form>
                    </div>
                    <div style="font-size: 12px; margin-top: 4px; color: #000000;">Click the key to copy. Use this in Magnitu's settings to connect.</div>
                </div>

                <div style="margin-bottom: 12px;">
                    <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Seismo API URL (for Magnitu)</label>
                    <?php
                        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                        $path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
                        $baseApiUrl = $protocol . '://' . $host . $path . '/index.php';
                    ?>
                    <input type="text" 
                           value="<?= htmlspecialchars($baseApiUrl) ?>" 
                           readonly
                           style="width: 100%; padding: 6px 10px; border: 2px solid #000000; font-family: monospace; font-size: 12px; background: #f5f5f5; box-sizing: border-box; cursor: pointer;"
                           onclick="this.select(); document.execCommand('copy'); this.style.borderColor='#00aa00'; setTimeout(()=>this.style.borderColor='#000000', 1500);"
                           title="Click to copy">
                </div>

                <!-- Status -->
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-top: 16px;">
                    <div style="text-align: center; padding: 10px; border: 2px solid #000000;">
                        <div style="font-size: 18px; font-weight: 700;"><?= $magnituScoreStats['total'] ?></div>
                        <div style="font-size: 12px;">Entries Scored</div>
                    </div>
                    <div style="text-align: center; padding: 10px; border: 2px solid #000000;">
                        <div style="font-size: 18px; font-weight: 700;"><?= $magnituScoreStats['magnitu'] ?></div>
                        <div style="font-size: 12px;">By Magnitu (full model)</div>
                    </div>
                    <div style="text-align: center; padding: 10px; border: 2px solid #000000;">
                        <div style="font-size: 18px; font-weight: 700;"><?= $magnituScoreStats['recipe'] ?></div>
                        <div style="font-size: 12px;">By Recipe (keywords)</div>
                    </div>
                </div>
                
                <?php if (!empty($magnituConfig['last_sync_at'])): ?>
                    <div style="font-size: 12px; margin-top: 12px;">
                        Last sync: <strong><?= htmlspecialchars($magnituConfig['last_sync_at']) ?></strong>
                        &middot; Recipe version: <strong><?= htmlspecialchars($magnituConfig['recipe_version'] ?? '0') ?></strong>
                    </div>
                <?php else: ?>
                    <div style="font-size: 12px; margin-top: 12px; color: #000000;">
                        No sync yet. Connect Magnitu using the API key and URL above.
                    </div>
                <?php endif; ?>

                <?php if (!empty($magnituConfig['model_name'])): ?>
                <!-- Connected Model -->
                <div style="margin-top: 16px; padding: 12px 14px; border: 2px solid #000000; background: #ffffff;">
                    <div style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 8px;">Connected Model</div>
                    <div style="display: flex; gap: 16px; align-items: baseline;">
                        <span style="font-size: 18px; font-weight: 700;"><?= htmlspecialchars($magnituConfig['model_name']) ?></span>
                        <?php if (!empty($magnituConfig['model_version'])): ?>
                            <span style="font-size: 12px; font-weight: 600; padding: 2px 8px; border: 2px solid #000000; background: #FF6B6B;">v<?= htmlspecialchars($magnituConfig['model_version']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($magnituConfig['model_description'])): ?>
                        <div style="font-size: 12px; margin-top: 4px; color: #000000;"><?= htmlspecialchars($magnituConfig['model_description']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($magnituConfig['model_trained_at'])): ?>
                        <div style="font-size: 12px; margin-top: 6px; color: #000000;">
                            Last trained: <strong><?= htmlspecialchars(substr($magnituConfig['model_trained_at'], 0, 16)) ?></strong>
                        </div>
                    <?php endif; ?>
                    <div style="font-size: 11px; margin-top: 8px; color: #000000; opacity: 0.6; font-style: italic;">Model files are managed in the Magnitu app.</div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Scoring Settings -->
            <form method="POST" action="<?= getBasePath() ?>/index.php?action=save_magnitu_config">
                <div style="margin-bottom: 24px; padding: 16px; border: 2px solid #000000; background: #fafafa;">
                    <h3 style="margin-top: 0; margin-bottom: 12px;">Scoring Settings</h3>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Alert Threshold (0.0 - 1.0)</label>
                            <input type="number" name="alert_threshold" 
                                   value="<?= htmlspecialchars($magnituConfig['alert_threshold'] ?? '0.75') ?>" 
                                   min="0" max="1" step="0.05"
                                   style="width: 100%; padding: 6px 10px; border: 2px solid #000000; font-family: inherit; font-size: 14px; box-sizing: border-box;">
                            <div style="font-size: 12px; margin-top: 4px; color: #000000;">Entries scoring above this are highlighted as alerts in the feed.</div>
                        </div>
                        <div>
                            <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Default Sort</label>
                            <label style="display: flex; align-items: center; gap: 8px; padding: 8px 0; cursor: pointer;">
                                <input type="checkbox" name="sort_by_relevance" value="1" <?= ($magnituConfig['sort_by_relevance'] ?? '0') === '1' ? 'checked' : '' ?>>
                                <span style="font-size: 14px;">Sort feed by relevance (instead of chronological)</span>
                            </label>
                        </div>
                    </div>

                    <div style="display: flex; gap: 12px; align-items: center;">
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </div>
                </div>
            </form>

            <!-- Danger Zone -->
            <div style="padding: 16px; border: 2px solid #FF2C2C; background: #fff5f5;">
                <h3 style="margin-top: 0; margin-bottom: 8px; color: #FF2C2C;">Danger Zone</h3>
                <p style="font-size: 12px; margin-bottom: 12px;">
                    Clear all Magnitu scores and the scoring recipe. The feed will return to chronological order.
                    Your Magnitu labels (in the Magnitu app) are not affected.
                </p>
                <form method="POST" action="<?= getBasePath() ?>/index.php?action=clear_magnitu_scores">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Clear all Magnitu scores and recipe? This cannot be undone.');">
                        Clear All Scores
                    </button>
                </form>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($settingsTab === 'basic'): ?>
        <p style="font-size: 12px; margin-bottom: 16px;">Add and manage RSS feeds and Substack newsletters.</p>

        <!-- RSS Section -->
        <section class="settings-section">
            <h2 style="background-color: #add8e6; padding: 8px 14px; display: inline-block;">RSS</h2>
            
            <!-- Add Feed Section -->
            <div class="add-feed-section" style="margin-bottom: 16px;">
                <form method="POST" action="<?= getBasePath() ?>/index.php?action=add_feed" enctype="multipart/form-data" class="add-feed-form">
                    <input type="hidden" name="from" value="settings">
                    <input type="url" name="url" placeholder="Enter RSS feed URL (e.g., https://example.com/feed.xml)" required class="feed-input">
                    <button type="submit" class="btn btn-primary">Add Feed</button>
                </form>
            </div>
            
            <!-- All Tags Section -->
            <?php if (!empty($allTags)): ?>
                <div style="margin-bottom: 12px;">
                    <h3 style="margin-top: 0; margin-bottom: 6px;">All Tags</h3>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        <?php foreach ($allTags as $tag): ?>
                            <div class="feed-tag-input-wrapper" style="display: inline-flex;">
                                <input 
                                    type="text" 
                                    class="feed-tag-input all-tag-input" 
                                    value="<?= htmlspecialchars($tag) ?>" 
                                    data-original-tag="<?= htmlspecialchars($tag) ?>"
                                    data-tag-name="<?= htmlspecialchars($tag, ENT_QUOTES) ?>"
                                    style="width: auto; min-width: 100px; padding: 6px 12px;"
                                >
                                <span class="feed-tag-indicator"></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (empty($allFeeds)): ?>
                <div class="empty-state">
                    <p>No feeds added yet.</p>
                </div>
            <?php else: ?>
                <div class="settings-list">
                    <?php foreach ($allFeeds as $feed): ?>
                        <div class="settings-item">
                            <div class="settings-item-info">
                                <div class="settings-item-title"><?= htmlspecialchars($feed['title']) ?></div>
                                <?php if (!empty($feed['description'])): ?>
                                    <div class="settings-item-meta"><?= htmlspecialchars($feed['description']) ?></div>
                                <?php endif; ?>
                                <div class="settings-item-meta"><?= htmlspecialchars($feed['url']) ?></div>
                                <?php if ($feed['last_fetched']): ?>
                                    <div class="settings-item-meta">Last updated: <?= date('d.m.Y H:i', strtotime($feed['last_fetched'])) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="settings-item-actions" style="flex-direction: column; align-items: flex-end; gap: 10px;">
                                <div style="display: flex; gap: 10px;">
                                    <a href="?action=toggle_feed&amp;id=<?= $feed['id'] ?>&amp;from=settings" class="btn <?= $feed['disabled'] ? 'btn-success' : 'btn-warning' ?>">
                                        <?= $feed['disabled'] ? 'Enable' : 'Disable' ?>
                                    </a>
                                    <a href="?action=delete_feed&amp;id=<?= $feed['id'] ?>&amp;from=settings" 
                                       class="btn btn-danger" 
                                       onclick="return confirm('Are you sure you want to delete this feed? This action cannot be undone.');">
                                        Delete
                                    </a>
                                </div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <label style="font-weight: 600;">Tag:</label>
                                    <div class="tag-input-wrapper">
                                        <input 
                                            type="text" 
                                            class="tag-input feed-tag-input" 
                                            value="<?= htmlspecialchars($feed['category'] ?? 'unsortiert') ?>" 
                                            data-feed-id="<?= $feed['id'] ?>"
                                            data-original-tag="<?= htmlspecialchars($feed['category'] ?? 'unsortiert') ?>"
                                            style="width: 150px;"
                                        >
                                        <span class="feed-tag-indicator"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- RSS Config file management -->
            <div style="margin-top: 16px; padding-top: 16px; border-top: 2px solid #000000;">
                <h3 style="margin-top: 0; margin-bottom: 8px;">Config File</h3>
                <p style="font-size: 12px; margin-bottom: 12px;">
                    Download your RSS feeds as JSON, or upload a config file to add/update feeds in bulk.
                </p>
                <div style="display: flex; gap: 12px; align-items: flex-start; flex-wrap: wrap;">
                    <a href="?action=download_rss_config" class="btn" style="text-decoration: none;">
                        Download rss_feeds.json
                    </a>
                    <form method="POST" action="<?= getBasePath() ?>/index.php?action=upload_rss_config" enctype="multipart/form-data" style="display: flex; gap: 8px; align-items: center;">
                        <input type="file" name="rss_config_file" accept=".json,application/json" style="font-size: 12px; font-family: inherit;">
                        <button type="submit" class="btn">Upload</button>
                    </form>
                </div>
            </div>
        </section>

        <!-- Substack Section -->
        <section class="settings-section">
            <h2 style="background-color: #C5B4D1; padding: 8px 14px; display: inline-block;">Substack</h2>

            <!-- Add Substack Section -->
            <div class="add-feed-section" style="margin-bottom: 16px;">
                <form method="POST" action="<?= getBasePath() ?>/index.php?action=add_substack" class="add-feed-form">
                    <input type="hidden" name="from" value="settings">
                    <input type="url" name="url" placeholder="Enter Substack URL (e.g., https://example.substack.com)" required class="feed-input">
                    <button type="submit" class="btn btn-primary">Add Substack</button>
                </form>
            </div>

            <!-- All Substack Tags Section -->
            <?php if (!empty($allSubstackTags)): ?>
                <div style="margin-bottom: 12px;">
                    <h3 style="margin-top: 0; margin-bottom: 6px;">All Tags</h3>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        <?php foreach ($allSubstackTags as $tag): ?>
                            <div class="feed-tag-input-wrapper" style="display: inline-flex;">
                                <input 
                                    type="text" 
                                    class="feed-tag-input all-substack-tag-input" 
                                    value="<?= htmlspecialchars($tag) ?>" 
                                    data-original-tag="<?= htmlspecialchars($tag) ?>"
                                    data-tag-name="<?= htmlspecialchars($tag, ENT_QUOTES) ?>"
                                    style="width: auto; min-width: 100px; padding: 6px 12px;"
                                >
                                <span class="feed-tag-indicator"></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (empty($substackFeeds)): ?>
                <div class="empty-state">
                    <p>No Substack subscriptions yet. <a href="?action=substack">Subscribe to a newsletter</a></p>
                </div>
            <?php else: ?>
                <div class="settings-list">
                    <?php foreach ($substackFeeds as $feed): ?>
                        <div class="settings-item">
                            <div class="settings-item-info">
                                <div class="settings-item-title"><?= htmlspecialchars($feed['title']) ?></div>
                                <?php if (!empty($feed['description'])): ?>
                                    <div class="settings-item-meta"><?= htmlspecialchars($feed['description']) ?></div>
                                <?php endif; ?>
                                <div class="settings-item-meta"><?= htmlspecialchars($feed['url']) ?></div>
                                <?php if ($feed['last_fetched']): ?>
                                    <div class="settings-item-meta">Last updated: <?= date('d.m.Y H:i', strtotime($feed['last_fetched'])) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="settings-item-actions" style="flex-direction: column; align-items: flex-end; gap: 10px;">
                                <div style="display: flex; gap: 10px;">
                                    <a href="?action=toggle_feed&amp;id=<?= $feed['id'] ?>&amp;from=settings" class="btn <?= $feed['disabled'] ? 'btn-success' : 'btn-warning' ?>">
                                        <?= $feed['disabled'] ? 'Enable' : 'Disable' ?>
                                    </a>
                                    <a href="?action=delete_feed&amp;id=<?= $feed['id'] ?>&amp;from=settings" 
                                       class="btn btn-danger" 
                                       onclick="return confirm('Are you sure you want to unsubscribe from this Substack?');">
                                        Delete
                                    </a>
                                </div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <label style="font-weight: 600;">Tag:</label>
                                    <div class="tag-input-wrapper">
                                        <input 
                                            type="text" 
                                            class="tag-input feed-tag-input" 
                                            value="<?= htmlspecialchars($feed['category'] ?? $feed['title']) ?>" 
                                            data-feed-id="<?= $feed['id'] ?>"
                                            data-original-tag="<?= htmlspecialchars($feed['category'] ?? $feed['title']) ?>"
                                            style="width: 150px;"
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Substack Config file management -->
            <div style="margin-top: 16px; padding-top: 16px; border-top: 2px solid #000000;">
                <h3 style="margin-top: 0; margin-bottom: 8px;">Config File</h3>
                <p style="font-size: 12px; margin-bottom: 12px;">
                    Download your Substack subscriptions as JSON, or upload a config file to add/update them in bulk.
                </p>
                <div style="display: flex; gap: 12px; align-items: flex-start; flex-wrap: wrap;">
                    <a href="?action=download_substack_config" class="btn" style="text-decoration: none;">
                        Download substack_feeds.json
                    </a>
                    <form method="POST" action="<?= getBasePath() ?>/index.php?action=upload_substack_config" enctype="multipart/form-data" style="display: flex; gap: 8px; align-items: center;">
                        <input type="file" name="substack_config_file" accept=".json,application/json" style="font-size: 12px; font-family: inherit;">
                        <button type="submit" class="btn">Upload</button>
                    </form>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($settingsTab === 'script'): ?>
        <p style="font-size: 12px; margin-bottom: 16px;">Manage email sources and web page scrapers fetched by server-side scripts.</p>

        <!-- Mail Section -->
        <section class="settings-section">
            <h2 style="background-color: #FFDBBB; padding: 8px 14px; display: inline-block;">Mail</h2>
            <p style="font-size: 12px; margin-bottom: 4px; line-height: 1.6;">
                <strong>Setup:</strong>
                ① Fill in your IMAP credentials below and hit Save.
                ② Download <code>fetch_mail.php</code> and <code>config.php</code>.
                ③ Upload both files to a folder on your server (requires PHP IMAP extension — enabled on most hosts).
                ④ Add a cronjob: <code>*/15 * * * * /usr/bin/php /path/to/fetch_mail.php</code>
            </p>

            <!-- Mail IMAP Configuration -->
            <div style="margin-bottom: 16px; padding: 16px; border: 2px solid #000000; background: #fafafa;">
                <h3 style="margin-top: 0; margin-bottom: 12px;">IMAP Configuration</h3>
                <form method="POST" action="<?= getBasePath() ?>/index.php?action=save_mail_config">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Mailbox (IMAP string)</label>
                            <input type="text" name="mail_imap_mailbox" value="<?= htmlspecialchars($mailConfig['imap_mailbox'] ?? '') ?>" placeholder="{imap.example.com:993/imap/ssl}INBOX" style="width: 100%; padding: 6px 10px; border: 2px solid #000; font-family: monospace; font-size: 13px;">
                        </div>
                        <div>
                            <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Username</label>
                            <input type="text" name="mail_imap_username" value="<?= htmlspecialchars($mailConfig['imap_username'] ?? '') ?>" placeholder="user@example.com" style="width: 100%; padding: 6px 10px; border: 2px solid #000; font-family: inherit; font-size: 14px;">
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Password</label>
                            <input type="password" name="mail_imap_password" value="<?= htmlspecialchars($mailConfig['imap_password'] ?? '') ?>" placeholder="IMAP password" style="width: 100%; padding: 6px 10px; border: 2px solid #000; font-family: inherit; font-size: 14px;">
                        </div>
                        <div>
                            <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">DB table name</label>
                            <input type="text" name="mail_db_table" value="<?= htmlspecialchars($mailConfig['db_table'] ?? 'fetched_emails') ?>" style="width: 100%; padding: 6px 10px; border: 2px solid #000; font-family: monospace; font-size: 13px;">
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                        <div>
                            <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Search criteria</label>
                            <select name="mail_search_criteria" style="width: 100%; padding: 6px 10px; border: 2px solid #000; font-family: inherit; font-size: 14px;">
                                <?php $criteria = $mailConfig['search_criteria'] ?? 'UNSEEN'; ?>
                                <option value="UNSEEN" <?= $criteria === 'UNSEEN' ? 'selected' : '' ?>>UNSEEN (new only)</option>
                                <option value="ALL" <?= $criteria === 'ALL' ? 'selected' : '' ?>>ALL</option>
                                <option value="RECENT" <?= $criteria === 'RECENT' ? 'selected' : '' ?>>RECENT</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Max messages per run</label>
                            <input type="number" name="mail_max_messages" value="<?= htmlspecialchars($mailConfig['max_messages'] ?? '50') ?>" min="1" max="500" style="width: 100%; padding: 6px 10px; border: 2px solid #000; font-family: inherit; font-size: 14px;">
                        </div>
                        <div style="display: flex; align-items: flex-end; padding-bottom: 2px;">
                            <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; cursor: pointer;">
                                <input type="checkbox" name="mail_mark_seen" value="1" <?= ($mailConfig['mark_seen'] ?? '1') === '1' ? 'checked' : '' ?>>
                                Mark fetched as read
                            </label>
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <button type="submit" class="btn btn-primary">Save</button>
                        <?php if ($mailConfigured ?? false): ?>
                            <a href="<?= getBasePath() ?>/index.php?action=download_mail_config" class="btn" style="text-decoration: none;">⬇ Download config.php</a>
                        <?php endif; ?>
                        <a href="<?= getBasePath() ?>/index.php?action=download_mail_script" class="btn" style="text-decoration: none;">⬇ Download fetch_mail.php</a>
                    </div>
                </form>
            </div>

            <!-- Sender Tags -->
            <!-- All Email Tags Section -->
            <?php if (!empty($allEmailTags)): ?>
                <div style="margin-bottom: 12px;">
                    <h3 style="margin-top: 0; margin-bottom: 6px;">All Tags</h3>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        <?php foreach ($allEmailTags as $tag): ?>
                            <div class="feed-tag-input-wrapper" style="display: inline-flex;">
                                <input 
                                    type="text" 
                                    class="feed-tag-input all-email-tag-input" 
                                    value="<?= htmlspecialchars($tag) ?>" 
                                    data-original-tag="<?= htmlspecialchars($tag) ?>"
                                    data-tag-name="<?= htmlspecialchars($tag, ENT_QUOTES) ?>"
                                    style="width: auto; min-width: 100px; padding: 6px 12px;"
                                >
                                <span class="feed-tag-indicator"></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (empty($senderTags)): ?>
                <div class="empty-state">
                    <p>No email senders found yet.</p>
                </div>
            <?php else: ?>
                <div class="settings-list">
                    <?php foreach ($senderTags as $sender): ?>
                        <div class="settings-item">
                            <div class="settings-item-info">
                                <div class="settings-item-title">
                                    <?= !empty($sender['name']) ? htmlspecialchars($sender['name']) : 'Unknown' ?>
                                </div>
                                <div class="settings-item-meta"><?= htmlspecialchars($sender['email']) ?></div>
                            </div>
                            <div class="settings-item-actions" style="flex-direction: column; align-items: flex-end; gap: 10px;">
                                <div style="display: flex; gap: 10px;">
                                    <form method="POST" action="<?= getBasePath() ?>/index.php?action=toggle_sender" style="margin: 0;">
                                        <input type="hidden" name="email" value="<?= htmlspecialchars($sender['email']) ?>">
                                        <button type="submit" class="btn <?= $sender['disabled'] ? 'btn-success' : 'btn-warning' ?>">
                                            <?= $sender['disabled'] ? 'Enable' : 'Disable' ?>
                                        </button>
                                    </form>
                                    <form method="POST" action="<?= getBasePath() ?>/index.php?action=delete_sender" style="margin: 0;">
                                        <input type="hidden" name="email" value="<?= htmlspecialchars($sender['email']) ?>">
                                        <button type="submit" class="btn btn-danger">
                                            Remove
                                        </button>
                                    </form>
                                </div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <label style="font-weight: 600;">Tag:</label>
                                    <div class="tag-input-wrapper">
                                        <input 
                                            type="text" 
                                            class="tag-input" 
                                            value="<?= htmlspecialchars($sender['tag'] ?? '') ?>" 
                                            placeholder="Enter tag..."
                                            data-sender-email="<?= htmlspecialchars($sender['email']) ?>"
                                            data-original-tag="<?= htmlspecialchars($sender['tag'] ?? '') ?>"
                                            style="width: 150px;"
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Scraper Section -->
        <section class="settings-section" style="margin-top: 32px;">
            <h2 style="background-color: #FFDBBB; padding: 8px 14px; display: inline-block;">🌐 Scraper</h2>
            <p style="font-size: 12px; margin-bottom: 4px; line-height: 1.6;">
                <strong>Setup:</strong>
                ① Add the URLs you want to scrape below.
                ② Download <code>seismo_scraper.php</code> and <code>config.php</code>.
                ③ Upload both files to a folder on your server.
                ④ Add a cronjob: <code>0 */6 * * * /usr/bin/php /path/to/seismo_scraper.php</code>
                — URLs are read from the database, so no re-download needed when you add or remove pages.
            </p>

            <?php if (!empty($scraperConfigs)): ?>
            <div class="settings-list" style="margin-bottom: 16px;">
                <?php foreach ($scraperConfigs as $sc): ?>
                <div class="settings-item">
                    <div class="settings-item-info" style="flex: 1;">
                        <div class="settings-item-title"><?= htmlspecialchars($sc['name']) ?></div>
                        <div class="settings-item-meta"><?= htmlspecialchars($sc['url']) ?></div>
                        <form method="POST" action="<?= getBasePath() ?>/index.php?action=update_scraper" style="margin: 4px 0 0 0;">
                            <input type="hidden" name="scraper_id" value="<?= $sc['id'] ?>">
                            <div style="display: flex; gap: 6px; align-items: center; margin-bottom: 4px;">
                                <input type="text" name="scraper_link_pattern" value="<?= htmlspecialchars($sc['link_pattern'] ?? '') ?>" placeholder="Link pattern (optional)" style="flex: 1; padding: 4px 8px; border: 1px solid #999; font-family: monospace; font-size: 11px;">
                            </div>
                            <div style="display: flex; gap: 6px; align-items: center;">
                                <input type="text" name="scraper_date_selector" value="<?= htmlspecialchars($sc['date_selector'] ?? '') ?>" placeholder="Date selector, e.g. time[datetime] or .article-date" style="flex: 1; padding: 4px 8px; border: 1px solid #999; font-family: monospace; font-size: 11px;">
                                <button type="submit" class="btn" style="padding: 4px 10px; font-size: 11px;">Save</button>
                            </div>
                        </form>
                    </div>
                    <div class="settings-item-actions" style="display: flex; gap: 10px;">
                        <form method="POST" action="<?= getBasePath() ?>/index.php?action=toggle_scraper" style="margin: 0;">
                            <input type="hidden" name="scraper_id" value="<?= $sc['id'] ?>">
                            <button type="submit" class="btn <?= $sc['disabled'] ? 'btn-success' : 'btn-warning' ?>">
                                <?= $sc['disabled'] ? 'Enable' : 'Disable' ?>
                            </button>
                        </form>
                        <form method="POST" action="<?= getBasePath() ?>/index.php?action=remove_scraper" style="margin: 0;">
                            <input type="hidden" name="scraper_id" value="<?= $sc['id'] ?>">
                            <button type="submit" class="btn btn-danger">Remove</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state" style="margin-bottom: 16px;">
                <p>No scrapers configured yet. Add a URL below.</p>
            </div>
            <?php endif; ?>

            <div id="scraper-add-row" style="display: none; margin-bottom: 16px; padding: 16px; border: 2px solid #000; background: #fafafa;">
                <form method="POST" action="<?= getBasePath() ?>/index.php?action=add_scraper">
                    <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end;">
                        <div style="flex: 1; min-width: 150px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 4px;">Name</label>
                            <input type="text" name="scraper_name" placeholder="e.g. BAG News" required style="width: 100%; padding: 8px; border: 2px solid #000; font-family: inherit;">
                        </div>
                        <div style="flex: 2; min-width: 250px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 4px;">URL</label>
                            <input type="url" name="scraper_url" placeholder="https://example.com/page" required style="width: 100%; padding: 8px; border: 2px solid #000; font-family: inherit;">
                        </div>
                        <button type="submit" class="btn btn-primary" style="white-space: nowrap;">Save</button>
                        <button type="button" class="btn" onclick="document.getElementById('scraper-add-row').style.display='none'">Cancel</button>
                    </div>
                    <div style="margin-top: 10px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 4px;">Link pattern <span style="font-weight: 400; font-size: 11px;">(optional — if set, follows links on the page matching this substring)</span></label>
                        <input type="text" name="scraper_link_pattern" placeholder="e.g. /de/mediencorner/medienmitteilungen/" style="width: 100%; padding: 8px; border: 2px solid #000; font-family: monospace; font-size: 13px;">
                    </div>
                    <div style="margin-top: 10px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 4px;">Date selector <span style="font-weight: 400; font-size: 11px;">(optional — CSS selector for the date element on article pages, e.g. <code>time[datetime]</code> or <code>.article-date</code>)</span></label>
                        <input type="text" name="scraper_date_selector" placeholder="e.g. time[datetime] or .publish-date or meta[property=&quot;article:published_time&quot;]" style="width: 100%; padding: 8px; border: 2px solid #000; font-family: monospace; font-size: 13px;">
                    </div>
                </form>
            </div>

            <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                <button type="button" class="btn btn-primary" onclick="document.getElementById('scraper-add-row').style.display='block'" style="font-size: 18px; padding: 6px 16px;">＋ Add URL</button>
                <?php if (!empty($scraperConfigs)): ?>
                <a href="<?= getBasePath() ?>/index.php?action=download_scraper_config" class="btn" style="text-decoration: none;">⬇ Download config.php</a>
                <a href="<?= getBasePath() ?>/index.php?action=download_scraper_script" class="btn" style="text-decoration: none;">⬇ Download seismo_scraper.php</a>
                <form method="POST" action="<?= getBasePath() ?>/index.php?action=delete_all_scraper_items" style="margin: 0;">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Delete ALL scraped entries from the database? This cannot be undone. Scraper configs will be kept.')">Delete all scraped entries</button>
                </form>
                <?php endif; ?>
            </div>
        </section>

        <!-- Background Refresh Cron -->
        <section class="settings-section" id="refresh-cron-settings">
            <h2 style="background-color: #FFDBBB; padding: 8px 14px; display: inline-block;">Background Refresh</h2>
            <p style="font-size: 12px; margin-bottom: 12px;">
                Automatically refresh all feeds, lex/jus sources, and Magnitu scores via a server cronjob.
            </p>
            <div style="font-size: 12px; margin-bottom: 12px; padding: 12px; border: 2px solid #000; background: #fafafa;">
                <strong>Setup:</strong>
                <ol style="margin: 6px 0 0 18px; padding: 0;">
                    <li>Download <code>refresh_cron.php</code> and <code>config.php</code> below.</li>
                    <li>Upload both files to a private folder on your server (e.g. <code>cronjob_refresh/</code>).</li>
                    <li>Add a cronjob: <code>*/15 * * * * /usr/bin/php /path/to/cronjob_refresh/refresh_cron.php</code></li>
                </ol>
            </div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <a href="<?= getBasePath() ?>/index.php?action=download_refresh_config" class="btn" style="text-decoration: none;">⬇ Download config.php</a>
                <a href="<?= getBasePath() ?>/index.php?action=download_refresh_script" class="btn" style="text-decoration: none;">⬇ Download refresh_cron.php</a>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($settingsTab === 'lex'): ?>
        <p style="font-size: 12px; margin-bottom: 16px;">Configure EU, Swiss and German legislation monitoring, and Swiss case law (Jus).</p>

        <!-- Lex Section -->
        <section class="settings-section" id="lex-settings">
            <h2 style="background-color: #f5f562; padding: 8px 14px; display: inline-block;">Lex</h2>

            <form method="POST" action="<?= getBasePath() ?>/index.php?action=save_lex_config" id="lex-config-form">
                <input type="hidden" name="autosave" value="1">
                <!-- EU Configuration -->
                <div style="margin-bottom: 24px; padding: 16px; border: 2px solid #000000; background: #fafafa;">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                        <label style="font-weight: 700; font-size: 18px;">🇪🇺 EUR-Lex</label>
                        <?php $euEnabled = (bool)($lexConfig['eu']['enabled'] ?? true); ?>
                        <input type="hidden" name="eu_enabled" value="<?= $euEnabled ? '1' : '0' ?>">
                        <button type="button" class="btn <?= $euEnabled ? 'btn-warning' : 'btn-success' ?>" data-lex-toggle="eu_enabled">
                            <?= $euEnabled ? 'Disable' : 'Enable' ?>
                        </button>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Language</label>
                            <select name="eu_language" style="width: 100%; padding: 6px 10px; border: 2px solid #000000; font-family: inherit; font-size: 14px;">
                                <?php
                                $euLangs = ['ENG' => 'English', 'DEU' => 'Deutsch', 'FRA' => 'Français', 'ITA' => 'Italiano'];
                                $currentEuLang = $lexConfig['eu']['language'] ?? 'ENG';
                                foreach ($euLangs as $code => $label): ?>
                                    <option value="<?= $code ?>" <?= $currentEuLang === $code ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Document class</label>
                            <input type="text" name="eu_document_class" value="<?= htmlspecialchars($lexConfig['eu']['document_class'] ?? 'cdm:legislation_secondary') ?>" 
                                   style="width: 100%; padding: 6px 10px; border: 2px solid #000000; font-family: monospace; font-size: 12px; box-sizing: border-box;">
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Lookback (days)</label>
                            <input type="number" name="eu_lookback_days" value="<?= (int)($lexConfig['eu']['lookback_days'] ?? 90) ?>" min="1" max="365"
                                   style="width: 100%; padding: 6px 10px; border: 2px solid #000000; font-family: inherit; font-size: 14px; box-sizing: border-box;">
                        </div>
                        <div>
                            <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Max results</label>
                            <input type="number" name="eu_limit" value="<?= (int)($lexConfig['eu']['limit'] ?? 100) ?>" min="1" max="500"
                                   style="width: 100%; padding: 6px 10px; border: 2px solid #000000; font-family: inherit; font-size: 14px; box-sizing: border-box;">
                        </div>
                    </div>
                    
                    <div>
                        <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Notes</label>
                        <textarea name="eu_notes" rows="2" placeholder="Optional notes about this query scope..."
                                  style="width: 100%; padding: 6px 10px; border: 2px solid #000000; font-family: inherit; font-size: 12px; resize: vertical; box-sizing: border-box;"><?= htmlspecialchars($lexConfig['eu']['notes'] ?? '') ?></textarea>
                    </div>
                    
                    <div style="margin-top: 8px; font-size: 12px;">
                        Endpoint: <code style="font-size: 12px;"><?= htmlspecialchars($lexConfig['eu']['endpoint'] ?? '') ?></code>
                    </div>
                    <div style="margin-top: 6px; font-size: 12px; line-height: 1.6;">
                        Reference:
                        <a href="https://op.europa.eu/en/web/eu-vocabularies/cdm" target="_blank" rel="noopener" style="text-decoration: underline;">CDM ontology</a>
                        &middot;
                        <a href="https://eur-lex.europa.eu/content/tools/webservices/SearchWebServiceUserManual_v2.00.pdf" target="_blank" rel="noopener" style="text-decoration: underline;">EUR-Lex web services manual</a>
                        &middot;
                        <a href="https://op.europa.eu/en/web/eu-vocabularies/dataset/-/resource?uri=http://publications.europa.eu/resource/dataset/resource-type" target="_blank" rel="noopener" style="text-decoration: underline;">EU resource-type vocabulary</a>
                        &middot;
                        <a href="https://publications.europa.eu/webapi/rdf/sparql" target="_blank" rel="noopener" style="text-decoration: underline;">SPARQL endpoint</a>
                    </div>
                </div>

                <!-- CH Configuration -->
                <div style="margin-bottom: 24px; padding: 16px; border: 2px solid #000000; background: #fafafa;">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                        <label style="font-weight: 700; font-size: 18px;">🇨🇭 Fedlex</label>
                        <?php $chEnabled = (bool)($lexConfig['ch']['enabled'] ?? true); ?>
                        <input type="hidden" name="ch_enabled" value="<?= $chEnabled ? '1' : '0' ?>">
                        <button type="button" class="btn <?= $chEnabled ? 'btn-warning' : 'btn-success' ?>" data-lex-toggle="ch_enabled">
                            <?= $chEnabled ? 'Disable' : 'Enable' ?>
                        </button>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Language</label>
                            <select name="ch_language" style="width: 100%; padding: 6px 10px; border: 2px solid #000000; font-family: inherit; font-size: 14px;">
                                <?php
                                $chLangs = ['DEU' => 'Deutsch', 'FRA' => 'Français', 'ITA' => 'Italiano', 'ENG' => 'English'];
                                $currentChLang = $lexConfig['ch']['language'] ?? 'DEU';
                                foreach ($chLangs as $code => $label): ?>
                                    <option value="<?= $code ?>" <?= $currentChLang === $code ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Lookback (days)</label>
                            <input type="number" name="ch_lookback_days" value="<?= (int)($lexConfig['ch']['lookback_days'] ?? 90) ?>" min="1" max="365"
                                   style="width: 100%; padding: 6px 10px; border: 2px solid #000000; font-family: inherit; font-size: 14px; box-sizing: border-box;">
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Max results</label>
                            <input type="number" name="ch_limit" value="<?= (int)($lexConfig['ch']['limit'] ?? 100) ?>" min="1" max="500"
                                   style="width: 100%; padding: 6px 10px; border: 2px solid #000000; font-family: inherit; font-size: 14px; box-sizing: border-box;">
                        </div>
                        <div></div>
                    </div>
                    
                    <div style="margin-bottom: 12px;">
                        <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Resource types (comma-separated IDs)</label>
                        <?php
                        $rtIds = array_map(function($rt) { return is_array($rt) ? $rt['id'] : $rt; }, $lexConfig['ch']['resource_types'] ?? []);
                        ?>
                        <input type="text" name="ch_resource_types" value="<?= htmlspecialchars(implode(', ', $rtIds)) ?>"
                               style="width: 100%; padding: 6px 10px; border: 2px solid #000000; font-family: monospace; font-size: 12px; box-sizing: border-box;">
                        <div style="margin-top: 6px; font-size: 12px; line-height: 1.6;">
                            <?php foreach (($lexConfig['ch']['resource_types'] ?? []) as $rt): ?>
                                <span style="display: inline-block; background: #f5f5f5; padding: 2px 8px; margin: 2px 4px 2px 0; border: 2px solid #000000; font-family: monospace; font-size: 12px;">
                                    <?= (int)(is_array($rt) ? $rt['id'] : $rt) ?> = <?= htmlspecialchars(is_array($rt) ? ($rt['label'] ?? '?') : '?') ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <div style="margin-top: 8px; font-size: 12px; line-height: 1.6;">
                            Browse all available IDs:
                            <a href="https://fedlex.data.admin.ch/vocabularies/de/page/resource-type" target="_blank" rel="noopener" style="text-decoration: underline;">Fedlex vocabulary: resource-type</a>
                            &middot;
                            <a href="https://fedlex.data.admin.ch/en-CH/home/models" target="_blank" rel="noopener" style="text-decoration: underline;">JOLux data model</a>
                            &middot;
                            <a href="https://github.com/swiss/fedlex-sparql" target="_blank" rel="noopener" style="text-decoration: underline;">SPARQL tutorial</a>
                        </div>
                    </div>
                    
                    <div>
                        <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Notes</label>
                        <textarea name="ch_notes" rows="2" placeholder="Optional notes about this query scope..."
                                  style="width: 100%; padding: 6px 10px; border: 2px solid #000000; font-family: inherit; font-size: 12px; resize: vertical; box-sizing: border-box;"><?= htmlspecialchars($lexConfig['ch']['notes'] ?? '') ?></textarea>
                    </div>
                    
                    <div style="margin-top: 8px; font-size: 12px;">
                        Endpoint: <code style="font-size: 12px;"><?= htmlspecialchars($lexConfig['ch']['endpoint'] ?? '') ?></code>
                    </div>
                </div>

                <!-- DE Configuration -->
                <div style="margin-bottom: 24px; padding: 16px; border: 2px solid #000000; background: #fafafa;">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                        <label style="font-weight: 700; font-size: 18px;">🇩🇪 recht.bund.de</label>
                        <?php $deEnabled = (bool)($lexConfig['de']['enabled'] ?? true); ?>
                        <input type="hidden" name="de_enabled" value="<?= $deEnabled ? '1' : '0' ?>">
                        <button type="button" class="btn <?= $deEnabled ? 'btn-warning' : 'btn-success' ?>" data-lex-toggle="de_enabled">
                            <?= $deEnabled ? 'Disable' : 'Enable' ?>
                        </button>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Lookback (days)</label>
                            <input type="number" name="de_lookback_days" value="<?= (int)($lexConfig['de']['lookback_days'] ?? 90) ?>" min="1" max="365"
                                   style="width: 100%; padding: 6px 10px; border: 2px solid #000000; font-family: inherit; font-size: 14px; box-sizing: border-box;">
                        </div>
                        <div>
                            <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Max results</label>
                            <input type="number" name="de_limit" value="<?= (int)($lexConfig['de']['limit'] ?? 100) ?>" min="1" max="500"
                                   style="width: 100%; padding: 6px 10px; border: 2px solid #000000; font-family: inherit; font-size: 14px; box-sizing: border-box;">
                        </div>
                    </div>
                    
                    <div>
                        <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Notes</label>
                        <textarea name="de_notes" rows="2" placeholder="Optional notes about this source..."
                                  style="width: 100%; padding: 6px 10px; border: 2px solid #000000; font-family: inherit; font-size: 12px; resize: vertical; box-sizing: border-box;"><?= htmlspecialchars($lexConfig['de']['notes'] ?? '') ?></textarea>
                    </div>
                    
                    <div style="margin-top: 8px; font-size: 12px;">
                        Feed: <code style="font-size: 12px;"><?= htmlspecialchars($lexConfig['de']['feed_url'] ?? '') ?></code>
                    </div>
                    <div style="margin-top: 6px; font-size: 12px; line-height: 1.6;">
                        Source: Bundesgesetzblatt (BGBl) Teil I + II via RSS.
                        <a href="https://www.recht.bund.de/" target="_blank" rel="noopener" style="text-decoration: underline;">recht.bund.de</a>
                        &middot;
                        <a href="https://www.gesetze-im-internet.de/" target="_blank" rel="noopener" style="text-decoration: underline;">gesetze-im-internet.de</a>
                    </div>
                </div>

                <!-- JUS: CH_BGer Configuration -->
                <div style="margin-bottom: 24px; padding: 16px; border: 2px solid #000000; background: #fafafa;">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                        <label style="font-weight: 700; font-size: 18px;">⚖️ BGer (Bundesgericht)</label>
                        <?php $bgerEnabled = (bool)($lexConfig['ch_bger']['enabled'] ?? false); ?>
                        <input type="hidden" name="ch_bger_enabled" value="<?= $bgerEnabled ? '1' : '0' ?>">
                        <button type="button" class="btn <?= $bgerEnabled ? 'btn-warning' : 'btn-success' ?>" data-lex-toggle="ch_bger_enabled">
                            <?= $bgerEnabled ? 'Disable' : 'Enable' ?>
                        </button>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Lookback (days)</label>
                            <input type="number" name="ch_bger_lookback_days" value="<?= (int)($lexConfig['ch_bger']['lookback_days'] ?? 90) ?>" min="1" max="365"
                                   style="width: 100%; padding: 6px 10px; border: 2px solid #000000; font-family: inherit; font-size: 14px; box-sizing: border-box;">
                        </div>
                        <div>
                            <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Max results</label>
                            <input type="number" name="ch_bger_limit" value="<?= (int)($lexConfig['ch_bger']['limit'] ?? 100) ?>" min="1" max="500"
                                   style="width: 100%; padding: 6px 10px; border: 2px solid #000000; font-family: inherit; font-size: 14px; box-sizing: border-box;">
                        </div>
                    </div>
                    
                    <div>
                        <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Notes</label>
                        <textarea name="ch_bger_notes" rows="2" placeholder="Optional notes about this source..."
                                  style="width: 100%; padding: 6px 10px; border: 2px solid #000000; font-family: inherit; font-size: 12px; resize: vertical; box-sizing: border-box;"><?= htmlspecialchars($lexConfig['ch_bger']['notes'] ?? '') ?></textarea>
                    </div>
                    
                    <div style="margin-top: 8px; font-size: 12px;">
                        Source: Swiss Federal Supreme Court decisions via
                        <a href="https://entscheidsuche.ch" target="_blank" rel="noopener" style="text-decoration: underline;">entscheidsuche.ch</a>
                        &middot;
                        Index: <code style="font-size: 12px;">https://entscheidsuche.ch/docs/Index/CH_BGer/last</code>
                    </div>
                    <div style="margin-top: 6px; font-size: 12px; line-height: 1.6;">
                        Uses incremental index manifests — only fetches new/updated decisions per run.
                        <a href="https://entscheidsuche.ch/pdf/EntscheidsucheAPI.pdf" target="_blank" rel="noopener" style="text-decoration: underline;">API documentation</a>
                    </div>
                </div>

                <!-- JUS: CH_BGE Configuration -->
                <div style="margin-bottom: 24px; padding: 16px; border: 2px solid #000000; background: #fafafa;">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                        <label style="font-weight: 700; font-size: 18px;">⚖️ BGE (Leitentscheide)</label>
                        <?php $bgeEnabled = (bool)($lexConfig['ch_bge']['enabled'] ?? false); ?>
                        <input type="hidden" name="ch_bge_enabled" value="<?= $bgeEnabled ? '1' : '0' ?>">
                        <button type="button" class="btn <?= $bgeEnabled ? 'btn-warning' : 'btn-success' ?>" data-lex-toggle="ch_bge_enabled">
                            <?= $bgeEnabled ? 'Disable' : 'Enable' ?>
                        </button>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Lookback (days)</label>
                            <input type="number" name="ch_bge_lookback_days" value="<?= (int)($lexConfig['ch_bge']['lookback_days'] ?? 90) ?>" min="1" max="365"
                                   style="width: 100%; padding: 6px 10px; border: 2px solid #000000; font-family: inherit; font-size: 14px; box-sizing: border-box;">
                        </div>
                        <div>
                            <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Max results</label>
                            <input type="number" name="ch_bge_limit" value="<?= (int)($lexConfig['ch_bge']['limit'] ?? 50) ?>" min="1" max="500"
                                   style="width: 100%; padding: 6px 10px; border: 2px solid #000000; font-family: inherit; font-size: 14px; box-sizing: border-box;">
                        </div>
                    </div>
                    
                    <div>
                        <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Notes</label>
                        <textarea name="ch_bge_notes" rows="2" placeholder="Optional notes about this source..."
                                  style="width: 100%; padding: 6px 10px; border: 2px solid #000000; font-family: inherit; font-size: 12px; resize: vertical; box-sizing: border-box;"><?= htmlspecialchars($lexConfig['ch_bge']['notes'] ?? '') ?></textarea>
                    </div>
                    
                    <div style="margin-top: 8px; font-size: 12px;">
                        Source: Published leading decisions (Leitentscheide) from the Swiss Federal Supreme Court via
                        <a href="https://entscheidsuche.ch" target="_blank" rel="noopener" style="text-decoration: underline;">entscheidsuche.ch</a>
                        &middot;
                        Index: <code style="font-size: 12px;">https://entscheidsuche.ch/docs/Index/CH_BGE/last</code>
                    </div>
                </div>

                <!-- JUS: CH_BVGer Configuration -->
                <div style="margin-bottom: 24px; padding: 16px; border: 2px solid #000000; background: #fafafa;">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                        <label style="font-weight: 700; font-size: 18px;">⚖️ BVGer (Bundesverwaltungsgericht)</label>
                        <?php $bvgerEnabled = (bool)($lexConfig['ch_bvger']['enabled'] ?? false); ?>
                        <input type="hidden" name="ch_bvger_enabled" value="<?= $bvgerEnabled ? '1' : '0' ?>">
                        <button type="button" class="btn <?= $bvgerEnabled ? 'btn-warning' : 'btn-success' ?>" data-lex-toggle="ch_bvger_enabled">
                            <?= $bvgerEnabled ? 'Disable' : 'Enable' ?>
                        </button>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Lookback (days)</label>
                            <input type="number" name="ch_bvger_lookback_days" value="<?= (int)($lexConfig['ch_bvger']['lookback_days'] ?? 90) ?>" min="1" max="365"
                                   style="width: 100%; padding: 6px 10px; border: 2px solid #000000; font-family: inherit; font-size: 14px; box-sizing: border-box;">
                        </div>
                        <div>
                            <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Max results</label>
                            <input type="number" name="ch_bvger_limit" value="<?= (int)($lexConfig['ch_bvger']['limit'] ?? 100) ?>" min="1" max="500"
                                   style="width: 100%; padding: 6px 10px; border: 2px solid #000000; font-family: inherit; font-size: 14px; box-sizing: border-box;">
                        </div>
                    </div>
                    
                    <div>
                        <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Notes</label>
                        <textarea name="ch_bvger_notes" rows="2" placeholder="Optional notes about this source..."
                                  style="width: 100%; padding: 6px 10px; border: 2px solid #000000; font-family: inherit; font-size: 12px; resize: vertical; box-sizing: border-box;"><?= htmlspecialchars($lexConfig['ch_bvger']['notes'] ?? '') ?></textarea>
                    </div>
                    
                    <div style="margin-top: 8px; font-size: 12px;">
                        Source: Swiss Federal Administrative Court decisions via
                        <a href="https://entscheidsuche.ch" target="_blank" rel="noopener" style="text-decoration: underline;">entscheidsuche.ch</a>
                        &middot;
                        Index: <code style="font-size: 12px;">https://entscheidsuche.ch/docs/Index/CH_BVGer/last</code>
                    </div>
                </div>

                <!-- JUS: Banned Words Filter -->
                <div style="margin-bottom: 24px; padding: 16px; border: 2px solid #000000; background: #fafafa;">
                    <label style="font-weight: 700; font-size: 18px; display: block; margin-bottom: 8px;">🚫 Banned Words</label>
                    <p style="font-size: 12px; margin: 0 0 12px 0;">
                        Case law entries whose title contains any of these words will be hidden from the Jus page and the main feed. One word or phrase per line, case-insensitive.
                    </p>
                    <?php
                        $bannedWords = $lexConfig['jus_banned_words'] ?? [];
                        $bannedWordsText = is_array($bannedWords) ? implode("\n", $bannedWords) : '';
                    ?>
                    <textarea name="jus_banned_words" rows="5" placeholder="e.g.&#10;Asyl&#10;Sozialhilfe&#10;Familiennachzug"
                              style="width: 100%; padding: 8px 10px; border: 2px solid #000000; font-family: inherit; font-size: 13px; resize: vertical; box-sizing: border-box;"><?= htmlspecialchars($bannedWordsText) ?></textarea>
                    <div style="margin-top: 6px; font-size: 12px; color: #666;">
                        <?= count(array_filter(is_array($bannedWords) ? $bannedWords : [], 'strlen')) ?> word(s) active
                    </div>
                </div>

            </form>

            <!-- Config file management -->
            <div style="margin-top: 20px; padding-top: 16px; border-top: 2px solid #000000;">
                <h3 style="margin-top: 0; margin-bottom: 10px;">Config File</h3>
                <p style="font-size: 12px; margin-bottom: 12px;">
                    Download the current configuration as JSON, or upload a config file to apply.
                </p>
                <div style="display: flex; gap: 12px; align-items: flex-start; flex-wrap: wrap;">
                    <a href="?action=download_lex_config" class="btn" style="text-decoration: none;">
                        Download lex_config.json
                    </a>
                    <form method="POST" action="<?= getBasePath() ?>/index.php?action=upload_lex_config" enctype="multipart/form-data" style="display: flex; gap: 8px; align-items: center;">
                        <input type="file" name="lex_config_file" accept=".json,application/json" style="font-size: 12px; font-family: inherit;">
                        <button type="submit" class="btn">Upload</button>
                    </form>
                </div>
            </div>
        </section>
        <?php endif; ?>

    </div>

    <script>
        // Feed tag management (same as feeds.php)
        (function() {
            // Tags are embedded server-side — no fetch() needed
            let allTags = <?= json_encode(array_values($allTags ?? [])) ?>;
            let allEmailTags = <?= json_encode(array_values($allEmailTags ?? [])) ?>;
            let allSubstackTags = <?= json_encode(array_values($allSubstackTags ?? [])) ?>;
            let currentSuggestions = [];
            let activeInput = null;
            let suggestionList = null;
            
            // Create suggestion dropdown
            function createSuggestionList() {
                const list = document.createElement('ul');
                list.className = 'feed-tag-suggestions';
                list.style.display = 'none';
                document.body.appendChild(list);
                return list;
            }
            
            suggestionList = createSuggestionList();
            
            // Show suggestions
            function showSuggestions(input, suggestions) {
                if (!suggestions.length) {
                    suggestionList.style.display = 'none';
                    return;
                }
                
                suggestionList.innerHTML = '';
                suggestions.forEach(tag => {
                    const li = document.createElement('li');
                    li.textContent = tag;
                    li.addEventListener('click', () => {
                        input.value = tag;
                        input.dispatchEvent(new Event('input'));
                        hideSuggestions();
                    });
                    suggestionList.appendChild(li);
                });
                
                const rect = input.getBoundingClientRect();
                suggestionList.style.top = (rect.bottom + window.scrollY) + 'px';
                suggestionList.style.left = (rect.left + window.scrollX) + 'px';
                suggestionList.style.width = rect.width + 'px';
                suggestionList.style.display = 'block';
            }
            
            function hideSuggestions() {
                suggestionList.style.display = 'none';
            }
            
            // Filter tags based on input
            function getTagSource(input) {
                if (input && input.classList.contains('all-email-tag-input')) return allEmailTags;
                if (input && input.classList.contains('all-substack-tag-input')) return allSubstackTags;
                return allTags;
            }
            
            function filterTags(query, input) {
                const tagsToSearch = getTagSource(input);
                if (!query || query === 'unsortiert') {
                    return [];
                }
                const lowerQuery = query.toLowerCase();
                return tagsToSearch.filter(tag => 
                    tag.toLowerCase().includes(lowerQuery) && tag !== query
                ).slice(0, 5);
            }
            
            // Check if tag is new
            function isNewTag(tag, input) {
                const tagsToSearch = getTagSource(input);
                return tag && tag !== 'unsortiert' && !tagsToSearch.includes(tag);
            }
            
            // Update indicator
            function updateIndicator(input, value) {
                const indicator = input.parentElement.querySelector('.feed-tag-indicator');
                if (indicator) {
                    if (isNewTag(value, input)) {
                        indicator.textContent = 'new';
                        indicator.className = 'feed-tag-indicator feed-tag-new';
                    } else {
                        indicator.textContent = '';
                        indicator.className = 'feed-tag-indicator';
                    }
                }
            }
            
            // Handle feed tag inputs (exclude all-tag-input, all-email-tag-input, all-substack-tag-input which have their own handlers)
            document.querySelectorAll('.feed-tag-input:not(.all-tag-input):not(.all-email-tag-input):not(.all-substack-tag-input)').forEach(input => {
                input.addEventListener('focus', function() {
                    activeInput = this;
                    const value = this.value.trim();
                    if (value && value !== 'unsortiert') {
                        const suggestions = filterTags(value, this);
                        showSuggestions(this, suggestions);
                    }
                    updateIndicator(this, value);
                });
                
                input.addEventListener('input', function() {
                    const value = this.value.trim();
                    updateIndicator(this, value);
                    
                    if (value && value !== 'unsortiert') {
                        const suggestions = filterTags(value, this);
                        showSuggestions(this, suggestions);
                    } else {
                        hideSuggestions();
                    }
                });
                
                input.addEventListener('blur', function() {
                    setTimeout(() => hideSuggestions(), 200);
                });
                
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        const value = this.value.trim();
                        
                        if (!value || value === '') {
                            this.value = this.dataset.originalTag || 'unsortiert';
                            updateIndicator(this, this.value);
                            hideSuggestions();
                            return;
                        }
                        
                        const feedId = this.dataset.feedId;
                        const formData = new FormData();
                        formData.append('feed_id', feedId);
                        formData.append('tag', value);
                        
                        this.classList.add('feed-tag-saving');
                        
                        fetch('?action=update_feed_tag', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                this.dataset.originalTag = value;
                                this.classList.remove('feed-tag-saving');
                                this.classList.add('feed-tag-saved');
                                setTimeout(() => {
                                    this.classList.remove('feed-tag-saved');
                                }, 2000);
                                this.blur();
                                hideSuggestions();
                                // Update local tag list
                                if (!allTags.includes(value)) { allTags.push(value); allTags.sort(); }
                            } else {
                                this.classList.remove('feed-tag-saving');
                                alert('Error: ' + (data.error || 'Failed to update tag'));
                            }
                        })
                        .catch(err => {
                            console.error('Error updating tag:', err);
                            this.classList.remove('feed-tag-saving');
                            alert('Error updating tag');
                        });
                    } else if (e.key === 'Escape') {
                        this.value = this.dataset.originalTag || 'unsortiert';
                        updateIndicator(this, this.value);
                        hideSuggestions();
                        this.blur();
                    }
                });
            });
            
            // Close suggestions when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.feed-tag-input-wrapper') && !e.target.closest('.feed-tag-suggestions')) {
                    hideSuggestions();
                }
            });
        })();
        
        // Handle "All Tags" editable inputs (RSS, Email, and Substack tags)
        document.querySelectorAll('.all-tag-input, .all-email-tag-input, .all-substack-tag-input').forEach(input => {
            input.addEventListener('focus', function() {
                activeInput = this;
                const value = this.value.trim();
                if (value && value !== 'unsortiert') {
                    const suggestions = filterTags(value, this);
                    showSuggestions(this, suggestions);
                }
                updateIndicator(this, value);
            });
            
            input.addEventListener('input', function() {
                const value = this.value.trim();
                updateIndicator(this, value);
                
                if (value && value !== 'unsortiert') {
                    const suggestions = filterTags(value, this);
                    showSuggestions(this, suggestions);
                } else {
                    hideSuggestions();
                }
            });
            
            input.addEventListener('blur', function() {
                setTimeout(() => hideSuggestions(), 200);
            });
            
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const value = this.value.trim();
                    const oldTag = this.dataset.tagName;
                    
                    // Validation: cannot be empty
                    if (!value || value === '') {
                        this.value = this.dataset.originalTag;
                        updateIndicator(this, this.value);
                        hideSuggestions();
                        return;
                    }
                    
                    // If unchanged, do nothing
                    if (value === oldTag) {
                        this.blur();
                        hideSuggestions();
                        return;
                    }
                    
                    // Determine tag type: email, substack, or RSS
                    const isEmailTag = this.classList.contains('all-email-tag-input');
                    const isSubstackTag = this.classList.contains('all-substack-tag-input');
                    const action = isEmailTag ? 'rename_email_tag' : (isSubstackTag ? 'rename_substack_tag' : 'rename_tag');
                    
                    // Rename tag
                    const formData = new FormData();
                    formData.append('old_tag', oldTag);
                    formData.append('new_tag', value);
                    
                    this.classList.add('feed-tag-saving');
                    
                    fetch('?action=' + action, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            this.dataset.originalTag = value;
                            this.dataset.tagName = value;
                            
                            this.classList.remove('feed-tag-saving');
                            this.classList.add('feed-tag-saved');
                            
                            setTimeout(() => {
                                this.classList.remove('feed-tag-saved');
                            }, 2000);
                            
                            this.blur();
                            hideSuggestions();
                            
                            // Update local tag list (replace old → new)
                            const targetArr = isEmailTag ? allEmailTags : (isSubstackTag ? allSubstackTags : allTags);
                            const oldIdx = targetArr.indexOf(oldTag);
                            if (oldIdx !== -1) targetArr.splice(oldIdx, 1);
                            if (!targetArr.includes(value)) targetArr.push(value);
                            targetArr.sort();
                        } else {
                            this.classList.remove('feed-tag-saving');
                            alert('Error: ' + (data.error || 'Failed to rename tag'));
                            this.value = this.dataset.originalTag;
                            updateIndicator(this, this.value);
                        }
                    })
                    .catch(err => {
                        console.error('Error renaming tag:', err);
                        this.classList.remove('feed-tag-saving');
                        alert('Error renaming tag');
                        this.value = this.dataset.originalTag;
                        updateIndicator(this, this.value);
                    });
                } else if (e.key === 'Escape') {
                    this.value = this.dataset.originalTag;
                    updateIndicator(this, this.value);
                    hideSuggestions();
                    this.blur();
                }
            });
        });
        
        // Handle sender tag updates
        document.querySelectorAll('.tag-input:not(.feed-tag-input)').forEach(function(input) {
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const value = this.value.trim();
                    const senderEmail = this.dataset.senderEmail;
                    const originalTag = this.dataset.originalTag || '';
                    
                    // If unchanged, do nothing
                    if (value === originalTag) {
                        return;
                    }
                    
                    // Save tag
                    const formData = new FormData();
                    formData.append('from_email', senderEmail);
                    formData.append('tag', value);
                    
                    // Add saving state
                    this.classList.add('tag-saving');
                    
                    fetch('?action=update_sender_tag', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            this.classList.remove('tag-saving');
                            this.classList.add('tag-saved');
                            this.dataset.originalTag = value;
                            
                            // Remove saved state after feedback
                            setTimeout(() => {
                                this.classList.remove('tag-saved');
                            }, 2000);
                        } else {
                            this.classList.remove('tag-saving');
                            alert('Error: ' + (data.error || 'Failed to update tag'));
                            this.value = originalTag;
                        }
                    })
                    .catch(error => {
                        this.classList.remove('tag-saving');
                        alert('Error updating tag');
                        this.value = originalTag;
                    });
                }
            });
            
            input.addEventListener('blur', function() {
                // Reset to original if empty and user didn't save
                if (this.value.trim() === '' && this.dataset.originalTag) {
                    this.value = this.dataset.originalTag;
                }
            });
        });

        // Lex settings: auto-save on change and button-based enable/disable toggles.
        (function() {
            const lexForm = document.getElementById('lex-config-form');
            if (!lexForm) return;

            let isSubmitting = false;
            const submitLexForm = () => {
                if (isSubmitting) return;
                isSubmitting = true;
                lexForm.submit();
            };

            lexForm.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach((field) => {
                field.addEventListener('change', submitLexForm);
            });

            lexForm.querySelectorAll('[data-lex-toggle]').forEach((button) => {
                button.addEventListener('click', function() {
                    const inputName = this.dataset.lexToggle;
                    const hiddenInput = lexForm.querySelector('input[name="' + inputName + '"]');
                    if (!hiddenInput) return;
                    hiddenInput.value = hiddenInput.value === '1' ? '0' : '1';
                    submitLexForm();
                });
            });
        })();
    </script>
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
