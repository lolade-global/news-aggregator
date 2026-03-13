<?php

namespace App\Services\NewsAggregator\Sources;

use App\DataTransferObjects\ArticleDto;
use App\Enums\NewsSourceEnum;
use Carbon\CarbonImmutable;

class GuardianSource extends AbstractNewsSource
{
    public function getIdentifier(): NewsSourceEnum
    {
        return NewsSourceEnum::GUARDIAN;
    }

    public function getSourceName(): string
    {
        return 'The Guardian';
    }

    protected function getEndpoint(): string
    {
        return 'search';
    }

    protected function getQueryParams(): array
    {
        return [
            'api-key' => $this->getApiKey(),
            'show-fields' => 'all',
            'page-size' => 100,
            'order-by' => 'newest',
        ];
    }

    protected function getResponsePath(): string
    {
        return 'response.results';
    }

    protected function mapToDto(array $item): ?ArticleDto
    {
        $fields = $item['fields'] ?? [];

        $title = $item['webTitle'] ?? null;
        $url = $item['webUrl'] ?? null;

        if (! $title || ! $url) {
            return null;
        }

        return new ArticleDto(
            title: $title,
            description: $fields['trailText'] ?? '',
            content: $fields['bodyText'] ?? null,
            url: $url,
            imageUrl: $fields['thumbnail'] ?? null,
            publishedAt: CarbonImmutable::parse($item['webPublicationDate'] ?? now()),
            source: $this->getIdentifier()->value,
            sourceUrl: $this->getBaseUrl() ?? '',
            author: $fields['byline'] ?? null,
            category: $item['sectionName'] ?? null,
        );
    }
}
