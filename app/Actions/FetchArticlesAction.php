<?php

namespace App\Actions;

use App\Http\Requests\GetArticlesRequest;
use App\Models\Article;
use App\Services\NewsAggregator\CacheKeyGenerator;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Cache;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class FetchArticlesAction
{
    public function execute(GetArticlesRequest $request): CursorPaginator
    {
        $cacheKey = CacheKeyGenerator::forArticleQuery($request);

        return Cache::flexible($cacheKey, [300, 600], function () use ($request) {
            return QueryBuilder::for(Article::class)
                ->allowedFilters([
                    AllowedFilter::partial('title'),
                    AllowedFilter::exact('source'),
                    AllowedFilter::partial('authors.name'),
                    AllowedFilter::partial('categories.name'),
                    AllowedFilter::scope('date_from', 'publishedAfter'),
                    AllowedFilter::scope('date_to', 'publishedBefore'),
                    AllowedFilter::scope('search', 'fullTextSearch'),
                ])
                ->allowedIncludes(['authors', 'categories'])
                ->allowedSorts(['title', 'source', 'published_at', 'created_at'])
                ->defaultSort('-published_at')
                ->cursorPaginate($request->input('per_page', 15))
                ->withQueryString();
        });
    }
}
