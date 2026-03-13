<?php

namespace App\Contracts;

use App\Enums\NewsSourceEnum;
use Illuminate\Support\Collection;

interface NewsSourceContract
{
    public function fetchArticles(): Collection;

    public function getIdentifier(): NewsSourceEnum;

    public function getSourceName(): string;

    public function isEnabled(): bool;

    public function isConfigured(): bool;

    public function getApiKey(): ?string;

    public function getBaseUrl(): ?string;

    /**
     * @return array{requests_per_minute: int, requests_per_day: int}
     */
    public function getRateLimits(): array;
}
