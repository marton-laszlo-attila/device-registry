<?php

declare(strict_types=1);

use App\Enums\ContractStatus;
use App\Enums\DeviceCategory;
use App\Models\Contract;
use App\Models\ContractDevice;
use App\Models\Device;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;

it('links device types and contracts many-to-many with a serial number per unit', function (): void {
    $device = Device::factory()->create();
    [$first, $second] = Contract::factory()->count(2)->create();

    $first->devices()->attach($device, ['serial_number' => 'SN-1']);
    $first->devices()->attach($device, ['serial_number' => 'SN-2']);
    $second->devices()->attach($device, ['serial_number' => 'SN-3']);

    expect($first->devices()->pluck('serial_number')->all())->toBe(['SN-1', 'SN-2'])
        ->and($first->devices->first()->pivot)->toBeInstanceOf(ContractDevice::class)
        ->and($first->devices->first()->pivot->id)->toBeInt()
        ->and($device->contracts()->count())->toBe(3)
        ->and($device->assignments()->count())->toBe(3)
        ->and($second->assignments()->sole()->device->is($device))->toBeTrue()
        ->and($second->assignments()->sole()->contract->is($second))->toBeTrue();
});

it('casts the enums and dates', function (): void {
    $contract = Contract::factory()->create(['status' => 'active', 'start_date' => '2026-01-01']);
    $device = Device::factory()->create(['category' => 'printer']);

    expect($contract->status)->toBe(ContractStatus::Active)
        ->and($contract->start_date->toDateString())->toBe('2026-01-01')
        ->and($device->category)->toBe(DeviceCategory::Printer);
});

it('keeps serial numbers unique across all contracts at database level', function (): void {
    $assignment = ContractDevice::factory()->create(['serial_number' => 'SN-1']);

    ContractDevice::factory()->create(['device_id' => $assignment->device_id, 'serial_number' => 'SN-1']);
})->throws(UniqueConstraintViolationException::class);

it('keeps the manufacturer and model pair unique at database level', function (): void {
    Device::factory()->count(2)->create(['manufacturer' => 'Dell', 'model' => 'Latitude 5440']);
})->throws(UniqueConstraintViolationException::class);

it('restricts deleting a contract or device that has assignments at database level', function (string $model): void {
    $assignment = ContractDevice::factory()->create();

    $assignment->{$model}->delete();
})->with(['contract', 'device'])->throws(QueryException::class);
