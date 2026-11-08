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

    it('continues on the next page where the previous one stopped', function (): void {
        $ids = Device::factory()->count(5)->create()->pluck('id')->all();

        $this->getJson('/api/v1/devices?per_page=2&page=2')
            ->assertOk()
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('data.0.id', $ids[2])
            ->assertJsonPath('data.1.id', $ids[3]);

        $this->getJson('/api/v1/devices?per_page=2&page=3')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ids[4]);
    });

    it('keeps the page size in the pagination links', function (): void {
        Device::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/devices?per_page=2&page=2')->assertOk();

        foreach (['first', 'last', 'prev', 'next'] as $link) {
            expect($response->json("links.{$link}"))->toContain('per_page=2');
        }

        // The links follow the clamped value, and carry no other query parameter of the request.
        expect($this->getJson('/api/v1/devices?per_page=1000&junk=1')->json('links.first'))
            ->toContain('per_page=100')
            ->not->toContain('junk');
    });

    it('returns an empty page beyond the last one', function (): void {
        Device::factory()->count(2)->create();

        $this->getJson('/api/v1/devices?page=99')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 2);
    });

    it('clamps per_page between 1 and 100', function (int $requested, int $expected): void {
        $this->getJson("/api/v1/devices?per_page={$requested}")
            ->assertOk()
            ->assertJsonPath('meta.per_page', $expected);
    })->with([
        'too large' => [500, 100],
        'one thousand' => [1000, 100],
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
        'too long description' => ['description', str_repeat('a', 5001)],
    ]);

    it('accepts values at the maximum length, counted in characters not bytes', function (): void {
        $this->postJson('/api/v1/devices', [
            'manufacturer' => str_repeat('é', 100),
            'model' => str_repeat('ő', 100),
            'category' => 'laptop',
            'description' => str_repeat('ű', 5000),
        ])->assertCreated();
    });

    it('returns accented and emoji text unchanged', function (): void {
        $payload = [
            'manufacturer' => 'Árvíztűrő Gyártó Kft.',
            'model' => 'Tükörfúrógép 😀',
            'category' => 'other',
            'description' => 'Öt szép szűzlány őrült írót nyúz.',
        ];

        $id = $this->postJson('/api/v1/devices', $payload)->assertCreated()->json('data.id');

        $this->getJson("/api/v1/devices/{$id}")
            ->assertOk()
            ->assertJsonPath('data.manufacturer', $payload['manufacturer'])
            ->assertJsonPath('data.model', $payload['model'])
            ->assertJsonPath('data.description', $payload['description']);
    });

    it('ignores fields that are not part of the contract', function (): void {
        $id = $this->postJson('/api/v1/devices', [
            ...$this->payload,
            'id' => 4242,
            'created_at' => '2000-01-01 00:00:00',
        ])->assertCreated()->json('data.id');

        expect($id)->not->toBe(4242)
            ->and(Device::query()->findOrFail($id)->created_at->year)->toBe((int) now()->year);
    });

    it('treats a duplicate manufacturer and model pair case-insensitively', function (): void {
        Device::factory()->create(['manufacturer' => 'Dell', 'model' => 'Latitude 5440']);

        $this->postJson('/api/v1/devices', [...$this->payload, 'manufacturer' => 'DELL', 'model' => 'latitude 5440'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['model']);
    });

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

    it('clears the description with an explicit null but keeps it when omitted', function (): void {
        $device = Device::factory()->create(['description' => 'Old text']);

        $this->patchJson("/api/v1/devices/{$device->id}", ['category' => 'phone'])
            ->assertOk()
            ->assertJsonPath('data.description', 'Old text');

        $this->patchJson("/api/v1/devices/{$device->id}", ['description' => null])
            ->assertOk()
            ->assertJsonPath('data.description', null);
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

        $this->assertModelExists($assignment->device);
    });

    it('stays protected while another contract still has it', function (): void {
        $device = Device::factory()->create();
        $first = ContractDevice::factory()->for($device)->create();
        ContractDevice::factory()->for($device)->create();

        $this->deleteJson("/api/v1/contracts/{$first->contract_id}/devices/{$first->id}")->assertNoContent();

        $this->deleteJson("/api/v1/devices/{$device->id}")->assertConflict();
        $this->assertModelExists($device);
    });

    it('can be deleted once its last unit is removed from every contract', function (): void {
        $assignment = ContractDevice::factory()->create();

        $this->deleteJson("/api/v1/contracts/{$assignment->contract_id}/devices/{$assignment->id}")->assertNoContent();

        $this->deleteJson("/api/v1/devices/{$assignment->device_id}")->assertNoContent();
    });

    it('returns 404 for a missing device', function (): void {
        $this->deleteJson('/api/v1/devices/999999')->assertNotFound();
    });
});
