<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f8fafc">
    <title>@yield('title', 'TaskFlow · Plan, Organize, Get Things Done')</title>
    <meta name="description" content="Plan projects, organize tasks, and move work forward with TaskFlow.">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <script>
        (() => {
            const accountTheme = @json(auth()->user()?->theme);
            let savedGuestTheme = null;
            try {
                savedGuestTheme = localStorage.getItem('taskflow-guest-theme');
            } catch (_) {}

            const initialTheme = accountTheme ?? (['light', 'dark', 'system'].includes(savedGuestTheme) ? savedGuestTheme : 'light');
            const media = window.matchMedia('(prefers-color-scheme: dark)');
            const apply = (theme) => {
                document.documentElement.classList.toggle('dark', theme === 'dark' || (theme === 'system' && media.matches));
                document.documentElement.dataset.theme = theme;
            };

            window.TaskFlow = {
                theme: initialTheme,
                applyTheme(theme) {
                    if (!['light', 'dark', 'system'].includes(theme)) return;

                    this.theme = theme;
                    apply(theme);

                    if (accountTheme === null) {
                        try {
                            localStorage.setItem('taskflow-guest-theme', theme);
                        } catch (_) {}
                    }
                },
            };

            apply(initialTheme);
            media.addEventListener('change', () => {
                if (window.TaskFlow.theme === 'system') apply('system');
            });
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    <header class="sticky top-0 z-40 border-b border-slate-200/80 bg-white/90 backdrop-blur-xl dark:border-slate-800 dark:bg-slate-950/90">
        <nav x-data="{ open: false }" class="mx-auto flex max-w-7xl items-center justify-between px-5 py-3.5 sm:px-8 lg:px-10" aria-label="Main navigation">
            <div class="flex items-center gap-2 sm:gap-3">
                <a href="{{ route('home') }}" class="inline-flex rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600" aria-label="TaskFlow home">
                    <x-brand-logo variant="full" class="h-auto w-36 sm:w-40" />
                </a>
            </div>

            <div class="hidden items-center gap-8 md:flex">
                <a href="{{ route('home') }}" @class(['text-sm font-medium transition hover:text-indigo-600 dark:hover:text-indigo-300', 'text-indigo-600 dark:text-indigo-300' => request()->routeIs('home'), 'text-slate-600 dark:text-slate-300' => ! request()->routeIs('home')])>Home</a>
                <a href="{{ route('features') }}" @class(['text-sm font-medium transition hover:text-indigo-600 dark:hover:text-indigo-300', 'text-indigo-600 dark:text-indigo-300' => request()->routeIs('features'), 'text-slate-600 dark:text-slate-300' => ! request()->routeIs('features')])>Features</a>
                <a href="{{ route('about') }}" @class(['text-sm font-medium transition hover:text-indigo-600 dark:hover:text-indigo-300', 'text-indigo-600 dark:text-indigo-300' => request()->routeIs('about'), 'text-slate-600 dark:text-slate-300' => ! request()->routeIs('about')])>About</a>
            </div>

            <div class="flex items-center gap-2">
                <div class="hidden items-center gap-3 md:flex">
                    @auth
                    <a href="{{ route('dashboard') }}" class="rounded-lg px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="rounded-lg px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">Log in</a>
                    <a href="{{ route('register') }}" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-950">Get started free</a>
                    @endauth
                </div>


                <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-slate-700 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:text-slate-200 dark:hover:bg-slate-800 md:hidden" x-on:click="open = !open" x-bind:aria-expanded="open.toString()" aria-controls="mobile-marketing-menu" aria-label="Toggle navigation menu">
                <svg x-show="!open" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
                <svg x-cloak x-show="open" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
                </button>
                @guest
                    <div x-data="{ themeOpen: false, theme: window.TaskFlow.theme }" x-on:click.outside="themeOpen = false" class="relative">
                        <button type="button" x-on:click="themeOpen = !themeOpen" x-bind:aria-expanded="themeOpen.toString()" aria-haspopup="true" aria-label="Choose appearance" title="Choose appearance" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">
                            <svg x-cloak x-show="theme === 'light'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path stroke-linecap="round" d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/></svg>
                            <svg x-cloak x-show="theme === 'dark'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20.5 15.5A8.5 8.5 0 0 1 8.5 3.5a8.5 8.5 0 1 0 12 12Z"/></svg>
                            <svg x-cloak x-show="theme === 'system'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="4" width="18" height="13" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M8 21h8m-4-4v4m-5-9 3-3 2 2 3-4"/></svg>
                        </button>
                        <div x-cloak x-show="themeOpen" x-transition role="group" aria-label="Appearance preference" class="absolute right-0 top-full z-50 mt-2 w-40 overflow-hidden rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg dark:border-slate-700 dark:bg-slate-900">
                            <button type="button" x-on:click="theme = 'light'; window.TaskFlow.applyTheme('light'); themeOpen = false" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path stroke-linecap="round" d="M12 2v2m0 16v2M2 12h2m16 0h2"/></svg>Light</button>
                            <button type="button" x-on:click="theme = 'dark'; window.TaskFlow.applyTheme('dark'); themeOpen = false" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20.5 15.5A8.5 8.5 0 0 1 8.5 3.5a8.5 8.5 0 1 0 12 12Z"/></svg>Dark</button>
                            <button type="button" x-on:click="theme = 'system'; window.TaskFlow.applyTheme('system'); themeOpen = false" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="4" width="18" height="13" rx="2"/><path stroke-linecap="round" d="M8 21h8m-4-4v4"/></svg>System</button>
                        </div>
                    </div>
                @endguest
            </div>

            <div x-cloak x-show="open" x-transition id="mobile-marketing-menu" class="absolute inset-x-0 top-full border-b border-slate-200 bg-white p-4 shadow-lg dark:border-slate-800 dark:bg-slate-950 md:hidden">
                <div class="mx-auto flex max-w-7xl flex-col gap-1">
                    <a href="{{ route('home') }}" class="rounded-lg px-3 py-2.5 text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-900">Home</a>
                    <a href="{{ route('features') }}" class="rounded-lg px-3 py-2.5 text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-900">Features</a>
                    <a href="{{ route('about') }}" class="rounded-lg px-3 py-2.5 text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-900">About</a>
                    <div class="mt-2 grid grid-cols-2 gap-2 border-t border-slate-200 pt-3 dark:border-slate-800">
                        @auth
                            <a href="{{ route('dashboard') }}" class="rounded-lg border border-slate-200 px-3 py-2.5 text-center text-sm font-semibold dark:border-slate-700">Dashboard</a>
                        @else
                            <a href="{{ route('login') }}" class="rounded-lg border border-slate-200 px-3 py-2.5 text-center text-sm font-semibold dark:border-slate-700">Log in</a>
                            <a href="{{ route('register') }}" class="rounded-lg bg-indigo-600 px-3 py-2.5 text-center text-sm font-semibold text-white">Get started</a>
                        @endauth
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="border-t border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <div class="mx-auto flex max-w-7xl flex-col gap-4 px-5 py-7 sm:flex-row sm:items-center sm:justify-between sm:px-8 lg:px-10">
            <div class="flex items-center gap-3">
                <x-brand-logo variant="icon" class="h-8 w-8" />
                <span class="text-sm text-slate-500 dark:text-slate-400">Plan clearly. Make progress. © {{ date('Y') }} TaskFlow.</span>
            </div>
            <div class="flex gap-5 text-sm text-slate-500 dark:text-slate-400">
                <a href="{{ route('features') }}" class="hover:text-indigo-600 dark:hover:text-indigo-300">Features</a>
                <a href="{{ route('about') }}" class="hover:text-indigo-600 dark:hover:text-indigo-300">About</a>
                <a href="{{ route('login') }}" class="hover:text-indigo-600 dark:hover:text-indigo-300">Log in</a>
            </div>
        </div>
    </footer>
    @livewireScripts
</body>
</html>
