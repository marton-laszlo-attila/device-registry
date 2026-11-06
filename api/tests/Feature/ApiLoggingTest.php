<?php

declare(strict_types=1);

use App\Models\ApiLog;
use App\Models\Device;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;

it('logs a successful request with its response', function (): void {
    $payload = ['manufacturer' => 'Dell', 'model' => 'Latitude 5450', 'category' => 'laptop'];

    $response = $this->postJson('/api/v1/devices?source=test', $payload)->assertCreated();

    $log = ApiLog::query()->sole();

    expect($log->method)->toBe('POST')
        ->and($log->url)->toBe(url('/api/v1/devices?source=test'))
        ->and($log->status_code)->toBe(201)
        ->and($log->request_headers)->toHaveKey('content-type', 'application/json')
        ->and(json_decode((string) $log->request_body, true))->toBe($payload)
        ->and($log->response_body)->toBe($response->getContent())
        ->and($log->ip)->toBe('127.0.0.1')
        ->and($log->duration_ms)->toBeInt()->toBeGreaterThanOrEqual(0)
        ->and($log->created_at)->not->toBeNull();
});

it('logs failed requests too', function (string $method, string $uri, int $status): void {
    $this->json($method, $uri)->assertStatus($status);

    expect(ApiLog::query()->sole())
        ->method->toBe($method)
        ->status_code->toBe($status)
        ->response_body->toContain('message');
})->with([
    '404 unknown resource' => ['GET', '/api/v1/devices/999999', 404],
    '404 unknown route' => ['GET', '/api/v1/unknown', 404],
    '405 method not allowed' => ['DELETE', '/api/v1/devices', 405],
    '422 validation' => ['POST', '/api/v1/devices', 422],
]);

it('stores an empty body as null', function (): void {
    $device = Device::factory()->create();

    $this->delete("/api/v1/devices/{$device->id}")->assertNoContent();

    expect(ApiLog::query()->sole())
        ->request_body->toBeNull()
        ->response_body->toBeNull();
});

it('masks sensitive headers', function (string $header): void {
    $this->withHeader($header, 'top-secret-value')->getJson('/api/v1/devices')->assertOk();

    $log = ApiLog::query()->sole();

    expect($log->request_headers)->toHaveKey(strtolower($header), '***')
        ->and(DB::table('api_logs')->value('request_headers'))->not->toContain('top-secret-value');
})->with(['Authorization', 'Cookie']);

it('truncates long bodies', function (): void {
    config(['api_logging.max_body_length' => 20]);

    $this->postJson('/api/v1/devices', ['description' => str_repeat('é', 100)])->assertUnprocessable();

    $log = ApiLog::query()->sole();

    expect($log->request_body)->toEndWith('... [truncated]')
        ->and(mb_strlen((string) $log->request_body))->toBe(20 + mb_strlen('... [truncated]'))
        ->and($log->response_body)->toEndWith('... [truncated]');
});

it('does not log when disabled', function (): void {
    config(['api_logging.enabled' => false]);

    $this->getJson('/api/v1/devices')->assertOk();

    expect(ApiLog::query()->count())->toBe(0);
});

it('does not log requests outside the API', function (): void {
    $this->get('/up')->assertOk();

    expect(ApiLog::query()->count())->toBe(0);
});

it('does not break the response when logging fails', function (): void {
    Exceptions::fake();

    ApiLog::creating(fn () => throw new RuntimeException('Log storage is down.'));

    $this->getJson('/api/v1/devices')->assertOk();

    Exceptions::assertReported(fn (RuntimeException $e) => $e->getMessage() === 'Log storage is down.');
    expect(ApiLog::query()->count())->toBe(0);
});
