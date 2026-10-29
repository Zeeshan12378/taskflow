<?php

use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Project list · TaskFlow')] class extends Component
{
    #[Locked]
    public int $projectId;

    public string $search = '';

    public string $statusFilter = 'all';

    public function mount(int $project): void
    {
        $record = Project::query()->accessibleTo(auth()->user())->findOrFail($project);
        $this->authorize('view', $record);
        $this->projectId = $record->id;
    }

    #[Computed]
    public function project(): Project
    {
        return Project::query()->accessibleTo(auth()->user())->with('statuses')->findOrFail($this->projectId);
    }

    #[Computed]
    public function tasks()
    {
        $query = $this->project->tasks()->with(['status', 'assignee:id,name']);

        if (trim($this->search) !== '') {
            $term = '%'.trim($this->search).'%';
            $query->where(fn (Builder $builder) => $builder->where('title', 'like', $term)->orWhere('description', 'like', $term));
        }

        if ($this->statusFilter !== 'all') {
            $query->where('project_status_id', $this->statusFilter);
        }

        return $query->orderByRaw('due_date is null')->orderBy('due_date')->latest('updated_at')->get();
    }
};
?>

<main class="mx-auto w-full max-w-[100rem] flex-1 px-4 py-7 sm:px-6 lg:px-8 lg:py-9">
    <x-projects.tabs :project="$this->project" active="list" />

    <div class="mt-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div><p class="text-sm font-medium text-indigo-600 dark:text-indigo-400">{{ $this->project->name }}</p><h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">List</h1><p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Tasks in this project, sorted by due date.</p></div>
        <div class="flex flex-col gap-2 sm:flex-row">
            <label class="sr-only" for="project-list-search">Search tasks</label><input id="project-list-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Search tasks..." class="min-h-10 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
            <label class="sr-only" for="project-list-status">Filter by status</label><select id="project-list-status" wire:model.live="statusFilter" class="min-h-10 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"><option value="all">All statuses</option>@foreach ($this->project->statuses as $status)<option value="{{ $status->id }}">{{ $status->name }}</option>@endforeach</select>
        </div>
    </div>

    <div class="mt-5 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <table class="w-full min-w-[760px] divide-y divide-slate-200 text-left dark:divide-slate-800">
            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-950/50 dark:text-slate-400"><tr><th class="px-5 py-3">Task</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Priority</th><th class="px-5 py-3">Assignee</th><th class="px-5 py-3">Due date</th></tr></thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($this->tasks as $task)
                    <tr wire:key="project-list-task-{{ $task->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-800/50"><td class="px-5 py-4"><a href="{{ route('projects.tasks.edit', [$this->project, $task]) }}" wire:navigate class="font-medium text-slate-900 hover:text-indigo-700 dark:text-white dark:hover:text-indigo-300">{{ $task->title }}</a><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ ucfirst($task->type) }} · #{{ $task->id }}</p></td><td class="px-5 py-4 text-sm text-slate-700 dark:text-slate-300">{{ $task->status?->name ?? 'No status' }}</td><td class="px-5 py-4 text-sm capitalize text-slate-700 dark:text-slate-300">{{ $task->priority }}</td><td class="px-5 py-4 text-sm text-slate-700 dark:text-slate-300">{{ $task->assignee?->name ?? 'Unassigned' }}</td><td @class(['px-5 py-4 text-sm', 'font-medium text-red-600 dark:text-red-400' => $task->due_date?->lt(today()) && ! $task->status?->is_complete, 'text-slate-600 dark:text-slate-300' => ! ($task->due_date?->lt(today()) && ! $task->status?->is_complete)])>{{ $task->due_date?->format('M j, Y') ?? '—' }}</td></tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-12 text-center text-sm text-slate-500 dark:text-slate-400">No matching tasks in this project.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</main>
