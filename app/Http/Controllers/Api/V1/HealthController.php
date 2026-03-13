<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\NewsAggregator\NewsAggregatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(NewsAggregatorService $aggregator): JsonResponse
    {
        $dbHealthy = true;

        try {
            DB::connection()->getPdo();
        } catch (\Exception) {
            $dbHealthy = false;
        }

        $sources = $aggregator->getSources()->map(fn ($source) => [
            'name' => $source->getSourceName(),
            'identifier' => $source->getIdentifier()->value,
            'configured' => $source->isConfigured(),
        ])->values();

        return ApiResponse::success('Health check', [
            'status' => $dbHealthy ? 'healthy' : 'degraded',
            'database' => $dbHealthy,
            'sources' => $sources,
        ]);
    }
}
