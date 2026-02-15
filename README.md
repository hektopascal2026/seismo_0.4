# Seismo

A self-hosted monitoring dashboard that aggregates RSS feeds, email newsletters, Substack publications, and EU/Swiss/German legislation into a single unified feed. Includes optional ML-powered relevance scoring via the companion app [Magnitu](https://github.com/your-org/magnitu).

## Features

- **Combined Feed** — merged timeline of all sources with full-text search and optional relevance sorting
- **RSS** — add and manage standard RSS/Atom feeds with tag-based filtering
- **Substack** — subscribe to Substack newsletters via their RSS feeds
- **Mail** — view email newsletters stored in the database, with sender tagging
- **Lex** — track legislation from the EU, Switzerland, and Germany
  - 🇪🇺 **EU CELLAR** — regulations, directives, and decisions from EUR-Lex via SPARQL (CDM ontology)
  - 🇨🇭 **Fedlex** — Bundesgesetze, Verordnungen, Bundesbeschlüsse, and international treaties via SPARQL (JOLux ontology)
  - 🇩🇪 **recht.bund.de** — Bundesgesetzblatt Teil I + II (German federal legislation) via RSS
- **Magnitu Integration** — optional companion ML app that learns which entries matter to you and pushes relevance scores back via API
- **Settings** — four-tab settings page (Basic, Script, Lex, Magnitu) to manage all sources and configuration
- **Consistent card layout** — unified entry cards across all pages with source tag, user-assigned category, and date

## Requirements

- PHP >= 7.2 with cURL extension
- MySQL / MariaDB
- Composer
- `mailparse` PHP extension (for email parsing)

## Quick Start

1. **Install dependencies**
   ```bash
   composer install
   ```

2. **Configure database**
   - Edit `config.php` with your database credentials (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`)

3. **Run the app**
   ```bash
   php -S localhost:8000
   ```

4. **Open in browser**
   - Visit `http://localhost:8000`
   - Database tables are created automatically on first load

## Pages

| Page | Description |
|------|-------------|
| **Feed** | Combined timeline of all active sources |
| **Magnitu** | ML-scored entries: investigation leads, important items |
| **RSS** | RSS/Atom feed items with tag filters |
| **Lex** | EU, Swiss, and German legislation with source filters (🇪🇺 / 🇨🇭 / 🇩🇪) |
| **Mail** | Email newsletters with sender tag filters |
| **Substack** | Substack newsletter items with tag filters |
| **Settings** | Four tabs — Basic (RSS/Substack), Script (Email), Lex (EU/CH/DE), Magnitu |
| **About** | Project info, data sources, and stats |

## Dependencies

- [SimplePie](https://github.com/simplepie/simplepie) — RSS/Atom parsing
- [PHP MIME Mail Parser](https://github.com/php-mime-mail-parser/php-mime-mail-parser) — email parsing
- [EasyRdf](https://github.com/easyrdf/easyrdf) — SPARQL/RDF queries for EU CELLAR and Fedlex
- PHP cURL — used for fetching the German legislation RSS feed (recht.bund.de requires a session cookie)

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

### Magnitu (optional)
- **Type:** Local Python companion app
- **Protocol:** REST API with bearer token authentication
- **Features:** ML relevance scoring, active learning, portable model profiles (`.magnitu` files)
- **Endpoints:** `magnitu_entries`, `magnitu_scores`, `magnitu_recipe`, `magnitu_labels`, `magnitu_status`

## Project Structure

```
seismo_0.4/
├── index.php          # Main router and controller logic
├── config.php         # Database config and table initialization
├── composer.json      # PHP dependencies
├── assets/
│   └── css/
│       └── style.css  # All styles
├── views/
│   ├── index.php      # Combined feed page
│   ├── magnitu.php    # Magnitu ML-scored entries
│   ├── feeds.php      # RSS feed page
│   ├── feed.php       # Single feed view
│   ├── lex.php        # Legislation page (EU + CH + DE)
│   ├── mail.php       # Email page
│   ├── substack.php   # Substack page
│   ├── settings.php   # Settings page (tabbed: Basic, Script, Lex, Magnitu)
│   ├── about.php      # About page
│   └── styleguide.php # Internal style reference
└── vendor/            # Composer dependencies
```

## License

Prototype project by [hektopascal.org](https://hektopascal.org).
