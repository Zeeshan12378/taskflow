<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Project;
use App\Models\Task;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Dashboard · TaskFlow')] class extends Component
{
    #[Computed]
    public function projectCount(): int
    {
        return Project::query()->accessibleTo(auth()->user())->count();
    }

    #[Computed]
    public function activeProjectCount(): int
    {
        return Project::query()->accessibleTo(auth()->user())->where('status', 'active')->count();
    }

    #[Computed]
    public function recentProjects()
    {
        return Project::query()->accessibleTo(auth()->user())->with('team')->latest('updated_at')->limit(3)->get();
    }

    #[Computed]
    public function taskCount(): int
    {
        return Task::query()->whereHas('project', fn ($query) => $query->accessibleTo(auth()->user()))->count();
    }

    #[Computed]
    public function completedTaskCount(): int
    {
        return Task::query()
            ->whereHas('project', fn ($query) => $query->accessibleTo(auth()->user()))
            ->whereHas('status', fn ($query) => $query->where('is_complete', true))
            ->count();
    }

    #[Computed]
    public function overdueTaskCount(): int
    {
        return Task::query()
            ->whereHas('project', fn ($query) => $query->accessibleTo(auth()->user()))
            ->whereDate('due_date', '<', today())
            ->whereHas('status', fn ($query) => $query->where('is_complete', false))
            ->count();
    }

    #[Computed]
    public function upcomingTasks()
    {
        return Task::query()
            ->whereHas('project', fn ($query) => $query->accessibleTo(auth()->user()))
            ->whereDate('due_date', '>=', today())
            ->whereHas('status', fn ($query) => $query->where('is_complete', false))
            ->with(['project:id,name', 'status:id,name,is_complete', 'assignee:id,name'])
            ->orderBy('due_date')
            ->limit(4)
            ->get();
    }
};
?>

<main class="mx-auto w-full max-w-7xl flex-1 px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-medium text-indigo-600 dark:text-indigo-400">Your workspace</p>
                <h1 class="mt-1 break-words text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
                    Welcome, {{ auth()->user()->name }}
                </h1>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    Your account is ready. Here is a snapshot of your work.
                </p>
            </div>
        </div>

        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Projects</p>
                <p class="mt-2 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $this->projectCount }}</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $this->activeProjectCount }} active</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Tasks</p>
                <p class="mt-2 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $this->taskCount }}</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Total tasks</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Completed</p>
                <p class="mt-2 text-3xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">{{ $this->completedTaskCount }}</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Tasks finished</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Overdue</p>
                <p class="mt-2 text-3xl font-bold tracking-tight text-amber-600 dark:text-amber-400">{{ $this->overdueTaskCount }}</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Need attention</p>
            </div>
        </div>

        <div class="mt-8 grid gap-5 lg:grid-cols-2">
            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900 dark:text-white">Recent projects</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Your latest workspaces.</p>
                    </div>
                    @if ($this->projectCount > 0)
                        <a href="{{ route('projects.index') }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:text-indigo-700 focus:outline-none focus-visible:underline dark:text-indigo-400">View all</a>
                    @endif
                </div>

                @if ($this->recentProjects->isNotEmpty())
                    <ul class="mt-5 divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($this->recentProjects as $project)
                            <li wire:key="recent-project-{{ $project->id }}" class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                                <div class="min-w-0">
                                    <a href="{{ route('projects.show', $project) }}" wire:navigate class="block truncate text-sm font-medium text-slate-900 hover:text-indigo-600 focus:outline-none focus-visible:underline dark:text-white dark:hover:text-indigo-400">{{ $project->name }}</a>
                                    <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">{{ $project->team?->name ?? 'Personal project' }} · Updated {{ $project->updated_at->diffForHumans() }}</span>
                                </div>
                                <span @class([
                                    'shrink-0 rounded-full px-2.5 py-1 text-xs font-medium capitalize',
                                    'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' => $project->status === 'active',
                                    'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => $project->status === 'archived',
                                ])>{{ $project->status }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="mt-6 rounded-lg border border-dashed border-slate-300 px-5 py-8 text-center dark:border-slate-700">
                        <span class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400" aria-hidden="true">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h6l2 2h8.5v9.5a2 2 0 0 1-2 2h-14a2 2 0 0 1-2-2v-9.5a2 2 0 0 1 2-2Z" /></svg>
                        </span>
                        <h3 class="mt-3 text-sm font-semibold text-slate-900 dark:text-white">No projects yet</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Create a project to give your work a home.</p>
                        <a href="{{ route('projects.create') }}" wire:navigate class="mt-3 inline-flex rounded-lg px-3 py-2 text-sm font-semibold text-indigo-600 hover:bg-indigo-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:text-indigo-400 dark:hover:bg-indigo-500/10">Create project</a>
                    </div>
                @endif
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900 dark:text-white">Upcoming tasks</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">A quick look at what needs your attention.</p>
                    </div>
                </div>

                @if ($this->upcomingTasks->isNotEmpty())
                    <ul class="mt-5 divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($this->upcomingTasks as $task)
                            <li wire:key="upcoming-task-{{ $task->id }}" class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                                <div class="min-w-0">
                                    <a href="{{ route('projects.board', $task->project) }}" wire:navigate class="block truncate text-sm font-medium text-slate-900 hover:text-indigo-600 focus:outline-none focus-visible:underline dark:text-white dark:hover:text-indigo-400">{{ $task->title }}</a>
                                    <span class="mt-1 block truncate text-xs text-slate-500 dark:text-slate-400">{{ $task->project->name }} · {{ $task->status->name }} · {{ $task->assignee?->name ?? 'Unassigned' }}</span>
                                </div>
                                <span class="shrink-0 text-xs font-medium text-slate-500 dark:text-slate-400">{{ $task->due_date->format('M j') }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('tasks.index') }}" wire:navigate class="mt-4 inline-block text-sm font-medium text-indigo-600 hover:underline dark:text-indigo-400">View all tasks</a>
                @else
                    <div class="mt-6 rounded-lg border border-dashed border-slate-300 px-5 py-8 text-center dark:border-slate-700">
                        <span class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400" aria-hidden="true">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="5" width="16" height="16" rx="2" /><path stroke-linecap="round" stroke-linejoin="round" d="M8 3v4m8-4v4M4 10h16m-11 5 2 2 4-4" /></svg>
                        </span>
                        <h3 class="mt-3 text-sm font-semibold text-slate-900 dark:text-white">Nothing scheduled</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Tasks with upcoming due dates will appear here.</p>
                        @if ($this->projectCount > 0)
                            <a href="{{ route('projects.board', Project::query()->accessibleTo(auth()->user())->first()) }}" wire:navigate class="mt-3 inline-flex rounded-lg px-3 py-2 text-sm font-semibold text-indigo-600 hover:bg-indigo-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:text-indigo-400 dark:hover:bg-indigo-500/10">Open a project board</a>
                        @endif
                    </div>
                @endif
            </section>
        </div>
    </main>
