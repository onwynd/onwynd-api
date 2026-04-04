<?php
/**
 * PhpStorm meta file to provide IDE type hints for Laravel packages
 * This file helps Pylance and other IDEs recognize methods from Laravel's HTTP Client
 * Reference: https://www.jetbrains.com/help/phpstorm/php-generics.html
 */

namespace PHPSTORM_META {

    // Illuminate\Http\Client\Response method hints
    override(
        \Illuminate\Http\Client\Response::successful(),
        type(0)
    );

    override(
        \Illuminate\Http\Client\Response::status(),
        type(0)
    );

    override(
        \Illuminate\Http\Client\Response::body(),
        type(0)
    );

    override(
        \Illuminate\Http\Client\Response::json(),
        type(0)
    );

    // Sanctum PersonalAccessToken hints
    override(
        \Illuminate\Foundation\Auth\User::currentAccessToken(),
        type(0)
    );

    override(
        \Illuminate\Foundation\Auth\User::tokens(),
        type(0)
    );

    override(
        \Illuminate\Foundation\Auth\User::token(),
        type(0)
    );

    // Auth facade hints
    override(
        \Illuminate\Support\Facades\Auth::user(),
        type(0)
    );

    override(
        \Illuminate\Support\Facades\Auth::id(),
        type(0)
    );

    override(
        \Illuminate\Support\Facades\Auth::check(),
        type(0)
    );

    override(
        \Illuminate\Support\Facades\Auth::guest(),
        type(0)
    );
}
