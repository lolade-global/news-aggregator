<?php

namespace App\Services\NewsAggregator\Sources;

use App\Contracts\NewsSourceContract;
use App\DataTransferObjects\ArticleDto;
use App\Exceptions\NewsSourceException;
use App\Services\NewsAggregator\Concerns\HasRateLimiting;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;

abstract class AbstractNewsSource implements NewsSourceContract
{
    use HasRateLimiting;

    abstract protected function getEndpoint(): string;

    /**
     * @return array<string, mixed>
     */
    abstract protected function getQueryParams(): array;

    abstract protected function getResponsePath(): string;

    abstract protected function mapToDto(array $item): ?ArticleDto;

    public function isEnabled(): bool
    {
        return (bool) config("news_sources.{$this->getIdentifier()->value}.enabled", false);
    }

    public function isConfigured(): bool
    {
        return $this->isEnabled()
            && ! empty($this->getApiKey())
            && ! empty($this->getBaseUrl());
    }

    public function getApiKey(): ?string
    {
        return config("news_sources.{$this->getIdentifier()->value}.api_key");
    }

    public function getBaseUrl(): ?string
    {
        return config("news_sources.{$this->getIdentifier()->value}.base_url");
    }

    /**
     * @return array{requests_per_minute: int, requests_per_day: int}
     */
    public function getRateLimits(): array
    {
        $config = config("news_sources.{$this->getIdentifier()->value}.rate_limit", []);

        return [
            'requests_per_minute' => $config['requests_per_minute'] ?? 10,
            'requests_per_day' => $config['requests_per_day'] ?? 500,
        ];
    }

    protected function getTimeout(): int
    {
        return (int) config("news_sources.{$this->getIdentifier()->value}.timeout", 30);
    }

    protected function getRetryAttempts(): int
    {
        return (int) config("news_sources.{$this->getIdentifier()->value}.retry_attempts", 3);
    }

    /**
     * @return array<int>
     */
    protected function getRetryDelays(): array
    {
        return config('news_aggregator.retry_delays', [1000, 3000, 9000]);
    }

    public function fetchArticles(): Collection
    {
        $response = $this->fetchArticleFromApi();

        $items = Arr::get($response, $this->getResponsePath(), []);

        return LazyCollection::make($items)
            ->map(function (array $item) {
                try {
                    return $this->mapToDto($item);
                } catch (\Throwable $e) {
                    Log::warning("Failed to map article from {$this->getSourceName()}: {$e->getMessage()}");

                    return null;
                }
            })
            ->filter()
            ->values()
            ->collect();
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchArticleFromApi(): array
    {
        return $this->withRateLimit(fn () => $this->fetchWithRetry());
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchWithRetry(): array
    {
        $attempts = $this->getRetryAttempts();
        $delays = $this->getRetryDelays();
        $lastException = null;

        for ($i = 0; $i < $attempts; $i++) {
            try {
                return $this->makeApiRequest();
            } catch (\Throwable $e) {
                $lastException = $e;

                Log::warning("Attempt ".($i + 1)."/{$attempts} failed for {$this->getSourceName()}: {$e->getMessage()}");

                if ($i < $attempts - 1) {
                    $delay = $delays[$i] ?? end($delays);
                    usleep($delay * 1000);
                }
            }
        }

        throw NewsSourceException::fetchFailed(
            $this->getSourceName(),
            $lastException?->getMessage() ?? 'Unknown error',
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function makeApiRequest(): array
    {
        $url = rtrim($this->getBaseUrl(), '/').'/'.$this->getEndpoint();

        $response = Http::timeout($this->getTimeout())
            ->get($url, $this->getQueryParams());

        $response->throw();

        return $response->json() ?? [];
    }
}
