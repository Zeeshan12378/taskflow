<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Profile · TaskFlow')] class extends Component
{
    //
};
?>

<main class="mx-auto w-full max-w-3xl flex-1 px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <a href="{{ route('dashboard') }}" wire:navigate class="inline-flex items-center rounded-lg text-sm font-medium text-indigo-600 dark:text-indigo-400 transition hover:text-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:text-indigo-400 dark:hover:text-indigo-300 dark:focus-visible:ring-offset-slate-950">
            Back to dashboard
        </a>

        <div class="mt-4">
            <p class="text-sm font-medium text-indigo-600 dark:text-indigo-400">Account</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Your profile</h1>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                These are the details associated with your TaskFlow account.
            </p>
        </div>

        <div class="mt-8 rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center gap-4 border-b border-slate-100 p-6 dark:border-slate-800">
                <span class="flex h-14 w-14 items-center justify-center rounded-full bg-indigo-50 text-xl font-semibold text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300">
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                </span>

                <div class="min-w-0">
                    <p class="truncate text-lg font-semibold text-slate-900 dark:text-white">{{ auth()->user()->name }}</p>
                    <p class="truncate text-sm text-slate-500 dark:text-slate-400">{{ auth()->user()->email }}</p>
                </div>
            </div>

            <dl class="divide-y divide-slate-100 dark:divide-slate-800">
                <div class="flex flex-col gap-1 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Full name</dt>
                    <dd class="text-sm text-slate-900 dark:text-white">{{ auth()->user()->name }}</dd>
                </div>

                <div class="flex flex-col gap-1 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Email address</dt>
                    <dd class="break-all text-sm text-slate-900 dark:text-white">{{ auth()->user()->email }}</dd>
                </div>

                <div class="flex flex-col gap-1 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Member since</dt>
                    <dd class="text-sm text-slate-900 dark:text-white">{{ auth()->user()->created_at->format('F j, Y') }}</dd>
                </div>
            </dl>
        </div>

        <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">
            <a href="{{ route('settings', ['tab' => 'profile']) }}" wire:navigate class="font-medium text-indigo-600 transition hover:text-indigo-700 focus:outline-none focus-visible:underline dark:text-indigo-400 dark:hover:text-indigo-300">
                Edit your profile in Settings
            </a>
        </p>
    </main>
