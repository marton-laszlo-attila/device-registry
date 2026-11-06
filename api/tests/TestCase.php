<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Creates a device type through the API and returns its id.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function createDeviceViaApi(array $overrides = []): int
    {
        return $this->postJson('/api/v1/devices', [
            'manufacturer' => 'Dell',
            'model' => 'Latitude 5440',
            'category' => 'laptop',
            ...$overrides,
        ])->assertCreated()->json('data.id');
    }

    /**
     * Creates a contract through the API and returns its id.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function createContractViaApi(array $overrides = []): int
    {
        return $this->postJson('/api/v1/contracts', [
            'contract_number' => 'CT-2026-0001',
            'partner_name' => 'Acme Kft.',
            'start_date' => '2026-01-01',
            'status' => 'active',
            ...$overrides,
        ])->assertCreated()->json('data.id');
    }

    /**
     * Adds a device unit to a contract through the API and returns the assignment id.
     */
    protected function assignDeviceViaApi(int $contractId, int $deviceId, string $serialNumber): int
    {
        return $this->postJson("/api/v1/contracts/{$contractId}/devices", [
            'device_id' => $deviceId,
            'serial_number' => $serialNumber,
        ])->assertCreated()->json('data.id');
    }
}
