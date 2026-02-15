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
                <button type="button" class="top-bar-btn" id="menuToggle" title="Menu"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
            </div>
        </div>

        <nav class="nav-drawer" id="navDrawer">
            <a href="?action=index" class="nav-link">Feed</a>
            <a href="?action=magnitu" class="nav-link">Magnitu</a>
            <a href="?action=feeds" class="nav-link">RSS</a>
            <a href="?action=lex" class="nav-link">Lex</a>
            <a href="?action=mail" class="nav-link">Mail</a>
            <a href="?action=substack" class="nav-link">Substack</a>
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

        <!-- RSS Section -->
        <section class="settings-section">
            <h2 style="background-color: #add8e6; padding: 8px 14px; display: inline-block;">RSS</h2>
            
            <!-- Add Feed Section -->
            <div class="add-feed-section" style="margin-bottom: 16px;">
                <form method="POST" action="?action=add_feed" class="add-feed-form">
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
                                    <a href="?action=toggle_feed&id=<?= $feed['id'] ?>&from=settings" class="btn <?= $feed['disabled'] ? 'btn-success' : 'btn-warning' ?>">
                                        <?= $feed['disabled'] ? 'Enable' : 'Disable' ?>
                                    </a>
                                    <a href="?action=delete_feed&id=<?= $feed['id'] ?>&from=settings" 
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
            <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #e0e0e0;">
                <h3 style="margin-top: 0; margin-bottom: 8px;">Config File</h3>
                <p style="font-size: 12px; margin-bottom: 12px;">
                    Download your RSS feeds as JSON, or upload a config file to add/update feeds in bulk.
                </p>
                <div style="display: flex; gap: 12px; align-items: flex-start; flex-wrap: wrap;">
                    <a href="?action=download_rss_config" class="btn" style="text-decoration: none;">
                        Download rss_feeds.json
                    </a>
                    <form method="POST" action="?action=upload_rss_config" enctype="multipart/form-data" style="display: flex; gap: 8px; align-items: center;">
                        <input type="file" name="rss_config_file" accept=".json,application/json" style="font-size: 12px; font-family: inherit;">
                        <button type="submit" class="btn">Upload</button>
                    </form>
                </div>
            </div>
        </section>

        <!-- Mail Section -->
        <section class="settings-section">
            <h2 style="background-color: #FFDBBB; padding: 8px 14px; display: inline-block;">Mail</h2>
            
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
                                    <form method="POST" action="?action=toggle_sender" style="margin: 0;">
                                        <input type="hidden" name="email" value="<?= htmlspecialchars($sender['email']) ?>">
                                        <button type="submit" class="btn <?= $sender['disabled'] ? 'btn-success' : 'btn-warning' ?>">
                                            <?= $sender['disabled'] ? 'Enable' : 'Disable' ?>
                                        </button>
                                    </form>
                                    <form method="POST" action="?action=delete_sender" style="margin: 0;">
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

        <!-- Substack Section -->
        <section class="settings-section">
            <h2 style="background-color: #C5B4D1; padding: 8px 14px; display: inline-block;">Substack</h2>
            
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
                                    <a href="?action=toggle_feed&id=<?= $feed['id'] ?>&from=settings" class="btn <?= $feed['disabled'] ? 'btn-success' : 'btn-warning' ?>">
                                        <?= $feed['disabled'] ? 'Enable' : 'Disable' ?>
                                    </a>
                                    <a href="?action=delete_feed&id=<?= $feed['id'] ?>&from=settings" 
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
            <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #e0e0e0;">
                <h3 style="margin-top: 0; margin-bottom: 8px;">Config File</h3>
                <p style="font-size: 12px; margin-bottom: 12px;">
                    Download your Substack subscriptions as JSON, or upload a config file to add/update them in bulk.
                </p>
                <div style="display: flex; gap: 12px; align-items: flex-start; flex-wrap: wrap;">
                    <a href="?action=download_substack_config" class="btn" style="text-decoration: none;">
                        Download substack_feeds.json
                    </a>
                    <form method="POST" action="?action=upload_substack_config" enctype="multipart/form-data" style="display: flex; gap: 8px; align-items: center;">
                        <input type="file" name="substack_config_file" accept=".json,application/json" style="font-size: 12px; font-family: inherit;">
                        <button type="submit" class="btn">Upload</button>
                    </form>
                </div>
            </div>
        </section>

        <!-- Lex Section -->
        <section class="settings-section" id="lex-settings">
            <h2 style="background-color: #f5f562; padding: 8px 14px; display: inline-block;">Lex</h2>
            <p style="margin: 8px 0 16px; font-size: 12px;">
                Configure how Seismo queries EU and Swiss legislative databases via SPARQL.
            </p>

            <form method="POST" action="?action=save_lex_config">
                <!-- EU Configuration -->
                <div style="margin-bottom: 24px; padding: 16px; border: 2px solid #000000; background: #fafafa;">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                        <label style="font-weight: 700; font-size: 18px;">🇪🇺 EUR-Lex</label>
                        <label style="display: flex; align-items: center; gap: 6px; font-size: 12px; cursor: pointer;">
                            <input type="checkbox" name="eu_enabled" value="1" <?= ($lexConfig['eu']['enabled'] ?? true) ? 'checked' : '' ?>>
                            Enabled
                        </label>
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
                        <label style="display: flex; align-items: center; gap: 6px; font-size: 12px; cursor: pointer;">
                            <input type="checkbox" name="ch_enabled" value="1" <?= ($lexConfig['ch']['enabled'] ?? true) ? 'checked' : '' ?>>
                            Enabled
                        </label>
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
                                <span style="display: inline-block; background: #f0f0f0; padding: 2px 8px; margin: 2px 4px 2px 0; border: 1px solid #ddd; font-family: monospace; font-size: 12px;">
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

                <div style="display: flex; gap: 12px; align-items: center;">
                    <button type="submit" class="btn btn-primary">Save Configuration</button>
                </div>
            </form>

            <!-- Config file management -->
            <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid #e0e0e0;">
                <h3 style="margin-top: 0; margin-bottom: 10px;">Config File</h3>
                <p style="font-size: 12px; margin-bottom: 12px;">
                    Download the current configuration as JSON, or upload a config file to apply.
                </p>
                <div style="display: flex; gap: 12px; align-items: flex-start; flex-wrap: wrap;">
                    <a href="?action=download_lex_config" class="btn" style="text-decoration: none;">
                        Download lex_config.json
                    </a>
                    <form method="POST" action="?action=upload_lex_config" enctype="multipart/form-data" style="display: flex; gap: 8px; align-items: center;">
                        <input type="file" name="lex_config_file" accept=".json,application/json" style="font-size: 12px; font-family: inherit;">
                        <button type="submit" class="btn">Upload</button>
                    </form>
                </div>
            </div>
        </section>
        <!-- Magnitu Section -->
        <section class="settings-section" id="magnitu-settings">
            <h2 style="background-color: #FF6B6B; padding: 8px 14px; display: inline-block;">Magnitu</h2>
            <p style="margin: 8px 0 16px; font-size: 12px;">
                Magnitu is a separate app that learns what feed entries are relevant to you as a journalist.
                It connects to Seismo via API to fetch entries, train a model, and push relevance scores back.
            </p>

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
                        <form method="POST" action="?action=regenerate_magnitu_key" style="margin: 0;">
                            <button type="submit" class="btn" onclick="return confirm('Regenerate API key? Magnitu will need the new key.');">Regenerate</button>
                        </form>
                    </div>
                    <div style="font-size: 11px; margin-top: 4px; color: #666;">Click the key to copy. Use this in Magnitu's settings to connect.</div>
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
                    <div style="text-align: center; padding: 10px; border: 1px solid #d0d0d0;">
                        <div style="font-size: 20px; font-weight: 700;"><?= $magnituScoreStats['total'] ?></div>
                        <div style="font-size: 11px;">Entries Scored</div>
                    </div>
                    <div style="text-align: center; padding: 10px; border: 1px solid #d0d0d0;">
                        <div style="font-size: 20px; font-weight: 700;"><?= $magnituScoreStats['magnitu'] ?></div>
                        <div style="font-size: 11px;">By Magnitu (full model)</div>
                    </div>
                    <div style="text-align: center; padding: 10px; border: 1px solid #d0d0d0;">
                        <div style="font-size: 20px; font-weight: 700;"><?= $magnituScoreStats['recipe'] ?></div>
                        <div style="font-size: 11px;">By Recipe (keywords)</div>
                    </div>
                </div>
                
                <?php if (!empty($magnituConfig['last_sync_at'])): ?>
                    <div style="font-size: 12px; margin-top: 12px;">
                        Last sync: <strong><?= htmlspecialchars($magnituConfig['last_sync_at']) ?></strong>
                        &middot; Recipe version: <strong><?= htmlspecialchars($magnituConfig['recipe_version'] ?? '0') ?></strong>
                    </div>
                <?php else: ?>
                    <div style="font-size: 12px; margin-top: 12px; color: #666;">
                        No sync yet. Connect Magnitu using the API key and URL above.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Scoring Settings -->
            <form method="POST" action="?action=save_magnitu_config">
                <div style="margin-bottom: 24px; padding: 16px; border: 2px solid #000000; background: #fafafa;">
                    <h3 style="margin-top: 0; margin-bottom: 12px;">Scoring Settings</h3>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Alert Threshold (0.0 - 1.0)</label>
                            <input type="number" name="alert_threshold" 
                                   value="<?= htmlspecialchars($magnituConfig['alert_threshold'] ?? '0.75') ?>" 
                                   min="0" max="1" step="0.05"
                                   style="width: 100%; padding: 6px 10px; border: 2px solid #000000; font-family: inherit; font-size: 14px; box-sizing: border-box;">
                            <div style="font-size: 11px; margin-top: 4px; color: #666;">Entries scoring above this are highlighted as alerts in the feed.</div>
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
                <form method="POST" action="?action=clear_magnitu_scores">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Clear all Magnitu scores and recipe? This cannot be undone.');">
                        Clear All Scores
                    </button>
                </form>
            </div>
        </section>
    </div>

    <script>
        // Feed tag management (same as feeds.php)
        (function() {
            let allTags = [];
            let allEmailTags = [];
            let allSubstackTags = [];
            let currentSuggestions = [];
            let activeInput = null;
            let suggestionList = null;
            
            // Load all tags on page load
            fetch('?action=api_tags')
                .then(response => response.json())
                .then(tags => {
                    allTags = tags;
                })
                .catch(err => console.error('Error loading tags:', err));
            
            // Load all email tags on page load
            fetch('?action=api_email_tags')
                .then(response => response.json())
                .then(tags => {
                    allEmailTags = tags;
                })
                .catch(err => console.error('Error loading email tags:', err));
            
            // Load all substack tags on page load
            fetch('?action=api_substack_tags')
                .then(response => response.json())
                .then(tags => {
                    allSubstackTags = tags;
                })
                .catch(err => console.error('Error loading substack tags:', err));
            
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
                                // Refresh both RSS and Substack tags
                                fetch('?action=api_tags').then(r => r.json()).then(t => { allTags = t; });
                                fetch('?action=api_substack_tags').then(r => r.json()).then(t => { allSubstackTags = t; });
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
                            
                            // Reload relevant tags list
                            if (isEmailTag) {
                                fetch('?action=api_email_tags').then(r => r.json()).then(t => { allEmailTags = t; });
                            } else if (isSubstackTag) {
                                fetch('?action=api_substack_tags').then(r => r.json()).then(t => { allSubstackTags = t; });
                            } else {
                                fetch('?action=api_tags').then(r => r.json()).then(t => { allTags = t; });
                            }
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
