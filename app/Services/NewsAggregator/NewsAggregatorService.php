<?php

namespace App\Services\NewsAggregator;

use App\Contracts\NewsSourceContract;
use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NewsAggregatorService
{
    /** @var Collection<int, NewsSourceContract> */
    private Collection $sources;

    public function __construct()
    {
        $this->sources = collect();
    }

    public function addNewsSource(NewsSourceContract $source): self
    {
        if (! $this->hasSource($source->getIdentifier()->value)) {
            $this->sources->push($source);
        }

        return $this;
    }

    public function hasSource(string $identifier): bool
    {
        return $this->sources->contains(fn (NewsSourceContract $s) => $s->getIdentifier()->value === $identifier);
    }

    /**
     * @return Collection<int, NewsSourceContract>
     */
    public function getSources(): Collection
    {
        return $this->sources;
    }

    public function fetchAndProcessFromSource(NewsSourceContract $source): void
    {
        Log::info("Fetching articles from {$source->getSourceName()}...");

        $articles = $source->fetchArticles();

        Log::info("Fetched {$articles->count()} articles from {$source->getSourceName()}.");

        $chunkSize = (int) config('news_aggregator.chunk_size', 50);

        $articles->chunk($chunkSize)->each(function (Collection $chunk) {
            $this->saveArticles($chunk);
        });
    }

    private function saveArticles(Collection $chunk): void
    {
        DB::transaction(function () use ($chunk) {
            $chunk->each(function ($dto) {
                $article = Article::updateOrCreate(
                    ['url' => $dto->url],
                    $dto->toArticleArray(),
                );

                $this->syncAuthors($article, $dto->getAuthors());
                $this->syncCategories($article, $dto->getCategories());
            });
        });
    }

    /**
     * @param  array<string>  $authorNames
     */
    private function syncAuthors(Article $article, array $authorNames): void
    {
        if (empty($authorNames)) {
            $article->authors()->detach();

            return;
        }

        $authorIds = collect($authorNames)->map(function (string $name) {
            return Author::firstOrCreate(['name' => $name])->id;
        })->all();

        $article->authors()->sync($authorIds);
    }

    /**
     * @param  array<string>  $categoryNames
     */
    private function syncCategories(Article $article, array $categoryNames): void
    {
        if (empty($categoryNames)) {
            $article->categories()->detach();

            return;
        }

        $categoryIds = collect($categoryNames)->map(function (string $name) {
            return Category::firstOrCreate(['name' => $name])->id;
        })->all();

        $article->categories()->sync($categoryIds);
    }
}
