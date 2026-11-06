<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Number;

abstract class Controller
{
    private const int DEFAULT_PER_PAGE = 15;

    private const int MAX_PER_PAGE = 100;

    /**
     * The requested page size, clamped to 1..100.
     */
    protected function perPage(Request $request): int
    {
        return (int) Number::clamp($request->integer('per_page', self::DEFAULT_PER_PAGE), 1, self::MAX_PER_PAGE);
    }

    /**
     * The requested page, at least 1, and at most the page whose offset still fits
     * in an integer: a larger one would overflow the query offset and fail with a 500.
     * Pages past the last one are simply empty.
     */
    protected function page(Request $request, int $perPage): int
    {
        return (int) Number::clamp($request->integer('page', 1), 1, intdiv(PHP_INT_MAX, $perPage));
    }
}
