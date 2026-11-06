<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\ContractService;
use App\Services\DeviceService;
use App\Services\Interfaces\ContractServiceInterface;
use App\Services\Interfaces\DeviceServiceInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\Response;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Service interface to implementation bindings.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        DeviceServiceInterface::class => DeviceService::class,
        ContractServiceInterface::class => ContractService::class,
    ];

    public function boot(): void
    {
        // The API has no authentication, so requests are limited per client IP. The limiter runs as
        // global middleware, so it sees every request and lets everything outside /api through.
        RateLimiter::for('api', fn (Request $request): Limit => $request->is('api/*')
            ? Limit::perMinute(60)->by((string) $request->ip())
            : Limit::none());

        // Only failed logins count, so reading the documentation is never limited,
        // but guessing its password is.
        RateLimiter::for('docs', fn (Request $request): Limit => Limit::perMinute(10)
            ->by((string) $request->ip())
            ->after(fn (Response $response): bool => $response->getStatusCode() === Response::HTTP_UNAUTHORIZED));
    }
}
