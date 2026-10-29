<footer class="hidden border-t border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900 sm:block">
    <div class="mx-auto flex max-w-6xl flex-col gap-3 px-4 py-6 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
        <p class="text-sm text-slate-500 dark:text-slate-400">
            &copy; {{ date('Y') }} TaskFlow. All rights reserved.
        </p>

        <nav class="flex items-center gap-4 text-sm" aria-label="Footer navigation">
            @auth
                <a
                    href="{{ route('dashboard') }}"
                    wire:navigate
                    class="rounded text-slate-500 transition hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:text-slate-400 dark:hover:text-white dark:focus-visible:ring-offset-slate-900"
                >
                    Dashboard
                </a>

                <a
                    href="{{ route('profile') }}"
                    wire:navigate
                    class="rounded text-slate-500 transition hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:text-slate-400 dark:hover:text-white dark:focus-visible:ring-offset-slate-900"
                >
                    Profile
                </a>

                <a
                    href="{{ route('settings') }}"
                    wire:navigate
                    class="rounded text-slate-500 transition hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:text-slate-400 dark:hover:text-white dark:focus-visible:ring-offset-slate-900"
                >
                    Settings
                </a>
            @else
                <a
                    href="{{ route('login') }}"
                    wire:navigate
                    class="rounded text-slate-500 transition hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:text-slate-400 dark:hover:text-white dark:focus-visible:ring-offset-slate-900"
                >
                    Sign in
                </a>

                <a
                    href="{{ route('register') }}"
                    wire:navigate
                    class="rounded text-slate-500 transition hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:text-slate-400 dark:hover:text-white dark:focus-visible:ring-offset-slate-900"
                >
                    Create account
                </a>
            @endauth
        </nav>
    </div>
</footer>
