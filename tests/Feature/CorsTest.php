<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsTest extends TestCase
{
    public function test_preflight_allows_any_origin_by_default(): void
    {
        foreach (['http://localhost:8081', 'https://anywhere.example'] as $origin) {
            $headers = ['Origin' => $origin, 'Access-Control-Request-Method' => 'POST', 'Access-Control-Request-Headers' => 'authorization,content-type'];
            $response = $this->call('OPTIONS', '/api/v1/auth/login', [], [], [], $this->transformHeadersToServerVars($headers));

            $this->assertContains($response->headers->get('Access-Control-Allow-Origin'), ['*', $origin]);
        }
    }

    public function test_cors_does_not_open_the_api_to_unauthenticated_calls(): void
    {
        $this->getJson('/api/v1/pets', ['Origin' => 'https://anywhere.example'])->assertUnauthorized();
    }
}
