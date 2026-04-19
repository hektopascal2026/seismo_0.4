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
                <?php $refreshFrom = 'about'; $refreshStyle = 'icon'; include __DIR__ . '/partials/refresh_btn.php'; ?>
                <button type="button" class="top-bar-btn" id="menuToggle" title="Menu"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
            </div>
        </div>

        <?php $navActive = 'about'; include __DIR__ . '/partials/nav.php'; ?>

        <!-- Overview -->
        <section class="about-section">
            <h2>What is Seismo?</h2>
            <p>
                Seismo is a self-hosted monitoring dashboard that aggregates information from multiple sources into a single feed.
                It tracks RSS feeds, email newsletters, Substack publications, legislative changes from the EU, Switzerland, Germany, and France, Swiss parliamentary press releases, Swiss case law, parliamentary calendars, and scraped web pages — helping you stay informed about policy, regulation, jurisprudence, and media that matter.
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
                    Email newsletters fetched via IMAP cronjob — configure credentials in Settings, download the script, and deploy. Uses PHP's native IMAP extension, no external libraries needed.
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
                    <span class="about-source-label" style="background-color: #f5f562;">🇫🇷 FR Lex</span>
                    French legislation via the <a href="https://www.legifrance.gouv.fr/" class="about-link" target="_blank" rel="noopener">Légifrance</a> PISTE API — lois, ordonnances, décrets from the Journal Officiel (JORF). Requires OAuth2 credentials from <a href="https://piste.gouv.fr/" class="about-link" target="_blank" rel="noopener">piste.gouv.fr</a>
                </li>
                <li>
                    <span class="about-source-label" style="background-color: #f5f562;">🏛 Parl MM</span>
                    Swiss parliamentary press releases via the <a href="https://www.parlament.ch/press-releases/" class="about-link" target="_blank" rel="noopener">parlament.ch</a> SharePoint REST API — multilingual titles, body text, and commission tags (e.g. FK-N, SGK-S)
                </li>
                <li>
                    <span class="about-source-label" style="background-color: #f5f562;">⚖️ BGer Jus</span>
                    Swiss Federal Supreme Court decisions via <a href="https://entscheidsuche.ch" class="about-link" target="_blank" rel="noopener">entscheidsuche.ch</a> — incremental sync via index manifests
                </li>
                <li>
                    <span class="about-source-label" style="background-color: #f5f562;">⚖️ BGE Jus</span>
                    Published leading decisions (Leitentscheide / BGE) from the Swiss Federal Supreme Court via <a href="https://entscheidsuche.ch" class="about-link" target="_blank" rel="noopener">entscheidsuche.ch</a>
                </li>
                <li>
                    <span class="about-source-label" style="background-color: #f5f562;">⚖️ BVGer Jus</span>
                    Swiss Federal Administrative Court decisions via <a href="https://entscheidsuche.ch" class="about-link" target="_blank" rel="noopener">entscheidsuche.ch</a> — incremental sync via index manifests
                </li>
                <li>
                    <span class="about-source-label" style="background-color: #FFDBBB;">🌐 Scraper</span>
                    Web pages scraped periodically via a cronjob script — content extracted automatically with readability heuristics.
                    Supports link-following mode (scrape articles from a listing page) and configurable date selectors to extract publication dates from the page HTML.
                    Entries can be soft-deleted (hidden) individually.
                </li>
                <li>
                    <span class="about-source-label" style="background-color: #C5E8C5;">📅 Calendar</span>
                    Parliamentary calendar events fetched from the Swiss Parliament data API — session schedules, committee meetings, and submitted texts with expand/collapse previews
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
                Each Magnitu instance runs one or more named profiles (e.g. "security", "digital policy"). Models are portable: they can be exported as <code>.magnitu</code> files and shared with colleagues. A <code>.magnitu</code> file contains the trained model, all labels, the keyword recipe, and a version manifest. When someone imports a model file, labels are merged (newer wins), and the trained model is loaded if it's a newer version — protecting against accidental regression.
            </p>
            <p style="margin-top: 8px;">
                In a multi-profile setup, each topic profile can push scores to its own <strong>satellite Seismo instance</strong>. The satellite reads entries from this (mothership) database and maintains independent scoring tables — letting different audiences get a feed scored for their specific focus area, without duplicating ingestion infrastructure.
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
                <li><strong>Email fetching:</strong> PHP native IMAP extension (no external libraries)</li>
                <li><strong>SPARQL / RDF:</strong> <a href="https://github.com/easyrdf/easyrdf" class="about-link" target="_blank" rel="noopener">EasyRdf</a></li>
                <li><strong>German Lex feed:</strong> PHP cURL with cookie-jar (recht.bund.de requires a session cookie)</li>
                <li><strong>French Lex API:</strong> OAuth2 client-credentials flow against <a href="https://piste.gouv.fr/" class="about-link" target="_blank" rel="noopener">PISTE</a>, then Légifrance search endpoint</li>
                <li><strong>Parl MM:</strong> SharePoint REST API (OData) with JSON response parsing</li>
                <li><strong>Calendar:</strong> Swiss Parliament data API (ws-old.parlament.ch)</li>
                <li><strong>Web scraping:</strong> PHP DOMDocument + cURL with polite delays, User-Agent rotation, CSS-to-XPath date extraction</li>
                <li><strong>Frontend:</strong> Vanilla HTML/CSS/JS — no framework, no build step</li>
            </ul>
        </section>

        <!-- Data -->
        <section class="about-section">
            <h2>Data</h2>
            <ul>
                <li><strong>RSS feeds:</strong> <?= number_format($stats['feeds'] ?? 0) ?> feeds, <?= number_format($stats['feed_items'] ?? 0) ?> items</li>
                <li><strong>Emails:</strong> <?= number_format($stats['emails'] ?? 0) ?> messages</li>
                <li><strong>Lex items:</strong> <?= number_format($stats['lex_eu'] ?? 0) ?> EU, <?= number_format($stats['lex_ch'] ?? 0) ?> CH, <?= number_format($stats['lex_de'] ?? 0) ?> DE, <?= number_format($stats['lex_fr'] ?? 0) ?> FR, <?= number_format($stats['lex_parl_mm'] ?? 0) ?> Parl MM</li>
                <li><strong>Jus items:</strong> <?= number_format($stats['jus_bger'] ?? 0) ?> BGer, <?= number_format($stats['jus_bge'] ?? 0) ?> BGE, <?= number_format($stats['jus_bvger'] ?? 0) ?> BVGer</li>
                <li><strong>Calendar:</strong> <?= number_format($stats['calendar'] ?? 0) ?> events</li>
                <li><strong>Scraper:</strong> <?= number_format($stats['scraper_configs'] ?? 0) ?> configured, <?= number_format($stats['scraper_items'] ?? 0) ?> items</li>
            </ul>
        </section>

        <!-- Development Timeline -->
        <section class="about-section">
            <h2>Development Timeline</h2>
            <p style="margin-bottom: 16px;">
                Seismo evolved through five major versions, each expanding the scope of sources and capabilities.
                The companion ML app <strong>Magnitu</strong> was developed in parallel starting with version 0.4.
            </p>

            <div style="border-left: 3px solid #000000; padding-left: 16px; margin-left: 4px;">

                <!-- 0.1 -->
                <div style="margin-bottom: 20px;">
                    <div style="font-weight: 700; font-size: 14px; margin-bottom: 4px;">
                        <span style="font-family: monospace; background: #f5f562; padding: 2px 6px; margin-right: 6px;">0.1</span>
                        RSS Reader
                        <span style="font-size: 11px; color: #666; margin-left: 8px;">Jan 2026</span>
                    </div>
                    <ul style="margin: 4px 0 0 0; padding-left: 16px;">
                        <li>RSS/Atom feed aggregation with <a href="https://github.com/simplepie/simplepie" class="about-link" target="_blank">SimplePie</a></li>
                        <li>Tag-based filtering and feed management</li>
                        <li>Unified main feed with full-text search</li>
                        <li>Initial design system: monochrome + yellow accent, SVG seismograph logo</li>
                    </ul>
                </div>

                <!-- 0.2 -->
                <div style="margin-bottom: 20px;">
                    <div style="font-weight: 700; font-size: 14px; margin-bottom: 4px;">
                        <span style="font-family: monospace; background: #f5f562; padding: 2px 6px; margin-right: 6px;">0.2</span>
                        Email &amp; Substack
                        <span style="font-size: 11px; color: #666; margin-left: 8px;">Jan 23 – Feb 7, 2026</span>
                    </div>
                    <ul style="margin: 4px 0 0 0; padding-left: 16px;">
                        <li>IMAP email fetching — standalone PHP CLI cronjob with native <code>imap_*</code> functions, no external libraries</li>
                        <li>Emails integrated into the main feed timeline alongside RSS entries</li>
                        <li>Sender tag management: assign, rename, toggle, soft-delete senders</li>
                        <li>Settings page separated from feed views</li>
                        <li>Substack newsletter support via RSS feeds with category filtering</li>
                        <li>AI-readable data export (<code>ai_view</code>) for external LLM consumption</li>
                        <li>Expand/collapse content previews on all entry cards</li>
                        <li>Styleguide page documenting the design system</li>
                        <li>UI overhaul: consistent card layout, tag pills, dense typography</li>
                    </ul>
                </div>

                <!-- 0.3 -->
                <div style="margin-bottom: 20px;">
                    <div style="font-weight: 700; font-size: 14px; margin-bottom: 4px;">
                        <span style="font-family: monospace; background: #f5f562; padding: 2px 6px; margin-right: 6px;">0.3</span>
                        Legislation &amp; Magnitu Foundation
                        <span style="font-size: 11px; color: #666; margin-left: 8px;">Feb 7 – 13, 2026</span>
                    </div>
                    <ul style="margin: 4px 0 0 0; padding-left: 16px;">
                        <li>🇪🇺 EU legislation via SPARQL queries to <a href="https://publications.europa.eu/webapi/rdf/sparql" class="about-link" target="_blank">EU CELLAR</a> (CDM ontology)</li>
                        <li>🇨🇭 Swiss legislation via SPARQL queries to <a href="https://fedlex.data.admin.ch/sparqlendpoint" class="about-link" target="_blank">Fedlex</a> (JOLux ontology)</li>
                        <li>Lex page with source filtering pills (🇪🇺 / 🇨🇭) and configurable SPARQL parameters</li>
                        <li>Lex items integrated into the main feed with source tags</li>
                        <li>Consolidated single-button refresh for all sources</li>
                        <li>Lex configuration section in Settings (endpoints, resource types, lookback)</li>
                        <li>About page with live data statistics</li>
                        <li>Beta page for AI view generation controls</li>
                        <li>First Magnitu API integration: <code>magnitu_entries</code>, <code>magnitu_scores</code>, <code>magnitu_recipe</code></li>
                        <li>Magnitu page for viewing ML-scored entries</li>
                        <li>Label syncing API (<code>magnitu_labels</code>) for multi-instance collaboration</li>
                        <li>API auth hardened for CGI/FastCGI shared hosting (<code>.htaccess</code> rewrite)</li>
                    </ul>
                </div>

                <!-- 0.4 -->
                <div style="margin-bottom: 20px;">
                    <div style="font-weight: 700; font-size: 14px; margin-bottom: 4px;">
                        <span style="font-family: monospace; background: #f5f562; padding: 2px 6px; margin-right: 6px;">0.4</span>
                        Full Stack
                        <span style="font-size: 11px; color: #666; margin-left: 8px;">Feb 13 – 21, 2026</span>
                    </div>

                    <p style="margin: 4px 0 6px 0; font-weight: 600; font-size: 12px;">New data sources:</p>
                    <ul style="margin: 0 0 8px 0; padding-left: 16px;">
                        <li>🇩🇪 German federal legislation via RSS from <a href="https://www.recht.bund.de/" class="about-link" target="_blank">recht.bund.de</a> (cURL with cookie jar for session handling)</li>
                        <li>🇫🇷 French legislation via <a href="https://www.legifrance.gouv.fr/" class="about-link" target="_blank">Légifrance</a> PISTE API (OAuth2 client credentials)</li>
                        <li>⚖️ Swiss case law: BGer, BGE (Leitentscheide), BVGer via <a href="https://entscheidsuche.ch" class="about-link" target="_blank">entscheidsuche.ch</a> with incremental sync</li>
                        <li>🏛 Swiss Parliament press releases via <a href="https://www.parlament.ch/press-releases/" class="about-link" target="_blank">parlament.ch</a> SharePoint REST API</li>
                        <li>📅 Parliamentary calendar events from the Swiss Parliament data API</li>
                        <li>🌐 Web scraper with link-following mode, CSS-based date extraction, readability heuristics, and soft-delete</li>
                    </ul>

                    <p style="margin: 0 0 6px 0; font-weight: 600; font-size: 12px;">Architecture &amp; hardening:</p>
                    <ul style="margin: 0 0 8px 0; padding-left: 16px;">
                        <li>Full refactoring: extracted 6 controllers from monolithic <code>index.php</code> — router is now ~320 lines</li>
                        <li>Credentials externalized to <code>config.local.php</code> (gitignored)</li>
                        <li>Parallel refresh with per-source circuit breaker (auto-pause after 3 failures)</li>
                        <li>CLI cronjob (<code>refresh_cron.php</code>) for background refresh of all sources</li>
                        <li>WAF and rate-limit resilience: eliminated client-side fetches that triggered shared hosting blocks</li>
                        <li>Integration test suite (112 checks against staging server)</li>
                    </ul>

                    <p style="margin: 0 0 6px 0; font-weight: 600; font-size: 12px;">UI &amp; configuration:</p>
                    <ul style="margin: 0 0 0 0; padding-left: 16px;">
                        <li>Tabbed settings page: Basic, Script, Lex, Magnitu, Styleguide</li>
                        <li>Mail fetcher config UI with downloadable IMAP scripts</li>
                        <li>Scraper config UI with downloadable cronjob scripts</li>
                        <li>Magnitu model info display (name, version, score coverage)</li>
                        <li>Banned words filter for case law entries</li>
                        <li>All/None pill toggle on the main feed</li>
                        <li>JUS card titles show case topic (Abstract) instead of raw header</li>
                    </ul>
                </div>

                <!-- 0.4.3 -->
                <div style="margin-bottom: 20px;">
                    <div style="font-weight: 700; font-size: 14px; margin-bottom: 4px;">
                        <span style="font-family: monospace; background: #f5f562; padding: 2px 6px; margin-right: 6px;">0.4.3</span>
                        Email Subscriptions
                        <span style="font-size: 11px; color: #666; margin-left: 8px;">Apr 2026</span>
                    </div>
                    <ul style="margin: 4px 0 0 0; padding-left: 16px;">
                        <li>Email newsletter senders are now first-class <strong>subscriptions</strong>, not just implicit tags on <code>sender_tags</code></li>
                        <li>Domain-first matching (<code>@example.com</code>) with per-email overrides — one-time migration from <code>sender_tags</code>, no data loss</li>
                        <li>New Mail subs page mirroring the RSS feeds UX: add, rename, categorize, pause, remove, restore, and per-source item counts</li>
                        <li><code>List-Unsubscribe</code> header parsed on ingest (URL + mailto), with RFC 8058 one-click POST when available and a same-domain safety check</li>
                        <li>Dashboard, Mail search, and Magnitu API now read tags and blocks from subscriptions while keeping <code>sender_tags</code> as a back-compat shim</li>
                        <li>Sync runs inside <code>refreshEmails()</code>, so the existing IMAP cronjob keeps subscriptions up to date with no extra setup</li>
                    </ul>
                </div>

                <!-- 0.4.4 -->
                <div style="margin-bottom: 20px;">
                    <div style="font-weight: 700; font-size: 14px; margin-bottom: 4px;">
                        <span style="font-family: monospace; background: #f5f562; padding: 2px 6px; margin-right: 6px;">0.4.4</span>
                        Module-owned management UI
                        <span style="font-size: 11px; color: #666; margin-left: 8px;">Apr 2026</span>
                    </div>
                    <p style="margin: 4px 0 6px 0;">
                        Each module now owns its own source-management UI directly on its tab, instead of burying it inside <em>Settings</em>. Settings becomes smaller and more focused on truly global configuration.
                    </p>
                    <ul style="margin: 0 0 0 0; padding-left: 16px;">
                        <li><strong>Mail</strong> tab got an inline <code>Items | Subscriptions</code> switch — manage email subscriptions without leaving Mail</li>
                        <li><strong>RSS</strong> tab now has an inline <code>Items | Feeds</code> switch with the full add / tag / enable / disable / delete / config-file flow</li>
                        <li>Legacy <code>from=settings</code> / <code>mail_subscriptions</code> URLs keep working via redirects, so old bookmarks don't break</li>
                        <li>Substack and scraper configs remain in Settings for now — same module-by-module migration coming next</li>
                    </ul>
                </div>

                <!-- 0.4.5 -->
                <div style="margin-bottom: 20px;">
                    <div style="font-weight: 700; font-size: 14px; margin-bottom: 4px;">
                        <span style="font-family: monospace; background: #f5f562; padding: 2px 6px; margin-right: 6px;">0.4.5</span>
                        Settings information architecture
                        <span style="font-size: 11px; color: #666; margin-left: 8px;">Apr 2026</span>
                    </div>
                    <p style="margin: 4px 0 6px 0;">
                        Settings is now organized around modules instead of a <em>Basic / Script</em> catch-all. One concept per tab, short and honest labels.
                    </p>
                    <ul style="margin: 0 0 0 0; padding-left: 16px;">
                        <li><strong>Basic</strong> → <strong>General</strong> (thin overview + pointers to module tabs)</li>
                        <li><strong>Script</strong> split into <strong>Mail</strong> (IMAP + fetcher) and <strong>Scraper</strong> (URLs + cron scripts)</li>
                        <li>New <strong>RSS</strong> tab — Substack settings live here (Substack is RSS under the hood); RSS feed sources remain on <em>RSS &rsaquo; Feeds</em></li>
                        <li><strong>Lex</strong> → <strong>Lex / Jus</strong>; <strong>Calendar</strong> → <strong>Leg</strong> (legislative / parliamentary activity, as opposed to finished legal text)</li>
                        <li><strong>Satellites</strong> → <strong>Satellite</strong>; <strong>Feed diagnostics</strong> → <strong>Diagnostics</strong> (still RSS-only for now &mdash; honest disclaimer shown, scope expanding later)</li>
                        <li>New <strong>LLM</strong> tab hosts the AI View Generator previously under the <em>Beta</em> nav entry; Beta nav item retired, <code>?action=beta</code> 302-redirects to <code>?action=settings&amp;tab=llm</code></li>
                        <li>All legacy tab slugs (<code>basic</code>, <code>script</code>, <code>calendar</code>, <code>satellites</code>, <code>feed_diagnostics</code>) still resolve via a normalization map so bookmarks don't break</li>
                    </ul>
                </div>

                <!-- 0.5 -->
                <div style="margin-bottom: 20px; padding: 10px 12px; border: 2px dashed #cccccc; background: #fafafa;">
                    <div style="font-weight: 700; font-size: 14px; margin-bottom: 4px;">
                        <span style="font-family: monospace; background: #d4e9ff; padding: 2px 6px; margin-right: 6px;">0.5</span>
                        Seismo Satellites
                        <span style="font-size: 11px; color: #666; margin-left: 8px;">in progress</span>
                    </div>
                    <p style="margin: 4px 0 6px 0;">
                        Lightweight, topic-specific Seismo instances (e.g. "security", "digital policy") that share the mothership's scraping but run their own Magnitu profile, labels, and scores. The main feature of 0.5.
                    </p>
                    <ul style="margin: 0 0 0 0; padding-left: 16px;">
                        <li>Groundwork already in 0.4: <code>SEISMO_MOTHERSHIP_DB</code>, <code>entryTable()</code>, satellite-aware email-table resolution, and a satellite-ready Magnitu API</li>
                        <li>0.5 adds the full satellite experience: per-instance identity, UI cues, setup docs, and an end-to-end mothership ⇄ satellite sync story</li>
                        <li>No duplicate scraping: satellites cross-DB-read feeds, emails, lex, and calendar from the mothership; only scoring tables are local</li>
                        <li>Each satellite pairs with its own Magnitu profile and its own API key — the API contract stays unchanged</li>
                        <li>Fresh API key per satellite, explicit SELECT grant on the mothership DB, and clear boundaries between entry-source tables and scoring tables</li>
                    </ul>
                </div>

                <!-- Magnitu -->
                <div style="margin-bottom: 4px; margin-top: 28px; padding-top: 16px; border-top: 2px solid #eeeeee;">
                    <div style="font-weight: 700; font-size: 14px; margin-bottom: 4px;">
                        <span style="font-family: monospace; background: #FFD4C4; padding: 2px 6px; margin-right: 6px;">Magnitu</span>
                        Companion ML Scoring App
                        <span style="font-size: 11px; color: #666; margin-left: 8px;">Feb 13 – 20, 2026</span>
                    </div>
                    <p style="margin: 4px 0 8px 0;">
                        Local Python application that learns which entries matter to you and pushes relevance scores back to Seismo via API. Developed in its own <a href="https://github.com/hektopascal2026/magnitu" class="about-link" target="_blank">repository</a>.
                    </p>

                    <p style="margin: 0 0 6px 0; font-weight: 600; font-size: 12px;">v1 — TF-IDF + Logistic Regression:</p>
                    <ul style="margin: 0 0 8px 0; padding-left: 16px;">
                        <li>Initial training pipeline: fetch entries from Seismo, label locally, train, push scores</li>
                        <li>Top 30 validation page for model accuracy review</li>
                        <li>Active learning: smart sampling of uncertain entries for labeling</li>
                        <li>One-click macOS installer (<code>curl</code> one-liner → venv → dependencies → launch)</li>
                        <li>Label syncing between Magnitu instances via Seismo</li>
                        <li>Portable model profiles: export/import <code>.magnitu</code> files (model + labels + recipe + manifest)</li>
                    </ul>

                    <p style="margin: 0 0 6px 0; font-weight: 600; font-size: 12px;">v2 — Transformer pipeline:</p>
                    <ul style="margin: 0 0 8px 0; padding-left: 16px;">
                        <li>Switched to <strong>XLM-RoBERTa</strong> transformer embeddings for multilingual support (DE/FR/IT/EN)</li>
                        <li>Knowledge distillation: teacher (transformer) trains student (fast linear) for production scoring</li>
                        <li>Title-weighted embeddings to reduce content asymmetry between short lex items and long articles</li>
                        <li>Stale embedding invalidation — re-embed when model changes</li>
                        <li>Apple Silicon GPU acceleration (MPS) for embedding computation</li>
                        <li>OOM fixes for constrained hardware (float16, smaller batches, memory release)</li>
                    </ul>

                    <p style="margin: 0 0 6px 0; font-weight: 600; font-size: 12px;">v3 — Reliability &amp; collaboration:</p>
                    <ul style="margin: 0 0 8px 0; padding-left: 16px;">
                        <li>Multi-user sync: label conflict resolution, incremental push, quality gating</li>
                        <li>Magnitu Mini: standalone mobile labeling web app for quick triage</li>
                        <li>Retry queue for silent label loss prevention</li>
                        <li>Guard against silent failures: structured error logging, contract tests, startup verification</li>
                        <li>Auto-update on startup (<code>git pull</code> + <code>pip install</code>)</li>
                    </ul>

                    <p style="margin: 0 0 6px 0; font-weight: 600; font-size: 12px;">v4 — Multi-profile &amp; satellite mode:</p>
                    <ul style="margin: 0 0 0 0; padding-left: 16px;">
                        <li>Multiple named profiles (e.g. "security", "digital policy") — each profile has its own trained model, label set, and push target</li>
                        <li>Each profile can push scores and recipes to a dedicated <strong>satellite Seismo instance</strong> — independent scoring without independent scraping</li>
                        <li>Mothership Seismo remains the single source of truth for entries; satellites read entries via cross-DB queries and maintain their own <code>entry_scores</code></li>
                        <li>Seismo groundwork: <code>SEISMO_MOTHERSHIP_DB</code> config constant, <code>entryTable()</code> helper, all Magnitu API entry reads satellite-aware</li>
                        <li>API contract unchanged — no format or endpoint changes required</li>
                    </ul>
                </div>

            </div>
        </section>

        <!-- Credits -->
        <section class="about-section">
            <h2>Credits</h2>
            <p>
                Built by <a href="https://hektopascal.org" class="about-link" target="_blank" rel="noopener">hektopascal.org</a>.
            </p>
            <p class="about-version" style="margin-top: 8px;">
                Version 0.4.5 · Last updated: <?= $lastChangeDate ?>
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
