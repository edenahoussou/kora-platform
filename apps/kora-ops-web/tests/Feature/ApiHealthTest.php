<?php

use Illuminate\Support\Facades\DB;

it('returns kora bus service health with dependency checks', function (): void {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonStructure([
            'status',
            'service',
            'scope',
            'version',
            'timestamp',
            'checks' => [
                'database' => ['status', 'latency_ms'],
                'cache' => ['status', 'latency_ms'],
            ],
        ])
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('service', config('kora.service'))
        ->assertJsonPath('scope', config('kora.scope'))
        ->assertJsonPath('version', config('kora.api_version'))
        ->assertJsonPath('checks.database.status', 'ok')
        ->assertJsonPath('checks.cache.status', 'ok');
});

it('reports a degraded status with 503 when the database is unreachable', function (): void {
    DB::shouldReceive('connection')->andThrow(new RuntimeException('connection refused'));

    $this->getJson('/api/v1/health')
        ->assertServiceUnavailable()
        ->assertJsonPath('status', 'degraded')
        ->assertJsonPath('checks.database.status', 'error');
});

it('answers the load balancer ping with an empty 204 response', function (): void {
    $this->getJson('/api/v1/health/ping')->assertNoContent();
});
