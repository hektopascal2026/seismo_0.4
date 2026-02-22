<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beta - Seismo</title>
    <link rel="stylesheet" href="<?= getBasePath() ?>/assets/css/style.css">
    <style>
        .beta-section {
            margin-bottom: 24px;
            padding-bottom: 18px;
            border-bottom: 2px solid #000000;
        }

        .beta-section:last-child {
            border-bottom: none;
        }

        .beta-section h2 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #000000;
        }

        .beta-section p {
            font-size: 12px;
            line-height: 1.6;
            color: #000000;
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
                    Beta
                </span>
                <span class="top-bar-subtitle">Experimental and in-progress pages</span>
            </div>
            <div class="top-bar-actions">
                <a href="?action=refresh_all&from=beta" class="top-bar-btn" title="Refresh all sources"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/></svg></a>
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
            <a href="?action=settings" class="nav-link">Settings</a>
            <a href="?action=about" class="nav-link">About</a>
            <a href="?action=beta" class="nav-link active">Beta</a>
        </nav>

        <section class="beta-section">
            <h2>AI View Generator</h2>
            <p style="margin-bottom: 12px;">Configure what data goes into the AI-readable unified feed, then generate it.</p>

            <form method="GET" action="" id="ai-generator-form">
                <input type="hidden" name="action" value="ai_view">

                <!-- Sources -->
                <div style="margin-bottom: 14px;">
                    <label style="display: block; font-weight: 700; margin-bottom: 6px; font-size: 13px;">Sources</label>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <?php
                        $sourceOptions = [
                            'rss' => 'RSS',
                            'substack' => 'Substack',
                            'email' => 'Email',
                            'lex' => 'Lex',
                            'parl_mm' => 'Parl MM',
                            'jus' => 'Jus',
                            'scraper' => 'Scraper',
                            'calendar' => 'Calendar',
                        ];
                        foreach ($sourceOptions as $key => $label): ?>
                        <label style="display: flex; align-items: center; gap: 4px; font-size: 12px; cursor: pointer;">
                            <input type="checkbox" name="sources[]" value="<?= $key ?>" checked>
                            <?= $label ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Date range -->
                <div style="margin-bottom: 14px;">
                    <label style="display: block; font-weight: 700; margin-bottom: 6px; font-size: 13px;">Date range</label>
                    <select name="since" style="padding: 6px 10px; border: 2px solid #000; font-family: inherit; font-size: 12px;">
                        <option value="24h">Last 24 hours</option>
                        <option value="3d">Last 3 days</option>
                        <option value="7d" selected>Last 7 days</option>
                        <option value="30d">Last 30 days</option>
                        <option value="90d">Last 90 days</option>
                        <option value="all">All time</option>
                    </select>
                </div>

                <!-- Magnitu score filter -->
                <div style="margin-bottom: 14px;">
                    <label style="display: block; font-weight: 700; margin-bottom: 6px; font-size: 13px;">Magnitu labels</label>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <?php
                        $labelOptions = [
                            'investigation_lead' => 'Investigation Lead',
                            'important' => 'Important',
                            'background' => 'Background',
                            'noise' => 'Noise',
                            'unscored' => 'Unscored',
                        ];
                        foreach ($labelOptions as $key => $label): ?>
                        <label style="display: flex; align-items: center; gap: 4px; font-size: 12px; cursor: pointer;">
                            <input type="checkbox" name="labels[]" value="<?= $key ?>" checked>
                            <?= $label ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Minimum score -->
                <div style="margin-bottom: 14px;">
                    <label style="display: block; font-weight: 700; margin-bottom: 6px; font-size: 13px;">Minimum score <span style="font-weight: 400; font-size: 11px;">(0-100, leave empty for no filter)</span></label>
                    <input type="number" name="min_score" min="0" max="100" placeholder="e.g. 50" style="width: 100px; padding: 6px 10px; border: 2px solid #000; font-family: inherit; font-size: 12px;">
                </div>

                <!-- Priority keywords -->
                <div style="margin-bottom: 14px;">
                    <label style="display: block; font-weight: 700; margin-bottom: 6px; font-size: 13px;">Priority keywords <span style="font-weight: 400; font-size: 11px;">(comma-separated — matching entries are boosted to the top)</span></label>
                    <input type="text" name="keywords" placeholder="e.g. regulation, investigation, compliance" style="width: 100%; max-width: 500px; padding: 6px 10px; border: 2px solid #000; font-family: inherit; font-size: 12px;">
                </div>

                <!-- Max entries -->
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-weight: 700; margin-bottom: 6px; font-size: 13px;">Max entries</label>
                    <input type="number" name="limit" min="1" max="1000" value="100" style="width: 100px; padding: 6px 10px; border: 2px solid #000; font-family: inherit; font-size: 12px;">
                </div>

                <button type="submit" class="btn btn-primary">Generate AI View</button>
            </form>
        </section>

        <section class="beta-section">
            <h2>Notes</h2>
            <p>
                This page collects beta or experimental links and features.
            </p>
            <p>
                Last updated: <?= $lastChangeDate ?>
            </p>
        </section>
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
