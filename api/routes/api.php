<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ContractController;
use App\Http\Controllers\Api\V1\ContractDeviceController;
use App\Http\Controllers\Api\V1\DeviceController;
use Illuminate\Support\Facades\Route;

// Identifiers are plain integers: MySQL would otherwise resolve "1abc" to the row with id 1.
Route::prefix('v1')->name('v1.')->where([
    'device' => '[0-9]+',
    'contract' => '[0-9]+',
    'assignment' => '[0-9]+',
])->group(function (): void {
    Route::apiResource('devices', DeviceController::class);
    Route::apiResource('contracts', ContractController::class);

    Route::scopeBindings()->group(function (): void {
        Route::post('contracts/{contract}/devices', [ContractDeviceController::class, 'store'])
            ->name('contracts.devices.store');
        Route::delete('contracts/{contract}/devices/{assignment}', [ContractDeviceController::class, 'destroy'])
            ->name('contracts.devices.destroy');
    });
});
