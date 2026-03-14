<?php

use App\Enums\NewsSourceEnum;
use App\Exceptions\NewsSourceException;
use App\Services\NewsAggregator\Sources\NytSource;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Config::set('news_sources.new_york_times', [
        'enabled' => true,
        'api_key' => 'test-key',
        'base_url' => 'https://api.nytimes.com/svc/search/v2/',
        'timeout' => 5,
        'retry_attempts' => 3,
        'rate_limit' => [
            'requests_per_minute' => 10,
            'requests_per_day' => 100,
        ],
    ]);
    Config::set('news_aggregator.retry_delays', [0, 0, 0]);

    $this->source = new NytSource;
    $this->source->resetRateLimits();
});

it('returns the correct identifier', function () {
    expect($this->source->getIdentifier())->toBe(NewsSourceEnum::NEW_YORK_TIMES);
});

it('returns the correct source name', function () {
    expect($this->source->getSourceName())->toBe('New York Times');
});

it('reports configured when enabled with API key and base URL', function () {
    expect($this->source->isConfigured())->toBeTrue();
});

it('reports not configured without API key', function () {
    Config::set('news_sources.new_york_times.api_key', null);
    expect($this->source->isConfigured())->toBeFalse();
});

it('fetches and maps articles successfully', function () {
    Http::fake([
        'api.nytimes.com/*' => Http::response([
            'response' => [
                'docs' => [
                    [
                        'headline' => ['main' => 'NYT Test Article'],
                        'web_url' => 'https://nytimes.com/article-1',
                        'abstract' => 'A test abstract',
                        'lead_paragraph' => 'Lead paragraph content',
                        'pub_date' => '2025-01-15T10:30:00Z',
                        'news_desk' => 'Foreign',
                        'byline' => ['original' => 'By Jane Smith'],
                        'multimedia' => [
                            ['url' => 'images/2025/photo.jpg'],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $articles = $this->source->fetchArticles();

    expect($articles)->toHaveCount(1);
    expect($articles[0]->title)->toBe('NYT Test Article');
    expect($articles[0]->description)->toBe('A test abstract');
    expect($articles[0]->content)->toBe('Lead paragraph content');
    expect($articles[0]->author)->toBe('Jane Smith');
    expect($articles[0]->category)->toBe('Foreign');
    expect($articles[0]->imageUrl)->toBe('https://www.nytimes.com/images/2025/photo.jpg');
    expect($articles[0]->source)->toBe('new_york_times');
});

it('handles empty multimedia array', function () {
    Http::fake([
        'api.nytimes.com/*' => Http::response([
            'response' => [
                'docs' => [
                    [
                        'headline' => ['main' => 'No Image Article'],
                        'web_url' => 'https://nytimes.com/article-2',
                        'abstract' => 'Test',
                        'lead_paragraph' => 'Test',
                        'pub_date' => '2025-01-15T10:30:00Z',
                        'news_desk' => 'Tech',
                        'byline' => ['original' => null],
                        'multimedia' => [],
                    ],
                ],
            ],
        ]),
    ]);

    $articles = $this->source->fetchArticles();
    expect($articles[0]->imageUrl)->toBeNull();
    expect($articles[0]->author)->toBeNull();
});

it('falls back category from news_desk to section_name', function () {
    Http::fake([
        'api.nytimes.com/*' => Http::response([
            'response' => [
                'docs' => [
                    [
                        'headline' => ['main' => 'Fallback Article'],
                        'web_url' => 'https://nytimes.com/article-3',
                        'abstract' => 'Test',
                        'lead_paragraph' => 'Test',
                        'pub_date' => '2025-01-15T10:30:00Z',
                        'section_name' => 'Arts',
                        'byline' => [],
                        'multimedia' => [],
                    ],
                ],
            ],
        ]),
    ]);

    $articles = $this->source->fetchArticles();
    expect($articles[0]->category)->toBe('Arts');
});

it('enforces rate limiting', function () {
    Config::set('news_sources.new_york_times.rate_limit.requests_per_minute', 1);

    Http::fake([
        'api.nytimes.com/*' => Http::response(['response' => ['docs' => []]]),
    ]);

    $this->source->fetchArticles();

    expect(fn () => $this->source->fetchArticles())
        ->toThrow(NewsSourceException::class, 'Rate limit exceeded');
});

it('throws after exhausting all retries', function () {
    Http::fake([
        'api.nytimes.com/*' => Http::response(null, 500),
    ]);

    expect(fn () => $this->source->fetchArticles())
        ->toThrow(NewsSourceException::class, 'Failed to fetch');
});
