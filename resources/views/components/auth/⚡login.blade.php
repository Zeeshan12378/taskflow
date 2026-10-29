<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Sign in · TaskFlow')] class extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::transliterate(Str::lower($this->email).'|'.request()->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $this->addError('email', 'Too many login attempts. Please try again in '.RateLimiter::availableIn($throttleKey).' seconds.');

            return;
        }

        if (! Auth::attempt([
            'email' => $this->email,
            'password' => $this->password,
        ], $this->remember)) {
            RateLimiter::hit($throttleKey);

            $this->addError('email', 'These credentials do not match our records.');

            return;
        }

        RateLimiter::clear($throttleKey);

        session()->regenerate();

        $this->redirectRoute('dashboard');
    }
};
?>

<div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-slate-50 px-4 py-10 sm:px-6 lg:px-8 dark:bg-slate-950">
    <div aria-hidden="true" class="pointer-events-none absolute inset-0 overflow-hidden">
        <div class="absolute -right-32 -top-40 h-96 w-96 rounded-full bg-indigo-100/70 blur-3xl dark:bg-indigo-500/10"></div>
        <div class="absolute -bottom-40 -left-32 h-96 w-96 rounded-full bg-blue-100/70 blur-3xl dark:bg-blue-500/10"></div>
    </div>

    <div class="relative w-full max-w-md">
        <div class="flex flex-col items-center text-center">
            <a href="/" aria-label="TaskFlow home" class="inline-flex rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-4 dark:focus-visible:ring-offset-slate-950">
                <x-brand-logo variant="full" class="h-auto w-56" />
            </a>

            <h1 class="mt-7 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Welcome back</h1>

            <p class="mt-2 max-w-sm text-sm leading-6 text-slate-500 dark:text-slate-400">
                Sign in to continue to your TaskFlow workspace.
            </p>
        </div>

        <div class="mt-8 rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 dark:border-slate-800 dark:bg-slate-900">
            <form wire:submit="login" class="space-y-5">
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-900 dark:text-slate-200">
                        Email address
                    </label>

                    <input
                        id="email"
                        type="email"
                        wire:model.blur="email"
                        autocomplete="email"
                        inputmode="email"
                        autofocus
                        required
                        placeholder="you@example.com"
                        @error('email') aria-describedby="email-error" aria-invalid="true" @enderror
                        @class([
                            'mt-1.5 block w-full rounded-lg border bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:ring-4 dark:bg-slate-950 dark:text-white dark:placeholder:text-slate-500',
                            'border-slate-300 focus:border-indigo-600 focus:ring-indigo-600/10 dark:border-slate-700 dark:focus:border-indigo-400 dark:focus:ring-indigo-400/10' => ! $errors->has('email'),
                            'border-red-300 focus:border-red-500 focus:ring-red-500/10 dark:border-red-500/60' => $errors->has('email'),
                        ])
                    >

                    @error('email')
                        <p id="email-error" class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div x-data="{ showPassword: false }">
                    <label for="password" class="block text-sm font-medium text-slate-900 dark:text-slate-200">
                        Password
                    </label>

                    <div class="relative mt-1.5">
                        <input
                            id="password"
                            :type="showPassword ? 'text' : 'password'"
                            wire:model="password"
                            autocomplete="current-password"
                            required
                            placeholder="Enter your password"
                            @error('password') aria-describedby="password-error" aria-invalid="true" @enderror
                            @class([
                                'block w-full rounded-lg border bg-white py-2.5 pl-3.5 pr-16 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:ring-4 dark:bg-slate-950 dark:text-white dark:placeholder:text-slate-500',
                                'border-slate-300 focus:border-indigo-600 focus:ring-indigo-600/10 dark:border-slate-700 dark:focus:border-indigo-400 dark:focus:ring-indigo-400/10' => ! $errors->has('password'),
                                'border-red-300 focus:border-red-500 focus:ring-red-500/10 dark:border-red-500/60' => $errors->has('password'),
                            ])
                        >

                        <button
                            type="button"
                            x-on:click="showPassword = ! showPassword"
                            x-bind:aria-label="showPassword ? 'Hide password' : 'Show password'"
                            x-bind:aria-pressed="showPassword"
                            class="absolute inset-y-0 right-0 flex items-center rounded-r-lg px-3.5 text-sm font-medium text-slate-500 transition hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:text-slate-400 dark:hover:text-slate-200 dark:focus-visible:ring-offset-slate-900"
                        >
                            <span x-text="showPassword ? 'Hide' : 'Show'">Show</span>
                        </button>
                    </div>

                    @error('password')
                        <p id="password-error" class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <label class="flex items-center gap-2.5 text-sm text-slate-600 dark:text-slate-300">
                    <input
                        type="checkbox"
                        wire:model="remember"
                        class="h-4 w-4 rounded border-slate-300 accent-indigo-600 focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 dark:border-slate-600 dark:bg-slate-950 dark:focus:ring-offset-slate-900"
                    >

                    <span>Remember me on this device</span>
                </label>

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="login"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 dark:focus-visible:ring-offset-slate-900"
                >
                    <span wire:loading wire:target="login" class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>

                    <span wire:loading.remove wire:target="login">Sign in</span>
                    <span wire:loading wire:target="login">Signing in...</span>
                </button>
            </form>
        </div>

        <p class="mt-6 text-center text-sm text-slate-600 dark:text-slate-400">
            New to TaskFlow?
            <a href="{{ route('register') }}" class="font-semibold text-indigo-600 transition hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300 focus:outline-none focus-visible:underline">
                Create an account
            </a>
        </p>

        <p class="mt-3 text-center text-xs leading-5 text-slate-500 dark:text-slate-400">
            Sign-in attempts are rate limited to keep your account safe.
        </p>
    </div>
</div>
