<?php

declare(strict_types=1);

use App\Models\Contract;

/*
 * The API speaks Y-m-d only (that is what the responses contain). The permissive
 * "date" rule also accepts values the database would store as a different date
 * (for example "10000-01-01" became 2000-01-01) or would silently truncate.
 */

beforeEach(function (): void {
    $this->payload = [
        'contract_number' => 'CT-2026-0001',
        'partner_name' => 'Acme Kft.',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'status' => 'active',
    ];
});

dataset('malformed dates', [
    'year with five digits' => '10000-01-01',
    'month first' => '01/02/2026',
    'day first with dots' => '02.01.2026',
    'with time' => '2026-01-01 10:00:00',
    'iso 8601 date and time' => '2026-01-01T10:00:00Z',
    'not a calendar day' => '2026-02-30',
    'leap day in a common year' => '2025-02-29',
    'relative word' => 'tomorrow',
]);

it('rejects a start date that is not in Y-m-d format', function (string $date): void {
    $this->postJson('/api/v1/contracts', [...$this->payload, 'start_date' => $date, 'end_date' => null])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['start_date']);

    expect(Contract::query()->count())->toBe(0);
})->with('malformed dates');

it('rejects an end date that is not in Y-m-d format', function (string $date): void {
    $this->postJson('/api/v1/contracts', [...$this->payload, 'start_date' => '2000-01-01', 'end_date' => $date])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['end_date']);

    expect(Contract::query()->count())->toBe(0);
})->with('malformed dates');

it('rejects a malformed date on update and keeps the stored one', function (): void {
    $contract = Contract::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2026-12-31']);

    $this->patchJson("/api/v1/contracts/{$contract->id}", ['start_date' => '10000-01-01'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['start_date']);

    expect($contract->fresh()->start_date->toDateString())->toBe('2026-01-01');
});

it('accepts a leap day and stores it unchanged', function (): void {
    $this->postJson('/api/v1/contracts', [...$this->payload, 'start_date' => '2028-02-29', 'end_date' => '2028-03-01'])
        ->assertCreated()
        ->assertJsonPath('data.start_date', '2028-02-29')
        ->assertJsonPath('data.end_date', '2028-03-01');
});
