<?php

namespace App\Services\NewsAggregator\Sources;

use App\DataTransferObjects\ArticleDto;
use App\Enums\NewsSourceEnum;
use Carbon\CarbonImmutable;

class NytSource extends AbstractNewsSource
{
    public function getIdentifier(): NewsSourceEnum
    {
        return NewsSourceEnum::NEW_YORK_TIMES;
    }

    public function getSourceName(): string
    {
        return 'New York Times';
    }

    protected function getEndpoint(): string
    {
        return 'articlesearch.json';
    }

    protected function getQueryParams(): array
    {
        return [
            'api-key' => $this->getApiKey(),
            'q' => 'news',
        ];
    }

    protected function getResponsePath(): string
    {
        return 'response.docs';
    }

    protected function mapToDto(array $item): ?ArticleDto
    {
        $title = $item['headline']['main'] ?? null;
        $url = $item['web_url'] ?? null;

        if (! $title || ! $url) {
            return null;
        }

        return new ArticleDto(
            title: $title,
            description: $item['abstract'] ?? '',
            content: $item['lead_paragraph'] ?? null,
            url: $url,
            imageUrl: $this->extractImageUrl($item),
            publishedAt: $this->parsePublishedDate($item['pub_date'] ?? null),
            source: $this->getIdentifier()->value,
            sourceUrl: $this->getBaseUrl() ?? '',
            author: $this->extractAuthor($item),
            category: $this->extractCategory($item),
        );
    }

    private function extractImageUrl(array $item): ?string
    {
        $multimedia = $item['multimedia'] ?? [];

        if (empty($multimedia)) {
            return null;
        }

        $imageUrl = $multimedia[0]['url'] ?? null;

        if (! $imageUrl) {
            return null;
        }

        if (! str_starts_with((string) $imageUrl, 'http')) {
            return 'https://www.nytimes.com/'.$imageUrl;
        }

        return $imageUrl;
    }

    private function extractAuthor(array $item): ?string
    {
        $byline = $item['byline']['original'] ?? null;

        if (! $byline) {
            return null;
        }

        return preg_replace('/^By\s+/i', '', (string) $byline);
    }

    private function extractCategory(array $item): string
    {
        return $item['news_desk'] ?? $item['section_name'] ?? 'Uncategorized';
    }

    private function parsePublishedDate(?string $date): CarbonImmutable
    {
        if (! $date) {
            return CarbonImmutable::now();
        }

        return CarbonImmutable::parse($date);
    }
}
