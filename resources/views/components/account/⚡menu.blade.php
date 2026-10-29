<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    #[On('profile-updated')]
    public function refreshProfile(): void {}

    public function logout(): void
    {
        Auth::logout();

        session()->invalidate();
        session()->regenerateToken();

        $this->redirectRoute('login');
    }
};
?>

<div
    x-data="{ open: false }"
    x-on:keydown.escape.window="open = false"
    x-on:click.outside="open = false"
    class="relative"
>
    <button
        type="button"
        x-on:click="open = ! open"
        x-bind:aria-expanded="open"
        aria-haspopup="menu"
        aria-controls="account-menu"
        aria-label="Open account menu"
        class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-50 text-sm font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-100 transition hover:bg-indigo-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:bg-indigo-500/15 dark:text-indigo-300 dark:ring-indigo-500/20 dark:hover:bg-indigo-500/25 dark:focus-visible:ring-offset-slate-900 sm:hidden"
    >
        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
    </button>

    <div class="hidden items-center gap-3 sm:flex">
        <span class="text-right">
            <span class="block max-w-44 truncate text-sm font-medium text-slate-900 dark:text-white">{{ auth()->user()->name }}</span>
            <span class="block max-w-48 truncate text-xs text-slate-500 dark:text-slate-400">{{ auth()->user()->email }}</span>
        </span>

        <button
            type="button"
            x-on:click="open = ! open"
            x-bind:aria-expanded="open"
            aria-haspopup="menu"
            aria-controls="account-menu"
            aria-label="Open account menu"
            class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-50 text-sm font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-100 transition hover:bg-indigo-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:bg-indigo-500/15 dark:text-indigo-300 dark:ring-indigo-500/20 dark:hover:bg-indigo-500/25 dark:focus-visible:ring-offset-slate-900"
        >
            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
        </button>
    </div>

    <div
        x-cloak
        x-show="open"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="scale-95 opacity-0"
        x-transition:enter-end="scale-100 opacity-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="scale-100 opacity-100"
        x-transition:leave-end="scale-95 opacity-0"
        id="account-menu"
        role="menu"
        class="absolute right-0 z-50 mt-3 w-56 origin-top-right overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-900"
    >
        <div class="p-1.5">
            <a
                href="{{ route('profile') }}"
                wire:navigate
                role="menuitem"
                x-on:click="open = false"
                class="flex items-center rounded-lg px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white"
            >
                Profile
            </a>

            <a
                href="{{ route('settings') }}"
                wire:navigate
                role="menuitem"
                x-on:click="open = false"
                class="flex items-center rounded-lg px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white"
            >
                Settings
            </a>
        </div>

        <div class="border-t border-slate-100 p-1.5 dark:border-slate-800">
            <button
                type="button"
                wire:click="logout"
                wire:loading.attr="disabled"
                wire:target="logout"
                role="menuitem"
                class="flex w-full items-center rounded-lg px-3 py-2 text-left text-sm font-medium text-red-600 transition hover:bg-red-50 hover:text-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 disabled:cursor-not-allowed disabled:opacity-60 dark:text-red-400 dark:hover:bg-red-500/10 dark:hover:text-red-300"
            >
                <span wire:loading.remove wire:target="logout">Sign out</span>
                <span wire:loading wire:target="logout">Signing out...</span>
            </button>
        </div>
    </div>
</div>
