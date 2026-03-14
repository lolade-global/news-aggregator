<?php

use App\Models\Article;
use App\Models\Author;
use App\Models\Category;

it('returns paginated articles', function () {
    Article::factory()->count(20)->create();

    $response = $this->getJson('/api/v1/articles');

    $response->assertOk()
        ->assertJsonStructure([
            'status',
            'message',
            'data' => [['id', 'title', 'description', 'url', 'source', 'published_at']],
            'pagination' => ['path', 'per_page', 'next_cursor', 'next_page_url'],
        ])
        ->assertJsonCount(15, 'data');
});

it('paginates with cursor', function () {
    Article::factory()->count(20)->create();

    $first = $this->getJson('/api/v1/articles?per_page=5');
    $first->assertOk()->assertJsonCount(5, 'data');

    $nextCursor = $first->json('pagination.next_cursor');
    expect($nextCursor)->not->toBeNull();

    $second = $this->getJson("/api/v1/articles?per_page=5&cursor={$nextCursor}");
    $second->assertOk()->assertJsonCount(5, 'data');

    $firstIds = collect($first->json('data'))->pluck('id');
    $secondIds = collect($second->json('data'))->pluck('id');
    expect($firstIds->intersect($secondIds))->toBeEmpty();
});

it('respects per_page parameter', function () {
    Article::factory()->count(10)->create();

    $this->getJson('/api/v1/articles?per_page=5')
        ->assertOk()
        ->assertJsonCount(5, 'data');
});

it('rejects per_page over 100', function () {
    $this->getJson('/api/v1/articles?per_page=200')
        ->assertStatus(422);
});

it('filters by title (partial match)', function () {
    Article::factory()->create(['title' => 'Laravel Framework News']);
    Article::factory()->create(['title' => 'React News Update']);

    $response = $this->getJson('/api/v1/articles?filter[title]=Laravel');

    $response->assertOk()->assertJsonCount(1, 'data');
    expect($response->json('data.0.title'))->toBe('Laravel Framework News');
});

it('filters by source (exact match)', function () {
    Article::factory()->create(['source' => 'guardian']);
    Article::factory()->create(['source' => 'new_york_times']);

    $response = $this->getJson('/api/v1/articles?filter[source]=guardian');

    $response->assertOk()->assertJsonCount(1, 'data');
    expect($response->json('data.0.source'))->toBe('guardian');
});

it('filters by author name (partial match)', function () {
    $article = Article::factory()->create();
    $author = Author::factory()->create(['name' => 'John Doe']);
    $article->authors()->attach($author);

    $other = Article::factory()->create();
    $otherAuthor = Author::factory()->create(['name' => 'Jane Smith']);
    $other->authors()->attach($otherAuthor);

    $response = $this->getJson('/api/v1/articles?filter[authors.name]=John');

    $response->assertOk()->assertJsonCount(1, 'data');
});

it('filters by category name (partial match)', function () {
    $article = Article::factory()->create();
    $category = Category::factory()->create(['name' => 'Technology']);
    $article->categories()->attach($category);

    $other = Article::factory()->create();
    $otherCategory = Category::factory()->create(['name' => 'Sports']);
    $other->categories()->attach($otherCategory);

    $response = $this->getJson('/api/v1/articles?filter[categories.name]=Tech');

    $response->assertOk()->assertJsonCount(1, 'data');
});

it('filters by date range', function () {
    Article::factory()->create(['published_at' => '2025-01-01']);
    Article::factory()->create(['published_at' => '2025-02-01']);
    Article::factory()->create(['published_at' => '2025-03-01']);

    $response = $this->getJson('/api/v1/articles?filter[date_from]=2025-01-15&filter[date_to]=2025-02-15');

    $response->assertOk()->assertJsonCount(1, 'data');
});

it('searches across title and description', function () {
    Article::factory()->create(['title' => 'Climate Change Report', 'description' => 'A study']);
    Article::factory()->create(['title' => 'Tech News', 'description' => 'Latest in tech']);

    $response = $this->getJson('/api/v1/articles?filter[search]=Climate');

    $response->assertOk()->assertJsonCount(1, 'data');
    expect($response->json('data.0.title'))->toBe('Climate Change Report');
});

it('includes authors when requested', function () {
    $article = Article::factory()->create();
    $article->authors()->attach(Author::factory()->create(['name' => 'Test Author']));

    $response = $this->getJson('/api/v1/articles?include=authors');

    $response->assertOk();
    expect($response->json('data.0.authors'))->toHaveCount(1);
    expect($response->json('data.0.authors.0.name'))->toBe('Test Author');
});

it('includes categories when requested', function () {
    $article = Article::factory()->create();
    $article->categories()->attach(Category::factory()->create(['name' => 'Tech']));

    $response = $this->getJson('/api/v1/articles?include=categories');

    $response->assertOk();
    expect($response->json('data.0.categories'))->toHaveCount(1);
    expect($response->json('data.0.categories.0.name'))->toBe('Tech');
});

it('sorts by published_at ascending', function () {
    Article::factory()->create(['published_at' => '2025-03-01', 'title' => 'Newer']);
    Article::factory()->create(['published_at' => '2025-01-01', 'title' => 'Older']);

    $response = $this->getJson('/api/v1/articles?sort=published_at');

    $response->assertOk();
    expect($response->json('data.0.title'))->toBe('Older');
});

it('sorts by published_at descending by default', function () {
    Article::factory()->create(['published_at' => '2025-01-01', 'title' => 'Older']);
    Article::factory()->create(['published_at' => '2025-03-01', 'title' => 'Newer']);

    $response = $this->getJson('/api/v1/articles');

    $response->assertOk();
    expect($response->json('data.0.title'))->toBe('Newer');
});

it('sorts by title', function () {
    Article::factory()->create(['title' => 'Zebra News']);
    Article::factory()->create(['title' => 'Alpha News']);

    $response = $this->getJson('/api/v1/articles?sort=title');

    $response->assertOk();
    expect($response->json('data.0.title'))->toBe('Alpha News');
});

it('rejects invalid filter', function () {
    $this->getJson('/api/v1/articles?filter[invalid_field]=foo')
        ->assertStatus(400);
});

it('rejects invalid sort', function () {
    $this->getJson('/api/v1/articles?sort=invalid_field')
        ->assertStatus(400);
});

it('returns empty data for no matches', function () {
    Article::factory()->count(3)->create();

    $this->getJson('/api/v1/articles?filter[title]=nonexistent_xyz')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('exposes ulid as id not auto-increment', function () {
    $article = Article::factory()->create();

    $response = $this->getJson('/api/v1/articles');

    $response->assertOk();
    expect($response->json('data.0.id'))->toBe($article->ulid);
});
