<?php

use App\Models\Project;
use App\Models\Cycle;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Project board · TaskFlow')] class extends Component
{
    #[Locked]
    public int $projectId;

    public string $search = '';

    public string $priorityFilter = 'all';

    public string $dueFilter = 'all';

    public string $typeFilter = 'all';

    public string $assigneeFilter = 'all';

    public string $reviewerFilter = 'all';

    public string $statusFilter = 'all';

    public string $cycleFilter = 'all';

    public string $reporterFilter = 'all';

    public string $dueFrom = '';

    public string $dueTo = '';

    public bool $blockedOnly = false;

    public bool $filtersOpen = false;

    public bool $taskModalOpen = false;

    public ?int $modalTaskId = null;

    public ?string $statusMessage = null;

    public string $statusMessageType = 'success';

    public ?int $quickAddStatusId = null;

    public string $quickAddTitle = '';

    public function mount(int $project): void
    {
        $record = Project::query()->accessibleTo(auth()->user())->findOrFail($project);
        $this->authorize('view', $record);
        $this->projectId = $record->id;

        $this->statusMessage = session()->get('status');
    }

    #[Computed]
    public function project(): Project
    {
        return Project::query()->accessibleTo(auth()->user())->with(['statuses', 'team.members', 'members'])->findOrFail($this->projectId);
    }

    #[Computed]
    public function tasksByStatus()
    {
        $query = $this->project->tasks()->with([
            'status',
            'assignee:id,name',
            'reviewer:id,name',
            'cycle' => fn (BelongsTo $cycle) => $cycle->where(function (Builder $query): void {
                $query->where('project_id', $this->projectId);

                if ($this->canAssignTeamCycles && $this->project->team_id !== null) {
                    $query->orWhere('team_id', $this->project->team_id);
                }
            }),
        ]);

        if (trim($this->search) !== '') {
            $term = '%'.trim($this->search).'%';
            $search = trim($this->search);
            $query->where(fn (Builder $query) => $query->where('title', 'like', $term)
                ->orWhere('description', 'like', $term)
                ->when(ctype_digit($search), fn (Builder $query) => $query->orWhereKey((int) $search)));
        }

        if (in_array($this->priorityFilter, Task::PRIORITIES, true)) {
            $query->where('priority', $this->priorityFilter);
        }

        if ($this->dueFilter === 'overdue') {
            $query->whereDate('due_date', '<', today())->whereHas('status', fn (Builder $query) => $query->where('is_complete', false));
        } elseif ($this->dueFilter === 'today') {
            $query->whereDate('due_date', today())->whereHas('status', fn (Builder $query) => $query->where('is_complete', false));
        } elseif ($this->dueFilter === 'next_7_days') {
            $query->whereBetween('due_date', [today(), today()->addDays(7)])->whereHas('status', fn (Builder $query) => $query->where('is_complete', false));
        } elseif ($this->dueFilter === 'upcoming') {
            $query->whereDate('due_date', '>', today()->addDays(7))->whereHas('status', fn (Builder $query) => $query->where('is_complete', false));
        } elseif ($this->dueFilter === 'none') {
            $query->whereNull('due_date');
        }

        if (in_array($this->typeFilter, Task::TYPES, true)) {
            $query->where('type', $this->typeFilter);
        }

        $memberIds = $this->project->accessibleMemberIds();

        foreach (['assigneeFilter' => 'assignee_id', 'reviewerFilter' => 'reviewer_id'] as $filter => $column) {
            $value = $this->{$filter};

            if ($value === 'unassigned') {
                $query->whereNull($column);
            } elseif (ctype_digit($value) && in_array((int) $value, $memberIds, true)) {
                $query->where($column, (int) $value);
            }
        }

        if (ctype_digit($this->statusFilter)) {
            $statusId = $this->project->statuses()->whereKey((int) $this->statusFilter)->value('id');
            $query->where('project_status_id', $statusId ?? 0);
        }

        if (ctype_digit($this->reporterFilter)) {
            $reporterId = $this->reporters->firstWhere('id', (int) $this->reporterFilter)?->id;
            if ($reporterId !== null) {
                $query->where('user_id', $reporterId);
            }
        }

        if ($this->canFilterCycles) {
            if ($this->cycleFilter === 'none') {
                $query->whereNull('cycle_id');
            } elseif (ctype_digit($this->cycleFilter)) {
                $cycleId = $this->availableCycles->firstWhere('id', (int) $this->cycleFilter)?->id;
                $query->where('cycle_id', $cycleId ?? 0);
            }
        }

        if ($this->dueFrom !== '') {
            $query->whereDate('due_date', '>=', $this->dueFrom);
        }

        if ($this->dueTo !== '') {
            $query->whereDate('due_date', '<=', $this->dueTo);
        }

        if ($this->blockedOnly) {
            $query->whereHas('status', fn (Builder $query) => $query->whereRaw('lower(name) = ?', ['blocked']));
        }

        return $query->orderBy('board_position')
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->latest('updated_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('project_status_id');
    }

    #[Computed]
    public function canReorderTasks(): bool
    {
        return trim($this->search) === '' && $this->activeFilterCount === 0;
    }

    #[Computed]
    public function visibleStatuses()
    {
        return ctype_digit($this->statusFilter)
            ? $this->project->statuses->where('id', (int) $this->statusFilter)
            : $this->project->statuses;
    }

    #[Computed]
    public function canFilterCycles(): bool
    {
        return true;
    }

    #[Computed]
    public function availableCycles()
    {
        return Cycle::query()
            ->where(function ($query): void {
                $query->where('project_id', $this->projectId);

                if ($this->canAssignTeamCycles && $this->project->team_id !== null) {
                    $query->orWhere('team_id', $this->project->team_id);
                }
            })
            ->orderByDesc('start_date')
            ->get(['id', 'project_id', 'team_id', 'name', 'start_date', 'end_date']);
    }

    #[Computed]
    public function canAssignTeamCycles(): bool
    {
        return $this->project->team?->members->contains('id', auth()->id()) ?? false;
    }

    #[Computed]
    public function reporters()
    {
        return User::query()
            ->whereIn('id', $this->project->tasks()->select('user_id')->distinct())
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    #[Computed]
    public function activeFilterCount(): int
    {
        return collect([
            $this->priorityFilter !== 'all', $this->dueFilter !== 'all', $this->typeFilter !== 'all',
            $this->assigneeFilter !== 'all', $this->reviewerFilter !== 'all', $this->statusFilter !== 'all',
            $this->cycleFilter !== 'all', $this->reporterFilter !== 'all', $this->dueFrom !== '', $this->dueTo !== '', $this->blockedOnly,
        ])->filter()->count();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'priorityFilter', 'dueFilter', 'typeFilter', 'assigneeFilter', 'reviewerFilter', 'statusFilter', 'cycleFilter', 'reporterFilter', 'dueFrom', 'dueTo', 'blockedOnly');
        $this->priorityFilter = 'all';
        $this->dueFilter = 'all';
        $this->typeFilter = 'all';
        $this->assigneeFilter = 'all';
        $this->reviewerFilter = 'all';
        $this->statusFilter = 'all';
        $this->cycleFilter = 'all';
        $this->reporterFilter = 'all';
        $this->blockedOnly = false;
        unset($this->tasksByStatus, $this->visibleStatuses);
    }

    public function moveTask(int $taskId, int $statusId): void
    {
        $project = Project::query()->accessibleTo(auth()->user())->findOrFail($this->projectId);
        $this->authorize('view', $project);

        $task = $project->tasks()->findOrFail($taskId);
        $this->authorize('update', $task);

        $status = $project->statuses()->findOrFail($statusId);

        if ($task->project_status_id !== $status->id) {
            if ($status->requiresReviewer() && $task->reviewer_id === null) {
                $this->feedback('Assign a reviewer in task details before moving this task into review.', 'error');

                return;
            }

            if (strtolower(trim($status->name)) === 'blocked' && blank($task->blocked_reason)) {
                $this->feedback('Add a blocked reason in task details before moving this task to Blocked.', 'error');

                return;
            }

            DB::transaction(fn () => $task->update([
                'project_status_id' => $status->id,
                'blocked_reason' => strtolower(trim($status->name)) === 'blocked' ? $task->blocked_reason : null,
            ]));
            $this->feedback('Task moved to '.$status->name.'.');
            unset($this->tasksByStatus);
        }
    }

    public function sortTask(int $taskId, int $position, int $statusId): void
    {
        if (! $this->canReorderTasks) {
            $this->feedback('Clear board filters before reordering tasks.', 'error');

            return;
        }

        if ($position < 0) {
            $this->feedback('Choose a valid task position.', 'error');

            return;
        }

        $project = $this->ownedProject();
        $this->authorize('view', $project);
        $status = $project->statuses()->findOrFail($statusId);

        $wasMoved = DB::transaction(function () use ($project, $status, $taskId, $position): ?bool {
            $task = $project->tasks()->lockForUpdate()->findOrFail($taskId);
            $this->authorize('update', $task);
            $sourceStatusId = $task->project_status_id;

            if ($sourceStatusId !== $status->id) {
                if ($status->requiresReviewer() && $task->reviewer_id === null) {
                    $this->feedback('Assign a reviewer in task details before moving this task into review.', 'error');

                    return null;
                }

                if (strtolower(trim($status->name)) === 'blocked' && blank($task->blocked_reason)) {
                    $this->feedback('Add a blocked reason in task details before moving this task to Blocked.', 'error');

                    return null;
                }
            }

            $sourceTaskIds = $this->orderedTaskIds($project, $sourceStatusId);

            if ($sourceStatusId === $status->id) {
                $sourceTaskIds = array_values(array_filter(
                    $sourceTaskIds,
                    fn (int $id): bool => $id !== $task->id,
                ));
                array_splice($sourceTaskIds, min($position, count($sourceTaskIds)), 0, [$task->id]);
                $this->persistTaskOrder($project, $sourceTaskIds);

                return false;
            }

            $targetTaskIds = $this->orderedTaskIds($project, $status->id);
            $sourceTaskIds = array_values(array_filter(
                $sourceTaskIds,
                fn (int $id): bool => $id !== $task->id,
            ));
            array_splice($targetTaskIds, min($position, count($targetTaskIds)), 0, [$task->id]);

            $task->project_status_id = $status->id;
            $task->board_position = $position;
            $task->blocked_reason = strtolower(trim($status->name)) === 'blocked' ? $task->blocked_reason : null;
            $task->save();

            $this->persistTaskOrder($project, $sourceTaskIds);
            $this->persistTaskOrder($project, $targetTaskIds);

            return true;
        });

        if ($wasMoved === null) {
            return;
        }

        $this->feedback($wasMoved ? 'Task moved to '.$status->name.'.' : 'Task order updated.');
        unset($this->tasksByStatus);
    }

    public function startQuickAddTask(int $statusId): void
    {
        $project = $this->ownedProject();
        $this->authorize('create', [Task::class, $project]);
        $project->statuses()->findOrFail($statusId);

        $this->quickAddStatusId = $statusId;
        $this->quickAddTitle = '';
        $this->resetValidation('quickAddTitle');
        $this->statusMessage = null;
    }

    public function createQuickTask(int $statusId): void
    {
        $project = $this->ownedProject();
        $this->authorize('create', [Task::class, $project]);
        $status = $project->statuses()->findOrFail($statusId);
        $this->quickAddTitle = trim($this->quickAddTitle);
        $validated = $this->validate([
            'quickAddTitle' => ['required', 'string', 'max:180'],
        ]);

        if ($status->requiresReviewer()) {
            $this->feedback('Use the full task form to assign a reviewer before creating a task in '.$status->name.'.', 'error');

            return;
        }

        if (strtolower(trim($status->name)) === 'blocked') {
            $this->feedback('Use the full task form to add a blocked reason before creating a task in this column.', 'error');

            return;
        }

        DB::transaction(fn () => $project->tasks()->create([
            'user_id' => auth()->id(),
            'project_status_id' => $status->id,
            'title' => $validated['quickAddTitle'],
            'type' => 'task',
            'priority' => 'medium',
        ]));

        $this->quickAddStatusId = null;
        $this->quickAddTitle = '';
        $this->resetValidation('quickAddTitle');
        $this->feedback('Task created in '.$status->name.'.');
        unset($this->tasksByStatus);
    }

    public function cancelQuickAddTask(): void
    {
        $this->quickAddStatusId = null;
        $this->quickAddTitle = '';
        $this->resetValidation('quickAddTitle');
        $this->statusMessage = null;
    }

    public function deleteTask(int $taskId): void
    {
        $project = Project::query()->accessibleTo(auth()->user())->findOrFail($this->projectId);
        $task = $project->tasks()->findOrFail($taskId);
        $this->authorize('delete', $task);
        $task->delete();

        $this->feedback('Task deleted.');
        unset($this->tasksByStatus);
    }

    public function openCreateTask(): void
    {
        $project = $this->ownedProject();
        $this->authorize('create', [Task::class, $project]);
        $this->modalTaskId = null;
        $this->taskModalOpen = true;
    }

    public function openTask(int $taskId): void
    {
        $task = $this->ownedProject()->tasks()->findOrFail($taskId);
        $this->authorize('update', $task);
        $this->modalTaskId = $task->id;
        $this->taskModalOpen = true;
    }

    public function closeTaskModal(): void
    {
        $this->taskModalOpen = false;
        $this->modalTaskId = null;
    }

    #[On('task-modal-close')]
    public function closeTaskModalFromChild(): void
    {
        $this->closeTaskModal();
    }

    #[On('task-modal-saved')]
    public function refreshAfterTaskSave(): void
    {
        $this->closeTaskModal();
        unset($this->tasksByStatus);
    }

    #[On('task-modal-refresh')]
    public function refreshTaskCards(): void
    {
        unset($this->tasksByStatus);
    }

    private function ownedProject(): Project
    {
        return Project::query()->accessibleTo(auth()->user())->findOrFail($this->projectId);
    }

    /**
     * @return array<int, int>
     */
    private function orderedTaskIds(Project $project, int $statusId): array
    {
        return $project->tasks()
            ->where('project_status_id', $statusId)
            ->orderBy('board_position')
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->latest('updated_at')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->pluck('id')
            ->map(fn (int $id): int => $id)
            ->all();
    }

    /**
     * @param  array<int, int>  $taskIds
     */
    private function persistTaskOrder(Project $project, array $taskIds): void
    {
        foreach ($taskIds as $position => $taskId) {
            DB::table('tasks')
                ->where('project_id', $project->id)
                ->where('id', $taskId)
                ->update(['board_position' => $position]);
        }
    }

    private function feedback(string $message, string $type = 'success'): void
    {
        $this->statusMessage = $message;
        $this->statusMessageType = $type;
    }
};
?>

<main class="mx-auto w-full max-w-[100rem] flex-1 px-4 py-7 sm:px-6 lg:px-8 lg:py-9">
    <x-projects.tabs :project="$this->project" active="board" />

    <div class="mt-4 flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <p class="text-sm font-medium text-indigo-600 dark:text-indigo-400">Project board</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $this->project->name }}</h1>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Move work through your project’s workflow.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <label class="sr-only" for="board-search">Search board</label>
            <input id="board-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Search tasks..." class="min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm outline-none placeholder:text-slate-400 focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10 sm:w-56 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
            <button type="button" wire:click="$toggle('filtersOpen')" aria-expanded="{{ $filtersOpen ? 'true' : 'false' }}" class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3 4.75A.75.75 0 0 1 3.75 4h12.5a.75.75 0 0 1 .59 1.22L12 11.4v4.1a.75.75 0 0 1-1.08.67l-2.5-1.25A.75.75 0 0 1 8 14.25v-2.84L3.16 5.22A.75.75 0 0 1 3 4.75Z" /></svg>
                Filters
                @if ($this->activeFilterCount > 0)<span class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-bold text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-200">{{ $this->activeFilterCount }}</span>@endif
            </button>
            @can('update', $this->project)
                <a href="{{ route('projects.edit', $this->project) }}" wire:navigate class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">Project settings</a>
            @endcan
            <button type="button" wire:click="openCreateTask" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600">New task</button>
        </div>
    </div>

    @if ($filtersOpen)
        <section class="mt-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 dark:border-slate-800 dark:bg-slate-900" aria-label="Board filters">
            <div class="mb-4 flex items-center justify-between gap-3"><div><h2 class="text-sm font-semibold text-slate-900 dark:text-white">Filter tasks</h2><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Combine fields to narrow the board.</p></div><button type="button" wire:click="clearFilters" class="text-sm font-semibold text-indigo-600 hover:underline dark:text-indigo-400">Clear all</button></div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div><label for="board-status" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Workflow status</label><select id="board-status" wire:model.live="statusFilter" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"><option value="all">All statuses</option>@foreach ($this->project->statuses as $status)<option value="{{ $status->id }}">{{ $status->name }}</option>@endforeach</select></div>
                <div><label for="board-type" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Task type</label><select id="board-type" wire:model.live="typeFilter" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"><option value="all">All types</option>@foreach (\App\Models\Task::TYPES as $type)<option value="{{ $type }}">{{ ucfirst($type) }}</option>@endforeach</select></div>
                <div><label for="board-priority" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Priority</label><select id="board-priority" wire:model.live="priorityFilter" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"><option value="all">All priorities</option>@foreach (\App\Models\Task::PRIORITIES as $priority)<option value="{{ $priority }}">{{ ucfirst($priority) }}</option>@endforeach</select></div>
                <div><label for="board-due" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Due date</label><select id="board-due" wire:model.live="dueFilter" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"><option value="all">Any due date</option><option value="overdue">Overdue</option><option value="today">Due today</option><option value="next_7_days">Next 7 days</option><option value="upcoming">After 7 days</option><option value="none">No due date</option></select></div>
                <div><label for="board-assignee" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Assignee</label><select id="board-assignee" wire:model.live="assigneeFilter" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"><option value="all">Anyone</option><option value="unassigned">Unassigned</option>@foreach ($this->project->accessibleMembers() as $member)<option value="{{ $member->id }}">{{ $member->name }}</option>@endforeach</select></div>
                <div><label for="board-reviewer" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Reviewer</label><select id="board-reviewer" wire:model.live="reviewerFilter" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"><option value="all">Anyone</option><option value="unassigned">No reviewer</option>@foreach ($this->project->accessibleMembers() as $member)<option value="{{ $member->id }}">{{ $member->name }}</option>@endforeach</select></div>
                <div><label for="board-reporter" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Reporter</label><select id="board-reporter" wire:model.live="reporterFilter" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"><option value="all">Anyone</option>@foreach ($this->reporters as $reporter)<option value="{{ $reporter->id }}">{{ $reporter->name }}</option>@endforeach</select></div>
                @if ($this->canFilterCycles)
                    <div><label for="board-cycle" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Sprint</label><select id="board-cycle" wire:model.live="cycleFilter" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"><option value="all">Any sprint</option><option value="none">No sprint</option>@foreach ($this->availableCycles as $cycle)<option value="{{ $cycle->id }}">{{ $cycle->project_id ? 'Project' : 'Team' }} · {{ $cycle->name }}</option>@endforeach</select></div>
                @endif
                <div><label for="board-due-from" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Due from</label><input id="board-due-from" type="date" wire:model.live="dueFrom" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"></div>
                <div><label for="board-due-to" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Due to</label><input id="board-due-to" type="date" wire:model.live="dueTo" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"></div>
                <label class="flex min-h-10 items-center gap-2 self-end rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:text-slate-200"><input type="checkbox" wire:model.live="blockedOnly" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-600"> Blocked tasks only</label>
            </div>
            @if ($search !== '' || $this->activeFilterCount > 0)<div class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3 dark:border-slate-800"><span class="text-xs text-slate-500 dark:text-slate-400">Filters are combined. <button type="button" wire:click="clearFilters" class="font-semibold text-indigo-600 hover:underline dark:text-indigo-400">Reset board</button></span></div>@endif
        </section>
    @endif

    @if ($statusMessage)
        <p @if ($statusMessageType === 'error') role="alert" @else role="status" @endif @class([
            'mt-5 rounded-lg border px-4 py-3 text-sm font-medium',
            'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300' => $statusMessageType === 'success',
            'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300' => $statusMessageType === 'error',
        ])>{{ $statusMessage }}</p>
    @endif

    <p wire:loading.flex wire:target="search,priorityFilter,dueFilter,typeFilter,assigneeFilter,reviewerFilter,statusFilter,cycleFilter,reporterFilter,dueFrom,dueTo,blockedOnly" role="status" class="mt-4 items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
        <span class="h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-indigo-600 dark:border-slate-700 dark:border-t-indigo-400" aria-hidden="true"></span>
        Updating board...
    </p>

    <div wire:loading.class="opacity-50" wire:loading.attr="aria-busy" wire:target="search,priorityFilter,dueFilter,typeFilter,assigneeFilter,reviewerFilter,statusFilter,cycleFilter,reporterFilter,dueFrom,dueTo,blockedOnly" class="mt-6 flex snap-x snap-mandatory items-start gap-4 overflow-x-auto overflow-y-hidden pb-5 transition-opacity" aria-label="Task board columns">
        @foreach ($this->visibleStatuses as $status)
            @php($columnTasks = $this->tasksByStatus->get($status->id, collect()))
            <section
                wire:key="board-column-{{ $status->id }}"
                @if (! $this->canReorderTasks)
                    x-on:dragover.prevent="$event.dataTransfer.dropEffect = 'move'"
                    x-on:drop.prevent="const taskId = Number($event.dataTransfer.getData('text/plain')); if (taskId) $wire.moveTask(taskId, {{ $status->id }})"
                @endif
                class="flex h-[calc(100dvh-21rem)] min-h-72 w-[min(86vw,21rem)] shrink-0 snap-start flex-col rounded-xl border border-slate-200 bg-slate-100/70 p-3 dark:border-slate-800 dark:bg-slate-900/60 sm:w-80"
            >
                <header style="border-top-color: {{ $status->accentHex() }}" class="flex items-start justify-between gap-2 rounded-lg border-t-4 bg-white px-3 py-3 dark:bg-slate-950">
                    <div class="min-w-0">
                        <h2 class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $status->name }}</h2>
                        <p class="mt-0.5 text-xs text-slate-600 dark:text-slate-400">{{ $columnTasks->count() }} {{ \Illuminate\Support\Str::plural('task', $columnTasks->count()) }}</p>
                    </div>
                    @if ($status->is_complete)
                        <span class="shrink-0 rounded-full bg-white/80 px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-emerald-700 dark:bg-slate-900/70 dark:text-emerald-300">Complete</span>
                    @endif
                </header>

                <div
                    @if ($this->canReorderTasks)
                        wire:sort="sortTask"
                        wire:sort:group="board-{{ $this->projectId }}"
                        wire:sort:group-id="{{ $status->id }}"
                    @endif
                    class="mt-3 min-h-24 flex-1 space-y-3 overflow-y-auto overscroll-y-contain pr-1 [scrollbar-gutter:stable]"
                >
                    @forelse ($columnTasks as $task)
                        <article
                            wire:key="board-task-{{ $task->id }}"
                            @if ($this->canReorderTasks)
                                wire:sort:item="{{ $task->id }}"
                            @else
                                draggable="true"
                                x-on:dragstart="$event.dataTransfer.effectAllowed = 'move'; $event.dataTransfer.setData('text/plain', '{{ $task->id }}')"
                            @endif
                            class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm transition hover:border-indigo-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-950 dark:hover:border-indigo-800"
                        >
                            <div class="flex items-start justify-between gap-2">
                                @if ($this->canReorderTasks)
                                    <span wire:sort:handle title="Drag to reorder" aria-label="Drag to reorder {{ $task->title }}" class="mt-0.5 shrink-0 cursor-grab text-slate-400 active:cursor-grabbing dark:text-slate-500">
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M7 4a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm0 6a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm0 6a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm9-12a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm0 6a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm0 6a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" /></svg>
                                    </span>
                                @endif
                                <button type="button" wire:click="openTask({{ $task->id }})" class="min-w-0 text-left text-sm font-semibold leading-5 text-slate-900 hover:text-indigo-700 focus:outline-none focus-visible:underline dark:text-white dark:hover:text-indigo-300">{{ $task->title }}</button>
                                <span @class([
                                    'shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide',
                                    'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300' => $task->priority === 'high',
                                    'bg-fuchsia-50 text-fuchsia-800 dark:bg-fuchsia-500/10 dark:text-fuchsia-200' => $task->priority === 'urgent',
                                    'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' => $task->priority === 'medium',
                                    'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => $task->priority === 'low',
                                ])>{{ $task->priority }}</span>
                            </div>
                            <span class="mt-2 inline-flex rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">{{ $task->type }}</span>
                            @if ($task->story_points)<span class="ml-1 mt-2 inline-flex rounded-full bg-violet-50 px-2 py-0.5 text-[10px] font-semibold text-violet-700 dark:bg-violet-500/10 dark:text-violet-300">{{ $task->story_points }} pts</span>@endif
                            @if ($task->reviewer)
                                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Reviewer: {{ $task->reviewer->name }}</p>
                            @endif
                            @if ($task->cycle)
                                <p class="mt-1 text-xs text-indigo-600 dark:text-indigo-300">Sprint: {{ $task->cycle->name }}</p>
                            @endif
                            @if ($task->isBlocked() && $task->blocked_reason)
                                <p class="rich-text-output mt-2 text-xs text-red-700 dark:text-red-300">Blocked: {!! \App\Support\RichText::sanitize($task->blocked_reason) !!}</p>
                            @endif
                            @if ($task->description)
                                <div class="rich-text-output mt-2 line-clamp-2 text-xs leading-5 text-slate-500 dark:text-slate-400">{!! \App\Support\RichText::sanitize($task->description) !!}</div>
                            @endif

                            <div class="mt-3 flex items-center justify-between gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">
                                <span @class([
                                    'text-xs',
                                    'font-medium text-red-600 dark:text-red-400' => $task->due_date && $task->due_date->lt(today()) && ! $task->status->is_complete,
                                    'text-slate-500 dark:text-slate-400' => ! $task->due_date || ! $task->due_date->lt(today()) || $task->status->is_complete,
                                ])>{{ $task->due_date?->format('M j') ?? 'No due date' }}</span>
                                @if ($task->assignee)
                                    <span title="Assigned to {{ $task->assignee->name }}" aria-label="Assigned to {{ $task->assignee->name }}" class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-[10px] font-bold text-indigo-700 dark:bg-indigo-400/20 dark:text-indigo-200">{{ strtoupper(substr($task->assignee->name, 0, 1)) }}</span>
                                @else
                                    <span class="sr-only">Unassigned</span>
                                @endif
                                <select aria-label="Move {{ $task->title }} to a column" wire:change="moveTask({{ $task->id }}, $event.target.value)" wire:loading.attr="disabled" wire:target="moveTask" class="max-w-32 rounded-md border border-slate-200 bg-white px-2 py-1 text-xs text-slate-700 focus:border-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-600/20 disabled:opacity-60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                    @foreach ($this->project->statuses as $option)
                                        <option value="{{ $option->id }}" @selected($option->id === $status->id)>{{ $option->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mt-2 flex justify-end gap-3 text-xs">
                                <button type="button" wire:click="openTask({{ $task->id }})" class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">Open</button>
                                <button type="button" wire:click="deleteTask({{ $task->id }})" wire:confirm="Delete {{ $task->title }}? This cannot be undone." wire:loading.attr="disabled" wire:target="deleteTask" class="font-medium text-red-600 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 disabled:opacity-50 dark:text-red-400">Delete</button>
                            </div>
                        </article>
                    @empty
                        <div @if ($this->canReorderTasks) wire:sort:ignore @endif class="rounded-lg border border-dashed border-slate-300 bg-white/70 px-4 py-7 text-center dark:border-slate-700 dark:bg-slate-950/40">
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">No tasks in this column</p>
                            <button type="button" wire:click="openCreateTask" class="mt-2 inline-block text-xs font-semibold text-indigo-600 hover:underline dark:text-indigo-400">Add a task</button>
                        </div>
                    @endforelse
                </div>

                @if ($quickAddStatusId === $status->id)
                    <form
                        wire:submit="createQuickTask({{ $status->id }})"
                        x-data
                        x-init="$nextTick(() => $el.querySelector('input')?.focus())"
                        x-on:keydown.escape="$wire.cancelQuickAddTask()"
                        class="mt-3 rounded-lg border border-indigo-300 bg-white p-3 shadow-sm dark:border-indigo-800 dark:bg-slate-950"
                    >
                        <label for="quick-add-title-{{ $status->id }}" class="sr-only">New task title for {{ $status->name }}</label>
                        <input
                            id="quick-add-title-{{ $status->id }}"
                            type="text"
                            wire:model="quickAddTitle"
                            maxlength="180"
                            placeholder="What needs to be done?"
                            aria-describedby="quick-add-help-{{ $status->id }}"
                            @error('quickAddTitle') aria-invalid="true" @enderror
                            class="block min-h-10 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none placeholder:text-slate-400 focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                        >
                        @error('quickAddTitle') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        <p id="quick-add-help-{{ $status->id }}" class="mt-2 text-[11px] text-slate-500 dark:text-slate-400">Press Enter to add · Esc to cancel</p>
                        <div class="mt-2 flex justify-end gap-2">
                            <button type="button" wire:click="cancelQuickAddTask" class="rounded-md px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">Cancel</button>
                            <button type="submit" wire:loading.attr="disabled" wire:target="createQuickTask" class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60">Add task</button>
                        </div>
                    </form>
                @else
                    <button
                        type="button"
                        wire:click="startQuickAddTask({{ $status->id }})"
                        aria-label="Add task to {{ $status->name }}"
                        class="mt-3 inline-flex min-h-10 w-full items-center justify-start gap-2 rounded-lg border border-dashed border-slate-300 px-3 text-sm font-medium text-slate-500 transition hover:border-indigo-300 hover:bg-white hover:text-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:border-slate-700 dark:text-slate-400 dark:hover:border-indigo-800 dark:hover:bg-slate-950 dark:hover:text-indigo-300"
                    >
                        <span class="text-lg leading-none" aria-hidden="true">+</span>
                        Add a task
                    </button>
                @endif
            </section>
        @endforeach
    </div>
    @if ($taskModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-3 backdrop-blur-sm sm:p-6" x-data x-on:keydown.escape.window="$wire.closeTaskModal()" wire:key="task-modal-shell-{{ $this->projectId }}-{{ $modalTaskId ?? 'new' }}">
            <section role="dialog" aria-modal="true" aria-label="{{ $modalTaskId ? 'Task details' : 'Create a task' }}" class="max-h-[calc(100vh-1.5rem)] w-full max-w-6xl overflow-y-auto rounded-2xl bg-white shadow-2xl ring-1 ring-black/10 dark:bg-slate-900 sm:max-h-[calc(100vh-3rem)]">
                @livewire('tasks.form', ['project' => $this->projectId, 'task' => $modalTaskId, 'modal' => true], key('task-modal-'.$this->projectId.'-'.($modalTaskId ?? 'new')))
            </section>
        </div>
    @endif
</main>
