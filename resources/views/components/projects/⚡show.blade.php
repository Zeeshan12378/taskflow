<?php

use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Cycle;
use App\Models\Task;
use App\Models\TaskActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Project · TaskFlow')] class extends Component
{
    #[Locked]
    public int $projectId;

    public string $cycleName = '';

    public string $cycleGoal = '';

    public string $cycleStartDate = '';

    public string $cycleEndDate = '';

    public array $cycleGoals = [];

    public array $rolloverTargets = [];

    public ?string $cycleStatusMessage = null;

    public string $cycleStatusType = 'success';

    public function mount(int $project): void
    {
        $record = Project::query()->accessibleTo(auth()->user())->findOrFail($project);
        $this->authorize('view', $record);
        $this->projectId = $record->id;
        $this->cycleGoals = $record->cycles()->where('status', 'planned')->pluck('goal', 'id')->map(fn ($goal) => $goal ?? '')->all();
    }

    #[Computed]
    public function project(): Project
    {
        return Project::query()->accessibleTo(auth()->user())
            ->with(['statuses'])
            ->findOrFail($this->projectId);
    }

    /**
     * @return array{completed: int, updated: int, created: int, dueSoon: int, unassigned: int, total: int}
     */
    #[Computed]
    public function summaryMetrics(): array
    {
        $activityQuery = TaskActivity::query()
            ->whereIn('task_id', $this->project->tasks()->select('id'))
            ->where('created_at', '>=', now()->subDays(7));
        $completedStatusNames = $this->project->statuses->where('is_complete')->pluck('name')->all();
        $tasks = $this->project->tasks();

        return [
            'completed' => $completedStatusNames === [] ? 0 : (clone $activityQuery)
                ->where('action', 'updated')
                ->whereIn('details->project_status_id->to', $completedStatusNames)
                ->distinct('task_id')
                ->count('task_id'),
            'updated' => (clone $activityQuery)->where('action', 'updated')->count(),
            'created' => (clone $activityQuery)->where('action', 'created')->count(),
            'dueSoon' => (clone $tasks)
                ->whereBetween('due_date', [today(), today()->addDays(7)])
                ->whereHas('status', fn (Builder $query) => $query->where('is_complete', false))
                ->count(),
            'unassigned' => (clone $tasks)
                ->whereNull('assignee_id')
                ->whereHas('status', fn (Builder $query) => $query->where('is_complete', false))
                ->count(),
            'total' => (clone $tasks)->count(),
        ];
    }

    #[Computed]
    public function summaryStatuses(): Collection
    {
        $counts = $this->project->tasks()
            ->selectRaw('project_status_id, count(*) as aggregate')
            ->groupBy('project_status_id')
            ->pluck('aggregate', 'project_status_id');
        $total = (int) $counts->sum();

        return $this->project->statuses->map(fn (ProjectStatus $status): array => [
            'name' => $status->name,
            'count' => (int) $counts->get($status->id, 0),
            'color' => $status->accentHex(),
            'percentage' => $total === 0 ? 0 : (int) round(((int) $counts->get($status->id, 0)) / $total * 100),
        ]);
    }

    #[Computed]
    public function summaryPriorities(): Collection
    {
        $counts = $this->project->tasks()
            ->selectRaw('priority, count(*) as aggregate')
            ->groupBy('priority')
            ->pluck('aggregate', 'priority');
        $maximum = (int) $counts->max();

        return collect(Task::PRIORITIES)->map(fn (string $priority): array => [
            'name' => ucfirst($priority),
            'count' => (int) $counts->get($priority, 0),
            'percentage' => $maximum === 0 ? 0 : (int) round(((int) $counts->get($priority, 0)) / $maximum * 100),
            'color' => match ($priority) {
                'urgent' => 'bg-fuchsia-500',
                'high' => 'bg-red-500',
                'medium' => 'bg-amber-500',
                default => 'bg-blue-500',
            },
        ]);
    }

    #[Computed]
    public function summaryTypes(): Collection
    {
        $counts = $this->project->tasks()
            ->selectRaw('type, count(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type');
        $maximum = (int) $counts->max();

        return collect(Task::TYPES)->map(fn (string $type): array => [
            'name' => ucfirst($type),
            'count' => (int) $counts->get($type, 0),
            'percentage' => $maximum === 0 ? 0 : (int) round(((int) $counts->get($type, 0)) / $maximum * 100),
        ]);
    }

    #[Computed]
    public function summaryWorkload(): Collection
    {
        return Task::query()
            ->where('project_id', $this->projectId)
            ->whereNotNull('assignee_id')
            ->whereHas('status', fn (Builder $query) => $query->where('is_complete', false))
            ->selectRaw('assignee_id, count(*) as aggregate')
            ->groupBy('assignee_id')
            ->with('assignee:id,name')
            ->orderByDesc('aggregate')
            ->limit(6)
            ->get();
    }

    /**
     * @return array{total: int, completed: int, remaining: int, percentage: int}
     */
    #[Computed]
    public function summaryEstimateProgress(): array
    {
        $tasks = $this->project->tasks();
        $total = (int) (clone $tasks)->sum('story_points');
        $completed = (int) (clone $tasks)
            ->whereHas('status', fn (Builder $query) => $query->where('is_complete', true))
            ->sum('story_points');

        return [
            'total' => $total,
            'completed' => $completed,
            'remaining' => max(0, $total - $completed),
            'percentage' => $total === 0 ? 0 : (int) round($completed / $total * 100),
        ];
    }

    #[Computed]
    public function recentActivity()
    {
        $taskIds = $this->project->tasks()->select('id');

        return TaskActivity::query()->whereIn('task_id', $taskIds)->with(['actor:id,name', 'task:id,title'])->latest()->limit(8)->get();
    }

    #[Computed]
    public function cycles(): Collection
    {
        return $this->project->cycles()
            ->withCount([
                'tasks',
                'tasks as completed_tasks_count' => fn ($query) => $query->whereHas('status', fn (Builder $status) => $status->where('is_complete', true)),
                'tasks as open_tasks_count' => fn ($query) => $query->whereHas('status', fn (Builder $status) => $status->where('is_complete', false)),
            ])
            ->withSum('tasks', 'story_points')
            ->withSum(['tasks as completed_story_points' => fn ($query) => $query->whereHas('status', fn (Builder $status) => $status->where('is_complete', true))], 'story_points')
            ->with(['snapshots' => fn ($query) => $query->orderBy('snapshot_date')->orderBy('created_at')])
            ->get();
    }

    /**
     * @return array{count: int, average: float}
     */
    #[Computed]
    public function cycleVelocity(): array
    {
        $deliveredPoints = $this->cycles
            ->where('status', 'completed')
            ->map(fn (Cycle $cycle) => $cycle->snapshots->firstWhere('snapshot_type', 'completion')?->completed_story_points)
            ->filter(fn (?int $points): bool => $points !== null);

        return [
            'count' => $deliveredPoints->count(),
            'average' => $deliveredPoints->isEmpty() ? 0 : round($deliveredPoints->avg(), 1),
        ];
    }

    public function createCycle(): void
    {
        $project = $this->project;
        $this->authorize('update', $project);
        $this->cycleName = trim($this->cycleName);
        $validated = $this->validate([
            'cycleName' => ['required', 'string', 'max:120', Rule::unique('cycles', 'name')->where('project_id', $project->id)],
            'cycleGoal' => ['nullable', 'string', 'max:500'],
            'cycleStartDate' => ['required', 'date'],
            'cycleEndDate' => ['required', 'date', 'after_or_equal:cycleStartDate'],
        ]);

        $cycle = DB::transaction(function () use ($project, $validated): ?Cycle {
            Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
            $overlaps = $project->cycles()
                ->whereDate('start_date', '<=', $validated['cycleEndDate'])
                ->whereDate('end_date', '>=', $validated['cycleStartDate'])
                ->exists();

            if ($overlaps) {
                return null;
            }

            return $project->cycles()->create([
                'name' => $validated['cycleName'],
                'goal' => filled($validated['cycleGoal'] ?? null) ? trim($validated['cycleGoal']) : null,
                'start_date' => $validated['cycleStartDate'],
                'end_date' => $validated['cycleEndDate'],
            ]);
        });

        if ($cycle === null) {
            $this->addError('cycleStartDate', 'Sprint dates cannot overlap another sprint for this project.');

            return;
        }

        $this->cycleGoals[$cycle->id] = $cycle->goal ?? '';
        $this->reset('cycleName', 'cycleGoal', 'cycleStartDate', 'cycleEndDate');
        $this->cycleStatusMessage = 'Project sprint planned.';
        $this->cycleStatusType = 'success';
        unset($this->cycles, $this->cycleVelocity);
    }

    public function startCycle(int $cycleId): void
    {
        $project = $this->project;
        $this->authorize('update', $project);
        $cycle = $project->cycles()->findOrFail($cycleId);
        abort_unless($cycle->status === 'planned', 422);

        $goal = trim((string) ($this->cycleGoals[$cycleId] ?? $cycle->goal));

        if ($goal === '') {
            $this->addError('cycleGoals.'.$cycleId, 'Add a sprint goal before starting this sprint.');

            return;
        }

        $this->cycleGoals[$cycleId] = $goal;
        $this->validate(['cycleGoals.'.$cycleId => ['required', 'string', 'max:500']]);

        if ($cycle->end_date->lt(today())) {
            $this->cycleStatusMessage = 'This sprint has already passed. Update its dates before starting it.';
            $this->cycleStatusType = 'error';

            return;
        }

        $started = DB::transaction(function () use ($project, $cycle, $goal): bool {
            Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();

            if ($project->cycles()->where('status', 'active')->exists()) {
                return false;
            }

            $cycle->update(['goal' => $goal, 'status' => 'active', 'started_at' => now()]);
            $cycle->recordSnapshot(today(), 'start');

            return true;
        });

        if (! $started) {
            $this->cycleStatusMessage = 'Complete the active project sprint before starting another one.';
            $this->cycleStatusType = 'error';

            return;
        }

        $this->cycleStatusMessage = $cycle->name.' started.';
        $this->cycleStatusType = 'success';
        unset($this->cycles, $this->cycleVelocity);
    }

    public function completeCycle(int $cycleId): void
    {
        $project = $this->project;
        $this->authorize('update', $project);
        $cycle = $project->cycles()->findOrFail($cycleId);
        abort_unless($cycle->status === 'active', 422);

        $target = (string) ($this->rolloverTargets[$cycleId] ?? 'backlog');
        $allowedTargets = ['backlog', ...$project->cycles()
            ->where('status', 'planned')
            ->whereDate('end_date', '>=', today())
            ->where('id', '!=', $cycle->id)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all()];

        if (! in_array($target, $allowedTargets, true)) {
            $this->addError('rolloverTargets.'.$cycleId, 'Choose the backlog or an upcoming sprint from this project.');

            return;
        }

        $openTaskCount = 0;
        DB::transaction(function () use ($cycle, $target, &$openTaskCount): void {
            $cycle->recordSnapshot(today(), 'completion');
            $openTasks = $cycle->tasks()
                ->whereHas('status', fn (Builder $status) => $status->where('is_complete', false))
                ->get();
            $openTaskCount = $openTasks->count();

            foreach ($openTasks as $task) {
                $task->update(['cycle_id' => $target === 'backlog' ? null : (int) $target]);
            }

            $cycle->update(['status' => 'completed', 'completed_at' => now()]);
        });

        $this->cycleStatusMessage = $openTaskCount === 0
            ? $cycle->name.' completed.'
            : $cycle->name.' completed. '.$openTaskCount.' open '.Str::plural('task', $openTaskCount).' moved to '.($target === 'backlog' ? 'the backlog' : 'the selected sprint').'.';
        $this->cycleStatusType = 'success';
        unset($this->cycles, $this->cycleVelocity);
    }
};
?>

