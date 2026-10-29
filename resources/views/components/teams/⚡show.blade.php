<?php

use App\Models\Team;
use App\Models\Cycle;
use App\Support\RichText;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Team · TaskFlow')] class extends Component
{
    #[Locked]
    public int $teamId;

    public string $name = '';

    public string $description = '';

    public string $cycleName = '';

    public string $cycleGoal = '';

    public string $cycleStartDate = '';

    public string $cycleEndDate = '';

    public array $cycleGoals = [];

    public array $rolloverTargets = [];

    public string $memberEmail = '';

    public string $memberRole = 'member';

    public ?string $statusMessage = null;

    public string $statusType = 'success';

    public function mount(int $team): void
    {
        $record = auth()->user()->teams()->findOrFail($team);
        $this->authorize('view', $record);
        $this->teamId = $record->id;
        $this->name = $record->name;
        $this->description = $record->description ?? '';
        $this->cycleGoals = $record->cycles()->where('status', 'planned')->pluck('goal', 'id')->map(fn ($goal) => $goal ?? '')->all();
    }

    #[Computed]
    public function team(): Team
    {
        return auth()->user()->teams()->with(['owner', 'members' => fn ($query) => $query->orderBy('name')])->findOrFail($this->teamId);
    }

    #[Computed]
    public function teamOverview(): array
    {
        $openTasks = Task::query()
            ->whereHas('project', fn ($query) => $query->where('team_id', $this->team->id))
            ->whereHas('status', fn ($query) => $query->where('is_complete', false));

        return [
            'projects' => $this->team->projects()->count(),
            'open_tasks' => (clone $openTasks)->count(),
            'overdue_tasks' => (clone $openTasks)->whereDate('due_date', '<', today())->count(),
            'unassigned_tasks' => (clone $openTasks)->whereNull('assignee_id')->count(),
        ];
    }

    #[Computed]
    public function membersWithWorkload()
    {
        return $this->team->members()
            ->withCount([
                'assignedTasks as team_open_tasks_count' => fn ($query) => $query
                    ->whereHas('project', fn ($project) => $project->where('team_id', $this->team->id))
                    ->whereHas('status', fn ($status) => $status->where('is_complete', false)),
                'assignedTasks as team_overdue_tasks_count' => fn ($query) => $query
                    ->whereHas('project', fn ($project) => $project->where('team_id', $this->team->id))
                    ->whereHas('status', fn ($status) => $status->where('is_complete', false))
                    ->whereDate('due_date', '<', today()),
            ])
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function projects()
    {
        return $this->team->projects()->with('user')->withCount('tasks')->latest('updated_at')->get();
    }

    #[Computed]
    public function cycles()
    {
        return $this->team->cycles()
            ->withCount([
                'tasks',
                'tasks as completed_tasks_count' => fn ($query) => $query->whereHas('status', fn ($status) => $status->where('is_complete', true)),
                'tasks as open_tasks_count' => fn ($query) => $query->whereHas('status', fn ($status) => $status->where('is_complete', false)),
            ])
            ->withSum('tasks', 'story_points')
            ->withSum(['tasks as completed_story_points' => fn ($query) => $query->whereHas('status', fn ($status) => $status->where('is_complete', true))], 'story_points')
            ->get();
    }

    public function createCycle(): void
    {
        $team = $this->team;
        $this->authorize('update', $team);

        $this->cycleName = trim($this->cycleName);
        $validated = $this->validate([
            'cycleName' => ['required', 'string', 'max:120', Rule::unique('cycles', 'name')->where('team_id', $team->id)],
            'cycleGoal' => ['nullable', 'string', 'max:500'],
            'cycleStartDate' => ['required', 'date'],
            'cycleEndDate' => ['required', 'date', 'after_or_equal:cycleStartDate'],
        ]);

        $overlaps = $team->cycles()
            ->whereDate('start_date', '<=', $validated['cycleEndDate'])
            ->whereDate('end_date', '>=', $validated['cycleStartDate'])
            ->exists();

        if ($overlaps) {
            $this->addError('cycleStartDate', 'Cycle dates cannot overlap another cycle for this team.');

            return;
        }

        $cycle = $team->cycles()->create([
            'name' => $validated['cycleName'],
            'goal' => filled($validated['cycleGoal'] ?? null) ? trim($validated['cycleGoal']) : null,
            'start_date' => $validated['cycleStartDate'],
            'end_date' => $validated['cycleEndDate'],
        ]);

        $this->cycleGoals[$cycle->id] = $cycle->goal ?? '';

        $this->reset('cycleName', 'cycleGoal', 'cycleStartDate', 'cycleEndDate');
        $this->statusMessage = 'Sprint planned.';
        $this->statusType = 'success';
        unset($this->cycles);
    }

    public function saveCycleGoal(int $cycleId): void
    {
        $team = $this->team;
        $this->authorize('update', $team);
        $cycle = $team->cycles()->where('status', 'planned')->findOrFail($cycleId);
        $key = 'cycleGoals.'.$cycleId;
        $validated = $this->validate([$key => ['required', 'string', 'max:500']]);
        $cycle->update(['goal' => trim($validated['cycleGoals'][$cycleId])]);

        $this->statusMessage = 'Sprint goal saved.';
        $this->statusType = 'success';
        unset($this->cycles);
    }

    public function startCycle(int $cycleId): void
    {
        $team = $this->team;
        $this->authorize('update', $team);
        $cycle = $team->cycles()->findOrFail($cycleId);
        abort_unless($cycle->status === 'planned', 422);

        $goal = trim((string) ($this->cycleGoals[$cycleId] ?? $cycle->goal));
        if ($goal === '') {
            $this->addError('cycleGoals.'.$cycleId, 'Add a sprint goal before starting this sprint.');

            return;
        }

        $this->cycleGoals[$cycleId] = $goal;
        $this->validate(['cycleGoals.'.$cycleId => ['required', 'string', 'max:500']]);

        if ($cycle->end_date->lt(today())) {
            $this->statusMessage = 'This sprint has already passed. Update its dates before starting it.';
            $this->statusType = 'error';

            return;
        }

        if ($team->cycles()->where('status', 'active')->exists()) {
            $this->statusMessage = 'Complete the active sprint before starting another one.';
            $this->statusType = 'error';

            return;
        }

        $cycle->update(['goal' => $goal, 'status' => 'active', 'started_at' => now()]);
        $this->statusMessage = $cycle->name.' started.';
        $this->statusType = 'success';
        unset($this->cycles);
    }

    public function completeCycle(int $cycleId): void
    {
        $team = $this->team;
        $this->authorize('update', $team);
        $cycle = $team->cycles()->findOrFail($cycleId);
        abort_unless($cycle->status === 'active', 422);

        $target = (string) ($this->rolloverTargets[$cycleId] ?? 'backlog');
        $allowedTargets = ['backlog', ...$team->cycles()
            ->where('status', 'planned')
            ->whereDate('end_date', '>=', today())
            ->whereKeyNot($cycle->id)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all()];

        if (! in_array($target, $allowedTargets, true)) {
            $this->addError('rolloverTargets.'.$cycleId, 'Choose the backlog or an upcoming sprint from this team.');

            return;
        }

        $openTaskCount = 0;
        DB::transaction(function () use ($cycle, $target, &$openTaskCount): void {
            $openTasks = $cycle->tasks()
                ->whereHas('status', fn ($query) => $query->where('is_complete', false))
                ->get();
            $openTaskCount = $openTasks->count();

            foreach ($openTasks as $task) {
                $task->update(['cycle_id' => $target === 'backlog' ? null : (int) $target]);
            }

            $cycle->update(['status' => 'completed', 'completed_at' => now()]);
        });

        $this->statusMessage = $openTaskCount === 0
            ? $cycle->name.' completed.'
            : $cycle->name.' completed. '.$openTaskCount.' open '.\Illuminate\Support\Str::plural('task', $openTaskCount).' moved to '.($target === 'backlog' ? 'the backlog' : 'the selected sprint').'.';
        $this->statusType = 'success';
        unset($this->cycles, $this->teamOverview, $this->membersWithWorkload);
    }

    public function saveTeam(): void
    {
        $team = $this->team;
        $this->authorize('update', $team);
        $this->description = RichText::sanitize($this->description);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:10000'],
        ]);

        if (mb_strlen(RichText::plainText($validated['description'] ?? '')) > 2000) {
            $this->addError('description', 'The description may not be greater than 2000 characters.');

            return;
        }

        $team->update($validated);
        $this->statusMessage = 'Team details updated.';
        unset($this->team);
    }

    public function addMember(): void
    {
        $team = $this->team;
        $this->authorize('update', $team);

        $validated = $this->validate([
            'memberEmail' => ['required', 'email', 'max:255', Rule::exists('users', 'email')],
            'memberRole' => ['required', Rule::in(['member', 'admin'])],
        ]);

        $member = User::query()->where('email', $validated['memberEmail'])->firstOrFail();

        if ($team->members()->whereKey($member->id)->exists()) {
            $this->addError('memberEmail', 'This person is already in the team.');

            return;
        }

        if ($member->id !== $team->owner_id && $validated['memberRole'] === 'admin' && auth()->id() !== $team->owner_id) {
            $this->addError('memberRole', 'Only the team owner can add another team admin.');

            return;
        }

        $team->members()->attach($member->id, ['role' => $validated['memberRole']]);
        $this->reset('memberEmail');
        $this->memberRole = 'member';
        $this->statusMessage = $member->name.' was added to the team.';
        unset($this->team);
    }

    public function updateMemberRole(int $memberId, string $role): void
    {
        $team = $this->team;
        $this->authorize('update', $team);

        if (! in_array($role, ['member', 'admin'], true) || $memberId === $team->owner_id) {
            return;
        }

        if ($role === 'admin' && auth()->id() !== $team->owner_id) {
            $this->feedback('Only the team owner can promote a new admin.', 'error');

            return;
        }

        $targetMember = $team->members()->whereKey($memberId)->firstOrFail();
        if ($targetMember->pivot->role === 'admin' && auth()->id() !== $team->owner_id) {
            $this->feedback('Only the team owner can change an admin’s role.', 'error');

            return;
        }

        $team->members()->updateExistingPivot($memberId, ['role' => $role]);
        $this->feedback('Team role updated.');
        unset($this->team);
    }

    public function removeMember(int $memberId): void
    {
        $team = $this->team;
        $this->authorize('update', $team);

        if ($memberId === $team->owner_id) {
            $this->feedback('The team owner cannot be removed.', 'error');

            return;
        }

        $member = $team->members()->whereKey($memberId)->firstOrFail();
        if ($member->pivot->role === 'admin' && auth()->id() !== $team->owner_id) {
            $this->feedback('Only the team owner can remove a team admin.', 'error');

            return;
        }
        $team->members()->detach($memberId);
        $this->feedback($member->name.' was removed from the team. They may still have direct access to individual projects.');
        unset($this->team);
    }

    public function deleteTeam(): void
    {
        $team = $this->team;
        $this->authorize('delete', $team);

        DB::transaction(fn () => $team->delete());

        session()->flash('status', 'Team deleted. Its projects remain available to their owners.');
        $this->redirectRoute('teams.index');
    }

    private function feedback(string $message, string $type = 'success'): void
    {
        $this->statusMessage = $message;
        $this->statusType = $type;
    }
};
?>

