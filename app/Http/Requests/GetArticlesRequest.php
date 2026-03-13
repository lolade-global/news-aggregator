<?php

namespace App\Http\Requests;

use App\Enums\NewsSourceEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetArticlesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter.title' => ['sometimes', 'string', 'max:255'],
            'filter.source' => ['sometimes', 'string', Rule::in(NewsSourceEnum::values())],
            'filter.authors.name' => ['sometimes', 'string', 'max:255'],
            'filter.categories.name' => ['sometimes', 'string', 'max:255'],
            'filter.date_from' => ['sometimes', 'date'],
            'filter.date_to' => ['sometimes', 'date'],
            'filter.search' => ['sometimes', 'string', 'min:2', 'max:255'],
            'sort' => ['sometimes', 'string'],
            'include' => ['sometimes', 'string'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'cursor' => ['sometimes', 'string'],
        ];
    }
}
