<?php

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Settings · TaskFlow')] class extends Component
{
    #[Url(as: 'tab', history: true)]
    public string $activeTab = 'general';

    public string $theme = 'system';

    public string $name = '';

    public string $email = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public ?string $themeStatus = null;

    public ?string $profileStatus = null;

    public ?string $passwordStatus = null;

    public function mount(): void
    {
        $user = auth()->user();

        $this->theme = $user->theme ?? 'system';
        $this->name = $user->name;
        $this->email = $user->email;

        if (! in_array($this->activeTab, $this->tabs(), true)) {
            $this->activeTab = 'general';
        }
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, $this->tabs(), true)) {
            $this->activeTab = $tab;
            $this->resetValidation();
        }
    }

    public function setTheme(string $theme): void
    {
        if (! in_array($theme, ['light', 'dark', 'system'], true)) {
            return;
        }

        $this->theme = $theme;

        auth()->user()->update(['theme' => $theme]);

        $this->themeStatus = 'Theme preference saved.';
    }

    public function updateProfile(): void
    {
        $user = auth()->user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        ]);

        $user->update($validated);

        $this->profileStatus = 'Profile updated successfully.';

        $this->dispatch('profile-updated');
    }

    public function updatePassword(): void
    {
        $validated = $this->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'confirmed', Password::min(8), 'different:current_password'],
        ]);

        auth()->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');
        $this->resetValidation();

        $this->passwordStatus = 'Password updated successfully.';
    }

    /**
     * @return array<int, string>
     */
    private function tabs(): array
    {
        return ['general', 'profile', 'password'];
    }
};
?>

