<?php

namespace App\Http\Resources;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/** @mixin Article */
class ArticleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'title' => $this->title,
            'description' => $this->description,
            'content' => $this->content,
            'url' => $this->url,
            'image_url' => $this->image_url,
            'source' => $this->source,
            'source_url' => $this->source_url,
            'published_at' => Carbon::parse($this->published_at)->toIso8601String(),
            'authors' => AuthorResource::collection($this->whenLoaded('authors')),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
        ];
    }
}
