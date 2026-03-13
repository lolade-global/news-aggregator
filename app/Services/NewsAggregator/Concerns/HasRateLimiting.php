<?php

namespace App\Services\NewsAggregator\Concerns;

use App\Exceptions\NewsSourceException;
use Illuminate\Support\Facades\RateLimiter;

trait HasRateLimiting
{
    protected function withRateLimit(callable $callback): mixed
    {
        $identifier = $this->getIdentifier()->value;
        $limits = $this->getRateLimits();

        $minuteKey = "news:{$identifier}:minute";
        $dayKey = "news:{$identifier}:day";

        if (RateLimiter::tooManyAttempts($minuteKey, $limits['requests_per_minute'])) {
            throw NewsSourceException::rateLimitExceeded($this->getSourceName());
        }

        if (RateLimiter::tooManyAttempts($dayKey, $limits['requests_per_day'])) {
            throw NewsSourceException::rateLimitExceeded($this->getSourceName());
        }

        RateLimiter::hit($minuteKey, 60);
        RateLimiter::hit($dayKey, 86400);

        return $callback();
    }

    public function isWithinRateLimit(): bool
    {
        $identifier = $this->getIdentifier()->value;
        $limits = $this->getRateLimits();

        return ! RateLimiter::tooManyAttempts("news:{$identifier}:minute", $limits['requests_per_minute'])
            && ! RateLimiter::tooManyAttempts("news:{$identifier}:day", $limits['requests_per_day']);
    }

    public function resetRateLimits(): void
    {
        $identifier = $this->getIdentifier()->value;

        RateLimiter::clear("news:{$identifier}:minute");
        RateLimiter::clear("news:{$identifier}:day");
    }
}
