<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Welcome back')
            ->assertSee('Create an account');
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'ada@example.com',
            'password' => 'password123',
        ]);

        Livewire::test('auth.login')
            ->set('email', 'ada@example.com')
            ->set('password', 'password123')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_regenerates_the_session(): void
    {
        $this->get(route('login'))->assertOk();

        $previousSessionId = session()->getId();

        User::factory()->create([
            'email' => 'ada@example.com',
            'password' => 'password123',
        ]);

        Livewire::test('auth.login')
            ->set('email', 'ada@example.com')
            ->set('password', 'password123')
            ->call('login')
            ->assertRedirect(route('dashboard'));

        $this->assertNotSame($previousSessionId, session()->getId());
    }

    public function test_user_can_be_remembered(): void
    {
        $user = User::factory()->create([
            'email' => 'ada@example.com',
            'password' => 'password123',
        ]);

        Livewire::test('auth.login')
            ->set('email', 'ada@example.com')
            ->set('password', 'password123')
            ->set('remember', true)
            ->call('login')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        Livewire::test('auth.login')
            ->set('email', 'ada@example.com')
            ->set('password', 'wrong-password')
            ->call('login')
            ->assertHasErrors(['email']);

        $this->assertGuest();
    }

    public function test_login_requires_email_and_password(): void
    {
        Livewire::test('auth.login')
            ->call('login')
            ->assertHasErrors([
                'email' => 'required',
                'password' => 'required',
            ]);
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        User::factory()->create([
            'email' => 'ada@example.com',
            'password' => 'password123',
        ]);

        $throttleKey = Str::transliterate(Str::lower('ada@example.com').'|'.request()->ip());

        for ($attempt = 0; $attempt < 5; $attempt++) {
            RateLimiter::hit($throttleKey, 60);
        }

        Livewire::test('auth.login')
            ->set('email', 'ada@example.com')
            ->set('password', 'password123')
            ->call('login')
            ->assertHasErrors(['email'])
            ->assertSee('Too many login attempts');

        $this->assertGuest();
    }

    public function test_authenticated_users_are_redirected_away_from_login(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('login'))
            ->assertRedirect(route('dashboard'));
    }
}
