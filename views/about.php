<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About - Seismo</title>
    <link rel="stylesheet" href="<?= getBasePath() ?>/assets/css/style.css">
    <style>
        .about-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #000000;
        }
        
        .about-section:last-child {
            border-bottom: none;
        }
        
        .about-section h2 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 12px;
            color: #000000;
        }
        
        .about-section p,
        .about-section li {
            font-size: 12px;
            line-height: 1.6;
            color: #000000;
        }
        
        .about-section ul {
            list-style: none;
            padding: 0;
        }
        
        .about-section ul li {
            padding: 6px 0;
            border-bottom: 1px solid #eeeeee;
        }
        
        .about-section ul li:last-child {
            border-bottom: none;
        }
        
        .about-source-label {
            display: inline-block;
            padding: 2px 8px;
            font-size: 12px;
            font-weight: 600;
            margin-right: 8px;
            vertical-align: middle;
        }

        .about-version {
            font-family: monospace;
            font-size: 12px;
            color: #000000;
        }

        .about-link {
            color: #000000;
            text-decoration: underline;
            text-underline-offset: 2px;
        }

        .about-link:hover {
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
                    About
                </span>
                <span class="top-bar-subtitle">Legislative and media monitoring tool</span>
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
            <a href="?action=about" class="nav-link active">About</a>
            <a href="?action=beta" class="nav-link">Beta</a>
        </nav>

        <!-- Overview -->
        <section class="about-section">
            <h2>What is Seismo?</h2>
            <p>
                Seismo is a self-hosted monitoring dashboard that aggregates information from multiple sources into a single feed.
                It tracks RSS feeds, email newsletters, Substack publications, and legislative changes from both the European Union and Switzerland — helping you stay informed about policy, regulation, and media that matter.
            </p>
        </section>

        <!-- Sources -->
        <section class="about-section">
            <h2>Sources</h2>
            <ul>
                <li>
                    <span class="about-source-label" style="background-color: #add8e6;">RSS</span>
                    Standard RSS/Atom feeds — news, blogs, institutional publications
                </li>
                <li>
                    <span class="about-source-label" style="background-color: #C5B4D1;">Substack</span>
                    Substack newsletters via their RSS feeds
                </li>
                <li>
                    <span class="about-source-label" style="background-color: #FFDBBB;">Mail</span>
                    Email newsletters stored in the database
                </li>
                <li>
                    <span class="about-source-label" style="background-color: #f5f562;">🇪🇺 EU Lex</span>
                    EU legislation via SPARQL queries to the <a href="https://publications.europa.eu/webapi/rdf/sparql" class="about-link" target="_blank" rel="noopener">EU CELLAR</a> endpoint (CDM ontology) — regulations, directives, and decisions
                </li>
                <li>
                    <span class="about-source-label" style="background-color: #f5f562;">🇨🇭 CH Lex</span>
                    Swiss federal legislation via SPARQL queries to <a href="https://fedlex.data.admin.ch/sparqlendpoint" class="about-link" target="_blank" rel="noopener">Fedlex</a> (JOLux ontology) — Bundesgesetze, Verordnungen, Bundesbeschlüsse, and international treaties
                </li>
            </ul>
        </section>

        <!-- Tech Stack -->
        <section class="about-section">
            <h2>Technical Details</h2>
            <ul>
                <li><strong>Language:</strong> PHP <?= phpversion() ?></li>
                <li><strong>Database:</strong> MySQL / MariaDB</li>
                <li><strong>RSS parsing:</strong> <a href="https://github.com/simplepie/simplepie" class="about-link" target="_blank" rel="noopener">SimplePie</a></li>
                <li><strong>Email parsing:</strong> <a href="https://github.com/php-mime-mail-parser/php-mime-mail-parser" class="about-link" target="_blank" rel="noopener">PHP MIME Mail Parser</a></li>
                <li><strong>SPARQL / RDF:</strong> <a href="https://github.com/easyrdf/easyrdf" class="about-link" target="_blank" rel="noopener">EasyRdf</a></li>
                <li><strong>Frontend:</strong> Vanilla HTML/CSS/JS — no framework, no build step</li>
            </ul>
        </section>

        <!-- Data -->
        <section class="about-section">
            <h2>Data</h2>
            <ul>
                <li><strong>RSS feeds:</strong> <?= number_format($stats['feeds'] ?? 0) ?> feeds, <?= number_format($stats['feed_items'] ?? 0) ?> items</li>
                <li><strong>Emails:</strong> <?= number_format($stats['emails'] ?? 0) ?> messages</li>
                <li><strong>Lex items:</strong> <?= number_format($stats['lex_eu'] ?? 0) ?> EU, <?= number_format($stats['lex_ch'] ?? 0) ?> CH</li>
            </ul>
        </section>

        <!-- Credits -->
        <section class="about-section">
            <h2>Credits</h2>
            <p>
                Built by <a href="https://hektopascal.org" class="about-link" target="_blank" rel="noopener">hektopascal.org</a>.
            </p>
            <p class="about-version" style="margin-top: 8px;">
                Version 0.3 · Last updated: <?= $lastChangeDate ?>
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
