<?php

declare(strict_types=1);

use App\Exceptions\ApiException;
use App\Exceptions\ConflictException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Every error of the API is JSON: { "message": ..., "errors": {...} } (errors only on 422).
        // Validation (422), 405, 429 and 500 already come in this shape from Laravel.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Business rule violations are expected outcomes, not errors to log.
        $exceptions->dontReport(ApiException::class);

        // A unique key that passed validation but lost a race in the database.
        $exceptions->map(
            UniqueConstraintViolationException::class,
            fn (UniqueConstraintViolationException $e) => ConflictException::duplicateRecord($e),
        );

        $exceptions->render(
            fn (ApiException $e) => response()->json(['message' => $e->getMessage()], $e->status()),
        );

        // Covers ModelNotFoundException too; the default message would expose the model class and id.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Resource not found.'], $e->getStatusCode());
            }

            return null;
        });
    })->create();
