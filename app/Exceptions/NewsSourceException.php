<?php

namespace App\Exceptions;

use RuntimeException;

class NewsSourceException extends RuntimeException
{
    public static function configurationMissing(string $sourceName): self
    {
        return new self("News source [{$sourceName}] is not properly configured. Check API key and base URL.");
    }

    public static function fetchFailed(string $sourceName, string $reason): self
    {
        return new self("Failed to fetch articles from [{$sourceName}]: {$reason}");
    }

    public static function rateLimitExceeded(string $sourceName): self
    {
        return new self("Rate limit exceeded for news source [{$sourceName}].");
    }
}
