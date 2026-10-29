<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Create your account · TaskFlow')] class extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        Auth::login($user);

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

            <h1 class="mt-7 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Create your account</h1>

            <p class="mt-2 max-w-sm text-sm leading-6 text-slate-500 dark:text-slate-400">
                Organize your projects and tasks in one calm, focused workspace.
            </p>
        </div>

        <div class="mt-8 rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 dark:border-slate-800 dark:bg-slate-900">
            <form wire:submit="register" class="space-y-5">
                <div>
                    <label for="name" class="block text-sm font-medium text-slate-900 dark:text-slate-200">
                        Full name
                    </label>

                    <input
                        id="name"
                        type="text"
                        wire:model.blur="name"
                        autocomplete="name"
                        autofocus
                        required
                        placeholder="Ada Lovelace"
                        @error('name') aria-describedby="name-error" aria-invalid="true" @enderror
                        @class([
                            'mt-1.5 block w-full rounded-lg border bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:ring-4 dark:bg-slate-950 dark:text-white dark:placeholder:text-slate-500',
                            'border-slate-300 focus:border-indigo-600 focus:ring-indigo-600/10 dark:border-slate-700 dark:focus:border-indigo-400 dark:focus:ring-indigo-400/10' => ! $errors->has('name'),
                            'border-red-300 focus:border-red-500 focus:ring-red-500/10 dark:border-red-500/60' => $errors->has('name'),
                        ])
                    >

                    @error('name')
                        <p id="name-error" class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

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
                            autocomplete="new-password"
                            required
                            placeholder="At least 8 characters"
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
                    @else
                        <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">Use at least 8 characters.</p>
                    @enderror
                </div>

                <div x-data="{ showPasswordConfirmation: false }">
                    <label for="password_confirmation" class="block text-sm font-medium text-slate-900 dark:text-slate-200">
                        Confirm password
                    </label>

                    <div class="relative mt-1.5">
                        <input
                            id="password_confirmation"
                            :type="showPasswordConfirmation ? 'text' : 'password'"
                            wire:model="password_confirmation"
                            autocomplete="new-password"
                            required
                            placeholder="Repeat your password"
                            class="block w-full rounded-lg border border-slate-300 bg-white py-2.5 pl-3.5 pr-16 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:placeholder:text-slate-500 dark:focus:border-indigo-400 dark:focus:ring-indigo-400/10"
                        >

                        <button
                            type="button"
                            x-on:click="showPasswordConfirmation = ! showPasswordConfirmation"
                            x-bind:aria-label="showPasswordConfirmation ? 'Hide password confirmation' : 'Show password confirmation'"
                            x-bind:aria-pressed="showPasswordConfirmation"
                            class="absolute inset-y-0 right-0 flex items-center rounded-r-lg px-3.5 text-sm font-medium text-slate-500 transition hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:text-slate-400 dark:hover:text-slate-200 dark:focus-visible:ring-offset-slate-900"
                        >
                            <span x-text="showPasswordConfirmation ? 'Hide' : 'Show'">Show</span>
                        </button>
                    </div>

                    @error('password_confirmation')
                        <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="register"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 dark:focus-visible:ring-offset-slate-900"
                >
                    <span wire:loading wire:target="register" class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>

                    <span wire:loading.remove wire:target="register">Create account</span>
                    <span wire:loading wire:target="register">Creating account...</span>
                </button>
            </form>
        </div>

        <p class="mt-6 text-center text-sm text-slate-600 dark:text-slate-400">
            Already have an account?
            <a href="{{ route('login') }}" class="font-semibold text-indigo-600 transition hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300 focus:outline-none focus-visible:underline">
                Sign in
            </a>
        </p>

        <p class="mt-3 text-center text-xs leading-5 text-slate-500 dark:text-slate-400">
            Your password is hashed before it is stored. Every account gets its own private workspace.
        </p>
    </div>
</div>
