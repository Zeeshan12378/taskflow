<?php

use App\Models\Cycle;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Project sprints · TaskFlow')] class extends Component
{
    #[Locked]
    public int $projectId;

    public function mount(int $project): void
    {
        $record = Project::query()->accessibleTo(auth()->user())->findOrFail($project);
        $this->authorize('view', $record);
        $this->projectId = $record->id;
    }

    #[Computed]
    public function project(): Project
    {
        return Project::query()
            ->accessibleTo(auth()->user())
            ->with(['statuses', 'team.members'])
            ->findOrFail($this->projectId);
    }

    #[Computed]
    public function canViewTeamSprints(): bool
    {
        return $this->project->team?->members->contains('id', auth()->id()) ?? false;
    }

    #[Computed]
    public function cycles(): Collection
    {
        return Cycle::query()
            ->where(function (Builder $query): void {
                $query->where('project_id', $this->projectId);

                if ($this->canViewTeamSprints && $this->project->team_id !== null) {
                    $query->orWhere('team_id', $this->project->team_id);
                }
            })
            ->orderByDesc('start_date')
            ->get();
    }

    #[Computed]
    public function tasks(): Collection
    {
        return $this->project->tasks()
            ->with(['status', 'assignee:id,name'])
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->latest('updated_at')
            ->get();
    }

    /**
     * @return Collection<int, array{cycle: Cycle|null, label: string, tasks: Collection<int, Task>}>
     */
    #[Computed]
    public function sprintGroups(): Collection
    {
        $visibleCycleIds = $this->cycles->modelKeys();
        $groups = $this->cycles->map(fn (Cycle $cycle): array => [
            'cycle' => $cycle,
            'label' => $cycle->project_id !== null ? 'Project sprint' : 'Team sprint',
            'tasks' => $this->tasks->where('cycle_id', $cycle->id)->values(),
        ]);
        $otherTasks = $this->tasks
            ->reject(fn (Task $task): bool => in_array($task->cycle_id, $visibleCycleIds, true))
            ->values();

        if ($otherTasks->isNotEmpty() || $groups->isEmpty()) {
            $groups->push([
                'cycle' => null,
                'label' => 'Backlog / other tasks',
                'tasks' => $otherTasks,
            ]);
        }

        return $groups;
    }
};
?>

<main class="mx-auto w-full max-w-[100rem] flex-1 px-4 py-7 sm:px-6 lg:px-8 lg:py-9">
    <x-projects.tabs :project="$this->project" active="sprints" />

    <header class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-medium text-indigo-600 dark:text-indigo-400">{{ $this->project->name }}</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Sprint tasks</h1>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Tasks grouped by each sprint available to you in this project.</p>
        </div>
        <a href="{{ route('projects.board', $this->project) }}" wire:navigate class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Open board</a>
    </header>

    <div class="mt-5 space-y-5">
        @foreach ($this->sprintGroups as $groupIndex => $group)
            @php
                $cycle = $group['cycle'];
                $tasks = $group['tasks'];
                $completedTasks = $tasks->filter(fn ($task) => $task->status?->is_complete)->count();
                $groupTitle = $cycle?->name ?? $group['label'];
                $groupId = 'sprint-task-group-'.$groupIndex;
            @endphp
            <details wire:key="sprint-task-group-{{ $cycle?->id ?? 'other' }}" class="group overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <summary class="flex cursor-pointer list-none flex-col gap-2 bg-slate-50 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5 dark:bg-slate-950/50">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span aria-hidden="true" class="inline-flex h-5 w-5 shrink-0 items-center justify-center text-xs text-slate-500 transition-transform group-open:rotate-90 dark:text-slate-400">▶</span>
                            <h2 id="{{ $groupId }}" class="truncate text-base font-semibold text-slate-900 dark:text-white">{{ $groupTitle }}</h2>
                            @if ($cycle)
                                <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">{{ $group['label'] }}</span>
                                <span @class(['rounded-full px-2.5 py-1 text-xs font-medium capitalize', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' => $cycle->status === 'active', 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => $cycle->status !== 'active'])>{{ $cycle->status }}</span>
                            @endif
                        </div>
                        @if ($cycle)
                            <p class="mt-1 pl-7 text-xs text-slate-500 dark:text-slate-400">{{ $cycle->start_date->format('M j') }} – {{ $cycle->end_date->format('M j') }}@if ($cycle->goal) · {{ $cycle->goal }}@endif</p>
                        @else
                            <p class="mt-1 pl-7 text-xs text-slate-500 dark:text-slate-400">Tasks not assigned to a sprint you can view.</p>
                        @endif
                    </div>
                    <p class="shrink-0 text-xs tabular-nums text-slate-500 dark:text-slate-400">{{ $tasks->count() }} {{ \Illuminate\Support\Str::plural('work item', $tasks->count()) }} · {{ $completedTasks }} complete</p>
                </summary>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[700px] divide-y divide-slate-200 text-left dark:divide-slate-800">
                        <thead class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <tr><th class="px-5 py-3">Task</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Priority</th><th class="px-5 py-3">Assignee</th><th class="px-5 py-3">Due date</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($tasks as $task)
                                <tr wire:key="sprint-task-{{ $task->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                    <td class="px-5 py-4">
                                        <a href="{{ route('projects.tasks.edit', [$this->project, $task]) }}" wire:navigate class="font-medium text-slate-900 hover:text-indigo-700 dark:text-white dark:hover:text-indigo-300">{{ $task->title }}</a>
                                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ ucfirst($task->type) }} · #{{ $task->id }}@if ($task->story_points) · {{ $task->story_points }} pts @endif</p>
                                    </td>
                                    <td class="px-5 py-4 text-sm text-slate-700 dark:text-slate-300">{{ $task->status?->name ?? 'No status' }}</td>
                                    <td class="px-5 py-4 text-sm capitalize text-slate-700 dark:text-slate-300">{{ $task->priority }}</td>
                                    <td class="px-5 py-4 text-sm text-slate-700 dark:text-slate-300">{{ $task->assignee?->name ?? 'Unassigned' }}</td>
                                    <td @class(['px-5 py-4 text-sm', 'font-medium text-red-600 dark:text-red-400' => $task->due_date?->lt(today()) && ! $task->status?->is_complete, 'text-slate-600 dark:text-slate-300' => ! ($task->due_date?->lt(today()) && ! $task->status?->is_complete)])>{{ $task->due_date?->format('M j, Y') ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-8 text-center text-sm text-slate-500 dark:text-slate-400">No tasks in this group.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </details>
        @endforeach
    </div>
</main>
