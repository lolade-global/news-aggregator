# News Aggregator API

A Laravel backend that aggregates news articles from multiple sources into a single API. Articles are fetched on an hourly schedule and served via a RESTful API with filtering, sorting, full-text search, and cursor pagination.

## Features

- Fetches articles from **The Guardian**, **New York Times**, and **NewsAPI.org**
- Automatic hourly fetching via Laravel scheduler
- Cursor-based pagination for consistent, performant paging
- Filter by title, source, author, category, date range, and full-text search
- Include related authors and categories via query parameter
- Sortable by title, source, published date, or creation date
- Rate limiting per news source (per-minute and per-day)
- Retry logic with configurable backoff for failed API calls
- Response caching with stale-while-revalidate strategy
- Health check endpoint with source status reporting

## Tech Stack

- **PHP 8.4** / **Laravel 12**
- **MySQL 8.0** (primary database)
- **Redis** (cache + queue)
- **Docker** via Laravel Sail
- **Pest** (testing), **Pint** (code style), **PHPStan + Larastan** (static analysis), **Rector** (refactoring)

## Requirements

- Docker & Docker Compose
- PHP 8.4+ (for running tests locally without Docker)
- Composer

## Quick Start

```bash
# Clone the repository
git clone <repo-url> news-aggregator
cd news-aggregator

# Copy environment file and configure API keys (see API Keys section below)
cp .env.example .env

# Start Docker containers, install dependencies, and run migrations
make setup

# Fetch articles from all configured sources
make fetch-news

# The API is now available at http://localhost/api/v1/articles
```

### Without Make

```bash
cp .env.example .env
composer install
./vendor/bin/sail build
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan news:fetch
```

## API Keys

Sign up for free API keys from each source and add them to your `.env` file:

| Source | Sign Up | Free Tier |
|--------|---------|-----------|
| The Guardian | https://open-platform.theguardian.com/access/ | 500 requests/day |
| New York Times | https://developer.nytimes.com/get-started | 500 requests/day |
| NewsAPI.org | https://newsapi.org/register | 100 requests/day, localhost only |

```env
GUARDIAN_API_KEY=your-guardian-key
NYT_API_KEY=your-nyt-key
NEWS_API_ORG_API_KEY=your-newsapi-key
```

Sources without a configured API key are automatically skipped.

## API Documentation

### GET /api/v1/articles

Returns a paginated list of articles.

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `filter[title]` | string | Partial match on article title |
| `filter[source]` | string | Exact match: `guardian`, `new_york_times`, `news_api_org` |
| `filter[authors.name]` | string | Partial match on author name |
| `filter[categories.name]` | string | Partial match on category name |
| `filter[date_from]` | date | Articles published on or after this date (Y-m-d) |
| `filter[date_to]` | date | Articles published on or before this date (Y-m-d) |
| `filter[search]` | string | Full-text search across title and description (min 2 chars) |
| `include` | string | Comma-separated: `authors`, `categories` |
| `sort` | string | Sort field: `title`, `source`, `published_at`, `created_at`. Prefix with `-` for descending. Default: `-published_at` |
| `per_page` | integer | Items per page (1-100, default: 15) |
| `cursor` | string | Cursor for next/previous page |

**Example Request:**

```bash
curl "http://localhost/api/v1/articles?filter[source]=guardian&filter[search]=climate&include=authors,categories&sort=-published_at&per_page=10"
```

**Example Response:**

```json
{
  "status": "success",
  "message": "Articles retrieved successfully",
  "data": [
    {
      "id": "01JEXAMPLE1234567890ABCDE",
      "title": "Climate Change Report 2025",
      "description": "New findings on global temperatures...",
      "content": "Full article content...",
      "url": "https://guardian.com/article-1",
      "image_url": "https://guardian.com/image.jpg",
      "source": "guardian",
      "source_url": "https://content.guardianapis.com/",
      "published_at": "2025-01-15T10:30:00+00:00",
      "authors": [
        { "name": "John Doe" }
      ],
      "categories": [
        { "name": "Environment", "slug": "environment" }
      ]
    }
  ],
  "meta": [],
  "pagination": {
    "path": "http://localhost/api/v1/articles",
    "per_page": 10,
    "next_cursor": "eyJpZCI6MTAsIl9wb2ludHNUb05leHRJdGVtcyI6dHJ1ZX0",
    "next_page_url": "http://localhost/api/v1/articles?cursor=eyJ...",
    "prev_cursor": null,
    "prev_page_url": null,
    "on_last_page": false
  }
}
```

### GET /api/v1/health

Returns system health and configured source status.

**Example Response:**

