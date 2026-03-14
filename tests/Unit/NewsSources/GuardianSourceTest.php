<?php

use App\Enums\NewsSourceEnum;
use App\Exceptions\NewsSourceException;
use App\Services\NewsAggregator\Sources\GuardianSource;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    Config::set('news_sources.guardian', [
        'enabled' => true,
        'api_key' => 'test-key',
        'base_url' => 'https://content.guardianapis.com/',
        'timeout' => 5,
        'retry_attempts' => 3,
        'rate_limit' => [
            'requests_per_minute' => 10,
            'requests_per_day' => 100,
        ],
    ]);
    Config::set('news_aggregator.retry_delays', [0, 0, 0]);

    $this->source = new GuardianSource;
    $this->source->resetRateLimits();
});

it('returns the correct identifier', function () {
    expect($this->source->getIdentifier())->toBe(NewsSourceEnum::GUARDIAN);
});

it('returns the correct source name', function () {
    expect($this->source->getSourceName())->toBe('The Guardian');
});

it('reports enabled status from config', function () {
    expect($this->source->isEnabled())->toBeTrue();

    Config::set('news_sources.guardian.enabled', false);
    expect($this->source->isEnabled())->toBeFalse();
});

it('reports configured when enabled with API key and base URL', function () {
    expect($this->source->isConfigured())->toBeTrue();
});

it('reports not configured without API key', function () {
    Config::set('news_sources.guardian.api_key', null);
    expect($this->source->isConfigured())->toBeFalse();
});

it('fetches and maps articles successfully', function () {
    Http::fake([
        'content.guardianapis.com/*' => Http::response([
            'response' => [
                'results' => [
                    [
                        'webTitle' => 'Test Article',
                        'webUrl' => 'https://guardian.com/article-1',
                        'webPublicationDate' => '2025-01-15T10:30:00Z',
                        'sectionName' => 'Technology',
                        'fields' => [
                            'trailText' => 'A test description',
                            'bodyText' => 'Full article content',
                            'thumbnail' => 'https://guardian.com/image.jpg',
                            'byline' => 'John Doe',
                        ],
                    ],
                    [
                        'webTitle' => 'Second Article',
                        'webUrl' => 'https://guardian.com/article-2',
                        'webPublicationDate' => '2025-01-16T08:00:00Z',
                        'sectionName' => 'World news',
                        'fields' => [
                            'trailText' => 'Another description',
                            'bodyText' => null,
                            'thumbnail' => null,
                            'byline' => null,
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $articles = $this->source->fetchArticles();

    expect($articles)->toHaveCount(2);
    expect($articles[0]->title)->toBe('Test Article');
    expect($articles[0]->url)->toBe('https://guardian.com/article-1');
    expect($articles[0]->description)->toBe('A test description');
    expect($articles[0]->content)->toBe('Full article content');
    expect($articles[0]->imageUrl)->toBe('https://guardian.com/image.jpg');
    expect($articles[0]->author)->toBe('John Doe');
    expect($articles[0]->category)->toBe('Technology');
    expect($articles[0]->source)->toBe('guardian');

    expect($articles[1]->imageUrl)->toBeNull();
    expect($articles[1]->author)->toBeNull();
});

it('enforces rate limiting', function () {
    Config::set('news_sources.guardian.rate_limit.requests_per_minute', 1);

    Http::fake([
        'content.guardianapis.com/*' => Http::response(['response' => ['results' => []]]),
    ]);

    $this->source->fetchArticles();

    expect(fn () => $this->source->fetchArticles())
        ->toThrow(NewsSourceException::class, 'Rate limit exceeded');
});

it('retries on failure up to configured attempts', function () {
    $attempt = 0;
    Http::fake(function () use (&$attempt) {
        $attempt++;
        if ($attempt < 3) {
            return Http::response(null, 500);
        }

        return Http::response(['response' => ['results' => []]], 200);
    });

    $articles = $this->source->fetchArticles();

    expect($articles)->toHaveCount(0);
    expect($attempt)->toBe(3);
});

it('throws after exhausting all retries', function () {
    Http::fake([
        'content.guardianapis.com/*' => Http::response(null, 500),
    ]);

    expect(fn () => $this->source->fetchArticles())
        ->toThrow(NewsSourceException::class, 'Failed to fetch');
});
