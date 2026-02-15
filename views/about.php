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
                <a href="?action=refresh_all&from=about" class="top-bar-btn" title="Refresh all sources"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/></svg></a>
                <button type="button" class="top-bar-btn" id="menuToggle" title="Menu"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
            </div>
        </div>

        <nav class="nav-drawer" id="navDrawer">
            <a href="?action=index" class="nav-link">Feed</a>
            <a href="?action=magnitu" class="nav-link">Magnitu</a>
            <a href="?action=feeds" class="nav-link">RSS</a>
            <a href="?action=lex" class="nav-link">Lex</a>
            <a href="?action=jus" class="nav-link">Jus</a>
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
                It tracks RSS feeds, email newsletters, Substack publications, legislative changes from the European Union, Switzerland, and Germany, and Swiss case law — helping you stay informed about policy, regulation, jurisprudence, and media that matter.
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
                <li>
                    <span class="about-source-label" style="background-color: #f5f562;">🇩🇪 DE Lex</span>
                    German federal legislation via RSS from <a href="https://www.recht.bund.de/" class="about-link" target="_blank" rel="noopener">recht.bund.de</a> — Bundesgesetzblatt Teil I + II (Gesetze, Verordnungen, Bekanntmachungen)
                </li>
                <li>
                    <span class="about-source-label" style="background-color: #f5f562;">⚖️ BGer Jus</span>
                    Swiss Federal Supreme Court decisions via <a href="https://entscheidsuche.ch" class="about-link" target="_blank" rel="noopener">entscheidsuche.ch</a> — incremental sync via index manifests
                </li>
                <li>
                    <span class="about-source-label" style="background-color: #f5f562;">⚖️ BGE Jus</span>
                    Published leading decisions (Leitentscheide / BGE) from the Swiss Federal Supreme Court via <a href="https://entscheidsuche.ch" class="about-link" target="_blank" rel="noopener">entscheidsuche.ch</a>
                </li>
            </ul>
        </section>

        <!-- Magnitu -->
        <section class="about-section">
            <h2>Magnitu</h2>
            <p>
                Magnitu is Seismo's companion scoring engine — a local Python application that learns which entries are relevant to you and pushes relevance scores back to Seismo via API.
            </p>
            <p style="margin-top: 8px;">
                Every entry in Seismo (feed items, emails, lex items) can be scored on a four-level scale:
            </p>
            <ul>
                <li><strong>Investigation Lead</strong> — could be the starting point of an investigative story</li>
                <li><strong>Important</strong> — significant development you should be aware of</li>
                <li><strong>Background</strong> — contextual information, worth archiving</li>
                <li><strong>Noise</strong> — not relevant to your work</li>
            </ul>
            <p style="margin-top: 8px;">
                Scoring works at two levels. The <strong>recipe scorer</strong> runs inside Seismo itself — it uses a keyword-based recipe with source weights and class weights to score new entries immediately during refresh. The <strong>Magnitu model</strong> is a full ML classifier that trains on your labels and pushes higher-quality scores via the API, overriding recipe scores when available.
            </p>

            <p style="margin-top: 12px; font-weight: 700;">Model Profiles</p>
            <p style="margin-top: 4px;">
                Each Magnitu instance runs a named model profile (e.g. "pascal1"). Models are portable: they can be exported as <code>.magnitu</code> files and shared with colleagues. A <code>.magnitu</code> file contains the trained model, all labels, the keyword recipe, and a version manifest. When someone imports a model file, labels are merged (newer wins), and the trained model is loaded if it's a newer version — protecting against accidental regression.
            </p>
            <p style="margin-top: 8px;">
                The currently connected model name and version are displayed at the top of the <a href="?action=magnitu" class="about-link">Magnitu page</a> and in <a href="?action=settings" class="about-link">Settings</a>.
            </p>

            <p style="margin-top: 12px; font-weight: 700;">API Endpoints</p>
            <p style="margin-top: 4px;">
                Magnitu connects to Seismo through these API endpoints:
            </p>
            <ul>
                <li><strong>magnitu_entries</strong> — exports entries for Magnitu to fetch and label</li>
                <li><strong>magnitu_scores</strong> — receives batch scores and model metadata from the trained model</li>
                <li><strong>magnitu_recipe</strong> — exchanges the keyword recipe between both systems</li>
                <li><strong>magnitu_labels</strong> — syncs labels between Magnitu instances via Seismo</li>
                <li><strong>magnitu_status</strong> — connectivity check and score coverage statistics</li>
            </ul>
            <p style="margin-top: 8px;">
                The more you label, the sharper the model gets. Results appear on the <a href="?action=magnitu" class="about-link">Magnitu page</a> and influence sort order on the main feed when relevance sorting is enabled.
            </p>
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
                <li><strong>German Lex feed:</strong> PHP cURL with cookie-jar (recht.bund.de requires a session cookie)</li>
                <li><strong>Frontend:</strong> Vanilla HTML/CSS/JS — no framework, no build step</li>
            </ul>
        </section>

        <!-- Data -->
        <section class="about-section">
            <h2>Data</h2>
            <ul>
                <li><strong>RSS feeds:</strong> <?= number_format($stats['feeds'] ?? 0) ?> feeds, <?= number_format($stats['feed_items'] ?? 0) ?> items</li>
                <li><strong>Emails:</strong> <?= number_format($stats['emails'] ?? 0) ?> messages</li>
                <li><strong>Lex items:</strong> <?= number_format($stats['lex_eu'] ?? 0) ?> EU, <?= number_format($stats['lex_ch'] ?? 0) ?> CH, <?= number_format($stats['lex_de'] ?? 0) ?> DE</li>
                <li><strong>Jus items:</strong> <?= number_format($stats['jus_bger'] ?? 0) ?> BGer, <?= number_format($stats['jus_bge'] ?? 0) ?> BGE</li>
            </ul>
        </section>

        <!-- Credits -->
        <section class="about-section">
            <h2>Credits</h2>
            <p>
                Built by <a href="https://hektopascal.org" class="about-link" target="_blank" rel="noopener">hektopascal.org</a>.
            </p>
            <p class="about-version" style="margin-top: 8px;">
                Version 0.4 · Last updated: <?= $lastChangeDate ?>
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
