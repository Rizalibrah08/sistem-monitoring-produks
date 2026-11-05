<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_entry_redirects_to_the_protected_dashboard(): void
    {
        $response = $this->get('/');

        $response->assertRedirectToRoute('dashboard');
    }
}
