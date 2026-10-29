@props(['project', 'active'])

<nav aria-label="Project views" class="sticky top-16 z-20 -mx-4 mt-0 overflow-x-auto border-b border-slate-200 bg-slate-50/95 px-4 backdrop-blur sm:mx-0 sm:px-0 md:top-20 dark:border-slate-800 dark:bg-slate-950/95">
    <div class="mx-auto flex min-w-max items-center gap-1 px-1 sm:min-w-0 sm:max-w-[100rem]">
        @foreach ([
            'overview' => ['Overview', 'projects.show'],
            'board' => ['Board', 'projects.board'],
            'list' => ['List', 'projects.list'],
            'calendar' => ['Calendar', 'projects.calendar'],
            'reports' => ['Reports', 'projects.reports'],
            'settings' => ['Settings', 'projects.edit'],
        ] as $key => [$label, $route])
            <a href="{{ route($route, $project) }}" wire:navigate @if ($active === $key) aria-current="page" @endif @class([
                'inline-flex items-center border-b-2 px-3 py-3 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-600',
                'border-indigo-600 text-indigo-700 dark:text-indigo-300' => $active === $key,
                'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white' => $active !== $key,
            ])>{{ $label }}</a>
        @endforeach
    </div>
</nav>
