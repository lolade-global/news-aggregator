<?php

namespace App\DataTransferObjects;

use Carbon\CarbonImmutable;

final readonly class ArticleDto
{
    public function __construct(
        public string $title,
        public string $description,
        public ?string $content,
        public string $url,
        public ?string $imageUrl,
        public CarbonImmutable $publishedAt,
        public string $source,
        public string $sourceUrl,
        public ?string $author,
        public ?string $category,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArticleArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'content' => $this->content,
            'url' => $this->url,
            'image_url' => $this->imageUrl,
            'source' => $this->source,
            'source_url' => $this->sourceUrl,
            'published_at' => $this->publishedAt,
        ];
    }

    /**
     * @return array<string>
     */
    public function getAuthors(): array
    {
        if ($this->author === null || trim($this->author) === '') {
            return [];
        }

        return array_filter(
            array_map(trim(...), explode(',', $this->author)),
            fn (string $name) => $name !== '',
        );
    }

    /**
     * @return array<string>
     */
    public function getCategories(): array
    {
        if ($this->category === null || trim($this->category) === '') {
            return [];
        }

        return array_filter(
            array_map(trim(...), explode(',', $this->category)),
            fn (string $name) => $name !== '',
        );
    }
}
