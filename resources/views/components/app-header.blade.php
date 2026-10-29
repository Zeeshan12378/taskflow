<header class="sticky top-0 z-30 border-b border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
        <a
            href="{{ auth()->check() ? route('dashboard') : url('/') }}"
            wire:navigate
            class="inline-flex items-center gap-3 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-4"
        >
            <x-brand-logo variant="full" class="h-auto w-44" />
        </a>

        <div class="flex items-center gap-4">
            @auth
                <nav class="hidden items-center gap-1 sm:flex" aria-label="Primary navigation">
                    <a
                        href="{{ route('dashboard') }}"
                        wire:navigate
                        @class([
                            'rounded-lg px-3 py-2 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900',
                            'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300' => request()->routeIs('dashboard'),
                            'text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white' => ! request()->routeIs('dashboard'),
                        ])
                    >
                        Dashboard
                    </a>
                </nav>

                <livewire:account.menu />
            @else
                <nav class="flex items-center gap-2" aria-label="Authentication navigation">
                    <a
                        href="{{ route('login') }}"
                        wire:navigate
                        class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white dark:focus-visible:ring-offset-slate-900"
                    >
                        Sign in
                    </a>

                    <a
                        href="{{ route('register') }}"
                        wire:navigate
                        class="rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900"
                    >
                        Create account
                    </a>
                </nav>
            @endauth
        </div>
    </div>
</header>

<x-app-mobile-nav />
