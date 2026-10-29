<?php

use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Project reports · TaskFlow')] class extends Component
{
    #[Locked]
    public int $projectId;

    public string $period = '30';

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
            ->with('statuses')
            ->findOrFail($this->projectId);
    }

    /**
     * @return array{completed: int, updated: int, created: int, dueSoon: int}
     */
    #[Computed]
    public function metrics(): array
    {
        $activity = $this->activityInPeriod();

        return [
            'completed' => $this->completedActivities->count(),
            'updated' => (clone $activity)->where('action', 'updated')->count(),
            'created' => (clone $activity)->where('action', 'created')->count(),
            'dueSoon' => $this->project->tasks()
                ->whereBetween('due_date', [today(), today()->addDays(7)])
                ->whereHas('status', fn (Builder $query) => $query->where('is_complete', false))
                ->count(),
        ];
    }

    #[Computed]
    public function statusDistribution(): array
    {
        $counts = $this->project->tasks()
            ->selectRaw('project_status_id, count(*) as aggregate')
            ->groupBy('project_status_id')
            ->pluck('aggregate', 'project_status_id');

        $items = $this->project->statuses->map(fn (ProjectStatus $status): array => [
            'label' => $status->name,
            'count' => (int) $counts->get($status->id, 0),
            'color' => $status->accentHex(),
        ]);

        return $this->donutData($items);
    }

    #[Computed]
    public function typeDistribution(): array
    {
        $counts = $this->project->tasks()
            ->selectRaw('type, count(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type');
        $colors = ['task' => '#3b82f6', 'bug' => '#ef4444', 'feature' => '#a855f7'];

        $items = collect(Task::TYPES)->map(fn (string $type): array => [
            'label' => ucfirst($type),
            'count' => (int) $counts->get($type, 0),
            'color' => $colors[$type],
        ]);

        return $this->donutData($items);
    }

    #[Computed]
    public function assigneeDistribution(): array
    {
        $counts = $this->project->tasks()
            ->selectRaw('assignee_id, count(*) as aggregate')
            ->groupBy('assignee_id')
            ->pluck('aggregate', 'assignee_id');
        $assigneeIds = $counts->keys()->filter()->map(fn ($id): int => (int) $id);
        $assignees = User::query()->whereKey($assigneeIds)->get(['id', 'name'])->keyBy('id');
        $colors = ['#3b82f6', '#22c55e', '#a855f7', '#f97316', '#ec4899', '#06b6d4', '#eab308'];
        $items = collect();

        foreach ($counts as $assigneeId => $count) {
            $items->push([
                'label' => $assigneeId === '' ? 'Unassigned' : ($assignees->get((int) $assigneeId)?->name ?? 'Former member'),
                'count' => (int) $count,
                'color' => $assigneeId === '' ? '#94a3b8' : $colors[$items->count() % count($colors)],
            ]);
        }

        return $this->donutData($items);
    }

    #[Computed]
    public function completedActivities(): Collection
    {
        $completeStatuses = $this->project->statuses->where('is_complete')->pluck('name')->all();
        if ($completeStatuses === []) {
            return collect();
        }

        return $this->activityInPeriod()
            ->where('action', 'updated')
            ->whereNotNull('details->project_status_id->to')
            ->with('task:id,created_at')
            ->orderBy('created_at')
            ->get(['id', 'task_id', 'created_at', 'details'])
            ->filter(fn (TaskActivity $activity): bool => in_array(
                $activity->details['project_status_id']['to'] ?? null,
                $completeStatuses,
                true,
            ))
            ->unique('task_id')
            ->values();
    }

    /**
     * @return Collection<int, array{label: string, created: int, completed: int, leadTime: ?float, cycleTime: ?float, x: float, createdHeight: float, completedHeight: float, leadY: ?float, cycleY: ?float}>
     */
    #[Computed]
    public function timeline(): Collection
    {
        $start = $this->periodStart();
        $createdByDay = $this->project->tasks()
            ->whereBetween('created_at', [$start, now()])
            ->selectRaw('date(created_at) as report_date, count(*) as aggregate')
            ->groupBy('report_date')
            ->pluck('aggregate', 'report_date');
        $completedByDay = $this->completedActivities
            ->groupBy(fn (TaskActivity $activity): string => $activity->created_at->toDateString());
        $timings = $this->completedTaskTimings;
        $stepDays = $this->periodDays() === 90 ? 7 : 1;
        $buckets = collect();

        for ($offset = 0; $offset < $this->periodDays(); $offset += $stepDays) {
            $bucketStart = $start->copy()->addDays($offset);
            $bucketEnd = $bucketStart->copy()->addDays($stepDays - 1)->min(today());
            $dates = collect();

            for ($day = $bucketStart->copy(); $day->lte($bucketEnd); $day->addDay()) {
                $dates->push($day->toDateString());
            }

            $bucketTimings = $timings->filter(fn (array $timing): bool => in_array($timing['date'], $dates->all(), true));
            $buckets->push([
                'label' => $stepDays === 1 ? $bucketStart->format($this->periodDays() === 7 ? 'D' : 'M j') : $bucketStart->format('M j'),
                'created' => (int) $dates->sum(fn (string $date): int => (int) $createdByDay->get($date, 0)),
                'completed' => (int) $dates->sum(fn (string $date): int => $completedByDay->get($date, collect())->count()),
                'leadTime' => $bucketTimings->isEmpty() ? null : round($bucketTimings->avg('leadTime'), 1),
                'cycleTime' => $bucketTimings->isEmpty() ? null : round($bucketTimings->avg('cycleTime'), 1),
            ]);
        }

        $maximumCount = max(1, (int) $buckets->max(fn (array $bucket): int => max($bucket['created'], $bucket['completed'])));
        $maximumDays = max(1.0, (float) $buckets->max(fn (array $bucket): float => max($bucket['leadTime'] ?? 0, $bucket['cycleTime'] ?? 0)));
        $lastIndex = max(1, $buckets->count() - 1);

        return $buckets->values()->map(function (array $bucket, int $index) use ($maximumCount, $maximumDays, $lastIndex): array {
            $bucket['x'] = round($index / $lastIndex * 620 + 10, 1);
            $bucket['createdHeight'] = round($bucket['created'] / $maximumCount * 100, 1);
            $bucket['completedHeight'] = round($bucket['completed'] / $maximumCount * 100, 1);
            $bucket['leadY'] = $bucket['leadTime'] === null ? null : round(190 - $bucket['leadTime'] / $maximumDays * 170, 1);
            $bucket['cycleY'] = $bucket['cycleTime'] === null ? null : round(190 - $bucket['cycleTime'] / $maximumDays * 170, 1);

            return $bucket;
        });
    }

    /**
     * @return array{leadTime: ?float, cycleTime: ?float}
     */
    #[Computed]
    public function averageTimes(): array
    {
        return [
            'leadTime' => $this->completedTaskTimings->isEmpty() ? null : round($this->completedTaskTimings->avg('leadTime'), 1),
            'cycleTime' => $this->completedTaskTimings->isEmpty() ? null : round($this->completedTaskTimings->avg('cycleTime'), 1),
        ];
    }

    #[Computed]
    public function reportTasks(): Collection
    {
        return $this->project->tasks()
            ->with(['status:id,name', 'assignee:id,name'])
            ->latest('created_at')
            ->limit(100)
            ->get();
    }

    #[Computed]
    public function completedTaskTimings(): Collection
    {
        $completed = $this->completedActivities;
        if ($completed->isEmpty()) {
            return collect();
        }

        $taskIds = $completed->pluck('task_id')->unique();
        $firstStatusChanges = TaskActivity::query()
            ->whereIn('task_id', $taskIds)
            ->where('action', 'updated')
            ->whereNotNull('details->project_status_id')
            ->oldest('created_at')
            ->get(['task_id', 'created_at'])
            ->unique('task_id')
            ->keyBy('task_id');

        return $completed->map(function (TaskActivity $activity) use ($firstStatusChanges): array {
            $createdAt = $activity->task?->created_at ?? $activity->created_at;
            $startedAt = $firstStatusChanges->get($activity->task_id)?->created_at ?? $createdAt;

            return [
                'date' => $activity->created_at->toDateString(),
                'leadTime' => max(0, ($activity->created_at->getTimestamp() - $createdAt->getTimestamp()) / 86400),
                'cycleTime' => max(0, ($activity->created_at->getTimestamp() - $startedAt->getTimestamp()) / 86400),
            ];
        });
    }

    private function activityInPeriod(): Builder
    {
        return TaskActivity::query()
            ->whereIn('task_id', $this->project->tasks()->select('id'))
            ->whereBetween('created_at', [$this->periodStart(), now()]);
    }

    private function periodDays(): int
    {
        return match ($this->period) {
            '7' => 7,
            '90' => 90,
            default => 30,
        };
    }

    private function periodStart(): \Illuminate\Support\Carbon
    {
        return today()->subDays($this->periodDays() - 1);
    }

    /**
     * @param  Collection<int, array{label: string, count: int, color: string}>  $items
     * @return array{total: int, background: string, items: Collection<int, array{label: string, count: int, color: string}>}
     */
    private function donutData(Collection $items): array
    {
        $total = (int) $items->sum('count');
        $offset = 0.0;
        $segments = [];

        foreach ($items as $item) {
            if ($total === 0 || $item['count'] === 0) {
                continue;
            }

            $angle = $item['count'] / $total * 360;
            $segments[] = "{$item['color']} {$offset}deg ".($offset + $angle).'deg';
            $offset += $angle;
        }

        return [
            'total' => $total,
            'background' => $segments === [] ? '#e2e8f0' : 'conic-gradient('.implode(', ', $segments).')',
            'items' => $items,
        ];
    }
};
?>

