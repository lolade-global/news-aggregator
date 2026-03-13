<?php

namespace App\Enums;

enum NewsSourceEnum: string
{
    case GUARDIAN = 'guardian';
    case NEW_YORK_TIMES = 'new_york_times';
    case NEWS_API_ORG = 'news_api_org';

    public function label(): string
    {
        return match ($this) {
            self::GUARDIAN => 'The Guardian',
            self::NEW_YORK_TIMES => 'New York Times',
            self::NEWS_API_ORG => 'NewsAPI.org',
        };
    }

    public function configKey(): string
    {
        return $this->value;
    }

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
