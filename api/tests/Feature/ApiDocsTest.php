<?php

declare(strict_types=1);

use App\Models\ApiLog;

beforeEach(function (): void {
    config(['docs.username' => 'docs-user', 'docs.password' => 'docs-secret']);
});

dataset('docs urls', ['/docs/api', '/docs/api.json']);

it('asks for basic auth without credentials', function (string $uri): void {
    $this->get($uri)
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate', 'Basic realm="API Docs"');
})->with('docs urls');

it('rejects wrong credentials', function (string $uri, string $username, string $password): void {
    $this->withBasicAuth($username, $password)->get($uri)->assertUnauthorized();
})->with('docs urls')->with([
    'wrong password' => ['docs-user', 'wrong'],
    'wrong username' => ['wrong', 'docs-secret'],
]);

it('serves the documentation with valid credentials', function (string $uri): void {
    $this->withBasicAuth('docs-user', 'docs-secret')->get($uri)->assertOk();
})->with('docs urls');

it('locks a client out for a minute after 10 failed logins', function (): void {
    foreach (range(1, 10) as $attempt) {
        $this->withBasicAuth('docs-user', 'guess-' . $attempt)->get('/docs/api.json')->assertUnauthorized();
    }

    $this->withBasicAuth('docs-user', 'guess-11')->get('/docs/api.json')
        ->assertTooManyRequests()
        ->assertHeader('Retry-After');

    // Even the right password waits, otherwise the lockout would confirm a correct guess.
    $this->withBasicAuth('docs-user', 'docs-secret')->get('/docs/api')->assertTooManyRequests();

    $this->travel(61)->seconds();

    $this->withBasicAuth('docs-user', 'docs-secret')->get('/docs/api')->assertOk();
});

it('does not count successful logins against the limit', function (): void {
    foreach (range(1, 15) as $attempt) {
        $this->withBasicAuth('docs-user', 'docs-secret')->get('/docs/api.json')->assertOk();
    }
});

it('counts failed logins per client IP', function (): void {
    foreach (range(1, 10) as $attempt) {
        $this->withBasicAuth('docs-user', 'wrong')->get('/docs/api.json')->assertUnauthorized();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
        ->withBasicAuth('docs-user', 'docs-secret')
        ->get('/docs/api.json')
        ->assertOk();
});

it('denies access when no credentials are configured', function (?string $configured): void {
    config(['docs.username' => $configured, 'docs.password' => $configured]);

    $this->withBasicAuth('', '')->get('/docs/api')->assertUnauthorized();
    $this->withBasicAuth('docs-user', 'docs-secret')->get('/docs/api')->assertUnauthorized();
})->with([
    'empty' => [''],
    'missing' => [null],
]);

it('documents every endpoint of the task', function (): void {
    $spec = $this->withBasicAuth('docs-user', 'docs-secret')->getJson('/docs/api.json')->assertOk()->json();

    expect($spec['info']['title'])->toBe('Device Registry API')
        ->and(array_map(array_keys(...), $spec['paths']))->toMatchArray([
            '/v1/devices' => ['get', 'post'],
            '/v1/devices/{device}' => ['get', 'put', 'delete'],
            '/v1/contracts' => ['get', 'post'],
            '/v1/contracts/{contract}' => ['get', 'put', 'delete'],
            '/v1/contracts/{contract}/devices' => ['post'],
            '/v1/contracts/{contract}/devices/{assignment}' => ['delete'],
        ]);
});

it('keeps the exported specification in sync with the code', function (): void {
    $live = $this->withBasicAuth('docs-user', 'docs-secret')->getJson('/docs/api.json')->json();
    $exported = json_decode((string) file_get_contents(base_path('docs/openapi.json')), true);

    expect($exported['paths'])->toBe($live['paths'])
        ->and($exported['components'])->toBe($live['components']);
});

it('does not log documentation traffic', function (): void {
    $this->withBasicAuth('docs-user', 'docs-secret')->get('/docs/api.json')->assertOk();

    expect(ApiLog::query()->count())->toBe(0);
});
