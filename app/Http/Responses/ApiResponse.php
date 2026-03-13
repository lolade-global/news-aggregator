<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\CursorPaginator;

class ApiResponse
{
    public static function success(string $message, mixed $data = [], array $meta = [], int $code = 200): JsonResponse
    {
        $response = [
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            'meta' => $meta,
        ];

        if ($data instanceof CursorPaginator) {
            $response['data'] = $data->items();
            $response['pagination'] = self::extractCursorPagination($data);
        } elseif (method_exists($data, 'resource') && $data->resource instanceof CursorPaginator) {
            $response['data'] = $data->resolve();
            $response['pagination'] = self::extractCursorPagination($data->resource);
        }

        return response()->json($response, $code);
    }

    public static function error(string $message, int $code = 400): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
        ], $code);
    }

    public static function tooManyRequests(string $message = 'Too many requests.'): JsonResponse
    {
        return self::error($message, 429);
    }

    /**
     * @return array<string, mixed>
     */
    private static function extractCursorPagination(CursorPaginator $paginator): array
    {
        return [
            'path' => $paginator->path(),
            'per_page' => $paginator->perPage(),
            'next_cursor' => $paginator->nextCursor()?->encode(),
            'next_page_url' => $paginator->nextPageUrl(),
            'prev_cursor' => $paginator->previousCursor()?->encode(),
            'prev_page_url' => $paginator->previousPageUrl(),
            'on_last_page' => ! $paginator->hasMorePages(),
        ];
    }
}
