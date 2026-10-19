<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_there_is_no_web_root_page(): void
    {
        $this->get('/')->assertNotFound();
    }

    public function test_health_endpoint_is_up(): void
    {
        $this->get('/up')->assertOk();
    }
}
