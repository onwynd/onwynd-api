<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;
use Throwable;

trait Loggable
{
    public function error(Throwable $e): void
    {
        Log::error($e->getMessage(), [
            'code' => $e->getCode(),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    }

    public function info(): void {}
}
