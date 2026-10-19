<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProductionHttpsTest extends TestCase
{
    public function test_production_rejects_insecure_requests(): void
    {
        config(['app.env' => 'production']);

        $this->getJson('/api/csrf-cookie')
            ->assertStatus(426)
            ->assertJsonPath('message', 'HTTPS is required.');
    }

    public function test_production_accepts_https_reported_by_a_trusted_proxy(): void
    {
        config([
            'app.env' => 'production',
            'trustedproxy.proxies' => ['10.0.0.1'],
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1', 'HTTPS' => 'off'])
            ->withHeader('X-Forwarded-Proto', 'https')
            ->getJson('/api/csrf-cookie')
            ->assertOk();
    }

    public function test_untrusted_forwarded_proto_does_not_bypass_production_https_requirement(): void
    {
        config([
            'app.env' => 'production',
            'trustedproxy.proxies' => ['10.0.0.1'],
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2', 'HTTPS' => 'off'])
            ->withHeader('X-Forwarded-Proto', 'https')
            ->getJson('/api/csrf-cookie')
            ->assertStatus(426);
    }
}