```json
{
  "status": "success",
  "message": "Health check",
  "data": {
    "status": "healthy",
    "database": true,
    "sources": [
      { "name": "The Guardian", "identifier": "guardian", "configured": true },
      { "name": "New York Times", "identifier": "new_york_times", "configured": true },
      { "name": "NewsAPI.org", "identifier": "news_api_org", "configured": true }
    ]
  },
  "meta": []
}
```

### Error Responses

| Status | Meaning |
|--------|---------|
| 400 | Invalid filter or sort parameter |
| 404 | Route not found |
| 422 | Validation error (e.g. `per_page` > 100) |
| 429 | Too many requests |
| 500 | Internal server error |

```json
{
  "status": "error",
  "message": "Invalid filter query: Requested filter(s) `invalid_field` are not allowed."
}
```

## Architecture

```
app/
├── Actions/                    # Single-purpose action classes
│   └── FetchArticlesAction     # Builds query, applies filters, caches results
├── Console/Commands/
│   └── FetchNewsCommand        # Artisan command: news:fetch --source=
├── Contracts/
│   └── NewsSourceContract      # Interface all news sources implement
├── DataTransferObjects/
│   └── ArticleDto              # Readonly DTO bridging API response → database
├── Enums/
│   └── NewsSourceEnum          # Backed string enum for source identifiers
├── Exceptions/
│   └── NewsSourceException     # Domain exception with static factories
├── Http/
│   ├── Controllers/Api/V1/     # Thin controllers delegating to actions
│   ├── Requests/               # FormRequest validation
│   ├── Resources/              # API resource transformers
│   └── Responses/              # Standardized JSON response builder
├── Jobs/
│   └── FetchArticlesFromSourceJob  # Queued job with retries and backoff
├── Models/                     # Eloquent models with ULIDs
├── Providers/
│   └── NewsSourceServiceProvider   # Config-driven source registration
└── Services/NewsAggregator/
    ├── Concerns/
    │   └── HasRateLimiting     # Rate limiting trait (per-minute + per-day)
    ├── Sources/
    │   ├── AbstractNewsSource  # Template Method: retry, rate limit, HTTP
    │   ├── GuardianSource
    │   ├── NytSource
    │   └── NewsApiOrgSource
    ├── CacheKeyGenerator       # Deterministic cache keys from query params
    └── NewsAggregatorService   # Orchestrates fetching and persisting articles
```

### Design Principles

- **SOLID**: Each class has a single responsibility. News sources implement a contract (Interface Segregation / Dependency Inversion). The service provider wires everything via configuration.
- **Template Method Pattern**: `AbstractNewsSource` handles retry logic, rate limiting, and HTTP calls. Concrete sources only define endpoint, params, response path, and DTO mapping.
- **DRY**: Common HTTP/retry/rate-limit logic lives in the abstract base class and trait, not duplicated across sources.
- **KISS**: Plain readonly DTOs instead of heavy data libraries. Direct Eloquent queries with Spatie Query Builder for filtering.

### Adding a New Source

1. Add a case to `NewsSourceEnum`
2. Create a class extending `AbstractNewsSource` implementing the four abstract methods
3. Add configuration to `config/news_sources.php` and `.env`
4. The service provider auto-registers it — no other changes needed

## Testing

```bash
# Run the full test suite (63 tests)
make test

# Or directly
./vendor/bin/pest
```

Tests use SQLite in-memory and array cache/queue drivers — no Docker required.

**Test coverage:**
- Unit tests for all 3 news source implementations (HTTP faking, rate limiting, retries)
- Unit tests for the aggregator service (persistence, deduplication, relationship syncing)
- Feature tests for article API endpoint (pagination, all filters, sorting, includes, validation)
- Feature tests for health endpoint
- Feature tests for the fetch command

## Code Quality

```bash
# Run all checks
make lint

# Auto-fix code style
make fix
```

| Tool | Purpose |
|------|---------|
| **Pint** | PSR-12 code style |
| **PHPStan** (Level 5) | Static analysis with Larastan |
| **Rector** | Automated refactoring (PHP 8.4 ruleset) |

## Makefile Commands

| Command | Description |
|---------|-------------|
| `make setup` | Full project setup (install, build, migrate) |
| `make up` | Start Docker containers |
| `make down` | Stop Docker containers |
| `make test` | Run test suite |
| `make lint` | Check code style + static analysis |
| `make fix` | Auto-fix code style + apply refactorings |
| `make fetch-news` | Fetch articles from all sources |
| `make db-fresh` | Drop and recreate all tables |
| `make shell` | Open a shell in the app container |

## Scheduling

The `news:fetch` command runs automatically every hour via Laravel's scheduler. To activate it, add this cron entry on the server:

```
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

With Sail, the scheduler runs automatically when the containers are up.
