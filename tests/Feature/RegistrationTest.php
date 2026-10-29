<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_page_renders(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Create your account')
            ->assertSee('TaskFlow');
    }

    public function test_guest_can_register_and_is_logged_in(): void
    {
        Livewire::test('auth.register')
            ->set('name', 'Ada Lovelace')
            ->set('email', 'ada@example.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $user = User::where('email', 'ada@example.com')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame('password123', $user->password);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_registration_regenerates_the_session(): void
    {
        $this->get(route('register'))->assertOk();

        $previousSessionId = session()->getId();

        Livewire::test('auth.register')
            ->set('name', 'Ada Lovelace')
            ->set('email', 'ada@example.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('register')
            ->assertRedirect(route('dashboard'));

        $this->assertNotSame($previousSessionId, session()->getId());
    }

    public function test_registration_requires_valid_input(): void
    {
        Livewire::test('auth.register')
            ->call('register')
            ->assertHasErrors([
                'name' => 'required',
                'email' => 'required',
                'password' => 'required',
            ]);
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        Livewire::test('auth.register')
            ->set('name', 'Ada Lovelace')
            ->set('email', 'ada@example.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('register')
            ->assertHasErrors(['email' => 'unique']);
    }

    public function test_password_must_be_confirmed(): void
    {
        Livewire::test('auth.register')
            ->set('name', 'Ada Lovelace')
            ->set('email', 'ada@example.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'different')
            ->call('register')
            ->assertHasErrors(['password' => 'confirmed']);
    }

    public function test_authenticated_users_are_redirected_away_from_registration(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('register'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_dashboard_renders_for_authenticated_users(): void
    {
        $user = User::factory()->create(['name' => 'Grace Hopper']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Welcome, Grace Hopper')
            ->assertSee('Main navigation')
            ->assertSee('No projects yet')
            ->assertSee('Upcoming tasks')
            ->assertSee('Nothing scheduled');
    }
}
