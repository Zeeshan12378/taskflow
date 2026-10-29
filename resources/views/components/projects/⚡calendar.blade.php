<?php

use App\Models\Project;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Project calendar · TaskFlow')] class extends Component
{
    #[Locked]
    public int $projectId;

    public string $month = '';

    public function mount(int $project): void
    {
        $record = Project::query()->accessibleTo(auth()->user())->findOrFail($project);
        $this->authorize('view', $record);
        $this->projectId = $record->id;
        $this->month = now()->format('Y-m');
    }

    #[Computed]
    public function project(): Project
    {
        return Project::query()->accessibleTo(auth()->user())->with('statuses')->findOrFail($this->projectId);
    }

    #[Computed]
    public function monthDate(): CarbonImmutable
    {
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $this->month.'-01');
        } catch (\Throwable) {
            $date = null;
        }

        return $date && $date->format('Y-m') === $this->month ? $date : CarbonImmutable::now()->startOfMonth();
    }

    #[Computed]
    public function tasksByDueDate()
    {
        return $this->project->tasks()
            ->with('status')
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$this->monthDate->startOfMonth()->toDateString(), $this->monthDate->endOfMonth()->toDateString()])
            ->orderBy('due_date')
            ->get()
            ->groupBy(fn ($task) => $task->due_date->toDateString());
    }

    public function previousMonth(): void
    {
        $this->month = $this->monthDate->subMonth()->format('Y-m');
        unset($this->monthDate, $this->tasksByDueDate);
    }

    public function nextMonth(): void
    {
        $this->month = $this->monthDate->addMonth()->format('Y-m');
        unset($this->monthDate, $this->tasksByDueDate);
    }
};
?>

<main class="mx-auto w-full max-w-[100rem] flex-1 px-4 py-7 sm:px-6 lg:px-8 lg:py-9">
    <x-projects.tabs :project="$this->project" active="calendar" />

    <div class="mt-6 flex flex-wrap items-end justify-between gap-4">
        <div><p class="text-sm font-medium text-indigo-600 dark:text-indigo-400">{{ $this->project->name }}</p><h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Calendar</h1><p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Tasks with due dates for this month.</p></div>
        <div class="flex items-center gap-2"><button type="button" wire:click="previousMonth" aria-label="Previous month" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">‹</button><p class="min-w-36 text-center text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $this->monthDate->format('F Y') }}</p><button type="button" wire:click="nextMonth" aria-label="Next month" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">›</button></div>
    </div>

    <div class="mt-5 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="min-w-[760px]">
            <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-950/50">@foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday)<div class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $weekday }}</div>@endforeach</div>
            <div class="grid grid-cols-7">
                @php($monthStart = $this->monthDate->startOfMonth())
                @for ($cell = 0; $cell < 42; $cell++)
                    @php($dayNumber = $cell - $monthStart->dayOfWeek + 1)
                    @php($inMonth = $dayNumber >= 1 && $dayNumber <= $monthStart->daysInMonth)
                    @php($cellDate = $inMonth ? $monthStart->day($dayNumber) : null)
                    <section @class(['min-h-28 border-b border-r border-slate-100 p-2 dark:border-slate-800 sm:min-h-32', 'bg-slate-50/70 dark:bg-slate-950/30' => ! $inMonth]) aria-label="{{ $inMonth ? $cellDate->format('F j, Y') : 'Outside selected month' }}">
                        @if ($inMonth)
                            <p @class(['text-xs font-semibold', 'text-indigo-700 dark:text-indigo-300' => $cellDate->isToday(), 'text-slate-700 dark:text-slate-300' => ! $cellDate->isToday()])>{{ $dayNumber }}</p>
                            <div class="mt-1 space-y-1">
                                @foreach ($this->tasksByDueDate->get($cellDate->toDateString(), collect())->take(3) as $task)
                                    <a wire:key="calendar-task-{{ $task->id }}" href="{{ route('projects.tasks.edit', [$this->project, $task]) }}" wire:navigate title="{{ $task->title }}" class="block truncate rounded bg-indigo-50 px-1.5 py-1 text-[11px] font-medium text-indigo-800 hover:bg-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-200 dark:hover:bg-indigo-500/20">{{ $task->title }}</a>
                                @endforeach
                                @if ($this->tasksByDueDate->get($cellDate->toDateString(), collect())->count() > 3)
                                    <p class="px-1 text-[10px] text-slate-500">+{{ $this->tasksByDueDate->get($cellDate->toDateString())->count() - 3 }} more</p>
                                @endif
                            </div>
                        @endif
                    </section>
                @endfor
            </div>
        </div>
    </div>
</main>
