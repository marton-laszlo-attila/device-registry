<?php

declare(strict_types=1);

use App\Models\ApiLog;
use App\Models\Contract;
use App\Models\ContractDevice;
use App\Models\Device;
use App\Services\Interfaces\DeviceServiceInterface;
use Illuminate\Database\QueryException;
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
    '415 body is not JSON' => [
        fn (): TestResponse => $this->post('/api/v1/devices', ['model' => 'X'], ['Accept' => 'application/json']),
        415,
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

it('answers 409 when a device is assigned while it is being deleted', function (): void {
    $device = Device::factory()->create();
    $contract = Contract::factory()->create();

    // Simulates the race: the service found no assignment, then another request
    // assigns the device right before this one deletes it.
    Device::deleting(function (Device $deleting) use ($contract): void {
        ContractDevice::factory()->for($contract)->for($deleting)->create();
    });

    $this->deleteJson("/api/v1/devices/{$device->id}")
        ->assertConflict()
        ->assertExactJson(['message' => 'A related record was changed by a concurrent request.']);

    $this->assertModelExists($device);
});

it('answers 409 when the device is deleted while it is being assigned', function (): void {
    $device = Device::factory()->create();
    $contract = Contract::factory()->create();

    // Simulates the race: the device passed the exists rule, then another request deletes it.
    ContractDevice::creating(function (ContractDevice $assignment): void {
        DB::table('devices')->where('id', $assignment->device_id)->delete();
    });

    $this->postJson("/api/v1/contracts/{$contract->id}/devices", [
        'device_id' => $device->id,
        'serial_number' => 'SN-RACE-2',
    ])
        ->assertConflict()
        ->assertExactJson(['message' => 'A related record was changed by a concurrent request.']);

    expect(ContractDevice::query()->count())->toBe(0);
});

it('keeps other database errors a 500 without details', function (): void {
    $cause = new PDOException('Base table or view not found');
    $cause->errorInfo = ['42S02', 1146, 'Table missing'];

    $this->mock(DeviceServiceInterface::class)
        ->shouldReceive('list')
        ->andThrow(new QueryException('mysql', 'select * from devices', [], $cause));

    $this->getJson('/api/v1/devices')->assertInternalServerError()->assertExactJson(['message' => 'Server Error']);
});

it('limits API requests to 60 per minute', function (): void {
    foreach (range(1, 60) as $attempt) {
        $this->getJson('/api/v1/devices')->assertOk();
    }

    $this->getJson('/api/v1/devices')
        ->assertTooManyRequests()
        ->assertHeader('Retry-After')
        ->assertExactJson(['message' => 'Too Many Attempts.']);

    expect(ApiLog::query()->latest('id')->value('status_code'))->toBe(429);
});

it('counts unknown API routes against the rate limit too', function (): void {
    // Every request is logged, so an unthrottled route would let a client fill the log without limit.
    foreach (range(1, 60) as $attempt) {
        $this->postJson('/api/v1/unknown', ['padding' => str_repeat('x', 1000)])->assertNotFound();
    }

    $this->postJson('/api/v1/unknown')->assertTooManyRequests()->assertHeader('Retry-After');
    $this->getJson('/api/v1/devices')->assertTooManyRequests();
});

it('does not apply the API rate limit outside the API', function (): void {
    foreach (range(1, 61) as $attempt) {
        $this->get('/up')->assertOk();
    }
});

it('keeps the HTML error page outside the API', function (): void {
    $response = $this->get('/missing-page')->assertNotFound();

    expect($response->headers->get('Content-Type'))->toStartWith('text/html');
});

it('answers a malformed JSON body with the consistent validation error, not a 500', function (string $body): void {
    $this->call('POST', '/api/v1/devices', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ], $body)
        ->assertUnprocessable()
        ->assertJsonStructure(['message', 'errors' => ['manufacturer', 'model', 'category']]);

    expect(Device::query()->count())->toBe(0);
})->with([
    'broken syntax' => ['{"manufacturer": '],
    'a list instead of an object' => ['[1, 2]'],
    'a bare string' => ['"text"'],
]);
