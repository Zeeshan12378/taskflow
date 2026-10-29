<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_requires_authentication(): void
    {
        $this->get(route('settings'))
            ->assertRedirect(route('login'));
    }

    public function test_settings_renders_for_authenticated_users(): void
    {
        $user = User::factory()->create([
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
        ]);

        $this->actingAs($user)
            ->get(route('settings'))
            ->assertOk()
            ->assertSee('Settings')
            ->assertSee('General')
            ->assertSee('Profile')
            ->assertSee('Password')
            ->assertSee('Appearance')
            ->assertSee('Light')
            ->assertSee('Dark')
            ->assertSee('System')
            ->assertSee('All rights reserved');
    }

    public function test_settings_uses_the_stored_theme_on_initial_page_load(): void
    {
        $user = User::factory()->create(['theme' => 'dark']);

        $this->actingAs($user)
            ->get(route('settings'))
            ->assertOk()
            ->assertSee('data-theme="dark"', false);
    }

    public function test_user_can_switch_tabs(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test('settings.index')
            ->assertSet('activeTab', 'general')
            ->call('setTab', 'profile')
            ->assertSet('activeTab', 'profile')
            ->assertSee('Profile information')
            ->call('setTab', 'password')
            ->assertSet('activeTab', 'password')
            ->assertSee('Change password')
            ->call('setTab', 'invalid')
            ->assertSet('activeTab', 'password');
    }

    public function test_user_can_update_their_theme(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test('settings.index')
            ->call('setTheme', 'dark')
            ->assertSet('theme', 'dark')
            ->assertSet('themeStatus', 'Theme preference saved.')
            ->assertSee('Theme preference saved.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'theme' => 'dark',
        ]);
    }

    public function test_invalid_theme_values_are_ignored(): void
    {
        $user = User::factory()->create(['theme' => 'system']);

        $this->actingAs($user);

        Livewire::test('settings.index')
            ->call('setTheme', 'neon')
            ->assertSet('theme', 'system');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'theme' => 'system',
        ]);
    }

    public function test_user_can_update_their_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
        ]);

        $this->actingAs($user);

        Livewire::test('settings.index')
            ->call('setTab', 'profile')
            ->set('name', 'Grace M. Hopper')
            ->set('email', 'grace.hopper@example.com')
            ->call('updateProfile')
            ->assertHasNoErrors()
            ->assertSet('profileStatus', 'Profile updated successfully.')
            ->assertDispatched('profile-updated')
            ->assertSee('Profile updated successfully.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Grace M. Hopper',
            'email' => 'grace.hopper@example.com',
        ]);
    }

    public function test_profile_email_must_be_unique(): void
    {
        $user = User::factory()->create(['email' => 'grace@example.com']);

        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($user);

        Livewire::test('settings.index')
            ->set('name', 'Grace Hopper')
            ->set('email', 'taken@example.com')
            ->call('updateProfile')
            ->assertHasErrors(['email' => 'unique']);
    }

    public function test_user_can_keep_their_existing_email_when_updating_their_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
        ]);

        $this->actingAs($user);

        Livewire::test('settings.index')
            ->set('name', 'Grace M. Hopper')
            ->set('email', 'grace@example.com')
            ->call('updateProfile')
            ->assertHasNoErrors();
    }

    public function test_user_can_change_their_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);

        $this->actingAs($user);

        Livewire::test('settings.index')
            ->call('setTab', 'password')
            ->set('current_password', 'old-password')
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('updatePassword')
            ->assertHasNoErrors()
            ->assertSet('passwordStatus', 'Password updated successfully.')
            ->assertSet('current_password', '')
            ->assertSet('password', '')
            ->assertSet('password_confirmation', '')
            ->assertSee('Password updated successfully.');

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_current_password_must_be_correct(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);

        $this->actingAs($user);

        Livewire::test('settings.index')
            ->set('current_password', 'wrong-password')
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('updatePassword')
            ->assertHasErrors(['current_password']);

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_new_password_must_be_confirmed(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);

        $this->actingAs($user);

        Livewire::test('settings.index')
            ->set('current_password', 'old-password')
            ->set('password', 'new-password')
            ->set('password_confirmation', 'different-password')
            ->call('updatePassword')
            ->assertHasErrors(['password' => 'confirmed']);

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }
}
