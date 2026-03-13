<?php

namespace App\Services\NewsAggregator\Sources;

use App\DataTransferObjects\ArticleDto;
use App\Enums\NewsSourceEnum;
use Carbon\CarbonImmutable;

class NewsApiOrgSource extends AbstractNewsSource
{
    public function getIdentifier(): NewsSourceEnum
    {
        return NewsSourceEnum::NEWS_API_ORG;
    }

    public function getSourceName(): string
    {
        return 'NewsAPI.org';
    }

    protected function getEndpoint(): string
    {
        return 'everything';
    }

    protected function getQueryParams(): array
    {
        return [
            'apiKey' => $this->getApiKey(),
            'q' => 'news',
            'language' => 'en',
            'pageSize' => 100,
        ];
    }

    protected function getResponsePath(): string
    {
        return 'articles';
    }

    protected function mapToDto(array $item): ?ArticleDto
    {
        $title = $item['title'] ?? null;
        $url = $item['url'] ?? null;

        if (! $title || ! $url) {
            return null;
        }

        if ($title === '[Removed]') {
            return null;
        }

        return new ArticleDto(
            title: $title,
            description: $item['description'] ?? '',
            content: $item['content'] ?? null,
            url: $url,
            imageUrl: $item['urlToImage'] ?? null,
            publishedAt: CarbonImmutable::parse($item['publishedAt'] ?? now()),
            source: $this->getIdentifier()->value,
            sourceUrl: $this->getBaseUrl() ?? '',
            author: $item['author'] ?? null,
            category: 'Uncategorized',
        );
    }
}
