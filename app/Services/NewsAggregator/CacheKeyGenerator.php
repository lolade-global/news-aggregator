<?php

namespace App\Services\NewsAggregator;

use Illuminate\Http\Request;

class CacheKeyGenerator
{
    public static function forArticleQuery(Request $request): string
    {
        $params = $request->only(['filter', 'sort', 'include', 'per_page', 'cursor']);
        ksort($params);

        return 'articles:query:'.md5(json_encode($params));
    }
}
