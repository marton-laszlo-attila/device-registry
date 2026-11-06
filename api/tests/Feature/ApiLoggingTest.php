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
})->with(['Authorization', 'Proxy-Authorization', 'Cookie', 'X-Api-Key', 'X-CSRF-Token', 'X-XSRF-Token']);

it('does not store the credentials PHP derives from an Authorization header', function (string $scheme): void {
    $scheme === 'basic'
        ? $this->withBasicAuth('api-user', 'basic-secret')
        : $this->withHeader('Authorization', 'Digest username="api-user", response="digest-secret"');

    $this->getJson('/api/v1/devices')->assertOk();

    expect((string) DB::table('api_logs')->value('request_headers'))
        ->not->toContain('secret')
        ->not->toContain('api-user')
        ->not->toContain(base64_encode('api-user:basic-secret'));
})->with(['basic', 'digest']);

it('logs a request with invalid UTF-8 instead of losing it', function (): void {
    $device = Device::factory()->create();

    // A single invalid byte used to make the insert fail, so the deletion left no trace.
    $this->withHeader('X-Note', "caf\xE9")->deleteJson("/api/v1/devices/{$device->id}")->assertNoContent();

    expect(ApiLog::query()->sole())
        ->method->toBe('DELETE')
        ->status_code->toBe(204)
        ->request_headers->toHaveKey('x-note', 'caf?');
});

it('logs a body with invalid UTF-8 instead of losing it', function (): void {
    $this->call('POST', '/api/v1/devices', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ], "{\"manufacturer\": \"\xFF\"}")->assertUnprocessable();

    expect(ApiLog::query()->sole()->request_body)->toBe('{"manufacturer": "?"}');
});

it('deletes log rows older than the retention period', function (): void {
    config(['api_logging.retention_days' => 30]);

    $this->travel(-31)->days();
    $this->getJson('/api/v1/devices')->assertOk();
    $this->travelBack();
    $this->getJson('/api/v1/contracts')->assertOk();

    $this->artisan('model:prune', ['--model' => [ApiLog::class]])->assertSuccessful();

    expect(ApiLog::query()->pluck('url')->all())->toBe([url('/api/v1/contracts')]);
});

it('prunes the log every day', function (): void {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('0 0 * * *  php artisan model:prune --model=\'App\Models\ApiLog\'')
        ->assertSuccessful();
});

it('truncates long bodies by bytes without splitting a character', function (): void {
    config(['api_logging.max_body_bytes' => 21]);

    // {"description":" is 16 bytes, then 2 bytes per "é": 21 bytes would end inside the third one.
    $response = $this->call('POST', '/api/v1/devices', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ], json_encode(['description' => str_repeat('é', 100)], JSON_UNESCAPED_UNICODE))->assertUnprocessable();

    $log = ApiLog::query()->sole();

    expect($log->request_body)->toBe('{"description":"éé... [truncated]')
        ->and($log->response_body)->toBe(substr((string) $response->getContent(), 0, 21) . '... [truncated]');
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
