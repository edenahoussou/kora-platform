<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiHealthTest extends TestCase
{
    public function test_api_v1_health_returns_kora_bus_scope(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'service' => 'kora-platform',
                'scope' => 'mvp-bus',
            ]);
    }
}
