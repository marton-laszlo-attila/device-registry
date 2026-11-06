<?php

declare(strict_types=1);

it('reports a healthy application', function (): void {
    $this->get('/up')->assertOk();
});

it('serves the landing page', function (): void {
    $this->get('/')->assertOk()->assertSee(config('app.name'));
});

it('answers unknown API routes with JSON', function (): void {
    $this->getJson('/api/v1/unknown')->assertNotFound()->assertJsonStructure(['message']);
});
