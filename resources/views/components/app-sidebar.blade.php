<div>
    <div
        x-cloak
        x-show="sidebarOpen"
        x-transition.opacity
        x-on:click="sidebarOpen = false"
        class="fixed inset-0 z-40 bg-slate-950/50 md:hidden"
        aria-hidden="true"
    ></div>

    <aside
        id="app-sidebar"
        aria-label="Main sidebar"
        :class="{
            'md:w-20': sidebarCollapsed,
            'md:w-64': ! sidebarCollapsed,
            'translate-x-0': sidebarOpen,
            '-translate-x-full': ! sidebarOpen,
        }"
        class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col border-slate-200 bg-white text-slate-700 shadow-sm transition-all dark:border-white/10 dark:bg-[#172033] dark:text-slate-200 dark:shadow-none duration-200 ease-in-out md:sticky md:top-0 md:z-20 md:h-screen md:translate-x-0 md:border-r"
    >
        <div class="flex h-20 shrink-0 items-center overflow-hidden border-b border-slate-200 px-5 dark:border-white/10" :class="sidebarCollapsed ? 'justify-center !px-2' : ''">
            <a
                href="{{ route('dashboard') }}"
                wire:navigate
                x-on:click="sidebarOpen = false"
                class="flex min-w-0 flex-1 items-center gap-3 overflow-hidden rounded-lg px-2 py-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-400"
                :class="sidebarCollapsed ? 'flex-none !px-0' : ''"
                aria-label="TaskFlow dashboard"
                title="TaskFlow"
            >
                <span x-show="! sidebarCollapsed" x-transition.opacity class="block min-w-0 flex-1">
                    <x-brand-logo variant="full" class="h-auto w-full max-w-[216px]" />
                </span>
                <span x-show="sidebarCollapsed" x-transition.opacity class="block h-10 w-10 shrink-0">
                    <x-brand-logo variant="icon" class="h-10 w-10" />
                </span>
            </a>
        </div>

        <button
            type="button"
            x-on:click="sidebarOpen = false"
            aria-label="Close navigation menu"
            class="absolute right-3 top-5 inline-flex h-10 w-10 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white focus-visible:ring-2 focus-visible:ring-indigo-400 md:hidden"
        >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
        </button>

        @php
            $sidebarProjects = \App\Models\Project::query()->accessibleTo(auth()->user())->orderBy('name')->get(['id', 'name']);
            $routeProject = request()->route('project');
            $currentProjectId = $routeProject instanceof \App\Models\Project ? $routeProject->id : (is_numeric($routeProject) ? (int) $routeProject : null);
            $projectsExpanded = request()->routeIs('projects.*');
        @endphp

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-6" aria-label="Main navigation">
            <a
                href="{{ route('dashboard') }}"
                wire:navigate
                x-on:click="sidebarOpen = false"
                @if (request()->routeIs('dashboard')) aria-current="page" @endif
                @class([
                    'flex items-center gap-3 overflow-hidden rounded-lg px-3 py-2.5 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:focus-visible:ring-indigo-400',
                    'bg-indigo-600 text-white shadow-sm' => request()->routeIs('dashboard'),
                    'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white' => ! request()->routeIs('dashboard'),
                ])
                :class="sidebarCollapsed ? 'justify-center !px-0' : ''"
                title="Dashboard"
            >
                <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m3 10 9-7 9 7M5 9v12h14V9M9 21v-7h6v7" /></svg>
                <span x-show="! sidebarCollapsed" x-transition.opacity class="whitespace-nowrap">Dashboard</span>
            </a>

            <div x-data="{ open: {{ $projectsExpanded ? 'true' : 'false' }} }">
                <div class="flex items-center gap-1">
                    <a href="{{ route('projects.index') }}" wire:navigate x-on:click="sidebarOpen = false" @if (request()->routeIs('projects.index')) aria-current="page" @endif @class([
                        'flex min-w-0 flex-1 items-center gap-3 overflow-hidden rounded-lg px-3 py-2.5 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:focus-visible:ring-indigo-400',
                        'bg-indigo-600 text-white shadow-sm' => request()->routeIs('projects.index'),
                        'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white' => ! request()->routeIs('projects.index'),
                    ]) :class="sidebarCollapsed ? 'justify-center !px-0' : ''" title="Projects">
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h6l2 2h8.5v9.5a2 2 0 0 1-2 2h-14a2 2 0 0 1-2-2v-9.5a2 2 0 0 1 2-2Z" /></svg>
                        <span x-show="! sidebarCollapsed" x-transition.opacity class="whitespace-nowrap">Projects</span>
                    </a>
                    <button type="button" x-show="! sidebarCollapsed" x-on:click="open = ! open" :aria-expanded="open" aria-label="Toggle project list" class="inline-flex h-9 w-8 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-white/10">
                        <svg class="h-4 w-4 transition-transform" :class="open ? 'rotate-90' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.25 4.5a.75.75 0 0 1 1.06 0l5 5a.75.75 0 0 1 0 1.06l-5 5a.75.75 0 1 1-1.06-1.06L11.72 10 7.25 5.56a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>
                    </button>
                </div>
                <div x-show="open && ! sidebarCollapsed" x-transition.opacity class="ml-5 mt-1 space-y-1 border-l border-slate-200 pl-3 dark:border-slate-700">
                    @forelse ($sidebarProjects as $sidebarProject)
                        <a href="{{ route('projects.board', $sidebarProject) }}" wire:navigate x-on:click="sidebarOpen = false" @if ($currentProjectId === $sidebarProject->id) aria-current="page" @endif @class([
                            'block truncate rounded-md px-3 py-2 text-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:focus-visible:ring-indigo-400',
                            'bg-indigo-50 font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300' => $currentProjectId === $sidebarProject->id,
                            'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/10 dark:hover:text-white' => $currentProjectId !== $sidebarProject->id,
                        ]) title="{{ $sidebarProject->name }}">{{ $sidebarProject->name }}</a>
                    @empty
                        <p class="px-3 py-2 text-xs text-slate-500 dark:text-slate-400">No projects yet</p>
                    @endforelse
                </div>
            </div>

            <a
                href="{{ route('teams.index') }}"
                wire:navigate
                x-on:click="sidebarOpen = false"
                @if (request()->routeIs('teams.*')) aria-current="page" @endif
                @class([
                    'flex items-center gap-3 overflow-hidden rounded-lg px-3 py-2.5 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:focus-visible:ring-indigo-400',
                    'bg-indigo-600 text-white shadow-sm' => request()->routeIs('teams.*'),
                    'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white' => ! request()->routeIs('teams.*'),
                ])
                :class="sidebarCollapsed ? 'justify-center !px-0' : ''"
                title="Teams"
            >
                <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2m16 0v-2a4 4 0 0 0-3-3.87M14 3.13a4 4 0 0 1 0 7.75M14 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" /></svg>
                <span x-show="! sidebarCollapsed" x-transition.opacity class="whitespace-nowrap">Teams</span>
            </a>

            <a href="{{ route('tasks.mine') }}" wire:navigate x-on:click="sidebarOpen = false" @if (request()->routeIs('tasks.mine')) aria-current="page" @endif @class(['flex items-center gap-3 overflow-hidden rounded-lg px-3 py-2.5 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:focus-visible:ring-indigo-400', 'bg-indigo-600 text-white shadow-sm' => request()->routeIs('tasks.mine'), 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white' => ! request()->routeIs('tasks.mine')]) :class="sidebarCollapsed ? 'justify-center !px-0' : ''" title="My Tasks">
                <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6M4 4h16v16H4z" /></svg>
                <span x-show="! sidebarCollapsed" x-transition.opacity class="whitespace-nowrap">My Tasks</span>
            </a>

            <a
                href="{{ route('profile') }}"
                wire:navigate
                x-on:click="sidebarOpen = false"
                @if (request()->routeIs('profile')) aria-current="page" @endif
                @class([
                    'flex items-center gap-3 overflow-hidden rounded-lg px-3 py-2.5 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:focus-visible:ring-indigo-400',
                    'bg-indigo-600 text-white shadow-sm' => request()->routeIs('profile'),
                    'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white' => ! request()->routeIs('profile'),
                ])
                :class="sidebarCollapsed ? 'justify-center !px-0' : ''"
                title="Profile"
            >
                <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="8" r="4" /><path stroke-linecap="round" stroke-linejoin="round" d="M4 21a8 8 0 0 1 16 0" /></svg>
                <span x-show="! sidebarCollapsed" x-transition.opacity class="whitespace-nowrap">Profile</span>
            </a>

            <a
                href="{{ route('settings') }}"
                wire:navigate
                x-on:click="sidebarOpen = false"
                @if (request()->routeIs('settings')) aria-current="page" @endif
                @class([
                    'flex items-center gap-3 overflow-hidden rounded-lg px-3 py-2.5 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:focus-visible:ring-indigo-400',
                    'bg-indigo-600 text-white shadow-sm' => request()->routeIs('settings'),
                    'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white' => ! request()->routeIs('settings'),
                ])
                :class="sidebarCollapsed ? 'justify-center !px-0' : ''"
                title="Settings"
            >
                <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h9m4 0h3M4 12h3m4 0h9M4 18h9m4 0h3" /><circle cx="15" cy="6" r="2" /><circle cx="9" cy="12" r="2" /><circle cx="15" cy="18" r="2" /></svg>
                <span x-show="! sidebarCollapsed" x-transition.opacity class="whitespace-nowrap">Settings</span>
            </a>
        </nav>

        <div class="shrink-0 border-t border-slate-200 p-3 dark:border-white/10">
            <div class="flex items-center gap-3 overflow-hidden rounded-lg px-2 py-2" :class="sidebarCollapsed ? 'justify-center !px-0' : ''">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-sm font-semibold text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-200" title="{{ auth()->user()->name }}">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                <span x-show="! sidebarCollapsed" x-transition.opacity class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-medium text-slate-900 dark:text-white">{{ auth()->user()->name }}</span>
                    <span class="block truncate text-xs text-slate-500 dark:text-slate-400">{{ auth()->user()->email }}</span>
                </span>
            </div>
        </div>
    </aside>
</div>
