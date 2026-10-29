<?php

use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Projects · TaskFlow')] class extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 'all')]
    public string $statusFilter = 'all';

    public ?string $statusMessage = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Project::class);
        $this->statusMessage = session()->get('status');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'all';
        $this->resetPage();
    }

    #[Computed]
    public function projects(): LengthAwarePaginator
    {
        return Project::query()->accessibleTo(auth()->user())->with('team')
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $term = '%'.trim($this->search).'%';

                $query->where(function (Builder $query) use ($term): void {
                    $query->where('name', 'like', $term)
                        ->orWhere('description', 'like', $term);
                });
            })
            ->when(in_array($this->statusFilter, ['active', 'archived'], true), fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->latest('updated_at')
            ->paginate(9);
    }

    public function deleteProject(int $projectId): void
    {
        $project = Project::query()->accessibleTo(auth()->user())->findOrFail($projectId);
        $this->authorize('delete', $project);

        $project->delete();
        $this->statusMessage = 'Project deleted.';
        unset($this->projects);

        if ($this->projects->isEmpty() && $this->getPage() > 1) {
            $this->previousPage();
            unset($this->projects);
        }
    }
};
?>

<main class="mx-auto w-full max-w-7xl flex-1 px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-medium text-indigo-600 dark:text-indigo-400">Your workspace</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Projects</h1>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Keep related work organized in focused spaces.</p>
        </div>

        <a href="{{ route('projects.create') }}" wire:navigate class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-950">
            <span aria-hidden="true" class="text-lg leading-none">+</span>
            New project
        </a>
    </div>

    @if ($statusMessage)
        <p role="status" class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">{{ $statusMessage }}</p>
    @endif

    <div class="mt-7 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-slate-500 dark:text-slate-400">
            {{ $this->projects->total() }} {{ \Illuminate\Support\Str::plural('project', $this->projects->total()) }}
        </p>

        <div class="grid gap-3 sm:w-auto sm:grid-cols-[minmax(13rem,1fr)_10rem]">
            <label class="relative block w-full">
                <span class="sr-only">Search projects</span>
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path stroke-linecap="round" d="m16 16 4 4" /></svg>
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search projects..." class="w-full rounded-lg border border-slate-300 bg-white py-2.5 pl-9 pr-3 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500">
            </label>
            <label>
                <span class="sr-only">Filter by project status</span>
                <select wire:model.live="statusFilter" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 shadow-sm outline-none transition focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    <option value="all">All projects</option>
                    <option value="active">Active</option>
                    <option value="archived">Archived</option>
                </select>
            </label>
        </div>
    </div>

    <p wire:loading.flex wire:target="search,statusFilter" role="status" class="mt-4 items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
        <span class="h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-indigo-600 dark:border-slate-700 dark:border-t-indigo-400" aria-hidden="true"></span>
        Updating projects...
    </p>

    <div wire:loading.class="opacity-50" wire:loading.attr="aria-busy" wire:target="search,statusFilter" class="transition-opacity">
    @if ($this->projects->isNotEmpty())
        <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->projects as $project)
                <article wire:key="project-{{ $project->id }}" class="flex min-h-56 flex-col rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-indigo-200 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-indigo-900">
                    <div class="flex items-start justify-between gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400" aria-hidden="true">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h6l2 2h8.5v9.5a2 2 0 0 1-2 2h-14a2 2 0 0 1-2-2v-9.5a2 2 0 0 1 2-2Z" /></svg>
                        </span>
                        <span @class([
                            'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium capitalize',
                            'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' => $project->status === 'active',
                            'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => $project->status === 'archived',
                        ])>{{ $project->status }}</span>
                    </div>

                    <h2 class="mt-4 text-lg font-semibold text-slate-900 dark:text-white">
                        <a href="{{ route('projects.show', $project) }}" wire:navigate class="rounded-sm hover:text-indigo-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:hover:text-indigo-400">{{ $project->name }}</a>
                    </h2>
                    <p class="mt-1 line-clamp-2 min-h-10 text-sm leading-5 text-slate-500 dark:text-slate-400">{{ \App\Support\RichText::plainText($project->description) ?: 'No description added.' }}</p>
                    <p class="mt-2 text-xs font-medium text-slate-500 dark:text-slate-400">{{ $project->team?->name ?? 'Personal project' }}</p>

                    <div class="mt-auto flex items-center justify-between gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
                        <span class="text-xs text-slate-500 dark:text-slate-400">Updated {{ $project->updated_at->diffForHumans() }}</span>
                        <div class="flex items-center gap-3">
                            <a href="{{ route('projects.edit', $project) }}" wire:navigate class="rounded text-sm font-medium text-indigo-600 hover:text-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:text-indigo-400">Edit</a>
                            <button type="button" wire:click="deleteProject({{ $project->id }})" wire:confirm="Delete {{ $project->name }}? This cannot be undone." wire:loading.attr="disabled" wire:target="deleteProject({{ $project->id }})" class="rounded text-sm font-medium text-red-600 hover:text-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 disabled:opacity-50 dark:text-red-400"><span wire:loading.remove wire:target="deleteProject({{ $project->id }})">Delete</span><span wire:loading wire:target="deleteProject({{ $project->id }})">Deleting...</span></button>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-6">{{ $this->projects->links() }}</div>
    @elseif (trim($search) !== '' || $statusFilter !== 'all')
        <section class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">No matching projects</h2>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Try another search term.</p>
            <button type="button" wire:click="clearFilters" class="mt-4 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Clear filters</button>
        </section>
    @else
        <section class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900 sm:px-10">
            <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400" aria-hidden="true">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h6l2 2h8.5v9.5a2 2 0 0 1-2 2h-14a2 2 0 0 1-2-2v-9.5a2 2 0 0 1 2-2Z" /></svg>
            </span>
            <h2 class="mt-4 text-lg font-semibold text-slate-900 dark:text-white">No projects yet</h2>
            <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500 dark:text-slate-400">Create your first project to give related tasks and ideas a shared home.</p>
            <a href="{{ route('projects.create') }}" wire:navigate class="mt-5 inline-flex min-h-10 items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900">Create a project</a>
        </section>
    @endif
    </div>
</main>
