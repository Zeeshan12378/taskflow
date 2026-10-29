<?php

use App\Models\Task;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Notifications · TaskFlow')] class extends Component
{
    #[Computed]
    public function notifications()
    {
        $notifications = auth()->user()->notifications()->latest()->limit(100)->get();
        $taskIds = $notifications->pluck('data.task_id')->filter()->unique()->all();

        $accessibleTaskIds = Task::query()
            ->whereKey($taskIds)
            ->whereHas('project', fn ($query) => $query->accessibleTo(auth()->user()))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $notifications->filter(fn (DatabaseNotification $notification) => in_array(
            (int) ($notification->data['task_id'] ?? 0),
            $accessibleTaskIds,
            true,
        ))->values();
    }

    #[Computed]
    public function unreadCount(): int
    {
        return $this->notifications->whereNull('read_at')->count();
    }

    public function markAsRead(string $notificationId): void
    {
        $notification = auth()->user()->notifications()->findOrFail($notificationId);
        $this->ensureTaskIsAccessible($notification);
        $notification->markAsRead();
        unset($this->notifications);
    }

    public function markAllAsRead(): void
    {
        $this->notifications
            ->whereNull('read_at')
            ->each(fn (DatabaseNotification $notification) => $notification->markAsRead());

        unset($this->notifications);
    }

    public function openTask(string $notificationId): void
    {
        $notification = auth()->user()->notifications()->findOrFail($notificationId);
        $this->ensureTaskIsAccessible($notification);
        $notification->markAsRead();

        $this->redirectRoute('projects.tasks.edit', [
            'project' => $notification->data['project_id'],
            'task' => $notification->data['task_id'],
        ]);
    }

    private function ensureTaskIsAccessible(DatabaseNotification $notification): void
    {
        $task = Task::query()
            ->whereKey($notification->data['task_id'] ?? 0)
            ->whereHas('project', fn ($query) => $query->accessibleTo(auth()->user()))
            ->first();

        abort_if($task === null, 404);
    }
};
?>

<main class="mx-auto w-full max-w-4xl flex-1 px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-medium text-indigo-600 dark:text-indigo-400">Your workspace</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Notifications</h1>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $this->unreadCount }} unread notifications</p>
        </div>
        @if ($this->unreadCount > 0)
            <button type="button" wire:click="markAllAsRead" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Mark all as read</button>
        @endif
    </div>

    @if ($this->notifications->isNotEmpty())
        <ol class="mt-6 divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
            @foreach ($this->notifications as $notification)
                <li wire:key="notification-{{ $notification->id }}" @class(['flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between sm:px-5', 'bg-indigo-50/60 dark:bg-indigo-500/5' => $notification->unread()])>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $notification->data['actor_name'] ?? 'A teammate' }} {{ $notification->data['message'] ?? 'updated a task' }}</p>
                        <p class="mt-1 truncate text-sm text-slate-700 dark:text-slate-300">{{ $notification->data['task_title'] ?? 'Task' }} <span class="text-slate-500 dark:text-slate-400">· {{ $notification->data['project_name'] ?? 'Project' }}</span></p>
                        <time class="mt-1 block text-xs text-slate-500 dark:text-slate-400" datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->diffForHumans() }}</time>
                    </div>
                    <div class="flex shrink-0 items-center gap-3">
                        @if ($notification->unread())
                            <button type="button" wire:click="markAsRead('{{ $notification->id }}')" class="text-sm font-medium text-slate-600 hover:underline dark:text-slate-300">Mark read</button>
                        @endif
                        <button type="button" wire:click="openTask('{{ $notification->id }}')" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Open task</button>
                    </div>
                </li>
            @endforeach
        </ol>
    @else
        <section class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">You’re all caught up</h2>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Task assignments and activity involving you will appear here.</p>
        </section>
    @endif
</main>
