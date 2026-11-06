<?php

declare(strict_types=1);

use App\Models\ApiLog;
use App\Models\Contract;
use App\Models\Device;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
 * Regression tests of the security review (docs/SECURITY_REVIEW.md) that cut across
 * the endpoints. Findings tied to one feature are tested next to it (ApiDocsTest,
 * ApiLoggingTest, ErrorHandlingTest, ContractDeviceTest).
 */

describe('cross-site requests', function (): void {
    dataset('bodies a cross-site HTML form can send', [
        'form-urlencoded' => ['application/x-www-form-urlencoded'],
        'multipart' => ['multipart/form-data'],
        'text/plain' => ['text/plain'],
        'no content type' => [''],
    ]);

    it('rejects a write whose body is not JSON', function (string $contentType): void {
        $device = Device::factory()->create(['model' => 'Original']);

        $server = ['CONTENT_TYPE' => $contentType, 'HTTP_ORIGIN' => 'https://evil.example'];
        $fields = ['manufacturer' => 'Evil', 'model' => 'Form', 'category' => 'other'];

        $this->call('POST', '/api/v1/devices', $fields, [], [], $server)
            ->assertUnsupportedMediaType()
            ->assertJsonPath('message', 'The request body must be JSON (Content-Type: application/json).');
        $this->call('PATCH', "/api/v1/devices/{$device->id}", $fields, [], [], $server)->assertUnsupportedMediaType();
        $this->call('PUT', "/api/v1/devices/{$device->id}", $fields, [], [], $server)->assertUnsupportedMediaType();

        expect(Device::query()->sole()->model)->toBe('Original');
    })->with('bodies a cross-site HTML form can send');

    it('rejects a form POST that asks for DELETE through _method', function (string $where): void {
        $device = Device::factory()->create();
        $contract = Contract::factory()->create();

        $query = $where === 'query string' ? '?_method=DELETE' : '';
        $fields = $where === 'form field' ? ['_method' => 'DELETE'] : [];
        $server = ['CONTENT_TYPE' => 'application/x-www-form-urlencoded', 'HTTP_ACCEPT' => 'application/json'];

        foreach (["/api/v1/devices/{$device->id}", "/api/v1/contracts/{$contract->id}"] as $uri) {
            $this->call('POST', $uri . $query, $fields, [], [], $server)->assertUnsupportedMediaType();
        }

        $this->assertModelExists($device);
        $this->assertModelExists($contract);
    })->with(['query string', 'form field']);

    it('accepts JSON with a charset or a +json media type', function (string $contentType): void {
        $this->call('POST', '/api/v1/devices', [], [], [], [
            'CONTENT_TYPE' => $contentType,
            'HTTP_ACCEPT' => 'application/json',
        ], (string) json_encode(['manufacturer' => 'Dell', 'model' => 'Latitude 7450', 'category' => 'laptop']))
            ->assertCreated();
    })->with(['application/json; charset=utf-8', 'application/vnd.api+json']);

    it('does not let other origins call the API from a browser', function (): void {
        $this->call('OPTIONS', '/api/v1/devices/1', [], [], [], [
            'HTTP_ORIGIN' => 'https://evil.example',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'DELETE',
        ])->assertHeaderMissing('Access-Control-Allow-Origin');

        $this->getJson('/api/v1/devices', ['Origin' => 'https://evil.example'])
            ->assertOk()
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    });

    it('lets the configured origins call the API', function (): void {
        config(['cors.allowed_origins' => ['https://registry.example.com']]);

        $this->call('OPTIONS', '/api/v1/devices/1', [], [], [], [
            'HTTP_ORIGIN' => 'https://registry.example.com',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'DELETE',
        ])->assertHeader('Access-Control-Allow-Origin', 'https://registry.example.com');
    });
});

describe('client address', function (): void {
    it('ignores X-Forwarded-For, whatever the Host header says', function (string $host): void {
        $this->getJson("http://{$host}/api/v1/devices", ['X-Forwarded-For' => '203.0.113.7'])->assertOk();

        expect(ApiLog::query()->sole()->ip)->toBe('127.0.0.1');
    })->with(['localhost', 'x.on-forge.com', 'x.on-vapor.com']);

    it('cannot get around the rate limit with a forged X-Forwarded-For', function (): void {
        foreach (range(1, 60) as $attempt) {
            $this->getJson('http://x.on-forge.com/api/v1/devices', ['X-Forwarded-For' => "203.0.113.{$attempt}"])
                ->assertOk();
        }

        $this->getJson('http://x.on-forge.com/api/v1/devices', ['X-Forwarded-For' => '198.51.100.1'])
            ->assertTooManyRequests();
    });
});

