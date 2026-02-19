# Seismo

A self-hosted monitoring dashboard that aggregates RSS feeds, email newsletters, Substack publications, EU/Swiss/German legislation, Swiss case law, and scraped web pages into a single unified feed. Includes optional ML-powered relevance scoring via the companion app [Magnitu](https://github.com/your-org/magnitu).

## Features

- **Combined Feed** — merged timeline of all sources with full-text search and optional relevance sorting
- **RSS** — add and manage standard RSS/Atom feeds with tag-based filtering
- **Substack** — subscribe to Substack newsletters via their RSS feeds
- **Mail** — IMAP email fetcher with configurable credentials, downloadable cronjob script (native PHP IMAP, no external libraries), and sender tagging
- **Lex** — track legislation from the EU, Switzerland, and Germany
  - 🇪🇺 **EU CELLAR** — regulations, directives, and decisions from EUR-Lex via SPARQL (CDM ontology)
  - 🇨🇭 **Fedlex** — Bundesgesetze, Verordnungen, Bundesbeschlüsse, and international treaties via SPARQL (JOLux ontology)
  - 🇩🇪 **recht.bund.de** — Bundesgesetzblatt Teil I + II (German federal legislation) via RSS
- **Jus** — Swiss case law from BGer, BGE, and BVGer via [entscheidsuche.ch](https://entscheidsuche.ch) with incremental sync
- **Scraper** — configurable web page scraper with link-following mode, CSS-based date extraction, polite delays, and per-entry soft-delete
- **Magnitu Integration** — optional companion ML app that learns which entries matter to you and pushes relevance scores back via API
- **Settings** — four-tab settings page (Basic, Script, Lex, Magnitu) to manage all sources and configuration
- **Consistent card layout** — unified entry cards across all pages with source tag, user-assigned category, and date

## Requirements

- PHP >= 7.2 with cURL and IMAP extensions
- MySQL / MariaDB
- Composer (for the main app; fetcher scripts have no external dependencies)

## Quick Start

1. **Install dependencies**
   ```bash
   composer install
   ```

2. **Configure database**
   - Copy `config.local.php.example` to `config.local.php` and fill in your database credentials (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`)

3. **Run the app**
   ```bash
   php -S localhost:8000
   ```

4. **Open in browser**
   - Visit `http://localhost:8000`
   - Database tables are created automatically on first load

5. **Set up automatic refresh (optional)**
   - Add a cronjob to run `refresh_cron.php` periodically — this refreshes all feeds, lex/jus sources, and Magnitu scores in the background:
   ```
   */15 * * * * /usr/bin/php /path/to/seismo/refresh_cron.php
   ```
   - The web UI refresh button still works for manual on-demand use
   - Feeds that fail 3+ times in a row are automatically paused (circuit breaker) — retry them manually from Settings or the feed view

## Pages

| Page | Description |
|------|-------------|
| **Feed** | Combined timeline of all active sources |
| **Magnitu** | ML-scored entries: investigation leads, important items |
| **RSS** | RSS/Atom feed items with tag filters |
| **Lex** | EU, Swiss, and German legislation with source filters (🇪🇺 / 🇨🇭 / 🇩🇪) |
| **Jus** | Swiss case law — BGer, BGE, BVGer decisions |
| **Mail** | Email newsletters with sender tag filters |
| **Substack** | Substack newsletter items with tag filters |
| **Scraper** | Scraped web page entries with per-source filters and delete |
| **Settings** | Four tabs — Basic (RSS/Substack), Script (Mail config + Scraper config with downloadable scripts), Lex (EU/CH/DE/Jus), Magnitu |
| **About** | Project info, data sources, and stats |

## Dependencies

- [SimplePie](https://github.com/simplepie/simplepie) — RSS/Atom parsing
- PHP IMAP extension — email fetching (native, no external library)
- [EasyRdf](https://github.com/easyrdf/easyrdf) — SPARQL/RDF queries for EU CELLAR and Fedlex
- PHP cURL — used for fetching the German legislation RSS feed (recht.bund.de requires a session cookie) and web scraping
- PHP DOMDocument — HTML parsing for scraper content extraction and date extraction via CSS-to-XPath

## Data Sources

### EU Legislation
- **Endpoint:** `https://publications.europa.eu/webapi/rdf/sparql`
- **Ontology:** CDM (Common Data Model)
- **Scope:** Finalized secondary legislation (regulations, directives, decisions) from the last 90 days

### Swiss Legislation
- **Endpoint:** `https://fedlex.data.admin.ch/sparqlendpoint`
- **Ontology:** JOLux
- **Scope:** Bundesgesetze, Verordnungen, Bundesbeschlüsse, and international treaties from the last 90 days

### German Legislation
- **Feed:** `https://www.recht.bund.de/rss/feeds/rss_bgbl-1-2.xml`
- **Format:** RSS 2.0 with custom `meta:` namespace for structured metadata
- **Scope:** Bundesgesetzblatt Teil I + II — Gesetze, Verordnungen, Bekanntmachungen from the last 90 days
- **Note:** recht.bund.de requires a load-balancer session cookie; Seismo uses cURL with a cookie jar to handle this automatically

### Swiss Case Law (Jus)
- **Source:** [entscheidsuche.ch](https://entscheidsuche.ch)
- **Courts:** BGer (Federal Supreme Court), BGE (Leading decisions), BVGer (Federal Administrative Court)
- **Sync:** Incremental via index manifests — only fetches new decisions since last sync

### Email (Mail)
- **Type:** Standalone PHP CLI script (`fetcher/mail/fetch_mail.php`) run via cronjob
- **Protocol:** IMAP with SSL/TLS — uses PHP's native `imap_*` functions, no Composer or external libraries
- **Setup:** Configure IMAP credentials in Settings > Script, download `config.php` + `fetch_mail.php`, upload to server, add cronjob
- **MIME parsing:** Recursive structure traversal with base64/quoted-printable decoding and charset conversion to UTF-8
- **Deduplication:** By IMAP UID — each message is stored once

### Web Scraper
- **Type:** Standalone PHP CLI script (`fetcher/scraper/seismo_scraper.php`) run via cronjob
- **Modes:** Single-page scrape, or link-following mode (scrape articles from a listing page via configurable URL pattern)
- **Date extraction:** Configurable CSS selector per scraper (e.g. `time[datetime]`, `.article-date`) — supports `datetime`/`content` attributes, German/French month names, `dd.mm.yyyy` format; falls back to current time
- **Polite scraping:** Random delays, rotating User-Agents, standard browser headers
- **Content extraction:** DOMDocument-based readability heuristics (largest text block from `<article>`, `<main>`, `<div>`, `<section>`)

### Magnitu (optional)
- **Type:** Local Python companion app
- **Protocol:** REST API with bearer token authentication
- **Features:** ML relevance scoring, active learning, portable model profiles (`.magnitu` files)
- **Endpoints:** `magnitu_entries`, `magnitu_scores`, `magnitu_recipe`, `magnitu_labels`, `magnitu_status`

## Project Structure

```
seismo_0.4/
├── index.php              # Thin router — maps actions to controller handlers
├── config.php             # Database helpers, table initialization, shared utilities
├── config.local.php       # Database credentials (gitignored)
├── refresh_cron.php       # CLI cronjob — full background refresh cycle
├── composer.json          # PHP dependencies
├── controllers/
│   ├── dashboard.php      # Main feed page, search, global refresh
│   ├── rss.php            # RSS & Substack feeds, CRUD, tags, config import/export
│   ├── mail.php           # Email page, sender management, mail fetcher config
│   ├── lex_jus.php        # EU/CH/DE legislation, Swiss case law (BGer/BGE/BVGer)
│   ├── scraper.php        # Web scraper configs, entries, script downloads
│   ├── magnitu.php        # ML scoring, Magnitu API, AI views
│   └── settings.php       # Settings page, about, beta, styleguide
├── views/
│   ├── index.php          # Combined feed page
│   ├── magnitu.php        # Magnitu ML-scored entries
│   ├── feeds.php          # RSS feed page
│   ├── feed.php           # Single feed view
│   ├── lex.php            # Legislation page (EU + CH + DE)
│   ├── jus.php            # Swiss case law page (BGer / BGE / BVGer)
│   ├── mail.php           # Email page
│   ├── substack.php       # Substack page
│   ├── scraper.php        # Scraped web pages
│   ├── settings.php       # Settings page (tabbed: Basic, Script, Lex, Magnitu)
│   ├── about.php          # About page
│   └── styleguide.php     # Internal style reference
├── fetcher/
│   ├── mail/
│   │   ├── fetch_mail.php      # IMAP mail fetcher CLI script (cronjob)
│   │   └── config.php.example  # IMAP + DB config template
│   └── scraper/
│       ├── seismo_scraper.php  # Web scraper CLI script (cronjob)
│       └── config.php.example  # DB config template for the scraper
├── tests/
│   └── test_staging.php   # Integration tests (112 checks against staging)
├── assets/
│   └── css/
│       └── style.css      # All styles
└── vendor/                # Composer dependencies
```

### Architecture

`index.php` is a pure router (~320 lines) — every `case` is a single-line call to a handler function in `controllers/`. Controllers are organized by **how content gets into Seismo**: RSS, Mail, Scraper, Lex/Jus, and Magnitu. Shared database helpers and config live in `config.php`. Views are plain PHP templates that render variables set by their controller.

## License

Prototype project by [hektopascal.org](https://hektopascal.org).
