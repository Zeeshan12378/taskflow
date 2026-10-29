<!DOCTYPE html>
<html
    lang="en"
    data-theme="{{ auth()->user()?->theme ?? 'light' }}"
    @class(['dark' => auth()->user()?->theme === 'dark'])
>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">

    <title>{{ $title ?? 'TaskFlow' }}</title>

    <script>
        (function () {
            const accountTheme = @json(auth()->user()?->theme);
            let savedGuestTheme = null;
            try {
                savedGuestTheme = localStorage.getItem('taskflow-guest-theme');
            } catch (_) {}

            const initialTheme = accountTheme ?? (['light', 'dark', 'system'].includes(savedGuestTheme) ? savedGuestTheme : 'light');
            const media = window.matchMedia('(prefers-color-scheme: dark)');

            const apply = (theme) => {
                const isDark = theme === 'dark' || (theme === 'system' && media.matches);

                document.documentElement.classList.toggle('dark', isDark);
                document.documentElement.dataset.theme = theme;
            };

            window.TaskFlow = {
                theme: initialTheme,
                applyTheme(theme) {
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
                if (window.TaskFlow.theme === 'system') {
                    apply('system');
                }
            });
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    @auth
        <div
            x-data="{ sidebarOpen: false, sidebarCollapsed: false }"
            x-on:keydown.escape.window="sidebarOpen = false"
            class="min-h-screen md:flex"
        >
            <x-app-sidebar />

            <div class="flex min-h-screen min-w-0 flex-1 flex-col">
                <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-slate-200 bg-white/95 px-4 backdrop-blur sm:px-6 lg:px-8 dark:border-slate-800 dark:bg-slate-900/95 md:h-20">
                    <div class="flex min-w-0 items-center gap-3">
                        <button
                            type="button"
                            x-on:click="sidebarCollapsed = ! sidebarCollapsed"
                            x-bind:aria-expanded="(! sidebarCollapsed).toString()"
                            x-bind:aria-label="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                            aria-controls="app-sidebar"
                            class="hidden h-10 w-10 items-center justify-center rounded-lg text-slate-600 transition hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 md:inline-flex dark:text-slate-300 dark:hover:bg-slate-800"
                            title="Collapse or expand sidebar"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
                        </button>
                        <button
                            type="button"
                            x-on:click="sidebarOpen = ! sidebarOpen"
                            x-bind:aria-expanded="sidebarOpen.toString()"
                            x-bind:aria-label="sidebarOpen ? 'Close navigation menu' : 'Open navigation menu'"
                            aria-controls="app-sidebar"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-slate-600 transition hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 md:hidden dark:text-slate-300 dark:hover:bg-slate-800"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
                        </button>
                        <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center md:hidden">
                            <x-brand-logo variant="full" class="h-auto w-36" />
                        </a>
                        <div class="hidden md:block">
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">Workspace</p>
                            <h1 class="truncate text-lg font-semibold text-slate-900 dark:text-white">{{ $title ?? 'TaskFlow' }}</h1>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('notifications.index') }}" wire:navigate aria-label="Notifications{{ auth()->user()->unreadNotifications()->exists() ? ', unread items' : '' }}" @class(['relative inline-flex h-10 w-10 items-center justify-center rounded-lg text-slate-600 transition hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:text-slate-300 dark:hover:bg-slate-800', 'text-indigo-700 dark:text-indigo-300' => request()->routeIs('notifications.*')])>
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" /></svg>
                            @if (auth()->user()->unreadNotifications()->exists())
                                <span class="absolute right-1.5 top-1.5 h-2.5 w-2.5 rounded-full bg-red-500 ring-2 ring-white dark:ring-slate-900"><span class="sr-only">Unread notifications</span></span>
                            @endif
                        </a>
                        <livewire:account.menu />
                    </div>
                </header>

                <div class="flex flex-1 flex-col pb-[calc(5rem+env(safe-area-inset-bottom))] md:pb-0">
                    {{ $slot }}

                    <footer class="mt-auto hidden border-t border-slate-200 bg-white px-6 py-5 text-center text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400 md:block">
                        &copy; {{ date('Y') }} TaskFlow. All rights reserved.
                    </footer>
                </div>
            </div>

            <x-app-mobile-nav />
        </div>
    @else
        {{ $slot }}
    @endauth

    @livewireScripts
</body>
</html>
