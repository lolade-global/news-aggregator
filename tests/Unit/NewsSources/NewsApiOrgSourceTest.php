<?php

use App\Enums\NewsSourceEnum;
use App\Exceptions\NewsSourceException;
use App\Services\NewsAggregator\Sources\NewsApiOrgSource;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    Config::set('news_sources.news_api_org', [
        'enabled' => true,
        'api_key' => 'test-key',
        'base_url' => 'https://newsapi.org/v2/',
        'timeout' => 5,
        'retry_attempts' => 3,
        'rate_limit' => [
            'requests_per_minute' => 10,
            'requests_per_day' => 100,
        ],
    ]);
    Config::set('news_aggregator.retry_delays', [0, 0, 0]);

    $this->source = new NewsApiOrgSource;
    $this->source->resetRateLimits();
});

it('returns the correct identifier', function () {
    expect($this->source->getIdentifier())->toBe(NewsSourceEnum::NEWS_API_ORG);
});

it('returns the correct source name', function () {
    expect($this->source->getSourceName())->toBe('NewsAPI.org');
});

it('reports configured when enabled with API key and base URL', function () {
    expect($this->source->isConfigured())->toBeTrue();
});

it('reports not configured without API key', function () {
    Config::set('news_sources.news_api_org.api_key', null);
    expect($this->source->isConfigured())->toBeFalse();
});

it('fetches and maps articles successfully', function () {
    Http::fake([
        'newsapi.org/*' => Http::response([
            'articles' => [
                [
                    'title' => 'NewsAPI Test Article',
                    'url' => 'https://example.com/article-1',
                    'description' => 'A test description',
                    'content' => 'Full content here... [+500 chars]',
                    'urlToImage' => 'https://example.com/image.jpg',
                    'publishedAt' => '2025-01-15T10:30:00Z',
                    'author' => 'John Doe, Jane Smith',
                ],
            ],
        ]),
    ]);

    $articles = $this->source->fetchArticles();

    expect($articles)->toHaveCount(1);
    expect($articles[0]->title)->toBe('NewsAPI Test Article');
    expect($articles[0]->description)->toBe('A test description');
    expect($articles[0]->author)->toBe('John Doe, Jane Smith');
    expect($articles[0]->category)->toBe('Uncategorized');
    expect($articles[0]->source)->toBe('news_api_org');
});

it('filters out articles with [Removed] title', function () {
    Http::fake([
        'newsapi.org/*' => Http::response([
            'articles' => [
                [
                    'title' => '[Removed]',
                    'url' => 'https://example.com/removed',
                    'description' => 'Removed article',
                    'content' => null,
                    'urlToImage' => null,
                    'publishedAt' => '2025-01-15T10:30:00Z',
                    'author' => null,
                ],
                [
                    'title' => 'Valid Article',
                    'url' => 'https://example.com/valid',
                    'description' => 'A valid one',
                    'content' => 'Content',
                    'urlToImage' => null,
                    'publishedAt' => '2025-01-15T10:30:00Z',
                    'author' => null,
                ],
            ],
        ]),
    ]);

    $articles = $this->source->fetchArticles();

    expect($articles)->toHaveCount(1);
    expect($articles[0]->title)->toBe('Valid Article');
});

it('handles null author gracefully', function () {
    Http::fake([
        'newsapi.org/*' => Http::response([
            'articles' => [
                [
                    'title' => 'No Author',
                    'url' => 'https://example.com/no-author',
                    'description' => 'Test',
                    'content' => null,
                    'urlToImage' => null,
                    'publishedAt' => '2025-01-15T10:30:00Z',
                    'author' => null,
                ],
            ],
        ]),
    ]);

    $articles = $this->source->fetchArticles();
    expect($articles[0]->author)->toBeNull();
});

it('enforces rate limiting', function () {
    Config::set('news_sources.news_api_org.rate_limit.requests_per_minute', 1);

    Http::fake([
        'newsapi.org/*' => Http::response(['articles' => []]),
    ]);

    $this->source->fetchArticles();

    expect(fn () => $this->source->fetchArticles())
        ->toThrow(NewsSourceException::class, 'Rate limit exceeded');
});

it('throws after exhausting all retries', function () {
    Http::fake([
        'newsapi.org/*' => Http::response(null, 500),
    ]);

    expect(fn () => $this->source->fetchArticles())
        ->toThrow(NewsSourceException::class, 'Failed to fetch');
});