<main class="mx-auto w-full max-w-7xl flex-1 px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
    <a href="{{ route('teams.index') }}" wire:navigate class="inline-flex items-center gap-2 text-sm font-medium text-indigo-600 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:text-indigo-400"><span aria-hidden="true">←</span> All teams</a>

    @if ($statusMessage)
        <p @if ($statusType === 'error') role="alert" @else role="status" @endif @class(['mt-5 rounded-lg border px-4 py-3 text-sm font-medium', 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300' => $statusType === 'success', 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300' => $statusType === 'error'])>{{ $statusMessage }}</p>
    @endif

    <div class="mt-5 grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,.65fr)]">
        <div>
            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-indigo-600 dark:text-indigo-400">Team workspace</p>
                        <h1 class="mt-1 break-words text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $this->team->name }}</h1>
                        <div class="rich-text-output mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">{!! \App\Support\RichText::sanitize($this->team->description) ?: 'Projects shared with this team are visible to every team member.' !!}</div>
                    </div>
                    @can('delete', $this->team)
                        <button type="button" wire:click="deleteTeam" wire:confirm="Delete this team? Its projects will be kept and become personal projects." wire:loading.attr="disabled" class="shrink-0 rounded-lg px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 disabled:opacity-50 dark:text-red-400 dark:hover:bg-red-500/10">Delete team</button>
                    @endcan
                </div>
                <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">Owned by {{ $this->team->owner->name }} · {{ $this->team->members->count() }} {{ \Illuminate\Support\Str::plural('member', $this->team->members->count()) }}</p>
            </section>

            <section class="mt-6" aria-label="Team workload overview">
                <h2 class="sr-only">Team workload overview</h2>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"><p class="text-xs font-medium text-slate-500 dark:text-slate-400">Projects</p><p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ $this->teamOverview['projects'] }}</p></div>
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"><p class="text-xs font-medium text-slate-500 dark:text-slate-400">Open tasks</p><p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ $this->teamOverview['open_tasks'] }}</p></div>
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"><p class="text-xs font-medium text-slate-500 dark:text-slate-400">Overdue</p><p class="mt-1 text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $this->teamOverview['overdue_tasks'] }}</p></div>
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"><p class="text-xs font-medium text-slate-500 dark:text-slate-400">Unassigned open tasks</p><p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ $this->teamOverview['unassigned_tasks'] }}</p></div>
                </div>
            </section>

            <section class="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900" aria-labelledby="team-cycles-heading">
                <div class="flex items-start justify-between gap-4">
                    <div><h2 id="team-cycles-heading" class="text-lg font-semibold text-slate-900 dark:text-white">Team sprints</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Set a sprint goal, plan team work, and review delivery.</p></div>
                </div>

                @can('update', $this->team)
                    <form wire:submit="createCycle" class="mt-5 grid gap-3 rounded-lg bg-slate-50 p-4 sm:grid-cols-2 lg:grid-cols-5 dark:bg-slate-950">
                        <div><label for="cycle-name" class="block text-xs font-medium text-slate-700 dark:text-slate-300">Sprint name</label><input id="cycle-name" wire:model="cycleName" maxlength="120" required placeholder="Sprint 1" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">@error('cycleName')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror</div>
                        <div><label for="cycle-goal" class="block text-xs font-medium text-slate-700 dark:text-slate-300">Sprint goal <span class="font-normal text-slate-500">(required to start)</span></label><input id="cycle-goal" wire:model="cycleGoal" maxlength="500" placeholder="What outcome will this sprint deliver?" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">@error('cycleGoal')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror</div>
                        <div><label for="cycle-start" class="block text-xs font-medium text-slate-700 dark:text-slate-300">Starts</label><input id="cycle-start" type="date" wire:model="cycleStartDate" required class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">@error('cycleStartDate')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror</div>
                        <div><label for="cycle-end" class="block text-xs font-medium text-slate-700 dark:text-slate-300">Ends</label><input id="cycle-end" type="date" wire:model="cycleEndDate" required class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">@error('cycleEndDate')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror</div>
                        <div class="flex items-end"><button type="submit" wire:loading.attr="disabled" wire:target="createCycle" class="min-h-10 w-full rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60"><span wire:loading.remove wire:target="createCycle">Plan sprint</span><span wire:loading wire:target="createCycle">Planning...</span></button></div>
                    </form>
                @endcan

                @if ($this->cycles->isNotEmpty())
                    <ul class="mt-5 divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($this->cycles as $cycle)
                            <li wire:key="team-cycle-{{ $cycle->id }}" class="space-y-3 py-4">
                                @php($completion = $cycle->tasks_count > 0 ? (int) round($cycle->completed_tasks_count / $cycle->tasks_count * 100) : 0)
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2"><p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $cycle->name }}</p><span @class(['rounded-full px-2.5 py-1 text-xs font-semibold capitalize', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' => $cycle->status === 'active', 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300' => $cycle->status === 'planned', 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => $cycle->status === 'completed'])>{{ $cycle->status === 'planned' ? ($cycle->start_date->isFuture() ? 'upcoming' : 'planned') : ($cycle->status === 'active' ? 'active' : 'completed') }}</span></div>
                                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $cycle->start_date->format('M j, Y') }} – {{ $cycle->end_date->format('M j, Y') }} · {{ $cycle->tasks_count }} {{ \Illuminate\Support\Str::plural('task', $cycle->tasks_count) }} · {{ $cycle->completed_tasks_count }} complete · {{ $cycle->open_tasks_count }} open</p>
                                        @if ($cycle->goal)<p class="mt-2 text-sm text-slate-700 dark:text-slate-300"><span class="font-medium">Goal:</span> {{ $cycle->goal }}</p>@elseif ($cycle->status === 'planned')<p class="mt-2 text-xs text-amber-700 dark:text-amber-300">Set a sprint goal before starting.</p>@endif
                                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800" role="progressbar" aria-label="{{ $cycle->name }} task completion" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $completion }}"><div class="h-full rounded-full bg-emerald-500 transition-all" style="width: {{ $completion }}%"></div></div>
                                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $completion }}% complete · {{ $cycle->tasks_sum_story_points ?? 0 }} planned points · {{ $cycle->completed_story_points ?? 0 }} completed points</p>
                                    </div>
                                    @can('update', $this->team)
                                        <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                                            @if ($cycle->status === 'planned')
                                                <input type="text" wire:model="cycleGoals.{{ $cycle->id }}" maxlength="500" aria-label="Sprint goal for {{ $cycle->name }}" placeholder="Add sprint goal" class="min-h-9 min-w-48 rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                                <button type="button" wire:click="saveCycleGoal({{ $cycle->id }})" class="rounded-lg border border-slate-300 px-2.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Save goal</button>
                                                <button type="button" wire:click="startCycle({{ $cycle->id }})" wire:loading.attr="disabled" class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">Start sprint</button>
                                                @error('cycleGoals.'.$cycle->id)<p class="w-full text-xs text-red-600">{{ $message }}</p>@enderror
                                            @elseif ($cycle->status === 'active')
                                                <select wire:model="rolloverTargets.{{ $cycle->id }}" aria-label="Move unfinished tasks to" class="min-h-9 rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs dark:border-slate-700 dark:bg-slate-950 dark:text-white"><option value="backlog">Move unfinished to backlog</option>@foreach ($this->cycles as $targetCycle)@if ($targetCycle->status === 'planned' && $targetCycle->end_date->gte(today()))<option value="{{ $targetCycle->id }}">Move unfinished to {{ $targetCycle->name }}</option>@endif @endforeach</select>
                                                <button type="button" wire:click="completeCycle({{ $cycle->id }})" wire:confirm="Complete {{ $cycle->name }} and move its unfinished tasks to the selected destination?" wire:loading.attr="disabled" class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700 disabled:opacity-50">Complete sprint</button>
                                                @error('rolloverTargets.'.$cycle->id)<p class="w-full text-xs text-red-600">{{ $message }}</p>@enderror
                                            @endif
                                        </div>
                                    @endcan
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-5 rounded-lg border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">No cycles yet. Create one to start planning work in time-boxed iterations.</p>
                @endif
            </section>

            <section class="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-start justify-between gap-4">
                    <div><h2 class="text-lg font-semibold text-slate-900 dark:text-white">Team projects</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Team members can see every project in this list.</p></div>
                    <a href="{{ route('projects.create') }}?team={{ $this->team->id }}" wire:navigate class="shrink-0 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700">New project</a>
                </div>
                @if ($this->projects->isNotEmpty())
                    <ul class="mt-5 divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($this->projects as $project)
                            <li wire:key="team-project-{{ $project->id }}" class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0"><a href="{{ route('projects.show', $project) }}" wire:navigate class="block truncate text-sm font-semibold text-slate-900 hover:text-indigo-600 focus:outline-none focus-visible:underline dark:text-white dark:hover:text-indigo-400">{{ $project->name }}</a><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Created by {{ $project->user->name }} · {{ $project->tasks_count }} {{ \Illuminate\Support\Str::plural('task', $project->tasks_count) }}</p></div>
                                <a href="{{ route('projects.board', $project) }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:underline dark:text-indigo-400">Open board</a>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="mt-5 rounded-lg border border-dashed border-slate-300 px-5 py-8 text-center dark:border-slate-700"><p class="text-sm text-slate-500 dark:text-slate-400">No team projects yet.</p><a href="{{ route('projects.create') }}?team={{ $this->team->id }}" wire:navigate class="mt-3 inline-flex rounded-lg px-3 py-2 text-sm font-semibold text-indigo-600 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-500/10">Create the first project</a></div>
                @endif
            </section>
        </div>

        <aside class="space-y-6">
            @can('update', $this->team)
                <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-white">Team details</h2>
                    <form wire:submit="saveTeam" class="mt-4 space-y-3">
                        <div><label for="team-name" class="block text-sm font-medium text-slate-800 dark:text-slate-200">Name</label><input id="team-name" wire:model="name" required maxlength="120" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">@error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                        <div><label class="mb-1.5 block text-sm font-medium text-slate-800 dark:text-slate-200">Description</label><x-rich-text-editor model="description" :value="$description" id="team-description" label="Team description" placeholder="Add a description for this team..." min-height="7rem" />@error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                        <button type="submit" wire:loading.attr="disabled" wire:target="saveTeam" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Save details</button>
                    </form>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-white">Add an existing member</h2>
                    <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">They need a TaskFlow account. They’ll see all projects assigned to this team.</p>
                    <form wire:submit="addMember" class="mt-4 space-y-3">
                        <div><label for="member-email" class="block text-sm font-medium text-slate-800 dark:text-slate-200">Account email</label><input id="member-email" type="email" wire:model="memberEmail" required placeholder="person@example.com" @error('memberEmail') aria-invalid="true" aria-describedby="member-email-error" @enderror class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">@error('memberEmail')<p id="member-email-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror</div>
                        <div><label for="member-role" class="block text-sm font-medium text-slate-800 dark:text-slate-200">Role</label><select id="member-role" wire:model="memberRole" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"><option value="member">Member</option>@if (auth()->id() === $this->team->owner_id)<option value="admin">Admin</option>@endif</select>@error('memberRole')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror</div>
                        <button type="submit" wire:loading.attr="disabled" wire:target="addMember" class="inline-flex min-h-10 w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"><span wire:loading.remove wire:target="addMember">Add to team</span><span wire:loading wire:target="addMember">Adding...</span></button>
                    </form>
                </section>
            @endcan

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Members</h2>
                <ul class="mt-3 divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($this->membersWithWorkload as $member)
                        <li wire:key="team-member-{{ $member->id }}" class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                            <div class="flex min-w-0 items-center gap-3"><span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-sm font-semibold text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-200">{{ strtoupper(substr($member->name, 0, 1)) }}</span><span class="min-w-0"><span class="block truncate text-sm font-medium text-slate-900 dark:text-white">{{ $member->name }}@if ($member->id === auth()->id()) <span class="text-xs text-slate-500">(you)</span>@endif</span><span class="block truncate text-xs text-slate-500 dark:text-slate-400">{{ $member->email }} · {{ $member->team_open_tasks_count }} open · {{ $member->team_overdue_tasks_count }} overdue</span></span></div>
                            <div class="flex shrink-0 items-center gap-2">
                                @if ($member->id === $this->team->owner_id)
                                    <span class="text-xs font-semibold text-indigo-600 dark:text-indigo-300">Owner</span>
                                @elseif (auth()->user()->can('update', $this->team))
                                    <select aria-label="Role for {{ $member->name }}" wire:change="updateMemberRole({{ $member->id }}, $event.target.value)" @disabled(auth()->id() !== $this->team->owner_id && $member->pivot->role === 'admin') class="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"><option value="member" @selected($member->pivot->role === 'member')>Member</option>@if (auth()->id() === $this->team->owner_id)<option value="admin" @selected($member->pivot->role === 'admin')>Admin</option>@endif</select>
                                    <button type="button" wire:click="removeMember({{ $member->id }})" wire:confirm="Remove {{ $member->name }} from this team?" class="rounded px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 dark:text-red-400 dark:hover:bg-red-500/10">Remove</button>
                                @else
                                    <span class="text-xs font-medium capitalize text-slate-500 dark:text-slate-400">{{ $member->pivot->role }}</span>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        </aside>
    </div>
</main>
