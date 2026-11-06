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
        // The API has no authentication, so requests are limited per client IP.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
    }
}
