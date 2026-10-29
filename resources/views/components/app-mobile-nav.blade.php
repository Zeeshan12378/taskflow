@auth
    <nav
        id="mobile-tab-bar"
        class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 pb-[env(safe-area-inset-bottom)] shadow-[0_-8px_24px_-16px_rgba(15,23,42,0.45)] backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/95 sm:hidden"
        aria-label="Mobile navigation"
    >
        <div class="mx-auto grid max-w-lg grid-cols-5 gap-1 px-2 pt-1.5">
            <a
                href="{{ route('dashboard') }}"
                wire:navigate
                @if (request()->routeIs('dashboard')) aria-current="page" @endif
                class="flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl px-2 py-1.5 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:focus-visible:ring-offset-slate-900"
            >
                <span @class([
                    'flex h-7 w-12 items-center justify-center rounded-full transition',
                    'bg-indigo-600 text-white shadow-sm shadow-indigo-600/20 dark:bg-indigo-500' => request()->routeIs('dashboard'),
                    'text-slate-500 dark:text-slate-400' => ! request()->routeIs('dashboard'),
                ])>
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5 12 3l9 7.5" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 9.75V21h14V9.75" />
                    </svg>
                </span>

                <span @class([
                    'text-[11px] font-medium leading-none',
                    'text-indigo-600 dark:text-indigo-400' => request()->routeIs('dashboard'),
                    'text-slate-500 dark:text-slate-400' => ! request()->routeIs('dashboard'),
                ])>
                    Dashboard
                </span>
            </a>

            <a href="{{ route('tasks.mine') }}" wire:navigate @if (request()->routeIs('tasks.mine')) aria-current="page" @endif class="flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl px-1 py-1.5 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:focus-visible:ring-offset-slate-900">
                <span @class(['flex h-7 w-12 items-center justify-center rounded-full transition', 'bg-indigo-600 text-white shadow-sm shadow-indigo-600/20 dark:bg-indigo-500' => request()->routeIs('tasks.mine'), 'text-slate-500 dark:text-slate-400' => ! request()->routeIs('tasks.mine')])>
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6M4 4h16v16H4z" /></svg>
                </span>
                <span @class(['text-[11px] font-medium leading-none', 'text-indigo-600 dark:text-indigo-400' => request()->routeIs('tasks.mine'), 'text-slate-500 dark:text-slate-400' => ! request()->routeIs('tasks.mine')])>My Tasks</span>
            </a>

            <a
                href="{{ route('teams.index') }}"
                wire:navigate
                @if (request()->routeIs('teams.*')) aria-current="page" @endif
                class="flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl px-1 py-1.5 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:focus-visible:ring-offset-slate-900"
            >
                <span @class([
                    'flex h-7 w-12 items-center justify-center rounded-full transition',
                    'bg-indigo-600 text-white shadow-sm shadow-indigo-600/20 dark:bg-indigo-500' => request()->routeIs('teams.*'),
                    'text-slate-500 dark:text-slate-400' => ! request()->routeIs('teams.*'),
                ])>
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2m16 0v-2a4 4 0 0 0-3-3.87M14 3.13a4 4 0 0 1 0 7.75M14 7a4 4 0 1 1 8 0" /></svg>
                </span>
                <span @class([
                    'text-[11px] font-medium leading-none',
                    'text-indigo-600 dark:text-indigo-400' => request()->routeIs('teams.*'),
                    'text-slate-500 dark:text-slate-400' => ! request()->routeIs('teams.*'),
                ])>Teams</span>
            </a>

            <a
                href="{{ route('profile') }}"
                wire:navigate
                @if (request()->routeIs('profile')) aria-current="page" @endif
                class="flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl px-2 py-1.5 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:focus-visible:ring-offset-slate-900"
            >
                <span @class([
                    'flex h-7 w-12 items-center justify-center rounded-full transition',
                    'bg-indigo-600 text-white shadow-sm shadow-indigo-600/20 dark:bg-indigo-500' => request()->routeIs('profile'),
                    'text-slate-500 dark:text-slate-400' => ! request()->routeIs('profile'),
                ])>
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 20.25a7.5 7.5 0 0 1 15 0" />
                    </svg>
                </span>

                <span @class([
                    'text-[11px] font-medium leading-none',
                    'text-indigo-600 dark:text-indigo-400' => request()->routeIs('profile'),
                    'text-slate-500 dark:text-slate-400' => ! request()->routeIs('profile'),
                ])>
                    Profile
                </span>
            </a>

            <a
                href="{{ route('settings') }}"
                wire:navigate
                @if (request()->routeIs('settings')) aria-current="page" @endif
                class="flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl px-2 py-1.5 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:focus-visible:ring-offset-slate-900"
            >
                <span @class([
                    'flex h-7 w-12 items-center justify-center rounded-full transition',
                    'bg-indigo-600 text-white shadow-sm shadow-indigo-600/20 dark:bg-indigo-500' => request()->routeIs('settings'),
                    'text-slate-500 dark:text-slate-400' => ! request()->routeIs('settings'),
                ])>
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h9M17 6h3M4 12h3M11 12h9M4 18h9M17 18h3" />
                        <circle cx="15" cy="6" r="2" />
                        <circle cx="9" cy="12" r="2" />
                        <circle cx="15" cy="18" r="2" />
                    </svg>
                </span>

                <span @class([
                    'text-[11px] font-medium leading-none',
                    'text-indigo-600 dark:text-indigo-400' => request()->routeIs('settings'),
                    'text-slate-500 dark:text-slate-400' => ! request()->routeIs('settings'),
                ])>
                    Settings
                </span>
            </a>
        </div>
    </nav>
@endauth
