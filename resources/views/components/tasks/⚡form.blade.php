<?php

use App\Models\Project;
use App\Models\Cycle;
use App\Models\Task;
use App\Support\RichText;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Task · TaskFlow')] class extends Component
{
    #[Locked]
    public int $projectId;

    #[Locked]
    public ?int $taskId = null;

    public string $title = '';

    public string $description = '';

    public string $statusId = '';

    public string $priority = 'medium';

    public string $type = 'task';

    public string $blockedReason = '';

    public string $dueDate = '';

    public string $githubUrl = '';

    public string $assigneeId = '';

    public string $reviewerId = '';

    public string $cycleId = '';

    public string $storyPoints = '';

    public string $commentBody = '';

    public bool $modal = false;

    public bool $editingTitle = false;

    public bool $editingDescription = false;

    public ?string $editingDetailField = null;

    #[Computed]
    public function project(): Project
    {
        return Project::query()->accessibleTo(auth()->user())->with(['statuses', 'team.members', 'members', 'user'])->findOrFail($this->projectId);
    }

    #[Computed]
    public function task(): ?Task
    {
        if ($this->taskId === null) {
            return null;
        }

        return $this->project->tasks()->with(['status', 'assignee'])->findOrFail($this->taskId);
    }

    #[Computed]
    public function canAssignCycle(): bool
    {
        return $this->project->team?->members->contains('id', auth()->id()) ?? false;
    }

    #[Computed]
    public function availableCycles()
    {
        if (! $this->canAssignCycle) {
            return collect();
        }

        $currentCycleId = $this->task?->cycle_id;

        return Cycle::query()
            ->where('team_id', $this->project->team_id)
            ->where(fn ($query) => $query->where(fn ($query) => $query->whereIn('status', ['planned', 'active'])->whereDate('end_date', '>=', today()))
                ->when($currentCycleId, fn ($query) => $query->orWhereKey($currentCycleId)))
            ->orderBy('start_date')
            ->get();
    }

    #[Computed]
    public function comments()
    {
        return $this->task?->comments()->with('user')->latest()->limit(50)->get()->reverse()->values() ?? collect();
    }

    #[Computed]
    public function activities()
    {
        return $this->task?->activities()->with('actor')->latest()->limit(50)->get() ?? collect();
    }

    public function addComment(): void
    {
        $task = $this->task;
        abort_if($task === null, 404);
        $this->authorize('comment', $task);

        $this->commentBody = RichText::sanitize($this->commentBody);

        if (RichText::isEmpty($this->commentBody)) {
            $this->addError('commentBody', 'Write a comment before posting.');

            return;
        }

        $validated = $this->validate([
            'commentBody' => ['required', 'string', 'max:20000'],
        ]);

        if (mb_strlen(RichText::plainText($validated['commentBody'])) > 5000) {
            $this->addError('commentBody', 'The comment may not be greater than 5000 characters.');

            return;
        }

        DB::transaction(function () use ($task, $validated): void {
            $comment = $task->comments()->create([
                'user_id' => auth()->id(),
                'body' => $validated['commentBody'],
            ]);
            $task->recordActivity('commented', ['comment_id' => $comment->id]);
            $task->notifyParticipants('commented', [$task->user_id, $task->assignee_id]);
        });

        $this->reset('commentBody');
        $this->resetValidation('commentBody');
        unset($this->comments, $this->activities);
    }

    public function mount(int $project, ?int $task = null, bool $modal = false): void
    {
        $this->modal = $modal;
        $record = Project::query()->accessibleTo(auth()->user())->with(['statuses', 'team.members', 'members', 'user'])->findOrFail($project);
        $this->authorize('view', $record);
        $this->projectId = $record->id;

        if ($task === null) {
            $this->authorize('create', [Task::class, $record]);
            $this->statusId = (string) $record->statuses->first()?->id;

            return;
        }

        $taskRecord = $record->tasks()->findOrFail($task);
        $this->authorize('update', $taskRecord);

        $this->taskId = $taskRecord->id;
        $this->title = $taskRecord->title;
        $this->description = $taskRecord->description ?? '';
        $this->statusId = (string) $taskRecord->project_status_id;
        $this->priority = $taskRecord->priority;
        $this->type = $taskRecord->type;
        $this->blockedReason = $taskRecord->blocked_reason ?? '';
        $this->dueDate = $taskRecord->due_date?->format('Y-m-d') ?? '';
        $this->githubUrl = $taskRecord->github_url ?? '';
        $this->assigneeId = (string) ($taskRecord->assignee_id ?? '');
        $this->reviewerId = (string) ($taskRecord->reviewer_id ?? '');
        $this->cycleId = (string) ($taskRecord->cycle_id ?? '');
        $this->storyPoints = (string) ($taskRecord->story_points ?? '');
    }

    public function editInlineField(string $field): void
    {
        abort_unless(in_array($field, ['title', 'description'], true), 404);
        abort_if($this->taskId === null, 404);
        $this->authorize('update', $this->task);
        $this->editingTitle = $field === 'title';
        $this->editingDescription = $field === 'description';
        $this->resetValidation($field);
    }

    public function cancelInlineField(string $field): void
    {
        abort_unless(in_array($field, ['title', 'description'], true), 404);
        $task = $this->task;
        abort_if($task === null, 404);
        $this->{$field} = $field === 'title' ? $task->title : ($task->description ?? '');
        $this->editingTitle = false;
        $this->editingDescription = false;
        $this->resetValidation($field);
    }

    public function saveInlineField(string $field): void
    {
        abort_unless(in_array($field, ['title', 'description'], true), 404);
        $task = $this->task;
        abort_if($task === null, 404);
        $this->authorize('update', $task);
        if ($field === 'description') {
            $this->description = RichText::sanitize($this->description);
        }
        $rules = $field === 'title'
            ? ['title' => ['required', 'string', 'max:180']]
            : ['description' => ['nullable', 'string', 'max:20000']];
        if ($field === 'title') {
            $this->title = trim($this->title);
        }

        $validated = $this->validate($rules);
        if ($field === 'description' && mb_strlen(RichText::plainText($validated['description'] ?? '')) > 5000) {
            $this->addError('description', 'The description may not be greater than 5000 characters.');

            return;
        }
        $value = $field === 'title' ? trim($validated[$field]) : ($validated[$field] ?: null);
        $task->update([$field => $value]);
        $this->{$field} = $value ?? '';
        $this->editingTitle = false;
        $this->editingDescription = false;
        unset($this->task, $this->activities);
        $this->dispatch('task-modal-refresh');
    }

    public function editDetailField(string $field): void
    {
        abort_unless(in_array($field, ['type', 'statusId', 'priority', 'dueDate', 'githubUrl', 'assigneeId', 'reviewerId', 'cycleId', 'storyPoints', 'blockedReason'], true), 404);
        $task = $this->task;
        abort_if($task === null, 404);
        $this->authorize('update', $task);
        $this->editingDetailField = $field;
        $this->resetValidation($field);
    }

    public function cancelDetailField(string $field): void
    {
        abort_unless(in_array($field, ['type', 'statusId', 'priority', 'dueDate', 'githubUrl', 'assigneeId', 'reviewerId', 'cycleId', 'storyPoints', 'blockedReason'], true), 404);
        $task = $this->task;
        abort_if($task === null, 404);
        $this->authorize('update', $task);

        $this->{$field} = match ($field) {
            'type' => $task->type,
            'statusId' => (string) $task->project_status_id,
            'priority' => $task->priority,
            'dueDate' => $task->due_date?->format('Y-m-d') ?? '',
            'githubUrl' => $task->github_url ?? '',
            'assigneeId' => (string) ($task->assignee_id ?? ''),
            'reviewerId' => (string) ($task->reviewer_id ?? ''),
            'cycleId' => (string) ($task->cycle_id ?? ''),
            'storyPoints' => (string) ($task->story_points ?? ''),
            'blockedReason' => $task->blocked_reason ?? '',
        };
        $this->editingDetailField = null;
        $this->resetValidation($field);
    }

    public function saveDetailField(string $field): void
    {
        abort_unless(in_array($field, ['type', 'statusId', 'priority', 'dueDate', 'githubUrl', 'assigneeId', 'reviewerId', 'cycleId', 'storyPoints', 'blockedReason'], true), 404);
        $task = $this->task;
        abort_if($task === null, 404);
        $this->authorize('update', $task);
        if ($field === 'blockedReason') {
            $this->blockedReason = RichText::sanitize($this->blockedReason);
        }
        $project = $this->project;
        $currentStatus = $task->status;
        $status = $field === 'statusId' ? $project->statuses()->findOrFail($this->statusId) : $currentStatus;
        $rules = match ($field) {
            'type' => ['type' => ['required', Rule::in(Task::TYPES)]],
            'statusId' => ['statusId' => ['required', 'integer', Rule::exists('project_statuses', 'id')->where('project_id', $project->id)]],
            'priority' => ['priority' => ['required', Rule::in(Task::PRIORITIES)]],
            'dueDate' => ['dueDate' => ['nullable', 'date']],
            'githubUrl' => ['githubUrl' => $this->githubUrlRules()],
            'assigneeId' => ['assigneeId' => ['nullable', 'integer', Rule::in($project->accessibleMemberIds())]],
            'reviewerId' => ['reviewerId' => [$currentStatus?->requiresReviewer() ? 'required' : 'nullable', 'integer', Rule::in($project->accessibleMemberIds())]],
            'cycleId' => ['cycleId' => ['nullable', 'integer', Rule::exists('cycles', 'id')->where('team_id', $project->team_id)->where(fn ($query) => $query->where(fn ($query) => $query->whereIn('status', ['planned', 'active'])->whereDate('end_date', '>=', today()))->orWhere('id', $task->cycle_id ?? 0))]],
            'storyPoints' => ['storyPoints' => ['nullable', 'integer', Rule::in([1, 2, 3, 5, 8, 13, 21])]],
            'blockedReason' => ['blockedReason' => [strtolower((string) $currentStatus?->name) === 'blocked' ? 'required' : 'nullable', 'string', 'max:10000']],
        };
        if ($field === 'blockedReason' && strtolower((string) $currentStatus?->name) === 'blocked' && RichText::isEmpty($this->blockedReason)) {
            $this->addError('blockedReason', 'A blocked reason is required.');

            return;
        }
        $validated = $this->validate($rules);
        if ($field === 'blockedReason' && mb_strlen(RichText::plainText($validated['blockedReason'] ?? '')) > 1000) {
            $this->addError('blockedReason', 'The blocked reason may not be greater than 1000 characters.');

            return;
        }

        if ($field === 'statusId' && $status->requiresReviewer() && $task->reviewer_id === null) {
            $this->addError('statusId', 'Assign a reviewer before moving this task into review.');

            return;
        }

        if ($field === 'statusId' && strtolower(trim($status->name)) === 'blocked' && RichText::isEmpty($task->blocked_reason)) {
            $this->addError('statusId', 'Add a blocked reason before moving this task to Blocked.');

            return;
        }

        $databaseField = match ($field) {
            'statusId' => 'project_status_id',
            'dueDate' => 'due_date',
            'githubUrl' => 'github_url',
            'assigneeId' => 'assignee_id',
            'reviewerId' => 'reviewer_id',
            'cycleId' => 'cycle_id',
            'storyPoints' => 'story_points',
            'blockedReason' => 'blocked_reason',
            default => $field,
        };
        $value = $validated[$field] ?? null;
        $value = in_array($field, ['githubUrl', 'assigneeId', 'reviewerId', 'cycleId', 'storyPoints'], true) && blank($value) ? null : $value;
        $attributes = [$databaseField => $value];

        if ($field === 'statusId' && strtolower(trim($status->name)) !== 'blocked') {
            $attributes['blocked_reason'] = null;
            $this->blockedReason = '';
        }

        $task->update($attributes);
        $this->{$field} = $value === null ? '' : (string) $value;
        $this->editingDetailField = null;
        unset($this->task, $this->activities);
        $this->dispatch('task-modal-refresh');
    }

    public function save(): void
    {
        $project = Project::query()->accessibleTo(auth()->user())->findOrFail($this->projectId);
        $this->authorize('view', $project);
        $this->description = RichText::sanitize($this->description);
        $this->blockedReason = trim(RichText::sanitize($this->blockedReason));
        $statusIsBlocked = strtolower((string) $project->statuses()->whereKey($this->statusId)->value('name')) === 'blocked';
        $targetStatus = $project->statuses()->whereKey($this->statusId)->first();
        $canAssignCycle = $project->team?->members->contains('id', auth()->id()) ?? false;
        $currentStatusId = $this->taskId === null ? null : $project->tasks()->whereKey($this->taskId)->value('project_status_id');
        $currentCycleId = $this->taskId === null ? null : $project->tasks()->whereKey($this->taskId)->value('cycle_id');
        $isEnteringReview = $targetStatus?->requiresReviewer()
            && ($currentStatusId === null || (int) $currentStatusId !== (int) $this->statusId);

        $rules = [
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:20000'],
            'statusId' => [
                'required',
                'integer',
                Rule::exists('project_statuses', 'id')->where('project_id', $project->id),
            ],
            'priority' => ['required', Rule::in(Task::PRIORITIES)],
            'type' => ['required', Rule::in(Task::TYPES)],
            'storyPoints' => ['nullable', 'integer', Rule::in([1, 2, 3, 5, 8, 13, 21])],
            'blockedReason' => [$statusIsBlocked ? 'required' : 'nullable', 'string', 'max:10000'],
            'dueDate' => ['nullable', 'date'],
            'githubUrl' => $this->githubUrlRules(),
            'assigneeId' => ['nullable', 'integer', Rule::in($project->accessibleMemberIds())],
            'reviewerId' => [$isEnteringReview ? 'required' : 'nullable', 'integer', Rule::in($project->accessibleMemberIds())],
        ];

        if ($canAssignCycle) {
            $rules['cycleId'] = ['nullable', 'integer', Rule::exists('cycles', 'id')->where('team_id', $project->team_id)->where(fn ($query) => $query->where(fn ($query) => $query->whereIn('status', ['planned', 'active'])->whereDate('end_date', '>=', today()))->orWhere('id', $currentCycleId ?? 0))];
        }

        $validated = $this->validate($rules);

        if (mb_strlen(RichText::plainText($validated['description'] ?? '')) > 5000) {
            $this->addError('description', 'The description may not be greater than 5000 characters.');

            return;
        }

        if ($statusIsBlocked && RichText::isEmpty($validated['blockedReason'] ?? '')) {
            $this->addError('blockedReason', 'A blocked reason is required.');

            return;
        }

        if (mb_strlen(RichText::plainText($validated['blockedReason'] ?? '')) > 1000) {
            $this->addError('blockedReason', 'The blocked reason may not be greater than 1000 characters.');

            return;
        }

        $attributes = [
            'title' => $validated['title'],
            'description' => $validated['description'],
            'project_status_id' => $validated['statusId'],
            'priority' => $validated['priority'],
            'type' => $validated['type'],
            'story_points' => blank($validated['storyPoints'] ?? null) ? null : $validated['storyPoints'],
            'blocked_reason' => $statusIsBlocked ? $validated['blockedReason'] : null,
            'due_date' => $validated['dueDate'] ?: null,
            'github_url' => $validated['githubUrl'] ?: null,
            'assignee_id' => $validated['assigneeId'] ?: null,
            'reviewer_id' => $validated['reviewerId'] ?: null,
        ];

        if ($canAssignCycle) {
            $attributes['cycle_id'] = $validated['cycleId'] ?: null;
        }

        if ($this->taskId !== null) {
            $task = $project->tasks()->findOrFail($this->taskId);
            $this->authorize('update', $task);
            DB::transaction(fn () => $task->update($attributes));
            session()->flash('status', 'Task updated.');
        } else {
            $this->authorize('create', [Task::class, $project]);
            DB::transaction(fn () => $project->tasks()->create($attributes + ['user_id' => auth()->id()]));
            session()->flash('status', 'Task created.');
        }

        if ($this->modal) {
            $this->dispatch('task-modal-saved');

            return;
        }

        $this->redirectRoute('projects.board', ['project' => $project]);
    }

    /**
     * @return array<int, \Closure|string>
     */
    private function githubUrlRules(): array
    {
        return [
            'nullable',
            'string',
            'max:2048',
            'url:https',
            function (string $attribute, mixed $value, \Closure $fail): void {
                $parts = parse_url($value);

                if (
                    ! is_array($parts)
                    || strtolower((string) ($parts['host'] ?? '')) !== 'github.com'
                    || isset($parts['user'])
                    || isset($parts['pass'])
                ) {
                    $fail('The GitHub reference must be an https://github.com URL.');
                }
            },
        ];
    }
};
?>

