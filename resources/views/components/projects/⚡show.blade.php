<?php

use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Task;
use App\Models\TaskActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Project · TaskFlow')] class extends Component
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
</main>
