<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_requires_authentication(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_dashboard_renders_summary_cards_and_empty_state_for_new_accounts(): void
    {
        $user = User::factory()->create([
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Welcome, Grace Hopper')
            ->assertSee('Your account is ready')
            ->assertSee('Your workspace')
            ->assertSee('Projects')
            ->assertSee('0 active')
            ->assertSee('Tasks')
            ->assertSee('Total tasks')
            ->assertSee('Completed')
            ->assertSee('Tasks finished')
            ->assertSee('Overdue')
            ->assertSee('Need attention')
            ->assertSee('No projects yet');

        $this->assertAuthenticatedAs($user);
    }
}
