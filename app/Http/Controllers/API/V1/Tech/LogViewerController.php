<?php

namespace App\Http\Controllers\API\V1\Tech;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LogViewerController extends BaseController
{
    private const LOG_PATH = 'logs/laravel.log';

    /**
     * GET /api/v1/ceo/logs
     * Return the last N lines of the Laravel log file.
     */
    public function index(Request $request)
    {
        $lines = max(1, min((int) $request->get('lines', 200), 2000));

        $path = storage_path('logs/laravel.log');

        if (! file_exists($path)) {
            return $this->sendResponse([
                'lines'     => [],
                'total'     => 0,
                'file_size' => 0,
            ], 'Log file is empty.');
        }

        $fileSize = filesize($path);
        $allLines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $tail     = array_slice($allLines, -$lines);

        // Parse each line into {level, timestamp, message}
        $parsed = array_map(function (string $raw) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}[^\]]*)\]\s+\w+\.(\w+):\s+(.+)$/', $raw, $m)) {
                return [
                    'timestamp' => $m[1],
                    'level'     => strtolower($m[2]),
                    'message'   => $m[3],
                    'raw'       => $raw,
                ];
            }

            return ['timestamp' => null, 'level' => 'debug', 'message' => $raw, 'raw' => $raw];
        }, $tail);

        return $this->sendResponse([
            'lines'     => $parsed,
            'total'     => count($allLines),
            'file_size' => $fileSize,
        ], 'Logs retrieved.');
    }

    /**
     * DELETE /api/v1/ceo/logs
     * Clear (truncate) the Laravel log file.
     */
    public function clear()
    {
        $path = storage_path('logs/laravel.log');

        if (file_exists($path)) {
            file_put_contents($path, '');
        }

        return $this->sendResponse([], 'Log file cleared.');
    }
}