<main class="mx-auto w-full max-w-4xl flex-1 px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div>
            <p class="text-sm font-medium text-indigo-600 dark:text-indigo-400">Account</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Settings</h1>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                Manage your appearance, profile details, and password.
            </p>
        </div>

        <div class="mt-6 border-b border-slate-200 dark:border-slate-800">
            <nav class="-mb-px flex gap-1 overflow-x-auto" role="tablist" aria-label="Settings sections">
                <button
                    type="button"
                    id="general-tab"
                    role="tab"
                    aria-selected="{{ $activeTab === 'general' ? 'true' : 'false' }}"
                    aria-controls="general-panel"
                    wire:click="setTab('general')"
                    @class([
                        'whitespace-nowrap rounded-t-lg border-b-2 px-4 py-3 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-950',
                        'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400' => $activeTab === 'general',
                        'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:text-slate-400 dark:hover:border-slate-700 dark:hover:text-slate-200' => $activeTab !== 'general',
                    ])
                >
                    General
                </button>

                <button
                    type="button"
                    id="profile-tab"
                    role="tab"
                    aria-selected="{{ $activeTab === 'profile' ? 'true' : 'false' }}"
                    aria-controls="profile-panel"
                    wire:click="setTab('profile')"
                    @class([
                        'whitespace-nowrap rounded-t-lg border-b-2 px-4 py-3 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-950',
                        'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400' => $activeTab === 'profile',
                        'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:text-slate-400 dark:hover:border-slate-700 dark:hover:text-slate-200' => $activeTab !== 'profile',
                    ])
                >
                    Profile
                </button>

                <button
                    type="button"
                    id="password-tab"
                    role="tab"
                    aria-selected="{{ $activeTab === 'password' ? 'true' : 'false' }}"
                    aria-controls="password-panel"
                    wire:click="setTab('password')"
                    @class([
                        'whitespace-nowrap rounded-t-lg border-b-2 px-4 py-3 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-950',
                        'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400' => $activeTab === 'password',
                        'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:text-slate-400 dark:hover:border-slate-700 dark:hover:text-slate-200' => $activeTab !== 'password',
                    ])
                >
                    Password
                </button>
            </nav>
        </div>

        @if ($activeTab === 'general')
            <section
                id="general-panel"
                role="tabpanel"
                aria-labelledby="general-tab"
                class="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900"
            >
                <div>
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Appearance</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Choose how TaskFlow looks. Your preference is saved to your account.
                    </p>
                </div>

                <div class="mt-6 grid gap-3 sm:grid-cols-3">
                    <button
                        type="button"
                        wire:click="setTheme('light')"
                        x-on:click="window.TaskFlow.applyTheme('light')"
                        aria-pressed="{{ $theme === 'light' ? 'true' : 'false' }}"
                        @class([
                            'rounded-xl border p-4 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900',
                            'border-indigo-600 bg-indigo-50/60 ring-2 ring-indigo-600/20 dark:border-indigo-400 dark:bg-indigo-500/10' => $theme === 'light',
                            'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700 dark:hover:bg-slate-800/60' => $theme !== 'light',
                        ])
                    >
                        <span class="flex h-16 overflow-hidden rounded-lg border border-slate-200 bg-slate-100">
                            <span class="w-1/3 border-r border-slate-200 bg-slate-200"></span>
                            <span class="flex-1 bg-white"></span>
                        </span>

                        <span class="mt-3 block text-sm font-semibold text-slate-900 dark:text-white">Light</span>
                        <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">Always use light mode</span>

                        <span @class([
                            'mt-3 inline-flex items-center gap-1.5 text-xs font-medium',
                            'text-indigo-600 dark:text-indigo-400' => $theme === 'light',
                            'text-slate-500 dark:text-slate-400' => $theme !== 'light',
                        ])>
                            <span @class([
                                'h-1.5 w-1.5 rounded-full',
                                'bg-indigo-600 dark:bg-indigo-400' => $theme === 'light',
                                'bg-slate-300 dark:bg-slate-600' => $theme !== 'light',
                            ])></span>
                            {{ $theme === 'light' ? 'Selected' : 'Select theme' }}
                        </span>
                    </button>

                    <button
                        type="button"
                        wire:click="setTheme('dark')"
                        x-on:click="window.TaskFlow.applyTheme('dark')"
                        aria-pressed="{{ $theme === 'dark' ? 'true' : 'false' }}"
                        @class([
                            'rounded-xl border p-4 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900',
                            'border-indigo-600 bg-indigo-50/60 ring-2 ring-indigo-600/20 dark:border-indigo-400 dark:bg-indigo-500/10' => $theme === 'dark',
                            'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700 dark:hover:bg-slate-800/60' => $theme !== 'dark',
                        ])
                    >
                        <span class="flex h-16 overflow-hidden rounded-lg border border-slate-700 bg-slate-800">
                            <span class="w-1/3 border-r border-slate-700 bg-slate-700"></span>
                            <span class="flex-1 bg-slate-900"></span>
                        </span>

                        <span class="mt-3 block text-sm font-semibold text-slate-900 dark:text-white">Dark</span>
                        <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">Always use dark mode</span>

                        <span @class([
                            'mt-3 inline-flex items-center gap-1.5 text-xs font-medium',
                            'text-indigo-600 dark:text-indigo-400' => $theme === 'dark',
                            'text-slate-500 dark:text-slate-400' => $theme !== 'dark',
                        ])>
                            <span @class([
                                'h-1.5 w-1.5 rounded-full',
                                'bg-indigo-600 dark:bg-indigo-400' => $theme === 'dark',
                                'bg-slate-300 dark:bg-slate-600' => $theme !== 'dark',
                            ])></span>
                            {{ $theme === 'dark' ? 'Selected' : 'Select theme' }}
                        </span>
                    </button>

                    <button
                        type="button"
                        wire:click="setTheme('system')"
                        x-on:click="window.TaskFlow.applyTheme('system')"
                        aria-pressed="{{ $theme === 'system' ? 'true' : 'false' }}"
                        @class([
                            'rounded-xl border p-4 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900',
                            'border-indigo-600 bg-indigo-50/60 ring-2 ring-indigo-600/20 dark:border-indigo-400 dark:bg-indigo-500/10' => $theme === 'system',
                            'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700 dark:hover:bg-slate-800/60' => $theme !== 'system',
                        ])
                    >
                        <span class="flex h-16 overflow-hidden rounded-lg border border-slate-200 bg-slate-100">
                            <span class="w-1/3 border-r border-slate-200 bg-slate-200"></span>
                            <span class="flex-1 bg-gradient-to-r from-white to-slate-900"></span>
                        </span>

                        <span class="mt-3 block text-sm font-semibold text-slate-900 dark:text-white">System</span>
                        <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">Match your device setting</span>

                        <span @class([
                            'mt-3 inline-flex items-center gap-1.5 text-xs font-medium',
                            'text-indigo-600 dark:text-indigo-400' => $theme === 'system',
                            'text-slate-500 dark:text-slate-400' => $theme !== 'system',
                        ])>
                            <span @class([
                                'h-1.5 w-1.5 rounded-full',
                                'bg-indigo-600 dark:bg-indigo-400' => $theme === 'system',
                                'bg-slate-300 dark:bg-slate-600' => $theme !== 'system',
                            ])></span>
                            {{ $theme === 'system' ? 'Selected' : 'Select theme' }}
                        </span>
                    </button>
                </div>

                @if ($themeStatus)
                    <p class="mt-4 text-sm font-medium text-emerald-600 dark:text-emerald-400" role="status">
                        {{ $themeStatus }}
                    </p>
                @endif
            </section>
        @elseif ($activeTab === 'profile')
            <section
                id="profile-panel"
                role="tabpanel"
                aria-labelledby="profile-tab"
                class="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900"
            >
                <div>
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Profile information</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Update the name and email address associated with your account.
                    </p>
                </div>

                <form wire:submit="updateProfile" class="mt-6 space-y-5">
                    <div>
                        <label for="settings-name" class="block text-sm font-medium text-slate-900 dark:text-slate-200">
                            Full name
                        </label>

                        <input
                            id="settings-name"
                            type="text"
                            wire:model.blur="name"
                            autocomplete="name"
                            @error('name') aria-describedby="settings-name-error" aria-invalid="true" @enderror
                            @class([
                                'mt-2 block w-full rounded-lg border bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:outline-none focus:ring-4 dark:bg-slate-950 dark:text-white dark:placeholder:text-slate-500',
                                'border-slate-300 focus:border-indigo-600 focus:ring-indigo-600/10 dark:border-slate-700 dark:focus:border-indigo-400 dark:focus:ring-indigo-400/10' => ! $errors->has('name'),
                                'border-red-300 focus:border-red-500 focus:ring-red-500/10 dark:border-red-500/60' => $errors->has('name'),
                            ])
                        />

                        @error('name')
                            <p id="settings-name-error" class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="settings-email" class="block text-sm font-medium text-slate-900 dark:text-slate-200">
                            Email address
                        </label>

                        <input
                            id="settings-email"
                            type="email"
                            wire:model.blur="email"
                            autocomplete="email"
                            inputmode="email"
                            @error('email') aria-describedby="settings-email-error" aria-invalid="true" @enderror
                            @class([
                                'mt-2 block w-full rounded-lg border bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:outline-none focus:ring-4 dark:bg-slate-950 dark:text-white dark:placeholder:text-slate-500',
                                'border-slate-300 focus:border-indigo-600 focus:ring-indigo-600/10 dark:border-slate-700 dark:focus:border-indigo-400 dark:focus:ring-indigo-400/10' => ! $errors->has('email'),
                                'border-red-300 focus:border-red-500 focus:ring-red-500/10 dark:border-red-500/60' => $errors->has('email'),
                            ])
                        />

                        @error('email')
                            <p id="settings-email-error" class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    @if ($profileStatus)
                        <p class="text-sm font-medium text-emerald-600 dark:text-emerald-400" role="status">
                            {{ $profileStatus }}
                        </p>
                    @endif

                    <div class="flex items-center gap-3">
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            wire:target="updateProfile"
                            class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 dark:focus-visible:ring-offset-slate-900"
                        >
                            Save changes
                        </button>

                        <span wire:loading wire:target="updateProfile" class="text-sm text-slate-500 dark:text-slate-400">
                            Saving...
                        </span>
                    </div>
                </form>
            </section>
        @else
            <section
                id="password-panel"
                role="tabpanel"
                aria-labelledby="password-tab"
                class="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900"
            >
                <div>
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Change password</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Use a strong password with at least 8 characters.
                    </p>
                </div>

                <form wire:submit="updatePassword" class="mt-6 space-y-5">
                    <div>
                        <label for="current-password" class="block text-sm font-medium text-slate-900 dark:text-slate-200">
                            Current password
                        </label>

                        <input
                            id="current-password"
                            type="password"
                            wire:model.blur="current_password"
                            autocomplete="current-password"
                            @error('current_password') aria-describedby="current-password-error" aria-invalid="true" @enderror
                            @class([
                                'mt-2 block w-full rounded-lg border bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:outline-none focus:ring-4 dark:bg-slate-950 dark:text-white dark:placeholder:text-slate-500',
                                'border-slate-300 focus:border-indigo-600 focus:ring-indigo-600/10 dark:border-slate-700 dark:focus:border-indigo-400 dark:focus:ring-indigo-400/10' => ! $errors->has('current_password'),
                                'border-red-300 focus:border-red-500 focus:ring-red-500/10 dark:border-red-500/60' => $errors->has('current_password'),
                            ])
                        />

                        @error('current_password')
                            <p id="current-password-error" class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="new-password" class="block text-sm font-medium text-slate-900 dark:text-slate-200">
                            New password
                        </label>

                        <input
                            id="new-password"
                            type="password"
                            wire:model.blur="password"
                            autocomplete="new-password"
                            @error('password') aria-describedby="new-password-error" aria-invalid="true" @enderror
                            @class([
                                'mt-2 block w-full rounded-lg border bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:outline-none focus:ring-4 dark:bg-slate-950 dark:text-white dark:placeholder:text-slate-500',
                                'border-slate-300 focus:border-indigo-600 focus:ring-indigo-600/10 dark:border-slate-700 dark:focus:border-indigo-400 dark:focus:ring-indigo-400/10' => ! $errors->has('password'),
                                'border-red-300 focus:border-red-500 focus:ring-red-500/10 dark:border-red-500/60' => $errors->has('password'),
                            ])
                        />

                        @error('password')
                            <p id="new-password-error" class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password-confirmation" class="block text-sm font-medium text-slate-900 dark:text-slate-200">
                            Confirm new password
                        </label>

                        <input
                            id="password-confirmation"
                            type="password"
                            wire:model.blur="password_confirmation"
                            autocomplete="new-password"
                            class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-indigo-600 focus:outline-none focus:ring-4 focus:ring-indigo-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:placeholder:text-slate-500 dark:focus:border-indigo-400 dark:focus:ring-indigo-400/10"
                        />
                    </div>

                    @if ($passwordStatus)
                        <p class="text-sm font-medium text-emerald-600 dark:text-emerald-400" role="status">
                            {{ $passwordStatus }}
                        </p>
                    @endif

                    <div class="flex items-center gap-3">
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            wire:target="updatePassword"
                            class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 dark:focus-visible:ring-offset-slate-900"
                        >
                            Update password
                        </button>

                        <span wire:loading wire:target="updatePassword" class="text-sm text-slate-500 dark:text-slate-400">
                            Updating...
                        </span>
                    </div>
                </form>
            </section>
        @endif
    </main>
