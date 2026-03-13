<?php

use App\Services\NewsAggregator\Sources\GuardianSource;
use App\Services\NewsAggregator\Sources\NewsApiOrgSource;
use App\Services\NewsAggregator\Sources\NytSource;

return [

    'guardian' => [
        'enabled' => env('GUARDIAN_ENABLED', true),
        'api_key' => env('GUARDIAN_API_KEY'),
        'class' => GuardianSource::class,
        'base_url' => env('GUARDIAN_BASE_URL', 'https://content.guardianapis.com/'),
        'timeout' => env('GUARDIAN_TIMEOUT', 30),
        'retry_attempts' => env('GUARDIAN_RETRY_ATTEMPTS', 3),
        'rate_limit' => [
            'requests_per_minute' => env('GUARDIAN_RATE_LIMIT_MINUTE', 10),
            'requests_per_day' => env('GUARDIAN_RATE_LIMIT_DAY', 500),
        ],
    ],

    'new_york_times' => [
        'enabled' => env('NYT_ENABLED', true),
        'api_key' => env('NYT_API_KEY'),
        'class' => NytSource::class,
        'base_url' => env('NYT_BASE_URL', 'https://api.nytimes.com/svc/search/v2/'),
        'timeout' => env('NYT_TIMEOUT', 30),
        'retry_attempts' => env('NYT_RETRY_ATTEMPTS', 3),
        'rate_limit' => [
            'requests_per_minute' => env('NYT_RATE_LIMIT_MINUTE', 10),
            'requests_per_day' => env('NYT_RATE_LIMIT_DAY', 500),
        ],
    ],

    'news_api_org' => [
        'enabled' => env('NEWS_API_ORG_ENABLED', true),
        'api_key' => env('NEWS_API_ORG_API_KEY'),
        'class' => NewsApiOrgSource::class,
        'base_url' => env('NEWS_API_ORG_BASE_URL', 'https://newsapi.org/v2/'),
        'timeout' => env('NEWS_API_ORG_TIMEOUT', 30),
        'retry_attempts' => env('NEWS_API_ORG_RETRY_ATTEMPTS', 3),
        'rate_limit' => [
            'requests_per_minute' => env('NEWS_API_ORG_RATE_LIMIT_MINUTE', 10),
            'requests_per_day' => env('NEWS_API_ORG_RATE_LIMIT_DAY', 100),
        ],
    ],

];
