# Seismo

A self-hosted monitoring dashboard that aggregates RSS feeds, email newsletters, Substack publications, and EU/Swiss legislation into a single unified feed.

## Features

- **Combined Feed** — merged timeline of all sources with full-text search
- **RSS** — add and manage standard RSS/Atom feeds with tag-based filtering
- **Substack** — subscribe to Substack newsletters via their RSS feeds
- **Mail** — view email newsletters stored in the database, with sender tagging
- **Lex** — track EU and Swiss federal legislation via SPARQL
  - 🇪🇺 **EU CELLAR** — regulations, directives, and decisions from EUR-Lex (CDM ontology)
  - 🇨🇭 **Fedlex** — Bundesgesetze, Verordnungen, Bundesbeschlüsse, and international treaties (JOLux ontology)
- **Settings** — manage all sources, assign/rename tags, enable/disable feeds and senders
- **Consistent card layout** — unified entry cards across all pages with source tag, user-assigned category, and date

## Requirements

- PHP >= 7.2
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
| **RSS** | RSS/Atom feed items with tag filters |
| **Lex** | EU and Swiss legislation with source filters (🇪🇺 / 🇨🇭) |
| **Mail** | Email newsletters with sender tag filters |
| **Substack** | Substack newsletter items with tag filters |
| **Settings** | Manage feeds, senders, tags |
| **About** | Project info, data sources, and stats |

## Dependencies

- [SimplePie](https://github.com/simplepie/simplepie) — RSS/Atom parsing
- [PHP MIME Mail Parser](https://github.com/php-mime-mail-parser/php-mime-mail-parser) — email parsing
- [EasyRdf](https://github.com/easyrdf/easyrdf) — SPARQL/RDF queries for EU CELLAR and Fedlex

## Data Sources

### EU Legislation
- **Endpoint:** `https://publications.europa.eu/webapi/rdf/sparql`
- **Ontology:** CDM (Common Data Model)
- **Scope:** Finalized secondary legislation (regulations, directives, decisions) from the last 90 days

### Swiss Legislation
- **Endpoint:** `https://fedlex.data.admin.ch/sparqlendpoint`
- **Ontology:** JOLux
- **Scope:** Bundesgesetze, Verordnungen, Bundesbeschlüsse, and international treaties from the last 90 days

## Project Structure

```
seismo_0.3/
├── index.php          # Main router and controller logic
├── config.php         # Database config and table initialization
├── composer.json      # PHP dependencies
├── assets/
│   └── css/
│       └── style.css  # All styles
├── views/
│   ├── index.php      # Combined feed page
│   ├── feeds.php      # RSS feed page
│   ├── feed.php       # Single feed view
│   ├── lex.php        # Legislation page (EU + CH)
│   ├── mail.php       # Email page
│   ├── substack.php   # Substack page
│   ├── settings.php   # Settings page
│   ├── about.php      # About page
│   └── styleguide.php # Internal style reference
└── vendor/            # Composer dependencies
```

## License

Prototype project by [hektopascal.org](https://hektopascal.org).
