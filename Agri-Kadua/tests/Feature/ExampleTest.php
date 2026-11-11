<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_root_redirects_a_guest_to_the_dashboard_route(): void
    {
        $response = $this->get('/');

        $response->assertRedirectToRoute('dashboard');
    }
}
