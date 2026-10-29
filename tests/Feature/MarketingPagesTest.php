<?php

namespace Tests\Feature;

use Tests\TestCase;

class MarketingPagesTest extends TestCase
{
    public function test_public_marketing_pages_render_navigation_and_guest_theme_control(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Organize your tasks.')
            ->assertSee('Choose appearance')
            ->assertSee('Light')
            ->assertSee('Dark')
            ->assertSee('System')
            ->assertSee(route('features'))
            ->assertSee(route('about'));

        $this->get(route('features'))
            ->assertOk()
            ->assertSee('A flexible workspace for the work ahead')
            ->assertSee(route('register'));

        $this->get(route('about'))
            ->assertOk()
            ->assertSee('Good work starts with a clear next step.')
            ->assertSee(route('register'));
    }
}
