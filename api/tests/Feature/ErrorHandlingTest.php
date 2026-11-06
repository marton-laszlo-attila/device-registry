<?php

declare(strict_types=1);

use App\Models\Contract;
use App\Models\ContractDevice;
use App\Models\Device;
use App\Services\Interfaces\DeviceServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    config(['app.debug' => false]);
});

dataset('api errors', [
    '404 unknown resource' => [
        fn (): TestResponse => $this->getJson('/api/v1/devices/999999'),
        404,
    ],
    '404 unknown route' => [
        fn (): TestResponse => $this->getJson('/api/v1/unknown'),
        404,
    ],
    '405 method not allowed' => [
        fn (): TestResponse => $this->deleteJson('/api/v1/devices'),
        405,
    ],
    '409 business rule' => [
        fn (): TestResponse => $this->deleteJson('/api/v1/devices/' . ContractDevice::factory()->create()->device_id),
        409,
    ],
    '422 validation' => [
        fn (): TestResponse => $this->postJson('/api/v1/devices', []),
        422,
    ],
    '500 unexpected error' => [
        function (): TestResponse {
            $this->mock(DeviceServiceInterface::class)
                ->shouldReceive('list')
                ->andThrow(new RuntimeException('SQLSTATE secret internals'));

            return $this->getJson('/api/v1/devices');
        },
        500,
    ],
]);

it('renders every API error as consistent JSON', function (TestResponse $response, int $status): void {
    $response->assertStatus($status)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonStructure(['message'])
        ->assertJsonMissingPath('exception')
        ->assertJsonMissingPath('trace')
        ->assertJsonMissingPath('file')
        ->assertJsonMissingPath('line');

    expect($response->json('message'))->toBeString()->not->toBeEmpty();

    $status === 422
        ? $response->assertJsonStructure(['errors'])
        : $response->assertJsonMissingPath('errors');
})->with('api errors');

it('answers JSON without an Accept header', function (): void {
    $this->get('/api/v1/devices/999999')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson(['message' => 'Resource not found.']);
});

it('does not reveal the model or the id on 404', function (string $uri): void {
    $response = $this->getJson($uri)->assertExactJson(['message' => 'Resource not found.']);

    expect($response->getContent())->not->toContain('Models')->not->toContain('999999');
})->with([
    '/api/v1/devices/999999',
    '/api/v1/contracts/999999',
]);

it('does not leak the internal error message on 500', function (): void {
    $this->mock(DeviceServiceInterface::class)
        ->shouldReceive('list')
        ->andThrow(new RuntimeException('SQLSTATE secret internals'));

    $this->getJson('/api/v1/devices')->assertExactJson(['message' => 'Server Error']);
});

it('answers 409 when a concurrent request takes the serial number first', function (): void {
    $contract = Contract::factory()->create();
    $device = Device::factory()->create();

    // Simulates the race: the request passes validation, then another request
    // inserts the same serial number right before this one is saved.
    ContractDevice::creating(function (ContractDevice $assignment): void {
        DB::table('contract_device')->insert([
            'contract_id' => $assignment->contract_id,
            'device_id' => $assignment->device_id,
            'serial_number' => $assignment->serial_number,
        ]);
    });

    $this->postJson("/api/v1/contracts/{$contract->id}/devices", [
        'device_id' => $device->id,
        'serial_number' => 'SN-RACE-1',
    ])
        ->assertConflict()
        ->assertExactJson(['message' => 'The resource conflicts with an existing record.']);

    expect(ContractDevice::query()->where('serial_number', 'SN-RACE-1')->count())->toBe(1);
});

it('limits API requests to 60 per minute', function (): void {
    foreach (range(1, 60) as $attempt) {
        $this->getJson('/api/v1/devices')->assertOk();
    }

    $this->getJson('/api/v1/devices')
        ->assertTooManyRequests()
        ->assertHeader('Retry-After')
        ->assertExactJson(['message' => 'Too Many Attempts.']);
});

it('keeps the HTML error page outside the API', function (): void {
    $response = $this->get('/missing-page')->assertNotFound();

    expect($response->headers->get('Content-Type'))->toStartWith('text/html');
});