<main class="mx-auto w-full max-w-[100rem] flex-1 px-4 py-7 sm:px-6 lg:px-8 lg:py-9">
    <x-projects.tabs :project="$this->project" active="reports" />

    <header class="mt-5 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-medium text-indigo-600 dark:text-indigo-400">Project reports</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $this->project->name }}</h1>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Understand work status, trends, and delivery across this project.</p>
        </div>
        <div class="w-full sm:w-48">
            <label for="report-period" class="sr-only">Report period</label>
            <select id="report-period" wire:model.live="period" class="min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-800 shadow-sm focus:border-indigo-600 focus:outline-none focus:ring-4 focus:ring-indigo-600/10 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                <option value="7">Last 7 days</option>
                <option value="30">Last 30 days</option>
                <option value="90">Last 90 days</option>
            </select>
        </div>
    </header>

    <section class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Report summary">
        @foreach ([
            ['Completed', $this->metrics['completed'], 'in the last '.$this->periodDays().' days', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300', '✓'],
            ['Updated', $this->metrics['updated'], 'in the last '.$this->periodDays().' days', 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300', '↻'],
            ['Created', $this->metrics['created'], 'in the last '.$this->periodDays().' days', 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300', '+'],
            ['Due soon', $this->metrics['dueSoon'], 'in the next 7 days', 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300', '◷'],
        ] as [$label, $value, $hint, $iconStyle, $icon])
            <article class="flex min-h-20 items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <span aria-hidden="true" @class(['flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-lg font-bold', $iconStyle])>{{ $icon }}</span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $value }} {{ $label }}</p>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $hint }}</p>
                </div>
            </article>
        @endforeach
    </section>

    <section class="mt-4 grid gap-4 xl:grid-cols-3" aria-label="Work item distribution">
        @foreach ([
            ['Work items by status', $this->statusDistribution],
            ['Work items by type', $this->typeDistribution],
            ['Work items by assignee', $this->assigneeDistribution],
        ] as [$title, $distribution])
            <article wire:key="report-distribution-{{ $loop->index }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">{{ $title }}</h2>
                <div class="mt-4 flex min-h-40 items-center gap-4">
                    <div class="relative flex h-32 w-32 shrink-0 items-center justify-center rounded-full" style="background: {{ $distribution['background'] }}">
                        <div class="flex h-20 w-20 flex-col items-center justify-center rounded-full bg-white text-center dark:bg-slate-900">
                            <span class="text-xl font-bold tabular-nums text-slate-900 dark:text-white">{{ $distribution['total'] }}</span>
                            <span class="text-[9px] text-slate-500 dark:text-slate-400">total items</span>
                        </div>
                    </div>
                    <ul class="max-h-36 min-w-0 flex-1 space-y-2 overflow-y-auto pr-1">
                        @forelse ($distribution['items'] as $item)
                            <li wire:key="report-legend-{{ $loop->parent->index }}-{{ $loop->index }}" class="flex items-center justify-between gap-2 text-[11px]">
                                <span class="flex min-w-0 items-center gap-2 text-slate-600 dark:text-slate-400"><span class="h-2.5 w-2.5 shrink-0 rounded-sm" style="background-color: {{ $item['color'] }}"></span><span class="truncate">{{ $item['label'] }}</span></span>
                                <span class="shrink-0 tabular-nums text-slate-500 dark:text-slate-400">{{ $item['count'] }}</span>
                            </li>
                        @empty
                            <li class="text-xs text-slate-500 dark:text-slate-400">No work items yet.</li>
                        @endforelse
                    </ul>
                </div>
            </article>
        @endforeach
    </section>

    @php
        $timelineMaximum = max(1, (int) $this->timeline->max(fn (array $bucket): int => max($bucket['created'], $bucket['completed'])));
        $leadLine = $this->timeline->filter(fn (array $bucket): bool => $bucket['leadY'] !== null)->map(fn (array $bucket): string => $bucket['x'].','.$bucket['leadY'])->implode(' ');
        $cycleLine = $this->timeline->filter(fn (array $bucket): bool => $bucket['cycleY'] !== null)->map(fn (array $bucket): string => $bucket['x'].','.$bucket['cycleY'])->implode(' ');
    @endphp

    <section class="mt-4 grid gap-4 xl:grid-cols-2" aria-label="Project trends">
        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Work item creation trend</h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">New tasks created during the selected period.</p>
                </div>
                <span class="shrink-0 text-xs tabular-nums text-slate-500 dark:text-slate-400">{{ $this->metrics['created'] }} created</span>
            </div>
            <div class="mt-4 flex h-44 items-end gap-1 border-b border-l border-slate-200 px-2 pb-1 dark:border-slate-700">
                @foreach ($this->timeline as $bucket)
                    <div wire:key="created-bar-{{ $loop->index }}" class="group flex h-full min-w-0 flex-1 items-end justify-center">
                        <span title="{{ $bucket['label'] }}: {{ $bucket['created'] }} created" class="w-2/3 max-w-7 rounded-t bg-indigo-500 transition hover:bg-indigo-600" style="height: {{ $bucket['created'] === 0 ? 0 : max(2, round($bucket['created'] / $timelineMaximum * 100)) }}%"></span>
                    </div>
                @endforeach
            </div>
            <div class="mt-2 flex justify-between text-[10px] text-slate-400"><span>{{ $this->timeline->first()['label'] ?? '' }}</span><span>{{ $this->timeline->last()['label'] ?? '' }}</span></div>
        </article>

        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Work item completion trend</h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Tasks moved into a completed workflow status.</p>
                </div>
                <span class="shrink-0 text-xs tabular-nums text-slate-500 dark:text-slate-400">{{ $this->metrics['completed'] }} completed</span>
            </div>
            <div class="mt-4 flex h-44 items-end gap-1 border-b border-l border-slate-200 px-2 pb-1 dark:border-slate-700">
                @foreach ($this->timeline as $bucket)
                    <div wire:key="completed-bar-{{ $loop->index }}" class="flex h-full min-w-0 flex-1 items-end justify-center">
                        <span title="{{ $bucket['label'] }}: {{ $bucket['completed'] }} completed" class="w-2/3 max-w-7 rounded-t bg-emerald-500 transition hover:bg-emerald-600" style="height: {{ $bucket['completed'] === 0 ? 0 : max(2, round($bucket['completed'] / $timelineMaximum * 100)) }}%"></span>
                    </div>
                @endforeach
            </div>
            <div class="mt-2 flex justify-between text-[10px] text-slate-400"><span>{{ $this->timeline->first()['label'] ?? '' }}</span><span>{{ $this->timeline->last()['label'] ?? '' }}</span></div>
        </article>

        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 xl:col-span-2">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Work item cycle and lead time</h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Average days from creation and first status change to completion.</p>
                </div>
                <div class="flex flex-wrap gap-4 text-xs">
                    <span class="inline-flex items-center gap-2 text-slate-600 dark:text-slate-300"><span class="h-2.5 w-2.5 rounded-full bg-indigo-500"></span>Lead time <strong>{{ $this->averageTimes['leadTime'] ?? '—' }} days</strong></span>
                    <span class="inline-flex items-center gap-2 text-slate-600 dark:text-slate-300"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>Cycle time <strong>{{ $this->averageTimes['cycleTime'] ?? '—' }} days</strong></span>
                </div>
            </div>
            <div class="mt-4 h-48 overflow-hidden">
                @if ($leadLine !== '' || $cycleLine !== '')
                    <svg viewBox="0 0 640 210" preserveAspectRatio="none" class="h-full w-full" role="img" aria-label="Average lead time and cycle time trends for completed tasks">
                        <line x1="10" y1="20" x2="630" y2="20" class="stroke-slate-200 dark:stroke-slate-700" />
                        <line x1="10" y1="105" x2="630" y2="105" class="stroke-slate-200 dark:stroke-slate-700" />
                        <line x1="10" y1="190" x2="630" y2="190" class="stroke-slate-200 dark:stroke-slate-700" />
                        @if ($leadLine !== '')<polyline points="{{ $leadLine }}" fill="none" class="stroke-indigo-500" stroke-width="2" vector-effect="non-scaling-stroke" />@endif
                        @if ($cycleLine !== '')<polyline points="{{ $cycleLine }}" fill="none" class="stroke-emerald-500" stroke-width="2" vector-effect="non-scaling-stroke" />@endif
                    </svg>
                @else
                    <div class="flex h-full items-center justify-center rounded-lg bg-slate-50 text-sm text-slate-500 dark:bg-slate-950/60 dark:text-slate-400">Complete tasks to start building cycle-time reports.</div>
                @endif
            </div>
        </article>
    </section>

    <section class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900" aria-label="Work item details">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-4 dark:border-slate-800 sm:px-5">
            <div>
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Work item details</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">The latest 100 tasks in this project.</p>
            </div>
            <a href="{{ route('projects.list', $this->project) }}" wire:navigate class="shrink-0 text-xs font-semibold text-indigo-600 hover:underline dark:text-indigo-400">View task list</a>
        </div>
        <div class="max-h-[28rem] overflow-auto">
            <table class="min-w-full divide-y divide-slate-100 text-left text-xs dark:divide-slate-800">
                <thead class="sticky top-0 bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-950 dark:text-slate-400">
                    <tr>
                        <th scope="col" class="px-4 py-3">Task</th>
                        <th scope="col" class="px-4 py-3">Type</th>
                        <th scope="col" class="px-4 py-3">Status</th>
                        <th scope="col" class="px-4 py-3">Priority</th>
                        <th scope="col" class="px-4 py-3">Assignee</th>
                        <th scope="col" class="px-4 py-3">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($this->reportTasks as $task)
                        <tr wire:key="report-task-{{ $task->id }}" class="text-slate-700 dark:text-slate-300">
                            <td class="max-w-72 truncate px-4 py-3 font-medium text-slate-900 dark:text-white">{{ $task->title }}</td>
                            <td class="px-4 py-3 capitalize">{{ $task->type }}</td>
                            <td class="px-4 py-3">{{ $task->status?->name ?? 'No status' }}</td>
                            <td class="px-4 py-3 capitalize">{{ $task->priority }}</td>
                            <td class="px-4 py-3">{{ $task->assignee?->name ?? 'Unassigned' }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $task->created_at->format('M j, Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500 dark:text-slate-400">No work items to report yet. Create a task from the Board tab.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</main>
