<?php

use App\Contracts\NewsSourceContract;
use App\DataTransferObjects\ArticleDto;
use App\Enums\NewsSourceEnum;
use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Services\NewsAggregator\NewsAggregatorService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeDto(array $overrides = []): ArticleDto
{
    return new ArticleDto(
        title: $overrides['title'] ?? 'Test Article',
        description: $overrides['description'] ?? 'A description',
        content: $overrides['content'] ?? 'Content',
        url: $overrides['url'] ?? 'https://example.com/'.uniqid(),
        imageUrl: $overrides['imageUrl'] ?? null,
        publishedAt: $overrides['publishedAt'] ?? CarbonImmutable::now(),
        source: $overrides['source'] ?? 'guardian',
        sourceUrl: $overrides['sourceUrl'] ?? 'https://api.example.com',
        author: $overrides['author'] ?? null,
        category: $overrides['category'] ?? null,
    );
}

function mockSource(array $dtos = [], NewsSourceEnum $identifier = NewsSourceEnum::GUARDIAN): NewsSourceContract
{
    $source = Mockery::mock(NewsSourceContract::class);
    $source->shouldReceive('fetchArticles')->andReturn(collect($dtos));
    $source->shouldReceive('getIdentifier')->andReturn($identifier);
    $source->shouldReceive('getSourceName')->andReturn($identifier->label());
    $source->shouldReceive('isConfigured')->andReturn(true);
    $source->allows()->and($source);

    return $source;
}

it('registers a news source', function () {
    $service = new NewsAggregatorService;
    $source = mockSource();

    $service->addNewsSource($source);

    expect($service->getSources())->toHaveCount(1);
});

it('prevents duplicate source registration', function () {
    $service = new NewsAggregatorService;
    $source = mockSource();

    $service->addNewsSource($source);
    $service->addNewsSource($source);

    expect($service->getSources())->toHaveCount(1);
});

it('checks if source exists by identifier', function () {
    $service = new NewsAggregatorService;
    $source = mockSource();

    $service->addNewsSource($source);

    expect($service->hasSource('guardian'))->toBeTrue();
    expect($service->hasSource('nonexistent'))->toBeFalse();
});

it('fetches and stores articles in database', function () {
    $service = new NewsAggregatorService;
    $dtos = [
        makeDto(['url' => 'https://example.com/1', 'title' => 'First']),
        makeDto(['url' => 'https://example.com/2', 'title' => 'Second']),
        makeDto(['url' => 'https://example.com/3', 'title' => 'Third']),
    ];
    $source = mockSource($dtos);

    $service->fetchAndProcessFromSource($source);

    expect(Article::count())->toBe(3);
});

it('updates existing articles by URL without duplicating', function () {
    $service = new NewsAggregatorService;

    $source1 = mockSource([makeDto(['url' => 'https://example.com/same', 'title' => 'Original'])]);
    $service->fetchAndProcessFromSource($source1);

    $source2 = mockSource([makeDto(['url' => 'https://example.com/same', 'title' => 'Updated'])]);
    $service->fetchAndProcessFromSource($source2);

    expect(Article::count())->toBe(1);
    expect(Article::first()->title)->toBe('Updated');
});

it('creates and syncs authors from comma-separated string', function () {
    $service = new NewsAggregatorService;
    $source = mockSource([makeDto(['author' => 'John Doe, Jane Smith'])]);

    $service->fetchAndProcessFromSource($source);

    expect(Author::count())->toBe(2);
    expect(Article::first()->authors)->toHaveCount(2);
});

it('creates and syncs categories', function () {
    $service = new NewsAggregatorService;
    $source = mockSource([makeDto(['category' => 'Tech, Science'])]);

    $service->fetchAndProcessFromSource($source);

    expect(Category::count())->toBe(2);
    expect(Article::first()->categories)->toHaveCount(2);
});

it('handles articles without authors or categories', function () {
    $service = new NewsAggregatorService;
    $source = mockSource([makeDto(['author' => null, 'category' => null])]);

    $service->fetchAndProcessFromSource($source);

    expect(Article::count())->toBe(1);
    expect(Article::first()->authors)->toHaveCount(0);
    expect(Article::first()->categories)->toHaveCount(0);
});

it('does not create duplicate authors or categories', function () {
    $service = new NewsAggregatorService;
    $source = mockSource([
        makeDto(['url' => 'https://example.com/a', 'author' => 'John Doe', 'category' => 'Tech']),
        makeDto(['url' => 'https://example.com/b', 'author' => 'John Doe', 'category' => 'Tech']),
    ]);

    $service->fetchAndProcessFromSource($source);

    expect(Author::count())->toBe(1);
    expect(Category::count())->toBe(1);
});

it('trims whitespace from author and category names', function () {
    $service = new NewsAggregatorService;
    $source = mockSource([makeDto(['author' => '  John Doe , Jane Smith  ', 'category' => ' Tech , Science '])]);

    $service->fetchAndProcessFromSource($source);

    expect(Author::pluck('name')->sort()->values()->all())->toBe(['Jane Smith', 'John Doe']);
    expect(Category::pluck('name')->sort()->values()->all())->toBe(['Science', 'Tech']);
});
