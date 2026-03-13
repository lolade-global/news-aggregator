<?php

namespace Database\Factories;

use App\Models\Article;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    protected $model = Article::class;

    public function definition(): array
    {
        return [
            'ulid' => Str::ulid()->toBase32(),
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'content' => fake()->paragraphs(3, true),
            'url' => fake()->unique()->url(),
            'image_url' => fake()->optional()->imageUrl(),
            'source' => fake()->randomElement(['guardian', 'new_york_times', 'news_api_org']),
            'source_url' => fake()->url(),
            'published_at' => fake()->dateTimeBetween('-1 month'),
        ];
    }
}
