<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsTest extends TestCase
{
    public function test_preflight_allows_configured_origin_only(): void
    {
        $headers = ['Origin' => 'http://localhost:8081', 'Access-Control-Request-Method' => 'POST', 'Access-Control-Request-Headers' => 'authorization,content-type'];
        $this->call('OPTIONS', '/api/v1/auth/login', [], [], [], $this->transformHeadersToServerVars($headers))
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:8081');

        $evil = ['Origin' => 'https://evil.example', 'Access-Control-Request-Method' => 'POST'];
        $response = $this->call('OPTIONS', '/api/v1/auth/login', [], [], [], $this->transformHeadersToServerVars($evil));
        $this->assertNull($response->headers->get('Access-Control-Allow-Origin'));
    }
}
