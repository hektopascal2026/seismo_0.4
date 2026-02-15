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
            <a href="?action=settings" class="nav-link">Settings</a>
            <a href="?action=about" class="nav-link">About</a>
            <a href="?action=beta" class="nav-link active">Beta</a>
        </nav>

        <section class="beta-section">
            <h2>AI Links</h2>
            <p>
                <a href="?action=ai_view_unified" class="btn btn-secondary">Open ai_view_unified</a>
            </p>
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
