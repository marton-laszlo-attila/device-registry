<?php

declare(strict_types=1);

use App\Models\Contract;
use App\Models\ContractDevice;
use App\Models\Device;

beforeEach(function (): void {
    $this->payload = [
            'contract_number' => 'CT-2026-0001',
            'partner_name' => 'Acme Kft.',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
            'notes' => 'Annual leasing contract',
    ];
});

describe('index', function (): void {
    it('lists contracts paginated, ordered by id', function (): void {
        Contract::factory()->count(3)->create();

        $this->getJson('/api/v1/contracts?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.id', Contract::query()->min('id'))
            ->assertJsonStructure([
                'data' => [[
                    'id', 'contract_number', 'partner_name', 'start_date', 'end_date',
                    'status', 'notes', 'created_at', 'updated_at',
                ]],
                'links',
                'meta',
            ]);
    });

    it('does not include the devices in the list', function (): void {
        ContractDevice::factory()->create();

        $this->getJson('/api/v1/contracts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('data.0.devices');
    });

    it('clamps per_page to 100', function (): void {
        $this->getJson('/api/v1/contracts?per_page=1000')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    });

    it('clamps per_page to 1 when it is zero', function (): void {
        Contract::factory()->count(2)->create();

        $this->getJson('/api/v1/contracts?per_page=0')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonCount(1, 'data');
    });
});

describe('store', function (): void {
    it('creates a contract', function (): void {
        $this->postJson('/api/v1/contracts', $this->payload)
            ->assertCreated()
            ->assertJsonPath('data.contract_number', 'CT-2026-0001')
            ->assertJsonPath('data.start_date', '2026-01-01')
            ->assertJsonPath('data.end_date', '2026-12-31')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('contracts', ['contract_number' => 'CT-2026-0001']);
    });

    it('creates an open-ended contract without end date', function (): void {
        $this->postJson('/api/v1/contracts', [...$this->payload, 'end_date' => null])
            ->assertCreated()
            ->assertJsonPath('data.end_date', null);
    });

    it('requires the mandatory fields', function (string $field): void {
        $this->postJson('/api/v1/contracts', [...$this->payload, $field => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    })->with(['contract_number', 'partner_name', 'start_date', 'status']);

    it('rejects invalid values', function (string $field, mixed $value): void {
        $this->postJson('/api/v1/contracts', [...$this->payload, $field => $value])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    })->with([
        'unknown status' => ['status', 'cancelled'],
        'invalid start date' => ['start_date', 'not-a-date'],
        'too long contract number' => ['contract_number', str_repeat('a', 51)],
        'too long partner name' => ['partner_name', str_repeat('a', 256)],
        'too long notes' => ['notes', str_repeat('a', 5001)],
        'non-string notes' => ['notes', ['array']],
    ]);

    it('accepts values at the maximum length, counted in characters not bytes', function (): void {
        $this->postJson('/api/v1/contracts', [
            ...$this->payload,
            'contract_number' => str_repeat('é', 50),
            'partner_name' => str_repeat('ő', 255),
            'notes' => str_repeat('ű', 5000),
        ])->assertCreated();
    });

    it('returns accented and emoji text unchanged', function (): void {
        $payload = [
            ...$this->payload,
            'partner_name' => 'Árvíztűrő Kft.',
            'notes' => 'Tükörfúrógép – határidő: június 30. 😀',
        ];

        $id = $this->postJson('/api/v1/contracts', $payload)->assertCreated()->json('data.id');

        $this->getJson("/api/v1/contracts/{$id}")
            ->assertOk()
            ->assertJsonPath('data.partner_name', 'Árvíztűrő Kft.')
            ->assertJsonPath('data.notes', $payload['notes']);
    });

    it('accepts a contract that starts and ends on the same day', function (): void {
        $sameDay = ['start_date' => '2026-05-01', 'end_date' => '2026-05-01'];

        $this->postJson('/api/v1/contracts', [...$this->payload, ...$sameDay])->assertCreated();
    });

    it('ignores fields that are not part of the contract', function (): void {
        $id = $this->postJson('/api/v1/contracts', [...$this->payload, 'id' => 4242])
            ->assertCreated()
            ->json('data.id');

        expect($id)->not->toBe(4242);
    });

    it('rejects an end date before the start date', function (): void {
        $this->postJson('/api/v1/contracts', [...$this->payload, 'end_date' => '2025-12-31'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);
    });

    it('rejects a duplicate contract number', function (): void {
        Contract::factory()->create(['contract_number' => 'CT-2026-0001']);

        $this->postJson('/api/v1/contracts', $this->payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['contract_number']);
    });
});

describe('show', function (): void {
    it('returns the contract with its assigned devices and serial numbers', function (): void {
        $contract = Contract::factory()->create();
        $device = Device::factory()->create();
        $first = ContractDevice::factory()->for($contract)->for($device)->create(['serial_number' => 'SN-A']);
        $second = ContractDevice::factory()->for($contract)->for($device)->create(['serial_number' => 'SN-B']);

        $this->getJson("/api/v1/contracts/{$contract->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $contract->id)
            ->assertJsonCount(2, 'data.devices')
            ->assertJsonPath('data.devices.0.id', $first->id)
            ->assertJsonPath('data.devices.0.serial_number', 'SN-A')
            ->assertJsonPath('data.devices.0.device.id', $device->id)
            ->assertJsonPath('data.devices.1.id', $second->id)
            ->assertJsonPath('data.devices.1.serial_number', 'SN-B')
            ->assertJsonStructure([
                'data' => [
                    'devices' => [['id', 'serial_number', 'assigned_at', 'device' => ['id', 'manufacturer', 'model']]],
                ],
            ]);
    });

    it('shows only the devices of the requested contract', function (): void {
        $mine = ContractDevice::factory()->create(['serial_number' => 'SN-MINE']);
        ContractDevice::factory()->create(['serial_number' => 'SN-OTHER']);

        $this->getJson("/api/v1/contracts/{$mine->contract_id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.devices')
            ->assertJsonPath('data.devices.0.serial_number', 'SN-MINE');
    });

    it('returns an empty device list for a contract without devices', function (): void {
        $contract = Contract::factory()->create();

        $this->getJson("/api/v1/contracts/{$contract->id}")
            ->assertOk()
            ->assertJsonPath('data.devices', []);
    });

    it('returns 404 for a missing contract', function (): void {
        $this->getJson('/api/v1/contracts/999999')->assertNotFound();
    });
});

describe('update', function (): void {
    it('partially updates a contract with PATCH', function (): void {
        $contract = Contract::factory()->create(['status' => 'draft']);

        $this->patchJson("/api/v1/contracts/{$contract->id}", ['status' => 'active'])
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.contract_number', $contract->contract_number);

        expect($contract->fresh()->status->value)->toBe('active');
    });

    it('treats PUT as a partial update too', function (): void {
        $contract = Contract::factory()->create();

        $this->putJson("/api/v1/contracts/{$contract->id}", ['partner_name' => 'Globex Zrt.'])
            ->assertOk()
            ->assertJsonPath('data.partner_name', 'Globex Zrt.');
    });

    it('clears the end date and notes with an explicit null but keeps them when omitted', function (): void {
        $contract = Contract::factory()->create(['end_date' => '2030-01-01', 'notes' => 'Keep me']);

        $this->patchJson("/api/v1/contracts/{$contract->id}", ['status' => 'terminated'])
            ->assertOk()
            ->assertJsonPath('data.end_date', '2030-01-01')
            ->assertJsonPath('data.notes', 'Keep me');

        $this->patchJson("/api/v1/contracts/{$contract->id}", ['end_date' => null, 'notes' => null])
            ->assertOk()
            ->assertJsonPath('data.end_date', null)
            ->assertJsonPath('data.notes', null);
    });

    it('keeps its own contract number', function (): void {
        $contract = Contract::factory()->create(['contract_number' => 'CT-2026-0001']);

        $this->patchJson("/api/v1/contracts/{$contract->id}", ['contract_number' => 'CT-2026-0001'])->assertOk();
    });

    it('rejects a contract number taken by another contract', function (): void {
        Contract::factory()->create(['contract_number' => 'CT-2026-0001']);
        $contract = Contract::factory()->create();

        $this->patchJson("/api/v1/contracts/{$contract->id}", ['contract_number' => 'CT-2026-0001'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['contract_number']);
    });

    it('rejects an end date before the stored start date', function (): void {
        $contract = Contract::factory()->create(['start_date' => '2026-01-01', 'end_date' => null]);

        $this->patchJson("/api/v1/contracts/{$contract->id}", ['end_date' => '2025-06-30'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);
    });

    it('rejects a start date after the stored end date', function (): void {
        $contract = Contract::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2026-12-31']);

        $this->patchJson("/api/v1/contracts/{$contract->id}", ['start_date' => '2027-01-01'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_date']);
    });

    it('accepts both dates moved together', function (): void {
        $contract = Contract::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2026-12-31']);

        $dates = ['start_date' => '2027-01-01', 'end_date' => '2027-12-31'];

        $this->patchJson("/api/v1/contracts/{$contract->id}", $dates)
            ->assertOk()
            ->assertJsonPath('data.start_date', '2027-01-01')
            ->assertJsonPath('data.end_date', '2027-12-31');
    });

    it('returns 404 for a missing contract', function (): void {
        $this->patchJson('/api/v1/contracts/999999', ['status' => 'active'])->assertNotFound();
    });
});

describe('destroy', function (): void {
    it('deletes a contract without devices', function (): void {
        $contract = Contract::factory()->create();

        $this->deleteJson("/api/v1/contracts/{$contract->id}")->assertNoContent();

        $this->assertModelMissing($contract);
    });

    it('refuses to delete a contract that has devices', function (): void {
        $assignment = ContractDevice::factory()->create();

        $this->deleteJson("/api/v1/contracts/{$assignment->contract_id}")
            ->assertConflict()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseHas('contracts', ['id' => $assignment->contract_id]);
    });

    it('returns 404 for a missing contract', function (): void {
        $this->deleteJson('/api/v1/contracts/999999')->assertNotFound();
    });
});
