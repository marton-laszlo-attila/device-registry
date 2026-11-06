<?php

declare(strict_types=1);

use App\Models\ContractDevice;
use App\Models\Device;

beforeEach(function (): void {
    $this->payload = [
            'manufacturer' => 'Dell',
            'model' => 'Latitude 5440',
            'category' => 'laptop',
            'description' => '14" business laptop',
    ];
});

describe('index', function (): void {
    it('lists devices paginated, ordered by id', function (): void {
        Device::factory()->count(3)->create();

        $this->getJson('/api/v1/devices?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.id', Device::query()->min('id'))
            ->assertJsonStructure([
                'data' => [['id', 'manufacturer', 'model', 'category', 'description', 'created_at', 'updated_at']],
                'links',
                'meta',
            ]);
    });

    it('clamps per_page between 1 and 100', function (int $requested, int $expected): void {
        $this->getJson("/api/v1/devices?per_page={$requested}")
            ->assertOk()
            ->assertJsonPath('meta.per_page', $expected);
    })->with([
        'too large' => [500, 100],
        'zero' => [0, 1],
        'negative' => [-5, 1],
    ]);
});

describe('store', function (): void {
    it('creates a device', function (): void {
        $this->postJson('/api/v1/devices', $this->payload)
            ->assertCreated()
            ->assertJsonPath('data.manufacturer', 'Dell')
            ->assertJsonPath('data.model', 'Latitude 5440')
            ->assertJsonPath('data.category', 'laptop');

        $this->assertDatabaseHas('devices', ['manufacturer' => 'Dell', 'model' => 'Latitude 5440']);
    });

    it('requires the mandatory fields', function (string $field): void {
        $this->postJson('/api/v1/devices', [...$this->payload, $field => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    })->with(['manufacturer', 'model', 'category']);

    it('rejects invalid values', function (string $field, mixed $value): void {
        $this->postJson('/api/v1/devices', [...$this->payload, $field => $value])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    })->with([
        'unknown category' => ['category', 'toaster'],
        'too long manufacturer' => ['manufacturer', str_repeat('a', 101)],
        'too long model' => ['model', str_repeat('a', 101)],
        'non-string description' => ['description', ['array']],
    ]);

    it('rejects a duplicate manufacturer and model pair', function (): void {
        Device::factory()->create(['manufacturer' => 'Dell', 'model' => 'Latitude 5440']);

        $this->postJson('/api/v1/devices', $this->payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['model']);
    });

    it('allows the same model name from another manufacturer', function (): void {
        Device::factory()->create(['manufacturer' => 'HP', 'model' => 'Latitude 5440']);

        $this->postJson('/api/v1/devices', $this->payload)->assertCreated();
    });
});

describe('show', function (): void {
    it('returns a single device', function (): void {
        $device = Device::factory()->create();

        $this->getJson("/api/v1/devices/{$device->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $device->id)
            ->assertJsonPath('data.model', $device->model);
    });

    it('returns 404 for a missing device', function (): void {
        $this->getJson('/api/v1/devices/999999')->assertNotFound();
    });
});

describe('update', function (): void {
    it('partially updates a device with PATCH', function (): void {
        $device = Device::factory()->create(['category' => 'laptop']);

        $this->patchJson("/api/v1/devices/{$device->id}", ['category' => 'monitor'])
            ->assertOk()
            ->assertJsonPath('data.category', 'monitor')
            ->assertJsonPath('data.model', $device->model);

        expect($device->fresh()->category->value)->toBe('monitor');
    });

    it('treats PUT as a partial update too', function (): void {
        $device = Device::factory()->create();

        $this->putJson("/api/v1/devices/{$device->id}", ['description' => 'Updated'])
            ->assertOk()
            ->assertJsonPath('data.description', 'Updated');
    });

    it('keeps its own manufacturer and model pair', function (): void {
        $device = Device::factory()->create($this->payload);

        $this->patchJson("/api/v1/devices/{$device->id}", $this->payload)->assertOk();
    });

    it('rejects a pair taken by another device when only the model changes', function (): void {
        Device::factory()->create(['manufacturer' => 'Dell', 'model' => 'Latitude 5440']);
        $device = Device::factory()->create(['manufacturer' => 'Dell', 'model' => 'OptiPlex 7010']);

        $this->patchJson("/api/v1/devices/{$device->id}", ['model' => 'Latitude 5440'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['model']);
    });

    it('rejects a pair taken by another device when only the manufacturer changes', function (): void {
        Device::factory()->create(['manufacturer' => 'Dell', 'model' => 'Latitude 5440']);
        $device = Device::factory()->create(['manufacturer' => 'HP', 'model' => 'Latitude 5440']);

        $this->patchJson("/api/v1/devices/{$device->id}", ['manufacturer' => 'Dell'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['manufacturer']);
    });

    it('rejects an empty required field', function (): void {
        $device = Device::factory()->create();

        $this->patchJson("/api/v1/devices/{$device->id}", ['manufacturer' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['manufacturer']);
    });

    it('returns 404 for a missing device', function (): void {
        $this->patchJson('/api/v1/devices/999999', ['category' => 'monitor'])->assertNotFound();
    });
});

describe('destroy', function (): void {
    it('deletes a device', function (): void {
        $device = Device::factory()->create();

        $this->deleteJson("/api/v1/devices/{$device->id}")->assertNoContent();

        $this->assertModelMissing($device);
    });

    it('refuses to delete a device that is on a contract', function (): void {
        $assignment = ContractDevice::factory()->create();

        $this->deleteJson("/api/v1/devices/{$assignment->device_id}")
            ->assertConflict()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseHas('devices', ['id' => $assignment->device_id]);
    });

    it('returns 404 for a missing device', function (): void {
        $this->deleteJson('/api/v1/devices/999999')->assertNotFound();
    });
});
