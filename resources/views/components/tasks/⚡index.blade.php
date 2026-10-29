<?php

use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Task;
use App\Models\Cycle;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Tasks · TaskFlow')] class extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 'all')]
    public string $statusFilter = 'all';

    #[Url(except: 'all')]
    public string $priorityFilter = 'all';

    #[Url(except: 'all')]
    public string $typeFilter = 'all';

    #[Url(except: 'all')]
    public string $dueFilter = 'all';

    #[Url(except: 'all')]
    public string $projectFilter = 'all';

    #[Url(except: 'all')]
    public string $workScope = 'all';

    #[Url(except: 'all')]
    public string $cycleFilter = 'all';

    #[Url(except: '')]
    public string $dueFrom = '';

    #[Url(except: '')]
    public string $dueTo = '';

    public bool $blockedOnly = false;

    public bool $taskModalOpen = false;

    #[Locked]
    public ?int $modalProjectId = null;

    #[Locked]
    public ?int $modalTaskId = null;

    public ?string $statusMessage = null;

    #[Locked]
    public bool $onlyAssignedToMe = false;

    public function mount(): void
    {
        $this->authorize('viewAny', Task::class);
        $this->onlyAssignedToMe = request()->routeIs('tasks.mine');
        $this->statusMessage = session()->get('status');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'statusFilter', 'priorityFilter', 'typeFilter', 'dueFilter', 'projectFilter', 'workScope', 'cycleFilter', 'dueFrom', 'dueTo', 'blockedOnly'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'all';
        $this->priorityFilter = 'all';
        $this->typeFilter = 'all';
        $this->dueFilter = 'all';
        $this->projectFilter = 'all';
        $this->workScope = 'all';
        $this->cycleFilter = 'all';
        $this->dueFrom = '';
        $this->dueTo = '';
        $this->blockedOnly = false;
        $this->resetPage();
    }

    #[Computed]
    public function projects(): Collection
    {
        return Project::query()->accessibleTo(auth()->user())->with('statuses')->orderBy('name')->get();
    }

    #[Computed]
    public function statuses(): Collection
    {
        return ProjectStatus::query()
            ->whereHas('project', fn (Builder $query) => $query->accessibleTo(auth()->user()))
            ->with('project:id,name')
            ->orderBy('project_id')
            ->orderBy('position')
            ->get();
    }

    #[Computed]
    public function availableCycles(): Collection
    {
        if (! $this->onlyAssignedToMe) {
            return collect();
        }

        $teamIds = auth()->user()->teams()->select('teams.id');
        $accessibleProjectIds = Project::query()->accessibleTo(auth()->user())->select('projects.id');

        return Cycle::query()
            ->where(function (Builder $query) use ($teamIds, $accessibleProjectIds): void {
                $query->whereIn('project_id', $accessibleProjectIds)
                    ->orWhere(function (Builder $query) use ($teamIds): void {
                        $query->whereIn('team_id', $teamIds)
                            ->whereHas('team.projects', fn (Builder $projects) => $projects->accessibleTo(auth()->user()));
                    });
            })
            ->orderByDesc('start_date')
            ->get(['id', 'project_id', 'team_id', 'name', 'start_date', 'end_date']);
    }

    #[Computed]
    public function workSummary(): array
    {
        if (! $this->onlyAssignedToMe) {
            return [];
        }

        $base = $this->myTasksQuery();
        $open = fn (Builder $query) => $query->whereHas('status', fn (Builder $status) => $status->where('is_complete', false));

        return [
            'assigned' => (clone $base)->where('assignee_id', auth()->id())->count(),
            'reviews' => (clone $base)->where('reviewer_id', auth()->id())->whereHas('status', fn (Builder $status) => $status->whereRaw('lower(name) in (?, ?, ?, ?)', ['verify', 'in review', 'qa', 'quality assurance']))->count(),
            'overdue' => $open((clone $base)->whereDate('due_date', '<', today()))->count(),
            'due_soon' => $open((clone $base)->whereBetween('due_date', [today(), today()->addDays(7)]))->count(),
        ];
    }

    #[Computed]
    public function tasks(): LengthAwarePaginator
    {
        $relationships = ['project:id,name', 'status:id,project_id,name,is_complete', 'assignee:id,name,email', 'reviewer:id,name'];
        if ($this->onlyAssignedToMe && auth()->user()->teams()->exists()) {
            $relationships[] = 'cycle:id,name';
        }

        return Task::query()
            ->whereHas('project', fn (Builder $query) => $query->accessibleTo(auth()->user()))
            ->when($this->onlyAssignedToMe, function (Builder $query): void {
                $query->where(fn (Builder $query) => match ($this->workScope) {
                    'assigned' => $query->where('assignee_id', auth()->id()),
                    'reviewing' => $query->where('reviewer_id', auth()->id()),
                    default => $query->where('assignee_id', auth()->id())->orWhere('reviewer_id', auth()->id()),
                });
            })
            ->with($relationships)
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $term = '%'.trim($this->search).'%';
                $query->where(fn (Builder $query) => $query->where('title', 'like', $term)->orWhere('description', 'like', $term));
            })
            ->when($this->projectFilter !== 'all' && ctype_digit($this->projectFilter), fn (Builder $query) => $query->where('project_id', (int) $this->projectFilter))
            ->when(in_array($this->priorityFilter, Task::PRIORITIES, true), fn (Builder $query) => $query->where('priority', $this->priorityFilter))
            ->when(in_array($this->typeFilter, Task::TYPES, true), fn (Builder $query) => $query->where('type', $this->typeFilter))
            ->when($this->statusFilter !== 'all' && ctype_digit($this->statusFilter), fn (Builder $query) => $query->whereHas('status', fn (Builder $query) => $query->whereKey((int) $this->statusFilter)->whereHas('project', fn (Builder $query) => $query->accessibleTo(auth()->user()))))
            ->when($this->dueFilter === 'overdue', fn (Builder $query) => $query->whereDate('due_date', '<', today())->whereHas('status', fn (Builder $query) => $query->where('is_complete', false)))
            ->when($this->dueFilter === 'today', fn (Builder $query) => $query->whereDate('due_date', today())->whereHas('status', fn (Builder $query) => $query->where('is_complete', false)))
            ->when($this->dueFilter === 'next_7_days', fn (Builder $query) => $query->whereBetween('due_date', [today(), today()->addDays(7)])->whereHas('status', fn (Builder $query) => $query->where('is_complete', false)))
            ->when($this->dueFilter === 'upcoming', fn (Builder $query) => $query->whereDate('due_date', '>', today()->addDays(7))->whereHas('status', fn (Builder $query) => $query->where('is_complete', false)))
            ->when($this->dueFilter === 'none', fn (Builder $query) => $query->whereNull('due_date'))
            ->when($this->dueFrom !== '', fn (Builder $query) => $query->whereDate('due_date', '>=', $this->dueFrom))
            ->when($this->dueTo !== '', fn (Builder $query) => $query->whereDate('due_date', '<=', $this->dueTo))
            ->when($this->onlyAssignedToMe && $this->blockedOnly, fn (Builder $query) => $query->whereHas('status', fn (Builder $status) => $status->whereRaw('lower(name) = ?', ['blocked'])))
            ->when($this->onlyAssignedToMe && $this->cycleFilter === 'none', fn (Builder $query) => $query->whereNull('cycle_id'))
            ->when($this->onlyAssignedToMe && ctype_digit($this->cycleFilter), function (Builder $query): void {
                $cycleId = $this->availableCycles->firstWhere('id', (int) $this->cycleFilter)?->id;
                $query->where('cycle_id', $cycleId ?? 0);
            })
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->latest('updated_at')
            ->paginate(15);
    }

    private function myTasksQuery(): Builder
    {
        return Task::query()
            ->whereHas('project', fn (Builder $query) => $query->accessibleTo(auth()->user()))
            ->where(fn (Builder $query) => $query->where('assignee_id', auth()->id())->orWhere('reviewer_id', auth()->id()));
    }

    public function openTask(int $taskId): void
    {
        $task = Task::query()
            ->whereHas('project', fn (Builder $query) => $query->accessibleTo(auth()->user()))
            ->when($this->onlyAssignedToMe, fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('assignee_id', auth()->id())->orWhere('reviewer_id', auth()->id())))
            ->findOrFail($taskId);
        $this->authorize('view', $task);

        $this->modalProjectId = $task->project_id;
        $this->modalTaskId = $task->id;
        $this->taskModalOpen = true;
    }

    public function openCreateTask(): void
    {
        $project = $this->projects->firstWhere('id', ctype_digit($this->projectFilter) ? (int) $this->projectFilter : null)
            ?? $this->projects->first();
        abort_if($project === null, 404);
        $this->authorize('create', [Task::class, $project]);

        $this->modalProjectId = $project->id;
        $this->modalTaskId = null;
        $this->taskModalOpen = true;
    }

    public function closeTaskModal(): void
    {
        $this->taskModalOpen = false;
        $this->modalProjectId = null;
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
        unset($this->tasks, $this->workSummary);
    }

    #[On('task-modal-refresh')]
    public function refreshTaskList(): void
    {
        unset($this->tasks, $this->workSummary);
    }

    public function deleteTask(int $taskId): void
    {
        $task = Task::query()->whereHas('project', fn (Builder $query) => $query->accessibleTo(auth()->user()))->findOrFail($taskId);
        $this->authorize('delete', $task);
        $task->delete();

        $this->statusMessage = 'Task deleted.';
        unset($this->tasks, $this->workSummary);

        if ($this->tasks->isEmpty() && $this->getPage() > 1) {
            $this->previousPage();
            unset($this->tasks);
        }
    }
};
?>

