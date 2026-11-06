<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ContractController;
use App\Http\Controllers\Api\V1\DeviceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('v1.')->group(function (): void {
    Route::apiResource('devices', DeviceController::class);
    Route::apiResource('contracts', ContractController::class);
});