<main @class(['mx-auto w-full max-w-3xl flex-1 px-4 py-8 sm:px-6 lg:px-8 lg:py-10' => ! $modal, 'w-full' => $modal])>
    @unless ($modal)
        <a href="{{ route('projects.board', $projectId) }}" wire:navigate class="inline-flex items-center gap-2 rounded text-sm font-medium text-indigo-600 transition hover:text-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:text-indigo-400 dark:hover:text-indigo-300"><span aria-hidden="true">←</span> Back to board</a>
    @endunless

    @if ($modal && $taskId)
        <header class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-white/95 px-5 py-3 backdrop-blur dark:border-slate-800 dark:bg-slate-900/95 sm:px-8">
            <p class="truncate text-sm font-medium text-slate-500 dark:text-slate-400">{{ $this->project->name }} <span class="px-1">/</span> {{ $this->task->id }}</p>
            <button type="button" wire:click="$dispatch('task-modal-close')" aria-label="Close task details" class="ml-4 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-xl text-slate-500 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:hover:bg-slate-800">×</button>
        </header>
        <div class="grid gap-6 p-5 sm:p-8 lg:grid-cols-[minmax(0,1.5fr)_minmax(18rem,0.9fr)]">
            <div class="min-w-0">
                @if ($editingTitle)
                    <div class="flex items-start gap-2">
                        <div class="min-w-0 flex-1"><label for="inlineTitle" class="sr-only">Task title</label><input id="inlineTitle" type="text" wire:model="title" maxlength="180" autofocus @error('title') aria-invalid="true" @enderror class="w-full rounded-lg border border-indigo-400 bg-white px-3 py-2.5 text-xl font-bold text-slate-900 outline-none ring-4 ring-indigo-600/10 dark:bg-slate-950 dark:text-white">@error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror</div>
                        <button type="button" wire:click="saveInlineField('title')" aria-label="Save title" class="rounded-lg bg-indigo-600 px-3 py-2 text-white hover:bg-indigo-700">✓</button><button type="button" wire:click="cancelInlineField('title')" aria-label="Cancel title edit" class="rounded-lg border border-slate-300 px-3 py-2 text-slate-600 dark:border-slate-700 dark:text-slate-300">×</button>
                    </div>
                @else
                    <button type="button" wire:click="editInlineField('title')" class="group w-full rounded text-left text-2xl font-bold tracking-tight text-slate-900 hover:text-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:text-white dark:hover:text-indigo-300"><span>{{ $this->task->title }}</span><span class="ml-2 text-sm font-normal text-slate-400 opacity-0 transition group-hover:opacity-100">Edit</span></button>
                @endif

                <div class="mt-7">
                    <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Description</h2>
                    @if ($editingDescription)
                        <div class="mt-2">
                            <x-rich-text-editor model="description" :value="$description" id="inlineDescription" label="Task description" placeholder="Add a description..." min-height="8rem" class="w-full" />
                            @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            <div class="mt-2 flex gap-2"><button type="button" wire:click="saveInlineField('description')" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-700">✓ Save</button><button type="button" wire:click="cancelInlineField('description')" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-semibold text-slate-600 dark:border-slate-700 dark:text-slate-300">× Cancel</button></div>
                        </div>
                    @else
                        <button type="button" wire:click="editInlineField('description')" class="rich-text-output mt-2 min-h-12 w-full rounded-lg p-2 text-left text-sm leading-6 text-slate-600 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:text-slate-300 dark:hover:bg-slate-800">{!! \App\Support\RichText::sanitize($this->task->description) ?: '<span class="text-slate-400">Add a description...</span>' !!}</button>
                    @endif
                </div>
            </div>
            <div>
    @endif

    @if (! $modal || ! $taskId)
    <div class="{{ $modal ? 'px-5 pt-5 sm:px-8' : 'mt-5' }}">
        <p class="text-sm font-medium text-indigo-600 dark:text-indigo-400">{{ $this->project->name }}</p>
        <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $taskId ? 'Edit task' : 'Create a task' }}</h1>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Keep the next action clear and easy to find.</p>
    </div>
    @endif

    @if ($modal && $taskId)
        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900" aria-label="Task details">
            <header class="flex items-center justify-between border-b border-slate-100 px-4 py-3 dark:border-slate-800"><h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Details</h2><svg class="h-4 w-4 text-slate-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 7.47a.75.75 0 0 1 1.06 0L10 11.19l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.53a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg></header>
            <dl class="divide-y divide-slate-100 px-4 dark:divide-slate-800">
                <div class="grid grid-cols-[6.75rem_minmax(0,1fr)] items-center gap-3 py-3"><dt class="text-sm text-slate-600 dark:text-slate-400">Status</dt><dd class="min-w-0">@if ($editingDetailField === 'statusId')<div class="flex min-w-0 items-center gap-1.5"><select wire:model="statusId" aria-label="Task status" class="min-w-0 flex-1 rounded-md border border-indigo-300 bg-white px-2 py-1.5 text-sm dark:border-indigo-700 dark:bg-slate-950 dark:text-white">@foreach ($this->project->statuses as $status)<option value="{{ $status->id }}">{{ $status->name }}</option>@endforeach</select><button type="button" wire:click="saveDetailField('statusId')" aria-label="Save status" class="rounded-md bg-indigo-600 px-2 py-1 text-white">✓</button><button type="button" wire:click="cancelDetailField('statusId')" aria-label="Cancel status edit" class="rounded-md border border-slate-300 px-2 py-1 text-slate-600 dark:border-slate-700 dark:text-slate-300">×</button></div>@else<button type="button" wire:click="editDetailField('statusId')" class="w-full rounded-md px-2 py-1.5 text-left text-sm font-medium text-slate-800 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800">{{ $this->task->status?->name ?? 'No status' }}</button>@endif @error('statusId')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</dd></div>
                <div class="grid grid-cols-[6.75rem_minmax(0,1fr)] items-center gap-3 py-3"><dt class="text-sm text-slate-600 dark:text-slate-400">Assignee</dt><dd class="min-w-0">@if ($editingDetailField === 'assigneeId')<div class="flex min-w-0 items-center gap-1.5"><select wire:model="assigneeId" aria-label="Task assignee" class="min-w-0 flex-1 rounded-md border border-indigo-300 bg-white px-2 py-1.5 text-sm dark:border-indigo-700 dark:bg-slate-950 dark:text-white"><option value="">Unassigned</option>@foreach ($this->project->accessibleMembers() as $member)<option value="{{ $member->id }}">{{ $member->name }}</option>@endforeach</select><button type="button" wire:click="saveDetailField('assigneeId')" aria-label="Save assignee" class="rounded-md bg-indigo-600 px-2 py-1 text-white">✓</button><button type="button" wire:click="cancelDetailField('assigneeId')" aria-label="Cancel assignee edit" class="rounded-md border border-slate-300 px-2 py-1 text-slate-600 dark:border-slate-700 dark:text-slate-300">×</button></div>@else<button type="button" wire:click="editDetailField('assigneeId')" class="w-full truncate rounded-md px-2 py-1.5 text-left text-sm text-slate-800 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800">{{ $this->task->assignee?->name ?? 'Unassigned' }}</button>@endif @error('assigneeId')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</dd></div>
                <div class="grid grid-cols-[6.75rem_minmax(0,1fr)] items-center gap-3 py-3"><dt class="text-sm text-slate-600 dark:text-slate-400">Priority</dt><dd class="min-w-0">@if ($editingDetailField === 'priority')<div class="flex min-w-0 items-center gap-1.5"><select wire:model="priority" aria-label="Task priority" class="min-w-0 flex-1 rounded-md border border-indigo-300 bg-white px-2 py-1.5 text-sm dark:border-indigo-700 dark:bg-slate-950 dark:text-white"><option value="urgent">Urgent</option><option value="high">High</option><option value="medium">Medium</option><option value="low">Low</option></select><button type="button" wire:click="saveDetailField('priority')" aria-label="Save priority" class="rounded-md bg-indigo-600 px-2 py-1 text-white">✓</button><button type="button" wire:click="cancelDetailField('priority')" aria-label="Cancel priority edit" class="rounded-md border border-slate-300 px-2 py-1 text-slate-600 dark:border-slate-700 dark:text-slate-300">×</button></div>@else<button type="button" wire:click="editDetailField('priority')" class="w-full rounded-md px-2 py-1.5 text-left text-sm capitalize text-slate-800 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800">{{ $this->task->priority }}</button>@endif @error('priority')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</dd></div>
                <div class="grid grid-cols-[6.75rem_minmax(0,1fr)] items-center gap-3 py-3"><dt class="text-sm text-slate-600 dark:text-slate-400">Project</dt><dd class="min-w-0"><a href="{{ route('projects.show', $this->project) }}" wire:navigate class="block truncate px-2 py-1.5 text-sm font-medium text-indigo-600 hover:underline dark:text-indigo-400">{{ $this->project->name }}</a></dd></div>
                <div class="grid grid-cols-[6.75rem_minmax(0,1fr)] items-center gap-3 py-3"><dt class="text-sm text-slate-600 dark:text-slate-400">Type</dt><dd class="min-w-0">@if ($editingDetailField === 'type')<div class="flex min-w-0 items-center gap-1.5"><select wire:model="type" aria-label="Task type" class="min-w-0 flex-1 rounded-md border border-indigo-300 bg-white px-2 py-1.5 text-sm dark:border-indigo-700 dark:bg-slate-950 dark:text-white"><option value="task">Task</option><option value="bug">Bug</option><option value="feature">Feature</option></select><button type="button" wire:click="saveDetailField('type')" aria-label="Save task type" class="rounded-md bg-indigo-600 px-2 py-1 text-white">✓</button><button type="button" wire:click="cancelDetailField('type')" aria-label="Cancel task type edit" class="rounded-md border border-slate-300 px-2 py-1 text-slate-600 dark:border-slate-700 dark:text-slate-300">×</button></div>@else<button type="button" wire:click="editDetailField('type')" class="w-full rounded-md px-2 py-1.5 text-left text-sm capitalize text-slate-800 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800">{{ $this->task->type }}</button>@endif @error('type')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</dd></div>
                <div class="grid grid-cols-[6.75rem_minmax(0,1fr)] items-center gap-3 py-3"><dt class="text-sm text-slate-600 dark:text-slate-400">Due date</dt><dd class="min-w-0">@if ($editingDetailField === 'dueDate')<div class="flex min-w-0 items-center gap-1.5"><input type="date" wire:model="dueDate" aria-label="Task due date" class="min-w-0 flex-1 rounded-md border border-indigo-300 bg-white px-2 py-1.5 text-sm dark:border-indigo-700 dark:bg-slate-950 dark:text-white"><button type="button" wire:click="saveDetailField('dueDate')" aria-label="Save due date" class="rounded-md bg-indigo-600 px-2 py-1 text-white">✓</button><button type="button" wire:click="cancelDetailField('dueDate')" aria-label="Cancel due date edit" class="rounded-md border border-slate-300 px-2 py-1 text-slate-600 dark:border-slate-700 dark:text-slate-300">×</button></div>@else<button type="button" wire:click="editDetailField('dueDate')" class="w-full rounded-md px-2 py-1.5 text-left text-sm text-slate-800 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800">{{ $this->task->due_date?->format('M j, Y') ?? 'Add due date' }}</button>@endif @error('dueDate')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</dd></div>
                <div class="grid grid-cols-[6.75rem_minmax(0,1fr)] items-center gap-3 py-3"><dt class="text-sm text-slate-600 dark:text-slate-400">GitHub reference</dt><dd class="min-w-0">@if ($editingDetailField === 'githubUrl')<div class="flex min-w-0 items-center gap-1.5"><input type="url" wire:model="githubUrl" aria-label="GitHub reference URL" placeholder="https://github.com/owner/repo/pull/123" class="min-w-0 flex-1 rounded-md border border-indigo-300 bg-white px-2 py-1.5 text-sm dark:border-indigo-700 dark:bg-slate-950 dark:text-white"><button type="button" wire:click="saveDetailField('githubUrl')" aria-label="Save GitHub reference" class="rounded-md bg-indigo-600 px-2 py-1 text-white">✓</button><button type="button" wire:click="cancelDetailField('githubUrl')" aria-label="Cancel GitHub reference edit" class="rounded-md border border-slate-300 px-2 py-1 text-slate-600 dark:border-slate-700 dark:text-slate-300">×</button></div>@else<div class="flex min-w-0 items-center gap-1">@if ($this->task->github_url)<a href="{{ $this->task->github_url }}" target="_blank" rel="noopener noreferrer" class="min-w-0 flex-1 truncate rounded-md px-2 py-1.5 text-sm text-indigo-600 hover:underline dark:text-indigo-400">{{ $this->task->github_url }}</a>@else<span class="min-w-0 flex-1 truncate px-2 py-1.5 text-sm text-slate-500 dark:text-slate-400">No GitHub reference</span>@endif<button type="button" wire:click="editDetailField('githubUrl')" aria-label="Edit GitHub reference" class="rounded-md px-2 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">Edit</button></div>@endif @error('githubUrl')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</dd></div>
                <div class="grid grid-cols-[6.75rem_minmax(0,1fr)] items-center gap-3 py-3"><dt class="text-sm text-slate-600 dark:text-slate-400">Reviewer</dt><dd class="min-w-0">@if ($editingDetailField === 'reviewerId')<div class="flex min-w-0 items-center gap-1.5"><select wire:model="reviewerId" aria-label="Task reviewer" class="min-w-0 flex-1 rounded-md border border-indigo-300 bg-white px-2 py-1.5 text-sm dark:border-indigo-700 dark:bg-slate-950 dark:text-white"><option value="">No reviewer</option>@foreach ($this->project->accessibleMembers() as $member)<option value="{{ $member->id }}">{{ $member->name }}</option>@endforeach</select><button type="button" wire:click="saveDetailField('reviewerId')" aria-label="Save reviewer" class="rounded-md bg-indigo-600 px-2 py-1 text-white">✓</button><button type="button" wire:click="cancelDetailField('reviewerId')" aria-label="Cancel reviewer edit" class="rounded-md border border-slate-300 px-2 py-1 text-slate-600 dark:border-slate-700 dark:text-slate-300">×</button></div>@else<button type="button" wire:click="editDetailField('reviewerId')" class="w-full truncate rounded-md px-2 py-1.5 text-left text-sm text-slate-800 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800">{{ $this->task->reviewer?->name ?? 'Unassigned' }}</button>@endif @error('reviewerId')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</dd></div>
                <div class="grid grid-cols-[6.75rem_minmax(0,1fr)] items-center gap-3 py-3"><dt class="text-sm text-slate-600 dark:text-slate-400">Reporter</dt><dd class="truncate px-2 py-1.5 text-sm text-slate-800 dark:text-slate-200">{{ $this->task->user?->name ?? 'Former member' }}</dd></div>
                @if ($this->canAssignCycle)
                    <div class="grid grid-cols-[6.75rem_minmax(0,1fr)] items-center gap-3 py-3"><dt class="text-sm text-slate-600 dark:text-slate-400">Sprint</dt><dd class="min-w-0">@if ($editingDetailField === 'cycleId')<div class="flex min-w-0 items-center gap-1.5"><select wire:model="cycleId" aria-label="Task sprint" class="min-w-0 flex-1 rounded-md border border-indigo-300 bg-white px-2 py-1.5 text-sm dark:border-indigo-700 dark:bg-slate-950 dark:text-white"><option value="">No sprint</option>@foreach ($this->availableCycles as $cycle)<option value="{{ $cycle->id }}">{{ $cycle->name }}</option>@endforeach</select><button type="button" wire:click="saveDetailField('cycleId')" aria-label="Save sprint" class="rounded-md bg-indigo-600 px-2 py-1 text-white">✓</button><button type="button" wire:click="cancelDetailField('cycleId')" aria-label="Cancel sprint edit" class="rounded-md border border-slate-300 px-2 py-1 text-slate-600 dark:border-slate-700 dark:text-slate-300">×</button></div>@else<button type="button" wire:click="editDetailField('cycleId')" class="w-full truncate rounded-md px-2 py-1.5 text-left text-sm text-indigo-600 hover:bg-slate-50 dark:text-indigo-400 dark:hover:bg-slate-800">{{ $this->task->cycle?->name ?? 'Add to sprint' }}</button>@endif @error('cycleId')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</dd></div>
                @endif
                <div class="grid grid-cols-[6.75rem_minmax(0,1fr)] items-start gap-3 py-3"><dt class="pt-1 text-sm text-slate-600 dark:text-slate-400">Blocked reason</dt><dd class="min-w-0">@if ($editingDetailField === 'blockedReason')<div class="space-y-2"><x-rich-text-editor model="blockedReason" :value="$blockedReason" id="inlineBlockedReason" label="Blocked reason" placeholder="What is blocking this task?" min-height="5rem" compact /><div class="flex justify-end gap-1.5"><button type="button" wire:click="saveDetailField('blockedReason')" aria-label="Save blocked reason" class="rounded-md bg-indigo-600 px-2 py-1 text-white">✓</button><button type="button" wire:click="cancelDetailField('blockedReason')" aria-label="Cancel blocked reason edit" class="rounded-md border border-slate-300 px-2 py-1 text-slate-600 dark:border-slate-700 dark:text-slate-300">×</button></div></div>@else<button type="button" wire:click="editDetailField('blockedReason')" class="rich-text-output w-full whitespace-pre-line rounded-md px-2 py-1.5 text-left text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800">{!! \App\Support\RichText::sanitize($this->task->blocked_reason) ?: 'Add blocked reason' !!}</button>@endif @error('blockedReason')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</dd></div>
                <div class="grid grid-cols-[6.75rem_minmax(0,1fr)] items-center gap-3 py-3"><dt class="text-sm text-slate-600 dark:text-slate-400">Estimate</dt><dd class="min-w-0">@if ($editingDetailField === 'storyPoints')<div class="flex min-w-0 items-center gap-1.5"><select wire:model="storyPoints" aria-label="Story point estimate" class="min-w-0 flex-1 rounded-md border border-indigo-300 bg-white px-2 py-1.5 text-sm dark:border-indigo-700 dark:bg-slate-950 dark:text-white"><option value="">No estimate</option>@foreach ([1, 2, 3, 5, 8, 13, 21] as $points)<option value="{{ $points }}">{{ $points }} points</option>@endforeach</select><button type="button" wire:click="saveDetailField('storyPoints')" aria-label="Save estimate" class="rounded-md bg-indigo-600 px-2 py-1 text-white">✓</button><button type="button" wire:click="cancelDetailField('storyPoints')" aria-label="Cancel estimate edit" class="rounded-md border border-slate-300 px-2 py-1 text-slate-600 dark:border-slate-700 dark:text-slate-300">×</button></div>@else<button type="button" wire:click="editDetailField('storyPoints')" class="w-full rounded-md px-2 py-1.5 text-left text-sm text-slate-800 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800">{{ $this->task->story_points ? $this->task->story_points.' points' : 'Add estimate' }}</button>@endif @error('storyPoints')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</dd></div>
            </dl>
        </section>
    @else
    <form wire:submit="save" class="{{ $modal && $taskId ? 'space-y-5' : 'mt-7 space-y-6' }} rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 dark:border-slate-800 dark:bg-slate-900">
        @if (! $modal || ! $taskId)
        <div>
            <label for="title" class="block text-sm font-medium text-slate-900 dark:text-slate-200">Task title</label>
            <input id="title" type="text" wire:model="title" maxlength="180" required autofocus placeholder="Write the project brief" @error('title') aria-describedby="title-error" aria-invalid="true" @enderror class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:placeholder:text-slate-500">
            @error('title') <p id="title-error" class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>
        @endif

        @if (! $modal || ! $taskId)
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-900 dark:text-slate-200">Description <span class="font-normal text-slate-500">(optional)</span></label>
            <x-rich-text-editor model="description" :value="$description" id="description" label="Task description" placeholder="Add context, notes, or acceptance criteria." min-height="8rem" />
            @error('description') <p id="description-error" class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>
        @endif

        <div @class([
            'grid gap-5',
            'grid-cols-1' => $modal && $taskId,
            'sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4' => ! ($modal && $taskId),
        ])>
            <div>
                <label for="type" class="block text-sm font-medium text-slate-900 dark:text-slate-200">Type</label>
                <select id="type" wire:model="type" required @error('type') aria-describedby="type-error" aria-invalid="true" @enderror class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="task">Task</option>
                    <option value="bug">Bug</option>
                    <option value="feature">Feature</option>
                </select>
                @error('type') <p id="type-error" class="mt-1.5 text-sm text-red-600 dark:text-red-400">Choose a supported task type.</p> @enderror
            </div>
            <div>
                <label for="statusId" class="block text-sm font-medium text-slate-900 dark:text-slate-200">Workflow status</label>
                <select id="statusId" wire:model="statusId" required @error('statusId') aria-describedby="status-id-error" aria-invalid="true" @enderror class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    @foreach ($this->project->statuses as $status)
                        <option value="{{ $status->id }}">{{ $status->name }}</option>
                    @endforeach
                </select>
                @error('statusId') <p id="status-id-error" class="mt-1.5 text-sm text-red-600 dark:text-red-400">Choose a status from this project.</p> @enderror
            </div>

            <div>
                <label for="priority" class="block text-sm font-medium text-slate-900 dark:text-slate-200">Priority</label>
                <select id="priority" wire:model="priority" required @error('priority') aria-describedby="priority-error" aria-invalid="true" @enderror class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                </select>
                @error('priority') <p id="priority-error" class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="storyPoints" class="block text-sm font-medium text-slate-900 dark:text-slate-200">Story point estimate <span class="font-normal text-slate-500">(optional)</span></label>
                <select id="storyPoints" wire:model="storyPoints" @error('storyPoints') aria-describedby="story-points-error" aria-invalid="true" @enderror class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="">No estimate</option>
                    @foreach ([1, 2, 3, 5, 8, 13, 21] as $points)<option value="{{ $points }}">{{ $points }} points</option>@endforeach
                </select>
                @error('storyPoints') <p id="story-points-error" class="mt-1.5 text-sm text-red-600 dark:text-red-400">Choose a supported story point estimate.</p> @enderror
            </div>

            <div>
                <label for="dueDate" class="block text-sm font-medium text-slate-900 dark:text-slate-200">Due date <span class="font-normal text-slate-500">(optional)</span></label>
                <input id="dueDate" type="date" wire:model="dueDate" @error('dueDate') aria-describedby="due-date-error" aria-invalid="true" @enderror class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                @error('dueDate') <p id="due-date-error" class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="githubUrl" class="block text-sm font-medium text-slate-900 dark:text-slate-200">GitHub reference <span class="font-normal text-slate-500">(optional)</span></label>
                <input id="githubUrl" type="url" wire:model="githubUrl" placeholder="https://github.com/owner/repo/pull/123" @error('githubUrl') aria-describedby="github-url-error" aria-invalid="true" @enderror class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                @error('githubUrl') <p id="github-url-error" class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">Use an HTTPS link on github.com.</p>
            </div>

            <div>
                <label for="assigneeId" class="block text-sm font-medium text-slate-900 dark:text-slate-200">Assignee</label>
                <select id="assigneeId" wire:model="assigneeId" @error('assigneeId') aria-describedby="assignee-error" aria-invalid="true" @enderror class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="">Unassigned</option>
                    @foreach ($this->project->accessibleMembers() as $member)
                        <option value="{{ $member->id }}">{{ $member->id === auth()->id() ? 'Assign to me' : $member->name }}</option>
                    @endforeach
                </select>
                @error('assigneeId') <p id="assignee-error" class="mt-1.5 text-sm text-red-600 dark:text-red-400">Choose someone who has access to this project.</p> @enderror
                <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">Only project members can be assigned.</p>
            </div>

            <div>
                <label for="reviewerId" class="block text-sm font-medium text-slate-900 dark:text-slate-200">Reviewer <span class="font-normal text-slate-500">(required for review)</span></label>
                <select id="reviewerId" wire:model="reviewerId" @error('reviewerId') aria-describedby="reviewer-error" aria-invalid="true" @enderror class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="">No reviewer</option>
                    @foreach ($this->project->accessibleMembers() as $member)
                        <option value="{{ $member->id }}">{{ $member->id === auth()->id() ? 'Assign review to me' : $member->name }}</option>
                    @endforeach
                </select>
                @error('reviewerId') <p id="reviewer-error" class="mt-1.5 text-sm text-red-600 dark:text-red-400">Choose a project member before moving this task into review.</p> @enderror
            </div>
            @if ($this->canAssignCycle)
                <div>
                    <label for="cycleId" class="block text-sm font-medium text-slate-900 dark:text-slate-200">Team sprint <span class="font-normal text-slate-500">(optional)</span></label>
                    <select id="cycleId" wire:model="cycleId" @error('cycleId') aria-describedby="cycle-error" aria-invalid="true" @enderror class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        <option value="">No sprint</option>
                        @foreach ($this->availableCycles as $cycle)
                            <option value="{{ $cycle->id }}">{{ $cycle->name }} · {{ $cycle->start_date->format('M j') }}–{{ $cycle->end_date->format('M j') }}</option>
                        @endforeach
                    </select>
                    @error('cycleId') <p id="cycle-error" class="mt-1.5 text-sm text-red-600 dark:text-red-400">Choose an active or upcoming sprint from this team.</p> @enderror
                </div>
            @endif
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-900 dark:text-slate-200">Blocked reason <span class="font-normal text-slate-500">(required for Blocked status)</span></label>
            <x-rich-text-editor model="blockedReason" :value="$blockedReason" id="blockedReason" label="Blocked reason" placeholder="What is preventing this task from moving forward?" min-height="5rem" compact />
            @error('blockedReason') <p id="blocked-reason-error" class="mt-1.5 text-sm text-red-600 dark:text-red-400">Add a reason before setting this task to Blocked.</p> @enderror
        </div>

        <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end dark:border-slate-800">
            @if ($modal)
                <button type="button" wire:click="$dispatch('task-modal-close')" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">Cancel</button>
            @else
                <a href="{{ route('projects.board', $projectId) }}" wire:navigate class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">Cancel</a>
            @endif
            <button type="submit" wire:loading.attr="disabled" wire:target="save" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 dark:focus-visible:ring-offset-slate-900">
                <span wire:loading.remove wire:target="save">{{ $taskId ? 'Save task' : 'Create task' }}</span>
                <span wire:loading wire:target="save">Saving...</span>
            </button>
        </div>
    </form>
    @endif
    @if ($modal && $taskId)
            </div>
        </div>
    @endif

    @if ($taskId)
        <section class="mt-6 grid gap-6 lg:grid-cols-2" aria-label="Task discussion and history">
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Comments</h2>
                <form wire:submit="addComment" class="mt-4">
                    <x-rich-text-editor model="commentBody" :value="$commentBody" id="commentBody" label="Add a comment" placeholder="Add context or a question..." min-height="6rem" />
                    @error('commentBody') <p id="comment-body-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    <div class="mt-3 flex justify-end"><button type="submit" wire:loading.attr="disabled" wire:target="addComment" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60"><span wire:loading.remove wire:target="addComment">Comment</span><span wire:loading wire:target="addComment">Posting...</span></button></div>
                </form>
                <ol class="mt-5 space-y-4">
                    @forelse ($this->comments as $comment)
                        <li wire:key="task-comment-{{ $comment->id }}" class="border-t border-slate-100 pt-4 dark:border-slate-800">
                            <div class="flex items-baseline justify-between gap-3"><p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $comment->user?->name ?? 'Former member' }}</p><time class="shrink-0 text-xs text-slate-500 dark:text-slate-400" datetime="{{ $comment->created_at->toIso8601String() }}">{{ $comment->created_at->diffForHumans() }}</time></div>
                            <div class="rich-text-output mt-2 break-words text-sm leading-6 text-slate-700 dark:text-slate-300">{!! \App\Support\RichText::sanitize($comment->body) !!}</div>
                        </li>
                    @empty
                        <li class="border-t border-slate-100 pt-4 text-sm text-slate-500 dark:border-slate-800 dark:text-slate-400">No comments yet.</li>
                    @endforelse
                </ol>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Activity</h2>
                <ol class="mt-4 space-y-4">
                    @forelse ($this->activities as $activity)
                        <li wire:key="task-activity-{{ $activity->id }}" class="flex gap-3 border-l border-slate-200 pl-4 dark:border-slate-700">
                            <div class="min-w-0"><p class="text-sm text-slate-700 dark:text-slate-300"><span class="font-semibold text-slate-900 dark:text-white">{{ $activity->actor?->name ?? 'System' }}</span> {{ $activity->summary() }}</p><time class="mt-1 block text-xs text-slate-500 dark:text-slate-400" datetime="{{ $activity->created_at->toIso8601String() }}">{{ $activity->created_at->diffForHumans() }}</time></div>
                        </li>
                    @empty
                        <li class="text-sm text-slate-500 dark:text-slate-400">No activity yet.</li>
                    @endforelse
                </ol>
            </div>
        </section>
    @endif
</main>
