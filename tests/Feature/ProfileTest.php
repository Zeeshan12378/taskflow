<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_requires_authentication(): void
    {
        $this->get(route('profile'))
            ->assertRedirect(route('login'));
    }

    public function test_profile_renders_for_authenticated_users(): void
    {
        $user = User::factory()->create([
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
        ]);

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee('Your profile')
            ->assertSee('Grace Hopper')
            ->assertSee('grace@example.com')
            ->assertSee('Dashboard')
            ->assertSee('Profile')
            ->assertSee('Sign out')
            ->assertSee('id="mobile-tab-bar"', false)
            ->assertSee('id="account-menu"', false)
            ->assertSee('aria-label="Mobile navigation"', false)
            ->assertSee('All rights reserved');
    }
}
