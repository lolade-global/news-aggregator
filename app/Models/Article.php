<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class Article extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'title',
        'description',
        'content',
        'url',
        'image_url',
        'source',
        'source_url',
        'published_at',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::flush());
        static::deleted(fn () => Cache::flush());
    }

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class, 'article_author');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'article_category');
    }

    public function scopePublishedAfter(Builder $query, string $date): Builder
    {
        return $query->where('published_at', '>=', Carbon::parse($date)->startOfDay());
    }

    public function scopePublishedBefore(Builder $query, string $date): Builder
    {
        return $query->where('published_at', '<=', Carbon::parse($date)->endOfDay());
    }

    public function scopeFullTextSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $q) use ($term) {
            $q->where('title', 'LIKE', "%{$term}%")
                ->orWhere('description', 'LIKE', "%{$term}%");
        });
    }
}
