<?php

namespace App\Console\Commands;

use App\Enums\NewsSourceEnum;
use App\Jobs\FetchArticlesFromSourceJob;
use App\Services\NewsAggregator\NewsAggregatorService;
use Illuminate\Console\Command;

class FetchNewsCommand extends Command
{
    protected $signature = 'news:fetch {--source= : Fetch from a specific source (guardian, new_york_times, news_api_org)}';

    protected $description = 'Fetch news articles from configured sources';

    public function handle(NewsAggregatorService $aggregator): int
    {
        $sources = $aggregator->getSources();

        if ($sources->isEmpty()) {
            $this->error('No configured news sources found. Check your API keys in .env');

            return self::FAILURE;
        }

        $sourceOption = $this->option('source');

        if ($sourceOption) {
            $enum = NewsSourceEnum::tryFrom($sourceOption);

            if (! $enum) {
                $this->error("Invalid source: {$sourceOption}. Valid options: ".implode(', ', NewsSourceEnum::values()));

                return self::FAILURE;
            }

            $sources = $sources->filter(
                fn ($s) => $s->getIdentifier() === $enum
            );

            if ($sources->isEmpty()) {
                $this->error("Source '{$sourceOption}' is not configured. Check your API key in .env");

                return self::FAILURE;
            }
        }

        $this->table(
            ['Source', 'Identifier', 'Configured'],
            $sources->map(fn ($s) => [
                $s->getSourceName(),
                $s->getIdentifier()->value,
                $s->isConfigured() ? 'Yes' : 'No',
            ])->toArray()
        );

        $dispatched = 0;

        foreach ($sources as $source) {
            FetchArticlesFromSourceJob::dispatch($source);
            $dispatched++;
        }

        $this->info("Dispatched {$dispatched} fetch job(s).");

        return self::SUCCESS;
    }
}
