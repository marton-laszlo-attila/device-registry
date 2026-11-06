<?php

declare(strict_types=1);

use App\Models\Contract;
use App\Models\ContractDevice;
use App\Models\Device;

/*
 * MySQL compares an integer column with a string by casting the string to a number,
 * so a lookup for "1abc" would find the row with id 1. The routes must reject
 * such identifiers instead of silently resolving them to another record.
 */

it('answers 404 for an identifier that is not a plain integer', function (string $method, string $suffix): void {
    $assignment = ContractDevice::factory()->create();
    $device = $assignment->device;
    $contract = $assignment->contract;

    $uri = str_replace(
        ['{device}', '{contract}', '{assignment}'],
        [(string) $device->id, (string) $contract->id, (string) $assignment->id],
        $suffix,
    );

    $body = ['status' => 'active', 'device_id' => $device->id, 'serial_number' => 'SN-X'];

    $this->json($method, "/api/v1/{$uri}", $body)
        ->assertNotFound()
        ->assertExactJson(['message' => 'Resource not found.']);

    $this->assertModelExists($device);
    $this->assertModelExists($contract);
    $this->assertModelExists($assignment);
})->with([
    'device, letters' => ['GET', 'devices/abc'],
    'device, trailing letters' => ['GET', 'devices/{device}abc'],
    'device, decimal' => ['GET', 'devices/{device}.0'],
    'device, update' => ['PATCH', 'devices/{device}abc'],
    'device, delete' => ['DELETE', 'devices/{device}abc'],
    'contract, letters' => ['GET', 'contracts/abc'],
    'contract, trailing letters' => ['GET', 'contracts/{contract}abc'],
    'contract, update' => ['PATCH', 'contracts/{contract}abc'],
    'contract, delete' => ['DELETE', 'contracts/{contract}abc'],
    'assignment, add to contract' => ['POST', 'contracts/{contract}abc/devices'],
    'assignment, remove with trailing letters' => ['DELETE', 'contracts/{contract}/devices/{assignment}abc'],
    'assignment, contract with trailing letters' => ['DELETE', 'contracts/{contract}abc/devices/{assignment}'],
]);

it('still resolves a plain integer identifier', function (): void {
    $device = Device::factory()->create();
    $contract = Contract::factory()->create();

    $this->getJson("/api/v1/devices/{$device->id}")->assertOk();
    $this->getJson("/api/v1/contracts/{$contract->id}")->assertOk();
});
