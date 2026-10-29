<?php

use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\User;
use App\Support\RichText;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Project details · TaskFlow')] class extends Component
{
    #[Locked]
    public ?int $projectId = null;

    public string $name = '';

    public string $description = '';

    public string $status = 'active';

    public string $teamId = '';

    public string $memberEmail = '';

    public string $memberRole = 'member';

    public ?string $memberMessage = null;

    public ?string $editingField = null;

    public ?string $projectMessage = null;

    public string $statusName = '';

    public string $statusColor = 'slate';

    public ?int $editingStatusId = null;

    public string $completionStatusId = '';

    public ?string $statusMessage = null;

    public string $statusMessageType = 'success';

    #[Computed]
    public function project(): ?Project
    {
        return $this->projectId === null
            ? null
            : Project::query()->accessibleTo(auth()->user())->with(['team.members', 'members', 'user'])->findOrFail($this->projectId);
    }

    public function addMember(): void
    {
        $project = Project::query()->accessibleTo(auth()->user())->findOrFail($this->projectId);
        $this->authorize('update', $project);

        $validated = $this->validate([
            'memberEmail' => ['required', 'email', 'max:255', Rule::exists('users', 'email')],
            'memberRole' => ['required', Rule::in(['member', 'admin'])],
        ]);

        $member = User::query()->where('email', $validated['memberEmail'])->firstOrFail();

        if ($member->id === $project->user_id || $project->members()->whereKey($member->id)->exists()) {
            $this->addError('memberEmail', 'This person already has direct project access.');

            return;
        }

        if ($project->team && $project->team->members()->whereKey($member->id)->exists()) {
            $this->addError('memberEmail', 'This person already has access through the team.');

            return;
        }

        $project->members()->attach($member->id, ['role' => $validated['memberRole']]);
        $this->reset('memberEmail');
        $this->memberRole = 'member';
        $this->memberMessage = $member->name.' was added to this project.';
        unset($this->project);
    }

    public function removeMember(int $memberId): void
    {
        $project = Project::query()->accessibleTo(auth()->user())->findOrFail($this->projectId);
        $this->authorize('update', $project);
        $member = $project->members()->whereKey($memberId)->firstOrFail();
        $project->members()->detach($memberId);

        $this->memberMessage = $project->team?->members()->whereKey($memberId)->exists()
            ? $member->name.' no longer has direct project access but remains a team member.'
            : $member->name.' was removed from this project.';
        unset($this->project);
    }

    public function deleteProject(): void
    {
        $project = Project::query()->accessibleTo(auth()->user())->findOrFail($this->projectId);
        $this->authorize('delete', $project);
        $project->delete();

        session()->flash('status', 'Project deleted.');
        $this->redirectRoute('projects.index');
    }

    public function startEditingField(string $field): void
    {
        abort_unless(in_array($field, ['name', 'description', 'status', 'teamId'], true), 404);
        $project = Project::query()->accessibleTo(auth()->user())->findOrFail($this->projectId);
        $this->authorize('update', $project);
        $this->editingField = $field;
        $this->resetValidation($field);
    }

    public function cancelEditingField(string $field): void
    {
        abort_unless(in_array($field, ['name', 'description', 'status', 'teamId'], true), 404);
        $project = Project::query()->accessibleTo(auth()->user())->findOrFail($this->projectId);
        $this->authorize('update', $project);

        $this->{$field} = match ($field) {
            'name' => $project->name,
            'description' => $project->description ?? '',
            'status' => $project->status,
            'teamId' => (string) ($project->team_id ?? ''),
        };
        $this->editingField = null;
        $this->resetValidation($field);
    }

    public function saveField(string $field): void
    {
        abort_unless(in_array($field, ['name', 'description', 'status', 'teamId'], true), 404);
        $project = Project::query()->accessibleTo(auth()->user())->findOrFail($this->projectId);
        $this->authorize('update', $project);

        if ($field === 'name') {
            $this->name = trim($this->name);
        } elseif ($field === 'description') {
            $this->description = RichText::sanitize($this->description);
        }

        $rules = match ($field) {
            'name' => ['name' => ['required', 'string', 'max:120']],
            'description' => ['description' => ['nullable', 'string', 'max:10000']],
            'status' => ['status' => ['required', Rule::in(['active', 'archived'])]],
            'teamId' => ['teamId' => ['nullable', 'integer', Rule::exists('team_members', 'team_id')->where('user_id', auth()->id())]],
        };
        $validated = $this->validate($rules);

        if ($field === 'description' && mb_strlen(RichText::plainText($validated['description'] ?? '')) > 2000) {
            $this->addError('description', 'The description may not be greater than 2000 characters.');

            return;
        }

        if ($field === 'teamId') {
            $nextTeamId = blank($validated['teamId']) ? null : (int) $validated['teamId'];

            if ($project->team_id !== $nextTeamId && $project->tasks()->whereNotNull('cycle_id')->exists()) {
                $this->addError('teamId', 'Move these tasks out of their current team cycles before changing the project team.');

                return;
            }

            $project->update(['team_id' => $nextTeamId]);
            $this->teamId = (string) ($nextTeamId ?? '');
        } else {
            $project->update([$field => $validated[$field] ?: ($field === 'description' ? null : $validated[$field])]);
            $this->{$field} = $validated[$field] ?? '';
        }

        $this->editingField = null;
        $this->projectMessage = ucfirst($field === 'teamId' ? 'team' : $field).' updated.';
        $this->resetValidation($field);
        unset($this->project);
    }

    public function mount(?int $project = null): void
    {
        if ($project === null) {
            $this->authorize('create', Project::class);

            $requestedTeam = request()->query('team');
            if (is_string($requestedTeam) && ctype_digit($requestedTeam) && auth()->user()->teams()->whereKey((int) $requestedTeam)->exists()) {
                $this->teamId = $requestedTeam;
            }

            return;
        }

        $record = Project::query()->accessibleTo(auth()->user())->findOrFail($project);
        $this->authorize('update', $record);

        $this->projectId = $record->id;
        $this->name = $record->name;
        $this->description = $record->description ?? '';
        $this->status = $record->status;
        $this->teamId = (string) ($record->team_id ?? '');
        $this->completionStatusId = (string) ($record->statuses()->where('is_complete', true)->value('id') ?? $record->statuses()->value('id'));
    }

    public function startEditingStatus(int $statusId): void
    {
        $project = Project::query()->accessibleTo(auth()->user())->findOrFail($this->projectId);
        $this->authorize('update', $project);
        $status = $project->statuses()->findOrFail($statusId);
        $this->editingStatusId = $status->id;
        $this->statusName = $status->name;
        $this->statusColor = $status->color ?? 'slate';
        $this->resetValidation();
    }

    public function saveStatus(): void
    {
        $project = Project::query()->accessibleTo(auth()->user())->findOrFail($this->projectId);
        $this->authorize('update', $project);
        $this->statusName = trim($this->statusName);
        $status = $this->editingStatusId ? $project->statuses()->findOrFail($this->editingStatusId) : null;
        $validated = $this->validate([
            'statusName' => ['required', 'string', 'max:80', Rule::unique('project_statuses', 'name')->where('project_id', $project->id)->ignore($status?->id)],
            'statusColor' => ['required', Rule::in(['slate', 'blue', 'emerald', 'amber', 'red', 'gray', 'violet', 'rose'])],
        ]);

        if ($status) {
            $status->update(['name' => $validated['statusName'], 'color' => $validated['statusColor']]);
            $this->feedback('Workflow column updated.');
        } else {
            $project->statuses()->create([
                'name' => $validated['statusName'],
                'color' => $validated['statusColor'],
                'position' => (int) $project->statuses()->max('position') + 1,
                'is_complete' => false,
            ]);
            $this->feedback('Workflow column added.');
        }

        $this->reset('statusName', 'editingStatusId');
        $this->statusColor = 'slate';
        unset($this->project);
    }

    public function cancelStatusEdit(): void
    {
        $this->reset('statusName', 'editingStatusId');
        $this->statusColor = 'slate';
        $this->resetValidation();
    }

    public function moveStatus(int $statusId, string $direction): void
    {
        $project = Project::query()->accessibleTo(auth()->user())->findOrFail($this->projectId);
        $this->authorize('update', $project);

        if (! in_array($direction, ['up', 'down'], true)) {
            return;
        }

        $statuses = $project->statuses()->get();
        $index = $statuses->search(fn (ProjectStatus $status) => $status->id === $statusId);
        abort_if($index === false, 404);
        $swapIndex = $direction === 'up' ? $index - 1 : $index + 1;

        if (! $statuses->has($swapIndex)) {
            return;
        }

        DB::transaction(function () use ($statuses, $index, $swapIndex): void {
            $current = $statuses[$index];
            $neighbor = $statuses[$swapIndex];
            $position = $current->position;
            $current->update(['position' => $neighbor->position]);
            $neighbor->update(['position' => $position]);
        });

        unset($this->project);
    }

    public function setCompletionStatus(): void
    {
        $project = Project::query()->accessibleTo(auth()->user())->findOrFail($this->projectId);
        $this->authorize('update', $project);
        $validated = $this->validate([
            'completionStatusId' => ['required', 'integer', Rule::exists('project_statuses', 'id')->where('project_id', $project->id)],
        ]);

        DB::transaction(function () use ($project, $validated): void {
            $project->statuses()->update(['is_complete' => false]);
            $project->statuses()->whereKey($validated['completionStatusId'])->update(['is_complete' => true]);
        });

        $this->feedback('Completion column saved.');
        unset($this->project);
    }

    public function deleteStatus(int $statusId): void
    {
        $project = Project::query()->accessibleTo(auth()->user())->findOrFail($this->projectId);
        $this->authorize('update', $project);
        $status = $project->statuses()->findOrFail($statusId);

        if ($status->tasks()->exists()) {
            $this->feedback('Move this column’s tasks to another column before deleting it.', 'error');

            return;
        }

        if ($status->is_complete && $project->statuses()->where('is_complete', true)->count() <= 1) {
            $this->feedback('Choose another completion column before deleting this one.', 'error');

            return;
        }

        $status->delete();
        $this->feedback('Workflow column deleted.');
        unset($this->project);
    }

    private function feedback(string $message, string $type = 'success'): void
    {
        $this->statusMessage = $message;
        $this->statusMessageType = $type;
    }

    public function save(): void
    {
        $this->description = RichText::sanitize($this->description);
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:10000'],
            'status' => ['required', Rule::in(['active', 'archived'])],
            'teamId' => ['nullable', 'integer', Rule::exists('team_members', 'team_id')->where('user_id', auth()->id())],
        ]);

        if (mb_strlen(RichText::plainText($validated['description'] ?? '')) > 2000) {
            $this->addError('description', 'The description may not be greater than 2000 characters.');

            return;
        }

        $validated['team_id'] = $validated['teamId'] ?: null;
        unset($validated['teamId']);

        if ($this->projectId !== null) {
            $project = Project::query()->accessibleTo(auth()->user())->findOrFail($this->projectId);
            $this->authorize('update', $project);

            $nextTeamId = $validated['team_id'] ? (int) $validated['team_id'] : null;
            if ($project->team_id !== $nextTeamId && $project->tasks()->whereNotNull('cycle_id')->exists()) {
                $this->addError('teamId', 'Move these tasks out of their current team cycles before changing the project team.');

                return;
            }

            $project->update($validated);

            session()->flash('status', 'Project updated.');
        } else {
            $project = DB::transaction(function () use ($validated): Project {
                $project = auth()->user()->projects()->create($validated);
                $project->statuses()->createMany(Project::DEFAULT_WORKFLOW_STATUSES);

                return $project;
            });

            session()->flash('status', 'Project created with its default workflow columns.');
        }

        $this->redirectRoute('projects.show', ['project' => $project]);
    }
};
?>

