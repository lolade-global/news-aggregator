<?php

namespace App\Providers;

use App\Contracts\NewsSourceContract;
use App\Services\NewsAggregator\NewsAggregatorService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class NewsSourceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NewsAggregatorService::class, function () {
            $aggregator = new NewsAggregatorService;

            foreach (config('news_sources', []) as $key => $sourceConfig) {
                $class = $sourceConfig['class'] ?? null;

                if (! $class || ! class_exists($class)) {
                    Log::warning("News source '{$key}': class not found or not specified.");

                    continue;
                }

                if (! is_subclass_of($class, NewsSourceContract::class)) {
                    Log::warning("News source '{$key}': class does not implement NewsSourceContract.");

                    continue;
                }

                $source = new $class;

                if (! $source->isConfigured()) {
                    Log::info("News source '{$key}': skipped (not configured).");

                    continue;
                }

                $aggregator->addNewsSource($source);
            }

            return $aggregator;
        });
    }
}
