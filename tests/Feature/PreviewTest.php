<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PreviewTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_dashboard_renders_the_monitoring_workspace(): void
    {
        $this->withoutVite()->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Workspace', false)
            ->where('screen', 'dashboard')
            ->where('initialRole', 'manager'));
    }

    public function test_ticket_details_preserve_the_requested_ticket_identifier(): void
    {
        $this->withoutVite()->get('/tickets/PRD-2026-0012')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Workspace', false)
            ->where('screen', 'ticket-detail')
            ->where('entityId', 'PRD-2026-0012'));
    }

    public function test_an_unknown_preview_screen_returns_not_found(): void
    {
        $this->get('/not-a-screen')->assertNotFound();
    }

    public function test_demo_workspace_is_not_exposed_in_production(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->get('/')->assertNotFound();
    }
}
