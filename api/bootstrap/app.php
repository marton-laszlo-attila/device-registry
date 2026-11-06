<?php

declare(strict_types=1);

use App\Exceptions\ApiException;
use App\Exceptions\ConflictException;
use App\Http\Middleware\LogApiTraffic;
use App\Http\Middleware\RequireJsonBody;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
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
        // No reverse proxy sits in front of PHP (nginx passes the client address over FastCGI),
        // so X-Forwarded-* headers are never trusted. Laravel would otherwise trust them for any
        // Host ending in .on-forge.com, letting a client pick its own IP for the rate limit and the log.
        $middleware->remove(TrustProxies::class);

        // Global rather than in the api group: unmatched routes (404, 405) never run group middleware,
        // but they are logged too, so they must count against the limit as well.
        $middleware->append(ThrottleRequests::using('api'));
        $middleware->append(LogApiTraffic::class);

        $middleware->api(append: RequireJsonBody::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Every error of the API is JSON: { "message": ..., "errors": {...} } (errors only on 422).
        // Validation (422), 405, 415, 429 and 500 already come in this shape from Laravel.
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

        // A foreign key that passed the service's check but lost a race: the device was deleted
        // while being assigned (MySQL 1452), or assigned while being deleted (1451).
        $exceptions->map(
            QueryException::class,
            fn (QueryException $e) => in_array($e->errorInfo[1] ?? null, [1451, 1452], true)
                ? ConflictException::concurrentChange($e)
                : $e,
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