<main class="mx-auto w-full max-w-[100rem] flex-1 px-4 py-7 sm:px-6 lg:px-8 lg:py-9">
    @if ($projectId)
        <x-projects.tabs :project="$this->project" active="settings" />
    @endif

    @if (! $projectId)
    <a href="{{ route('projects.index') }}" wire:navigate class="mt-4 inline-flex items-center gap-2 rounded text-sm font-medium text-indigo-600 transition hover:text-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:text-indigo-400 dark:hover:text-indigo-300">
        <span aria-hidden="true">←</span>
        Back to projects
    </a>

    <div class="mx-auto mt-5 max-w-3xl">
        <p class="text-sm font-medium text-indigo-600 dark:text-indigo-400">Project workspace</p>
        <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Create a project</h1>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Give your work a clear name and a home of its own.</p>
    </div>

    <form wire:submit="save" class="mx-auto mt-7 max-w-3xl space-y-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 dark:border-slate-800 dark:bg-slate-900">
        <div>
            <label for="name" class="block text-sm font-medium text-slate-900 dark:text-slate-200">Project name</label>
            <input id="name" type="text" wire:model="name" maxlength="120" required autofocus placeholder="Website redesign" @error('name') aria-describedby="name-error" aria-invalid="true" @enderror class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:placeholder:text-slate-500">
            @error('name') <p id="name-error" class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-900 dark:text-slate-200">Description <span class="font-normal text-slate-500">(optional)</span></label>
            <x-rich-text-editor model="description" :value="$description" id="project-description" label="Project description" placeholder="What is this project about?" min-height="8rem" />
            @error('description') <p id="description-error" class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="status" class="block text-sm font-medium text-slate-900 dark:text-slate-200">Project status</label>
            <select id="status" wire:model="status" required @error('status') aria-describedby="status-error" aria-invalid="true" @enderror class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                <option value="active">Active</option>
                <option value="archived">Archived</option>
            </select>
            @error('status') <p id="status-error" class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="teamId" class="block text-sm font-medium text-slate-900 dark:text-slate-200">Team <span class="font-normal text-slate-500">(optional)</span></label>
            <select id="teamId" wire:model="teamId" @error('teamId') aria-describedby="team-id-error" aria-invalid="true" @enderror class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                <option value="">Personal project</option>
                @foreach (auth()->user()->teams()->orderBy('name')->get() as $team)
                    <option value="{{ $team->id }}">{{ $team->name }}</option>
                @endforeach
            </select>
            @error('teamId') <p id="team-id-error" class="mt-1.5 text-sm text-red-600 dark:text-red-400">Choose a team you belong to.</p> @enderror
            <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">Everyone on the team can access its projects. You can also share a personal project with individual people.</p>
        </div>

        <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end dark:border-slate-800">
            <a href="{{ $projectId ? route('projects.show', $projectId) : route('projects.index') }}" wire:navigate class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">Cancel</a>
            <button type="submit" wire:loading.attr="disabled" wire:target="save" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 dark:focus-visible:ring-offset-slate-900">
                <span wire:loading.remove wire:target="save">{{ $projectId ? 'Save changes' : 'Create project' }}</span>
                <span wire:loading wire:target="save">Saving...</span>
            </button>
        </div>
    </form>
    @else
        <a href="{{ route('projects.show', $projectId) }}" wire:navigate class="mt-4 inline-flex items-center gap-2 rounded text-sm font-medium text-indigo-600 transition hover:text-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:text-indigo-400 dark:hover:text-indigo-300"><span aria-hidden="true">←</span> Back to project overview</a>
        <div class="mx-auto mt-5 max-w-6xl"><p class="text-sm font-medium text-indigo-600 dark:text-indigo-400">Project workspace</p><h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Project settings</h1><p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Manage project details, workflow, and access.</p></div>
        @if ($projectMessage)<p role="status" class="mx-auto mt-5 max-w-6xl rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">{{ $projectMessage }}</p>@endif
        <section class="mx-auto mt-6 max-w-6xl rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7 dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-4"><h2 class="text-lg font-semibold text-slate-900 dark:text-white">Project profile</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Select a value to change that setting.</p></div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                <div class="grid gap-2 py-4 sm:grid-cols-[10rem_minmax(0,1fr)]"><h3 class="pt-2 text-sm font-medium text-slate-600 dark:text-slate-400">Project name</h3>@if ($editingField === 'name')<div><div class="flex items-start gap-2"><input id="projectName" type="text" wire:model="name" maxlength="120" autofocus @error('name') aria-invalid="true" @enderror class="min-w-0 flex-1 rounded-lg border border-indigo-400 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none ring-4 ring-indigo-600/10 dark:bg-slate-950 dark:text-white"><button type="button" wire:click="saveField('name')" aria-label="Save project name" class="rounded-lg bg-indigo-600 px-3 py-2 text-white hover:bg-indigo-700">✓</button><button type="button" wire:click="cancelEditingField('name')" aria-label="Cancel project name edit" class="rounded-lg border border-slate-300 px-3 py-2 text-slate-600 dark:border-slate-700 dark:text-slate-300">×</button></div>@error('name')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror</div>@else<button type="button" wire:click="startEditingField('name')" class="group flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-left hover:bg-slate-50 dark:hover:bg-slate-800"><span class="font-medium text-slate-900 dark:text-white">{{ $this->project->name }}</span><span class="text-xs text-indigo-600 opacity-0 group-hover:opacity-100 dark:text-indigo-400">Edit</span></button>@endif</div>
                <div class="grid gap-2 py-4 sm:grid-cols-[10rem_minmax(0,1fr)]"><h3 class="pt-2 text-sm font-medium text-slate-600 dark:text-slate-400">Description</h3>@if ($editingField === 'description')<div class="min-w-0"><div class="flex items-start gap-2"><x-rich-text-editor model="description" :value="$description" id="projectDescription" label="Project description" placeholder="Add a project description..." min-height="7rem" class="min-w-0 flex-1" /><button type="button" wire:click="saveField('description')" aria-label="Save project description" class="rounded-lg bg-indigo-600 px-3 py-2 text-white hover:bg-indigo-700">✓</button><button type="button" wire:click="cancelEditingField('description')" aria-label="Cancel project description edit" class="rounded-lg border border-slate-300 px-3 py-2 text-slate-600 dark:border-slate-700 dark:text-slate-300">×</button></div>@error('description')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror</div>@else<button type="button" wire:click="startEditingField('description')" class="group flex min-h-11 w-full items-start justify-between gap-3 rounded-lg px-3 py-2 text-left hover:bg-slate-50 dark:hover:bg-slate-800"><span class="rich-text-output text-sm leading-6 text-slate-700 dark:text-slate-300">{!! \App\Support\RichText::sanitize($this->project->description) ?: 'Add a project description...' !!}</span><span class="shrink-0 text-xs text-indigo-600 opacity-0 group-hover:opacity-100 dark:text-indigo-400">Edit</span></button>@endif</div>
                <div class="grid gap-2 py-4 sm:grid-cols-[10rem_minmax(0,1fr)]"><h3 class="pt-2 text-sm font-medium text-slate-600 dark:text-slate-400">Project status</h3>@if ($editingField === 'status')<div><div class="flex items-start gap-2"><select id="projectStatus" wire:model="status" class="min-w-0 flex-1 rounded-lg border border-indigo-400 bg-white px-3 py-2.5 text-sm dark:bg-slate-950 dark:text-white"><option value="active">Active</option><option value="archived">Archived</option></select><button type="button" wire:click="saveField('status')" aria-label="Save project status" class="rounded-lg bg-indigo-600 px-3 py-2 text-white hover:bg-indigo-700">✓</button><button type="button" wire:click="cancelEditingField('status')" aria-label="Cancel project status edit" class="rounded-lg border border-slate-300 px-3 py-2 text-slate-600 dark:border-slate-700 dark:text-slate-300">×</button></div>@error('status')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror</div>@else<button type="button" wire:click="startEditingField('status')" class="group flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-left hover:bg-slate-50 dark:hover:bg-slate-800"><span @class(['inline-flex rounded-full px-2.5 py-1 text-xs font-medium capitalize', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' => $this->project->status === 'active', 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => $this->project->status === 'archived'])>{{ $this->project->status }}</span><span class="text-xs text-indigo-600 opacity-0 group-hover:opacity-100 dark:text-indigo-400">Edit</span></button>@endif</div>
                <div class="grid gap-2 py-4 sm:grid-cols-[10rem_minmax(0,1fr)]"><h3 class="pt-2 text-sm font-medium text-slate-600 dark:text-slate-400">Team</h3>@if ($editingField === 'teamId')<div><div class="flex items-start gap-2"><select id="projectTeam" wire:model="teamId" class="min-w-0 flex-1 rounded-lg border border-indigo-400 bg-white px-3 py-2.5 text-sm dark:bg-slate-950 dark:text-white"><option value="">Personal project</option>@foreach (auth()->user()->teams()->orderBy('name')->get() as $team)<option value="{{ $team->id }}">{{ $team->name }}</option>@endforeach</select><button type="button" wire:click="saveField('teamId')" aria-label="Save project team" class="rounded-lg bg-indigo-600 px-3 py-2 text-white hover:bg-indigo-700">✓</button><button type="button" wire:click="cancelEditingField('teamId')" aria-label="Cancel project team edit" class="rounded-lg border border-slate-300 px-3 py-2 text-slate-600 dark:border-slate-700 dark:text-slate-300">×</button></div>@error('teamId')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror</div>@else<button type="button" wire:click="startEditingField('teamId')" class="group flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-left hover:bg-slate-50 dark:hover:bg-slate-800"><span class="text-sm text-slate-800 dark:text-slate-200">{{ $this->project->team?->name ?? 'Personal project' }}</span><span class="text-xs text-indigo-600 opacity-0 group-hover:opacity-100 dark:text-indigo-400">Edit</span></button>@endif</div>
            </div>
        </section>
    @endif

    @if ($projectId)
        @if ($statusMessage)
            <p @if ($statusMessageType === 'error') role="alert" @else role="status" @endif @class(['mx-auto mt-6 max-w-6xl rounded-lg border px-4 py-3 text-sm font-medium', 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300' => $statusMessageType === 'success', 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300' => $statusMessageType === 'error'])>{{ $statusMessage }}</p>
        @endif

        <section class="mx-auto mt-6 grid max-w-6xl gap-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7 lg:grid-cols-2 dark:border-slate-800 dark:bg-slate-900">
            <form wire:submit="saveStatus" class="space-y-4">
                <div><h2 class="text-base font-semibold text-slate-900 dark:text-white">{{ $editingStatusId ? 'Edit workflow column' : 'Add workflow column' }}</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Configure the stages used on this project’s board.</p></div>
                <div><label for="statusName" class="block text-sm font-medium text-slate-900 dark:text-slate-200">Column name</label><input id="statusName" type="text" wire:model="statusName" maxlength="80" placeholder="For example, Code Review" class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">@error('statusName')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <div><label for="statusColor" class="block text-sm font-medium text-slate-900 dark:text-slate-200">Accent color</label><select id="statusColor" wire:model="statusColor" class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">@foreach (['slate', 'blue', 'emerald', 'amber', 'red', 'gray', 'violet', 'rose'] as $color)<option value="{{ $color }}">{{ ucfirst($color) }}</option>@endforeach</select>@error('statusColor')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <div class="flex flex-wrap gap-2"><button type="submit" wire:loading.attr="disabled" wire:target="saveStatus" class="min-h-10 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"><span wire:loading.remove wire:target="saveStatus">{{ $editingStatusId ? 'Save column' : 'Add column' }}</span><span wire:loading wire:target="saveStatus">Saving...</span></button>@if ($editingStatusId)<button type="button" wire:click="cancelStatusEdit" class="min-h-10 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 dark:border-slate-700 dark:text-slate-200">Cancel edit</button>@endif</div>
            </form>
            <div class="space-y-4">
                <div><h2 class="text-base font-semibold text-slate-900 dark:text-white">Workflow order and completion</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Choose the completion stage and arrange board columns.</p></div>
                <form wire:submit="setCompletionStatus" class="flex flex-col gap-2 sm:flex-row"><label class="sr-only" for="completionStatusId">Completion column</label><select id="completionStatusId" wire:model="completionStatusId" class="min-h-10 flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">@foreach ($this->project->statuses as $status)<option value="{{ $status->id }}">{{ $status->name }}</option>@endforeach</select><button type="submit" class="min-h-10 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200">Save completion</button></form>
                <ol class="divide-y divide-slate-100 rounded-lg border border-slate-200 dark:divide-slate-800 dark:border-slate-800">@foreach ($this->project->statuses as $index => $status)<li wire:key="settings-workflow-{{ $status->id }}" class="flex flex-wrap items-center justify-between gap-2 px-3 py-2.5"><div class="flex min-w-0 items-center gap-2"><span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $status->accentHex() }}" aria-hidden="true"></span><span class="truncate text-sm font-medium text-slate-900 dark:text-white">{{ $status->name }}</span>@if ($status->is_complete)<span class="text-xs text-emerald-700 dark:text-emerald-400">Complete</span>@endif</div><div class="flex items-center gap-1"><button type="button" wire:click="moveStatus({{ $status->id }}, 'up')" @disabled($index === 0) aria-label="Move {{ $status->name }} up" class="rounded px-2 py-1 text-sm text-slate-600 disabled:opacity-30 dark:text-slate-300">↑</button><button type="button" wire:click="moveStatus({{ $status->id }}, 'down')" @disabled($loop->last) aria-label="Move {{ $status->name }} down" class="rounded px-2 py-1 text-sm text-slate-600 disabled:opacity-30 dark:text-slate-300">↓</button><button type="button" wire:click="startEditingStatus({{ $status->id }})" class="rounded px-2 py-1 text-xs font-medium text-indigo-600 dark:text-indigo-400">Rename</button><button type="button" wire:click="deleteStatus({{ $status->id }})" wire:confirm="Delete the {{ $status->name }} column? It must be empty first." class="rounded px-2 py-1 text-xs font-medium text-red-600 dark:text-red-400">Delete</button></div></li>@endforeach</ol>
            </div>
        </section>

        <section class="mx-auto mt-6 grid max-w-6xl items-start gap-6 lg:grid-cols-2">
            <div class="h-full rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Project access</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Manage who can work on this project.</p>
                <div class="mt-4 rounded-lg border border-slate-200 p-4 dark:border-slate-800"><div class="flex items-center justify-between gap-3"><div><h3 class="text-sm font-semibold text-slate-900 dark:text-white">Team access</h3><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $this->project->team ? 'All members of this team can access the project.' : 'This project is not part of a team.' }}</p></div>@if ($this->project->team)<a href="{{ route('teams.show', $this->project->team) }}" wire:navigate class="shrink-0 text-sm font-medium text-indigo-600 hover:underline dark:text-indigo-400">{{ $this->project->team->name }}</a>@endif</div></div>
                <div class="mt-3 rounded-lg border border-slate-200 p-4 dark:border-slate-800"><h3 class="text-sm font-semibold text-slate-900 dark:text-white">Project owner</h3><p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ $this->project->user->name }} <span class="text-xs text-slate-500">({{ $this->project->user->email }})</span></p></div>
                <div class="mt-3 rounded-lg border border-slate-200 p-4 dark:border-slate-800"><h3 class="text-sm font-semibold text-slate-900 dark:text-white">Additional project members</h3>
                    @if ($this->project->members->isNotEmpty())
                        <ul class="mt-2 divide-y divide-slate-100 dark:divide-slate-800">@foreach ($this->project->members as $member)<li wire:key="settings-project-member-{{ $member->id }}" class="flex items-center justify-between gap-3 py-2"><span class="min-w-0"><span class="block truncate text-sm font-medium text-slate-800 dark:text-slate-200">{{ $member->name }}</span><span class="block truncate text-xs text-slate-500 dark:text-slate-400">{{ $member->email }} · {{ ucfirst($member->pivot->role) }}</span></span><button type="button" wire:click="removeMember({{ $member->id }})" wire:confirm="Remove {{ $member->name }}’s direct access to this project?" class="shrink-0 rounded px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10">Remove</button></li>@endforeach</ul>
                    @else
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">No additional people have direct project access.</p>
                    @endif
                </div>
                @if ($memberMessage)<p role="status" class="mt-3 text-sm text-emerald-700 dark:text-emerald-300">{{ $memberMessage }}</p>@endif
            </div>
            @can('update', $this->project)
                <aside class="h-full rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 dark:border-slate-800 dark:bg-slate-900"><h2 class="text-base font-semibold text-slate-900 dark:text-white">Share with a person</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Add an existing TaskFlow account to this project only.</p><form wire:submit="addMember" class="mt-4 space-y-3"><div><label for="member-email" class="block text-sm font-medium text-slate-800 dark:text-slate-200">Account email</label><input id="member-email" type="email" wire:model="memberEmail" required placeholder="person@example.com" @error('memberEmail') aria-invalid="true" aria-describedby="project-member-email-error" @enderror class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">@error('memberEmail')<p id="project-member-email-error" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror</div><div><label for="member-role" class="block text-sm font-medium text-slate-800 dark:text-slate-200">Project role</label><select id="member-role" wire:model="memberRole" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"><option value="member">Member</option><option value="admin">Admin</option></select></div><button type="submit" wire:loading.attr="disabled" wire:target="addMember" class="inline-flex min-h-10 w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"><span wire:loading.remove wire:target="addMember">Add project member</span><span wire:loading wire:target="addMember">Adding...</span></button></form></aside>
            @endcan
        </section>
        @can('delete', $this->project)
            <section class="mx-auto mt-6 max-w-6xl rounded-xl border border-red-200 bg-white p-5 shadow-sm sm:p-6 dark:border-red-900/70 dark:bg-slate-900"><div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="text-base font-semibold text-red-700 dark:text-red-300">Danger zone</h2><p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Deleting this project also removes its workflow and tasks.</p></div><button type="button" wire:click="deleteProject" wire:confirm="Delete {{ $this->project->name }}? This cannot be undone." wire:loading.attr="disabled" wire:target="deleteProject" class="inline-flex min-h-10 shrink-0 items-center justify-center rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 disabled:opacity-50 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950/40"><span wire:loading.remove wire:target="deleteProject">Delete project</span><span wire:loading wire:target="deleteProject">Deleting...</span></button></div></section>
        @endcan
    @endif
</main>