<main class="mx-auto w-full max-w-7xl flex-1 px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-medium text-indigo-600 dark:text-indigo-400">{{ $onlyAssignedToMe ? 'Personal workspace' : 'Your workspace' }}</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $onlyAssignedToMe ? 'My Tasks' : 'Tasks' }}</h1>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $onlyAssignedToMe ? 'Your assigned work and review requests, across every project you can access.' : 'Search and filter tasks across your projects.' }}</p>
        </div>
        @if ($this->projects->isNotEmpty())
            <button type="button" wire:click="openCreateTask" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 disabled:opacity-60">
                <span aria-hidden="true">＋</span> New task
            </button>
        @endif
    </div>

    @if ($statusMessage)
        <p role="status" class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">{{ $statusMessage }}</p>
    @endif

    @if ($onlyAssignedToMe)
        <section class="mt-7 grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="My work summary">
            @foreach ([['Assigned to me', $this->workSummary['assigned'], 'All tasks you own'], ['Review requests', $this->workSummary['reviews'], 'Tasks waiting in review or QA'], ['Overdue', $this->workSummary['overdue'], 'Open work past its due date'], ['Due in 7 days', $this->workSummary['due_soon'], 'Open work due this week']] as [$label, $value, $hint])
                <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $label }}</p>
                    <p class="mt-2 text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ $value }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $hint }}</p>
                </article>
            @endforeach
        </section>

        <div class="mt-6 flex flex-wrap gap-2" role="group" aria-label="Filter work type">
            @foreach (['all' => 'All my work', 'assigned' => 'Assigned to me', 'reviewing' => 'Reviewing'] as $scope => $label)
                <button type="button" wire:click="$set('workScope', '{{ $scope }}')" @class(['rounded-lg px-3.5 py-2 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600', 'bg-indigo-600 text-white' => $workScope === $scope, 'border border-slate-300 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800' => $workScope !== $scope]) aria-pressed="{{ $workScope === $scope ? 'true' : 'false' }}">{{ $label }}</button>
            @endforeach
        </div>
    @endif

    <section class="mt-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900" aria-label="Task filters">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        <label class="sm:col-span-2 lg:col-span-1">
            <span class="sr-only">Search tasks</span>
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search tasks..." class="min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none placeholder:text-slate-400 focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
        </label>
        <label>
            <span class="sr-only">Filter by project</span>
            <select wire:model.live="projectFilter" class="min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 shadow-sm focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">
                <option value="all">All projects</option>
                @foreach ($this->projects as $project)
                    <option value="{{ $project->id }}">{{ $project->name }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span class="sr-only">Filter by status</span>
            <select wire:model.live="statusFilter" class="min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 shadow-sm focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">
                <option value="all">All statuses</option>
                @foreach ($this->statuses as $status)
                    <option value="{{ $status->id }}">{{ $status->project->name }} · {{ $status->name }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span class="sr-only">Filter by priority</span>
            <select wire:model.live="priorityFilter" class="min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 shadow-sm focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">
                <option value="all">All priorities</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
                <option value="medium">Medium</option>
                <option value="low">Low</option>
            </select>
        </label>
        <label>
            <span class="sr-only">Filter by type</span>
            <select wire:model.live="typeFilter" class="min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 shadow-sm focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">
                <option value="all">All types</option>
                <option value="task">Task</option>
                <option value="bug">Bug</option>
                <option value="feature">Feature</option>
            </select>
        </label>
        <label>
            <span class="sr-only">Filter by due date</span>
            <select wire:model.live="dueFilter" class="min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 shadow-sm focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">
                <option value="all">Any due date</option>
                <option value="today">Due today</option>
                <option value="next_7_days">Due in next 7 days</option>
                <option value="overdue">Overdue</option>
                <option value="upcoming">Upcoming</option>
                <option value="none">No due date</option>
            </select>
        </label>
        @if ($onlyAssignedToMe)
            <label>
                <span class="sr-only">Filter by sprint</span>
                <select wire:model.live="cycleFilter" class="min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 shadow-sm focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">
                    <option value="all">All sprints</option><option value="none">No sprint</option>
                    @foreach ($this->availableCycles as $cycle)<option value="{{ $cycle->id }}">{{ $cycle->project_id ? 'Project' : 'Team' }} · {{ $cycle->name }}</option>@endforeach
                </select>
            </label>
            <label class="flex min-h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">
                <input type="checkbox" wire:model.live="blockedOnly" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-600">
                Blocked only
            </label>
            <label>
                <span class="sr-only">Due from</span>
                <input type="date" wire:model.live="dueFrom" aria-label="Due from" class="min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">
            </label>
            <label>
                <span class="sr-only">Due to</span>
                <input type="date" wire:model.live="dueTo" aria-label="Due to" class="min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">
            </label>
        @endif
    </div>
    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">
        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $this->tasks->total() }} matching {{ $onlyAssignedToMe ? 'work items' : 'tasks' }}</p>
        <button type="button" wire:click="clearFilters" class="rounded-md px-2.5 py-1.5 text-sm font-medium text-indigo-600 hover:bg-indigo-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:text-indigo-400 dark:hover:bg-indigo-500/10">Clear filters</button>
    </div>
    </section>

    <p wire:loading.flex wire:target="search,statusFilter,priorityFilter,typeFilter,dueFilter,projectFilter,workScope,cycleFilter,dueFrom,dueTo,blockedOnly" role="status" class="mt-4 items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
        <span class="h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-indigo-600 dark:border-slate-700 dark:border-t-indigo-400" aria-hidden="true"></span>
        Updating tasks...
    </p>

    <div wire:loading.class="opacity-50" wire:loading.attr="aria-busy" wire:target="search,statusFilter,priorityFilter,typeFilter,dueFilter,projectFilter,workScope,cycleFilter,dueFrom,dueTo,blockedOnly" class="transition-opacity">
    @if ($this->tasks->isNotEmpty())
        <div class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <ul class="divide-y divide-slate-100 dark:divide-slate-800" aria-label="{{ $onlyAssignedToMe ? 'My tasks' : 'Tasks' }}">
                @foreach ($this->tasks as $task)
                    <li wire:key="task-{{ $task->id }}" class="flex flex-col gap-3 p-4 transition hover:bg-slate-50/70 sm:flex-row sm:items-center sm:justify-between sm:px-5 dark:hover:bg-slate-800/40">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button" wire:click="openTask({{ $task->id }})" class="max-w-full truncate text-left text-sm font-semibold text-slate-900 hover:text-indigo-600 focus:outline-none focus-visible:underline dark:text-white dark:hover:text-indigo-400">{{ $task->title }}</button>
                                <span class="text-xs text-slate-400">#{{ $task->id }}</span>
                            </div>
                            <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                                <a href="{{ route('projects.board', $task->project) }}" wire:navigate class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">{{ $task->project->name }}</a>
                                <span aria-hidden="true">·</span><span>{{ $task->status->name }}</span><span aria-hidden="true">·</span><span class="capitalize">{{ $task->type }}</span>
                                @if ($task->assignee_id === auth()->id())<span class="rounded bg-indigo-50 px-1.5 py-0.5 font-medium text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">Assigned to me</span>@endif
                                @if ($task->reviewer_id === auth()->id())<span class="rounded bg-violet-50 px-1.5 py-0.5 font-medium text-violet-700 dark:bg-violet-500/10 dark:text-violet-300">Reviewing</span>@endif
                                @if ($task->cycle && $this->availableCycles->contains('id', $task->cycle_id))<span>{{ $task->cycle->name }}</span>@endif
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                            <span @class([
                                'rounded-full px-2.5 py-1 text-xs font-medium capitalize',
                                'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300' => $task->priority === 'high',
                                'bg-fuchsia-50 text-fuchsia-800 dark:bg-fuchsia-500/10 dark:text-fuchsia-200' => $task->priority === 'urgent',
                                'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' => $task->priority === 'medium',
                                'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => $task->priority === 'low',
                            ])>{{ $task->priority }}</span>
                            <span @class(['min-w-24 text-right text-xs', 'font-semibold text-red-600 dark:text-red-400' => $task->due_date?->lt(today()) && ! $task->status?->is_complete, 'text-slate-500 dark:text-slate-400' => ! ($task->due_date?->lt(today()) && ! $task->status?->is_complete)])>{{ $task->due_date?->format('M j, Y') ?? 'No due date' }}</span>
                            <button type="button" wire:click="openTask({{ $task->id }})" class="rounded px-2 py-1 text-sm font-medium text-indigo-600 hover:bg-indigo-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:text-indigo-400 dark:hover:bg-indigo-500/10">Open</button>
                            <button type="button" wire:click="deleteTask({{ $task->id }})" wire:confirm="Delete {{ $task->title }}? This cannot be undone." wire:loading.attr="disabled" wire:target="deleteTask({{ $task->id }})" class="rounded px-2 py-1 text-sm font-medium text-red-600 hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 disabled:opacity-50 dark:text-red-400 dark:hover:bg-red-500/10"><span wire:loading.remove wire:target="deleteTask({{ $task->id }})">Delete</span><span wire:loading wire:target="deleteTask({{ $task->id }})">Deleting...</span></button>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="mt-6">{{ $this->tasks->links() }}</div>
    @elseif ($this->projects->isEmpty())
        <section class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Create a project first</h2>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Tasks live inside projects, so start by creating a project workspace.</p>
            <a href="{{ route('projects.create') }}" wire:navigate class="mt-5 inline-flex rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600">Create a project</a>
        </section>
    @else
        <section class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ $onlyAssignedToMe ? 'No matching work found' : 'No tasks found' }}</h2>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $onlyAssignedToMe ? 'Tasks assigned to you and review requests will appear here. Try another work view or clear your filters.' : 'Try clearing a filter or create a task in one of your projects.' }}</p>
            <button type="button" wire:click="clearFilters" class="mt-4 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Clear filters</button>
            @if ($this->projects->isNotEmpty())<button type="button" wire:click="openCreateTask" class="ml-2 mt-4 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600">Create task</button>@endif
        </section>
    @endif
    </div>
    @if ($taskModalOpen && $modalProjectId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-3 backdrop-blur-sm sm:p-6" x-data x-on:keydown.escape.window="$wire.closeTaskModal()" wire:key="task-modal-shell-{{ $modalProjectId }}-{{ $modalTaskId ?? 'new' }}">
            <section role="dialog" aria-modal="true" aria-label="{{ $modalTaskId ? 'Task details' : 'Create a task' }}" class="max-h-[calc(100vh-1.5rem)] w-full max-w-6xl overflow-y-auto rounded-2xl bg-white shadow-2xl ring-1 ring-black/10 dark:bg-slate-900 sm:max-h-[calc(100vh-3rem)]">
                @livewire('tasks.form', ['project' => $modalProjectId, 'task' => $modalTaskId, 'modal' => true], key('my-task-modal-'.$modalProjectId.'-'.($modalTaskId ?? 'new')))
            </section>
        </div>
    @endif
</main>
