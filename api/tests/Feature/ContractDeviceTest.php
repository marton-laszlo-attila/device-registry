<?php

declare(strict_types=1);

use App\Models\Contract;
use App\Models\ContractDevice;
use App\Models\Device;

describe('store', function (): void {
    it('assigns a device to a contract with a serial number', function (): void {
        $contract = Contract::factory()->create();
        $device = Device::factory()->create();

        $response = $this->postJson("/api/v1/contracts/{$contract->id}/devices", [
            'device_id' => $device->id,
            'serial_number' => 'SN-0001',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.serial_number', 'SN-0001')
            ->assertJsonPath('data.device.id', $device->id)
            ->assertJsonStructure(['data' => ['id', 'serial_number', 'assigned_at', 'device']]);

        $this->assertDatabaseHas('contract_device', [
            'id' => $response->json('data.id'),
            'contract_id' => $contract->id,
            'device_id' => $device->id,
            'serial_number' => 'SN-0001',
        ]);
    });

    it('assigns the same device type twice with different serial numbers', function (): void {
        $contract = Contract::factory()->create();
        $device = Device::factory()->create();

        foreach (['SN-0001', 'SN-0002'] as $serialNumber) {
            $this->postJson("/api/v1/contracts/{$contract->id}/devices", [
                'device_id' => $device->id,
                'serial_number' => $serialNumber,
            ])->assertCreated();
        }

        expect($contract->assignments()->count())->toBe(2);
    });

    it('assigns the same device type to several contracts', function (): void {
        $device = Device::factory()->create();
        ContractDevice::factory()->for($device)->create();
        $contract = Contract::factory()->create();

        $this->postJson("/api/v1/contracts/{$contract->id}/devices", [
            'device_id' => $device->id,
            'serial_number' => 'SN-OTHER',
        ])->assertCreated();

        expect($device->contracts()->count())->toBe(2);
    });

    it('rejects a serial number that is already in use', function (bool $sameContract): void {
        $existing = ContractDevice::factory()->create(['serial_number' => 'SN-0001']);
        $contract = $sameContract ? $existing->contract : Contract::factory()->create();

        $this->postJson("/api/v1/contracts/{$contract->id}/devices", [
            'device_id' => $existing->device_id,
            'serial_number' => 'SN-0001',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['serial_number']);
    })->with([
        'on the same contract' => [true],
        'on another contract' => [false],
    ]);

    it('rejects a device that does not exist', function (): void {
        $contract = Contract::factory()->create();

        $this->postJson("/api/v1/contracts/{$contract->id}/devices", [
            'device_id' => 999999,
            'serial_number' => 'SN-0001',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['device_id']);
    });

    it('requires the mandatory fields', function (string $field): void {
        $contract = Contract::factory()->create();
        $payload = ['device_id' => Device::factory()->create()->id, 'serial_number' => 'SN-0001'];
        unset($payload[$field]);

        $this->postJson("/api/v1/contracts/{$contract->id}/devices", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    })->with(['device_id', 'serial_number']);

    it('rejects a too long serial number', function (): void {
        $contract = Contract::factory()->create();

        $this->postJson("/api/v1/contracts/{$contract->id}/devices", [
            'device_id' => Device::factory()->create()->id,
            'serial_number' => str_repeat('a', 101),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['serial_number']);
    });

    it('returns 404 for a missing contract', function (): void {
        $this->postJson('/api/v1/contracts/999999/devices', [
            'device_id' => Device::factory()->create()->id,
            'serial_number' => 'SN-0001',
        ])->assertNotFound();
    });
});

describe('destroy', function (): void {
    it('removes a device from a contract', function (): void {
        $assignment = ContractDevice::factory()->create();

        $this->deleteJson("/api/v1/contracts/{$assignment->contract_id}/devices/{$assignment->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('contract_device', ['id' => $assignment->id]);
        $this->assertDatabaseHas('devices', ['id' => $assignment->device_id]);
    });

    it('makes the contract deletable once its last device is removed', function (): void {
        $assignment = ContractDevice::factory()->create();

        $this->deleteJson("/api/v1/contracts/{$assignment->contract_id}/devices/{$assignment->id}")->assertNoContent();
        $this->deleteJson("/api/v1/contracts/{$assignment->contract_id}")->assertNoContent();
    });

    it('returns 404 for an assignment of another contract', function (): void {
        $assignment = ContractDevice::factory()->create();
        $otherContract = Contract::factory()->create();

        $this->deleteJson("/api/v1/contracts/{$otherContract->id}/devices/{$assignment->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('contract_device', ['id' => $assignment->id]);
    });

    it('returns 404 for a missing assignment', function (): void {
        $contract = Contract::factory()->create();

        $this->deleteJson("/api/v1/contracts/{$contract->id}/devices/999999")->assertNotFound();
    });
});
