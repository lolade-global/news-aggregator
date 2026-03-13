<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\FetchArticlesAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\GetArticlesRequest;
use App\Http\Resources\ArticleResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class ArticleController extends Controller
{
    public function index(GetArticlesRequest $request, FetchArticlesAction $action): JsonResponse
    {
        $articles = $action->execute($request);

        return ApiResponse::success(
            message: 'Articles retrieved successfully',
            data: ArticleResource::collection($articles),
        );
    }
}