describe('injection', function (): void {
    dataset('sql payloads', [
        'tautology' => ["' OR '1'='1"],
        'stacked query' => ['1; DROP TABLE devices; --'],
        'union' => ["x' UNION SELECT contract_number FROM contracts --"],
        'escaped quote' => ["\\' OR 1=1 #"],
        'double quotes' => ['" OR ""="'],
    ]);

    it('stores SQL in text fields as plain text', function (string $payload): void {
        $deviceId = $this->createDeviceViaApi(['manufacturer' => $payload, 'description' => $payload]);
        $contractId = $this->createContractViaApi(['partner_name' => $payload, 'notes' => $payload]);

        $this->getJson("/api/v1/devices/{$deviceId}")
            ->assertJsonPath('data.manufacturer', $payload)
            ->assertJsonPath('data.description', $payload);
        $this->getJson("/api/v1/contracts/{$contractId}")
            ->assertJsonPath('data.partner_name', $payload)
            ->assertJsonPath('data.notes', $payload);

        expect(DB::table('devices')->count())->toBe(1)
            ->and(DB::table('contracts')->count())->toBe(1);
    })->with('sql payloads');

    it('never puts query parameters into SQL', function (string $payload): void {
        Device::factory()->count(3)->create();

        $query = http_build_query(array_fill_keys(['per_page', 'page', 'sort', 'filter'], $payload));

        $this->getJson("/api/v1/devices?{$query}")->assertOk()->assertJsonPath('meta.current_page', 1);
        $this->getJson("/api/v1/contracts?{$query}")->assertOk()->assertJsonPath('meta.current_page', 1);

        expect(Device::query()->count())->toBe(3);
    })->with('sql payloads');

    it('checks the manufacturer and model pair with the literal value', function (string $manufacturer): void {
        $payload = ['manufacturer' => $manufacturer, 'model' => 'M-1', 'category' => 'laptop'];

        // A quote and a backslash used to add parameters to the serialized unique rule (500).
        $id = $this->postJson('/api/v1/devices', $payload)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/devices', $payload)->assertUnprocessable()->assertJsonValidationErrors(['model']);
        $this->patchJson("/api/v1/devices/{$id}", ['model' => 'M-2'])->assertOk();
    })->with([
        'extra condition' => ['a\\",nope,x'],
        'odd parameter count' => ['a\\",nope'],
        'quotes' => ['"quoted"'],
        'backslash' => ['back\\slash'],
    ]);

    it('checks the pair when the counterpart is not a string', function (): void {
        $this->postJson('/api/v1/devices', ['manufacturer' => ['Dell', 'HP'], 'model' => 'M-1', 'category' => 'laptop'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['manufacturer']);
    });
});

describe('resource consumption', function (): void {
    it('answers an empty page instead of a 500 for a huge page number', function (string $uri, string $page): void {
        Device::factory()->create();
        Contract::factory()->create();

        $this->getJson("{$uri}?per_page=100&page={$page}")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    })->with(['/api/v1/devices', '/api/v1/contracts'])->with([
        'offset overflows an int' => ['922337203685477580'],
        'beyond PHP_INT_MAX' => ['99999999999999999999'],
    ]);
});

describe('inventory', function (): void {
    it('exposes only the API, its documentation, the health check and the landing page', function (): void {
        $uris = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route): string => $route->uri())
            // Scramble's dev tools asset exists only while APP_DEBUG is on; tested below.
            ->reject(fn (string $uri): bool => $uri === '_scramble/dev-tools/devtools.js')
            ->unique()
            ->sort();

        expect($uris->values()->all())->toBe([
            '/',
            'api/v1/contracts',
            'api/v1/contracts/{contract}',
            'api/v1/contracts/{contract}/devices',
            'api/v1/contracts/{contract}/devices/{assignment}',
            'api/v1/devices',
            'api/v1/devices/{device}',
            'docs/api',
            'docs/api.json',
            'up',
        ]);
    });

    it('never serves the Scramble dev tools without the documentation credentials', function (): void {
        config(['docs.username' => 'docs-user', 'docs.password' => 'docs-secret']);

        expect($this->get('/_scramble/dev-tools/devtools.js')->getStatusCode())->toBeIn([401, 404]);
    });

    it('keeps the unauthenticated Boost browser log endpoint switched off', function (): void {
        // Boost does not boot in tests, so the route list above cannot show its route.
        expect(config('boost.browser_logs_watcher'))->toBeFalse();
    });
});
