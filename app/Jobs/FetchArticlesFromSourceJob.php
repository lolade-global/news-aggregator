<?php

namespace App\Jobs;

use App\Contracts\NewsSourceContract;
use App\Services\NewsAggregator\NewsAggregatorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class FetchArticlesFromSourceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public bool $failOnTimeout = true;

    public function __construct(
        private readonly NewsSourceContract $source
    ) {}

    public function handle(NewsAggregatorService $aggregator): void
    {
        if (! $this->source->isConfigured()) {
            Log::warning("Source {$this->source->getSourceName()} not configured, skipping.");

            return;
        }

        $aggregator->fetchAndProcessFromSource($this->source);
    }

    /**
     * @return array<int>
     */
    public function backoff(): array
    {
        return [10, 30, 90];
    }

    /**
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'news-aggregator',
            'source:'.$this->source->getIdentifier()->value,
        ];
    }
}