<main class="mx-auto w-full max-w-[100rem] flex-1 px-4 py-7 sm:px-6 lg:px-8 lg:py-9">
    <x-projects.tabs :project="$this->project" active="overview" />

    @if (session()->has('status'))
        <p role="status" class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">{{ session('status') }}</p>
    @endif

    <section class="mt-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0"><div class="flex flex-wrap items-center gap-3"><h1 class="truncate text-2xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-3xl">{{ $this->project->name }}</h1><span @class(['inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium capitalize', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' => $this->project->status === 'active', 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => $this->project->status === 'archived'])>{{ $this->project->status }}</span></div><p class="mt-2 max-w-3xl truncate text-sm text-slate-500 dark:text-slate-400">{{ \App\Support\RichText::plainText($this->project->description) ?: 'Project health and delivery overview.' }}</p></div>
        <a href="{{ route('projects.board', $this->project) }}" wire:navigate class="inline-flex min-h-10 shrink-0 items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600">Open board</a>
    </section>

    @php
        $statusSegments = [];
        $statusOffset = 0;
        $summaryTotal = $this->summaryMetrics['total'];
        foreach ($this->summaryStatuses as $summaryStatus) {
            $segmentAngle = $summaryTotal === 0 ? 0 : $summaryStatus['count'] / $summaryTotal * 360;
            if ($segmentAngle > 0) {
                $statusSegments[] = $summaryStatus['color'].' '.$statusOffset.'deg '.($statusOffset + $segmentAngle).'deg';
                $statusOffset += $segmentAngle;
            }
        }
        $statusChartBackground = $statusSegments === []
            ? '#e2e8f0'
            : 'conic-gradient('.implode(', ', $statusSegments).')';
        $largestWorkload = (int) ($this->summaryWorkload->first()?->aggregate ?? 0);
    @endphp

    <section class="mt-6" aria-label="Project summary">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['Completed', $this->summaryMetrics['completed'], 'in the last 7 days', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300', '✓'],
                ['Updated', $this->summaryMetrics['updated'], 'in the last 7 days', 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300', '↻'],
                ['Created', $this->summaryMetrics['created'], 'in the last 7 days', 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300', '+'],
                ['Due soon', $this->summaryMetrics['dueSoon'], 'in the next 7 days', 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300', '◷'],
            ] as [$label, $value, $hint, $iconStyle, $icon])
                <article class="flex min-h-20 items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <span aria-hidden="true" @class(['flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-lg font-bold', $iconStyle])>{{ $icon }}</span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $value }} {{ $label }}</p>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $hint }}</p>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-4 grid gap-4 xl:grid-cols-2">
            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 dark:border-slate-800 dark:bg-slate-900">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Status overview</h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">A snapshot of work across this project.</p>
                </div>
                <div class="mt-4 flex min-h-44 items-center gap-5">
                    <div class="relative flex h-36 w-36 shrink-0 items-center justify-center rounded-full" style="background: {{ $statusChartBackground }}">
                        <div class="flex h-24 w-24 flex-col items-center justify-center rounded-full bg-white text-center dark:bg-slate-900">
                            <span class="text-2xl font-bold tabular-nums text-slate-900 dark:text-white">{{ $summaryTotal }}</span>
                            <span class="text-[10px] text-slate-500 dark:text-slate-400">total tasks</span>
                        </div>
                    </div>
                    <ul class="max-h-40 min-w-0 flex-1 space-y-2 overflow-y-auto pr-1">
                        @forelse ($this->summaryStatuses as $summaryStatus)
                            <li wire:key="overview-status-{{ $loop->index }}" class="flex items-center justify-between gap-3 text-xs">
                                <span class="flex min-w-0 items-center gap-2 text-slate-700 dark:text-slate-300"><span class="h-2.5 w-2.5 shrink-0 rounded-sm" style="background-color: {{ $summaryStatus['color'] }}"></span><span class="truncate">{{ $summaryStatus['name'] }}</span></span>
                                <span class="shrink-0 tabular-nums text-slate-500 dark:text-slate-400">{{ $summaryStatus['count'] }}</span>
                            </li>
                        @empty
                            <li class="text-xs text-slate-500 dark:text-slate-400">No workflow columns configured.</li>
                        @endforelse
                    </ul>
                </div>
            </article>

            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Recent activity</h2>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">The latest updates across this project.</p>
                    </div>
                    <span class="shrink-0 rounded-full bg-slate-100 px-2 py-1 text-[10px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">Latest 8</span>
                </div>
                <ol class="mt-3 max-h-44 space-y-3 overflow-y-auto pr-1">
                    @forelse ($this->recentActivity as $activity)
                        <li wire:key="overview-activity-{{ $activity->id }}" class="border-l-2 border-indigo-200 pl-3 dark:border-indigo-900">
                            <p class="line-clamp-2 text-xs leading-5 text-slate-700 dark:text-slate-300"><span class="font-semibold text-slate-900 dark:text-white">{{ $activity->actor?->name ?? 'A teammate' }}</span> {{ $activity->summary() }} <span class="text-slate-500">on {{ $activity->task?->title ?? 'a task' }}</span></p>
                            <time class="mt-0.5 block text-[10px] text-slate-400">{{ $activity->created_at->diffForHumans() }}</time>
                        </li>
                    @empty
                        <li class="py-5 text-center text-xs text-slate-500 dark:text-slate-400">Project activity will appear here as work progresses.</li>
                    @endforelse
                </ol>
            </article>

            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Priority breakdown</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">See how work is prioritized.</p>
                <ul class="mt-4 space-y-3">
                    @foreach ($this->summaryPriorities as $priority)
                        <li wire:key="overview-priority-{{ $priority['name'] }}" class="grid grid-cols-[4.5rem_minmax(0,1fr)_2rem] items-center gap-3 text-xs">
                            <span class="text-slate-600 dark:text-slate-400">{{ $priority['name'] }}</span>
                            <span class="h-3 overflow-hidden rounded-sm bg-slate-100 dark:bg-slate-800"><span class="{{ $priority['color'] }} block h-full rounded-sm" style="width: {{ $priority['percentage'] }}%"></span></span>
                            <span class="text-right tabular-nums text-slate-500 dark:text-slate-400">{{ $priority['count'] }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-3 text-right text-[10px] text-slate-400">Scale is relative to the most common priority.</p>
            </article>

            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Types of work</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Distribution of tasks by type.</p>
                <ul class="mt-4 space-y-3">
                    @foreach ($this->summaryTypes as $type)
                        <li wire:key="overview-type-{{ $type['name'] }}" class="grid grid-cols-[4.5rem_minmax(0,1fr)_2rem] items-center gap-3 text-xs">
                            <span class="text-slate-600 dark:text-slate-400">{{ $type['name'] }}</span>
                            <span class="h-3 overflow-hidden rounded-sm bg-slate-100 dark:bg-slate-800"><span class="block h-full rounded-sm bg-indigo-500" style="width: {{ $type['percentage'] }}%"></span></span>
                            <span class="text-right tabular-nums text-slate-500 dark:text-slate-400">{{ $type['count'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </article>

            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Team workload</h2>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Open tasks assigned to each person.</p>
                    </div>
                    <span class="shrink-0 text-xs tabular-nums text-slate-500 dark:text-slate-400">{{ $this->summaryMetrics['unassigned'] }} unassigned</span>
                </div>
                <ul class="mt-4 space-y-3">
                    @forelse ($this->summaryWorkload as $assigneeWorkload)
                        <li wire:key="overview-workload-{{ $assigneeWorkload->assignee_id }}" class="grid grid-cols-[minmax(0,7rem)_minmax(0,1fr)_2rem] items-center gap-3 text-xs">
                            <span class="truncate text-slate-600 dark:text-slate-400">{{ $assigneeWorkload->assignee?->name ?? 'Unknown assignee' }}</span>
                            <span class="h-3 overflow-hidden rounded-sm bg-slate-100 dark:bg-slate-800"><span class="block h-full rounded-sm bg-slate-400" style="width: {{ $largestWorkload === 0 ? 0 : (int) round($assigneeWorkload->aggregate / $largestWorkload * 100) }}%"></span></span>
                            <span class="text-right tabular-nums text-slate-500 dark:text-slate-400">{{ $assigneeWorkload->aggregate }}</span>
                        </li>
                    @empty
                        <li class="py-4 text-center text-xs text-slate-500 dark:text-slate-400">No open tasks are assigned yet.</li>
                    @endforelse
                </ul>
            </article>

            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Story point progress</h2>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Estimated effort in completed workflow columns.</p>
                    </div>
                    <span class="shrink-0 text-xs font-semibold tabular-nums text-slate-700 dark:text-slate-300">{{ $this->summaryEstimateProgress['percentage'] }}%</span>
                </div>
                <div class="mt-5 h-3 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                    <div class="h-full rounded-full bg-emerald-500 transition-all" style="width: {{ $this->summaryEstimateProgress['percentage'] }}%"></div>
                </div>
                <div class="mt-3 flex items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400">
                    <span>{{ $this->summaryEstimateProgress['completed'] }} completed points</span>
                    <span>{{ $this->summaryEstimateProgress['remaining'] }} remaining of {{ $this->summaryEstimateProgress['total'] }}</span>
                </div>
                @if ($this->summaryEstimateProgress['total'] === 0)
                    <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">Add story point estimates to tasks to track effort progress.</p>
                @endif
            </article>
        </div>
    </section>

    <section class="mt-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900" aria-labelledby="project-sprints-heading">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 id="project-sprints-heading" class="text-lg font-semibold text-slate-900 dark:text-white">Project sprints</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Plan and review sprints owned by this project, independently of team sprints.</p>
            </div>
            <div class="shrink-0 rounded-lg bg-indigo-50 px-3 py-2 text-sm dark:bg-indigo-500/10">
                <span class="font-semibold text-indigo-700 dark:text-indigo-300">{{ $this->cycleVelocity['average'] }}</span>
                <span class="text-indigo-700 dark:text-indigo-300"> average delivered points</span>
                <span class="text-xs text-indigo-600 dark:text-indigo-400"> · {{ $this->cycleVelocity['count'] }} completed</span>
            </div>
        </div>

        @if ($cycleStatusMessage)
            <p role="status" @class(['mt-4 rounded-lg border px-3 py-2 text-sm', 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300' => $cycleStatusType === 'success', 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300' => $cycleStatusType === 'error'])>{{ $cycleStatusMessage }}</p>
        @endif

        @can('update', $this->project)
            <form wire:submit="createCycle" class="mt-5 grid gap-3 rounded-lg bg-slate-50 p-4 sm:grid-cols-2 lg:grid-cols-5 dark:bg-slate-950">
                <div>
                    <label for="project-cycle-name" class="block text-xs font-medium text-slate-700 dark:text-slate-300">Sprint name</label>
                    <input id="project-cycle-name" wire:model="cycleName" maxlength="120" required placeholder="Sprint 1" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                    @error('cycleName') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="project-cycle-goal" class="block text-xs font-medium text-slate-700 dark:text-slate-300">Sprint goal <span class="font-normal text-slate-500">(required to start)</span></label>
                    <input id="project-cycle-goal" wire:model="cycleGoal" maxlength="500" placeholder="What outcome will this sprint deliver?" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                    @error('cycleGoal') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="project-cycle-start" class="block text-xs font-medium text-slate-700 dark:text-slate-300">Starts</label>
                    <input id="project-cycle-start" type="date" wire:model="cycleStartDate" required class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                    @error('cycleStartDate') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="project-cycle-end" class="block text-xs font-medium text-slate-700 dark:text-slate-300">Ends</label>
                    <input id="project-cycle-end" type="date" wire:model="cycleEndDate" required class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                    @error('cycleEndDate') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div class="flex items-end">
                    <button type="submit" wire:loading.attr="disabled" wire:target="createCycle" class="min-h-10 w-full rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60"><span wire:loading.remove wire:target="createCycle">Plan sprint</span><span wire:loading wire:target="createCycle">Planning...</span></button>
                </div>
            </form>
        @endif

        @if ($this->cycles->isNotEmpty())
            <ul class="mt-5 divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($this->cycles as $cycle)
                    <li wire:key="project-cycle-{{ $cycle->id }}" class="space-y-3 py-4">
                        @php($completion = $cycle->tasks_count > 0 ? (int) round($cycle->completed_tasks_count / $cycle->tasks_count * 100) : 0)
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $cycle->name }}</p>
                                    <span @class(['rounded-full px-2.5 py-1 text-xs font-semibold capitalize', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' => $cycle->status === 'active', 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300' => $cycle->status === 'planned', 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => $cycle->status === 'completed'])>{{ $cycle->status === 'planned' ? ($cycle->start_date->isFuture() ? 'upcoming' : 'planned') : ($cycle->status === 'active' ? 'active' : 'completed') }}</span>
                                </div>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $cycle->start_date->format('M j, Y') }} – {{ $cycle->end_date->format('M j, Y') }} · {{ $cycle->tasks_count }} {{ Str::plural('task', $cycle->tasks_count) }} · {{ $cycle->completed_tasks_count }} complete · {{ $cycle->open_tasks_count }} open</p>
                                @if ($cycle->goal)
                                    <p class="mt-2 text-sm text-slate-700 dark:text-slate-300"><span class="font-medium">Goal:</span> {{ $cycle->goal }}</p>
                                @elseif ($cycle->status === 'planned')
                                    <p class="mt-2 text-xs text-amber-700 dark:text-amber-300">Set a sprint goal before starting.</p>
                                @endif
                                <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800" role="progressbar" aria-label="{{ $cycle->name }} task completion" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $completion }}"><div class="h-full rounded-full bg-emerald-500 transition-all" style="width: {{ $completion }}%"></div></div>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $completion }}% complete · {{ $cycle->tasks_sum_story_points ?? 0 }} planned points · {{ $cycle->completed_story_points ?? 0 }} completed points</p>
                            </div>

                            @can('update', $this->project)
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($cycle->status === 'planned')
                                        <input aria-label="Goal for {{ $cycle->name }}" wire:model="cycleGoals.{{ $cycle->id }}" maxlength="500" placeholder="Sprint goal" @error('cycleGoals.'.$cycle->id) aria-invalid="true" @enderror class="min-h-9 min-w-48 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                        <button type="button" wire:click="startCycle({{ $cycle->id }})" class="min-h-9 rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-700">Start sprint</button>
                                        @error('cycleGoals.'.$cycle->id) <p class="w-full text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                    @elseif ($cycle->status === 'active')
                                        <select aria-label="Move open tasks from {{ $cycle->name }} to" wire:model="rolloverTargets.{{ $cycle->id }}" class="min-h-9 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                            <option value="backlog">Backlog</option>
                                            @foreach ($this->cycles->where('status', 'planned')->where('end_date', '>=', today()) as $nextCycle)
                                                @if ($nextCycle->id !== $cycle->id)<option value="{{ $nextCycle->id }}">{{ $nextCycle->name }}</option>@endif
                                            @endforeach
                                        </select>
                                        <button type="button" wire:click="completeCycle({{ $cycle->id }})" wire:confirm="Complete {{ $cycle->name }} and move its open tasks to the selected destination?" class="min-h-9 rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Complete sprint</button>
                                        @error('rolloverTargets.'.$cycle->id) <p class="w-full text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                    @endif
                                </div>
                            @endcan
                        </div>

                        @if ($cycle->status === 'completed' && $cycle->snapshots->isNotEmpty())
                            @php($finalSnapshot = $cycle->snapshots->firstWhere('snapshot_type', 'completion') ?? $cycle->snapshots->last())
                            <details class="rounded-lg border border-slate-200 dark:border-slate-700">
                                <summary class="cursor-pointer px-3 py-2 text-sm font-semibold text-slate-700 dark:text-slate-200">Sprint report · {{ $finalSnapshot->completed_story_points }} points delivered</summary>
                                <div class="overflow-x-auto border-t border-slate-200 dark:border-slate-700">
                                    <table class="w-full min-w-[38rem] text-left text-xs">
                                        <thead class="bg-slate-50 text-slate-500 dark:bg-slate-950 dark:text-slate-400"><tr><th class="px-3 py-2 font-medium">Date</th><th class="px-3 py-2 font-medium">Scope (points)</th><th class="px-3 py-2 font-medium">Completed (points)</th><th class="px-3 py-2 font-medium">Remaining</th><th class="px-3 py-2 font-medium">Tasks complete</th></tr></thead>
                                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                            @php($previousSnapshot = null)
                                            @foreach ($cycle->snapshots as $snapshot)
                                                @php($scopeChange = $previousSnapshot ? $snapshot->total_story_points - $previousSnapshot->total_story_points : null)
                                                <tr>
                                                    <td class="whitespace-nowrap px-3 py-2 text-slate-700 dark:text-slate-300">{{ $snapshot->snapshot_date->format('M j') }} <span class="text-slate-500">{{ $snapshot->snapshot_type === 'start' ? '· start' : ($snapshot->snapshot_type === 'completion' ? '· final' : '') }}</span></td>
                                                    <td class="px-3 py-2 text-slate-700 dark:text-slate-300">{{ $snapshot->total_story_points }} @if ($scopeChange !== null && $scopeChange !== 0)<span class="{{ $scopeChange > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">({{ $scopeChange > 0 ? '+' : '' }}{{ $scopeChange }})</span>@endif</td>
                                                    <td class="px-3 py-2 text-slate-700 dark:text-slate-300">{{ $snapshot->completed_story_points }}</td>
                                                    <td class="px-3 py-2 text-slate-700 dark:text-slate-300">{{ max(0, $snapshot->total_story_points - $snapshot->completed_story_points) }}</td>
                                                    <td class="px-3 py-2 text-slate-700 dark:text-slate-300">{{ $snapshot->completed_tasks }} / {{ $snapshot->total_tasks }}</td>
                                                </tr>
                                                @php($previousSnapshot = $snapshot)
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </details>
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            <p class="mt-5 rounded-lg border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">No project sprints planned yet.</p>
        @endif

        @if ($this->cycleVelocity['count'] > 0)
            <div class="mt-5 border-t border-slate-200 pt-4 dark:border-slate-800">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Historical velocity</h3>
                <ul class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($this->cycles->where('status', 'completed')->sortByDesc('completed_at')->take(6) as $completedCycle)
                        @php($completionSnapshot = $completedCycle->snapshots->firstWhere('snapshot_type', 'completion'))
                        @if ($completionSnapshot)
                            <li wire:key="project-velocity-{{ $completedCycle->id }}" class="flex items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2 text-sm dark:bg-slate-950">
                                <span class="truncate text-slate-700 dark:text-slate-300">{{ $completedCycle->name }}</span>
                                <span class="shrink-0 font-semibold tabular-nums text-slate-900 dark:text-white">{{ $completionSnapshot->completed_story_points }} pts</span>
                            </li>
                        @endif
                    @endforeach
                </ul>
            </div>
        @endif
    </section>
</main>
