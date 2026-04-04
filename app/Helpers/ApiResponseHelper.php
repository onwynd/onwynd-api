<?php

namespace App\Helpers;

/**
 * API Response Helper
 * Standardized API responses across the application
 */
class ApiResponseHelper
{
    /**
     * Success response
     */
    public static function success($data = null, $message = 'Success', $statusCode = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    /**
     * Error response
     */
    public static function error($message = 'Error', $errors = null, $statusCode = 400)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $statusCode);
    }

    /**
     * Paginated response
     */
    public static function paginated($items, $message = 'Success', $statusCode = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $items->items(),
            'pagination' => [
                'total' => $items->total(),
                'count' => $items->count(),
                'per_page' => $items->perPage(),
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
            ],
        ], $statusCode);
    }

    /**
     * Collection response
     */
    public static function collection($items, $message = 'Success', $statusCode = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $items,
            'count' => count($items),
        ], $statusCode);
    }

    /**
     * Validation error response
     */
    public static function validationError($errors)
    {
        return self::error('Validation failed', $errors, 422);
    }

    /**
     * Not found response
     */
    public static function notFound($message = 'Resource not found')
    {
        return self::error($message, null, 404);
    }

    /**
     * Unauthorized response
     */
    public static function unauthorized($message = 'Unauthorized')
    {
        return self::error($message, null, 401);
    }

    /**
     * Forbidden response
     */
    public static function forbidden($message = 'Forbidden')
    {
        return self::error($message, null, 403);
    }
}
