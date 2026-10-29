<?php

use App\Models\Team;
use App\Support\RichText;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Teams · TaskFlow')] class extends Component
{
    public string $name = '';

    public string $description = '';

    public ?string $statusMessage = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Team::class);
    }

    #[Computed]
    public function teams()
    {
        return auth()->user()->teams()
            ->withCount(['members', 'projects'])
            ->orderBy('name')
            ->get();
    }

    public function createTeam(): void
    {
        $this->authorize('create', Team::class);
        $this->description = RichText::sanitize($this->description);
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:10000'],
        ]);

        if (mb_strlen(RichText::plainText($validated['description'] ?? '')) > 2000) {
            $this->addError('description', 'The description may not be greater than 2000 characters.');

            return;
        }

        $team = DB::transaction(function () use ($validated): Team {
            $team = Team::query()->create($validated + ['owner_id' => auth()->id()]);
            $team->members()->attach(auth()->id(), ['role' => 'owner']);

            return $team;
        });

        $this->redirectRoute('teams.show', $team);
    }
};
?>

<main class="mx-auto w-full max-w-7xl flex-1 px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
    <div class="grid gap-7 lg:grid-cols-[minmax(0,1.35fr)_minmax(18rem,.65fr)]">
        <section>
            <p class="text-sm font-medium text-indigo-600 dark:text-indigo-400">Collaboration</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Your teams</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 dark:text-slate-400">Teams bring people together. Projects in a team are visible to all of its members.</p>

            @if ($this->teams->isNotEmpty())
                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    @foreach ($this->teams as $team)
                        <a href="{{ route('teams.show', $team) }}" wire:navigate class="group rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-indigo-700">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2m16 0v-2a4 4 0 0 0-3-3.87M14 3.13a4 4 0 0 1 0 7.75M14 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z"/></svg></div>
                                <span class="text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-indigo-600" aria-hidden="true">→</span>
                            </div>
                            <h2 class="mt-4 text-lg font-semibold text-slate-900 dark:text-white">{{ $team->name }}</h2>
                            <div class="rich-text-output mt-1 line-clamp-2 min-h-10 text-sm leading-5 text-slate-500 dark:text-slate-400">{!! \App\Support\RichText::sanitize($team->description) ?: 'A shared space for projects and tasks.' !!}</div>
                            <p class="mt-4 text-xs font-medium text-slate-500 dark:text-slate-400">{{ $team->members_count }} {{ \Illuminate\Support\Str::plural('member', $team->members_count) }} · {{ $team->projects_count }} {{ \Illuminate\Support\Str::plural('project', $team->projects_count) }}</p>
                        </a>
                    @endforeach
                </div>
            @else
                <section class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">No teams yet</h2>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Create a team to share projects and collaborate with other TaskFlow members.</p>
                </section>
            @endif
        </section>

        <aside class="h-fit rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Create a team</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">You’ll be its owner and can add existing TaskFlow users.</p>
            <form wire:submit="createTeam" class="mt-5 space-y-4">
                <div>
                    <label for="team-name" class="block text-sm font-medium text-slate-900 dark:text-slate-200">Team name</label>
                    <input id="team-name" type="text" wire:model="name" maxlength="120" required placeholder="Product team" @error('name') aria-invalid="true" aria-describedby="team-name-error" @enderror class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none placeholder:text-slate-400 focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    @error('name')<p id="team-name-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-900 dark:text-slate-200">Description <span class="font-normal text-slate-500">(optional)</span></label>
                    <x-rich-text-editor model="description" :value="$description" id="team-description" label="Team description" placeholder="What is this team about?" min-height="7rem" />
                    @error('description')<p id="team-description-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <button type="submit" wire:loading.attr="disabled" wire:target="createTeam" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 disabled:opacity-60"><span wire:loading.remove wire:target="createTeam">Create team</span><span wire:loading wire:target="createTeam">Creating team...</span></button>
            </form>
        </aside>
    </div>
</main>
