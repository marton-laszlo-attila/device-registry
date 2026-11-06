<?php

declare(strict_types=1);

use App\Models\Contract;
use App\Models\ContractDevice;
use App\Models\Device;

/*
 * `make fresh` (migrate:fresh --seed) is the documented way to get demo data.
 * A migration change that breaks the seeder would otherwise only show up there.
 */

it('seeds a consistent demo data set that the API can serve', function (): void {
    $this->seed();

    expect(Device::query()->count())->toBe(4)
        ->and(Contract::query()->count())->toBe(3)
        ->and(ContractDevice::query()->count())->toBe(5)
        ->and(ContractDevice::query()->distinct()->count('serial_number'))->toBe(5);

    // The demo shows the N:N case: one device type on two contracts.
    expect(Device::query()->has('contracts', '>=', 2)->exists())->toBeTrue();

    $contract = Contract::query()->where('contract_number', 'CT-2026-0001')->sole();

    $this->getJson("/api/v1/contracts/{$contract->id}")
        ->assertOk()
        ->assertJsonCount(3, 'data.devices');
});
