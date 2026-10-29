<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test('account.menu')
            ->call('logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_logout_invalidates_the_session_and_regenerates_the_csrf_token(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        $this->get(route('dashboard'))->assertOk();

        session()->put('temporary-key', 'temporary-value');

        $previousSessionId = session()->getId();
        $previousToken = session()->token();

        Livewire::test('account.menu')
            ->call('logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNotSame($previousSessionId, session()->getId());
        $this->assertNotSame($previousToken, session()->token());
        $this->assertNull(session('temporary-key'));
    }
}
